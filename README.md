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

<img src="https://raw.githubusercontent.com/andreybolonin/RatchetMultiInstance/master/RatchetMultiInstance.png">
