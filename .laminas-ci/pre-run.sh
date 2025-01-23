#!/bin/bash

JOB=$3
PHP_VERSION=$(echo "${JOB}" | jq -r '.php')
if [ "$PHP_VERSION" = "8.4" ] ; then
  mv phpunit.xml.php84 phpunit.xml.dist
fi