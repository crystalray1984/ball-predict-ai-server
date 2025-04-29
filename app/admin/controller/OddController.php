<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\OddService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckAdminToken;
use support\Controller;
use support\Request;
use support\Response;

/**
 * 盘口管理接口
 */
class OddController extends Controller
{
    #[Inject]
    protected OddService $oddService;

    /**
     * 获取盘口抓取数据
     * @param Request $request
     * @return Response
     */
    public function getOddList(Request $request): Response
    {
        $params = v::input($request->post(), [
            'start_date' => v::optional(v::stringType()->date())->setName('start_date'),
            'end_date' => v::optional(v::stringType()->date())->setName('end_date'),
            'matched1' => v::optional(v::in([0, 1, -1]))->setName('matched1'),
            'matched2' => v::optional(v::in([0, 1, -1]))->setName('matched2'),
        ]);

        return $this->success(
            $this->oddService->getOddList($params)
        );
    }
}