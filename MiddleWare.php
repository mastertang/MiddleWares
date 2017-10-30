<?php
namespace MiddleWares;

class MiddleWare
{
    /**
     * 中间件
     */
    protected static $middleWares = [];

    /**
     * 中间件类固定执行函数名
     */
    protected static $method = 'operation';

    /**
     * 单例模式
     */
    public static function singleton($alias, $class)
    {
        if (class_exists($class) && method_exists(new $class(), self::$method)) {
            self::$middleWares[$alias] = $class;
        }
    }

    public static function getAll(){
        return self::$middleWares;
    }

    /**
     * 添加中间件
     */
    public static function add($middlewares)
    {
        foreach ($middlewares as $alias => $class) {
            self::singleton($alias, $class);
        }
    }

    /**
     * 清空中间件
     */
    public static function clear()
    {
        self::$middleWares = [];
    }

    /**
     * 移除中间件
     */
    public static function remove($alias)
    {
        $alias = (array)$alias;
        foreach ($alias as $name) {
            if (isset(self::$middleWares[$name])) {
                unset(self::$middleWares[$name]);
            }
        }
    }

    /**
     * 动态设置方法名
     */
    public static function setMethod($methodName)
    {
        self::$method = $methodName;
    }

    /**
     * 获取中间默认方法名
     */
    public static function getMethodName()
    {
        return self::$method;
    }

    /**
     * 获取中间件
     */
    public static function get($alias)
    {
        if (is_array($alias)) {
            $middleWares = [];
            foreach ($alias as $name) {
                if (isset(self::$middleWares[$name])) {
                    $middleWares[] = self::$middleWares[$name];
                }
            }
            return $middleWares;
        } else {
            return isset(self::$middleWares[$alias]) ? self::$middleWares[$alias] : null;
        }
    }
}
