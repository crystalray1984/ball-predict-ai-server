<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\BmissBetService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckAdminToken;
use support\Controller;
use support\Request;
use support\Response;

/**
 * Bmiss投注控制器
 */
class BmissBetController extends Controller
{
    #[Inject]
    protected BmissBetService $service;

    /**
     * 获取用户列表
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function getUsers(Request $request): Response
    {
        $params = v::input($request->post(), [
            'appid' => v::optional(v::stringType())->setName('appid'),
            'openid' => v::optional(v::stringType())->setName('openid'),
            'nickname' => v::optional(v::stringType())->setName('nickname'),
            'order_field' => v::optional(v::in(['id', 'last_login_at', 'profit']))->setName('order_field'),
            'order_sort' => v::optional(v::in(['asc', 'desc']))->setName('order_sort'),
            'page' => v::optional(v::intType()->greaterThan(0))->setName('page'),
            'page_size' => v::optional(v::intType()->greaterThan(0))->setName('page_size'),
        ]);

        return $this->success(
            $this->service->getUsers($params)
        );
    }

    /**
     * 获取投注记录列表
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function getBetRecords(Request $request): Response
    {
        $params = v::input($request->post(), [
            'match_id' => v::optional(v::intType())->setName('match_id'),
            'user_id' => v::optional(v::intType())->setName('user_id'),
            'appid' => v::optional(v::stringType())->setName('appid'),
            'openid' => v::optional(v::stringType())->setName('openid'),
            'paid' => v::optional(v::boolType())->setName('paid'),
            'result' => v::optional(v::in([-1, 0, 1, '']))->setName('result'),
            'page' => v::optional(v::intType()->greaterThan(0))->setName('page'),
            'page_size' => v::optional(v::intType()->greaterThan(0))->setName('page_size'),
        ]);

        return $this->success(
            $this->service->getBetRecords($params)
        );
    }

    /**
     * 查询没有比赛覆盖的时间段
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function getEmptyTimeRange(Request $request): Response
    {
        $params = v::input($request->post(), [
            'start_date' => v::stringType()->date()->setName('start_date'),
            'end_date' => v::stringType()->date()->setName('end_date'),
        ]);

        return $this->success($this->service->getEmptyRange($params['start_date'], $params['end_date']));
    }
}