<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\MatchService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckAdminToken;
use support\Controller;
use support\Request;
use support\Response;

/**
 * 比赛相关的控制器
 */
class MatchController extends Controller
{
    #[Inject]
    protected MatchService $matchService;

    /**
     * 获取赛事列表
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function getTournamentList(Request $request): Response
    {
        $params = v::input($request->post(), [
            'name' => v::optional(v::stringType())->setName('name'),
        ]);

        return $this->success(
            $this->matchService->getTournamentList($params)
        );
    }

    /**
     * 获取比赛列表
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function getMatchList(Request $request): Response
    {
        $params = v::input($request->post(), [
            'start_date' => v::optional(v::stringType()->date())->setName('start_date'),
            'end_date' => v::optional(v::stringType()->date())->setName('end_date'),
            'tournament_id' => v::optional(v::intType()->min(0))->setName('tournament_id'),
            'team_id' => v::optional(v::intType())->setName('team_id'),
            'team' => v::optional(v::stringType())->setName('team'),
            'page' => v::optional(v::intType()->min(1))->setName('page'),
            'page_size' => v::optional(v::intType()->min(1))->setName('page_size'),
        ]);

        return $this->success(
            $this->matchService->getMatchList($params)
        );
    }

    /**
     * 设置比赛异常状态
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function setMatchErrorStatus(Request $request): Response
    {
        $params = v::input($request->post(), [
            'match_id' => v::intType()->notEmpty()->setName('match_id'),
            'error_status' => v::intType()->min(0)->setName('error_status'),
        ]);

        $this->matchService->setMatchErrorStatus($params['match_id'], $params['error_status']);
        return $this->success();
    }

    /**
     * 批量设置赛果
     * @param Request $request
     * @return Response
     */
    public function multiSetMatchScore(Request $request): Response
    {
        $data = $request->post();
        v::arrayType()
            ->notEmpty()
            ->each(
                v::arrayType()
                    ->key('match_id', v::intType()->notEmpty())
                    ->key('period1', v::boolType())
            )
            ->check($data);

        $this->matchService->multiSetMatchScore($data);
        return $this->success();
    }

    /**
     * 设置赛果
     * @param Request $request
     * @return Response
     */
    public function setMatchScore(Request $request): Response
    {
        $params = v::input($request->post(), [
            'match_id' => v::intType()->notEmpty()->setName('match_id'),
            'score1' => v::intType()->min(0)->setName('score1'),
            'score2' => v::intType()->min(0)->setName('score2'),
            'corner1' => v::intType()->min(0)->setName('corner1'),
            'corner2' => v::intType()->min(0)->setName('corner2'),
            'score1_period1' => v::intType()->min(0)->setName('score1_period1'),
            'score2_period1' => v::intType()->min(0)->setName('score2_period1'),
            'corner1_period1' => v::intType()->min(0)->setName('corner1_period1'),
            'corner2_period1' => v::intType()->min(0)->setName('corner2_period1'),
            'period1' => v::boolType()->setName('period1'),
        ]);

        $this->matchService->setMatchScore($params);
        return $this->success();
    }

    /**
     * 获取需要获取赛果的比赛
     * @return Response
     */
    public function getRequireScoreMatches(): Response
    {
        return $this->success(
            $this->matchService->getRequireScoreMatches()
        );
    }
}