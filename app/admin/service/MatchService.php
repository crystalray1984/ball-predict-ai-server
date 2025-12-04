<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\Match1;
use app\model\MatchView;
use app\model\Promoted;
use app\model\Tournament;
use app\model\TournamentLabel;
use Carbon\Carbon;
use support\Db;
use support\exception\BusinessError;
use support\Redis;
use Throwable;

/**
 * 与比赛相关的业务逻辑
 */
class MatchService
{
    /**
     * 设置赛果
     * @param array $data 赛果数据
     * @return void
     */
    public function setMatchScore(array $data): void
    {
        //首先读取所有需要更新的推荐盘口
        $query = Promoted::query()
            ->where('match_id', '=', $data['match_id']);
        if ($data['period1']) {
            $query->where('period', '=', 'period1');
        }
        $promotes = $query
            ->get([
                'id',
                'variety',
                'period',
                'type',
                'condition',
            ])
            ->toArray();

        //然后进行赛果计算
        $updates = [];

        foreach ($promotes as $promoted) {
            //角球无数据的判断
            if ($promoted['variety'] === 'corner') {
                if ($promoted['period'] === 'period1') {
                    if (!is_int($data['corner1_period1']) || !is_int($data['corner2_period1'])) {
                        continue;
                    }
                } else {
                    if (!is_int($data['corner1']) || !is_int($data['corner2'])) {
                        continue;
                    }
                }
            }

            //计算第一赛果
            $update = [];
            $result = get_odd_score($data, $promoted);
            $update['score'] = $result['score'];
            $update['score1'] = $result['score1'];
            $update['score2'] = $result['score2'];
            $update['result'] = $result['result'];

            $updates[$promoted['id']] = $update;
        }

        Db::beginTransaction();
        try {
            //首先设置赛果
            if ($data['period1']) {
                Match1::query()
                    ->where('id', '=', $data['match_id'])
                    ->update([
                        'score1_period1' => $data['score1_period1'],
                        'score2_period1' => $data['score2_period1'],
                        'corner1_period1' => $data['corner1_period1'],
                        'corner2_period1' => $data['corner2_period1'],
                        'has_period1_score' => true,
                    ]);
            } else {
                Match1::query()
                    ->where('id', '=', $data['match_id'])
                    ->update([
                        'score1' => $data['score1'],
                        'score2' => $data['score2'],
                        'corner1' => $data['corner1'],
                        'corner2' => $data['corner2'],
                        'score1_period1' => $data['score1_period1'],
                        'score2_period1' => $data['score2_period1'],
                        'corner1_period1' => $data['corner1_period1'],
                        'corner2_period1' => $data['corner2_period1'],
                        'has_score' => true,
                        'has_period1_score' => true,
                    ]);
            }

            //然后设置推荐盘口的结果
            foreach ($updates as $id => $update) {
                Promoted::query()
                    ->where('id', '=', $id)
                    ->update($update);
            }

            Db::commit();
        } catch (Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }

    /**
     * 获取赛事列表
     * @param array $params
     * @return array
     */
    public function getTournamentList(array $params): array
    {
        $query = Tournament::query()
            ->leftJoin('tournament_label', 'tournament_label.id', '=', 'tournament.label_id');
        if (!empty($params['name'])) {
            $query->where('tournament.name', 'like', '%' . $params['name'] . '%');
        }

        if (isset($params['label_id'])) {
            $query->where('tournament.label_id', '=', $params['label_id']);
        }

        if (!empty($params['order_field']) && !empty($params['order_order'])) {
            $query->orderBy('tournament.' . $params['order_field'], $params['order_order']);
        } else {
            $query->orderBy('tournament.name');
        }

        return $query
            ->get([
                'tournament.id',
                'tournament.name',
                'tournament.is_open',
                'tournament.is_rockball_open',
                'tournament.label_id',
                'tournament_label.title AS label_title',
            ])
            ->toArray();
    }

    /**
     * 获取比赛列表
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
                crown_time($params['start_date'])->toISOString(),
            );
        }
        if (!empty($params['end_date'])) {
            $query->where(
                'v_match.match_time',
                '<',
                crown_time($params['end_date'])
                    ->addDays()
                    ->toISOString(),
            );
        }
        if (!empty($params['tournament_id'])) {
            $query->where('v_match.tournament_id', '=', $params['tournament_id']);
        }
        if (!empty($params['team_id'])) {
            $query->where(function ($where) use ($params) {
                $where->where('v_match.team1_id', '=', $params['team_id'])
                    ->orWhere('v_match.team2_id', '=', $params['team_id']);
            });
        } elseif (!empty($params['team'])) {
            $query->where(function ($where) use ($params) {
                $where->where('v_match.team1_name', 'like', '%' . $params['team'] . '%')
                    ->orWhere('v_match.team2_name', 'like', '%' . $params['team'] . '%');
            });
        }

        if (!empty($params['status'])) {
            $query->whereIn('v_match.status', $params['status']);
        }

        $count = $query->count();
        $list = $query
            ->orderBy('v_match.match_time', 'DESC')
            ->forPage($params['page'] ?? DEFAULT_PAGE, $params['page_size'] ?? DEFAULT_PAGE_SIZE)
            ->get()
            ->toArray();

        $list = array_map(function (array $row) {
            $row['team1'] = [
                'id' => $row['team1_id'],
                'name' => $row['team1_name'],
            ];
            $row['team2'] = [
                'id' => $row['team2_id'],
                'name' => $row['team2_name'],
            ];
            $row['tournament'] = [
                'id' => $row['tournament_id'],
                'name' => $row['tournament_name'],
            ];
            unset(
                $row['status'],
                $row['team1_id'],
                $row['team1_name'],
                $row['team2_id'],
                $row['team2_name'],
                $row['tournament_id'],
                $row['tournament_name'],
                $row['created_at'],
                $row['updated_at'],
            );
            return $row;
        }, $list);

        return [
            'count' => $count,
            'list' => $list,
        ];
    }

    /**
     * 获取单个比赛的信息
     * @param int $match_id
     * @return array
     */
    public function getMatch(int $match_id): array
    {
        $match = MatchView::query()
            ->where('id', '=', $match_id)
            ->first();
        if (!$match) {
            throw new BusinessError('未找到指定的比赛');
        }
        $match = $match->toArray();

        $match['team1'] = [
            'id' => $match['team1_id'],
            'name' => $match['team1_name'],
        ];
        $match['team2'] = [
            'id' => $match['team2_id'],
            'name' => $match['team2_name'],
        ];
        $match['tournament'] = [
            'id' => $match['tournament_id'],
            'name' => $match['tournament_name'],
        ];
        unset(
            $match['status'],
            $match['team1_id'],
            $match['team1_name'],
            $match['team2_id'],
            $match['team2_name'],
            $match['tournament_id'],
            $match['tournament_name'],
            $match['created_at'],
            $match['updated_at'],
        );
        return $match;
    }

    /**
     * 设置比赛的异常状态
     * @param int $match_id
     * @param int $error_status
     * @return void
     */
    public function setMatchErrorStatus(int $match_id, int $error_status): void
    {
        Match1::query()
            ->where('id', '=', $match_id)
            ->update([
                'error_status' => $error_status,
            ]);
    }

    /**
     * 获取联赛标签列表
     * @return array
     */
    public function getTournamentLabelList(): array
    {
        return TournamentLabel::query()
            ->orderBy('id', 'DESC')
            ->get()
            ->toArray();
    }

    /**
     * 保存联赛标签
     * @param array $data
     * @return void
     */
    public function saveTournamentLabel(array $data): void
    {
        if (!empty($data['id'])) {
            $label = TournamentLabel::query()
                ->where('id', '=', $data['id'])
                ->first();
            if (!$label) {
                throw new BusinessError('未找到要编辑的标签');
            }
        } else {
            $label = new TournamentLabel();
        }

        //检查重复的uid
        $exists = TournamentLabel::query()
            ->where('luffa_uid', '=', $data['luffa_uid'])
            ->when(!empty($data['id']), fn($query) => $query->where('id', '!=', $data['id']))
            ->exists();
        if ($exists) {
            throw new BusinessError('推送目标已被其他标签使用');
        }

        //保存信息
        $label->luffa_uid = $data['luffa_uid'];
        $label->luffa_type = $data['luffa_type'];
        $label->title = $data['title'];
        $label->save();

        //清空标签缓存
        Redis::del('tournament_labels');
    }

    /**
     * 删除联赛标签
     * @param int $id
     * @return void
     */
    public function deleteTournamentLabel(int $id): void
    {
        Db::beginTransaction();
        try {
            Tournament::query()
                ->where('label_id', '=', $id)
                ->update(['label_id' => 0]);
            TournamentLabel::query()
                ->where('id', '=', $id)
                ->delete();

            Db::beginTransaction();
        } catch (Throwable $e) {
            Db::rollBack();
            throw $e;
        }

        //清空标签缓存
        Redis::del('tournament_labels');
    }

    /**
     * 设置联赛标签
     * @param int $label_id 标签id
     * @param int|int[] $tournament_id 联赛id或数组
     * @return void
     */
    public function setTournamentLabel(int $label_id, int|array $tournament_id): void
    {
        //检查标签是否存在
        $exists = TournamentLabel::query()
            ->where('id', '=', $label_id)
            ->exists();
        if (!$exists) {
            throw new BusinessError('标签不存在');
        }

        $query = Tournament::query();
        if (is_array($tournament_id)) {
            $query->whereIn('id', $tournament_id);
        } else {
            $query->where('id', '=', $tournament_id);
        }

        $query->update(['label_id' => $label_id]);
    }
}