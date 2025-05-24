<?php declare(strict_types=1);

namespace app\api\controller;

use support\attribute\CheckUserToken;
use support\Controller;
use support\Request;
use support\Response;

/**
 * Luffa订单控制器
 */
class LuffaOrderController extends Controller
{
    /**
     * 创建Luffa订单
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken]
    public function create(Request $request): Response
    {

    }

    /**
     * 完成Luffa订单
     * @param Request $request
     * @return Response
     */
    public function complete(Request $request): Response
    {

    }

    /**
     * 获取Luffa购买配置
     * @param Request $request
     * @return Response
     */
    public function config(Request $request): Response
    {

    }
}