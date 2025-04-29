<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\AdminService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckAdminToken;
use support\Controller;
use support\Request;
use support\Response;
use Tinywan\Captcha\Captcha;

/**
 * 管理端用户控制器
 */
class AdminController extends Controller
{
    #[Inject]
    protected AdminService $adminService;

    /**
     * 管理员登录接口
     * @param Request $request
     * @return Response
     */
    public function login(Request $request): Response
    {
        $params = v::input($request->post(), [
            'username' => v::stringType()->notEmpty()->setName('username'),
            'password' => v::stringType()->notEmpty()->setName('password'),
            'code_key' => v::stringType()->notEmpty()->setName('code_key'),
            'code' => v::stringType()->notEmpty()->setName('code'),
        ]);

        //校验验证码
        if (!Captcha::check($params['code'], $params['code_key'])) {
            return $this->fail('验证码错误');
        }

        return $this->success(
            $this->adminService->login($params)
        );
    }

    /**
     * 获取当前登录的管理员信息
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function info(Request $request): Response
    {
        return $this->success(
            $request->admin
        );
    }
}