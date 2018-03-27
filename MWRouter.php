<?php

namespace MiddleWares;

class MWRouter
{
    const POSITION_PREPOST = "prepost";
    const POSITION_BEHIND  = "behind";

    /**
     * 处理的命名空间
     */
    protected $namespace = '';

    /**
     * 路由方法
     */
    protected $method = '';

    /**
     * 路由会通过的所有中间件
     */
    protected static $throughMiddleWares = [
        "prepose" => [],
        "behind"  => []
    ];

    /**
     * 路由不执行中间件
     */
    protected static $exceptMiddleWares = [];

    /**
     * 运行时路由不执行的中间件
     */
    protected static $runtimeExceptMiddlewares = [];

    /**
     * 传递的数据
     */
    protected $passData = null;

    /**
     * 绑定前置命名空间的中间件
     */
    protected static $router = [];

    /**
     * 中间件组
     */
    protected static $group = [];

    /**
     * 给命名空间绑定中间件
     */
    public static function bind($middlewares, $groups = NULL)
    {
        if (!empty($middlewares) && is_array($middlewares)) {
            $prepostMiddlewares = isset($middlewares[self::POSITION_PREPOST]) ? $middlewares[self::POSITION_PREPOST] : [];
            $behindMiddlewares  = isset($middlewares[self::POSITION_BEHIND]) ? $middlewares[self::POSITION_BEHIND] : [];
            foreach ($prepostMiddlewares as $namespace => $middleware) {
                if (is_string($middleware) && !empty($middleware)) {
                    self::bindAdd($namespace, [$middleware], self::POSITION_PREPOST);
                } elseif (is_array($middleware) && !empty($middleware)) {
                    self::bindAdd($namespace, $middleware, self::POSITION_PREPOST);
                }
            }
            foreach ($behindMiddlewares as $namespace => $middleware) {
                if (is_string($middleware) && !empty($middleware)) {
                    self::bindAdd($namespace, [$middleware], self::POSITION_BEHIND);
                } elseif (is_array($middleware) && !empty($middleware)) {
                    self::bindAdd($namespace, $middleware, self::POSITION_BEHIND);
                }
            }
        }
        if (!empty($groups) && is_array($groups)) {
            $preposGroups = isset($groups[self::POSITION_PREPOST]) ? $groups[self::POSITION_PREPOST] : [];
            $behindGroups = isset($groups[self::POSITION_BEHIND]) ? $groups[self::POSITION_BEHIND] : [];
            foreach ($preposGroups as $namespace => $group) {
                if (is_string($group) && !empty($group) && isset(self::$group[$group])) {
                    self::bindAdd($namespace, self::$group[$group], self::POSITION_PREPOST);
                } elseif (is_array($group) && !empty($group)) {
                    foreach ($group as $alias) {
                        if (isset(self::$group[$alias])) {
                            self::bindAdd($namespace, self::$group[$alias], self::POSITION_PREPOST);
                        }
                    }
                }
            }
            foreach ($behindGroups as $namespace => $group) {
                if (is_string($group) && !empty($group) && isset(self::$group[$group])) {
                    self::bindAdd($namespace, self::$group[$group], self::POSITION_BEHIND);
                } elseif (is_array($group) && !empty($group)) {
                    foreach ($group as $alias) {
                        if (isset(self::$group[$alias])) {
                            self::bindAdd($namespace, self::$group[$alias], self::POSITION_BEHIND);
                        }
                    }
                }
            }
        }
    }

    /**
     * 添加中间件
     */
    protected static function bindAdd($namespace, $middlewares, $position)
    {
        self::$router[$namespace][$position] = !isset(self::$router[$namespace][$position]) ?
            $middlewares :
            array_merge(self::$router[$namespace][$position], $middlewares);
    }

    /**
     * 设置中间件组
     */
    public static function group($groupAlias)
    {
        if (!empty($groupAlias) && is_array($groupAlias)) {
            foreach ($groupAlias as $alias => $middleware) {
                self::$group[$alias] = $middleware;
            }
        }
    }

    /**
     * 为路由设置不需要的中间件
     */
    public static function except($namespace, $middlewares)
    {
        if (is_string($middlewares)) {
            $middlewares = [$middlewares];
        }
        self::$exceptMiddleWares[$namespace] = isset(self::$exceptMiddleWares[$namespace]) ?
            $middlewares :
            array_merge(self::$exceptMiddleWares[$namespace], $middlewares);
    }

    /**
     * 动态路由方法名
     */
    public function method($method)
    {
        $this->method = $method;
        return $this;
    }


    /**
     * 设置要处理的命名空间
     */
    public function name_space($nameSpace)
    {
        $this->namespace = $nameSpace;
        return $this;
    }

    /**
     * 设置在中间件中传递的数据
     */
    public function send($pass)
    {
        $this->passData = $pass;
        return $this;
    }

    /**
     * 获取通过的中间件
     */
    public function through()
    {
        if (!empty($this->namespace)) {
            $throughMiddleWares = [
                self::POSITION_PREPOST => [],
                self::POSITION_BEHIND  => []
            ];
            if (!empty($this->namespace)) {
                //根据命名空间添加中间件
                if (!empty($this->method)) {
                    $this->namespace[] = "::{$this->method}";
                }
                foreach ($this->namespace as $split) {
                    $temp = empty($temp) ? $split : "{$temp}\\{$split}";
                    if (isset(self::$router[$temp])) {
                        if (isset(self::$router[$temp][self::POSITION_PREPOST])) {
                            $throughMiddleWares[self::POSITION_PREPOST] =
                                array_merge(
                                    $throughMiddleWares[self::POSITION_PREPOST],
                                    self::$router[$temp][self::POSITION_PREPOST]
                                );
                        }
                        if (isset(self::$router[$temp][self::POSITION_BEHIND])) {
                            $throughMiddleWares[self::POSITION_BEHIND] =
                                array_merge(
                                    $throughMiddleWares[self::POSITION_BEHIND],
                                    self::$router[$temp][self::POSITION_BEHIND]
                                );
                        }
                    }
                    if (isset(self::$exceptMiddlewares[$temp])) {
                        self::$runtimeExceptMiddlewares = array_merge(
                            self::$runtimeExceptMiddlewares,
                            self::$exceptMiddleWares[$temp]
                        );
                    }
                }
            }
            self::$throughMiddleWares = $throughMiddleWares;
        }
        return $this;
    }

    /**
     * 开始处理
     */
    public function then($lastFunction = null, $handle = null, $params = [])
    {
        array_reduce(
            self::$throughMiddleWares[self::POSITION_PREPOST],
            $this->createWrapFunctions(),
            $this->passData);
        $passAble = $this->passData;
        if (is_callable($lastFunction)) {
            $passAble = call_user_func_array($lastFunction, array_merge([$passAble], $params));
        }
        if (is_callable($handle)) {
            $passAble = call_user_func_array($handle, [$this->passData, $passAble]);
        }
        return array_reduce(
            self::$throughMiddleWares[self::POSITION_BEHIND],
            $this->createWrapFunctions(),
            $passAble
        );
    }

    /**
     * 创建array_reduce递归包裹函数
     */
    protected function createWrapFunctions()
    {
        return function ($passData, $middleWare) {
            if ($middleWare instanceof \Closure) {
                call_user_func($middleWare, $passData);
            } elseif (is_string($middleWare) && !in_array(self::$runtimeExceptMiddlewares)) {
                $class = MiddleWare::get($middleWare);
                if (!is_null($class) &&
                    !in_array(self::$runtimeExceptMiddlewares) &&
                    class_exists($class) &&
                    method_exists(new $class(), MiddleWare::getMethodName())
                ) {
                    call_user_func_array([new $class(), MiddleWare::getMethodName()], [$passData]);
                }
            }
            return $passData;
        };
    }
}