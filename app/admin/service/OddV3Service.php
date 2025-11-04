<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\CrownOdd;
use app\model\MatchView;
use app\model\Odd;
use app\model\PromotedOddChannel2;
use Carbon\Carbon;
use Illuminate\Database\Query\JoinClause;

class OddV3Service
{
    /**
     * 获取进行中或者有盘口数据的比赛列表
     * @param array $params
     * @return array
     */
    public function getMatchList(array $params): array
    {
        $query = MatchView::query();

        if (!empty($params['start_date'])) {
            $query->where(
                'v_match.match_time',
                '>=',
                Carbon::parse($params['start_date'])->toISOString(),
            );
        }
        if (!empty($params['end_date'])) {
            $query->where(
                'v_match.match_time',
                '<',
                Carbon::parse($params['end_date'])
                    ->addDays()
                    ->toISOString(),
            );
        }

        if ($params['ready_status'] === 0) {
            $query->whereNotIn('id', Odd::query()->where('status', '=', 'ready')->select(['match_id']));
        } else {
            if ($params['ready_status'] === 1) {
                $query->whereIn('id', Odd::query()->where('status', '=', 'ready')->select(['match_id']));
            }

            if (isset($params['promoted'])) {
                switch ($params['promoted']) {
                    case 1:
                        $query->whereIn('v_match.id', PromotedOddChannel2::query()->select('match_id'));
                        break;
                    case 0:
                        $query->whereNotIn('v_match.id', PromotedOddChannel2::query()->select('match_id'));
                        break;
                }
            }
        }

        $matches = $query
            ->orderBy('v_match.match_time', 'DESC')
            ->orderBy('v_match.id', 'DESC')
            ->get(['v_match.*'])
            ->toArray();

        if (empty($matches)) {
            //没有比赛
            return [];
        }

        $matchIds = array_column($matches, 'id');

        //读取触发这些比赛的盘口
        $odds = Odd::query()
            ->joinSub(
                Odd::query()
                    ->where('status', '=', 'ready')
                    ->whereIn('match_id', $matchIds)
                    ->select(['id'])
                    ->selectRaw('ROW_NUMBER() OVER (PARTITION BY match_id ORDER BY ready_at ASC) AS row_no'),
                'a',
                function (JoinClause $join) {
                    $join->on('odd.id', '=', 'a.id')
                        ->where('a.row_no', '=', 1);
                }
            )
            ->select([
                'odd.id',
                'odd.match_id',
                'odd.variety',
                'odd.period',
                'odd.type',
                'odd.condition',
                'odd.surebet_value',
                'odd.crown_value',
                'odd.ready_at'
            ])
            ->get()
            ->toArray();
        //按比赛id组合
        $odds = array_column($odds, null, 'match_id');

        //读取这些比赛的推荐数据
        $_promoted = PromotedOddChannel2::query()
            ->whereIn('match_id', $matchIds)
            ->orderBy('id', 'DESC')
            ->get()
            ->toArray();

        //按比赛id组合数据
        $promoted = [];
        foreach ($_promoted as $row) {
            if (!empty($row['start_odd_data'])) {
                $row['start_odd_data'] = json_decode($row['start_odd_data'], true);
            }
            if (!empty($row['end_odd_data'])) {
                $row['end_odd_data'] = json_decode($row['end_odd_data'], true);
            }
            $promoted[$row['match_id']][] = $row;
        }

        //输出数据
        return array_map(function (array $row) use ($odds, $promoted) {
            return [
                'id' => $row['id'],
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
                'promoted' => $promoted[$row['id']] ?? [],
                'surebet' => $odds[$row['id']] ?? null
            ];
        }, $matches);
    }

    /**
     * 获取指定比赛的盘口列表
     * @param int $matchId
     * @param string $type
     * @return array
     */
    public function getOddRecords(int $matchId, string $type): array
    {
        return CrownOdd::query()
            ->where('match_id', '=', $matchId)
            ->where('type', '=', $type)
            ->where('variety', '=', 'goal')
            ->where('period', '=', 'regularTime')
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();
    }
}