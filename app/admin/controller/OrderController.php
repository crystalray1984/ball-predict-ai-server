<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\OrderService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckAdminToken;
use support\Controller;
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
     * 查询订单列表
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function list(Request $request): Response
    {
        $params = v::input($request->post(), [
            'type' => v::optional(v::stringType())->setName('type'),
            'start_date' => v::optional(v::stringType()->date())->setName('start_date'),
            'end_date' => v::optional(v::stringType()->date())->setName('end_date'),
            'extra' => v::optional(v::arrayType())->setName('extra'),
            'page' => v::optional(v::intType()->min(1))->setName('page'),
            'page_size' => v::optional(v::intType()->min(1))->setName('page_size'),
        ]);

        return $this->success(
            $this->orderService->getList($params)
        );
    }
}