<?php

namespace dokuwiki\plugin\captcha\test;

use dokuwiki\plugin\captcha\IpCounter;
use DokuWikiTest;

/**
 * Tests for the IpCounter class
 *
 * @group plugin_captcha
 * @group plugins
 */
class IpCounterTest extends DokuWikiTest
{
    protected $pluginsEnabled = ['captcha'];

    /** @var IpCounter */
    protected $counter;

    public function setUp(): void
    {
        parent::setUp();
        global $conf;
        $conf['plugin']['captcha']['logindenial'] = 5;
        $conf['plugin']['captcha']['logindenial_max'] = 3600;
        $this->counter = new IpCounter();
        $this->counter->reset();
    }

    public function tearDown(): void
    {
        $this->counter->reset();
        parent::tearDown();
    }

    public function testInitialState()
    {
        $this->assertEquals(0, $this->counter->get());
        $this->assertEquals(0, $this->counter->getLastAttempt());
    }

    public function testIncrement()
    {
        $this->assertEquals(0, $this->counter->get());

        $this->counter->increment();
        $this->assertEquals(1, $this->counter->get());

        $this->counter->increment();
        $this->assertEquals(2, $this->counter->get());

        $this->counter->increment();
        $this->assertEquals(3, $this->counter->get());
    }

    public function testReset()
    {
        $this->counter->increment();
        $this->counter->increment();
        $this->assertEquals(2, $this->counter->get());

        $this->counter->reset();
        $this->assertEquals(0, $this->counter->get());
    }

    public function testGetLastAttempt()
    {
        $this->assertEquals(0, $this->counter->getLastAttempt());

        $before = time();
        $this->counter->increment();
        $after = time();

        $lastAttempt = $this->counter->getLastAttempt();
        $this->assertGreaterThanOrEqual($before, $lastAttempt);
        $this->assertLessThanOrEqual($after, $lastAttempt);
    }

    public function testCalculateTimeoutNoFailures()
    {
        $this->assertEquals(0, $this->counter->calculateTimeout());
    }

    public function testCalculateTimeoutDisabled()
    {
        global $conf;
        $conf['plugin']['captcha']['logindenial'] = 0;
        $counter = new IpCounter();

        $counter->increment();
        $this->assertEquals(0, $counter->calculateTimeout());

        $counter->reset();
    }

    public function testCalculateTimeoutExponentialGrowth()
    {
        // First failure: base * 2^0 = 5
        $this->counter->increment();
        $this->assertEquals(5, $this->counter->calculateTimeout());

        // Second failure: base * 2^1 = 10
        $this->counter->increment();
        $this->assertEquals(10, $this->counter->calculateTimeout());

        // Third failure: base * 2^2 = 20
        $this->counter->increment();
        $this->assertEquals(20, $this->counter->calculateTimeout());

        // Fourth failure: base * 2^3 = 40
        $this->counter->increment();
        $this->assertEquals(40, $this->counter->calculateTimeout());

        // Fifth failure: base * 2^4 = 80
        $this->counter->increment();
        $this->assertEquals(80, $this->counter->calculateTimeout());
    }

    public function testCalculateTimeoutMaxCap()
    {
        // Add many failures to exceed the max
        for ($i = 0; $i < 20; $i++) {
            $this->counter->increment();
        }

        // Should be capped at max (3600)
        $this->assertEquals(3600, $this->counter->calculateTimeout());
    }

    public function testCalculateTimeoutMaxCapLower()
    {
        global $conf;
        $conf['plugin']['captcha']['logindenial_max'] = 100;
        $counter = new IpCounter();

        // Add many failures to exceed the max
        for ($i = 0; $i < 20; $i++) {
            $counter->increment();
        }

        // Should be capped at max (100)
        $this->assertEquals(100, $counter->calculateTimeout());

        $counter->reset();
    }

    public function testCalculateTimeoutDifferentBase()
    {
        global $conf;
        $conf['plugin']['captcha']['logindenial'] = 10;
        $counter = new IpCounter();

        $counter->increment();
        $this->assertEquals(10, $counter->calculateTimeout());

        $counter->increment();
        $this->assertEquals(20, $counter->calculateTimeout());

        $counter->increment();
        $this->assertEquals(40, $counter->calculateTimeout());

        $counter->reset();
    }

    public function testGetRemainingTimeNoFailures()
    {
        $this->assertEquals(0, $this->counter->getRemainingTime());
    }

    public function testGetRemainingTimeImmediatelyAfterFailure()
    {
        $this->counter->increment();

        $remaining = $this->counter->getRemainingTime();

        // Immediately after increment, remaining should be close to full timeout (5s)
        // Allow 1 second tolerance for test execution time
        $this->assertGreaterThanOrEqual(4, $remaining);
        $this->assertLessThanOrEqual(5, $remaining);
    }

    public function testGetRemainingTimeAfterTimeoutExpires()
    {
        $this->counter->increment();

        // Manipulate the file's mtime to simulate time passing
        $store = $this->getInaccessibleProperty($this->counter, 'store');
        touch($store, time() - 10); // 10 seconds ago

        // Timeout is 5 seconds, 10 seconds have passed, so remaining should be 0
        $this->assertEquals(0, $this->counter->getRemainingTime());
    }

    public function testGetRemainingTimePartiallyElapsed()
    {
        $this->counter->increment();
        $this->counter->increment(); // timeout = 10 seconds

        // Manipulate the file's mtime to simulate 3 seconds passing
        $store = $this->getInaccessibleProperty($this->counter, 'store');
        touch($store, time() - 3);

        $remaining = $this->counter->getRemainingTime();

        // 10 second timeout, 3 seconds elapsed, ~7 seconds remaining
        $this->assertGreaterThanOrEqual(6, $remaining);
        $this->assertLessThanOrEqual(7, $remaining);
    }
}
