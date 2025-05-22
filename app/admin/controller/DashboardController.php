<?php declare(strict_types=1);

namespace app\admin\controller;

use support\attribute\CheckAdminToken;
use support\Controller;
use support\Response;

/**
 * 统计数据控制器
 */
class DashboardController extends Controller
{
    /**
     * 概览面板的数据统计
     * @return Response
     */
    #[CheckAdminToken]
    public function dashboardSummary(): Response
    {

    }
}