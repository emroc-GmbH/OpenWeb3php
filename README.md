# OpenWeb3php
[![PHP](https://github.com/emroc-GmbH/OpenWeb3php/actions/workflows/php.yml/badge.svg)](https://github.com/emroc-GmbH/OpenWeb3php/actions/workflows/php.yml)


Easy interaction with the Ethereum and compatible blockchain ecosystem for PHP 8!


 
- [changelog](CHANGELOG.md)
- [https://github.com/emroc-GmbH/OpenWeb3php](https://github.com/emroc-GmbH/OpenWeb3php)

# Install

```
composer require emroc/openweb3php ^0.2
```

Or you can add this line in composer.json

```
"emroc/openweb3php": "^0.2"
```


# Usage

### New instance

```php
use OpenWeb3\Web3;

$web3 = new Web3('http://localhost:8545');
```

### Using provider

```php
use OpenWeb3\Web3;
use OpenWeb3\Providers\HttpProvider;
use OpenWeb3\RequestManagers\HttpRequestManager;

$web3 = new Web3(new HttpProvider(new HttpRequestManager('http://localhost:8545')));

// timeout
$web3 = new Web3(new HttpProvider(new HttpRequestManager('http://localhost:8545', 0.1)));
```

### You can use callback to each rpc call:
```php
$web3->clientVersion(function ($err, $version) {
    if ($err !== null) {
        // do something
        return;
    }
    if (isset($version)) {
        echo 'Client version: ' . $version;
    }
});
```

### Eth

```php
use OpenWeb3\Web3;

$web3 = new Web3('http://localhost:8545');
$eth = $web3->eth;
```

Or

```php
use OpenWeb3\Eth;

$eth = new Eth('http://localhost:8545');
```

### Net

```php
use OpenWeb3\Web3;

$web3 = new Web3('http://localhost:8545');
$net = $web3->net;
```

Or

```php
use OpenWeb3\Net;

$net = new Net('http://localhost:8545');
```

### Batch

web3
```php
$web3->batch(true);
$web3->clientVersion();
$web3->hash('0x123456789');
$web3->execute(function ($err, $data) {
    if ($err !== null) {
        // do something
        // it may throw exception or array of exception depends on error type
        // connection error: throw exception
        // json rpc error: array of exception
        return;
    }
    // do something
});
```

eth

```php
$eth->batch(true);
$eth->protocolVersion();
$eth->syncing();

$eth->provider->execute(function ($err, $data) {
    if ($err !== null) {
        // do something
        return;
    }
    // do something
});
```

net
```php
$net->batch(true);
$net->version();
$net->listening();

$net->provider->execute(function ($err, $data) {
    if ($err !== null) {
        // do something
        return;
    }
    // do something
});
```

personal
```php
$personal->batch(true);
$personal->listAccounts();
$personal->newAccount('123456');

$personal->provider->execute(function ($err, $data) {
    if ($err !== null) {
        // do something
        return;
    }
    // do something
});
```

### Contract

```php
use OpenWeb3\Contract;

$contract = new Contract('http://localhost:8545', $abi);

// deploy contract
$contract->bytecode($bytecode)->new($param1,$param2,..., $callback);

// call contract function
$contract->at($contractAddress)->call($functionName, $param1,$param2,..., $callback);

// change function state
$contract->at($contractAddress)->send($functionName, $param1,$param2,..., $callback);

// estimate deploy contract gas
$contract->bytecode($bytecode)->estimateGas($param1,$param2,..., $callback);

// estimate function gas
$contract->at($contractAddress)->estimateGas($functionName, $param1,$param2,..., $callback);

// get constructor data
$constructorData = $contract->bytecode($bytecode)->getData($param1,$param2,...);

// get function data
$functionData = $contract->at($contractAddress)->getData($functionName, $param1,$param2,...);

//get event log data 
//$fromBlock and $toBlock are optional, default to 'latest' and accept block numbers integers
$events = $contract->getEventLogs($eventName, $fromBlock, $toBlock);
```

# Assign value to outside scope(from callback scope to outside scope)
Due to callback is not like javascript callback, 
if we need to assign value to outside scope, 
we need to assign reference to callback.
```php
$newAccount = '';

$web3->personal->newAccount('123456', function ($err, $account) use (&$newAccount) {
    if ($err !== null) {
        echo 'Error: ' . $err->getMessage();
        return;
    }
    $newAccount = $account;
    echo 'New account: ' . $account . PHP_EOL;
});
```

# Examples

To run examples, you need to run ethereum blockchain local (testrpc).

If you are using docker as development machain, you can try [ethdock](https://github.com/sc0vu/ethdock) to run local ethereum blockchain, just simply run `docker-compose up -d testrpc` and expose the `8545` port.

# Develop

### Local php cli installed

1. Clone the repo and install packages.
```
git clone https://github.com/emroc/OpenWeb3php.git && cd web3.php && composer install
```

2. Run test script.
```
vendor/bin/phpunit
```

### Docker container

1. Clone the repo and run docker container.
```
git clone https://github.com/emroc/OpenWeb3php.git
```

2. Copy web3.php to web3.php/docker/app directory and start container.
```
cp files docker/app && docker-compose up -d php ganache
```

3. Enter php container and install packages.
```
docker-compose exec php ash
```

4. Change testHost in `TestCase.php`
```
/**
 * testHost
 * 
 * @var string
 */
protected $testHost = 'http://ganache:8545';
```

5. Run test script
```
vendor/bin/phpunit
```

###### Install packages
Enter container first
```
docker-compose exec php ash
```

1. gmp
```
apk add gmp-dev
docker-php-ext-install gmp
```

2. bcmath
```
docker-php-ext-install bcmath
```

###### Remove extension
Move the extension config from `/usr/local/etc/php/conf.d/`
```
mv /usr/local/etc/php/conf.d/extension-config-name to/directory
```

# History
OpenWeb3php started as a fork of [web3p/web3.php](https://github.com/web3p/web3.php)!

# License
MIT
