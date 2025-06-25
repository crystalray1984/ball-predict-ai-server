<?php declare(strict_types=1);

namespace app\api\controller;

use app\api\service\CommissionService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckUserToken;
use support\Controller;
use support\Request;
use support\Response;

/**
 * 佣金接口
 */
class CommissionController extends Controller
{
    #[Inject]
    protected CommissionService $commissionService;

    /**
     * 获取佣金配置
     * @return Response
     */
    public function config(): Response
    {
        return $this->success(config('commission'));
    }

    /**
     * 获取用户佣金
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken]
    public function get(Request $request): Response
    {
        return $this->success([
            'usable' => $request->user->commission,
            'incoming' => $this->commissionService->getUserIncomingCommission($request->user->id),
        ]);
    }

    /**
     * 获取用户的佣金变更记录
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken]
    public function getRecords(Request $request): Response
    {
        $params = v::input($request->post(), [
            'type' => v::optional(v::arrayType()->each(v::stringType()->notEmpty())->notEmpty())->setName('type'),
            'page' => v::optional(v::intType()->min(1))->setName('page'),
            'page_size' => v::optional(v::intType()->min(1))->setName('page_size'),
        ]);

        return $this->success(
            $this->commissionService->getChangeList(
                $request->user->id,
                $params
            )
        );
    }

    /**
     * 获取用户的佣金收益记录
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken]
    public function getList(Request $request): Response
    {
        $params = v::input($request->post(), [
            'settled' => v::optional(v::in([-1, 0, 1]))->setName('settled'),
            'page' => v::optional(v::intType()->min(1))->setName('page'),
            'page_size' => v::optional(v::intType()->min(1))->setName('page_size'),
            'client_type' => v::optional(v::stringType()->notEmpty())->setName('client_type'),
        ]);

        return $this->success(
            $this->commissionService->getUserCommissionList(
                $request->user->id,
                $params,
            )
        );
    }

    /**
     * 佣金提现
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken]
    public function withdrawal(Request $request): Response
    {
        $params = v::input($request->post(), [
            'amount' => v::intType()->min(1)->setName('amount'),
            'channel_type' => v::stringType()->notEmpty()->setName('channel_type'),
            'withdrawal_account' => v::stringType()->notEmpty()->setName('withdrawal_account'),
        ]);

        $this->commissionService->withdrawal(
            $request->user->id,
            $params,
        );
        return $this->success();
    }
}