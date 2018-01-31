# Docker Compose Helper

**(STILL IN DEVELOPMENT, use at your own risk)**

> Please note that the network related functionality in this helper (alias/proxy) **only works on macOS**.
I'm really sorry about that. I would love to help Windows people as well,
but fact is that it simply does not support most of the required functionality.

If you use docker compose and you want to be able to develop using a decent url for your project like *http://myproject.test*  instead of *http://127.0.0.1:32721* with nearly no extra setup, then read on!

This tool wraps around docker-compose and will be ran instead of it so it can add some exiting new functionality to your development environment.

Please note that this script **will need sudo** rights for some of the commands.

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

## Notes

- Currently `dchelper` assumes that there will be "one of each service". 
  It is mainly for development purposes and until someone requests support for multiple containers per service I'm not going to think about the added complexity. 
- Mapping IP & Hostname is currently for the first found IP only (with a preference for aliased instead of proxied).

## What does it do?

In short:

 - Provides you with a "dedicated IP" for your project (instead of `127.0.0.1`)
 - Get rid of having to specify ports
 - Automatically create/maintain a hostname for your project. Use [myproject.test](myproject.test) in your browser instead of an IP!
 - Easily "shell" into a container (`docker-compose shell` and you have a login-shell into the default container).
 - Built-in helpers for docker-compose v3.4 (or higher) configurations (there's "envsubst" and scriptrunner at this moment, suggestions welcome)
 - All of this via environment variables (`.env`) or docker-compose.yml settings.
 
### Ports, ports, ports!

When publishing a port from a container, by default the docker VM maps this onto your `localhost` (`127.0.0.1`).

If you don't specify a remote port, you won't get port collisions but you'll end up with some obscure port like `32122`.
I don't know about you, but hosting my development site at `http(s)://127.0.0.1:32122` just doesn't seem right.
Even assigning a host to it doesn't make it any prettier when you always have to specify a port.

The alternative would be to map it to the default remote port, but then you'll probably end up with "port in use" etc.

Docker actually has its own solution for this and DCHelper adds a second one.

The premise is that we can add an extra virtual IP per docker-compose-project.
That would mean that heavily used ports like 80 and 443 are always available on "your" IP.

There is already a solution built-in in your OS: Linux (and by extension macOS) allow you to add "virtual" IPs to your loopback network interface (aka IP alias).
Both docker and `dchelper` can use this functionality to simplify your life a bit.

### Remote IPs (docker)

Docker (and Docker Compose) define a port specification as follows: `[[remote_ip:]remote_port[-remote_port]:]port[/protocol]`

This means that, when you are adding a published port, you can also specify the IP on which you want the port to be added:

```
container:
  ports:
    - 172.99.0.6:80:80
```

So the above tells docker to link service port `80` to port `80` on IP `172.99.0.6` (instead of `127.0.0.1`).
Pretty awesome no? There is a small gotcha however: `docker-compose` will not add the aliased IP to your network interface.

DCHelper will.

It interprets the docker-compose config in advance (as well as your `.env` if one was found) and determines what IPs need to be aliased for your configuration to work.

If it finds any that don't exist yet, it will register them for you before passing control to docker-compose.

Using this functionality is easy: The only modification needed to your configuration is what was shown in the example above.

More extensive example:

`.env`:
```
COMPOSE_ALIAS_IP=172.99.0.1
```

`docker-compose.yml`:
```
services:
  db:
   ports:
      - ${COMPOSE_ALIAS_IP}:3306:3306
      
  nginx:
    ports:
      - ${COMPOSE_ALIAS_IP}:80:80
```

By using a configured value you don't have much work to update the IP later on.
Since the variable will be replaced by docker-compose, DCHelper will still pick up the actual IPs.

`COMPOSE_ALIAS_IP` has a special meaning: DCHelper will always alias this IP, even if it wasn't used further in your configuration.
The variable is also used by the **hosts** functionality.

This means slightly more in your configuration file, but docker takes care of the rest, no external utilities needed.

### TCP Proxying/Tunneling (via socat)

This functionality uses [`socat` (SOcket CAT)](http://www.dest-unreach.org/socat/doc/socat.html), which is a pretty awesome tool that describes itself as a "Multipurpose relay".
(It can be easily installed on a mac via HomeBrew)

For this variant your ports-configuration remains as is:
```
container:
  ports:
    - 80
```

The trick here is that you either add a global `COMPOSE_PROXY_IP` environment variable, or specify a `PROXY_IP` in the containers' environment:

```
container:
  ports:
    - 80
  environment:
    PROXY_IP: 172.99.0.1  
```

(Specifying `PROXY_IP` allows you to use different IPs in your setup. Use the `COMPOSE_PROXY_IP` globally if you only need the one IP.)

When parsing the configuration, DCHelper will detect all published ports and where they are linked to.
It will then launch a bunch of `socat` instances to establish a tunnel between the proxy IP and the randomly assigned docker port on `127.0.0.1`.

The net effect will be the same as the "Remote IP"-method, so it depends on what you prefer.
The disadvantage here is that this only works if you detach from the containers when running the `up` command.
The tunneling can only be done after your "up" command finalises (containers need to be running to detect the configuration), which doesn't happen without `-d`

This example yields an identical result:

`.env`:
```
COMPOSE_PROXY_IP=172.99.0.1
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

As mentioned above: It is possible to use different IPs per service, just use `PROXY_IP` instead.

## Hostnames

By adding a `COMPOSE_HOSTNAME` to your environment, you tell DCHelper to check your `/etc/hosts` file and if needed, add a new entry to it.
The IP used will be either `COMPOSE_ALIAS_IP` or, if that wasn't set, `COMPOSE_PROXY_IP`.

There is currently no support for a per service/container host, just the global one.
(I'll add this if someone requests it, I currently have no need for it)

## Shell

DCHelper adds a `shell`-command. By doing `dchelper shell php` for example, it will trigger a (login) shell for in the related container.
This actually runs `docker exec -it <container> bash -l` in the background, but it will allow you to specify the compose service name instead of having to figure out the docker container name.

By using `COMPOSE_SHELL_DEFAULT=service-name` in your environment you can indicate what service to use if none was specified.
This can also be done by adding `SHELL_DEFAULT=1` to one of your service environment definitions.

If you have a terminal application that understands escape sequences (like iTerm2), DCHelper can also change the tab title for you.
You can either specify a title per service in the services environment using `SHELL_TITLE` or specify a global format via `COMPOSE_SHELL_TITLE`. In this case `{CONTAINER}` will be replaced by the containers' name.

For example: `COMPOSE_SHELL_TITLE="${COMPOSE_HOSTNAME: {CONTAINER}"`. The hostname replacement will be taken care of by docker compose, the `{CONTAINER}` will be replace by DCHelper.

## Helpers

This only works if you use compose file format v3.4 or later.
This is the first version that allows for (ignored) vendor-specific root entries (`x-...`) 

### Using helpers
#### Single helper
You can specify a single command to run:
```
x-dchelper:
  command:
    configuration...
```

```
x-dchelper:
  command:
    configuration...
  command2:   
    configuration...
```

#### Multiple helpers
If you want to run the same helper multiple times, you can add some "junk" to create different keys:

```
x-dchelper:
  command.serviceone:
    configuration...
  command.servicetwo:   
    configuration...
```
What is behind the dot is discarded, so use whatever you want.

#### Root

You can specify `root` as the helper name and it will set the 'base path' for every relative **source/origin** directory on
the local system:

```
x-dchelper:
  root: /generic/docker/folder/
  envsubst:
    files:
      - nginx.template:./.docker/nginx.conf
```

This will use the template from `/generic/docker/folder/nginx.template` and store it in the `.docker/nginx.conf` relative to where `docker-compose.yml` lives.

#### Stages

Currently the helpers can run at 2 stages: `pre.up` and `post.up`. (as in before and after the `docker-compose up` command).
By specifying `at` in the configuration you can override this behavior. 

### EnvSubst

If your compose project needs configuration files with values based on your environment, the trick so far was splice in an `envsubst` call somewhere
that takes care of this for you. DCHelper supports this natively and in a simple manner.

Note: This is internal functionality and does not require `envsubst` to be installed. 

To generate a configuration file, you just add an entry for `envsubst`:

```
x-dchelper:
  envsubst:
    environment:
      - .env
      - nginx
    files:
      - /generic/template/folder/nginx/site-fpm.conf:./.docker/site.conf
```

By default it runs @ `pre.up` 

#### environment

What environments to use. The default (if not specified) is everything from `.env`.
In the example it will use everything from `.env` and all environment from the *nginx* service.

#### files

List of files to do the replacement on. The source (or even target) files do not need to be in the project directory.

This allows you to create a number of templates and then generate a per-project config whenever you run `docker-compose up`.

By using the multiple commands syntax you can run `envsubst` multiple times if you want different environments.

By prepending the name of the output file with a service name the configuration will be created in the relevant container.
This path **has to be absolute**. [`docker cp`](https://docs.docker.com/engine/reference/commandline/cp/) is used for this functionality.

Example for the above:
```
 x-dchelper:
   envsubst:
     files:
       - /generic/template/folder/aws/credentials.conf:aws-cli:/home/root/.aws/site.conf
```

`dchelper` will make sure the target folder gets created for you if needed and then copies the file into it.

#### Result

The `envsubst` helper runs before anything else, so the generated files are available in all your services:

```
  nginx:
    image: nginx:latest
    volumes:
      - ./.docker/site.conf:/etc/nginx/conf.d/site.conf
``` 

### ScriptRunner

`scriptrunner` allows you to run shell scripts within the container. It doesn't really care if the script is mapped into the container or not, it just works around that.
 
An example:

```
x-dchelper:
  scriptrunner:
    service: php
    lock-file: /.scripts
    once:
      - /my/script/dir/php/install_pdo.sh
      - /my/script/dir/php/install_xdebug.sh      
```

#### service
What service/container to run against5

#### lock-file
For "once" command

## Full example

Below is an example of a full `docker-compose.yml`:

```
version: '3.4'

services:
  php:
    image: php:7-fpm
    volumes:
      - .:/project:rw
    environment:
      - XDEBUG_CONFIG=remote_enable=1 remote_mode=req remote_port=9000 remote_host=172.99.0.100 remote_connect_back=0
      - PHP_IDE_CONFIG=serverName=docker-dev

  nginx:
    image: nginx:latest
    volumes:
      - .:/project:rw
      - ./storage/app/docker/site.conf:/etc/nginx/conf.d/site.conf
    ports:
      - ${COMPOSE_ALIAS_IP}:80:80

  db:
    image: mysql
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - ~/tmp/dev-data/myproject.test/mysql:/var/lib/mysql:rw
    ports:
      - ${COMPOSE_ALIAS_IP}:3306:3306

x-dchelper:
  root: ~/Steve/Development/Docker
  envsubst:
    environment:
      - .env
      - nginx
    files:
      - nginx/conf/site_php-fpm_php-9000.template:./storage/app/docker/site.conf
  envsubst.aws:
    at: post.up
    files:
      - aws/conf/credentials.template:php:/root/.aws/credentials 
  scriptrunner:
    service: php
    lock-file: /.scripts
    once:
      - debian/scripts/install_aws-cli.sh
      - debian/scripts/install_top.sh
      - debian/scripts/install_ping.sh
      - php/scripts/install_pdo_mysql.sh
      - php/scripts/install_xdebug.sh
      - php/scripts/laravel_artisan_xdebug.sh
      - php/scripts/fpm_reload.sh
``` 

Extra things in my `.env`:

```
COMPOSE_ALIAS_IP=172.99.0.1
COMPOSE_HOSTNAME=myproject.test
COMPOSE_SHELL_TITLE="${COMPOSE_HOSTNAME}: {CONTAINER}"

APP_URL=http://${COMPOSE_HOSTNAME}
```

So basically:
This config will give you a working [http://myproject.test](http://myproject.test) that has a port `80` and `3306`. It runs on a 3 container structure, all using unmodified images from the docker hub.

The `root` entry signifies that all relative **source** locations will be mapped in there. 
The target locations do not use this root. `~` is translate for both target and source. 
Note: if the `root` is also relative it will use the current working directory as its base.

 
We use 2 `envsubst` commands here:
The `nginx`-one creates a locally stored file based on the configuration at the `pre.up` stage. The resulting file is mounted into the `nginx` container so that it can be used when it boots.
The `aws`-one will create a file directly in the container. Since that functionality only works *after* it the container up, we use `at: post.up`. 

The `scriptrunner` command runs some of my reusable scripts to install things in the containers and it keeps track of what was installed within that container. 
`once` indicates that these will only be ran once, if not found in the `lock-file` yet.
Since these will be executed "passthrough", you'll see what is being done in your console without having to look in the container logs to see when things are wrapped up. 

The `.env` file has an `APP_URL` that is already part of the laravel settings, but I added it to show that I just reuse the variables there as well. 
The goal here is to have to modify as little as possible to setup a new project.
