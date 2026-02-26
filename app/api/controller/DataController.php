<?php declare(strict_types=1);

namespace app\api\controller;

use app\api\service\DataService;
use DI\Attribute\Inject;
use support\attribute\CheckUserToken;
use support\Controller;
use support\Request;
use support\Response;

/**
 * 数据控制器
 */
class DataController extends Controller
{
    #[Inject]
    protected DataService $dataService;

    /**
     * 获取滚球数据
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken(true)]
    public function rockball(Request $request): Response
    {
        return $this->success([
            'is_expired' => $request->user?->is_expired ?? 0,
            'list' => $this->dataService->promotedByCrownDate(['rockball'], $request->user?->id ?? 0, $request->user?->expire_time),
            'summary' => $this->dataService->summary(['rockball']),
            'preparing' => $this->dataService->rockballPreparing('rockball'),
        ]);
    }

    /**
     * 获取精选数据
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken(true)]
    public function featured(Request $request): Response
    {
        return $this->success([
            'is_expired' => $request->user?->is_expired ?? 0,
            'list' => $this->dataService->promotedByCrownDate(['direct'], $request->user?->id ?? 0, $request->user?->expire_time),
            'summary' => $this->dataService->summary(['direct']),
            'preparing' => [],
        ]);
    }

    /**
     * 获取综合数据
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken(true)]
    public function synthesis(Request $request): Response
    {
        return $this->success([
            'is_expired' => $request->user?->is_expired ?? 0,
            'list' => $this->dataService->promotedByCrownDate(['mansion'], $request->user?->id ?? 0, $request->user?->expire_time),
            'summary' => $this->dataService->summary(['mansion']),
            'preparing' => $this->dataService->mansionPreparing(),
        ]);
    }
}