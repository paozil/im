<?php

use db\RedisType;
use swoole\MessageType;
use swoole\Task;
use tiny\Container;
use tiny\Loader;
use tiny\Logger;
use tiny\Redis;

require '../framework/Loader.php';
Loader::register();
Container::get("app")->initialize();

class WebSocketServer {

  public function __construct() {
    $ws = new swoole_websocket_server(config('swoole.websocket.host'), config('swoole.websocket.port'));

    $ws->on('open', [$this, 'onOpen']);
    $ws->on('start', [$this, 'onStart']);
    $ws->on('message', [$this, 'onMessage']);
    $ws->on('task', [$this, 'onTask']);
    $ws->on('finish', [$this, 'onFinish']);
    $ws->on('close', [$this, 'onClose']);

    $ws->set([
      'worker_num' => 1,
      'task_worker_num' => 1,
    ]);
    $ws->start();
  }

  public function onStart($server) {
    swoole_set_process_name("Ws_Server");
  }

  public function onOpen($ws, $request) {
    $uid = $request->get['uid'];
    //uid绑定线程id
    Redis::getInstance()->redis()->sAdd(RedisType::WS . $uid, $request->fd);
    //线程id绑定uid
    Redis::getInstance()->redis()->set(RedisType::WS . $request->fd, $uid);
    Logger::getInstance()->info("uid{$uid}与fd{$request->fd}双向绑定成功");
  }

  //通过uid获取fd
  //判断
  public function onMessage($ws, $frame) {
    $message = json_decode($frame->data);
    $data = [
      'type' => 'sendMessage',
      'data' => $message,
    ];
    $ws->task($data);
  }

  //销毁redis
  public function onClose($ws, $fd) {
    //获取fd对应的uid
    $uid = Redis::getInstance()->redis()->get(RedisType::WS . $fd);
    //删除fd对应的uid的记录
    Redis::getInstance()->redis()->del(RedisType::WS . $fd);
    //删除该uid的线程id
    Redis::getInstance()->redis()->sRem(RedisType::WS . $uid, $fd);

    Logger::getInstance()->info("{$fd}断开了连接");
  }

  public function onTask(swoole_server $server, int $taskId, int $workId, $data) {
    $obj = new Task();
    $method = $data['type'];
    $obj->{$method}($server, $data['data']);
    return "finish";
  }

  public function onFinish(swoole_server $server, int $taskId, $data) {
    Logger::getInstance()->info('finish');
  }
}

$ws = new WebSocketServer();