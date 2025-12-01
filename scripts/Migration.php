<?php declare(strict_types=1);

namespace scripts;

use app\model\LabelPromoted;
use app\model\Odd;
use app\model\Promoted;
use app\model\PromotedOdd;
use app\model\PromotedOddMansion;
use app\model\RockBallPromoted;
use app\model\SurebetV2Promoted;
use Carbon\Carbon;
use support\Db;

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../support/bootstrap.php";

/**
 * 从v3到v4数据表迁移数据
 */
class Migration
{
    public function run(): void
    {
        //读取基础数据
        $promotes = PromotedOdd::query()
            ->orderBy('created_at')
            ->select([
                '*'
            ])
            ->selectRaw("'generic' AS channel")
            ->get()
            ->toArray();
        array_walk($promotes, function (array &$item) {
            $item['_time'] = Carbon::parse($item['created_at'])->getTimestampMs();
        });

        $mansionPromotes = PromotedOddMansion::query()
            ->orderBy('created_at')
            ->select([
                '*'
            ])
            ->selectRaw("'mansion' AS channel")
            ->get()
            ->toArray();
        array_walk($mansionPromotes, function (array &$item) {
            $item['_time'] = Carbon::parse($item['created_at'])->getTimestampMs();
        });

        $rockballPromotes = RockBallPromoted::query()
            ->orderBy('created_at')
            ->select([
                '*'
            ])
            ->selectRaw("'rockball' AS channel")
            ->get()
            ->toArray();
        array_walk($rockballPromotes, function (array &$item) {
            $item['_time'] = Carbon::parse($item['created_at'])->getTimestampMs();
        });

        $optimizedPromotes = SurebetV2Promoted::query()
            ->orderBy('created_at')
            ->select([
                '*'
            ])
            ->selectRaw("'optimized' AS channel")
            ->get()
            ->toArray();
        array_walk($optimizedPromotes, function (array &$item) {
            $item['_time'] = Carbon::parse($item['created_at'])->getTimestampMs();
        });

        $merged = array_merge($promotes, $mansionPromotes, $rockballPromotes, $optimizedPromotes);
        usort($merged, fn(array $a, array $b) => $a['_time'] - $b['_time']);

        $updates = [];

        //生成数据
        foreach ($merged as $origin) {
            echo $origin['channel'] . ' ' . $origin['id'] . ' ' . Carbon::parse($origin['created_at'])->toDateTimeLocalString() . PHP_EOL;

            //新表的字段
            $row = [
                'match_id' => $origin['match_id'],
                'source_type' => '',
                'source_id' => 0,
                'channel' => $origin['channel'],
                'is_valid' => $origin['is_valid'],
                'skip' => $origin['skip'] ?? '',
                'week_day' => $origin['week_day'] ?? 0,
                'week_id' => $origin['week_id'] ?? 0,
                'variety' => $origin['variety'],
                'period' => $origin['period'],
                'type' => $origin['type'],
                'odd_type' => $origin['odd_type'],
                'condition' => $origin['condition'],
                'value' => $origin['value'],
                'result' => $origin['result'],
                'score' => $origin['score'],
                'score1' => $origin['score1'],
                'score2' => $origin['score2'],
                'extra' => null,
                'created_at' => Carbon::parse($origin['created_at'])->toISOString(),
            ];

            if ($origin['channel'] === 'generic') {
                //总台
                $row['source_type'] = $origin['source'];
                $row['source_id'] = $origin['source_id'];
                if (!empty($origin['start_odd_data']) && !empty($origin['end_odd_data'])) {
                    $row['extra'] = json_enc([
                        'start_odd_data' => json_decode($origin['start_odd_data'], true),
                        'end_odd_data' => json_decode($origin['end_odd_data'], true),
                    ]);
                }

                $id = Promoted::insertGetId($row);
                if ($origin['source'] === 'manual_promote_odd') {
                    $updates['manual_promote_odd'][] = [
                        'where' => ['id' => $origin['source_id']],
                        'update' => ['promoted_odd_id' => $id],
                    ];
                }
            } else if ($origin['channel'] === 'mansion') {
                //mansion对比
                $row['source_type'] = 'mansion';
                $row['source_id'] = $origin['odd_mansion_id'];
                $row['extra'] = json_enc([
                    'odd_id' => $origin['odd_id'],
                    'mansion_id' => $origin['odd_mansion_id'],
                    'value0' => $origin['value0'],
                    'value1' => $origin['value1'],
                    'back' => $origin['back'],
                ]);
                Promoted::insert($row);
            } else if ($origin['channel'] === 'rockball') {
                //滚球
                $row['source_type'] = 'rockball';
                $row['source_id'] = $origin['odd_id'];
                if (!$row['is_valid']) {
                    $row['skip'] = 'manual_close';
                }
                Promoted::insert($row);
            } else if ($origin['channel'] === 'optimized') {
                //融合优化
                $row['source_type'] = 'optimized';

                //从原始盘口表中寻找surebet盘口
                [$type, $condition] = get_reverse_odd($origin['type'], $origin['condition']);
                $oddRow = Odd::query()
                    ->where('match_id', '=', $origin['match_id'])
                    ->where('variety', '=', $origin['variety'])
                    ->where('period', '=', $origin['period'])
                    ->where('type', '=', $type)
                    ->where('condition', '=', $condition)
                    ->first(['id']);
                $row['source_id'] = $oddRow?->id ?? 0;

                $promoted = Promoted::query()
                    ->where('match_id', '=', $origin['match_id'])
                    ->where('variety', '=', $origin['variety'])
                    ->where('period', '=', $origin['period'])
                    ->where('type', '=', $origin['type'])
                    ->where('condition', '=', $origin['condition'])
                    ->where('channel', '=', 'generic')
                    ->first(['id']);

                $row['extra'] = json_enc([
                    'back' => 1 - $origin['back'],
                    'promoted_id' => $promoted?->id ?? 0,
                ]);

                $id = Promoted::insertGetId($row);

                //查询标签表中需要更新的记录
                $labelPromoted = LabelPromoted::query()
                    ->where('promote_id', '=', $origin['id'])
                    ->pluck('id')
                    ->toArray();
                if (!empty($labelPromoted)) {
                    $updates['label_promoted'][] = [
                        'where' => [['id', 'in', $labelPromoted]],
                        'update' => ['promote_id' => $id],
                    ];
                }
            }
        }

        var_export($updates);

        //更新其他表
        foreach ($updates as $table => $list) {
            foreach ($list as $item) {
                Db::table($table)
                    ->where($item['where'])
                    ->update($item['update']);
            }
        }
    }
}

(new Migration())->run();