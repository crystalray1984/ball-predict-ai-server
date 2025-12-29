<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\DataService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckAdminToken;
use support\Controller;
use support\Request;
use support\Response;

/**
 * 数据控制器
 */
class DataController extends Controller
{
    #[Inject]
    protected DataService $dataService;

    /**
     * 生成滚球统计数据
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function rockballSummary(Request $request): Response
    {
        $params = v::input($request->post(), [
            'type' => v::stringType()->notEmpty()->setName('type'),
            'period' => v::stringType()->notEmpty()->setName('period'),
            'condition' => v::stringType()->numericVal()->setName('condition'),
            'condition2' => v::optional(v::stringType()->numericVal())->setName('condition2'),
        ]);

        return $this->success(
            $this->dataService->rockBallSummary($params)
        );
    }
}