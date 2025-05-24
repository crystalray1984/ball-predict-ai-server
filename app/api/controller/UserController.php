<?php declare(strict_types=1);

namespace app\api\controller;

use app\api\service\LoginRegisterService;
use app\api\service\UserService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckUserToken;
use support\Controller;
use support\Request;
use support\Response;
use support\Token;
use Tinywan\Captcha\Captcha;

/**
 * 用户控制器
 */
class UserController extends Controller
{
    #[Inject]
    protected UserService $userService;

    #[Inject]
    protected LoginRegisterService $loginRegisterService;

    /**
     * 邮件密码登录
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

        $user = $this->loginRegisterService->emailPasswordLogin($params);

        //生成token
        $token = Token::create(['id' => $user->id, 'type' => 'user']);

        return $this->success([
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Luffa用户登录
     * @param Request $request
     * @return Response
     */
    public function luffaLogin(Request $request): Response
    {
        $params = v::input($request->post(), [
            'network' => v::stringType()->in(['eds', 'endless'])->setName('network'),
            'info' => v::arrayType()
                ->notEmpty()
                ->key('uid', v::stringType()->notEmpty()->setName('network.uid'))
                ->setName('info'),
        ]);

        //获取登录的用户
        $user = $this->loginRegisterService->luffaLogin($params['network'], $params['info']);
        //生成token
        $token = Token::create(['id' => $user->id, 'type' => 'user']);

        return $this->success([
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * 获取当前用户的信息
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken]
    public function info(Request $request): Response
    {
        return $this->success(
            $request->user
        );
    }

    /**
     * 读取VIP购买记录
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken]
    public function getVipRecords(Request $request): Response
    {
        $params = v::input($request->post(), [
            'page' => v::optional(v::intType()->min(1))->setName('page'),
            'page_size' => v::optional(v::intType()->min(1))->setName('page_size'),
        ]);

        return $this->success(
            $this->userService->getVipRecords(
                $request->user->id,
                $params['page'] ?? DEFAULT_PAGE,
                $params['page_size'] ?? DEFAULT_PAGE_SIZE
            )
        );
    }
}