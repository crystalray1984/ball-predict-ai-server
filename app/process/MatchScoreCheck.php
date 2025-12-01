<?php declare(strict_types=1);

namespace app\process;

use app\model\NotificationLog;
use app\model\PromotedView;
use Carbon\Carbon;
use support\Log;
use support\Luffa;
use Throwable;
use Workerman\Timer;

/**
 * 检查比赛结果未能正确获取的定时任务
 */
class MatchScoreCheck
{
    public function onWorkerStart(): void
    {
        Timer::add(60, function () {
            $this->check();
        });
    }

    protected function check(): void
    {
        //获取已经推荐的，且到了时间未能获取到结果的推荐盘口
        $period1Time = Carbon::now()->subMinutes(60)->toISOString();
        $regularTime = Carbon::now()->subHours(2)->toISOString();
        $list = PromotedView::query()
            ->where('is_valid', '=', 1)
            ->whereNull('result')
            ->where(function ($query) use ($period1Time, $regularTime) {
                $query->where(function ($period1Query) use ($period1Time) {
                    $period1Query->where('period', '=', 'period1')
                        ->where('match_time', '<', $period1Time);
                })
                    ->orWhere(function ($regularQuery) use ($regularTime) {
                        $regularQuery->where('period', '=', 'regularTime')
                            ->where('match_time', '<', $regularTime);
                    });
            })
            ->get([
                'match_id',
                'period',
                'match_time',
                'tournament_name',
                'team1_name',
                'team2_name',
            ])
            ->toArray();

        foreach ($list as $row) {
            //检查通知是否已经发送
            $keyword = "score_error:{$row['match_id']}:{$row['period']}";
            $exists = NotificationLog::query()
                ->where('keyword', '=', $keyword)
                ->exists();
            if ($exists) {
                continue;
            }

            NotificationLog::insert(['keyword' => $keyword]);

            $match_time = Carbon::parse($row['match_time'])->tz('Asia/Shanghai')->format('m/d H:i');
            $period = $row['period'] === 'period1' ? '半场' : '全场';

            //发送通知
            $content = <<<EOF
**赛果获取异常通知 V3**
赛事 {$row['tournament_name']}
时间 $match_time
主队 {$row['team1_name']}
客队 {$row['team2_name']}
时段 $period
EOF;
            try {
                Luffa::sendNotification($content);
            } catch (Throwable $e) {
                Log::error($e);
            }
        }
    }
}