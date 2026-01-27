<?php declare(strict_types=1);

namespace app\api\controller;

use app\api\service\LoginRegisterService;
use app\api\service\UserService;
use app\model\UserClientConfig;
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
            'user' => $this->userService->getUserInfo($user, $request->header('platform')),
        ]);
    }

    /**
     * 新用户通过邮箱注册
     * @param Request $request
     * @return Response
     */
    public function emailRegister(Request $request): Response
    {
        $params = v::input($request->post(), [
            'username' => v::stringType()->notEmpty()->setName('username'),
            'password' => v::stringType()->notEmpty()->setName('password'),
            'code' => v::stringType()->notEmpty()->setName('code'),
        ]);

        $user = $this->loginRegisterService->emailRegister($params);

        //生成token
        $token = Token::create(['id' => $user->id, 'type' => 'user']);

        return $this->success([
            'token' => $token,
            'user' => $this->userService->getUserInfo($user, $request->header('platform')),
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
            'user' => $this->userService->getUserInfo($user, $request->header('platform')),
        ]);
    }

    /**
     * Bmiss小程序登录
     * @param Request $request
     * @return Response
     */
    public function bmissLogin(Request $request): Response
    {
        $params = v::input($request->post(), [
            'appid' => v::stringType()->notEmpty()->setName('appid'),
            'openid' => v::stringType()->notEmpty()->setName('openid'),
        ]);

        //获取登录的用户
        $user = $this->loginRegisterService->bmissLogin($params);
        //生成token
        $token = Token::create(['id' => $user->id, 'type' => 'user']);

        return $this->success([
            'token' => $token,
            'user' => $this->userService->getUserInfo($user, $request->header('platform')),
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
            $this->userService->getUserInfo($request->user, $request->header('platform'))
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

    /**
     * 绑定用户邀请关系
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken]
    public function bindInviter(Request $request): Response
    {
        ['code' => $code] = v::input($request->post(), [
            'code' => v::stringType()->notEmpty()->setName('code'),
        ]);

        $this->userService->bindInviter($request->user->id, $code);
        return $this->success();
    }

    /**
     * 通过邮箱和验证码重设用户密码并登录
     * @param Request $request
     * @return Response
     */
    public function resetPassword(Request $request): Response
    {
        $params = v::input($request->post(), [
            'username' => v::stringType()->notEmpty()->setName('username'),
            'password' => v::stringType()->notEmpty()->setName('password'),
            'code' => v::stringType()->notEmpty()->setName('code'),
        ]);

        $user = $this->userService->resetPassword($params);

        //生成token
        $token = Token::create(['id' => $user->id, 'type' => 'user']);

        return $this->success([
            'token' => $token,
            'user' => $this->userService->getUserInfo($user, $request->header('platform')),
        ]);
    }

    /**
     * 标记推荐盘口
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken]
    public function mark(Request $request): Response
    {
        [
            'id' => $id,
            'marked' => $marked,
        ] = v::input($request->post(), [
            'id' => v::intType()->positive()->setName('id'),
            'marked' => v::boolType()->setName('marked'),
        ]);

        $this->userService->mark($request->user->id, $id, $marked);
        return $this->success();
    }

    /**
     * 设置用户的客户端配置
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken]
    public function setClientConfig(Request $request): Response
    {
        $params = v::input($request->post(), [
            'rockball_filter' => v::optional(v::arrayType())->setName('rockball_filter'),
        ]);

        $this->userService->setClientConfig($request->user->id, $params);
        return $this->success();
    }

    /**
     * 注销账号
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken]
    public function cancellation(Request $request): Response
    {
        $this->userService->cancellation($request->user);
        return $this->success();
    }

    /**
     * 中止注销账号
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken]
    public function stopCancellation(Request $request): Response
    {
        $this->userService->stopCancellation($request->user->id);
        return $this->success();
    }
}