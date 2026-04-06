<?php declare(strict_types=1);

namespace app\api\controller;

use app\api\service\AutoBetService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckUserToken;
use support\Controller;
use support\Request;
use support\Response;

/**
 * 自动投注接口
 */
class AutoBetController extends Controller
{
    #[Inject]
    protected AutoBetService $service;

    /**
     * 获取自动投注记录
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken]
    public function list(Request $request): Response
    {
        $params = v::input($request->post(), [
            'channel' => v::optional(v::stringType())->setName('channel'),
            'crown_uid' => v::optional(v::stringType())->setName('crown_uid'),
            'start_date' => v::optional(v::stringType()->date())->setName('start_date'),
            'end_date' => v::optional(v::stringType()->date())->setName('end_date'),
            'page' => v::optional(v::intType()->min(1))->setName('page'),
            'page_size' => v::optional(v::intType()->min(1))->setName('page_size'),
        ]);

        return $this->success(
            $this->service->getRecords($request->user->id, $params)
        );
    }

    /**
     * 添加自动投注记录
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken]
    public function add(Request $request): Response
    {
        $params = v::input($request->post(), [
            'crown_uid' => v::optional(v::stringType())->setName('crown_uid'),
            'promote_id' => v::intType()->greaterThan(0)->setName('promote_id'),
            'bet_value' => v::numericVal()->setName('bet_value'),
            'bet_amount' => v::numericVal()->setName('bet_amount'),
            'extra' => v::arrayType()->setName('extra'),
        ]);

        return $this->success(
            $this->service->addRecord($request->user->id, $params)
        );
    }

    /**
     * 在自动下注之前，做一些逻辑判断，确定要不要买
     * @param Request $request
     * @return Response
     */
    public function before(Request $request): Response
    {
        $params = v::input($request->post(), [
            'channel' => v::stringType()->notEmpty()->setName('promote_id'),
            'match_time' => v::stringType()->notEmpty()->setName('match_time'),
        ]);

//        $params['user_id'] = $request->user?->id ?? 0;
        return $this->success(
            $this->service->beforeBet($params)
        );
    }
}