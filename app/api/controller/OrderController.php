<?php declare(strict_types=1);

namespace app\api\controller;

use Respect\Validation\Validator as v;
use support\attribute\CheckUserToken;
use support\Controller;
use support\Request;
use support\Response;

/**
 * 订单控制器
 */
class OrderController extends Controller
{
    /**
     * Luffa购买会员下单
     */
    #[CheckUserToken]
    public function createLuffaOrder(Request $request): Response
    {
        $params = v::input($request->post(), [
            'type' => v::in(['week', 'month', 'year'])->setName('type'),
            'network' => v::optional(v::in(['endless', 'eds']))->setName('network'),
        ]);
    }

    /**
     * Luffa订单完成
     */
    #[CheckUserToken]
    public function completeLuffaOrder()
    {

    }
}