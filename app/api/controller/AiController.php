<?php declare(strict_types=1);

namespace app\api\controller;

use app\api\service\AiService;
use DI\Attribute\Inject;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator as v;
use support\Controller;
use support\Request;
use support\Response;

/**
 * AI相关控制器
 */
class AiController extends Controller
{
    #[Inject]
    protected AiService $aiService;

    /**
     * 获取需要预测的比赛列表
     * @param Request $request
     * @return Response
     */
    public function getPreparingMatches(Request $request): Response
    {
        $next = $request->get('next');
        if (empty($next) || !is_numeric($next)) {
            $next = 0;
        }

        return $this->success($this->aiService->getPreparingMatches((int)$next));
    }

    /**
     * 第二版获取要预测的比赛列表，带皇冠盘口
     * @return Response
     */
    public function getPreparingMatchesV2(): Response
    {
        return $this->success($this->aiService->getPreparingMatchesV2());
    }

    /**
     * 创建推荐
     * @param Request $request
     * @return Response
     * @throws \AMQPException
     */
    public function createPromotion(Request $request): Response
    {
        $data = v::input($request->post(), [
            'match_id' => v::intType()->greaterThan(0)->setName('match_id'),
            'period' => v::in(['regularTime', 'period1'])->setName('period'),
            'type' => v::in(['ah1', 'ah2', 'over', 'under', 'win1', 'win2', 'draw', 'btts_yes', 'btts_no'])->setName('type'),
        ]);

        if (in_array($data['type'], ['ah1', 'ah2', 'over', 'under'])) {
            //让球盘和大小球盘需要传盘口
            $condition = (string)$request->post('condition');
            v::numericVal()->setName('condition')->check($condition);

            //如果是大小球，盘口必须大于等于0.25
            if (in_array($data['type'], ['over', 'under']) && bccomp($condition, '0.25', 3) < 0) {
                v::alwaysInvalid()->setName('condition')->check($condition);
            }

            //盘口必须是0.25的倍数
            if (bccomp(bcmod($condition, '0.25', 3), '0', 3) !== 0) {
                v::alwaysInvalid()->setName('condition')->check($condition);
            }

            $data['condition'] = $condition;
        } else {
            $data['condition'] = '0';
        }

        return $this->success($this->aiService->createPromotion($data));
    }
}