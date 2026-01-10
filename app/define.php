<?php declare(strict_types=1);

const DEFAULT_PAGE = 1;

const DEFAULT_PAGE_SIZE = 20;

const CACHE_USER_KEY = 'user:';

const CACHE_AGENT_KEY = 'agent:';

const CACHE_ADMIN_KEY = 'admin:';

/**
 * 应用程序配置的Redis缓存键名
 */
const CACHE_SETTING_KEY = 'settings';

/**
 * UI时区
 */
const UI_TIMEZONE = 'Asia/Shanghai';

/**
 * 皇冠所在的时区
 */
const CROWN_TIMEZONE = 'America/Martinique';

const CHANNELS = [
    'rockball' => [
        'title' => '滚球',
    ],
    'direct' => [
        'title' => '初盘',
    ],
    'mansion' => [
        'title' => '内测',
    ],
];
