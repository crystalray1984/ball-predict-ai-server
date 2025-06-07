<?php declare(strict_types=1);

namespace app\api\controller;

use app\api\service\OrderService;
use app\model\Order;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckUserToken;
use support\Controller;
use support\Log;
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
        $config = config('payment');
        $config = array_map(fn(array $item) => $item['config'], $config);
        return $this->success($config);
    }

    /**
     * 创建订单接口
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken]
    public function create(Request $request): Response
    {
        $params = v::input($request->post(), [
            'type' => v::in(['day', 'week', 'month'])->setName('type'),
            'channel' => v::in(['endless', 'eds', 'plisio'])->setName('channel'),
            'client_type' => v::optional(v::stringType())->setName('client_type'),
            'redirect_url' => v::optional(v::stringType())->setName('redirect_url'),
        ]);

        //根据不同的支付通道，执行不同的订单生成
        $result = match ($params['channel']) {
            'endless', 'eds' => $this->orderService->createLuffaOrder(
                $request->user->id,
                $params['channel'],
                $params['type'],
            ),
            'plisio' => $this->orderService->createPlisioOrder(
                $request->user->id,
                $params['type'],
                $params['client_type'],
                $params['redirect_url']
            ),
        };

        return $this->success($result);
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
        Log::debug('[支付回调]', $post);

        //校验回调数据
        $plisio = new \Plisio\ClientAPI(config('payment.plisio.secret'));
        $verified = $plisio->verifyCallbackData($post, config('payment.plisio.secret'));
        if (!$verified) {
            Log::debug('[支付回调校验失败]', $post);
            return \response();
        }

        //从响应体中找到内部订单id
        /** @var Order $order */
        $order = Order::query()
            ->where('order_number', '=', $post['order_number'])
            ->first();
        if (!$order) {
            //没找到订单
            Log::debug('[支付回调] 未找到订单', $post['order_number']);
            return \response();
        }

        if ($order->status === 'paid') {
            //订单已经支付完成
            Log::debug('[支付回调] 重复通知', $post['order_number']);
            return \response();
        }

        //校验订单
        $this->orderService->checkPlisioOrder($order);
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