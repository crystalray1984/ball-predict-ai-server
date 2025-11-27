<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\MatchService;
use app\model\Match1;
use app\model\Tournament;
use Carbon\Carbon;
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
            'label_id' => v::optional(v::intType())->setName('label_id'),
            'order_field' => v::optional(v::stringType())->setName('order_field'),
            'order_order' => v::optional(v::in(['asc', 'desc']))->setName('order_order'),
        ]);

        return $this->success(
            $this->matchService->getTournamentList($params)
        );
    }

    /**
     * 切换赛事的开启和关闭
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function toggleTournamentOpen(Request $request): Response
    {
        $params = v::input($request->post(), [
            'id' => v::intType()->min(1)->setName('id'),
            'is_open' => v::in([0, 1])->setName('is_open'),
        ]);

        Tournament::query()
            ->where('id', '=', $params['id'])
            ->update(['is_open' => $params['is_open']]);

        return $this->success();
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
            'status' => v::optional(v::arrayType()->each(v::in(['', 'final'])))->setName('status'),
        ]);

        return $this->success(
            $this->matchService->getMatchList($params)
        );
    }

    /**
     * 获取单个比赛信息
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function getMatch(Request $request): Response
    {
        $params = v::input($request->post(), [
            'id' => v::intType()->min(1)->setName('id'),
        ]);

        return $this->success(
            $this->matchService->getMatch($params['id'])
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
            'error_status' => v::stringType()->in([
                '',
                'delayed',
                'cancelled',
                'interrupted'
            ])->setName('error_status'),
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
     * 修改比赛时间
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function setMatchTime(Request $request): Response
    {
        [
            'id' => $id,
            'match_time' => $match_time,
        ] = v::input($request->post(), [
            'id' => v::intType()->positive()->setName('id'),
            'match_time' => v::intType()->positive()->setName('match_time'),
        ]);

        Match1::withoutTimestamps(function ($query) use ($id, $match_time) {
            $query->where('id', '=', $id)
                ->update(['match_time' => Carbon::createFromTimestampMs($match_time)->toISOString()]);
        });

        return $this->success();
    }

    /**
     * 设置赛果
     * @param Request $request
     * @return Response
     */
    public function setMatchScore(Request $request): Response
    {
        $params = $request->post();

        //常规参数检查
        v::input($params, [
            'match_id' => v::intType()->notEmpty()->setName('match_id'),
            'score1_period1' => v::intType()->min(0)->setName('score1_period1'),
            'score2_period1' => v::intType()->min(0)->setName('score2_period1'),
            'corner1_period1' => v::intType()->min(0)->setName('corner1_period1'),
            'corner2_period1' => v::intType()->min(0)->setName('corner2_period1'),
            'period1' => v::boolType()->setName('period1'),
        ]);

        if (!$params['period1']) {
            //全场赛果
            v::input($params, [
                'score1' => v::intType()->min(0)->setName('score1'),
                'score2' => v::intType()->min(0)->setName('score2'),
                'corner1' => v::intType()->min(0)->setName('corner1'),
                'corner2' => v::intType()->min(0)->setName('corner2'),
            ]);
        }

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

    /**
     * 获取联赛标签列表
     * @return Response
     */
    #[CheckAdminToken]
    public function getTournamentLabelList(): Response
    {
        return $this->success(
            $this->matchService->getTournamentLabelList()
        );
    }

    /**
     * 保存标签
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function saveTournamentLabel(Request $request): Response
    {
        $data = v::input($request->post(), [
            'id' => v::optional(v::intType())->setName('id'),
            'luffa_uid' => v::stringType()->notEmpty()->setName('luffa_uid'),
            'luffa_type' => v::in([0, 1])->setName('luffa_type'),
            'title' => v::stringType()->notEmpty()->setName('title'),
        ]);

        $this->matchService->saveTournamentLabel($data);
        return $this->success();
    }

    /**
     * 删除联赛标签
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function deleteTournamentLabel(Request $request): Response
    {
        [
            'id' => $id,
        ] = v::input($request->post(), [
            'id' => v::intType()->notEmpty()->setName('id'),
        ]);

        $this->matchService->deleteTournamentLabel($id);
        return $this->success();
    }

    /**
     * 设置联赛标签
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function setTournamentLabel(Request $request): Response
    {
        [
            'label_id' => $label_id,
            'tournament_id' => $tournament_id,
        ] = v::input($request->post(), [
            'label_id' => v::intType()->setName('label_id'),
            'tournament_id' => v::anyOf(
                v::intType()->notEmpty(),
                v::arrayType()->notEmpty()->each(v::intType()->notEmpty())
            )->setName('tournament_id'),
        ]);

        $this->matchService->setTournamentLabel($label_id, $tournament_id);
        return $this->success();
    }
}