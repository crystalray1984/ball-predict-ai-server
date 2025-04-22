<?php declare(strict_types=1);

if (!function_exists('array_deep_merge')) {
    /**
     * 深度合并多个数组的内容，传入的非数组会被忽略
     * @param mixed ...$args
     * @return mixed
     */
    function array_deep_merge(mixed ...$args): array
    {
        $args = array_filter($args, fn($item) => is_array($item));
        if (count($args) === 0) return [];
        if (count($args) === 1) return $args[0];

        return array_reduce($args, function (array $prev, array $current) {
            //2个列表直接拼接合并
            if (array_is_list($prev) && array_is_list($current)) {
                return array_merge($prev, $current);
            }

            foreach ($current as $key => $value) {
                //非同时为数组时，后面的覆盖前面的
                if (!is_array($value) || !isset($prev[$key]) || !is_array($prev[$key])) {
                    $prev[$key] = $value;
                    continue;
                }

                $prev[$key] = array_deep_merge($prev[$key], $value);
            }

            return $prev;
        }, []);
    }
}

if (!function_exists('yaml_load')) {
    /**
     * 加载YAML配置文件
     * @param string $path 待加载的路径
     * @param bool $findLocal 是否额外寻找结尾为.local的本地配置文件
     * @return array
     */
    function yaml_load(string $path, bool $findLocal = true): array
    {
        $files = [$path];
        if ($findLocal) {
            $files[] = $path . '.local';
        }

        $yaml = [];
        foreach ($files as $file) {
            if (!($file = realpath($file))) {
                continue;
            }
            $yaml[] = \Symfony\Component\Yaml\Yaml::parseFile($file);
        }

        return array_deep_merge(...$yaml);
    }
}

if (!function_exists('yaml')) {
    /**
     * 从YAML配置文件中读取数据
     * @param string|null $key 配置键名，传null则返回整个配置文件的数据
     * @param mixed|null $default 读取失败时的默认值
     * @param string|null $path 配置文件路径（默认为根目录下config.yaml）
     * @return mixed 配置值
     */
    function yaml(?string $key = null, mixed $default = null, ?string $path = null): mixed
    {
        $path = $path ?? base_path() . DIRECTORY_SEPARATOR . 'config.yaml';
        $yaml = yaml_load($path);
        if (is_null($key)) {
            return $yaml;
        }

        $data = $yaml;
        $keys = explode('.', $key);
        foreach ($keys as $k) {
            if (!is_array($data)) {
                $data = $default;
                break;
            }
            $data = $data[$k] ?? $default;
        }
        return $data;
    }
}

if (!function_exists('json_enc')) {
    /**
     * 预置了一些特定参数的json_encode
     * @param mixed $input
     * @param int $flags
     * @param int $depth
     * @return string
     */
    function json_enc(mixed $input, int $flags = 0, int $depth = 512): string
    {
        return json_encode(
            $input,
            $flags | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            $depth
        );
    }
}