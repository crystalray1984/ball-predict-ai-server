<?php declare(strict_types=1);

namespace support\payment;

use app\model\Order;

/**
 * 支付引擎基类
 */
abstract class Engine
{
    /**
     * 可用的支付引擎
     */
    const ENGINES = [
        'endless' => Endless::class,
        'tron' => Tron::class,
        'ethereum' => Ethereum::class,
        'bsc' => Bsc::class,
    ];

    /**
     * 获取支付方式类型
     * @return string
     */
    public abstract function getPaymentType(): string;

    /**
     * 创建交易
     * @param Order $order 内部交易订单对象
     * @return array 需要返回给前端的支付数据
     */
    public abstract function create(Order $order): array;

    /**
     * 完成交易
     * @param Order $order 内部交易订单对象
     * @param array $transaction 支付完成数据
     * @return bool
     */
    public abstract function complete(Order $order, array $transaction): bool;

    /**
     * 通过订单查询外部交易数据
     * @param Order $order
     * @return array
     */
    public abstract function check(Order $order): array;

    /**
     * 校验交易回调数据
     * @param array $post
     * @return bool
     */
    public abstract function verifyCallback(array $post): bool;
}