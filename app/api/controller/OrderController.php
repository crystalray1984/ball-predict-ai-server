<?php declare(strict_types=1);

namespace app\api\controller;

use app\api\service\OrderService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckUserToken;
use support\Controller;
use support\Log;
use support\Request;
use support\Response;

/**
 * 订单控制器
 */
class OrderController extends Controller
{
    #[Inject]
    protected OrderService $orderService;

    /**
     * 获取Luffa购买配置
     */
    public function getLuffaConfig(Request $request): Response
    {
        $params = v::input($request->post(), [
            'network' => v::in(['endless', 'eds'])->setName('network'),
        ]);

        return $this->success(
            config("payment.{$params['network']}.config")
        );
    }

    /**
     * Luffa购买会员下单
     */
    #[CheckUserToken]
    public function createLuffaOrder(Request $request): Response
    {
        $params = v::input($request->post(), [
            'type' => v::in(['day', 'week', 'month', 'quarter'])->setName('type'),
            'network' => v::in(['endless', 'eds'])->setName('network'),
        ]);

        return $this->success(
            $this->orderService->createLuffaOrder(
                $request->user->id,
                $params['network'],
                $params['type'],
            )
        );
    }

    /**
     * Luffa订单完成
     */
    public function completeLuffaOrder(Request $request): Response
    {
        $params = v::input($request->post(), [
            'order_id' => v::intType()->notEmpty()->setName('order_id'),
            'hash' => v::stringType()->notEmpty()->setName('hash'),
        ]);

        Log::channel('important')->info('Luffa订单完成 ' . json_enc($params));

        $this->orderService->completeLuffaOrder($params['order_id'], $params['hash']);
        return $this->success();
    }
}