<?php declare(strict_types=1);

namespace tasks;

use app\model\User;
use app\model\UserCommission;
use app\model\UserCommissionRecord;
use Carbon\Carbon;
use support\Db;
use support\Log;
use Throwable;
use WebmanTech\CrontabTask\BaseTask;

/**
 * 佣金月结定时任务
 */
class CommissionSettlementTask extends BaseTask
{

    /**
     * @return void
     */
    public function handle(): void
    {
        $logger = Log::channel('CommissionSettlement');
        $logger->info('每月佣金结算');

        //查询截止本月1日0点前待结算佣金
        $endTime = Carbon::today()->startOfMonth();

        $rows = UserCommission::query()
            ->where('created_at', '<', $endTime->toISOString())
            ->whereNull('settled_at')
            ->groupBy('user_id')
            ->select(['user_id'])
            ->selectRaw('SUM(commission) AS commission')
            ->get()
            ->toArray();

        foreach ($rows as $row) {
            $logger->info('结算用户id=' . $row['user_id'] . ' 结算金额=' . $row['commission']);
            if (bccomp($row['commission'], '0', 2) <= 0) {
                continue;
            }

            Db::beginTransaction();
            try {
                //修改用户的可提现余额
                User::query()
                    ->where('id', '=', $row['user_id'])
                    ->update([
                        'commission' => User::raw('commission + ' . $row['commission']),
                    ]);

                //查询用户现在的佣金余额
                $commission = User::query()
                    ->where('id', '=', $row['user_id'])
                    ->value('commission');

                //记录佣金变更记录
                UserCommissionRecord::insert([
                    'user_id' => $row['user_id'],
                    'type' => 'settlement',
                    'amount' => $row['commission'],
                    'amount_after' => $commission,
                ]);

                //把之前的佣金记录标记为已结算
                UserCommission::query()
                    ->where('created_at', '<', $endTime->toISOString())
                    ->where('user_id', '=', $row['user_id'])
                    ->whereNull('settled_at')
                    ->update([
                        'settled_at' => UserCommission::raw('CURRENT_TIMESTAMP'),
                    ]);

                Db::commit();
            } catch (Throwable $e) {
                Db::rollBack();
                $logger->error($e);
            }
        }

        $logger->info('每月佣金结算完成');
    }
}