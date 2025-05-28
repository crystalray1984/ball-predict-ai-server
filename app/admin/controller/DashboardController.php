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

    /**
     * 概览面板的VIP订单统计
     * @return Response
     */
    #[CheckAdminToken]
    public function vipSummary(): Response
    {
        return $this->success($this->dashboardService->getVipSummary());
    }
}