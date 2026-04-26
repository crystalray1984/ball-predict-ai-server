<?php declare(strict_types=1);

namespace app\api\service;

use app\model\OddMansion;
use app\model\Promoted;
use app\model\PromotedView;
use app\model\RockBallOdd;
use app\model\UserMarked;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class DataService
{
    protected function formatPreparingList(array $list): array
    {
        $output = [];
        foreach ($list as $row) {
            $key = $row['tournament_id'] . ':' . Carbon::parse($row['match_time'])->getTimestamp();
            if (!empty($output[$key])) {
                $output[$key]['matches'][] = [
                    'id' => $row['id'],
                    'team1' => $row['team1_name'],
                    'team2' => $row['team2_name'],
                ];
            } else {
                $output[$key] = [
                    'id' => $row['tournament_id'],
                    'match_time' => Carbon::parse($row['match_time'])->toISOString(),
                    'tournament' => [
                        'id' => $row['tournament_id'],
                        'name' => $row['tournament_name'],
                    ],
                    'matches' => [
                        [
                            'id' => $row['id'],
                            'team1' => $row['team1_name'],
                            'team2' => $row['team2_name'],
                        ]
                    ],
                ];
            }
        }

        return array_values($output);
    }

    /**
     * 获取滚球准备中的数据
     * @param string $channel 滚球频道
     * @return array
     */
    public function rockballPreparing(string $channel): array
    {
        $list = RockBallOdd::query()
            ->join('v_match', "v_match.id", '=', "rockball_odd.match_id")
            ->where('rockball_odd.status', '=', '')
            ->where('rockball_odd.is_open', '=', 1)
            ->where('rockball_odd.channel', '=', $channel)
            //比赛时间判断
            ->where(function (Builder $where) {
                //上半场盘口判断条件
                $where->where(function (Builder $subWhere) {
                    $subWhere->where('rockball_odd.period', '=', 'period1')
                        ->where('v_match.has_period1_score', '=', 0)
                        ->where('v_match.match_time', '>', $subWhere->raw("CURRENT_TIMESTAMP - interval '60 minutes'"));
                })
                    //全场盘口判断条件
                    ->orWhere(function (Builder $subWhere) {
                        $subWhere->where('rockball_odd.period', '=', 'regularTime')
                            ->where('v_match.has_score', '=', 0)
                            ->where('v_match.match_time', '>', $subWhere->raw("CURRENT_TIMESTAMP - interval '2 hours'"));
                    });
            })
            ->orderBy('v_match.match_time')
            ->orderBy('v_match.tournament_id')
            ->orderBy('v_match.id')
            ->distinct()
            ->get([
                'v_match.id',
                'v_match.team1_name',
                'v_match.team2_name',
                'v_match.tournament_id',
                'v_match.tournament_name',
                'v_match.match_time'
            ])
            ->toArray();

        return $this->formatPreparingList($list);
    }

    /**
     * 获取mansion准备中的数据
     * @return array
     */
    public function mansionPreparing(): array
    {
        ['final_check_time' => $finalCheckTime] = get_settings(['final_check_time']);

        $list = OddMansion::query()
            ->join('v_match', "v_match.id", '=', "odd_mansion.match_id")
            ->where('odd_mansion.status', '=', 'ready')
            ->where('v_match.match_time', '>', OddMansion::raw("CURRENT_TIMESTAMP + interval '$finalCheckTime minutes'"))
            ->where('v_match.tournament_is_open', '=', 1)
            ->orderBy('v_match.match_time')
            ->orderBy('v_match.tournament_id')
            ->distinct()
            ->get([
                'v_match.id',
                'v_match.team1_name',
                'v_match.team2_name',
                'v_match.tournament_id',
                'v_match.tournament_name',
                'v_match.match_time'
            ])
            ->toArray();

        return $this->formatPreparingList($list);
    }

    /**
     * 获取统计数据
     * @param string[] $channels 推荐频道的标识列表
     * @param Carbon|string|null $start 开始时间（含）
     * @param Carbon|string|null $end 结束时间（不含）
     * @return array
     */
    public function summary(array $channels, Carbon|string|null $start = null, Carbon|string|null $end = null): array
    {
        $query = Promoted::query()
            ->whereIn('promoted.channel', $channels)
            ->where('promoted.is_valid', '=', 1);

        if (!empty($start) || !empty($end)) {
            $query->join('match', "match.id", '=', "promoted.match_id");
            if (!empty($start)) {
                $query->where('match.match_time', '>=', crown_time($start)->toISOString());
            }
            if (!empty($end)) {
                $query->where('match.match_time', '<', crown_time($end)->toISOString());
            }
        }

        $total = $query->count();

        $data = $query->clone()->whereNotNull('promoted.result')
            ->groupBy('promoted.result')
            ->select([
                'promoted.result',
            ])
            ->selectRaw('count(*) as count')
            ->get()
            ->toArray();

        $profit = $query->clone()->whereNotNull('promoted.result_profit')
            ->selectRaw('SUM(promoted.result_profit) AS profit')
            ->value('profit') ?? 0;

        return [
            ...get_summary_data($data),
            'total' => $total,
            'profit' => $profit,
        ];
    }

    /**
     * 获取推荐数据
     * @param array $channels 推荐频道的标识列表
     * @param Carbon|string|null $matchTimeStart 比赛时间的开始日期
     * @param Carbon|string|null $expireTime 用户的VIP过期时间
     * @return array
     */
    public function promoted(array $channels, int $userId = 0, Carbon|string|null $matchTimeStart = null, Carbon|string|null $expireTime = null): array
    {
        $query = PromotedView::query()
            ->whereIn('channel', $channels)
            ->where('is_valid', '=', 1);

        if (!empty($matchTimeStart)) {
            $query->where('match_time', '>=', crown_time($matchTimeStart)->subDay()->toISOString());
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

        $marked = [];
        if (!empty($userId) && !empty($list)) {
            $marked = UserMarked::query()
                ->where('user_id', '=', $userId)
                ->whereIn('promote_id', array_column($list, 'id'))
                ->pluck('promote_id')
                ->toArray();
        }

        return array_map(function (array $row) use ($marked) {
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
                'marked' => in_array($row['id'], $marked),
            ];
        }, $list);
    }

    /**
     * 基于皇冠的日期划分，返回推荐数据
     * @param array $channels
     * @param int $userId
     * @param Carbon|string|null $expireTime
     * @return array
     */
    public function promotedByCrownDate(array $channels, int $userId = 0, Carbon|string|null $expireTime = null): array
    {
        $maxDate = PromotedView::query()
            ->whereIn('channel', $channels)
            ->where('is_valid', '=', 1)
            ->orderBy('match_time', 'DESC')
            ->value('match_time');
        $minDate = crown_date($maxDate)->subDays(6)->toISOString();

        $query = PromotedView::query()
            ->whereIn('channel', $channels)
            ->where('is_valid', '=', 1)
            ->where('match_time', '>=', $minDate);

        if (empty($expireTime)) {
            //未登录，只能查看已经有赛果的推荐
            $query->whereNotNull('result');
        } else {
            //根据用户的VIP过期时间，筛选过期之前产生的推荐
            $query->where(function ($where) use ($expireTime) {
                $where->whereNotNull('result')
                    ->orWhere('created_at', '<', Carbon::parse($expireTime)->toISOString());
            });
        }

        $list = $query
            ->orderBy('id', 'DESC')
            ->orderBy('match_time', 'DESC')
            ->orderBy('match_id')
            ->get()
            ->toArray();

        $marked = [];
        if (!empty($userId) && !empty($list)) {
            $marked = UserMarked::query()
                ->where('user_id', '=', $userId)
                ->whereIn('promote_id', array_column($list, 'id'))
                ->pluck('promote_id')
                ->toArray();
        }

        return array_map(function (array $row) use ($marked) {
            return [
                'id' => $row['id'],
                'variety' => $row['variety'],
                'period' => $row['period'],
                'type' => $row['type'],
                'condition' => $row['condition'],
                'value' => $row['value'],
                'match_time' => Carbon::parse($row['match_time'])->toISOString(),
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
                    'score1' => $row['score1'],
                    'score2' => $row['score2'],
                    'result_value' => $row['result_value'],
                    'result_profit' => $row['result_profit'],
                ] : null,
                'marked' => in_array($row['id'], $marked),
                'crown_match_id' => $row['crown_match_id'],
                'channel' => $row['channel'],
                'updated_at' => Carbon::parse($row['updated_at'])->toISOString(),
                'is_rockball' => $row['is_rockball'],
            ];
        }, $list);
    }

    /**
     * 获取全部的推荐数据
     * @param array $channels 频道列表
     * @param int $userId 用户id
     * @return array
     */
    public function allData(array $channels, int $userId = 0): array
    {
        //查询起点
        $startDate = crown_date()->subDays(6);

        //首先获取一下每个频道的最后一场推荐场次的时间
        $maxChannelTimes = Promoted::query()
            ->join('match', 'match.id', '=', 'promoted.match_id')
            ->where('promoted.is_valid', '=', 1)
            ->where('match.match_time', '>=', $startDate->toISOString())
            ->whereIn('promoted.channel', $channels)
            ->groupBy('promoted.channel')
            ->select('promoted.channel')
            ->selectRaw('MAX(match.match_time) as match_time')
            ->get()
            ->toArray();

        $maxChannelTimes = array_column($maxChannelTimes, 'match_time', 'channel');
        /** @var array<string, Carbon> $minChannelTimes */
        $minChannelTimes = array_map(fn($time) => crown_date($time)->subDays(6), $maxChannelTimes);

        //然后查询所有推荐场次数据
        $list = PromotedView::query()
            ->whereIn('channel', $channels)
            ->where('is_valid', '=', 1)
            ->where('match_time', '>=', $startDate->toISOString())
            ->orderBy('id', 'DESC')
            ->get()
            ->toArray();

        //整理数据
        $list = array_filter($list, function (array $row) use (&$minChannelTimes, &$startDate) {
            $channel = $row['channel'];
            if (!empty($minChannelTimes[$channel])) {
                return crown_date($row['match_time'])->unix() >= $minChannelTimes[$channel]->unix();
            } else {
                return crown_date($row['match_time'])->unix() >= $startDate->unix();
            }
        });

        $marked = [];
        if (!empty($userId) && !empty($list)) {
            $marked = UserMarked::query()
                ->where('user_id', '=', $userId)
                ->whereIn('promote_id', array_column($list, 'id'))
                ->pluck('promote_id')
                ->toArray();
        }

        //返回数据
        return array_map(function (array $row) use ($marked) {
            return [
                'id' => $row['id'],
                'variety' => $row['variety'],
                'period' => $row['period'],
                'type' => $row['type'],
                'condition' => $row['condition'],
                'value' => $row['value'],
                'match_time' => Carbon::parse($row['match_time'])->toISOString(),
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
                    'score1' => $row['score1'],
                    'score2' => $row['score2'],
                    'result_value' => $row['result_value'],
                    'result_profit' => $row['result_profit'],
                ] : null,
                'marked' => in_array($row['id'], $marked),
                'crown_match_id' => $row['crown_match_id'],
                'channel' => $row['channel'],
                'updated_at' => Carbon::parse($row['updated_at'])->toISOString(),
                'is_rockball' => $row['is_rockball'],
            ];
        }, $list);
    }

    /**
     * 获取增量推荐数据
     * @param array $channels 频道列表
     * @param string $lastUpdated 上次的数据更新时间
     * @param int $userId 用户id
     * @return array
     */
    public function incrementData(array $channels, string $lastUpdated, int $userId = 0): array
    {
        //查询增量更新数据
        $list = PromotedView::query()
            ->whereIn('channel', $channels)
            ->where('is_valid', '=', 1)
            ->where('updated_at', '>=', $lastUpdated)
            ->orderBy('id', 'DESC')
            ->get()
            ->toArray();

        $marked = [];
        if (!empty($userId) && !empty($list)) {
            $marked = UserMarked::query()
                ->where('user_id', '=', $userId)
                ->whereIn('promote_id', array_column($list, 'id'))
                ->pluck('promote_id')
                ->toArray();
        }

        //返回数据
        return array_map(function (array $row) use ($marked) {
            return [
                'id' => $row['id'],
                'variety' => $row['variety'],
                'period' => $row['period'],
                'type' => $row['type'],
                'condition' => $row['condition'],
                'value' => $row['value'],
                'match_time' => Carbon::parse($row['match_time'])->toISOString(),
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
                    'score1' => $row['score1'],
                    'score2' => $row['score2'],
                    'result_value' => $row['result_value'],
                    'result_profit' => $row['result_profit'],
                ] : null,
                'marked' => in_array($row['id'], $marked),
                'crown_match_id' => $row['crown_match_id'],
                'channel' => $row['channel'],
                'updated_at' => Carbon::parse($row['updated_at'])->toISOString(),
                'is_rockball' => $row['is_rockball'],
            ];
        }, $list);
    }
}