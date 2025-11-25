<?php declare(strict_types=1);

namespace app\admin\service;

use app\model\ClientVersion;
use app\model\ClientVersionBuild;
use Illuminate\Database\Query\JoinClause;
use support\Db;
use support\exception\BusinessError;
use support\storage\Storage;
use ZipArchive;

/**
 * 客户端版本相关业务逻辑
 */
class VersionService
{
    /**
     * 获取桌面版本列表
     * @param array $params
     * @return array
     */
    public function getDesktopVersionList(array $params): array
    {
        $query = ClientVersionBuild::query()
            ->join('client_version', function (JoinClause $join) {
                $join->on('client_version.id', '=', 'client_version_build.client_version_id')
                    ->where('client_version_build.is_base', '=', 1);
            });
        if (isset($params['arch'])) {
            $query->where('client_version.arch', '=', $params['arch']);
        }
        if (isset($params['status'])) {
            $query->where('client_version.status', '=', $params['status']);
        }

        $query->whereNull('client_version.deleted_at');

        $count = $query->count();

        $list = $query->orderBy('client_version.version_number', 'desc')
            ->forPage($params['page'] ?? DEFAULT_PAGE, $params['page_size'] ?? DEFAULT_PAGE_SIZE)
            ->get([
                'client_version.*',
                'client_version_build.full_info',
                'client_version_build.hot_update_info',
                'client_version_build.zip_info',
            ])
            ->toArray();

        $list = array_map(function (array $row) {
            if (!empty($row['full_info'])) {
                $row['full_info']['url'] = Storage::getUrl($row['full_info']['path']);
            }
            if (!empty($row['hot_update_info'])) {
                $row['hot_update_info']['url'] = Storage::getUrl($row['hot_update_info']['path']);
            }
            return $row;
        }, $list);

        return [
            'count' => $count,
            'list' => $list,
        ];
    }

    /**
     * 保存桌面客户端版本
     * @param array $data
     * @return void
     */
    public function saveDesktopVersion(array $data): void
    {
        $version_base = 1;
        $version_number = array_reduce(explode('.', $data['version']), function (int $result, string $value) use (&$version_base) {
            $result += intval($value) * $version_base;
            $version_base *= 1000;
            return $result;
        }, 0);

        if (!empty($data['id'])) {
            /** @var ClientVersion $version */
            $version = ClientVersion::query()
                ->where('id', '=', $data['id'])
                ->first();
            if (!$version) {
                throw new BusinessError('未找到要编辑的版本');
            }

            /** @var ClientVersionBuild $build */
            $build = ClientVersionBuild::query()
                ->where('client_version_id', '=', $data['id'])
                ->first();

            if (!$build) {
                throw new BusinessError('未找到要编辑的版本');
            }

            //检查版本号
            $exists = ClientVersion::query()
                ->where('platform', '=', $version->platform)
                ->where('arch', '=', $version->arch)
                ->where('version_number', '=', $version_number)
                ->where('id', '!=', $version->id)
                ->exists();

            if ($exists) {
                throw new BusinessError('版本号已存在');
            }
        } else {
            $version = new ClientVersion();
            $version->platform = $data['platform'];
            $version->arch = $data['arch'] ?? '';

            //检查版本号
            $exists = ClientVersion::query()
                ->where('platform', '=', $version->platform)
                ->where('arch', '=', $version->arch)
                ->where('version_number', '=', $version_number)
                ->exists();

            if ($exists) {
                throw new BusinessError('版本号已存在');
            }

            $build = new ClientVersionBuild();
            $build->is_base = 1;
        }

        //填充内容
        $version->version = $data['version'];
        $version->version_number = $version_number;
        $version->status = $build->status = $data['status'];
        $version->is_mandatory = $data['is_mandatory'];
        $version->note = $data['note'];

        //处理全量安装包
        if (!empty($data['full_info'])) {
            $full_path = "update/$version->platform/" . uniqid() . '/';
            if ($version->platform === 'win32') {
                $full_path .= "setup-win32-$version->arch-$version->version.zip";
            } else {
                $full_path .= "setup-darwin-$version->version.dmg";
            }

            //移动安装包文件
            Storage::copyFile($data['full_info']['path'], $full_path);
            $build->full_info = [
                'path' => $full_path,
                'size' => $data['full_info']['size'],
            ];
        }

        //处理更新包
        if (!empty($data['hot_update_info'])) {
            $base_path = "update/$version->platform/update/";
            $hot_update_path = $base_path . uniqid() . ($version->platform === 'win32' ? '.exe' : '.zip');
            $blockmap_path = $hot_update_path . '.blockmap';

            Storage::copyFile($data['hot_update_info']['path'], $hot_update_path);
            Storage::copyFile($data['hot_update_info']['blockmap'], $blockmap_path);

            $build->hot_update_info = [
                'path' => $hot_update_path,
                'size' => $data['hot_update_info']['size'],
                'hash' => $data['hot_update_info']['hash'],
                'blockmap' => $blockmap_path,
            ];
        }

        //保存数据
        Db::beginTransaction();
        try {
            $version->save();
            $build->client_version_id = $version->id;
            $build->save();

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }

    /**
     * 生成压缩包
     * @param string $remotePath 远端的原始文件
     * @param string $toPath 生成完压缩包之后要写入的远端文件
     * @return int
     */
    protected function createZipFile(string $remotePath, string $toPath): int
    {
        $fileName = substr($remotePath, strrpos($remotePath, '/') + 1);
        $extName = substr($fileName, strrpos($fileName, '.'));
        $remoteTempPath = runtime_path(uniqid() . $extName);
        Storage::getFile($remotePath, $remoteTempPath);
        $zipTempPath = runtime_path(uniqid() . '.zip');

        try {
            //把文件打入压缩包
            try {
                $zip = new ZipArchive();
                $zip->open($zipTempPath, ZipArchive::CREATE);
                $zip->addFile($remoteTempPath, $fileName);
                $zip->close();

                //上传文件
                $fp = fopen($zipTempPath, 'r');
                try {
                    Storage::putFile($toPath, $fp);
                    return filesize($zipTempPath);
                } finally {
                    fclose($fp);
                }
            } finally {
                @unlink($zipTempPath);
            }
        } finally {
            @unlink($remoteTempPath);
        }

        return 0;
    }

    /**
     * 删除版本
     * @param int $id
     * @return void
     */
    public function deleteVersion(int $id): void
    {
        Db::beginTransaction();
        try {
            ClientVersion::where('id', '=', $id)->delete();
            ClientVersionBuild::where('client_version_id', '=', $id)->delete();

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }
}