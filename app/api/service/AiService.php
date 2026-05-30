<?php declare(strict_types=1);

namespace app\api\service;

use app\model\AiPromoted;
use app\model\Match1;
use app\model\MatchView;
use app\model\Promoted;
use app\model\RockBallOdd;
use Carbon\Carbon;
use Illuminate\Database\Query\JoinClause;
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
     * 第二版获取要预测的比赛列表，带皇冠盘口
     * @return array
     */
    public function getPreparingMatchesV2(): array
    {
        $matches = MatchView::query()
            ->join('crown_odd_record', function (JoinClause $join) {
                $join->on('crown_odd_record.match_id', '=', 'v_match.id')
                    ->where('crown_odd_record.show_type', '=', 'today')
                    ->where('crown_odd_record.is_last', '=', 1)
                    ->where('crown_odd_record.created_at', '<', MatchView::raw("CURRENT_TIMESTAMP - interval '5 minutes'"));
            })
            ->where('v_match.match_time', '>', MatchView::raw('CURRENT_TIMESTAMP'))
            ->where('v_match.match_time', '<', MatchView::raw("CURRENT_TIMESTAMP + interval '2 hours'"))
            ->orderBy('v_match.crown_hot_at', 'ASC')
            ->get([
                'v_match.id',
                'v_match.tournament_id',
                'v_match.tournament_name',
                'v_match.match_time',
                'v_match.team1_id',
                'v_match.team1_name',
                'v_match.team1_i18n_name',
                'v_match.team2_id',
                'v_match.team2_name',
                'v_match.team2_i18n_name',
                'crown_odd_record.odd_data',
                'crown_odd_record.created_at AS odds_updated_at',
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

            //解析盘口数据
            $odds = [];
            $rawOdds = json_decode($match['odd_data'], true);
            foreach ($rawOdds as $odd) {
                if ($odd['variety'] !== 'goal') continue;
                switch ($odd['type']) {
                    case 'r':
                        //全场让球
                        $odds['ah'][] = [
                            'primary' => false,
                            'condition' => $odd['condition'],
                            'ah1' => $odd['value_h'],
                            'ah2' => $odd['value_c'],
                        ];
                        break;
                    case 'hr':
                        //上半场让球
                        $odds['ah_period1'][] = [
                            'primary' => false,
                            'condition' => $odd['condition'],
                            'ah1' => $odd['value_h'],
                            'ah2' => $odd['value_c'],
                        ];
                        break;
                    case 'ou':
                        //全场大小球
                        $odds['ou'][] = [
                            'primary' => false,
                            'condition' => $odd['condition'],
                            'under' => $odd['value_h'],
                            'over' => $odd['value_c'],
                        ];
                        break;
                    case 'hou':
                        //上半场大小球
                        $odds['ou_period1'][] = [
                            'primary' => false,
                            'condition' => $odd['condition'],
                            'under' => $odd['value_h'],
                            'over' => $odd['value_c'],
                        ];
                        break;
                    case "h":
                        //全场胜平负
                        $odds['win'][] = [
                            'primary' => false,
                            'win1' => $odd['value_h'],
                            'win2' => $odd['value_c'],
                            'draw' => $odd['value_n'],
                        ];
                        break;
                    case "hm":
                        //上半场胜平负
                        $odds['win_period1'][] = [
                            'primary' => false,
                            'win1' => $odd['value_h'],
                            'win2' => $odd['value_c'],
                            'draw' => $odd['value_n'],
                        ];
                        break;
                    case "ts":
                        //全场双方进球
                        $odds['btts'][] = [
                            'primary' => false,
                            'btts_yes' => $odd['value_h'],
                            'btts_no' => $odd['value_c'],
                        ];
                        break;
                    case "hts":
                        //上半场双方进球
                        $odds['btts_period1'][] = [
                            'primary' => false,
                            'btts_yes' => $odd['value_h'],
                            'btts_no' => $odd['value_c'],
                        ];
                        break;
                }
            }

            //标记主盘
            foreach ($odds as $type => $odd) {
                $odds[$type][0]['primary'] = true;
            }

            $match['odd_data'] = $odds;

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
        $channel = "ai2";

        $exists = Promoted::query()
            ->where('match_id', '=', $data['match_id'])
            ->where('period', '=', $data['period'])
            ->where('odd_type', '=', $oddType)
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