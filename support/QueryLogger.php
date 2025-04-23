<?php declare(strict_types=1);

namespace support;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Str;
use WeakMap;

final class QueryLogger
{
    /**
     * @var WeakMap<object, callable>
     */
    protected static WeakMap $weak_listeners;

    /**
     * 在指定的闭包中运行代码，并捕获之中所有的SQL查询记录
     * @param Closure $handler 待执行的闭包
     * @param callable $listener 监听到的SQL查询输出，入参为一个数组
     */
    public static function capture(Closure $handler, callable $listener): mixed
    {
        self::init();
        self::$weak_listeners->offsetSet($handler, $listener);
        try {
            return $handler();
        } finally {
            self::$weak_listeners->offsetUnset($handler);
        }
    }

    protected static bool $has_init = false;

    /**
     * 初始化查询监听器
     * @return void
     */
    protected static function init(): void
    {
        if (self::$has_init) return;
        self::$has_init = true;

        if (empty(self::$weak_listeners)) {
            self::$weak_listeners = new WeakMap();
        }

        //增加一个监听器
        Db::connection()->getEventDispatcher()->listen(function (QueryExecuted $executed) {
            //格式化查询数据
            $log = self::formatQueryExecuted($executed);

            //输出
            $iterator = self::$weak_listeners->getIterator();
            foreach ($iterator as $handler) {
                $handler($log);
            }
        });
    }

    protected static function formatQueryExecuted(QueryExecuted $executed): array
    {
        $bindings = array_map(
            fn(mixed $value) => $executed->connection->escape($value),
            $executed->connection->prepareBindings($executed->bindings)
        );

        return [
            'time' => $executed->time,
            'sql' => Str::replaceArray('?', $bindings, $executed->sql),
            'bindings' => $executed->bindings
        ];
    }
}