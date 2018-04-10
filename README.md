# QuickStart

### 0) Check, do you install high perfomance C event extension (libevent, libev)

http://socketo.me/docs/deploy#evented-io-extensions

https://github.com/reactphp/event-loop#exteventloop

https://bitbucket.org/osmanov/pecl-event

https://bitbucket.org/osmanov/pecl-ev

| Connections	| stream_select | libevent
| ------------- |:-------------:| -----:|
| 100	        | 10.656	    | 9.298
| 500	        | 11.175	    | 9.791
| 800	        | 17.327	    | 9.709
| 1000	        | 23.282	    | 9.749

https://www.pigo.idv.tw/archives/589

`libevent` vs `libev`

http://libev.schmorp.de/bench.html

### 1) Install

`composer req andreybolonin/ratchet-bundle`

### 2) Define your pool

`config/services.yaml`

```sh
ratchet_bundle:
    wampserver_pool: ['127.0.0.1:8095', '127.0.0.1:8097', '127.0.0.1:8099']
```

### 3) Run your nodes

`bin/console wamp:server:run --host=127.0.0.1 --port=8095`

`bin/console wamp:server:run --host=127.0.0.1 --port=8097`

`bin/console wamp:server:run --host=127.0.0.1 --port=8099`

### 4) Setup NGINX (as load balancer)

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

### 5) Define your Topic-class:

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

### 6) Inject

`use RatchetMultiInstanceTrait;` into your Topic-class

### 7) Send the 

`$topic->broadcast($event)` with `$this->broadcast($event)` for broadcasting in another WampServer nodes

### 8) Benchmark

`wrk -t4 -c400 -d10s ws://127.0.0.1:8090`

### 9) Arch

<img src="https://raw.githubusercontent.com/andreybolonin/RatchetMultiInstance/master/RatchetMultiInstance.png">
