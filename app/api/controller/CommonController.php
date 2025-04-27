<?php declare(strict_types=1);

namespace app\api\controller;

use support\Controller;
use support\Response;
use Tinywan\Captcha\Captcha;

/**
 * 公共接口
 */
class CommonController extends Controller
{
    /**
     * 获取验证码图片
     * @return Response
     */
    public function captcha(): Response
    {
        return $this->success(Captcha::base64());
    }
}