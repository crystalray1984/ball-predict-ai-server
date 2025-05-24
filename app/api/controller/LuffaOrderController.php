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
 * Luffa订单控制器
 */
class LuffaOrderController extends Controller
{
    #[Inject]
    protected OrderService $orderService;

    /**
     * 创建Luffa订单
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken]
    public function create(Request $request): Response
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
     * 完成Luffa订单
     * @param Request $request
     * @return Response
     */
    public function complete(Request $request): Response
    {
        Log::channel('endless')
            ->info("[Luffa订单完成] " . json_enc($request->post()));

        $params = v::input($request->post(), [
            'order_id' => v::intType()->notEmpty()->setName('order_id'),
            'hash' => v::stringType()->notEmpty()->setName('hash'),
        ]);

        $this->orderService->completeLuffaOrder($params['order_id'], $params['hash']);
        return $this->success();
    }

    /**
     * 获取Luffa购买配置
     * @param Request $request
     * @return Response
     */
    public function config(Request $request): Response
    {
        $params = v::input($request->post(), [
            'network' => v::in(['endless', 'eds'])->setName('network'),
        ]);

        return $this->success(
            config("payment.{$params['network']}.config")
        );
    }
}