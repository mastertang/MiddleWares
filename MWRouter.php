<?php
namespace MiddleWares;

class MWRouter
{
    /**
     * 处理的命名空间
     */
    protected $namespace = '';

    /**
     * 路由方法
     */
    protected $method = '';

    /**
     * 保存通过的路由所有前置中间件
     */
    protected $preposeThroughMiddleWares = [];

    /**
     * 保存请求执行后执行的后置中间件
     */
    protected $behindThroughMiddleWares = [];

    /**
     * 传递的数据
     */
    protected $passData = null;

    /**
     * 绑定前置命名空间的中间件
     */
    protected static $router = [];

    /**
     * 绑定后置命名空间的中间件
     */
    protected static $behindRouter = [];

    /**
     * 中间件组
     */
    protected static $group = [];

    /**
     * 后置中间件组
     */
    protected static $behindGroup = [];

    /**
     * 给命名空间绑定中间件
     */
    public static function bind($namespace, $params)
    {
        if (!empty($namespace) && !empty($params) && is_array($params)) {
            foreach ($params as $key => $middlewares) {
                switch ($key) {
                    case 'middleware':
                        self::bindAdd($namespace, (array)$middlewares);
                        break;
                    case 'group':
                        self::bindAdd($namespace, self::$group[$middlewares]);
                        break;
                    default:
                        break;
                }
            }
        }
    }

    /**
     * 添加中间件
     */
    protected static function bindAdd($namespace, $middlewares)
    {
        self::$router[$namespace] = !isset(self::$router[$namespace]) ?
            $middlewares :
            array_merge(self::$router[$namespace], $middlewares);
    }

    /**
     * 设置中间件组
     */
    public static function group($groupAlias, $middlewares)
    {
        if (!empty($groupAlias) && !empty($middlewares) && is_array($middlewares)) {
            self::$group[$groupAlias] = $middlewares;
        }
    }

    /**
     * 添加后置中间件
     */
    public static function behind($namespace, $params)
    {
        if (!empty($namespace) && !empty($params) && is_array($params)) {
            foreach ($params as $key => $middlewares) {
                switch ($key) {
                    case 'middleware':
                        self::behindBindAdd($namespace, (array)$middlewares);
                        break;
                    case 'group':
                        self::behindBindAdd($namespace, self::$behindGroup[$middlewares]);
                        break;
                    default:
                        break;
                }
            }
        }
    }

    /**
     * 添加后置中间件
     */
    protected static function behindBindAdd($namespace, $middlewares)
    {
        self::$behindRouter[$namespace] = !isset(self::$behindRouter[$namespace]) ?
            $middlewares :
            array_merge(self::$behindRouter[$namespace], $middlewares);
    }

    /**
     * 设置后置中间件组
     */
    public static function behindGroup($groupAlias, $middlewares)
    {
        if (!empty($groupAlias) && !empty($middlewares) && is_array($middlewares)) {
            self::$behindGroup[$groupAlias] = $middlewares;
        }
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
            $namespaceString = trim(str_replace("/", "\\", $this->namespace), " ");
            $namespaceSplit = explode('\\', $namespaceString);
            $throughMiddleWares = [];
            $behindThroughMiddleWares = [];
            $temp = '';
            if (!empty($namespaceSplit)) {
                //根据命名空间添加中间件
                foreach ($namespaceSplit as $split) {
                    $temp = empty($temp) ? $temp = $split : "{$temp}\\{$split}";
                    if (isset(self::$router[$temp])) {
                        $throughMiddleWares = array_merge($throughMiddleWares, self::$router[$temp]);
                    }
                    if (isset(self::$behindRouter[$temp])) {
                        $behindThroughMiddleWares = array_merge($throughMiddleWares, self::$behindRouter[$temp]);
                    }
                }
                //添加控制器方法执行前的中间件
                if (!empty($temp) && !empty($this->method)) {
                    $temp .= "::{$this->method}";
                    if (isset(self::$router[$temp])) {
                        $throughMiddleWares = array_merge($throughMiddleWares, self::$router[$temp]);
                    }
                    if (isset(self::$behindRouter[$temp])) {
                        $behindThroughMiddleWares = array_merge($behindThroughMiddleWares, self::$behindRouter[$temp]);
                    }
                }
            }
            $this->preposeThroughMiddleWares = $throughMiddleWares;
            $this->behindThroughMiddleWares = $behindThroughMiddleWares;
        }
//        if (!empty(self::$behind)) {
//            $this->behindThroughMiddleWares = self::$behind;
//        }
        return $this;
    }

    /**
     * 开始处理
     */
    public function then($lastFunction = null, $handle = null, $params = [])
    {
        array_reduce(
            $this->preposeThroughMiddleWares,
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
            $this->behindThroughMiddleWares,
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
            } elseif (is_string($middleWare)) {
                $class = MiddleWare::get($middleWare);
                if (!is_null($class) &&
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