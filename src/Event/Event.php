<?php
namespace ImStart\Event;

/**
 * 事件类
 */
class Event
{
    protected $events = [];

    /**
     * 事件注册
     * @param  string $event    事件标识
     * @param  Closure $callback 事件回调函数
     */
    public function register($event, $callback)
    {
        $event = \strtolower($event);

        // 判断事件是否存在
        // if (condition) {
        //   // code...
        // }

        $this->events[$event] = ['callback' => $callback];
    }
    /**
     * 事件的触发函数
     * @param  string $event 事件标识
     * @param  array  $param 事件参数
     * @return bool
     */

    public function trigger($event, $param = [])
    {
        $event = \strtolower($event);

        if (isset($this->events[$event])) {
            ($this->events[$event]['callback'])(...$param);
            echo "事件执行成功\n";
            return true;
        }
        echo "事件不存在\n";
    }

    public function getEvents($event = null)
    {
        return empty($event) ? $this->events : $this->events[$event];
    }
}
