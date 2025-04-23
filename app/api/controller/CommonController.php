<?php declare(strict_types=1);

namespace app\api\controller;

use support\attribute\AllowGuest;
use support\Controller;
use support\Response;
use Tinywan\Captcha\Captcha;

/**
 * 公共接口
 */
#[AllowGuest]
class CommonController extends Controller
{
    /**
     * 获取验证码图片
     * @return Response
     */
    #[AllowGuest]
    public function captcha(): Response
    {
        return $this->success(Captcha::base64());
    }
}