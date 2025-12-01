<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\UserService;
use app\model\UserConnect;
use Carbon\Carbon;
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
            'luffa_id' => v::optional(v::stringType())->setName('luffa_id'),
            'email' => v::optional(v::stringType())->setName('email'),
            'register_date_start' => v::optional(v::stringType()->date())->setName('register_date_start'),
            'register_date_end' => v::optional(v::stringType()->date())->setName('register_date_end'),
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
     * 设置用户的状态
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function setStatus(Request $request): Response
    {
        $params = v::input($request->post(), [
            'user_id' => v::intType()->min(1)->setName('user_id'),
            'status' => v::in([0, 1])->setName('status'),
        ]);
        $this->userService->setStatus($params['user_id'], $params['status']);
        return $this->success();
    }

    /**
     * 设置用户的有效期
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function setExpireTime(Request $request): Response
    {
        $params = v::input($request->post(), [
            'user_id' => v::intType()->min(1)->setName('user_id'),
            'days' => v::optional(v::intType()->min(1))->setName('days'),
            'expire_time' => v::optional(v::stringType()->dateTime())->setName('expire_time'),
        ]);

        //要求days字段和expire_time字段必须传至少一个
        v::oneOf(
            v::key('days', v::intType()->min(1)),
            v::key('expire_time', v::stringType()->dateTime())
        )->check($params);

        //具体的有效期优先
        if (!empty($params['expire_time'])) {
            $result = $this->userService->setExpireTime($params['user_id'], $params['expire_time']);
        } else {
            $result = $this->userService->addExpireTime($params['user_id'], $params['days']);
        }

        return $this->success($result);
    }

    /**
     * 修改用户密码
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function setPassword(Request $request): Response
    {
        $params = v::input($request->post(), [
            'user_id' => v::intType()->min(1)->setName('user_id'),
            'platform' => v::optional(v::in(['email']))->setName('platform'),
            'password' => v::stringType()->notEmpty()->setName('password'),
        ]);

        UserConnect::query()
            ->where('user_id', '=', $params['user_id'])
            ->where('platform', '=', $params['platform'] ?? 'email')
            ->update(['password' => md5($params['password'])]);

        return $this->success();
    }
}