<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\AgentService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckAdminToken;
use support\Controller;
use support\Request;
use support\Response;

/**
 * 代理控制器
 */
class AgentController extends Controller
{
    #[Inject]
    protected AgentService $agentService;

    /**
     * 获取代理列表
     * @param Request $request
     * @return Response
     */
    public function list(Request $request): Response
    {
        $params = v::input($request->post(), [
            'parent_id' => v::optional(v::intType()->min(-1))->setName('parent_id'),
        ]);

        return $this->success(
            $this->agentService->getList($params),
        );
    }

    /**
     * 获取代理详情
     * @param Request $request
     * @return Response
     */
    public function get(Request $request): Response
    {
        $params = v::input($request->post(), [
            'id' => v::intType()->notEmpty()->setName('id'),
        ]);

        return $this->success(
            $this->agentService->getDetails($params['id']),
        );
    }

    /**
     * 保存代理
     * @param Request $request
     * @return Response
     */
    public function save(Request $request): Response
    {
        $params = v::input($request->post(), [
            'id' => v::optional(v::intType()->min(0))->setName('id'),
            'parent_id' => v::intType()->min(0)->setName('parent_id'),
            'username' => v::stringType()->notEmpty()->setName('username'),
            'password' => v::optional(v::stringType())->setName('password'),
            'status' => v::in([0, 1])->setName('status'),
            'note' => v::stringType()->setName('note'),
            'commission_config' => v::arrayType()->notEmpty()->each(
                v::arrayType()
                    ->key('min_value', v::intType()->min(0)->setName('min_value'))
                    ->key('rate', v::intType()->between(0, 100)->setName('rate'))
            )
        ]);

        //新增的代理必须传密码
        if (empty($params['id'])) {
            v::input($params, [
                'password' => v::stringType()->notEmpty()->setName('password'),
            ]);
        }

        $this->agentService->save($params);
        return $this->success();
    }
}