<?php

/**
 * This file is part of web3.php package.
 * 
 * (c) Kuan-Cheng,Lai <alk03073135@gmail.com>
 * 
 * @author Peter Lai <alk03073135@gmail.com>
 * @license MIT
 */

namespace OpenWeb3\Methods\Eth;

use InvalidArgumentException;
use OpenWeb3\Methods\EthMethod;
use OpenWeb3\Validators\TagValidator;
use OpenWeb3\Validators\QuantityValidator;
use OpenWeb3\Validators\AddressValidator;
use OpenWeb3\Formatters\AddressFormatter;
use OpenWeb3\Formatters\QuantityFormatter;
use OpenWeb3\Formatters\OptionalQuantityFormatter;

class GetStorageAt extends EthMethod
{
    /**
     * validators
     * 
     * @var array
     */
    protected $validators = [
        AddressValidator::class, QuantityValidator::class, [
            TagValidator::class, QuantityValidator::class
        ]
    ];

    /**
     * inputFormatters
     * 
     * @var array
     */
    protected $inputFormatters = [
        AddressFormatter::class, QuantityFormatter::class, OptionalQuantityFormatter::class
    ];

    /**
     * outputFormatters
     * 
     * @var array
     */
    protected $outputFormatters = [];

    /**
     * defaultValues
     * 
     * @var array
     */
    protected $defaultValues = [
        2 => 'latest'
    ];

    /**
     * construct
     * 
     * @param string $method
     * @param array $arguments
     * @return void
     */
    // public function __construct($method='', $arguments=[])
    // {
    //     parent::__construct($method, $arguments);
    // }
}
