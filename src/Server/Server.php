<?php
namespace ImStart\Server;

use Redis;

use ImStart\RPC\Rpc;

use ImStart\Supper\Inotify;
use Swoole\Coroutine\Http\Client;

use Swoole\Server as SwooleServer;
use ImStart\Foundation\Application;

/**
 * 所有服务的父类， 写一写公共的操作
 */
abstract class Server
{
    // 属性
    /**
     *
     * @var Swoole/Server
     */
    protected $swooleServer;

    protected $app ;

    protected $inotify = null;

    protected $port = 9000;

    protected $host = "0.0.0.0";

    protected $watchFile = false;

    protected $redis;
    /**
     * 用于记录系统pid的信息
     * @var string
     */
    protected $pidFile = "/runtime/swostar.pid";
    /**
     * 这是swoole服务的配置
     * @var [type]
     */
    protected $config = [
        'task_worker_num' => 0,
    ];
    /**
     * 用于记录pid的信息
     * @var array
     */
    protected $pidMap = [
        'masterPid'  => 0,
        'managerPid' => 0,
        'workerPids' => [],
        'taskPids'   => []
    ];
    /**
     * 注册的回调事件
     * [
     *   // 所有服务均会注册
     *   "server" => [],
     *   // 子类的服务
     *   "sub" => [],
     *   // 额外扩展的回调函数
     *   "ext" => []
     * ]
     *
     * @var array
     */
    protected $event = [
        // 这是所有服务均会注册的时间
        "server" => [
            // 事件   =》 事件函数
            "start"        => "onStart",
            "managerStart" => "onManagerStart",
            "managerStop"  => "onManagerStop",
            "shutdown"     => "onShutdown",
            "workerStart"  => "onWorkerStart",
            "workerStop"   => "onWorkerStop",
            "workerError"  => "onWorkerError",
        ],
        // 子类的服务
        "sub" => [],
        // 额外扩展的回调函数
        // 如 ontart等
        "ext" => []
    ];


    protected abstract function initSetting();

    public function __construct(Application $app, $flag = 'http')
    {
        $this->flag = $flag;
        $this->app = $app;
        // 初始化swoole配置
        $this->initSetting();
        // 创建服务
        $this->createServer();
        // 设置回调函数
        $this->initEvent();
        // 设置swoole的回调事件
        $this->setSwooleEvent();

    }

    /**
     * 指定给某一个连接的服务器发送信息
     * @xiaolele
     * @param  [type] $ip   [description]
     * @param  [type] $port [description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function send($ip, $port, $data, $header = null)
    {
        $cli = new Client($ip, $port);

        empty($header)?:$cli->setHeaders($header);

        if ($cli->upgrade('/')) {
            $cli->push(\json_encode($data));
        }
    }
    /**
     * 创建服务
     * @xiaolele
     */
    protected abstract function createServer();
    /**
     * 初始化监听的事件
     * @xiaolele
     */
    protected abstract function initEvent();
    // 通用的方法

    public function start()
    {
        $config = app('config');
        // 2. 设置配置信息
        $this->swooleServer->set($this->config);
        if ($config->get('server.rpc.tcpable')) {
            new Rpc($this->swooleServer, $config->get('server.rpc'));
        }
        // 5. 启动
        $this->swooleServer->start();
    }

    /**
     * 设置swoole的回调事件
     * @xiaolele
     */
    protected function setSwooleEvent()
    {
        foreach ($this->event as $type => $events) {
            foreach ($events as $event => $func) {
                $this->swooleServer->on($event, [$this, $func]);
            }
        }
    }
    protected function watchEvent()
    {
        return function($event){
            // $action = 'file:';
            // switch ($event['mask']) {
            //     case IN_CREATE:
            //       $action = 'IN_CREATE';
            //       break;
            //     case IN_DELETE:
            //       $action = 'IN_DELETE';
            //       break;
            //     case \IN_MODIFY:
            //       $action = 'IN_MODIF';
            //       break;
            //     case \IN_MOVE:
            //       $action = 'IN_MOVE';
            //       break;
            // }
            // echo "因为什么重启";
            $this->swooleServer->reload();
        };
    }
    // master启动-回调方法
    public function onStart(SwooleServer $server)
    {
        $this->pidMap['masterPid'] = $server->master_pid;
        $this->pidMap['managerPid'] = $server->manager_pid;

        if ($this->watchFile ) {
            $this->inotify = new Inotify($this->app->getBasePath(), $this->watchEvent());
            $this->inotify->start();
        }
        //触发启动事件
        $this->app->make('event')->trigger('start', [$this]);
    }

    //Task和Worker进程已创建
    public function onManagerStart(SwooleServer $server)
    {

    }

    //Worker进程/Task进程启动回调
    public function onWorkerStart(SwooleServer $server, int $worker_id)
    {
        $this->pidMap['workerPids'] = [
            'id'  => $worker_id,
            'pid' => $server->worker_id
        ];

        //每个进程创建单独的redis连接
        $this->redis = new Redis;
        $this->redis->pconnect("127.0.0.1", 6379);
    }
    public function onManagerStop(SwooleServer $server)
    {

    }
    public function onShutdown(SwooleServer $server)
    {

    }

    public function onWorkerStop(SwooleServer $server, int $worker_id)
    {

    }
    public function onWorkerError(SwooleServer $server, int $workerId, int $workerPid, int $exitCode, int $signal)
    {
    }

    /**
     * @param array
     *
     * @return static
     */
    public function setEvent($type, $event)
    {
        // 暂时不支持直接设置系统的回调事件
        if ($type == "server") {
            return $this;
        }
        $this->event[$type] = $event;
        return $this;
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array $config
     *
     * @return static
     */
    public function setConfig($config)
    {
        $this->config = array_map($this->config, $config);
        return $this;
    }

    public function watchFile($watchFile)
    {
        $this->watchFile = $watchFile;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }
    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }


    /**
     * [getRedis description]
     * @xiaolele
     * @return Redis [description]
     */
    public function getRedis()
    {
        return $this->redis;
    }
}
