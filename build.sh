#!/usr/bin/env bash

php=$(which php)

$php -d phar.readonly=0 vendor/bin/phar-builder package composer.json -f -n -o dist