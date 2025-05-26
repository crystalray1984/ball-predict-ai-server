<?php declare(strict_types=1);

namespace scripts;

use Carbon\Carbon;
use Illuminate\Database\Connection;
use support\Db;

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../support/bootstrap.php";

/**
 * 执行从v1数据库迁移数据的任务
 */
class Migration
{
    protected Connection $to;

    protected Connection $from;

    /**
     * @param bool $clean 是否清理原数据
     */
    public function __construct(protected bool $clean = true)
    {
        //打开数据连接
        $this->to = Db::connection();
        $this->from = Db::connection('org');
    }

    /**
     * 迁移常规数据表（新旧表结构相同）
     * @param string $to
     * @param string|null $from
     * @param string $incrementKey 自增字段
     * @return void
     */
    protected function copyTable(string $to, ?string $from = null, string $incrementKey = 'id'): void
    {
        if (empty($from)) {
            $from = $to;
        }

        if (!empty($incrementKey)) {
            //以自增的方式迁移
            $lastId = 0;
            while (true) {
                echo "迁移 $from -> $to last_id=$lastId\n";
                $list = $this->from->table($from)
                    ->where($incrementKey, '>', $lastId)
                    ->orderBy($incrementKey)
                    ->limit(500)
                    ->get()
                    ->map(fn($row) => (array)$row)
                    ->toArray();
                if (empty($list)) {
                    break;
                }
                $this->to->table($to)->insert($list);
                $lastId = last($list)[$incrementKey];
            }
            $lastId++;
            $this->to->select("SELECT setval('public.{$to}_{$incrementKey}_seq', $lastId, false)");
        } else {
            //常规迁移
            $list = $this->from->table($from)
                ->get()
                ->map(fn($row) => (array)$row)
                ->toArray();
            if (!empty($list)) {
                $this->to->table($to)->insert($list);
            }
            echo "迁移 $from -> $to\n";
        }
    }

    /**
     * 截断数据表
     * @param string $table
     * @param bool $force
     * @return void
     */
    protected function truncate(string $table, bool $force = false): void
    {
        if (!$this->clean && !$force) return;
        $this->to->table($table)->truncate();
        echo "清理数据 $table\n";
    }

    /**
     * 开始迁移
     * @return void
     */
    public function run(): void
    {
        //管理员表
        $this->truncate('admin');
        $this->copyTable('admin');

        //配置表
//        $this->truncate('setting');
//        $this->copyTable('setting', incrementKey: '');

        //联赛表
        $this->truncate('tournament');
        $this->copyTable('tournament');

        //队伍表
        $this->truncate('team');
        $this->copyTeamTable();

        //比赛表
        $this->truncate('match');
        $this->copyMatchTable();

        //盘口表
        $this->truncate('odd');
        $this->copyOddTable();

        //推荐盘口表
        $this->truncate('promoted_odd');
        $this->copyPromotedOddTable();

        //用户表
        $this->truncate('user');
        $this->truncate('user_connect');
        $this->copyUserTable();
        $this->copyLuffaUserTable();

        //订单表
        $this->truncate('order');
        $this->copyOrderTable();
    }

    /**
     * 迁移队伍表数据
     * @return void
     */
    protected function copyTeamTable(): void
    {
        $from = 'team';
        $to = 'team';
        $lastId = 0;
        while (true) {
            echo "迁移 $from -> $to last_id=$lastId\n";
            $list = $this->from->table($from)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(500)
                ->get()
                ->map(fn($row) => (array)$row)
                ->toArray();
            if (empty($list)) {
                break;
            }

            //数据处理
            foreach ($list as $k => $row) {
                unset($list[$k]['titan007_team_id'], $list[$k]['titan007_team_id']);
            }

            $this->to->table($to)->insert($list);
            $lastId = last($list)['id'];
        }
        $lastId++;
        $this->to->select("SELECT setval('public.team_id_seq', $lastId, false)");
    }

    /**
     * 迁移比赛表数据
     * @return void
     */
    protected function copyMatchTable(): void
    {
        $from = 'match';
        $to = 'match';
        $lastId = 0;
        while (true) {
            echo "迁移 $from -> $to last_id=$lastId\n";
            $list = $this->from->table($from)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(500)
                ->get()
                ->map(fn($row) => (array)$row)
                ->toArray();
            if (empty($list)) {
                break;
            }

            //数据处理
            foreach ($list as $k => $row) {
                unset($list[$k]['titan007_match_id']);
                $list[$k]['has_score'] = $row['has_score'] ? 1 : 0;
                $list[$k]['has_period1_score'] = $row['has_period1_score'] ? 1 : 0;
            }

            $this->to->table($to)->insert($list);
            $lastId = last($list)['id'];
        }
        $lastId++;
        $this->to->select("SELECT setval('public.match_id_seq', $lastId, false)");
    }

    /**
     * 迁移盘口表数据
     * @return void
     */
    protected function copyOddTable(): void
    {
        $from = 'odd';
        $to = 'odd';
        $lastId = 0;
        while (true) {
            echo "迁移 $from -> $to last_id=$lastId\n";
            $list = $this->from->table($from)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(500)
                ->get()
                ->map(fn($row) => (array)$row)
                ->toArray();
            if (empty($list)) {
                break;
            }

            //数据处理
            foreach ($list as $k => $row) {
                unset($list[$k]['surebet_updated_at'], $list[$k]['crown_updated_at']);
                if ($row['status'] === 'ready') {
                    $list[$k]['ready_at'] = $row['surebet_updated_at'];
                    $list[$k]['final_at'] = null;
                } else if (!empty($row['status'])) {
                    $list[$k]['final_at'] = $list[$k]['ready_at'] = $row['surebet_updated_at'];
                } else {
                    $list[$k]['final_at'] = $list[$k]['ready_at'] = null;
                }
            }

            $this->to->table($to)->insert($list);
            $lastId = last($list)['id'];
        }
        $lastId++;
        $this->to->select("SELECT setval('public.odd_id_seq', $lastId, false)");
    }

    /**
     * 迁移推荐盘口表数据
     * @return void
     */
    protected function copyPromotedOddTable(): void
    {
        $from = 'promoted_odd';
        $to = 'promoted_odd';
        $lastId = 0;
        while (true) {
            echo "迁移 $from -> $to last_id=$lastId\n";
            $list = $this->from->table($from)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(500)
                ->get()
                ->map(fn($row) => (array)$row)
                ->toArray();
            if (empty($list)) {
                break;
            }

            //数据处理
            foreach ($list as $k => $row) {
                unset($list[$k]['special'], $list[$k]['special_odd']);
                $list[$k]['is_valid'] = $row['is_valid'] ? 1 : 0;
                $list[$k]['back'] = $row['back'] ? 1 : 0;
                $list[$k]['final_rule'] = $row['special'] ? 'crown_special' : 'crown';
            }

            $this->to->table($to)->insert($list);
            $lastId = last($list)['id'];
        }
        $lastId++;
        $this->to->select("SELECT setval('public.promoted_odd_id_seq', $lastId, false)");
    }

    /**
     * 迁移用户表
     * @return void
     */
    protected function copyUserTable(): void
    {
        $from = 'user';
        $to = 'user';
        $lastId = 0;
        while (true) {
            echo "迁移 $from -> $to last_id=$lastId\n";
            $list = $this->from->table($from)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(500)
                ->get()
                ->map(fn($row) => (array)$row)
                ->toArray();
            if (empty($list)) {
                break;
            }

            //数据处理
            foreach ($list as $k => $row) {
                $list[$k]['reg_source'] =
                    str_starts_with($row['username'], 'luffa:')
                        ? 'luffa'
                        : '';
                unset(
                    $list[$k]['username'],
                    $list[$k]['password'],
                    $list[$k]['note'],
                    $list[$k]['agent1_id'],
                    $list[$k]['agent2_id'],
                    $list[$k]['email'],
                );
            }

            $this->to->table($to)->insert($list);
            $lastId = last($list)['id'];
        }
        $lastId++;
        $this->to->select("SELECT setval('public.user_id_seq', $lastId, false)");
    }

    /**
     * 迁移luffa用户
     * @return void
     */
    protected function copyLuffaUserTable(): void
    {
        $from = 'luffa_user';
        $to = 'user_connect';

        echo "迁移 $from -> $to\n";
        $list = $this->from->table($from)
            ->get()
            ->map(fn($row) => (array)$row)
            ->toArray();

        $list = array_map(function (array $row) {
            return [
                'user_id' => $row['user_id'],
                'platform' => 'luffa',
                'platform_id' => $row['network'],
                'account' => $row['uid'],
                'extra' => json_enc([
                    'nickname' => $row['nickname'],
                    'uid' => $row['uid'],
                    'account' => $row['address'],
                    'address' => $row['address'],
                    'network' => $row['network'],
                    'avatar' => $row['avatar'],
                    'cid' => $row['cid'],
                ]),
            ];
        }, $list);

        $this->to->table($to)->insert($list);
    }

    /**
     * 迁移订单表
     * @return void
     */
    protected function copyOrderTable(): void
    {
        $from = 'order';
        $to = 'order';
        $lastId = 0;
        while (true) {
            echo "迁移 $from -> $to last_id=$lastId\n";
            $list = $this->from->table($from)
                ->where('id', '>', $lastId)
                ->where('status', '=', 1)
                ->orderBy('id')
                ->limit(500)
                ->get()
                ->map(fn($row) => (array)$row)
                ->toArray();
            if (empty($list)) {
                break;
            }

            //数据处理
            $insert_list = array_map(function (array $row) {
                $order_time = Carbon::parse($row['created_at']);
                $order_date = (int)$order_time->format('Ymd');
                $order_number = $order_date . str_pad((string)$row['id'], 6, '0', STR_PAD_LEFT);


                return [
                    'order_date' => $order_date,
                    'order_number' => $order_number,
                    'user_id' => $row['user_id'],
                    'type' => 'vip',
                    'amount' => $row['amount'],
                    'currency' => $row['currency'],
                    'status' => 'paid',
                    'extra' => $row['extra'],
                    'channel_type' => $row['channel'],
                    'channel_id' => $row['channel_id'],
                    'channel_order_no' => $row['channel_trade_no'],
                    'channel_order_info' => $row['channel_order_info'],
                    'paid_at' => $row['payment_at'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                ];
            }, $list);

            $this->to->table($to)->insert($insert_list);
            $lastId = last($list)['id'];
        }
    }
}

(new Migration())->run();