<?php declare(strict_types=1);

use app\model\Admin;
use app\model\User;
use Illuminate\Database\Eloquent\Model;
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
            'score1' => $score['score1'],
            'score2' => $score['score2'],
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
     * @return User|Model|null
     */
    function get_user(int $id, bool $allowCache = true): User|Model|null
    {
        if ($allowCache) {
            //尝试通过缓存获取用户信息
            $cache = \support\Redis::get(CACHE_USER_KEY . $id);
            if (is_string($cache) && !empty($cache)) {
                return User::make(json_decode($cache, true));
            }
        }

        /**
         * @var User|null $user
         */
        $user = User::query()->where('id', '=', $id)->first();
        if ($user) {
            \support\Redis::setEx(CACHE_USER_KEY . $id, 3600, json_enc($user->makeVisible(['deleted_at'])));
        }

        return $user;
    }
}

if (!function_exists('get_users')) {
    /**
     * 通过id数组批量获取用户信息
     * @param array $idList
     * @param bool $allowCache
     * @return array
     */
    function get_users(array $idList, bool $allowCache = true): array
    {
        if (empty($idList)) return [];

        $result = [];
        if ($allowCache) {
            $cache = \support\Redis::mGet(array_map(fn(mixed $id) => CACHE_USER_KEY . $id, $idList));
            $result = array_combine($idList, $cache);
            $idList = array_keys(
                array_filter($result, fn($item) => empty($item))
            );
            $result = array_filter($result, fn($item) => !empty($item));
        }

        if (empty($idList)) {
            return $result;
        }

        $users = User::query()->whereIn('id', $idList)->get()->toArray();
        if (!empty($users)) {
            foreach ($users as $user) {
                \support\Redis::setEx(CACHE_USER_KEY . $user['id'], 3600, json_enc($user));
            }
            $result += array_column($users, null, 'id');
        }
        return $result;
    }
}

if (!function_exists('get_admin')) {
    /**
     * 通过id获取管理员信息
     * @param int $id
     * @param bool $allowCache
     * @return Admin|Model|null
     */
    function get_admin(int $id, bool $allowCache = true): Model|Admin|null
    {
        if ($allowCache) {
            //尝试通过缓存获取用户信息
            $cache = \support\Redis::get(CACHE_ADMIN_KEY . $id);
            if (is_string($cache) && !empty($cache)) {
                return Admin::make(json_decode($cache, true));
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

if (!function_exists('get_settings')) {
    /**
     * 读取系统配置
     * @param string[] $keys
     * @param bool $allowCache
     * @return array
     */
    function get_settings(array $keys, bool $allowCache = true): array
    {
        if (empty($keys)) return [];

        //先判断缓存是否存在
        if ($allowCache) {
            $exists = \support\Redis::exists(CACHE_SETTING_KEY);
            if ($exists) {
                $cache = \support\Redis::hmget(CACHE_SETTING_KEY, $keys);
                $data = array_map(fn(string|null $value) => is_string($value) && $value !== '' ? json_decode($value, true) : null, $cache);
                return array_combine($keys, $data);
            }
        }

        if ($allowCache) {
            $data = \app\model\Setting::query()->get()->toArray();
            $data = array_column($data, 'value', 'name');
            \support\Redis::del(CACHE_SETTING_KEY);
            \support\Redis::hmset(CACHE_SETTING_KEY, $data);
            $data = array_filter($data, fn(string $key) => in_array($key, $keys), ARRAY_FILTER_USE_KEY);
        } else {
            $data = \app\model\Setting::query()
                ->whereIn('name', $keys)
                ->get()
                ->toArray();
            $data = array_column($data, 'value', 'name');
        }
        return array_map(fn(string|null $value) => is_string($value) && $value !== '' ? json_decode($value, true) : null, $data);
    }
}

/**
 * 获取反向盘口
 */
if (!function_exists('get_reverse_odd')) {
    /**
     * 获取反向盘口
     * @param string $type
     * @param string $condition
     * @return string[]
     */
    function get_reverse_odd(string $type, string $condition): array
    {
        switch ($type) {
            case 'ah1':
                $type = 'ah2';
                $condition = bcsub('0', $condition, 2);
                break;
            case 'ah2':
                $type = 'ah1';
                $condition = bcsub('0', $condition, 2);
                break;
            case 'over':
                $type = 'under';
                break;
            case 'under':
                $type = 'over';
                break;
        }

        return [$type, $condition];
    }
}

if (!function_exists('rabbitmq_publish')) {
    /**
     * 把数据抛到rabbitmq队列
     * @param string $queueName 队列名
     * @param string|array $content 队列数据
     * @param array $headers 数据头
     * @return void
     * @throws AMQPException
     */
    function rabbitmq_publish(string $queueName, string|array $content, array $headers = []): void
    {
        if (empty($content)) return;

        $config = config('rabbitmq');

        //建立连接
        $connection = new AMQPConnection($config);
        $connection->connect();

        try {
            //打开通道
            $channel = new AMQPChannel($connection);

            //打开队列
            $queue = new AMQPQueue($channel);
            $queue->setName($queueName);
            $queue->setFlags(AMQP_DURABLE);
            $queue->declareQueue();

            //打开默认交换机
            $exchange = new AMQPExchange($channel);

            $headers += ['delivery_mode' => 2];

            //发布数据
            if (is_string($content)) {
                $exchange->publish($content, $queueName, null, $headers);
            } elseif (is_array($content)) {
                foreach ($content as $item) {
                    $exchange->publish($item, $queueName, null, $headers);
                }
            }
        } finally {
            //关闭连接
            $connection->disconnect();
        }
    }
}

if (!function_exists('duration')) {
    /**
     * 输出时长的内容
     * @param int $duration 时长秒数
     * @return string
     */
    function duration(int $duration): string
    {
        $seconds = $duration % 60;
        $duration -= $seconds;
        $duration /= 60;
        $minutes = $duration % 60;
        $duration -= $minutes;
        $hours = $duration / 60;

        return $hours .
            ':' . str_pad((string)$minutes, 2, '0', STR_PAD_LEFT) .
            ':' . str_pad((string)$seconds, 2, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('get_odd_identification')) {
    /**
     * 获取投注方向的综合类型
     * @param string $type
     * @return string
     */
    function get_odd_identification(string $type): string
    {
        return match ($type) {
            'ah1', 'ah2', 'draw' => 'ah',
            'under', 'over' => 'sum',
            'default' => '',
        };
    }
}

if (!function_exists('get_period_text')) {
    /**
     * 获取时段的展示文字
     * @param string $period
     * @return string
     */
    function get_period_text(string $period): string
    {
        return match ($period) {
            'regularTime' => '全场',
            'period1' => '半场',
            default => '',
        };
    }
}

if (!function_exists('get_variety_text')) {
    function get_variety_text(string $variety): string
    {
        return match ($variety) {
            'goal' => '进球',
            'corner' => '角球',
            default => '',
        };
    }
}

if (!function_exists('get_odd_type_text')) {
    function get_odd_type_text(string $type): string
    {
        //推荐方向
        return match ($type) {
            'ah1' => '主队',
            'ah2' => '客队',
            'under' => '小球',
            'over' => '大球',
            'draw' => '平局',
            default => '',
        };
    }
}

if (!function_exists('get_condition_text')) {
    function get_condition_text(string|int|float $condition, string $type): string
    {
        $condition = floatval($condition);
        return match ($type) {
            'ah1', 'ah2' => $condition <= 0 ? strval($condition) : "+$condition",
            default => strval($condition),
        };
    }
}

if (!function_exists('get_version_number')) {
    /**
     * 获取版本号对应的数值
     * @param string $version
     * @return int
     */
    function get_version_number(string $version): int
    {
        if (!preg_match('/^(\d+)\.(\d+)(\.\d+)?$/', $version, $matches)) {
            return 0;
        }

        $value = intval($matches[1]) * 1000000 + intval($matches[2]) * 1000;
        if (!empty($matches[3])) {
            $value += intval(substr($matches[3], 1));
        }
        return $value;
    }
}

if (!function_exists('get_summary_data')) {
    /**
     * 按赛果整理统计数据
     * @param array $data
     * @return array
     */
    function get_summary_data(array $data): array
    {
        if (empty($data)) {
            //没有数据
            return [
                'win' => 0,
                'loss' => 0,
                'draw' => 0,
                'win_rate' => 0,
            ];
        }

        $data = array_column($data, 'count', 'result');

        $result = [
            'win' => $data[1] ?? 0,
            'loss' => $data[-1] ?? 0,
            'draw' => $data[0] ?? 0,
        ];

        $valid = $result['win'] + $result['loss'];
        if ($valid === 0) {
            $result['win_rate'] = 0;
        } else {
            $result['win_rate'] = round($result['win'] * 100 / $valid, 1);
        }

        return $result;
    }
}

if (!function_exists('get_odd_profit')) {
    /**
     * 获取盘口收益
     * @param array{
     *     type: string,
     *     condition: string,
     *     value: string,
     *     score1: number,
     *     score2: number
     * } $data
     * @return array
     */
    function get_odd_profit(array $data): array
    {
        $condition = parse_condition($data['condition']);
        $win_profit = bcsub($data['value'], '1', 6);
        $profit = '0';
        $part_win_profit = bcdiv($win_profit, (string)count($condition['value']), 6);
        $part_loss_profit = bcdiv('1', (string)count($condition['value']), 6);
        $win_base = 2 / count($condition['value']);
        $win_count = 0;
        if ($data['type'] === 'ah1') {
            //主队
            foreach ($condition['value'] as $value) {
                $part_score = $condition['symbol'] === '-' ?
                    bcsub((string)$data['score1'], $value, 1) :
                    bcadd((string)$data['score1'], $value, 1);
                switch (bccomp($part_score, (string)$data['score2'], 1)) {
                    case 1:
                        $profit = bcadd($profit, $part_win_profit, 6);
                        $win_count += $win_base;
                        break;
                    case -1:
                        $profit = bcsub($profit, $part_loss_profit, 6);
                        break;
                }
            }
        } elseif ($data['type'] === 'ah2') {
            //客队
            foreach ($condition['value'] as $value) {
                $part_score = $condition['symbol'] === '-' ?
                    bcsub((string)$data['score2'], $value, 1) :
                    bcadd((string)$data['score2'], $value, 1);
                switch (bccomp($part_score, (string)$data['score1'], 1)) {
                    case 1:
                        $profit = bcadd($profit, $part_win_profit, 6);
                        $win_count += $win_base;
                        break;
                    case -1:
                        $profit = bcsub($profit, $part_loss_profit, 6);
                        break;
                }
            }
        } elseif ($data['type'] === 'over') {
            //大球
            $total = (string)($data['score1'] + $data['score2']);
            foreach ($condition['value'] as $value) {
                switch (bccomp($total, $value, 1)) {
                    case 1:
                        $profit = bcadd($profit, $part_win_profit, 6);
                        $win_count += $win_base;
                        break;
                    case -1:
                        $profit = bcsub($profit, $part_loss_profit, 6);
                        break;
                }
            }
        } elseif ($data['type'] === 'under') {
            //小球
            $total = (string)($data['score1'] + $data['score2']);
            foreach ($condition['value'] as $value) {
                switch (bccomp($value, $total, 1)) {
                    case 1:
                        $profit = bcadd($profit, $part_win_profit, 6);
                        $win_count += $win_base;
                        break;
                    case -1:
                        $profit = bcsub($profit, $part_loss_profit, 6);
                        break;
                }
            }
        } elseif ($data['type'] === 'draw') {
            //平球
            if ($data['score1'] === $data['score2']) {
                $profit = bcadd($profit, $part_win_profit, 6);
                $win_count += $win_base;
            } else {
                $profit = bcsub($profit, $part_loss_profit, 6);
            }
        }

        return [$profit, $win_count];
    }
}