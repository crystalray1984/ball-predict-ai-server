<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\MansionService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckAdminToken;
use support\Controller;
use support\Request;
use support\Response;

class MansionController extends Controller
{
    #[Inject]
    protected MansionService $mansionService;

    #[CheckAdminToken]
    public function getList(Request $request): Response
    {
        $params = v::input($request->post(), [
            'start_date' => v::optional(v::stringType()->date())->setName('start_date'),
            'end_date' => v::optional(v::stringType()->date())->setName('end_date'),
            'ready_status' => v::optional(v::in([0, 1, -1]))->setName('ready_status'),
            'promoted' => v::optional(v::in([0, 1, -1]))->setName('promoted'),
        ]);

        return $this->success($this->mansionService->getOddList($params));
    }

    public function exportList(Request $request): Response
    {
        $params = v::input($request->post(), [
            'start_date' => v::optional(v::stringType()->date())->setName('start_date'),
            'end_date' => v::optional(v::stringType()->date())->setName('end_date'),
            'ready_status' => v::optional(v::in([0, 1, -1, '0', '1', '-1']))->setName('ready_status'),
            'promoted' => v::optional(v::in([0, 1, -1, '0', '1', '-1']))->setName('promoted'),
        ]);

        if (isset($params['ready_status'])) {
            $params['ready_status'] = intval($params['ready_status']);
        }
        if (isset($params['promoted'])) {
            $params['promoted'] = intval($params['promoted']);
        }

        $filePath = $this->mansionService->exportOddList($params);
        $resp = new Response();
        $resp->download($filePath, basename($filePath));
        return $resp;
    }
}