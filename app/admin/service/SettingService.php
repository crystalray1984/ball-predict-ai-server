<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\Setting;
use support\Db;
use support\Redis;
use Throwable;

/**
 * 配置项业务逻辑
 */
class SettingService
{
    /**
     * 读取当前的配置项
     */
    public function getSettings(): array
    {
        $data = Setting::all()->toArray();
        $data = array_column($data, 'value', 'name');
        return array_map(function (string|null $value) {
            if (!isset($value) || $value === '') {
                return '';
            } else {
                return json_decode($value, false);
            }
        }, $data);
    }

    /**
     * 保存设置
     * @param array $params
     * @return void
     */
    public function saveSettings(array $params): void
    {
        if (empty($params)) return;
        Db::beginTransaction();
        try {
            foreach ($params as $name => $value) {
                $strValue = '';
                if (isset($value)) {
                    $strValue = json_enc($value);
                }
                Setting::upsert([
                    'name' => $name,
                    'value' => $strValue,
                ], ['name'], ['value']);
            }
            Db::commit();
        } catch (Throwable $e) {
            Db::rollBack();
            throw $e;
        }

        //清除redis缓存
        Redis::del('settings');
    }
}