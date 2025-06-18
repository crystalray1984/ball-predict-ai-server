<?php declare(strict_types=1);

namespace app\api\controller;

use app\api\service\DashboardService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckUserToken;
use support\Controller;
use support\Request;
use support\Response;

/**
 * 首页面板控制器
 */
class DashboardController extends Controller
{
    #[Inject]
    protected DashboardService $dashboardService;

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
            $this->dashboardService->summary($params)
        );
    }

    /**
     * 获取准备中的比赛
     * @return Response
     */
    public function preparing(): Response
    {
        return $this->success(
            $this->dashboardService->preparing()
        );
    }

    /**
     * 获取已推荐的盘口
     * @param Request $request
     * @return Response
     */
    public function promoted(Request $request): Response
    {
        $params = v::input($request->post(), [
            'start_date' => v::optional(v::stringType()->date())->setName('start_date'),
            'end_date' => v::optional(v::stringType()->date())->setName('end_date'),
            'sort' => v::optional(v::in(['match_time', 'promote']))->setName('sort'),
            'sort_order' => v::optional(v::in(['asc', 'desc']))->setName('sort_order'),
        ]);

        return $this->success(
            $this->dashboardService->promoted($params)
        );
    }

    #[CheckUserToken]
    public function promotedV2(Request $request): Response
    {
        $params = v::input($request->post(), [
            'start_date' => v::optional(v::stringType()->date())->setName('start_date'),
            'end_date' => v::optional(v::stringType()->date())->setName('end_date'),
            'sort_order' => v::optional(v::in(['asc', 'desc']))->setName('sort_order'),
        ]);

        $list = $this->dashboardService->promoted($params, $request->user->expire_time);

        return $this->success([
            'is_expired' => $request->user->is_expired,
            'list' => $list,
        ]);
    }
}