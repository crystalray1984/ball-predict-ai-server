<?php declare(strict_types=1);

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
                    bcsub($score['score1'], $value, 1) :
                    bcadd($score['score1'], $value, 1);
                $result['result'] += bccomp($part_score, (string)$score['score2'], 1);
            }
        } elseif ($odd['type'] === 'ah2') {
            //客队
            $result['score'] = $score['score1'] . ':' . $score['score2'];
            foreach ($condition['value'] as $value) {
                $part_score = $condition['symbol'] === '-' ?
                    bcsub($score['score2'], $value, 1) :
                    bcadd($score['score2'], $value, 1);
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
        }

        if ($result['result'] > 0) {
            $result['result'] = 1;
        } elseif ($result['result'] < 0) {
            $result['result'] = -1;
        }

        return $result;
    }
}