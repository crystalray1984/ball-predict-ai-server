<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\Match1;
use app\model\PromotedOdd;
use support\Db;
use Throwable;

/**
 * 与比赛相关的业务逻辑
 */
class MatchService
{
    /**
     * 设置赛果
     * @param int $match_id 比赛id
     * @param array $data 赛果数据
     * @return void
     */
    public function setMatchScore(int $match_id, array $data): void
    {
        //查询比赛所有的已推荐盘口
        $odds = PromotedOdd::query()
            ->where('match_id', '=', $match_id)
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
                ->where('id', '=', $match_id)
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
}