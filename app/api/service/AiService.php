<?php declare(strict_types=1);

namespace app\api\service;

use app\model\AiPromoted;
use app\model\Match1;
use app\model\MatchView;
use app\model\Promoted;
use Carbon\Carbon;
use support\exception\BusinessError;

/**
 * 与AI相关的业务逻辑
 */
class AiService
{
    /**
     * 获取需要预测的比赛
     * @param int $next
     * @return array
     */
    public function getPreparingMatches(int $next = 0): array
    {
        $matches = MatchView::query()
            ->whereNotNull('crown_hot_at')
            ->when(!empty($next), fn($query) => $query->where('crown_hot_at', '>', Carbon::createFromTimestampMs($next)->toISOString()))
            ->where('match_time', '<', MatchView::raw('CURRENT_TIMESTAMP'))
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
        ]));

        return [
            'id' => $id,
        ];
    }
}