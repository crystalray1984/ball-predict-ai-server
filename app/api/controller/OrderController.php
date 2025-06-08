<?php declare(strict_types=1);

namespace app\api\controller;

use app\api\service\OrderService;
use app\model\Order;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckUserToken;
use support\Controller;
use support\Log;
use support\payment\Engine;
use support\Request;
use support\Response;

/**
 * 订单接口
 */
class OrderController extends Controller
{
    #[Inject]
    protected OrderService $orderService;

    /**
     * 获取VIP购买配置接口
     * @return Response
     */
    public function config(): Response
    {
        $config = config('vip');
        return $this->success(array_map(fn(array $item) => $item['price'], $config));
    }

    /**
     * 创建订单接口
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken]
    public function create(Request $request): Response
    {
        $engines = array_keys(Engine::ENGINES);

        $params = v::input($request->post(), [
            'type' => v::in(['day', 'week', 'month'])->setName('type'),
            'channel' => v::in($engines)->setName('channel'),
        ]);

        return $this->success(
            $this->orderService->createVipOrder(
                $request->user->id,
                $params['channel'],
                $params['type'],
            )
        );
    }

    /**
     * 查询订单的完成状态接口
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken]
    public function query(Request $request): Response
    {
        ['order_id' => $order_id] = v::input($request->post(), [
            'order_id' => v::intType()->notEmpty()->setName('order_id'),
        ]);

        return $this->success(
            $this->orderService->queryOrder($order_id, $request->user->id)
        );
    }

    /**
     * Plisio订单回调接口
     * @param Request $request
     * @return Response
     */
    public function plisioCallback(Request $request): Response
    {
        $post = $request->post();
        Log::channel('plisio')->debug('[支付回调]', $post);

        //从响应体中找到内部订单id
        /** @var Order $order */
        $order = Order::query()
            ->where('order_number', '=', $post['order_number'])
            ->first();
        if (!$order) {
            //没找到订单
            Log::channel('plisio')->debug('[支付回调] 未找到订单', $post['order_number']);
            return \response();
        }

        if ($order->status === 'paid') {
            //订单已经支付完成
            Log::channel('plisio')->debug('[支付回调] 重复通知', $post['order_number']);
            return \response();
        }

        //校验订单
        $this->orderService->completeOrder($order);
        return \response();
    }

    /**
     * 订单完成的空白页面
     * @param Request $request
     * @return Response
     */
    public function finish(Request $request): Response
    {
        return \response();
    }
}