<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\DashboardService;
use DI\Attribute\Inject;
use support\attribute\CheckAdminToken;
use support\Controller;
use support\Response;

/**
 * 统计数据控制器
 */
class DashboardController extends Controller
{
    #[Inject]
    protected DashboardService $dashboardService;

    /**
     * 概览面板的数据统计
     * @return Response
     */
    #[CheckAdminToken]
    public function summary(): Response
    {
        return $this->success($this->dashboardService->getSummary());
    }

    #[CheckAdminToken]
    public function v2ToV3Summary(): Response
    {
        return $this->success($this->dashboardService->getV2ToV3Summary());
    }

    /**
     * 用户数据统计
     * @return Response
     */
    #[CheckAdminToken]
    public function userSummary(): Response
    {
        return $this->success($this->dashboardService->getUserSummary());
    }

    /**
     * 概览面板的VIP订单统计
     * @return Response
     */
    #[CheckAdminToken]
    public function vipSummary(): Response
    {
        return $this->success($this->dashboardService->getVipSummary());
    }

    /**
     * 各个标签的胜率统计
     * @return Response
     */
    #[CheckAdminToken]
    public function labelSummary(): Response
    {
        return $this->success($this->dashboardService->getLabelSummary());
    }
}