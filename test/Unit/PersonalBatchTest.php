<?php

namespace Test\Unit;

use RuntimeException;
use Test\TestCase;

class PersonalBatchTest extends TestCase
{
    /**
     * personal
     * 
     * @var Web3\Personal
     */
    protected $personal;
    protected $testHash;

    /**
     * setUp
     * 
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->personal = $this->web3->personal;
    }

    /**
     * testBatch
     * 
     * @return void
     */
    public function testBatch()
    {
        $personal = $this->personal;

        $personal->batch(true);
        $personal->listAccounts();
        $personal->newAccount('123456');

        $personal->provider->execute(function ($err, $data) {
            if ($err !== null) {
                $this->assertNotNull( $err );
                return;
            }
            $this->assertTrue(is_array($data[0]));
            $this->assertTrue(is_string($data[1]));
        });
    }

    /**
     * testWrongParam
     * 
     * @return void
     */
    public function testWrongParam()
    {
        $this->expectException(RuntimeException::class);

        $personal = $this->personal;

        $personal->batch(true);
        $personal->listAccounts();
        $personal->newAccount($personal);

        $personal->provider->execute(function ($err, $data) {
            if ($err !== null) {
                $this->fail($err->getMessage());
                return;
            }
            $this->assertTrue(is_string($data[0]));
            $this->assertEquals($data[1], $this->testHash);
        });
    }
}
