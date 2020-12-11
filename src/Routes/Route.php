<?php
namespace ImStart\Routes;


class Route
{
    protected static $instance = null;
    // 路由本质实现是会有一个容器在存储解析之后的路由
    protected $routes = [];

    // 定义了访问的类型
    protected $verbs = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
    // 记录路由的文件地址
    protected $routeMap = [];
    // 记录请求的方式
    protected $method = null;

    protected $flag = null;

    protected function __construct( )
    {
        $this->routeMap = [
            'Http'      => app()->getBasePath().'/route/http.php',
            'WebSocket' => app()->getBasePath().'/route/web_socket.php',
        ];
    }

    public static function getInstance()
    {
        if (\is_null(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance ;
    }

    public function get($uri, $action)
    {
        return $this->addRoute(['GET'], $uri, $action);
    }

    public function post($uri, $action)
    {
        return $this->addRoute(['POST'], $uri, $action);
    }

    public function any($uri, $action)
    {
        return $this->addRoute(self::$verbs, $uri, $action);
    }

    public function wsController($uri, $controller)
    {
        $actions = [
          'open',
          'message',
          'close',
        ];
        foreach ($actions as $key => $action) {
            $this->addRoute([$action], $uri, $controller.'@'.$action);
        }
    }
    /**
     * 注册路由
     * @param array $methods [description]
     * @param [type] $uri     [description]
     * @param [type] $action  [description]
     */
    protected function addRoute($methods, $uri, $action)
    {
        foreach ($methods as $method ) {
            $this->routes[$this->flag][$method][$uri] = $action;
        }
        return $this;
    }
    /**
     * 根据请求校验路由，并执行方法
     * @return [type] [description]
     */
    public function match($path, $param = [])
    {
        $action = null;
        foreach ($this->routes[$this->flag][$this->method] as $uri => $value) {
            $uri = ($uri && substr($uri,0,1)!='/') ? "/".$uri : $uri;

            if ($path === $uri) {
                $action = $value;
                break;
            }
        }
        if (!empty($action)) {
            return $this->runAction($action, $param);
        }

        echo "没找到方法\n";

        return "404";

    }

    private function runAction($action, $param = null)
    {
        if ($action instanceof \Closure) {
            return $action(...$param);
        } else {
            // 控制器解析
            $namespace = "\App\\".$this->flag."\Controller\\";

            // IndexController@dd 
            $arr = \explode("@", $action);
            $controller = $namespace.$arr[0];
            $class = new $controller();
            return $class->{$arr[1]}(...$param);
        }

    }

    public function registerRoute()
    {
        foreach ($this->routeMap as $key => $path) {
            $this->flag = $key;
            require_once $path;
        }
        return $this;
    }

    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }

    public function setFlag($flag)
    {
        $this->flag = $flag;
        return $this;
    }
    public function getRoutes()
    {
        return $this->routes;
    }
}
