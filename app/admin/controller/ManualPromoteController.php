<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\ManualPromoteService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckAdminToken;
use support\Controller;
use support\Request;
use support\Response;

/**
 * 手动推荐控制器
 */
class ManualPromoteController extends Controller
{
    #[Inject]
    protected ManualPromoteService $manualPromoteService;

    /**
     * 添加手动推荐记录
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function create(Request $request): Response
    {
        $params = v::input($request->post(), [
            'id' => v::optional(v::intType())->setName('id'),
            'type' => v::in(['single', 'chain'])->setName('type'),
            'odds' => v::arrayType()->each(
                v::arrayType()
                    ->key('match_id', v::intType()->notEmpty()->setName('odd.match_id'))
                    ->key('variety', v::stringType()->in(['goal', 'corner']))
                    ->key('period', v::stringType()->in(['regularTime', 'period1']))
                    ->key('condition', v::stringType()->numericVal())
                    ->key('type', v::stringType()->in(['ah1', 'ah2', 'over', 'under']))
                    ->key('condition2', v::optional(v::stringType()->numericVal()), false)
                    ->key('type2', v::optional(v::stringType()->in(['ah1', 'ah2', 'over', 'under', 'draw'])), false)
            )->notEmpty()->setName('odds'),
        ]);

        $this->manualPromoteService->createManualRecord($params);
        return $this->success();
    }

    /**
     * 删除手动推荐
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function remove(Request $request): Response
    {
        $params = v::input($request->post(), [
            'id' => v::intType()->notEmpty()->setName('id'),
            'odd_id' => v::optional(v::intType())->setName('odd_id'),
        ]);

        $this->manualPromoteService->remove($params['id'], $params['odd_id']);
        return $this->success();
    }

    /**
     * 手动推荐列表
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function list(Request $request): Response
    {
        $params = v::input($request->post(), [
            'page' => v::optional(v::intType()->min(1))->setName('page'),
            'page_size' => v::optional(v::intType()->min(1))->setName('page_size'),
        ]);

        return $this->success(
            $this->manualPromoteService->getList($params)
        );
    }

    /**
     * 手动推荐统计
     * @return Response
     */
    #[CheckAdminToken]
    public function summary(): Response
    {
        return $this->success(
            $this->manualPromoteService->getSummary()
        );
    }
}