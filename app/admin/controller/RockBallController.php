<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\RockBallService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckAdminToken;
use support\Controller;
use support\Request;
use support\Response;

class RockBallController extends Controller
{
    #[Inject]
    protected RockBallService $rockBallService;

    /**
     * 获取盘口列表
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function getList(Request $request): Response
    {
        $params = v::input($request->post(), [
            'start_date' => v::optional(v::stringType()->date())->setName('start_date'),
            'end_date' => v::optional(v::stringType()->date())->setName('end_date'),
            'period' => v::optional(v::stringType()->in(['regularTime', 'period1']))->setName('period'),
            'variety' => v::optional(v::stringType()->in(['goal', 'corner']))->setName('variety'),
            'type' => v::optional(v::stringType()->in(['ah1', 'ah2', 'under', 'over']))->setName('type'),
            'promote' => v::optional(v::in([0, 1]))->setName('promote'),
            'order' => v::optional(v::in(['match_time', 'ready_time', 'promote_time']))->setName('order'),
        ]);

        return $this->success(
            $this->rockBallService->getOddList($params)
        );
    }

    /**
     * 获取盘口列表
     * @param Request $request
     * @return Response
     */
    public function exportList(Request $request): Response
    {
        $params = v::input($request->post(), [
            'start_date' => v::optional(v::stringType()->date())->setName('start_date'),
            'end_date' => v::optional(v::stringType()->date())->setName('end_date'),
            'period' => v::optional(v::stringType()->in(['regularTime', 'period1']))->setName('period'),
            'variety' => v::optional(v::stringType()->in(['goal', 'corner']))->setName('variety'),
            'type' => v::optional(v::stringType()->in(['ah1', 'ah2', 'under', 'over']))->setName('type'),
            'promoted' => v::optional(v::in([0, 1, '0', '1']))->setName('promoted'),
            'order' => v::optional(v::in(['match_time', 'ready_time']))->setName('order'),
        ]);

        $filePath = $this->rockBallService->exportList($params);
        $resp = new Response();
        $resp->download($filePath, basename($filePath));
        return $resp;
    }

    /**
     * 设置是否开启推送
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function setIsOpen(Request $request): Response
    {
        [
            'id' => $id,
            'is_open' => $is_open,
        ] = v::input($request->post(), [
            'id' => v::intType()->notEmpty()->setName('id'),
            'is_open' => v::in([0, 1])->setName('is_open'),
        ]);

        $this->rockBallService->setIsOpen($id, $is_open);
        return $this->success();
    }
}