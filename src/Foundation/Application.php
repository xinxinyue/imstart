<?php
namespace ImStart\Foundation;

use ImStart\Container\Container;
use ImStart\Event\Event;

use ImStart\Routes\Route;

use ImStart\Server\Http\HttpServer;

use ImStart\Server\WebSocket\WebSocketServer;

class Application extends Container
{
    protected const SWOSTAR_WELCOME = "
      _____                _____   _                    _   
     |_   _|              / ____| | |                  | |  
       | |    _ __ ___   | (___   | |_    __ _   _ __  | |_ 
       | |   | '_ ` _ \   \___ \  | __|  / _` | | '__| | __|
      _| |_  | | | | | |  ____) | | |_  | (_| | | |    | |_ 
     |_____| |_| |_| |_| |_____/   \__|  \__,_| |_|     \__| 
    ";

    protected $basePath = "";

    public function __construct($path = null)
    {
        if (!empty($path)) {
            $this->setBasePath($path);
        }
        $this->registerBaseBindings();
        $this->init();

        echo self::SWOSTAR_WELCOME;
    }

    public function run($arg)
    {
        $server = null;
        switch ($arg[1]) {
          case 'http:start':
            $server = new HttpServer($this);
            break;
          case 'ws:start':
            $server = new WebSocketServer($this);
            break;
        }
        $server->watchFile(false);
        $server->start();
    }

    public function registerBaseBindings()
    {
        self::setInstance($this);
        $binds = [
            // 标识  ， 对象
            'config'      => (new \ImStart\Config\Config()),
            'httpRequest' => (new \ImStart\Message\Http\Request()),
        ];
        foreach ($binds as $key => $value) {
            $this->bind($key, $value);
        }
    }

    public function init()
    {
        $this->bind('route', Route::getInstance()->registerRoute());
        $this->bind('event', $this->registerEvent());
    }
    /**
     * 注册框架事件
     * @return Event
     */
    public function registerEvent()
    {
        $event = new Event();

        $files = scandir($this->getBasePath().'/app/Listener');
        // 2. 读取文件信息
        foreach ($files as $key => $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $class = 'App\\Listener\\'.\explode('.', $file)[0];
            if (\class_exists($class)) {
                $listener = new $class($this);
                $event->register($listener->getName(), [$listener, 'handler']);
            }
        }

        return $event;
    }

    public function setBasePath($path)
    {
        $this->basePath = \rtrim($path, '\/');
    }
    public function getBasePath()
    {
        return $this->basePath;
    }
}
