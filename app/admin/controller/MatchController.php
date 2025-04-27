<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\MatchService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
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
                    ->key('score1', v::intType()->min(0), false)
                    ->key('score2', v::intType()->min(0), false)
                    ->key('corner1', v::intType()->min(0), false)
                    ->key('corner2', v::intType()->min(0), false)
                    ->key('score1_period1', v::intType()->min(0))
                    ->key('score2_period1', v::intType()->min(0))
                    ->key('corner1_period1', v::intType()->min(0))
                    ->key('corner2_period1', v::intType()->min(0))
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