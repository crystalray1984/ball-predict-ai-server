<?php declare(strict_types=1);

namespace app\api\controller;

use app\api\service\UserService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\Controller;
use support\Request;
use support\Response;

/**
 * 用户控制器
 */
class UserController extends Controller
{
    #[Inject]
    protected UserService $userService;

    /**
     * 用户登录
     * @param Request $request
     * @return Response
     */
    public function login(Request $request): Response
    {
        $params = v::input($request->post(), [
            'username' => v::stringType()->notEmpty()->setName('username'),
            'password' => v::stringType()->notEmpty()->setName('password'),
        ]);

        return $this->success(
            $this->userService->login($params['username'], $params['password'])
        );
    }
}