#!/bin/bash

# Get and make mongodb PHP driver
sudo apt-get install libssl-dev \
    && git clone https://github.com/mongodb/mongo-php-driver.git --recursive \
    && cd mongo-php-driver \
    && phpize \
    && ./configure --with-mongodb-ssl=openssl \
    && make all \
    && make install \
    && echo "extension=mongodb.so" >> `php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||"` \
    && cd .. \
    && rm -rf mongo-php-driver

// Install gRPC
sudo apt-get install php-pear \
    && sudo pecl channel-update pecl.php.net \
    && sudo pecl install grpc \
    && echo "extension=grpc.so" >> `php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||"`
