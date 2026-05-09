<?php declare(strict_types=1);

namespace app\api\service;

use app\model\AiPromoted;
use app\model\Match1;
use app\model\MatchView;
use app\model\Promoted;
use app\model\RockBallOdd;
use Carbon\Carbon;
use support\exception\BusinessError;

/**
 * 与AI相关的业务逻辑
 */
class AiService
{
    /**
     * 获取需要预测的比赛
     * @return array
     */
    public function getPreparingMatches(): array
    {
        $matches = MatchView::query()
            ->where('match_time', '>', MatchView::raw('CURRENT_TIMESTAMP'))
            ->where('match_time', '<', MatchView::raw("CURRENT_TIMESTAMP + interval '12 hours'"))
            ->orderBy('crown_hot_at', 'ASC')
            ->get([
                'id',
                'tournament_id',
                'tournament_name',
                'match_time',
                'team1_id',
                'team1_name',
                'team1_i18n_name',
                'team2_id',
                'team2_name',
                'team2_i18n_name',
            ])
            ->toArray();

        return array_map(function (array $match) {
            if (!empty($match['team1_i18n_name'])) {
                $team1_i18n_name = json_decode($match['team1_i18n_name'], true);
                $match['team1_name_en'] = $team1_i18n_name['en'] ?? '';
            }
            if (!empty($match['team2_i18n_name'])) {
                $team2_i18n_name = json_decode($match['team2_i18n_name'], true);
                $match['team2_name_en'] = $team2_i18n_name['en'] ?? '';
            }
            unset($match['team1_i18n_name'], $match['team2_i18n_name']);
            return $match;
        }, $matches);
    }

    /**
     * 创建AI推荐结果
     * @param array $data
     * @return array{
     *     match_id: int,
     *     type: string,
     *     condition: string,
     *     period: string
     * }
     * @throws \AMQPException
     */
    public function createPromotion(array $data): array
    {
        //检查比赛id
        $match = Match1::query()
            ->where('id', '=', $data['match_id'])
            ->first(['id', 'crown_match_id', 'match_time']);
        if (!$match) {
            throw new BusinessError('比赛不存在');
        }

        //计算盘口类型
        $oddType = get_odd_identification($data['type']);

        //检查是否存在相同的推荐
        $exists = AiPromoted::query()
            ->where('match_id', '=', $data['match_id'])
            ->where('period', '=', $data['period'])
            ->where('odd_type', '=', $oddType)
            ->exists();
        if ($exists) {
            throw new BusinessError('已经存在相同类型的推荐');
        }

        //特殊逻辑，AI推过来的上半场大0.5，进滚球5预测中
        if ($data['period'] === 'period1' && $data['type'] === 'over' && bccomp($data['condition'], '0.5', 2) === 0) {
            //先查询盘口是否存在
            $exists = RockBallOdd::query()
                ->where('match_id', '=', $data['match_id'])
                ->where('channel', '=', 'rockball5')
                ->where('period', '=', 'period1')
                ->exists();
            if ($exists) {
                throw new BusinessError('已经存在相同类型的推荐');
            }

            //写入记录
            $id = AiPromoted::insertGetId([
                'match_id' => $data['match_id'],
                'odd_type' => $oddType,
                'type' => $data['type'],
                'condition' => $data['condition'],
                'crown_match_id' => $match->crown_match_id,
                'period' => $data['period'],
            ]);

            //插入滚球5预测中
            RockBallOdd::insert([
                'match_id' => $data['match_id'],
                'crown_match_id' => $data['crown_match_id'],
                'source_variety' => 'goal',
                'source_period' => 'period1',
                'source_condition' => '0.5',
                'source_type' => 'over',
                'source_value' => '0',
                'variety' => 'goal',
                'period' => 'period1',
                'type' => 'over',
                'condition' => '0.5',
                'value' => '1.88',
                'is_open' => 1,
                'source_channel' => 'ai_promoted',
                'source_id' => $id,
                'channel' => 'rockball5',
            ]);

            return [
                'id' => $id,
            ];
        }

        //检查频道是否已有相同类型的推荐
        $channel = "ai_$oddType";

        $exists = Promoted::query()
            ->where('match_id', '=', $data['match_id'])
            ->where('period', '=', $data['period'])
            ->where('channel', '=', $channel)
            ->exists();
        if ($exists) {
            throw new BusinessError('已经存在相同类型的推荐');
        }

        //写入记录
        $id = AiPromoted::insertGetId([
            'match_id' => $data['match_id'],
            'odd_type' => $oddType,
            'type' => $data['type'],
            'condition' => $data['condition'],
            'crown_match_id' => $match->crown_match_id,
            'period' => $data['period'],
        ]);

        //抛到皇冠盘口检测队列
        rabbitmq_publish('crown_odd', json_encode([
            'next' => 'ai_promoted',
            'crown_match_id' => $match->crown_match_id,
            'extra' => [
                'id' => $id,
            ],
        ]), [], ['x-max-priority' => 20]);

        return [
            'id' => $id,
        ];
    }
}