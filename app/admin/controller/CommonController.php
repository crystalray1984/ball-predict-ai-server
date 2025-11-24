<?php declare(strict_types=1);

namespace app\admin\controller;

use Respect\Validation\Validator as v;
use support\attribute\CheckAdminToken;
use support\Controller;
use support\Request;
use support\Response;
use support\storage\Storage;

class CommonController extends Controller
{
    /**
     * 获取上传表单数据
     * @param Request $request
     * @return Response
     */
    #[CheckAdminToken]
    public function createUploadForm(Request $request): Response
    {
        $params = v::input($request->post(), [
            'type' => v::stringType()->notEmpty()->setName('type'),
            'filename' => v::stringType()->notEmpty()->setName('filename'),
        ]);

        $dot = strrpos($params['filename'], '.');
        $ext = substr($params['filename'], $dot);
        $remotePath = $params['type'] . '/' . uniqid() . $ext;

        return $this->success(
            Storage::instance()->getUploadForm($remotePath)
        );
    }
}