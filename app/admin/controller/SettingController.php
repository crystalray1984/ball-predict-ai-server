<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\SettingService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckAdminToken;
use support\Controller;
use support\Request;
use support\Response;

class SettingController extends Controller
{
    #[Inject]
    protected SettingService $service;

    /**
     * 读取系统配置
     * @return Response
     */
    #[CheckAdminToken]
    public function get(): Response
    {
        return $this->success(
            $this->service->getSettings()
        );
    }

    /**
     * 保存系统配置
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function save(Request $request): Response
    {
        $params = $request->post();
        v::arrayType()->notEmpty()->check($params);
        $this->service->saveSettings($params);
        return $this->success();
    }
}