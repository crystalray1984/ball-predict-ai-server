<?php declare(strict_types=1);

namespace scripts;

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
        $this->truncate('setting');
        $this->copyTable('setting', incrementKey: '');

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
    }
}

(new Migration())->run();