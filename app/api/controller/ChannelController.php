<?php declare(strict_types=1);

namespace app\api\controller;

use app\api\service\DataReportService;
use app\api\service\DataService;
use app\model\PromotedView;
use Carbon\Carbon;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckUserToken;
use support\Controller;
use support\Request;
use support\Response;

/**
 * 推荐频道控制器
 */
class ChannelController extends Controller
{
    #[Inject]
    protected DataService $dataService;

    #[Inject]
    protected DataReportService $dataReportService;

    /**
     * 获取频道列表
     * @return Response
     */
    public function list(): Response
    {
        return $this->success(config('channel'));
    }

    /**
     * 获取频道数据
     * @param Request $request
     * @param string $channel
     * @return Response
     */
    #[CheckUserToken(true)]
    public function data(Request $request, string $channel): Response
    {
        $platform = $request->header('platform');

        //基于platform计算过期时间
        $userId = 0;

        /** @var Carbon|null $expireTime */
        $expireTime = null;

        $isExpired = 1;

        if ($request->user) {
            $userId = $request->user->id;
            if (in_array($platform, ['ios', 'android'])) {
                $expireTime = Carbon::now()->addDay();
            } else {
                $expireTime = $request->user->expire_time;
            }
            $isExpired = $expireTime->unix() >= time() ? 0 : 1;
        }

        //推荐数据
        $list = $this->dataService->promotedByCrownDate([$channel], $userId, $expireTime);
        //统计数据
        $summary = $this->dataService->summary([$channel]);
        //测算中数据
        $preparing = match ($channel) {
            'rockball' => $this->dataService->rockballPreparing('rockball'),
            'rockball2' => $this->dataService->rockballPreparing('rockball2'),
            'mansion' => $this->dataService->mansionPreparing(),
            default => [],
        };

        //返回数据
        return $this->success([
            'is_expired' => $isExpired,
            'list' => $list,
            'summary' => $summary,
            'preparing' => $preparing,
        ]);
    }

    /**
     * 获取频道报告
     * @param Request $request
     * @param string $channel
     * @return Response
     */
    public function report(Request $request, string $channel): Response
    {
        ['time' => $time] = v::input($request->post(), [
            'time' => v::optional(v::intType())->setName('time'),
        ]);

        //基于channel计算最早的数据
        $minRow = PromotedView::query()
            ->where('channel', '=', $channel)
            ->whereNotNull('result')
            ->orderBy('match_time')
            ->first(['match_time']);
        if (!$minRow) {
            //这个频道没有数据
            return $this->success([
                'list' => [],
                'next' => 0,
            ]);
        }

        $minDate = crown_week($minRow->match_time)->unix();
        $week = crown_week($time ? Carbon::createFromTimestamp($time) : null)->unix();
        $max = 5;

        $list = [];

        while ($max > 0 && $week >= $minDate) {
            $list[] = $this->dataReportService->getDataReport($channel, $week);
            $week -= 7 * 86400;
            $max--;
        }

        $next = $week >= $minDate ? $week : 0;

        return $this->success([
            'list' => $list,
            'next' => $next,
        ]);
    }
}