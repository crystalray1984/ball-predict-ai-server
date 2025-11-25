<?php declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\VersionService;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\attribute\CheckAdminToken;
use support\Controller;
use support\Request;
use support\Response;

/**
 * 版本管理控制器
 */
class VersionController extends Controller
{
    #[Inject]
    protected VersionService $versionService;

    /**
     * 获取客户端版本列表
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function getVersionList(Request $request): Response
    {
        $params = v::input($request->post(), [
            'platform' => v::in(['win32', 'darwin', 'ios', 'android'])->setName('platform'),
            'arch' => v::optional(v::stringType())->setName('arch'),
            'status' => v::optional(v::intType())->setName('status'),
            'page' => v::optional(v::intType()->min(1))->setName('page'),
            'page_size' => v::optional(v::intType()->min(1)->max(100))->setName('page_size'),
        ]);

        $result = match ($params['platform']) {
            'win32', 'darwin' => $this->versionService->getDesktopVersionList($params),
            default => [
                'list' => [],
                'count' => 0,
            ],
        };

        return $this->success($result);
    }

    /**
     * 保存桌面版客户端版本
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function saveDesktopVersion(Request $request): Response
    {
        $data = v::input($request->post(), [
            'id' => v::optional(v::intType())->setName('id'),
            'platform' => v::in(['win32', 'darwin', 'ios', 'android'])->setName('platform'),
            'arch' => v::optional(v::stringType())->setName('arch'),
            'status' => v::in([0, 1])->setName('status'),
            'version' => v::stringType()->version()->setName('version'),
            'is_mandatory' => v::in([0, 1])->setName('is_mandatory'),
            'note' => v::optional(v::stringType())->setName('note'),
            'full_info' => v::optional(
                v::arrayType()
                    ->key('path', v::stringType()->notEmpty()->setName('full_info.path'))
                    ->key('hash', v::stringType()->notEmpty()->setName('full_info.hash'))
                    ->key('size', v::intType()->positive()->setName('full_info.size'))
                    ->key('blockmap', v::stringType()->notEmpty()->setName('full_info.blockmap'))
            )->setName('full_info'),
            'hot_update_info' => v::optional(
                v::arrayType()
                    ->key('path', v::stringType()->notEmpty()->setName('hot_update_info.path'))
                    ->key('hash', v::stringType()->notEmpty()->setName('hot_update_info.hash'))
                    ->key('size', v::intType()->positive()->setName('hot_update_info.size'))
                    ->key('blockmap', v::stringType()->notEmpty()->setName('full_info.blockmap'))
            )->setName('hot_update_info'),
        ]);

        $this->versionService->saveDesktopVersion($data);
        return $this->success();
    }

    #[CheckAdminToken]
    public function deleteVersion(Request $request): Response
    {
        ['id' => $id] = v::input($request->post(), [
            'id' => v::intType()->notEmpty()->setName('id'),
        ]);
        $this->versionService->deleteVersion($id);
        return $this->success();
    }
}