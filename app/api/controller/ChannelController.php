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
use support\Redis;
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
        $cache = Redis::get("summary:$channel");
        if (!empty($cache)) {
            $summary = json_decode($cache, true);
        } else {
            $summary = $this->dataService->summary([$channel]);
            Redis::setEx("summary:$channel", 300, json_enc($summary));
        }

        //测算中数据
        $preparing = [];
        switch ($channel) {
            case 'rockball':
            case 'rockball2':
            case 'rockball3':
            case 'mansion':
                $cache = Redis::get("preparing:$channel");
                if (!empty($cache)) {
                    $preparing = json_decode($cache, true);
                } else {
                    $preparing = match ($channel) {
                        'rockball' => $this->dataService->rockballPreparing('rockball'),
                        'rockball2' => $this->dataService->rockballPreparing('rockball2'),
                        'rockball3' => $this->dataService->rockballPreparing('rockball3'),
                        'mansion' => $this->dataService->mansionPreparing(),
                    };
                    Redis::setEx("preparing:$channel", 300, json_enc($preparing));
                }
                break;
            default:
                break;
        }

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

    /**
     * 查询完整的数据
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken(true)]
    public function fullData(Request $request): Response
    {
        ['channels' => $channels] = v::input($request->post(), [
            'channels' => v::optional(v::arrayType())->setName('channels'),
        ]);
        if (empty($channels)) {
            $channels = array_column(config('channel'), 'key');
        }

        $result = [
            'last_updated' => Carbon::now()->toISOString(),
            'channels' => [],
        ];

        //读取全数据
        $allPromoted = $this->dataService->allData($channels, $request->user?->id ?? 0);

        foreach ($channels as $channel) {
            //推荐数据
            $list = array_filter($allPromoted, fn($item) => $item['channel'] === $channel);

            //统计数据
            $cache = Redis::get("summary:$channel");
            if (!empty($cache)) {
                $summary = json_decode($cache, true);
            } else {
                $summary = $this->dataService->summary([$channel]);
                Redis::setEx("summary:$channel", 300, json_enc($summary));
            }

            //测算中数据
            $preparing = [];
            switch ($channel) {
                case 'rockball':
                case 'rockball2':
                case 'rockball3':
                case 'mansion':
                    $cache = Redis::get("preparing:$channel");
                    if (!empty($cache)) {
                        $preparing = json_decode($cache, true);
                    } else {
                        $preparing = match ($channel) {
                            'rockball' => $this->dataService->rockballPreparing('rockball'),
                            'rockball2' => $this->dataService->rockballPreparing('rockball2'),
                            'rockball3' => $this->dataService->rockballPreparing('rockball3'),
                            'mansion' => $this->dataService->mansionPreparing(),
                        };
                        Redis::setEx("preparing:$channel", 300, json_enc($preparing));
                    }
                    break;
                default:
                    break;
            }

            $result['channels'][$channel] = [
                'summary' => $summary,
                'list' => $list,
                'preparing' => $preparing,
            ];
        }

        return $this->success($result);
    }

    /**
     * 查询增量数据
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken(true)]
    public function incrementing(Request $request): Response
    {
        [
            'channels' => $channels,
            'last_updated' => $lastUpdated,
        ] = v::input($request->post(), [
            'channels' => v::optional(v::arrayType())->setName('channels'),
            'last_updated' => v::stringType()->dateTime()->setName('last_updated'),
        ]);
        if (empty($channels)) {
            $channels = array_column(config('channel'), 'key');
        }

        $result = [
            'last_updated' => Carbon::now()->toISOString(),
            'channels' => [],
        ];

        //读取全数据
        $allPromoted = $this->dataService->incrementData($channels, $lastUpdated, $request->user?->id ?? 0);

        foreach ($channels as $channel) {
            //推荐数据
            $list = array_filter($allPromoted, fn($item) => $item['channel'] === $channel);
            //统计数据
            $cache = Redis::get("summary:$channel");
            if (!empty($cache)) {
                $summary = json_decode($cache, true);
            } else {
                $summary = $this->dataService->summary([$channel]);
                Redis::setEx("summary:$channel", 300, json_enc($summary));
            }

            //测算中数据
            $preparing = [];
            switch ($channel) {
                case 'rockball':
                case 'rockball2':
                case 'rockball3':
                case 'mansion':
                    $cache = Redis::get("preparing:$channel");
                    if (!empty($cache)) {
                        $preparing = json_decode($cache, true);
                    } else {
                        $preparing = match ($channel) {
                            'rockball' => $this->dataService->rockballPreparing('rockball'),
                            'rockball2' => $this->dataService->rockballPreparing('rockball2'),
                            'rockball3' => $this->dataService->rockballPreparing('rockball3'),
                            'mansion' => $this->dataService->mansionPreparing(),
                        };
                        Redis::setEx("preparing:$channel", 300, json_enc($preparing));
                    }
                    break;
                default:
                    break;
            }

            $result['channels'][$channel] = [
                'summary' => $summary,
                'list' => $list,
                'preparing' => $preparing,
            ];
        }

        return $this->success($result);
    }
}