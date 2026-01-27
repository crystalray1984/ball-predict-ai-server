<?php declare(strict_types=1);

namespace app\api\controller;

use app\api\service\BmissService;
use app\api\service\OrderService;
use app\api\service\UserService;
use app\model\Order;
use DI\Attribute\Inject;
use Respect\Validation\Validator as v;
use support\Controller;
use support\Log;
use support\Request;
use support\Response;
use support\Token;

/**
 * 与Bmiss对接使用的控制器
 */
class BmissController extends Controller
{
    #[Inject]
    protected BmissService $service;

    #[Inject]
    protected UserService $userService;

    /**
     * Bmiss小程序登录
     * @param Request $request
     * @return Response
     */
    public function login(Request $request): Response
    {
        $params = v::input($request->post(), [
            'appid' => v::stringType()->notEmpty()->setName('appid'),
            'openid' => v::stringType()->notEmpty()->setName('openid'),
        ]);

        //获取登录的用户
        $user = $this->service->login($params);
        //生成token
        $token = Token::create(['id' => $user->id, 'type' => 'user']);

        return $this->success([
            'token' => $token,
            'user' => $this->userService->getUserInfo($user),
        ]);
    }

    /**
     * 回调接口
     * @param Request $request
     * @return Response
     */
    public function callback(Request $request): Response
    {
        $signature = $request->get('signature', '');
        $body = $request->rawBody();

        //校验签名
        $bodySignature = md5(md5($body . config('bmiss.app_secret')));
        if ($bodySignature !== $signature) {
            return \response('签名错误', 400);
        }

        //根据回调的类型执行相应的业务
        try {
            $type = $request->post('type', '');
            $data = $request->post('data', []);

            switch ($type) {
                case 'consume': //消费回调
                    /** @var Order $order */
                    $order = Order::query()
                        ->where('order_number', '=', $data['out_order_no'])
                        ->first();
                    if (!$order) {
                        //没找到订单
                        Log::channel('bmiss')->debug('[支付回调] 未找到订单', $data['out_order_no']);
                        return \response('未找到订单', 400);
                    }

                    if ($order->status === 'paid') {
                        //订单已经支付完成
                        Log::channel('bmiss')->debug('[支付回调] 重复通知', $data['out_order_no']);
                        return \response();
                    }

                    //完成订单
                    G(OrderService::class)->completeOrder($order);
                    break;
            }
        } catch (\Throwable $e) {
            return \response($e->getMessage(), 400);
        }


        return \response();
    }
}