<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\Model3Service;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckAdminToken;
use support\Controller;
use support\Request;
use support\Response;

/**
 * 模型3控制器
 */
class Model3Controller extends Controller
{
    #[Inject]
    protected Model3Service $model3Service;

    #[CheckAdminToken]
    public function getList(Request $request): Response
    {
        $params = v::input($request->post(), [
            'start_date' => v::optional(v::stringType()->date())->setName('start_date'),
            'end_date' => v::optional(v::stringType()->date())->setName('end_date'),
            'sort' => v::optional(v::in(['created_at', 'match_time']))->setName('sort'),
        ]);

        return $this->success($this->model3Service->getList($params));
    }
}