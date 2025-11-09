<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\OddService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckAdminToken;
use support\Controller;
use support\Request;
use support\Response;

class OddController extends Controller
{
    #[Inject]
    protected OddService $oddService;

    /**
     * 读取比赛列表
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function getMatchList(Request $request): Response
    {
        $params = v::input($request->post(), [
            'team' => v::optional(v::stringType())->setName('team'),
            'start_date' => v::optional(v::stringType()->date())->setName('start_date'),
            'end_date' => v::optional(v::stringType()->date())->setName('end_date'),
            'ready_status' => v::optional(v::in([0, 1, -1]))->setName('ready_status'),
            'promoted' => v::optional(v::in([0, 1, -1]))->setName('promoted'),
        ]);

        return $this->success($this->oddService->getMatchList($params));
    }

    /**
     * 导出比赛列表数据
     * @param Request $request
     * @return Response
     */
    public function exportMatchList(Request $request): Response
    {
        $params = v::input($request->post(), [
            'team' => v::optional(v::stringType())->setName('team'),
            'start_date' => v::optional(v::stringType()->date())->setName('start_date'),
            'end_date' => v::optional(v::stringType()->date())->setName('end_date'),
            'ready_status' => v::optional(v::in(['0', '1', '-1']))->setName('ready_status'),
            'promoted' => v::optional(v::in(['0', '1', '-1']))->setName('promoted'),
        ]);

        if (isset($params['ready_status'])) {
            $params['ready_status'] = intval($params['ready_status']);
        }
        if (isset($params['promoted'])) {
            $params['promoted'] = intval($params['promoted']);
        }

        $filePath = $this->oddService->exportMatchList($params);
        $resp = new Response();
        $resp->download($filePath, basename($filePath));
        return $resp;
    }

    /**
     * 读取盘口追踪记录
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function getOddRecords(Request $request): Response
    {
        $params = v::input($request->post(), [
            'match_id' => v::intType()->notEmpty()->setName('match_id'),
            'type' => v::in(['ah', 'sum'])->setName('type'),
        ]);

        return $this->success($this->oddService->getOddRecords($params['match_id'], $params['type']));
    }
}