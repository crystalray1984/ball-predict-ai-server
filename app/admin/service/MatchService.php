<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\Match1;
use app\model\PromotedOdd;
use Carbon\Carbon;
use support\Db;
use Throwable;

/**
 * 与比赛相关的业务逻辑
 */
class MatchService
{
    /**
     * 获取需要获取赛果的比赛列表
     * @return array
     */
    public function getRequireScoreMatches(): array
    {
        return Match1::query()
            ->join('team AS team1', 'team1.id', '=', 'match.team1_id')
            ->join('team AS team2', 'team2.id', '=', 'match.team2_id')
            ->join('odd', 'odd.match_id', '=', 'match.id')
            ->where('match.match_time', '<=', Carbon::now()->subMinutes(120)->toISOString())
            ->where('match.match_time', '>=', Carbon::now()->subDays(2)->toISOString())
            ->where('match.has_score', '=', false)
            ->distinct()
            ->get([
                'match.id',
                'match.match_time',
                'team1.name AS team1',
                'team2.name AS team2',
            ])
            ->toArray();
    }

    /**
     * 设置赛果
     * @param array $data 赛果数据
     * @return void
     */
    public function setMatchScore(array $data): void
    {
        //查询比赛所有的已推荐盘口
        $odds = PromotedOdd::query()
            ->where('match_id', '=', $data['match_id'])
            ->get([
                'id',
                'variety',
                'period',
                'type',
                'condition'
            ])
            ->toArray();

        Db::beginTransaction();
        try {
            //设置比赛的结果
            Match1::query()
                ->where('id', '=', $data['match_id'])
                ->update([
                    'score1' => $data['score1'],
                    'score2' => $data['score2'],
                    'corner1' => $data['corner1'],
                    'corner2' => $data['corner2'],
                    'score1_period1' => $data['score1_period1'],
                    'score2_period1' => $data['score2_period1'],
                    'corner1_period1' => $data['corner1'],
                    'corner2_period1' => $data['corner2_period1'],
                    'has_score' => true,
                ]);

            //设置推荐盘口的结果
            if (!empty($odds)) {
                foreach ($odds as $odd) {
                    $result = get_odd_score($data, $odd);
                    PromotedOdd::query()
                        ->where('id', '=', $odd['id'])
                        ->update([
                            'score' => $result['score'],
                            'result' => $result['result'],
                        ]);
                }
            }

            Db::commit();
        } catch (Throwable $exception) {
            Db::rollBack();
            throw $exception;
        }
    }

    /**
     * 批量设置赛果
     * @param array $list 赛果列表
     * @return void
     */
    public function multiSetMatchScore(array $list): void
    {
        if (empty($list)) return;
        Db::beginTransaction();
        try {
            foreach ($list as $data) {
                $this->setMatchScore($data);
            }
            Db::commit();
        } catch (Throwable $exception) {
            Db::rollBack();
            throw $exception;
        }
    }
}