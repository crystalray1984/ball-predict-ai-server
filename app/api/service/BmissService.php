<?php declare(strict_types=1);

namespace app\api\service;

use app\model\User;
use app\model\UserConnect;
use DI\Attribute\Inject;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use stdClass;
use support\Db;
use support\exception\BusinessError;
use Throwable;

class BmissService
{
    #[Inject]
    protected LoginRegisterService $loginRegisterService;

    /**
     * 调用Bmiss接口
     * @param string $url
     * @param array $data
     * @return array
     */
    public function api(string $url, array $data = []): array
    {
        //构建请求体
        $body = [
            'appid' => config('bmiss.appid'),
            'request_number' => md5(uniqid('', true)),
            'timestamp' => time(),
            'data' => !empty($data) ? $data : new stdClass(),
        ];

        //转换为JSON字符串
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        //计算签名
        $signature = md5(md5($json . config('bmiss.app_secret')));

        //执行请求
        $client = new Client([
            'base_uri' => config('bmiss.api_url'),
        ]);
        $resp = $client->post($url, [
            RequestOptions::QUERY => [
                'signature' => $signature,
            ],
            RequestOptions::HEADERS => [
                'Content-Type' => 'application/json',
            ],
            'body' => $json,
        ]);
        return json_decode($resp->getBody()->getContents(), true);
    }

    /**
     * Bmiss小程序登录
     * @param array{
     *     appid: string,
     *     openid: string
     * } $params
     * @return User
     */
    public function login(array $params): User
    {
        //通过Bmiss接口获取用户信息
        $retUser = $this->api('/mini/user_info', ['openid' => $params['openid']]);
        if ($retUser['code'] !== 0) {
            throw new BusinessError('登录失败');
        }

        //首先看看用户是否存在
        /** @var UserConnect $connect */
        $connect = UserConnect::query()
            ->where('platform', '=', 'bmiss')
            ->where('platform_id', '=', $params['appid'])
            ->where('account', '=', $params['openid'])
            ->first(['user_id']);

        if ($connect) {
            //已经找到用户就直接返回
            $user = get_user($connect->user_id);

            if (!$user) {
                throw new BusinessError('用户不存在');
            }
            if (!$user->status) {
                throw new BusinessError('用户已被禁用');
            }

            //更新用户信息
            $user->nickname = $retUser['data']['nick_name'];
            $user->avatar = $retUser['data']['avatar'];
            User::query()
                ->where('id', '=', $connect->user_id)
                ->update([
                    'nickname' => $retUser['data']['nick_name'],
                    'avatar' => $retUser['data']['avatar'],
                ]);

            return $user;
        }


        //尝试创建用户
        $connect = new UserConnect();
        $connect->platform = 'bmiss';
        $connect->platform_id = $params['appid'];
        $connect->account = $params['openid'];

        Db::beginTransaction();
        try {
            //创建用户
            $user = $this->loginRegisterService->createUser([
                'nickname' => $retUser['data']['nick_name'],
                'avatar' => $retUser['data']['avatar'],
                'reg_source' => 'bmiss',
            ]);

            //写入用户连接表里的用户id并保存
            $connect->user_id = $user->id;
            $connect->save();
            Db::commit();

            return $user;
        } catch (Throwable $exception) {
            Db::rollBack();
            throw $exception;
        }
    }
}