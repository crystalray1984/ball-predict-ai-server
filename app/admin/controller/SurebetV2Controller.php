<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\SurebetV2Service;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckAdminToken;
use support\Controller;
use support\Request;
use support\Response;

class SurebetV2Controller extends Controller
{
    #[Inject]
    protected SurebetV2Service $surebetV2Service;

    #[CheckAdminToken]
    public function list(Request $request): Response
    {
        $params = v::input($request->post(), [
            'start_date' => v::optional(v::stringType()->date())->setName('start_date'),
            'end_date' => v::optional(v::stringType()->date())->setName('end_date'),
            'label_id' => v::optional(v::intType())->setName('label_id'),
            'order' => v::optional(v::in(['match_time', 'promote_time']))->setName('order'),
        ]);

        return $this->success($this->surebetV2Service->getList($params));
    }

    public function export(Request $request): Response
    {
        $params = v::input($request->post(), [
            'start_date' => v::optional(v::stringType()->date())->setName('start_date'),
            'end_date' => v::optional(v::stringType()->date())->setName('end_date'),
            'order' => v::optional(v::in(['match_time', 'promote_time']))->setName('order'),
        ]);

        $filePath = $this->surebetV2Service->exportList($params);
        $resp = new Response();
        $resp->download($filePath, basename($filePath));
        return $resp;
    }
}