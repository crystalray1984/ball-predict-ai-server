<?php declare(strict_types=1);

namespace app\process;

use Carbon\Carbon;
use GatewayWorker\Lib\Gateway;
use Workerman\Timer;

/**
 * 检查ws连接里的用户vip有效期
 */
class CheckVipSocket
{
    public function onWorkerStart(): void
    {
        Timer::add(60, function () {
            $this->check();
        });
    }

    public function check(): void
    {
        $sessions = Gateway::getAllClientSessions('vip');

        //获取所有已经连接到vip组的ws连接
        $uids = Gateway::getUidListByGroup('vip');
        if (empty($uids)) return;

        //基于uid查询用户信息
        $users = get_users(array_values($uids));
        $kickUids = [];
        foreach ($uids as $uid) {
            $intUid = intval($uid);
            if (empty($users[$intUid]) || Carbon::parse($users[$intUid]['expire_time'])->unix() <= time()) {
                $kickUids[] = $intUid;
            }
        }
        if (empty($kickUids)) return;

        $clientList = Gateway::getClientIdByUids($kickUids);
        if (empty($clientList)) return;
        foreach ($clientList as $clientId) {
            //不踢ios和安卓客户端
            if (!empty($sessions[$clientId]['platform']) && in_array($sessions[$clientId]['platform'], ['ios', 'android'])) {
                continue;
            }
            Gateway::sendToClient($clientId, json_enc([
                'type' => 'expired'
            ]));
            Gateway::leaveGroup($clientId, 'vip');
        }
    }
}