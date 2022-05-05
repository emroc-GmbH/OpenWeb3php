#!/usr/bin/env sh


# phpstan test
echo "phpstan tests..."
vendor/bin/phpstan -n
ret=$?
if [ $ret != 0 ]; then
  echo "phpstan error"
  # make sure to stop ganache-cli
  kill -9 $ganachecli_pid
  echo "Kill ganache-cli"
  return $ret
fi

# starting ganache
ganache-cli -g 0 -l 6000000 >/dev/null &
ganachecli_pid=$!
echo "Start ganache-cli pid: $ganachecli_pid and sleep 3 seconds"

sleep 3

# phpunit test
echo "phpunit tests..."
vendor/bin/phpunit --coverage-clover=coverage.xml
ret=$?

kill -9 $ganachecli_pid
echo "Kill ganache-cli"

exit $ret
