<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\UserService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckAdminToken;
use support\Controller;
use support\Request;
use support\Response;

/**
 * 用户管理控制器
 */
class UserController extends Controller
{
    #[Inject]
    protected UserService $userService;

    /**
     * 获取用户列表
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function list(Request $request): Response
    {
        $params = v::input($request->post(), [
            'agent_id' => v::optional(v::intType())->setName('agent_id'),
            'username' => v::optional(v::stringType())->setName('username'),
            'page' => v::optional(v::intType()->min(1))->setName('page'),
            'page_size' => v::optional(v::intType()->min(1))->setName('page_size'),
        ]);

        return $this->success(
            $this->userService->getList($params)
        );
    }

    /**
     * 获取用户详情
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function get(Request $request): Response
    {
        $params = v::input($request->post(), [
            'id' => v::intType()->notEmpty()->setName('id'),
        ]);

        return $this->success(
            $this->userService->getDetails($params['id'])
        );
    }

    /**
     * 保存用户信息
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function save(Request $request): Response
    {
        $params = v::input($request->post(), [
            'id' => v::optional(v::intType())->setName('id'),
            'username' => v::stringType()->notEmpty()->setName('username'),
            'password' => v::optional(v::stringType())->setName('password'),
            'status' => v::in([0, 1])->setName('status'),
            'expire_time' => v::stringType()->dateTime()->setName('expire_time'),
            'note' => v::stringType()->setName('note'),
        ]);

        //新增的用户必须传密码
        if (empty($params['id'])) {
            v::input($params, [
                'password' => v::stringType()->notEmpty()->setName('password'),
            ]);
        }

        $this->userService->save($params);
        return $this->success();
    }
}