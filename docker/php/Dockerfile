FROM php:8.2-cli

ARG XDEBUG_VERSION=3.5.0

# Install build deps, compile and enable Xdebug, then remove build deps to keep image small
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        unzip \
        libzip-dev \
        libxml2-dev \
        zlib1g-dev \
        libssl-dev \
        build-essential \
        autoconf \
        pkg-config \
    ; \
    pecl channel-update pecl.php.net; \
    pecl install xdebug-${XDEBUG_VERSION}; \
    docker-php-ext-enable xdebug; \
    # cleanup build deps and package lists to reduce image size
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false \
        build-essential autoconf pkg-config unzip \
    ; \
    rm -rf /var/lib/apt/lists/* /tmp/pear /var/cache/apt/*;

# Do not enable coverage by default; allow enabling via environment variable XDEBUG_MODE
ENV XDEBUG_MODE=off

# Small wrapper to show versions when running image interactively
CMD ["php", "-v"]
