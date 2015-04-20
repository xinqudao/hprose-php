<?php
/**********************************************************\
|                                                          |
|                          hprose                          |
|                                                          |
| Official WebSite: http://www.hprose.com/                 |
|                   http://www.hprose.org/                 |
|                                                          |
\**********************************************************/

/**********************************************************\
 *                                                        *
 * Hprose/Swoole/WebSocket/Service.php                    *
 *                                                        *
 * hprose swoole websocket service library for php 5.3+   *
 *                                                        *
 * LastModified: Apr 20, 2015                             *
 * Author: Ma Bingyao <andot@hprose.com>                  *
 *                                                        *
\**********************************************************/

namespace Hprose\Swoole\WebSocket {
    class Service extends \Hprose\Swoole\Http\Service {
        private function ws_handle($server, $fd, $data) {
            $id = substr($data, 0, 4);
            $data = substr($data, 4);

            $context = new \stdClass();
            $context->server = $server;
            $context->fd = $fd;
            $context->id = $id;
            $context->userdata = new \stdClass();
            $self = $this;

            $this->user_fatal_error_handler = function($error) use ($self, $context) {
                @ob_end_clean();
                $context->server->push($context->fd, $context->id . $self->sendError($error, $context), true);
            };

            $result = $this->defaultHandle($data, $context);

            $server->push($fd, $id . $result, true);
        }
        public function set_ws_handle($server) {
            $self = $this;
            $buffers = array();
            $server->on('open', function ($server, $request) use (&$buffers) {
                if (isset($buffers[$request->fd])) {
                    unset($buffers[$request->fd]);
                }
            });
            $server->on('close', function ($server, $fd) use (&$buffers) {
                if (isset($buffers[$fd])) {
                    unset($buffers[$fd]);
                }
            });
            $server->on('message', function($server, $frame) use (&$buffers, $self) {
                if (isset($buffers[$frame->fd])) {
                    if ($frame->finish) {
                        $data = $buffers[$frame->fd] . $frame->data;
                        unset($buffers[$frame->fd]);
                        $self->ws_handle($server, $frame->fd, $data);
                    }
                    else {
                        $buffers[$frame->fd] .= $frame->data;
                    }
                }
                else {
                    if ($frame->finish) {
                        $self->ws_handle($server, $frame->fd, $frame->data);
                    }
                    else {
                        $buffers[$frame->fd] = $frame->data;
                    }
                }
            });
        }
    }

    class Server extends Service {
        private $ws;
        public function __construct($host, $port) {
            parent::__construct();
            $this->ws = new \swoole_websocket_server($host, $port);
        }
        public function set($setting) {
            $this->ws->set($setting);
        }
        public function addListener($host, $port) {
            $this->ws->addListener($host, $port);
        }
        public function start() {
            $this->set_ws_handle($this->ws);
            $this->ws->on('request', array($this, 'handle'));
            $this->ws->start();
        }
    }
}