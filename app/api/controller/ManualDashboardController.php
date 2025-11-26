<?php declare(strict_types=1);

namespace app\api\controller;

use app\api\service\ManualDashboardService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckUserToken;
use support\Controller;
use support\Request;
use support\Response;

/**
 * 手动推荐数据接口
 */
class ManualDashboardController extends Controller
{
    #[Inject]
    protected ManualDashboardService $manualDashboardService;

    /**
     * 按推荐顺序获取手动推荐数据
     * @param Request $request
     * @return Response
     */
    #[CheckUserToken(true)]
    public function promotedById(Request $request): Response
    {
        $params = v::input($request->post(), [
            'start_date' => v::stringType()->date()->setName('start_date'),
            'last_id' => v::optional(v::intType())->setName('last_id'),
        ]);

        $list = $this->manualDashboardService->promotedById($params, $request->user);

        return $this->success([
            'is_expired' => $request->user?->is_expired ?? 0,
            'list' => $list,
            'summary' => $this->manualDashboardService->summary()
        ]);
    }
}