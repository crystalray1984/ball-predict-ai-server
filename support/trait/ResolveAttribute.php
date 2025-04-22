<?php declare(strict_types=1);

namespace support\trait;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * 提供从反射获取注解的功能，并缓存
 */
trait ResolveAttribute
{
    /**
     * @var array<string, static[]> 注解实例缓存
     */
    protected static array $_attributeCache = [];

    /**
     * 获取为指定注解类获取注解实例
     * @param string|object $classOrObject 类名或类实例
     * @param string|null $method 类方法名
     * @return static|null
     */
    public static function getAttribute(string|object $classOrObject, ?string $method = null): static|null
    {
        $attributes = static::getAllAttributes($classOrObject, $method);
        return empty($attributes) ? null : $attributes[0];
    }

    /**
     * 获取为指定注解类获取注解实例列表
     * @param string|object $classOrObject 类名或类实例
     * @param string|null $method 类方法名
     * @return array<static>
     */
    public static function getAllAttributes(string|object $classOrObject, ?string $method = null): array
    {
        try {
            if (empty($method)) {
                $ref = new ReflectionClass($classOrObject);
                $name = $ref->getName();
            } else {
                $ref = new ReflectionMethod($classOrObject, $method);
                $name = $ref->getDeclaringClass()->getName() . '::' . $ref->getName();
            }
        } catch (ReflectionException) {
            return [];
        }
        if (isset(static::$_attributeCache[$name])) {
            return [] + static::$_attributeCache[$name];
        }

        $attributes = $ref->getAttributes(static::class);
        $instances = array_map(fn($attribute) => $attribute->newInstance(), $attributes);
        static::$_attributeCache[$name] = $instances;
        return [] + $instances;
    }
}
