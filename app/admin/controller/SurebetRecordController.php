<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\SurebetRecordService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\Controller;
use support\Request;
use support\Response;

/**
 * Surebet推送记录控制器
 */
class SurebetRecordController extends Controller
{
    #[Inject]
    protected SurebetRecordService $surebetRecordService;

    /**
     * 导出记录
     * @param Request $request
     * @return Response
     */
    public function export(Request $request): Response
    {
        $params = v::input($request->post(), [
            'date_start' => v::optional(v::stringType()->date())->setName('date_start'),
            'date_end' => v::optional(v::stringType()->date())->setName('date_end'),
        ]);

        $filePath = $this->surebetRecordService->exportList(
            $params
        );

        $resp = new Response();
        $resp->download($filePath, basename($filePath));
        return $resp;
    }
}