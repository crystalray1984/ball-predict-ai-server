<?php declare(strict_types=1);

namespace app\process;

use app\api\service\DataReportService;
use DI\Attribute\Inject;
use Workerman\Timer;

/**
 * 更新报告
 */
class UpdateReport
{
    #[Inject]
    protected DataReportService $dataReportService;

    public function onWorkerStart(): void
    {
        Timer::add(60, function () {
            //如果还在周一就更新上周的报告
            if (date('w') === '1') {
                $this->dataReportService->getReport(['rockball'], 1, true);
            }
            //更新本周的报告
            $this->dataReportService->getReport(['rockball'], 0, true);
        });
    }
}