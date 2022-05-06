<?php

/**
 * This file is part of web3.php package.
 * 
 * (c) Kuan-Cheng,Lai <alk03073135@gmail.com>
 * 
 * @author Peter Lai <alk03073135@gmail.com>
 * @author Developer Team - emroc GmbH <devteam@emroc.gmbh>
 * @license MIT
 */

namespace OpenWeb3;

use InvalidArgumentException;
use OpenWeb3\Providers\Provider;
use OpenWeb3\Providers\HttpProvider;
use OpenWeb3\RequestManagers\HttpRequestManager;
use OpenWeb3\Contracts\Ethabi;
use OpenWeb3\Contracts\Types\Address;
use OpenWeb3\Contracts\Types\Boolean;
use OpenWeb3\Contracts\Types\Bytes;
use OpenWeb3\Contracts\Types\DynamicBytes;
use OpenWeb3\Contracts\Types\Integer;
use OpenWeb3\Contracts\Types\Str;
use OpenWeb3\Contracts\Types\Uinteger;
use OpenWeb3\Validators\AddressValidator;
use OpenWeb3\Validators\HexValidator;
use OpenWeb3\Validators\StringValidator;
use OpenWeb3\Validators\TagValidator;
use OpenWeb3\Validators\QuantityValidator;
use OpenWeb3\Formatters\AddressFormatter;
use RuntimeException;
use stdClass;


class Contract
{
    protected array $constructor = [];
    protected array $events = [];
    protected array $functions = [];


    protected Eth $eth;
    protected Ethabi $ethabi;
    protected Provider $provider;
    protected array $abi;
    protected string $toAddress;
    protected string $bytecode;
    protected mixed $defaultBlock;

    public function __construct( Provider|string $provider, array|string|stdClass $abi, string $defaultBlock = 'latest')
    {
        if (is_string($provider) && (filter_var($provider, FILTER_VALIDATE_URL) !== false)) {
            // check the uri schema
            if (preg_match('/^https?:\/\//', $provider) === 1) {
                $requestManager = new HttpRequestManager($provider);

                $this->provider = new HttpProvider($requestManager);
            }
        } else if ($provider instanceof Provider) {
            $this->provider = $provider;
        }

        $this->initAbi($abi);

        if (TagValidator::validate($defaultBlock) || QuantityValidator::validate($defaultBlock)) {
            $this->defaultBlock = $defaultBlock;
        } else {
            $this->$defaultBlock = 'latest';
        }
        $this->eth = new Eth($this->provider);
        $this->ethabi = new Ethabi([
            'address' => new Address,
            'bool' => new Boolean,
            'bytes' => new Bytes,
            'dynamicBytes' => new DynamicBytes,
            'int' => new Integer,
            'string' => new Str,
            'uint' => new Uinteger,
        ]);
    }

    public function __get(string $name)
    {
        $method = 'get' . ucfirst($name);

        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], []);
        }
        return false;
    }

    public function __set(string $name, mixed $value)
    {
        $method = 'set' . ucfirst($name);

        if (method_exists($this, $method)) {
            return $this->$method( $value );
        }
        return false;
    }

    public function __isset( string $name ): bool
    {
        return false !== $this->__get($name);
    }

    public function getProvider():Provider
    {
        return $this->provider;
    }

    public function setProvider($provider):self
    {
        if ($provider instanceof Provider) {
            $this->provider = $provider;
        }
        return $this;
    }

    public function getDefaultBlock():string
    {
        return $this->defaultBlock;
    }

    public function setDefaultBlock($defaultBlock):self
    {
        if (TagValidator::validate($defaultBlock) || QuantityValidator::validate($defaultBlock)) {
            $this->defaultBlock = $defaultBlock;
        } else {
            $this->$defaultBlock = 'latest';
        }
        return $this;
    }

    public function getFunctions(): array
    {
        return $this->functions;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function getToAddress(): string
    {
        return $this->toAddress;
    }

    public function getConstructor(): array
    {
        return $this->constructor;
    }

    public function getAbi(): array
    {
        return $this->abi;
    }

    public function setAbi(array|string|stdClass $abi):self
    {
        return $this->initAbi($abi);
    }

    public function getEthabi(): Ethabi
    {
        return $this->ethabi;
    }

    public function getEth(): Eth
    {
        return $this->eth;
    }

    public function setBytecode(string $bytecode):self

    {
        return $this->bytecode($bytecode);
    }

    public function setToAddress(string $address):self

    {
        return $this->at($address);
    }

    public function at(string $address):self
    {
        if (AddressValidator::validate($address) === false) {
            throw new InvalidArgumentException('Please make sure address is valid.');
        }
        $this->toAddress = AddressFormatter::format($address);

        return $this;
    }

    public function bytecode(string $bytecode):self
    {
        if (HexValidator::validate($bytecode) === false) {
            throw new InvalidArgumentException('Please make sure bytecode is valid.');
        }
        $this->bytecode = Utils::stripZero($bytecode);

        return $this;
    }

    public function initAbi(array|string|stdClass $abi):self
    {
        if (StringValidator::validate($abi) === false) {
            throw new InvalidArgumentException('Please make sure abi is valid.');
        }
        $abiArray = [];
        if (is_string($abi)) {
            $abiArray = json_decode($abi, true);

            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new InvalidArgumentException('abi decode error: ' . json_last_error_msg());
            }
        } else {
            $abiArray = Utils::jsonToArray($abi);
        }

        foreach ($abiArray as $item) {
            if (isset($item['type'])) {
                if ($item['type'] === 'function') {
                    $this->functions[] = $item;
                } elseif ($item['type'] === 'constructor') {
                    $this->constructor = $item;
                } elseif ($item['type'] === 'event') {
                    $this->events[$item['name']] = $item;
                }
            }
        }
        $this->abi = $abiArray;

        return $this;
    }

    /**
     * new
     * Deploy a contruct with params.
     *
     * @param mixed
     * @return mixed
     */
    public function new():mixed
    {
        if (isset($this->constructor)) {
            $constructor = $this->constructor;
            $arguments = func_get_args();
            $callback = array_pop($arguments);

            $input_count = isset($constructor['inputs']) ? count($constructor['inputs']) : 0;
            if (count($arguments) < $input_count) {
                throw new InvalidArgumentException('Please make sure you have put all constructor params and callback.');
            }
            if (is_callable($callback) !== true) {
                throw new InvalidArgumentException('The last param must be callback function.');
            }
            if (!isset($this->bytecode)) {
                throw new InvalidArgumentException('Please call bytecode($bytecode) before new().');
            }
            $params = array_splice($arguments, 0, $input_count);
            $data = $this->ethabi->encodeParameters($constructor, $params);
            $transaction = [];

            if (count($arguments) > 0) {
                $transaction = $arguments[0];
            }
            $transaction['data'] = '0x' . $this->bytecode . Utils::stripZero($data);

            $this->eth->sendTransaction($transaction, function ($err, $transaction) use ($callback){
                if ($err !== null) {
                    return call_user_func($callback, $err, null);
                }
                return call_user_func($callback, null, $transaction);
            });
        }
        return null;
    }

    /**
     * send
     * Send function method.
     *
     * @param mixed
     * @return mixed
     */
    public function send():mixed
    {
        if (isset($this->functions)) {
            $arguments = func_get_args();
            $method = array_splice($arguments, 0, 1)[0];
            $callback = array_pop($arguments);

            if (!is_string($method)) {
                throw new InvalidArgumentException('Please make sure the method is string.');
            }

            $functions = [];
            foreach ($this->functions as $function) {
                if ($function["name"] === $method) {
                    $functions[] = $function;
                }
            }
            if (count($functions) < 1) {
                throw new InvalidArgumentException('Please make sure the method exists.');
            }
            if (is_callable($callback) !== true) {
                throw new InvalidArgumentException('The last param must be callback function.');
            }

            // check the last one in arguments is transaction object
            $argsLen = count($arguments);
            $transaction = [];
            $hasTransaction = false;

            if ($argsLen > 0) {
                $transaction = $arguments[$argsLen - 1];
            }
            if (
                isset($transaction["from"]) ||
                isset($transaction["to"]) ||
                isset($transaction["gas"]) ||
                isset($transaction["gasPrice"]) ||
                isset($transaction["value"]) ||
                isset($transaction["data"]) ||
                isset($transaction["nonce"])
            ) {
                $hasTransaction = true;
            } else {
                $transaction = [];
            }

            $params = [];
            $data = "";
            $functionName = "";
            foreach ($functions as $function) {
                if ($hasTransaction) {
                    if ($argsLen - 1 !== count($function['inputs'])) {
                        continue;
                    }

                    $paramsLen = $argsLen - 1;
                } else {
                    if ($argsLen !== count($function['inputs'])) {
                        continue;
                    }

                    $paramsLen = $argsLen;
                }
                try {
                    $params = array_splice($arguments, 0, $paramsLen);
                    $data = $this->ethabi->encodeParameters($function, $params);
                    $functionName = Utils::jsonMethodToString($function);
                } catch (InvalidArgumentException) {
                    continue;
                }
                break;
            }
            if (empty($data) || empty($functionName)) {
                throw new InvalidArgumentException('Please make sure you have put all function params and callback.');
            }
            $functionSignature = $this->ethabi->encodeFunctionSignature($functionName);
            $transaction['to'] = $this->toAddress;
            $transaction['data'] = $functionSignature . Utils::stripZero($data);

            $this->eth->sendTransaction($transaction, function ($err, $transaction) use ($callback){
                if ($err !== null) {
                    return call_user_func($callback, $err, null);
                }
                return call_user_func($callback, null, $transaction);
            });
        }
        return null;
    }

    /**
     * call
     * Call function method.
     *
     * @param mixed
     * @return mixed
     */
    public function call(): mixed
    {
        if (isset($this->functions)) {
            $arguments = func_get_args();
            $method = array_splice($arguments, 0, 1)[0];
            $callback = array_pop($arguments);

            if (!is_string($method)) {
                throw new InvalidArgumentException('Please make sure the method is string.');
            }

            $functions = [];
            foreach ($this->functions as $function) {
                if ($function["name"] === $method) {
                    $functions[] = $function;
                }
            }
            if (count($functions) < 1) {
                throw new InvalidArgumentException('Please make sure the method exists.');
            }
            if (is_callable($callback) !== true) {
                throw new InvalidArgumentException('The last param must be callback function.');
            }

            // check the arguments
            $argsLen = count($arguments);
            $transaction = [];
            $defaultBlock = $this->defaultBlock;
            $params = [];
            $data = "";
            $functionName = "";
            $paramsLen = 0;
            foreach ($functions as $function) {
                try {
                    $paramsLen = count($function['inputs']);
                    $params = array_slice($arguments, 0, $paramsLen);
                    $data = $this->ethabi->encodeParameters($function, $params);
                    $functionName = Utils::jsonMethodToString($function);
                } catch (InvalidArgumentException $e) {
                    continue;
                }
                break;
            }
            if (empty($data) || empty($functionName)) {
                throw new InvalidArgumentException('Please make sure you have put all function params and callback.');
            }
            // remove arguments
            array_splice($arguments, 0, $paramsLen);
            $argsLen -= $paramsLen;

            if ($argsLen > 1) {
                $defaultBlock = $arguments[$argsLen - 1];
                $transaction = $arguments[$argsLen - 2];
            } else if ($argsLen > 0) {
                if (is_array($arguments[$argsLen - 1])) {
                    $transaction = $arguments[$argsLen - 1];
                } else {
                    $defaultBlock = $arguments[$argsLen - 1];
                }
            }
            if (!TagValidator::validate($defaultBlock) && !QuantityValidator::validate($defaultBlock)) {
                $defaultBlock = $this->defaultBlock;
            }
            if (
                !is_array($transaction) &&
                !isset($transaction["from"]) &&
                !isset($transaction["to"]) &&
                !isset($transaction["gas"]) &&
                !isset($transaction["gasPrice"]) &&
                !isset($transaction["value"]) &&
                !isset($transaction["data"]) &&
                !isset($transaction["nonce"])
            ) {
                $transaction = [];
            }
            $functionSignature = $this->ethabi->encodeFunctionSignature($functionName);
            $transaction['to'] = $this->toAddress;
            $transaction['data'] = $functionSignature . Utils::stripZero($data);

            $this->eth->call($transaction, $defaultBlock, function ($err, $transaction) use ($callback, $function){
                if ($err !== null) {
                    return call_user_func($callback, $err, null);
                }
                $decodedTransaction = $this->ethabi->decodeParameters($function, $transaction);

                return call_user_func($callback, null, $decodedTransaction);
            });
        }
        return null;
    }

    /**
     * estimateGas
     * Estimate function gas.
     *
     * @param mixed
     * @return mixed
     */
    public function estimateGas():mixed
    {
        if (isset($this->functions) || isset($this->constructor)) {
            $arguments = func_get_args();
            $callback = array_pop($arguments);

            if (empty($this->toAddress) && !empty($this->bytecode)) {
                $constructor = $this->constructor;

                if (count($arguments) < count($constructor['inputs'])) {
                    throw new InvalidArgumentException('Please make sure you have put all constructor params and callback.');
                }
                if (is_callable($callback) !== true) {
                    throw new InvalidArgumentException('The last param must be callback function.');
                }
                if (!isset($this->bytecode)) {
                    throw new InvalidArgumentException('Please call bytecode($bytecode) before estimateGas().');
                }
                $params = array_splice($arguments, 0, count($constructor['inputs']));
                $data = $this->ethabi->encodeParameters($constructor, $params);
                $transaction = [];

                if (count($arguments) > 0) {
                    $transaction = $arguments[0];
                }
                $transaction['data'] = '0x' . $this->bytecode . Utils::stripZero($data);
            } else {
                $method = array_splice($arguments, 0, 1)[0];

                if (!is_string($method)) {
                    throw new InvalidArgumentException('Please make sure the method is string.');
                }
    
                $functions = [];
                foreach ($this->functions as $function) {
                    if ($function["name"] === $method) {
                        $functions[] = $function;
                    }
                };
                if (count($functions) < 1) {
                    throw new InvalidArgumentException('Please make sure the method exists.');
                }
                if (is_callable($callback) !== true) {
                    throw new InvalidArgumentException('The last param must be callback function.');
                }
    
                // check the last one in arguments is transaction object
                $argsLen = count($arguments);
                $transaction = [];
                $hasTransaction = false;

                if ($argsLen > 0) {
                    $transaction = $arguments[$argsLen - 1];
                }
                if (
                    isset($transaction["from"]) ||
                    isset($transaction["to"]) ||
                    isset($transaction["gas"]) ||
                    isset($transaction["gasPrice"]) ||
                    isset($transaction["value"]) ||
                    isset($transaction["data"]) ||
                    isset($transaction["nonce"])
                ) {
                    $hasTransaction = true;
                } else {
                    $transaction = [];
                }

                $params = [];
                $data = "";
                $functionName = "";
                foreach ($functions as $function) {
                    if ($hasTransaction) {
                        if ($argsLen - 1 !== count($function['inputs'])) {
                            continue;
                        } else {
                            $paramsLen = $argsLen - 1;
                        }
                    } else {
                        if ($argsLen !== count($function['inputs'])) {
                            continue;
                        } else {
                            $paramsLen = $argsLen;
                        }
                    }
                    try {
                        $params = array_splice($arguments, 0, $paramsLen);
                        $data = $this->ethabi->encodeParameters($function, $params);
                        $functionName = Utils::jsonMethodToString($function);
                    } catch (InvalidArgumentException $e) {
                        continue;
                    }
                    break;
                }
                if (empty($data) || empty($functionName)) {
                    throw new InvalidArgumentException('Please make sure you have put all function params and callback.');
                }
                $functionSignature = $this->ethabi->encodeFunctionSignature($functionName);
                $transaction['to'] = $this->toAddress;
                $transaction['data'] = $functionSignature . Utils::stripZero($data);
            }

            $this->eth->estimateGas($transaction, function ($err, $gas) use ($callback) {
                if ($err !== null) {
                    return call_user_func($callback, $err, null);
                }
                return call_user_func($callback, null, $gas);
            });
        }
        return null;
    }

    /**
     * getData
     * Get the contract method's call data.
     * With this function, you can send signed contract method transactions.
     * 1. Get the method data with parameters.
     * 2. Sign the data with user private key.
     * 3. Call sendRawTransaction.
     * 
     * @param mixed
     * @return string
     */
    public function getData():string
    {
        if (isset($this->functions) || isset($this->constructor)) {
            $arguments = func_get_args();
            $functionData = '';

            if (empty($this->toAddress) && !empty($this->bytecode)) {
                $constructor = $this->constructor;

                if (count($arguments) < count($constructor['inputs'])) {
                    throw new InvalidArgumentException('Please make sure you have put all constructor params and callback.');
                }
                if (!isset($this->bytecode)) {
                    throw new InvalidArgumentException('Please call bytecode($bytecode) before getData().');
                }
                $params = array_splice($arguments, 0, count($constructor['inputs']));
                $data = $this->ethabi->encodeParameters($constructor, $params);
                $functionData = $this->bytecode . Utils::stripZero($data);
            } else {
                $method = array_splice($arguments, 0, 1)[0];

                if (!is_string($method)) {
                    throw new InvalidArgumentException('Please make sure the method is string.');
                }
    
                $functions = [];
                foreach ($this->functions as $function) {
                    if ($function["name"] === $method) {
                        $functions[] = $function;
                    }
                }
                if (count($functions) < 1) {
                    throw new InvalidArgumentException('Please make sure the method exists.');
                }
    
                $params = $arguments;
                $data = "";
                $functionName = "";
                foreach ($functions as $function) {
                    if (count($arguments) !== count($function['inputs'])) {
                        continue;
                    }
                    try {
                        $data = $this->ethabi->encodeParameters($function, $params);
                        $functionName = Utils::jsonMethodToString($function);
                    } catch (InvalidArgumentException ) {
                        continue;
                    }
                    break;
                }
                if (empty($data) || empty($functionName)) {
                    throw new InvalidArgumentException('Please make sure you have put all function params and callback.');
                }
                $functionSignature = $this->ethabi->encodeFunctionSignature($functionName);
                $functionData = Utils::stripZero($functionSignature) . Utils::stripZero($data);
            }
            return $functionData;
        }
        return '';
    }

    /**
     * getEventLogs
     *
     * @param string $eventName
     * @param string|int $fromBlock
     * @param string|int $toBlock
     * @return array
     */
    public function getEventLogs(string $eventName, $fromBlock = 'latest', $toBlock = 'latest')
    {
        //try to ensure block numbers are valid together
        if ($fromBlock !== 'latest') {
            if (!is_int($fromBlock) || $fromBlock < 1) {
                throw new InvalidArgumentException('Please make sure fromBlock is a valid block number');
            } else if ($toBlock !== 'latest' && $fromBlock > $toBlock) {
                throw new InvalidArgumentException('Please make sure fromBlock is equal or less than toBlock');
            }
        }

        if ($toBlock !== 'latest') {
            if (!is_int($toBlock) || $toBlock < 1) {
                throw new InvalidArgumentException('Please make sure toBlock is a valid block number');
            } else if ($fromBlock === 'latest') {
                throw new InvalidArgumentException('Please make sure toBlock is equal or greater than fromBlock');
            }
        }

        $eventLogData = [];

        //ensure the event actually exists before trying to filter for it
        if (!array_key_exists($eventName, $this->events)) {
            throw new InvalidArgumentException("'{$eventName}' does not exist in the ABI for this contract");
        }

        //indexed and non-indexed event parameters must be treated separately
        //indexed parameters are stored in the 'topics' array
        //non-indexed parameters are stored in the 'data' value
        $eventParameterNames = [];
        $eventParameterTypes = [];
        $eventIndexedParameterNames = [];
        $eventIndexedParameterTypes = [];

        foreach ($this->events[$eventName]['inputs'] as $input) {
            if ($input['indexed']) {
                $eventIndexedParameterNames[] = $input['name'];
                $eventIndexedParameterTypes[] = $input['type'];
            } else {
                $eventParameterNames[] = $input['name'];
                $eventParameterTypes[] = $input['type'];
            }
        }

        $numEventIndexedParameterNames = count($eventIndexedParameterNames);

        //filter through log data to find any logs which match this event (topic) from
        //this contract, between these specified blocks (defaulting to the latest block only)
        $this->eth->getLogs([
            'fromBlock' => (is_int($fromBlock)) ? '0x' . dechex($fromBlock) : $fromBlock,
            'toBlock' => (is_int($toBlock)) ? '0x' . dechex($toBlock) : $toBlock,
            'topics' => [$this->ethabi->encodeEventSignature($this->events[$eventName])],
            'address' => $this->toAddress
        ],
            function ($error, $result) use (&$eventLogData, $eventParameterTypes, $eventParameterNames, $eventIndexedParameterTypes, $eventIndexedParameterNames,$numEventIndexedParameterNames) {
                if ($error !== null) {
                    throw new RuntimeException($error->getMessage());
                }

                foreach ($result as $object) {
                    //decode the data from the log into the expected formats, with its corresponding named key
                    $decodedData = array_combine($eventParameterNames, $this->ethabi->decodeParameters($eventParameterTypes, $object->data));

                    //decode the indexed parameter data
                    for ($i = 0; $i < $numEventIndexedParameterNames; $i++) {
                        //topics[0] is the event signature, so we start from $i + 1 for the indexed parameter data
                        $decodedData[$eventIndexedParameterNames[$i]] = $this->ethabi->decodeParameters([$eventIndexedParameterTypes[$i]], $object->topics[$i + 1])[0];
                    }

                    //include block metadata for context, along with event data
                    $eventLogData[] = [
                        'transactionHash' => $object->transactionHash,
                        'blockHash' => $object->blockHash,
                        'blockNumber' => hexdec($object->blockNumber),
                        'data' => $decodedData
                    ];
                }
            });

        return $eventLogData;
    }
}
