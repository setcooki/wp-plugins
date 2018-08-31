#!/usr/bin/env bash

PHP=$(which php)

#parse command line arguments
for i in "$@"
do
case $i in
    --php=*)
    PHP="${i#*=}"
    shift # past argument=value
    ;;
    *)
    # unknown option
    ;;
esac
done;

$PHP -d phar.readonly=0 vendor/bin/phar-builder package composer.json -z -o ./dist