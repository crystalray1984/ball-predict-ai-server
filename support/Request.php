<?php declare(strict_types=1);

namespace support;

use app\model\Admin;
use app\model\User;

/**
 * Class Request
 * @package support
 */
class Request extends \Webman\Http\Request
{
    /**
     * 当前请求登录的用户
     * @var User|null
     */
    public User|null $user = null;

    /**
     * 当前请求登录的管理员
     * @var Admin|null
     */
    public Admin|null $admin = null;
}