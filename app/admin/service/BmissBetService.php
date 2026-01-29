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

        $query = Match1::query()
            ->where('bmiss_bet_enable', '=', 1)
            ->whereBetween('match_time', [$start->toISOString(), $end->toISOString()])
            ->orderBy('match_time')
            ->distinct()
            ->select(['match_time']);

        $matches = $query->get()->toArray();

        $time = $start->unix();
        $end_time = $end->unix();

        $result = [];

        while ($time < $end_time && !empty($matches)) {
            $match = array_shift($matches);
            $match_time = Carbon::parse($match['match_time'])->unix();
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

    /**
     * 查询统计
     * @return array
     */
    public function summary(): array
    {
        $today_start = Carbon::today();
        $yesterday_start = Carbon::today()->subDay();
        $days_7_start = Carbon::today()->subDays(6);
        $days_30_start = Carbon::today()->subDays(29);

        return [
            'today' => [
                'users' => $this->getUserCountSummary($today_start),
                ...$this->getBetSummary($today_start),
            ],
            'yesterday' => [
                'users' => $this->getUserCountSummary($yesterday_start, $today_start),
                ...$this->getBetSummary($yesterday_start, $today_start),
            ],
            'days_7' => [
                'users' => $this->getUserCountSummary($days_7_start),
                ...$this->getBetSummary($days_7_start),
            ],
            'days_30' => [
                'users' => $this->getUserCountSummary($days_30_start),
                ...$this->getBetSummary($days_30_start),
            ],
            'all' => [
                'users' => $this->getUserCountSummary(),
                ...$this->getBetSummary(),
            ],
        ];
    }

    /**
     * 用户数统计
     * @param Carbon|null $start
     * @param Carbon|null $end
     * @return int
     */
    protected function getUserCountSummary(?Carbon $start = null, ?Carbon $end = null): int
    {
        $query = BmissUser::query();
        if (!empty($start)) {
            $query->where('last_login_at', '>=', $start->toISOString());
        }
        if (!empty($end)) {
            $query->where('last_login_at', '<', $end->toISOString());
        }
        return $query->count();
    }

    /**
     * 投注统计
     * @param Carbon|null $start
     * @param Carbon|null $end
     * @return array
     */
    protected function getBetSummary(?Carbon $start = null, ?Carbon $end = null): array
    {
        $query = BmissUserBet::query()
            ->where('paid', '=', 1);
        if (!empty($start)) {
            $query->where('created_at', '>=', $start->toISOString());
        }
        if (!empty($end)) {
            $query->where('created_at', '<', $end->toISOString());
        }
        $query->selectRaw('count(*) as count');
        $query->selectRaw('SUM(amount) as amount');
        $query->selectRaw('SUM(CASE WHEN result IS NULL THEN 0 ELSE amount - result_amount END) as profit');
        $row = $query->first();
        return [
            'bets' => $row?->count ?? 0,
            'amount' => $row?->amount ?? 0,
            'profit' => $row?->profit ?? 0,
        ];
    }
}