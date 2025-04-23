FROM php:8.1-cli-alpine

RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories && \
    apk update && \
    # 安装基础依赖
    apk add --no-cache \
    autoconf \
    build-base \
    libevent-dev \
    libuuid \
    e2fsprogs-dev \
    libzip-dev \
    openssl-dev \
    libpq-dev \
    libpng-dev \
    libwebp-dev \
    libjpeg-turbo-dev \
    freetype-dev  \
    strace \
    linux-headers && \
    # 配置GD库
    docker-php-ext-configure gd \
    --with-jpeg=/usr/include/ \
    --with-freetype=/usr/include/ && \
    # 安装php扩展
    docker-php-ext-install sockets pcntl pdo_pgsql bcmath zip gd && \
    # 安装pecl扩展
    pecl install redis uuid event && \
    # 启用pecl扩展
    docker-php-ext-enable redis uuid && \
    # 启用event
    docker-php-ext-enable --ini-name event.ini event && \
    # 安装composer
    curl -o /usr/local/bin/composer https://mirrors.aliyun.com/composer/composer.phar && chmod +x /usr/local/bin/composer

EXPOSE 8000 8001
VOLUME /var/www
WORKDIR /var/www

# 覆盖启动命令，不再直接启动服务器，而是用一个空进程维持容器运行
STOPSIGNAL SIGKILL
CMD tail -f /dev/null
