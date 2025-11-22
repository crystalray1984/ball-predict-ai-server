<?php declare(strict_types=1);

namespace app\api\controller;

use app\api\service\RockballDashboardService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckUserToken;
use support\Controller;
use support\Request;
use support\Response;

/**
 * 提供滚球数据的控制器
 */
class RockballDashboardController extends Controller
{
    #[Inject]
    protected RockballDashboardService $rockballDashboardService;

    /**
     * 获取统计数据
     * @param Request $request
     * @return Response
     */
    public function summary(Request $request): Response
    {
        $params = v::input($request->post(), [
            'start_date' => v::optional(v::stringType()->date())->setName('start_date'),
            'end_date' => v::optional(v::stringType()->date())->setName('end_date'),
        ]);

        return $this->success(
            $this->rockballDashboardService->summary($params)
        );
    }

    /**
     * 获取准备中的比赛
     * @return Response
     */
    public function preparing(): Response
    {
        return $this->success(
            $this->rockballDashboardService->preparing()
        );
    }

    #[CheckUserToken]
    public function promoted(Request $request): Response
    {
        $params = v::input($request->post(), [
            'start_date' => v::optional(v::stringType()->date())->setName('start_date'),
            'end_date' => v::optional(v::stringType()->date())->setName('end_date'),
            'sort_order' => v::optional(v::in(['asc', 'desc']))->setName('sort_order'),
        ]);

        $list = $this->rockballDashboardService->promoted($params, $request->user->expire_time);

        return $this->success([
            'is_expired' => $request->user->is_expired,
            'list' => $list,
        ]);
    }
}