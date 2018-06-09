#!/bin/bash

cwd=$(pwd)

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


git clone -b $(curl -L https://grpc.io/release) https://github.com/grpc/grpc
cd grpc
git pull --recurse-submodules && git submodule update --init --recursive
make
sudo make install

cd src/php/ext/grpc
phpize
./configure
make
sudo make install

cd $cwd