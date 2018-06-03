# QuickStart

### 0) Check, do you install high perfomance C event extension (libevent, libev, libuv)

http://socketo.me/docs/deploy#evented-io-extensions

https://github.com/reactphp/event-loop#exteventloop

https://bitbucket.org/osmanov/pecl-event

https://bitbucket.org/osmanov/pecl-ev

https://github.com/bwoebi/php-uv

| Connections	| stream_select | libevent
| ------------- |:-------------:| -----:|
| 100	        | 10.656	    | 9.298
| 500	        | 11.175	    | 9.791
| 800	        | 17.327	    | 9.709
| 1000	        | 23.282	    | 9.749

https://www.pigo.idv.tw/archives/589

`libevent` vs `libev`

http://libev.schmorp.de/bench.html

`libev` vs `libuv`

https://gist.github.com/andreybolonin/2413da76f088e2c5ab04df53f07659ea

### 1) Set ulimit

add to `/etc/security/limits.conf`

```sh
*               soft    nofile          1000000
*               hard    nofile          1000000
``` 

add to `/etc/sysctl.conf`

`fs.file-max=1000000`

relogin to your server and check

`ulimit -n` should be `1000000`

### 2) Install

`composer req andreybolonin/ratchet-bundle`

### 3) Define your pool

`config/packages/ratchet_bundle.yaml`

```sh
ratchet_bundle:
    wampserver_pool: ['127.0.0.1:8095', '127.0.0.1:8097', '127.0.0.1:8099']
```

### 4) Run your nodes

`bin/console wamp:server:run --host=127.0.0.1 --port=8095`

`bin/console wamp:server:run --host=127.0.0.1 --port=8097`

`bin/console wamp:server:run --host=127.0.0.1 --port=8099`

### 5) Setup NGINX (as load balancer)

`/etc/nginx/nginx.conf`

```sh
worker_processes        auto;
worker_rlimit_nofile    49152;
timer_resolution        100ms;
worker_priority         -5;

events {
    use epoll;
    worker_connections 24576;
    multi_accept on;
}
```

`/etc/nginx/sites-enabled/*`

```sh
upstream socket {
    server 127.0.0.1:8095;
    server 127.0.0.1:8097;
    server 127.0.0.1:8099;
}

map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}

server {
	server_name 127.0.0.1;
	listen 8090;

	proxy_next_upstream error;
	proxy_set_header X-Real-IP $remote_addr;
	proxy_set_header X-Scheme $scheme;
	proxy_set_header Host $http_host;

	location / {
		proxy_pass http://socket;
                proxy_http_version 1.1;
                proxy_set_header Upgrade $http_upgrade;
                proxy_set_header Connection "upgrade";
                proxy_set_header Host $host;

                proxy_set_header X-Real-IP $remote_addr;
                proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
                proxy_set_header X-Forwarded-Proto https;
                proxy_read_timeout 86400; # neccessary to avoid websocket timeout disconnect
                proxy_redirect off;
	}
}
```

http://nginx.org/en/docs/http/load_balancing.html

http://nginx.org/en/docs/http/websocket.html

### 6) Define your Topic-class:

```sh
<?php

namespace App\Topic;

use App\Entity\Bidding;
use App\Entity\Lot;
use App\Entity\LotStatistic;
use App\Entity\Order;
use App\Entity\User;
use App\Entity\Session;
use App\Service\Counter;
use App\Twig\LotStatus;
use Doctrine\ORM\EntityManagerInterface;
use Gos\Component\WebSocketClient\Wamp\Client;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;
use Ratchet\Wamp\Topic;
use Ratchet\ConnectionInterface;
use Ratchet\Wamp\WampServerInterface;

/**
 * Class BiddingTopic.
 */
class BiddingTopic implements WampServerInterface
{
    /**
     * @const Количество повторений торговых периодов без подтверждения стартовой цены
     */
    const BIDDING_PERIOD_REPEAT = 3;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var Counter
     */
    private $counter;

    /**
     * @var Router
     */
    private $router;

    /**
     * @var array
     */
    private $wampserver_broadcast;

    /**
     * @var string
     */
    private $websocket_this_node;

    /**
     * CounterTopic constructor.
     *
     * @param EntityManagerInterface $em
     * @param Counter                $counter
     * @param RouterInterface        $router
     */
    public function __construct(EntityManagerInterface $em, Counter $counter, RouterInterface $router, array $wampserver_broadcast, string $websocket_this_node)
    {
        $this->em = $em;
        $this->counter = $counter;
        $this->router = $router;

        $this->wampserver_broadcast = $wampserver_broadcast;
        $this->websocket_this_node = $websocket_this_node;

        $key = array_search($this->websocket_this_node, $this->wampserver_broadcast);
        unset($this->wampserver_broadcast[$key]);
    }

    /**
     * This will receive any Subscription requests for this topic.
     *
     * @param ConnectionInterface $connection
     * @param Topic               $topic
     */
    public function onSubscribe(ConnectionInterface $connection, $topic)
    {
        //this will broadcast the message to ALL subscribers of this topic.
        $topic->broadcast(['msg' => $connection->resourceId.' has joined '.$topic->getId()]);
    }

    /**
     * This will receive any UnSubscription requests for this topic.
     *
     * @param ConnectionInterface $connection
     * @param Topic               $topic
     */
    public function onUnSubscribe(ConnectionInterface $connection, $topic)
    {
        //this will broadcast the message to ALL subscribers of this topic.
        $topic->broadcast(['msg' => $connection->resourceId.' has left '.$topic->getId()]);
    }

    /**
     * Онлайн-торги.
     *
     * This will receive any Publish requests for this topic.
     *
     * @param ConnectionInterface $connection
     * @param Topic               $topic
     * @param $event
     * @param array $exclude
     * @param array $eligible
     *
     * @return mixed|void
     */
    public function onPublish(ConnectionInterface $connection, $topic, $event, array $exclude, array $eligible)
    {
        switch ($topic->getId()) {
            case 'counter/channel':
                $this->CounterTopic($connection, $topic, $event, $exclude, $eligible);
                break;

            case 'price/channel':
                $this->PriceTopic($connection, $topic, $event, $exclude, $eligible);
                break;

            case 'broadcast/channel':
                $this->BroadcastTopic($connection, $topic, $event, $exclude, $eligible);
                break;
        }
    }

    /**
     * Counter Topic.
     *
     * This will receive any Publish requests for this topic.
     *
     * @param ConnectionInterface $connection
     * @param Topic               $topic
     * @param $event
     * @param array $exclude
     * @param array $eligible
     *
     * @return mixed|void
     */
    public function CounterTopic(ConnectionInterface $connection, $topic, $event, array $exclude, array $eligible)
    {
	// если все лоты сняты или проданы - снимаем сессию
	if ($allLotsSales) {
	    $session->setDateFinished(new \DateTime());
	    $event['finishUrl'] = $this->router->generate('cabinet_session_finish', ['session_id' => $session->getId()]);
	    $topic->broadcast($event);
	    $this->broadcast($event);
	} else {
	    $connection->event($topic->getId(), $event);
	}
    }

    /**
     * Price Topic.
     *
     * This will receive any Publish requests for this topic.
     *я
     *
     * @param ConnectionInterface $connection
     * @param Topic               $topic
     * @param $event
     * @param array $exclude
     * @param array $eligible
     *
     * @return mixed|void
     */
    public function PriceTopic(ConnectionInterface $connection, $topic, $event, array $exclude, array $eligible)
    {
        if ($lots && $buyer && Lot::STATUS_ON_BIDDING == $lots[0]->getStatus()) {
                $topic->broadcast($event);
                $this->broadcast($event);
            } else {
                $connection->event($topic->getId(), false);
            }
        } else {
            $connection->event($topic->getId(), false);
        }
    }

    /**
     * BroadcastTopic.
     *
     * This will receive any Publish requests for this topic.
     *
     * @param ConnectionInterface $connection
     * @param Topic               $topic
     * @param $event
     * @param array $exclude
     * @param array $eligible
     *
     * @return mixed|void
     */
    public function BroadcastTopic(ConnectionInterface $connection, $topic, $event, array $exclude, array $eligible)
    {
        $topic->broadcast($event);
    }

    public function onCall(ConnectionInterface $connection, $id, $topic, array $params)
    {
        $connection->callError($id, $topic, 'RPC not supported on this demo');
    }

    public function onOpen(ConnectionInterface $connection)
    {
        echo $connection->resourceId.' connected'.PHP_EOL;
    }

    public function onClose(ConnectionInterface $connection)
    {
        echo $connection->resourceId.' disconnected'.PHP_EOL;
    }

    public function onError(ConnectionInterface $connection, \Exception $e)
    {
        echo 'connection error occurred: '.$e->getMessage().PHP_EOL;
    }

    /**
     * @param array $event
     *
     * @throws \Gos\Component\WebSocketClient\Exception\BadResponseException
     * @throws \Gos\Component\WebSocketClient\Exception\WebsocketException
     */
    public function broadcast(array $event)
    {
        foreach ($this->wampserver_broadcast as $broadcast) {
            $host = parse_url($broadcast, PHP_URL_HOST);
            $port = parse_url($broadcast, PHP_URL_PORT);
            var_dump($host.':'.$port);
            $client = new Client($host, $port);
            $client->connect();
            $client->publish('broadcast/channel', $event);
            $client->disconnect();
        }
    }
}

```

```sh
    app.bidding_topic_service:
        class: App\Topic\BiddingTopic
        arguments:
            $em: '@doctrine.orm.entity_manager'
            $counter: '@app.service.counter'
            $router: '@Symfony\Component\Routing\RouterInterface'
        public: true
        lazy: true
```

### 7) Inject

`use RatchetMultiInstanceTrait;` into your Topic-class

### 8) Send the 

`$topic->broadcast($event)` with `$this->broadcast($event)` for broadcasting in another WampServer nodes

### 9) Benchmark

`wrk -t4 -c400 -d10s ws://127.0.0.1:8090`

1 node (828req/sec)

2 node (4.410req/sec)

<img src="https://raw.githubusercontent.com/andreybolonin/RatchetBundle/master/bench.png">

### 10) Arch

Is something differnt of https://nodejs.org/api/cluster.html#cluster_cluster

https://hackernoon.com/scaling-websockets-9a31497af051

<img src="https://raw.githubusercontent.com/andreybolonin/RatchetMultiInstance/master/RatchetMultiInstance.png">
