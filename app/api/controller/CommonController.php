<?php declare(strict_types=1);

namespace app\api\controller;

use app\model\ClientVersion;
use app\model\ClientVersionBuild;
use app\model\LuffaGame;
use app\model\UserConnect;
use Carbon\Carbon;
use GatewayWorker\Lib\Gateway;
use Respect\Validation\Validator as v;
use support\Controller;
use support\Redis;
use support\Request;
use support\Response;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use support\storage\Storage;
use Symfony\Component\Yaml\Yaml;
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

    /**
     * 桌面版检查更新
     * @param Request $request
     * @return Response
     */
    public function checkDesktopUpdate(Request $request): Response
    {
        $platform = $request->get('platform', $request->header('platform'));
        $arch = $request->get('arch', $request->header('arch'));
        $version_number = get_version_number(
            $request->get('version', $request->header('version'))
        );

        $version = ClientVersionBuild::query()
            ->join('client_version', 'client_version_build.client_version_id', '=', 'client_version.id')
            ->where('client_version.platform', '=', $platform)
            ->when(!empty($arch), fn($query) => $query->where('client_version.arch', '=', $arch))
            ->where('client_version.version_number', '>=', $version_number)
            ->where('client_version.status', '=', 1)
            ->whereNull('client_version.deleted_at')
            ->orderBy('client_version.version_number', 'DESC')
            ->first([
                'client_version.id',
                'client_version.version',
                'client_version.version_number',
                'client_version.is_mandatory',
                'client_version_build.hot_update_info',
                'client_version_build.updated_at',
            ]);

        if (!$version) {
            if (empty($request->method() === 'POST')) {
                return $this->success();
            } else {
                return \response();
            }
        }

        //判断是否需要强制更新
        if (!$version->is_mandatory && $version->version_number > $version_number) {
            $exists = ClientVersion::query()
                ->where('client_version.platform', '=', $platform)
                ->when(!empty($arch), fn($query) => $query->where('client_version.arch', '=', $arch))
                ->where('client_version.version_number', '>', $version_number)
                ->where('client_version.version_number', '<', $version->version_number)
                ->where('client_version.status', '=', 1)
                ->where('client_version.is_mandatory', '=', 1)
                ->exists();
            if ($exists) {
                $version->is_mandatory = 1;
            }
        }

        $url = Storage::getUrl($version->hot_update_info['path']);

        //输出yaml
        $result = [
            'version' => $version->version,
            'files' => [
                [
                    'url' => $url,
                    'sha512' => $version->hot_update_info['hash'],
                    'size' => $version->hot_update_info['size'],
                ]
            ],
            'path' => $url,
            'sha512' => $version->hot_update_info['hash'],
            'releaseDate' => $version->updated_at->toISOString(),
            'releaseNotes' => $version->is_mandatory ? '1' : null,
        ];
        return response(Yaml::dump($result, 2, 2), 200, ['Content-Type' => 'application/yaml']);
    }

    /**
     * 向指定的WS连接发送消息
     * @param Request $request
     * @return Response
     */
    public function sendSocketMessage(Request $request): Response
    {
        $params = v::input($request->post(), [
            'type' => v::in(['uid', 'group'])->setName('type'),
            'target' => v::anyOf(
                v::arrayType()->notEmpty(),
                v::stringType()->notEmpty(),
                v::intType()->positive(),
            )->setName('target'),
            'message' => v::arrayType()
                ->key('type', v::stringType()->notEmpty())
                ->setName('message'),
        ]);

        $message = json_enc($params['message']);
        switch ($params['type']) {
            case 'uid':
                Gateway::sendToUid($params['target'], $message);
                break;
            case 'group':
                Gateway::sendToGroup($params['target'], $message);
                break;
        }

        return $this->success();
    }

    /**
     * 获取Luffa小游戏列表
     * @return Response
     */
    public function luffaGameList(): Response
    {
        $list = LuffaGame::query()
            ->where('is_visible', '=', 1)
            ->orderBy('sort', 'DESC')
            ->orderBy('id', 'DESC')
            ->get([
                'id',
                'app_id',
                'app_entry',
                'img_path',
                'name'
            ])
            ->toArray();

        array_walk($list, function (&$item) {
            if (!empty($item['img_path'])) {
                $item['img_path'] = Storage::getUrl($item['img_path']);
            }
        });

        return $this->success($list);
    }
}