#!/usr/bin/env bash

php -d phar.readonly=0 vendor/bin/phar-builder package composer.json -z -o ./dist