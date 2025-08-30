<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\SurebetRecordService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckAdminToken;
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
            'game' => v::optional(v::stringType())->setName('game'),
            'base' => v::optional(v::stringType())->setName('base'),
            'variety' => v::optional(v::stringType())->setName('variety'),
            'type' => v::optional(v::stringType())->setName('type'),
            'match_date_start' => v::optional(v::stringType()->date())->setName('match_date_start'),
            'match_date_end' => v::optional(v::stringType()->date())->setName('match_date_end'),
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

    /**
     * 查询surebet推送数据
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function list(Request $request): Response
    {
        $params = v::input($request->post(), [
            'game' => v::optional(v::stringType())->setName('game'),
            'base' => v::optional(v::stringType())->setName('base'),
            'variety' => v::optional(v::stringType())->setName('variety'),
            'type' => v::optional(v::stringType())->setName('type'),
            'match_date_start' => v::optional(v::stringType()->date())->setName('match_date_start'),
            'match_date_end' => v::optional(v::stringType()->date())->setName('match_date_end'),
            'date_start' => v::optional(v::stringType()->date())->setName('date_start'),
            'date_end' => v::optional(v::stringType()->date())->setName('date_end'),
            'page' => v::optional(v::intType()->min(1))->setName('page'),
            'page_size' => v::optional(v::intType()->min(1))->setName('page_size'),
        ]);

        return $this->success(
            $this->surebetRecordService->getList($params)
        );
    }
}