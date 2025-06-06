<?php declare(strict_types=1);

namespace app\api\controller;

use app\api\service\UserService;
use Carbon\Carbon;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckUserToken;
use support\Controller;
use support\Request;
use support\Response;
use Tinywan\Captcha\Captcha;

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
            'code_key' => v::stringType()->notEmpty()->setName('code_key'),
            'code' => v::stringType()->notEmpty()->setName('code'),
        ]);

        //校验验证码
        if (!Captcha::check($params['code'], $params['code_key'])) {
            return $this->fail('验证码错误');
        }

        return $this->success(
            $this->userService->login($params)
        );
    }

    /**
     * Luffa用户登录
     * @param Request $request
     * @return Response
     */
    public function luffaLogin(Request $request): Response
    {
        $params = v::input($request->post(), [
            'network' => v::in(['endless', 'eds'])->setName('network'),
            'info' => v::arrayType()->notEmpty()->setName('info'),
        ]);

        $params['info'] = v::input($params['info'], [
            'uid' => v::stringType()->notEmpty()->setName('info.uid'),
            'avatar' => v::optional(v::stringType())->setName('info.avatar'),
            'cid' => v::optional(v::stringType())->setName('info.cid'),
            'nickname' => v::optional(v::stringType())->setName('info.nickname'),
            'address' => v::optional(v::stringType())->setName('info.address'),
        ]);

        return $this->success(
            $this->userService->luffaLogin($params['network'], $params['info'])
        );
    }

    /**
     * 用户注册
     * @param Request $request
     * @return Response
     */
    public function register(Request $request): Response
    {
        $params = v::input($request->post(), [
            'username' => v::stringType()->notEmpty()->setName('username'),
            'password' => v::stringType()->notEmpty()->setName('password'),
            'code_key' => v::stringType()->notEmpty()->setName('code_key'),
            'code' => v::stringType()->notEmpty()->setName('code'),
            'invite_code' => v::optional(v::stringType())->setName('invite_code'),
        ]);

        //校验验证码
        if (!Captcha::check($params['code'], $params['code_key'])) {
            return $this->fail('验证码错误');
        }

        return $this->success(
            $this->userService->register($params)
        );
    }

    /**
     * 获取当前用户的信息
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken]
    public function info(Request $request): Response
    {
        $user = $request->user;
        if (is_string($user->expire_time)) {
            $expireTime = Carbon::parse($user->expire_time);
        } else {
            $expireTime = $user->expire_time;
        }
        $user->is_expired = $expireTime->unix() <= time();

        return $this->success(
            $user
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