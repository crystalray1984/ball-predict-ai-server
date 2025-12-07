<?php declare(strict_types=1);

namespace app\api\controller;

use app\api\service\DataReportService;
use Carbon\Carbon;
use DI\Attribute\Inject;
use Respect\Validation\Validator;
use support\Controller;
use support\Request;
use support\Response;

/**
 * 数据报告
 */
class DataReportController extends Controller
{
    /**
     * @var int 报告的周起点
     */
    protected int $minWeek;

    public function __construct()
    {
        $this->minWeek = strtotime('2025/11/15 00:00:00');
    }

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


    /**
     * 滚球数据报告列表
     * @param Request $request
     * @return Response
     */
    public function rockballList(Request $request): Response
    {
        ['time' => $time] = Validator::input($request->post(), [
            'time' => Validator::intType()->notEmpty()->setName('time'),
        ]);
        $maxWeeks = (int)floor(($time - $this->minWeek) / (7 * 86400));
        $output = [];

        $week = 0;
        while (true) {
            $output[] = $this->dataReportService->getReport(['rockball'], $week, false, $time);
            if ($week >= $maxWeeks || $week >= 5) break;
            $week++;
        }

        $next = $week >= $maxWeeks ? 0 : $time - ($maxWeeks - $week) * 7 * 86400;

        return $this->success([
            'list' => $output,
            'next' => $next,
        ]);
    }
}