<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\OddV3Service;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\Controller;
use support\Request;
use support\Response;

class OddV3Controller extends Controller
{
    #[Inject]
    protected OddV3Service $oddV3Service;

    /**
     * 读取比赛列表
     * @param Request $request
     * @return Response
     */
    public function getMatchList(Request $request): Response
    {
        $params = v::input($request->post(), [
            'team' => v::optional(v::stringType())->setName('team'),
            'start_date' => v::optional(v::stringType()->date())->setName('start_date'),
            'end_date' => v::optional(v::stringType()->date())->setName('end_date'),
            'ready_status' => v::optional(v::in([0, 1, -1]))->setName('ready_status'),
            'promoted' => v::optional(v::in([0, 1, -1]))->setName('promoted'),
        ]);

        return $this->success($this->oddV3Service->getMatchList($params));
    }

    /**
     * 读取盘口追踪记录
     * @param Request $request
     * @return Response
     */
    public function getOddRecords(Request $request): Response
    {
        $params = v::input($request->post(), [
            'match_id' => v::intType()->notEmpty()->setName('match_id'),
            'type' => v::in(['ah', 'sum'])->setName('type'),
        ]);

        return $this->success($this->oddV3Service->getOddRecords($params['match_id'], $params['type']));
    }
}