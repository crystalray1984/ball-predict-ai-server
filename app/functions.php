<?php declare(strict_types=1);

use app\model\Admin;
use app\model\Agent;
use app\model\User;
use support\Container;

if (!function_exists('enable_debug')) {
    /**
     * 判断是否开启了调试
     * @param \Webman\Http\Request|null $request
     * @return bool
     */
    function enable_debug(?\Webman\Http\Request $request = null): bool
    {
        if ($request) {
            //尝试从请求头或者请求参数中获取调试开关
            if ($request->header('__debug') === '1') {
                return true;
            }
        }
        return config('app.debug');
    }
}

if (!function_exists('G')) {
    /**
     * 获取全局缓存的实例
     * @template T
     * @param class-string<T> $class
     * @return T
     */
    function G(string $class): mixed
    {
        return Container::get($class);
    }
}

if (!function_exists('parse_condition')) {
    /**
     * 将小数的投注条件拆解为数组
     * @param string $value 以小数形式表示的投注条件
     * @return array{
     *     symbol: string,
     *     value: string[]
     * }
     */
    function parse_condition(string $value): array
    {
        $symbol = str_starts_with($value, '-') ? '-' : '+';
        $value = (string)abs((float)$value);
        if (str_ends_with($value, '.75')) {
            $low_value = bcsub($value, '0.25', 1);
            $high_value = bcadd($low_value, '0.5', 0);
            $return = [
                'symbol' => $symbol,
                'value' => [$low_value, $high_value],
            ];
        } elseif (str_ends_with($value, '.25')) {
            $low_value = bcsub($value, '0.25', 0);
            $high_value = bcadd($low_value, '0.5', 1);
            $return = [
                'symbol' => $symbol,
                'value' => [$low_value, $high_value],
            ];
        } else {
            //单一水位
            $return = [
                'symbol' => $symbol,
                'value' => [$value],
            ];
        }

        if ($return['symbol'] === '-' && count($return['value']) > 1) {
            $return['value'] = array_reverse($return['value']);
        }

        return $return;
    }
}

if (!function_exists('get_odd_score')) {
    /**
     * 根据比赛的赛果数据，计算盘口对应的赛果
     * @param array $match_score 赛果数据
     * @param array $odd 盘口数据
     * @return array{
     *     score: string,
     *     result: int
     * }
     */
    function get_odd_score(array $match_score, array $odd): array
    {
        $score = [];

        if ($odd['variety'] === 'goal') {
            //进球
            if ($odd['period'] === 'period1') {
                //上半场
                $score = [
                    'score1' => $match_score['score1_period1'],
                    'score2' => $match_score['score2_period1'],
                    'total' => $match_score['score1_period1'] + $match_score['score2_period1'],
                ];
            } else {
                //全场
                $score = [
                    'score1' => $match_score['score1'],
                    'score2' => $match_score['score2'],
                    'total' => $match_score['score1'] + $match_score['score2'],
                ];
            }
        } elseif ($odd['variety'] === 'corner') {
            //角球
            if ($odd['period'] === 'period1') {
                //上半场
                $score = [
                    'score1' => $match_score['corner1_period1'],
                    'score2' => $match_score['corner2_period1'],
                    'total' => $match_score['corner1_period1'] + $match_score['corner2_period1'],
                ];
            } else {
                //全场
                $score = [
                    'score1' => $match_score['corner1'],
                    'score2' => $match_score['corner2'],
                    'total' => $match_score['corner1'] + $match_score['corner2'],
                ];
            }
        }

        //计算赛果
        $condition = parse_condition($odd['condition']);
        $result = [
            'result' => 0,
        ];
        if ($odd['type'] === 'ah1') {
            //主队
            $result['score'] = $score['score1'] . ':' . $score['score2'];
            foreach ($condition['value'] as $value) {
                $part_score = $condition['symbol'] === '-' ?
                    bcsub((string)$score['score1'], $value, 1) :
                    bcadd((string)$score['score1'], $value, 1);
                $result['result'] += bccomp($part_score, (string)$score['score2'], 1);
            }
        } elseif ($odd['type'] === 'ah2') {
            //客队
            $result['score'] = $score['score1'] . ':' . $score['score2'];
            foreach ($condition['value'] as $value) {
                $part_score = $condition['symbol'] === '-' ?
                    bcsub((string)$score['score2'], $value, 1) :
                    bcadd((string)$score['score2'], $value, 1);
                $result['result'] += bccomp($part_score, (string)$score['score1'], 1);
            }
        } elseif ($odd['type'] === 'over') {
            //大球
            $result['score'] = (string)$score['total'];
            foreach ($condition['value'] as $value) {
                $result['result'] += bccomp((string)$score['total'], $value, 1);
            }
        } elseif ($odd['type'] === 'under') {
            //小球
            $result['score'] = (string)$score['total'];
            foreach ($condition['value'] as $value) {
                $result['result'] += bccomp($value, (string)$score['total'], 1);
            }
        } elseif ($odd['type'] === 'draw') {
            //平球
            $result['score'] = $score['score1'] . ':' . $score['score2'];
            $result['result'] += $score['score1'] === $score['score2'] ? 1 : -1;
        }

        if ($result['result'] > 0) {
            $result['result'] = 1;
        } elseif ($result['result'] < 0) {
            $result['result'] = -1;
        }

        return $result;
    }
}

if (!function_exists('get_user')) {
    /**
     * 通过id获取用户信息
     * @param int $id
     * @param bool $allowCache
     * @return User|null
     */
    function get_user(int $id, bool $allowCache = true): User|null
    {
        if ($allowCache) {
            //尝试通过缓存获取用户信息
            $cache = \support\Redis::get(CACHE_USER_KEY . $id);
            if (is_string($cache) && !empty($cache)) {
                return new User(json_decode($cache, true));
            }
        }

        /**
         * @var User|null $user
         */
        $user = User::query()->where('id', '=', $id)->first();
        if ($user) {
            \support\Redis::setEx(CACHE_USER_KEY . $id, 3600, json_encode($user));
        }

        return $user;
    }
}

if (!function_exists('get_agent')) {
    /**
     * 通过id获取代理信息
     * @param int $id
     * @param bool $allowCache
     * @return Agent|null
     */
    function get_agent(int $id, bool $allowCache = true): Agent|null
    {
        if ($allowCache) {
            //尝试通过缓存获取用户信息
            $cache = \support\Redis::get(CACHE_AGENT_KEY . $id);
            if (is_string($cache) && !empty($cache)) {
                return new Agent(json_decode($cache, true));
            }
        }

        /**
         * @var Agent|null $agent
         */
        $agent = Agent::query()->where('id', '=', $id)->first();
        if ($agent) {
            \support\Redis::setEx(CACHE_AGENT_KEY . $id, 3600, json_encode($agent));
        }

        return $agent;
    }
}

if (!function_exists('get_admin')) {
    /**
     * 通过id获取管理员信息
     * @param int $id
     * @param bool $allowCache
     * @return Admin|null
     */
    function get_admin(int $id, bool $allowCache = true): Admin|null
    {
        if ($allowCache) {
            //尝试通过缓存获取用户信息
            $cache = \support\Redis::get(CACHE_ADMIN_KEY . $id);
            if (is_string($cache) && !empty($cache)) {
                return new Admin(json_decode($cache, true));
            }
        }

        /**
         * @var Admin|null $admin
         */
        $admin = Admin::query()->where('id', '=', $id)->first();
        if ($admin) {
            \support\Redis::setEx(CACHE_ADMIN_KEY . $id, 3600, json_encode($admin));
        }

        return $admin;
    }
}