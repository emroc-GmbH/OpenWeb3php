<?php

namespace Test\Unit;

use Test\TestCase;
use OpenWeb3\Validators\BooleanValidator;

class BooleanValidatorTest extends TestCase
{
    /**
     * validator
     * 
     * @var \OpenWeb3\Validators\BooleanValidator
     */
    protected $validator;

    /**
     * setUp
     * 
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->validator = new BooleanValidator;
    }

    /**
     * testValidate
     * 
     * @return void
     */
    public function testValidate()
    {
        $validator = $this->validator;

        $this->assertEquals(false, $validator->validate('0XCA35B7D915458EF540ADE6068DFE2F44E8FA733C'));
        $this->assertEquals(false, $validator->validate(0xCA35B7D915458EF540ADE6068DFE2F44E8FA733C));
        $this->assertEquals(true, $validator->validate(true));
    }
}
