<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\BmissUser;
use app\model\BmissUserBet;
use app\model\Match1;
use Carbon\Carbon;

/**
 * Bmiss投注管理服务
 */
class BmissBetService
{
    /**
     * 获取用户列表
     * @param array $params
     * @return array
     */
    public function getUsers(array $params): array
    {
        $query = BmissUser::query();
        if (!empty($params['appid'])) {
            $query->where('appid', '=', $params['appid']);
        }
        if (!empty($params['openid'])) {
            $query->where('openid', '=', $params['openid']);
        }
        if (!empty($params['nickname'])) {
            $query->where('nickname', 'LIKE', "%{$params['nickname']}%");
        }

        $count = $query->count();

        if (!empty($params['order_field']) && !empty($params['order_sort'])) {
            $query->orderBy($params['order_field'], $params['order_sort']);
        } else {
            $query->orderBy('id', 'desc');
        }

        $list = $query
            ->forPage($params['page'] ?? DEFAULT_PAGE, $params['page_size'] ?? DEFAULT_PAGE_SIZE)
            ->get()
            ->toArray();

        return [
            'count' => $count,
            'list' => $list,
        ];
    }

    /**
     * 查询投注列表
     * @param array $params
     * @return array
     */
    public function getBetRecords(array $params): array
    {
        $query = BmissUserBet::query()
            ->join('v_match', 'bmiss_user_bet.match_id', '=', 'v_match.id');

        if (!empty($params['match_id'])) {
            $query->where('bmiss_user_bet.match_id', '=', $params['match_id']);
        }

        if (!empty($params['user_id'])) {
            $query->where('bmiss_user_bet.user_id', '=', $params['user_id']);
        }

        if (!empty($params['appid'])) {
            $query->where('bmiss_user_bet.appid', '=', $params['appid']);
        }

        if (!empty($params['openid'])) {
            $query->where('bmiss_user_bet.openid', '=', $params['openid']);
        }

        if (isset($params['paid'])) {
            if ($params['paid']) {
                $query->where('bmiss_user_bet.paid', '=', 1);
            } else {
                $query->where('bmiss_user_bet.paid', '>', 1);
            }
        } else {
            $query->where('bmiss_user_bet.paid', '!=', 0);
        }

        if (isset($params['result'])) {
            switch ($params['result']) {
                case '':
                    $query->whereNull('bmiss_user_bet.result');
                    break;
                default:
                    $query->where('bmiss_user_bet.result', '=', $params['result']);
                    break;
            }
        }

        $count = $query->count();

        $list = $query
            ->orderBy('bmiss_user_bet.created_at', 'DESC')
            ->forPage($params['page'] ?? DEFAULT_PAGE, $params['page_size'] ?? DEFAULT_PAGE_SIZE)
            ->get([
                'bmiss_user_bet.*',
                'v_match.team1_name',
                'v_match.team2_name',
                'v_match.tournament_name',
                'v_match.match_time',
            ])
            ->toArray();

        return [
            'count' => $count,
            'list' => $list,
        ];
    }

    /**
     * 获取没有比赛覆盖的时间段
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public function getEmptyRange(string $start_date, string $end_date): array
    {
        $start = Carbon::parse($start_date);
        $end = Carbon::parse($end_date)->addDays();

        $range_start = $start->clone()->addDay()->toISOString();
        $range_end = $end->clone()->addDay()->toISOString();

        $matches = Match1::query()
            ->where('bmiss_bet_enable', '=', 1)
            ->whereBetween('match_time', [$range_start, $range_end])
            ->orderBy('match_time')
            ->distinct()
            ->pluck('match_time')
            ->toArray();

        $time = $start->unix();
        $end_time = $end->unix();

        $result = [];

        while ($time < $end_time && !empty($matches)) {
            $match = array_shift($matches);
            $match_time = Carbon::parse($match)->unix();
            $bet_start = $match_time - 86400;
            if ($bet_start > $time) {
                $result[] = [$time, $bet_start];
            }
            $time = $match_time;
        }

        if ($time < $end_time && empty($matches)) {
            $result[] = [$time, $end_time];
        }

        return $result;
    }
}