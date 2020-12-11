<?php
namespace ImStart\Server\Http;

use ImStart\Console\Input;

use ImStart\Message\Http\Request as HttpRequest;
use ImStart\Server\Server;
use Swoole\Http\Server as SwooleServer;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;

/**
 * http server
 */
class HttpServer extends Server
{
    public function createServer()
    {
        $this->swooleServer = new SwooleServer($this->host, $this->port);
    }
    // 初始化默认设置
    protected function initSetting()
    {
        $config = app('config');
        $this->port = $config->get('server.http.port');
        $this->host = $config->get('server.http.host');
        $this->config = $config->get('server.http.swoole');
    }
    protected function initEvent(){
        $this->setEvent('sub', [
            'request' => 'onRequest',
        ]);
    }

    // onRequest

    public function onRequest(SwooleRequest $request, SwooleResponse $response)
    {
        $uri = $request->server['request_uri'];
        //chrome会两次请求
        if ($uri == '/favicon.ico') {
            $response->status(404);
            $response->end('');
            return null;
        }


        $httpRequest = HttpRequest::init($request);

        // 执行控制器的方法
        $return = app('route')->setFlag('Http')->setMethod($httpRequest->getMethod())->match($httpRequest->getUriPath());

        $response->end($return);
    }
}

