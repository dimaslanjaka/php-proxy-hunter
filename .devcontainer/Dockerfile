FROM mcr.microsoft.com/devcontainers/base:ubuntu

# Set environment variable
ENV NODE_OPTIONS="--max_old_space_size=4096"

# Update Ubuntu packages and install necessary tools
RUN apt-get update \
    && apt-get install -y \
        software-properties-common \
        apt-transport-https \
        ca-certificates \
        curl \
        gnupg \
        lsb-release \
        git \
        unzip \
        wget \
        libicu-dev \
    && apt-get clean

# Install PHP and extensions
RUN add-apt-repository -y ppa:ondrej/php \
    && apt-get update \
    && apt-get install -y \
        php \
        php-cli \
        php-common \
        php-curl \
        php-xml \
        php-mbstring \
        php-mysql \
        php-pgsql \
        php-sqlite3 \
        php-json \
        php-zip \
        php-bcmath \
        php-intl \
        php-gd \
        php-dev \
        php-zip \
    && apt-get clean

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Node.js 18
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && npm uninstall -g yarn pnpm \
    && npm install -g corepack \
    && corepack prepare yarn --activate \
    && apt-get clean

# Install Python 3.11
RUN add-apt-repository -y ppa:deadsnakes/ppa \
    && apt-get update \
    && apt-get remove -y python \
    && apt-get install -y \
        python3.11 \
        python3.11-dev \
        python3.11-distutils \
    && apt-get clean

# Set default versions
# RUN update-alternatives --install /usr/bin/python3 python3 /usr/bin/python3.11 1
