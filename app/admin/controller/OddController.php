<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\OddService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckAdminToken;
use support\Controller;
use support\Request;
use support\Response;

/**
 * 盘口管理接口
 */
class OddController extends Controller
{
    #[Inject]
    protected OddService $oddService;

    /**
     * 获取盘口抓取数据
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function getOddList(Request $request): Response
    {
        $params = v::input($request->post(), [
            'start_date' => v::optional(v::stringType()->date())->setName('start_date'),
            'end_date' => v::optional(v::stringType()->date())->setName('end_date'),
            'variety' => v::optional(v::in(['goal', 'corner']))->setName('variety'),
            'period' => v::optional(v::in(['period1', 'regularTime']))->setName('period'),
            'matched1' => v::optional(v::in([0, 1, -1]))->setName('matched1'),
            'matched2' => v::optional(v::in([0, 1, -1, 'titan007', 'crown', 'crown_special']))->setName('matched2'),
            'promoted' => v::optional(v::in([0, 1, 2, -1]))->setName('promoted'),
        ]);

        return $this->success(
            $this->oddService->getOddList($params)
        );
    }

    /**
     * 导出数据
     * @param Request $request
     * @return Response
     */
    public function exportOddList(Request $request): Response
    {
        $params = v::input($request->post(), [
            'start_date' => v::optional(v::stringType()->date())->setName('start_date'),
            'end_date' => v::optional(v::stringType()->date())->setName('end_date'),
            'variety' => v::optional(v::in(['goal', 'corner']))->setName('variety'),
            'period' => v::optional(v::in(['period1', 'regularTime']))->setName('period'),
            'matched1' => v::optional(v::in(['0', '1', '-1']))->setName('matched1'),
            'matched2' => v::optional(v::in(['0', '1', '-1', 'crown', 'crown_special']))->setName('matched2'),
            'promoted' => v::optional(v::in(['0', '1', '2', '-1']))->setName('promoted'),
        ]);

        //数据整理
        if (isset($params['matched1'])) {
            $params['matched1'] = intval($params['matched1']);
        }
        if (in_array($params['matched2'], ['0', '1', '-1'])) {
            $params['matched2'] = intval($params['matched2']);
        }
        if (isset($params['promoted'])) {
            $params['promoted'] = intval($params['promoted']);
        }

        $filePath = $this->oddService->exportOddList(
            $this->oddService->getOddList($params, true)
        );

        $resp = new Response();
        $resp->download($filePath, basename($filePath));
        return $resp;
    }

    /**
     * 通过比赛获取盘口列表
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function getOddsByMatch(Request $request): Response
    {
        $params = v::input($request->post(), [
            'match_id' => v::intType()->min(1)->setName('match_id'),
        ]);

        return $this->success(
            $this->oddService->getOddsByMatch($params['match_id'])
        );
    }

    /**
     * 补充推荐
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function addOdd(Request $request): Response
    {
        $params = v::input($request->post(), [
            'match_id' => v::intType()->min(1)->setName('match_id'),
            'odd_id' => v::intType()->min(1)->setName('odd_id'),
            'back' => v::in([0, 1])->setName('back'),
        ]);

        $this->oddService->add($params);
        return $this->success();
    }

    /**
     * 删除推荐
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function removePromoted(Request $request): Response
    {
        $params = v::input($request->post(), [
            'id' => v::intType()->min(1)->setName('id'),
        ]);

        $this->oddService->removePromoted($params['id']);
        return $this->success();
    }
}