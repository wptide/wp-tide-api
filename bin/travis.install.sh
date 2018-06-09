#!/bin/bash

# Get and make mongodb PHP driver
if [[ ! -e $HOME/mongo-php-driver ]]; then
    apt-get install libssl-dev \
        && git clone https://github.com/mongodb/mongo-php-driver.git --recursive \
        && cd mongo-php-driver \
        && phpize \
        && ./configure --with-mongodb-ssl=openssl \
        && make all \
        && make install \
        && echo "extension=mongodb.so" >> `php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||"` \
        && cd ..
fi