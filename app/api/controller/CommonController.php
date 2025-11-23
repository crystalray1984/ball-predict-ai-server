<?php declare(strict_types=1);

namespace app\api\controller;

use app\model\UserConnect;
use Respect\Validation\Validator as v;
use support\Controller;
use support\Redis;
use support\Request;
use support\Response;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
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

    /**
     * 发送邮件验证码
     * @param Request $request
     * @return Response
     */
    public function sendEmailCode(Request $request): Response
    {
        $params = v::input($request->post(), [
            'email' => v::stringType()->notEmpty()->email()->setName('email'),
            'check_exists' => v::optional(v::boolType())->setName('check_exists'),
        ]);

        if ($params['check_exists']) {
            //校验邮箱是否存在
            $connect = UserConnect::query()
                ->where('platform', '=', 'email')
                ->where('account', '=', $params['email'])
                ->first(['id']);
            if ($connect) {
                return $this->fail('此邮箱已被使用');
            }
        }

        //生成验证码
        $code = str_pad((string)rand(1, 9999), 4, '0', STR_PAD_LEFT);

        //写入缓存
        Redis::setEx('email_code:' . $params['email'], 600, $code);

        //发送验证码邮件
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = config('mail.transport.host');
        $mail->SMTPAuth = true;
        $mail->Username = config('mail.transport.username');
        $mail->Password = config('mail.transport.password');
        $mail->Port = config('mail.transport.port');
        $mail->SMTPAutoTLS = false;

        $mail->setFrom(config('mail.from'), 'BallPredictAI');
        $mail->addAddress($params['email']);     //Add a recipient

        $mail->isHTML(true);                                  //Set email format to HTML
        $mail->Subject = 'Your verification code';
        $mail->Body = "Your verification code is <b>$code</b>";
        $mail->AltBody = "Your verification code is $code";
        $mail->send();

        return $this->success();
    }
}