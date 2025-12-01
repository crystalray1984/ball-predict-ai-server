<?php declare(strict_types=1);

namespace app\api\service;

use app\model\OddMansion;
use app\model\Promoted;
use app\model\PromotedView;
use app\model\RockBallOdd;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class DataService
{
    /**
     * 获取滚球准备中的数据
     * @return array
     */
    public function rockballPreparing(): array
    {
        return RockBallOdd::query()
            ->join('v_match', "v_match.id", '=', "rockball_odd.match_id")
            ->where('rockball_odd.status', '=', 'ready')
            ->where('rockball_odd.is_open', '=', 1)
            ->where('v_match.tournament_is_rockball_open', '=', 1)
            //比赛时间判断
            ->where(function (Builder $where) {
                //上半场盘口判断条件
                $where->where(function (Builder $subWhere) {
                    $subWhere->where('rockball_odd.period', '', 'period1')
                        ->where('v_match.has_period1_score', '=', 0)
                        ->where('v_match.match_time', '>', $subWhere->raw("CURRENT_TIMESTAMP - interval '60 minutes'"));
                })
                    //全场盘口判断条件
                    ->orWhere(function (Builder $subWhere) {
                        $subWhere->where('rockball_odd.period', '', 'regularTime')
                            ->where('v_match.has_score', '=', 0)
                            ->where('v_match.match_time', '>', $subWhere->raw("CURRENT_TIMESTAMP - interval '2 hours'"));
                    });
            })
            ->orderBy('v_match.match_time')
            ->orderBy('v_match.tournament_id')
            ->distinct()
            ->get([
                'v_match.tournament_id AS id',
                'v_match.tournament_name AS name',
                'v_match.match_time'
            ])
            ->toArray();
    }

    /**
     * 获取mansion准备中的数据
     * @return array
     */
    public function mansionPreparing(): array
    {
        ['final_check_time' => $finalCheckTime] = get_settings(['final_check_time']);

        return OddMansion::query()
            ->join('v_match', "v_match.id", '=', "odd_mansion.match_id")
            ->where('odd_mansion.status', '=', 'ready')
            ->where('v_match.match_time', '<', OddMansion::raw("CURRENT_TIMESTAMP - interval '$finalCheckTime minutes'"))
            ->where('v_match.tournament_is_open', '=', 1)
            ->orderBy('v_match.match_time')
            ->orderBy('v_match.tournament_id')
            ->distinct()
            ->get([
                'v_match.tournament_id AS id',
                'v_match.tournament_name AS name',
                'v_match.match_time'
            ])
            ->toArray();
    }

    /**
     * 获取统计数据
     * @param string[] $channels 推荐频道的标识列表
     * @return array
     */
    public function summary(array $channels): array
    {
        $query = Promoted::query()
            ->whereIn('channel', $channels)
            ->where('is_valid', '=', 1);

        $total = $query->count();

        $data = $query->whereNotNull('result')
            ->groupBy('result')
            ->select([
                'result',
            ])
            ->selectRaw('count(*) as count')
            ->get()
            ->toArray();

        if (empty($data)) {
            //没有数据
            return [
                'total' => $total,
                'win' => 0,
                'loss' => 0,
                'draw' => 0,
                'win_rate' => 0,
            ];
        }

        $data = array_column($data, 'count', 'result');

        $result = [
            'total' => $total,
            'win' => $data[1] ?? 0,
            'loss' => $data[-1] ?? 0,
            'draw' => $data[0] ?? 0,
        ];

        $valid = $result['win'] + $result['loss'];
        if ($valid === 0) {
            $result['win_rate'] = 0;
        } else {
            $result['win_rate'] = round($result['win'] * 100 / $valid, 1);
        }

        return $result;
    }

    /**
     * 获取推荐数据
     * @param array $channels 推荐频道的标识列表
     * @param Carbon|string|null $matchTimeStart 比赛时间的开始日期
     * @param Carbon|string|null $expireTime 用户的VIP过期时间
     * @return array
     */
    public function promoted(array $channels, Carbon|string|null $matchTimeStart = null, Carbon|string|null $expireTime = null): array
    {
        $query = PromotedView::query()
            ->whereIn('channel', $channels)
            ->where('is_valid', '=', 1);

        if (!empty($matchTimeStart)) {
            $query->where('match_time', '>=', Carbon::parse($matchTimeStart)->toISOString());
        }

        if (empty($expireTime)) {
            //未登录，只能查看已经有赛果的推荐
            $query->whereNotNull('result');
        } else {
            //根据用户的VIP过期时间，筛选过期之前产生的推荐
            $query->where('created_at', '<', Carbon::parse($expireTime)->toISOString());
        }

        $list = $query
            ->orderBy('id', 'DESC')
            ->orderBy('match_time', 'DESC')
            ->orderBy('match_id')
            ->get([
                'id',
                'variety',
                'period',
                'type',
                'condition',
                'value',
                'result',
                'score',
                'match_time',
                'tournament_id',
                'tournament_name',
                'team1_id',
                'team1_name',
                'team2_id',
                'team2_name',
            ])
            ->toArray();

        return array_map(function (array $row) {
            return [
                'id' => $row['id'],
                'variety' => $row['variety'],
                'period' => $row['period'],
                'type' => $row['type'],
                'condition' => $row['condition'],
                'value' => $row['value'],
                'match_time' => $row['match_time'],
                'tournament' => [
                    'id' => $row['tournament_id'],
                    'name' => $row['tournament_name'],
                ],
                'team1' => [
                    'id' => $row['team1_id'],
                    'name' => $row['team1_name'],
                ],
                'team2' => [
                    'id' => $row['team2_id'],
                    'name' => $row['team2_name'],
                ],
                'result' => isset($row['result']) ? [
                    'result' => $row['result'],
                    'score' => $row['score'],
                ] : null,
            ];
        }, $list);
    }
}