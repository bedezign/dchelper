# Docker Compose Helper

**(STILL IN DEVELOPMENT, use at your own risk)**

> Please note that most functionality in this helper **only works on macOS**.
I'm really sorry about that. I would love to help Windows people as well, 
but fact is that it simply does not support most of the required functionality.

If you use docker compose and you want to be able to develop using a decent url for your project like *http://myproject.test*  instead of *http://127.0.0.1:32721* with nearly no extra setup, then read on!

This tool wraps around docker-compose and will be ran instead of it so it can add some exiting new functionality to your development environment.

## Installing

You can simply add it via packagist/composer:

```
composer global require bedezign/dchelper
```

After that you might want to setup an alias in your `.bashrc` file:
```
alias docker-compose="php ~/.composer/bin/dchelper"
```

It's as easy as that!

## What does it do?

### Ports, ports, ports!

When publishing a port from a container, by default the docker VM maps this onto your `localhost` (`127.0.0.1`).

If you don't specify a remote port, you won't get port collisions but you'll end up with some obscure port like `32122`.
I don't know about you, but hosting my site at `http(s)://127.0.0.1:32122` just doesn't seem right.
Even assigning a host to it doesn't make it any prettier when you have to specify a port.

The alternative would be to specify a remote port but then you'll end up with collisions (if you can already use port 80 for example, it's only for one container) 

Docker actually has its own solution for this and DCHelper adds a second one.

Linux (and by extension macOS) allow you to add ("virtual") IPs to your loopback network interface (IP alias).
Both docker and `dchelper` can use this functionality to simplify your life a bit.

The premise of all this is that we can add an extra virtual IP per docker-compose project we are running.
This also means that those heavily used ports like 80 and 443 are always available on "your" IP. 

### Remote IPs (docker)

When adding a published port, you can simply specify the IP on which you want the port to be added:

```
container:
  ports:
    - 172.99.0.6:80:80
``` 

This tells docker to link port `80` on IP `172.99.0.6` instead of `127.0.0.1`. 
docker-compose will not, however, add the aliased IP to your network interface.

DCHelper will. Interprets the docker-compose config in advance (as well as your `.env` if one was found) and determines what IPs need to be aliased for your configuration to work.

If it finds any that don't exist yet, it will "register" them for you before passing control to docker-compose.

Using this functionality is easy: The only modification you had to do was what was shown in the example above.

More extensive example:

`.env`:
```
DOCKER_ALIAS_IP=172.99.0.1
```

`docker-compose.yml`:
```
services:
  db:
   ports:
      - ${DOCKER_ALIAS_IP}:3306:3306
      
  nginx:
    ports:
      - ${DOCKER_ALIAS_IP}:80:80
```

In this example the name `DOCKER_ALIAS_IP` is not really important. 
Since it will be replaced by docker-compose in the config file, DCHelper will pick up the IPs. 
Doing it like that makes it easier to change the IP later on.

That being said, DCHelper will pick up a `DOCKER_ALIAS_IP` environment variable on its own and alias it. Even if not used further in your configuration.
Might be handy, no idea.
 
In any case, docker takes care of the rest if the IP exists.

### TCP Proxying (via socat)

This functionality uses [`socat` (SOcket CAT)](http://www.dest-unreach.org/socat/doc/socat.html), wich is a pretty awesome tool that describes itself as a "Multipurpose relay".
It can be easily installed on a mac via HomeBrew.

For people not wanting to pollute their `docker-compose.yml`-files with IP addresses and so on: This is for you.

Your configuration remains as is:
```
container:
  ports:
    - 80
```

The trick here is that you either add a global `DOCKER_PROXY_IP` environment variable, or specify a `PROXY_IP` in the containers' environment:

```
container:
  ports:
    - 80
  environment:
    PROXY_IP: 172.99.0.1  
```

(Specifying `PROXY_IP` allows you to use different IPs in your setup. Use the `DOCKER_PROXY_IP` globally if you only need the one IP.)

When parsing the configuration, DCHelper will detect all published ports and where they are linked to.
It will then launch a bunch of `socat` instances to establish a tunnel between the specified proxy IP and the randomly assigned docker port.

The net effect will be the same as the remote-ip method, so it depends on what you prefer. Disadvantage here is that this only works if you detach from the containers when running the `up` command, since everything needs to be running to be able to figure out the ports.

This example yields an identical result:

`.env`:
```
DOCKER_PROXY_IP=172.99.0.1
```
`docker-compose.yml`:
```
services:
  db:
   ports:
      - 3306
      
  nginx:
    ports:
      - 80
```
   
Both port `3306` and `80` will be available via `172.99.0.1`.
 
If you want different IPs depending on the 

## Hostnames (still in development)

By adding a `DOCKER_HOSTNAME` to your environment, you tell dchelper to check your `/etc/hosts` file and if needed, add a new entry to it.

This should probably also support a per service/container host instead of only global. 
