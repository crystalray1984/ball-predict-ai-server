<?php declare(strict_types=1);

namespace app\api\controller;

use app\api\service\DataReportService;
use DI\Attribute\Inject;
use support\Controller;
use support\Response;

/**
 * 数据报告
 */
class DataReportController extends Controller
{
    #[Inject]
    protected DataReportService $dataReportService;

    /**
     * 滚球数据报告
     * @return Response
     */
    public function rockball(): Response
    {
        return $this->success([
            'this_week' => $this->dataReportService->getReport(['rockball'], 0),
            'last_week' => $this->dataReportService->getReport(['rockball'], 1),
        ]);
    }
}