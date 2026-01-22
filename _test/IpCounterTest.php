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
        $this->assertEquals(0, $this->counter->calculateTimeout(5, 3600));
    }

    public function testCalculateTimeoutExponentialGrowth()
    {
        // First failure: base * 2^0 = 5
        $this->counter->increment();
        $this->assertEquals(5, $this->counter->calculateTimeout(5, 3600));

        // Second failure: base * 2^1 = 10
        $this->counter->increment();
        $this->assertEquals(10, $this->counter->calculateTimeout(5, 3600));

        // Third failure: base * 2^2 = 20
        $this->counter->increment();
        $this->assertEquals(20, $this->counter->calculateTimeout(5, 3600));

        // Fourth failure: base * 2^3 = 40
        $this->counter->increment();
        $this->assertEquals(40, $this->counter->calculateTimeout(5, 3600));

        // Fifth failure: base * 2^4 = 80
        $this->counter->increment();
        $this->assertEquals(80, $this->counter->calculateTimeout(5, 3600));
    }

    public function testCalculateTimeoutMaxCap()
    {
        // Add many failures to exceed the max
        for ($i = 0; $i < 20; $i++) {
            $this->counter->increment();
        }

        // Should be capped at max
        $this->assertEquals(3600, $this->counter->calculateTimeout(5, 3600));
        $this->assertEquals(100, $this->counter->calculateTimeout(5, 100));
    }

    public function testCalculateTimeoutDifferentBase()
    {
        $this->counter->increment();
        $this->assertEquals(10, $this->counter->calculateTimeout(10, 3600));

        $this->counter->increment();
        $this->assertEquals(20, $this->counter->calculateTimeout(10, 3600));

        $this->counter->increment();
        $this->assertEquals(40, $this->counter->calculateTimeout(10, 3600));
    }

    public function testGetRemainingTimeNoFailures()
    {
        $this->assertEquals(0, $this->counter->getRemainingTime(5, 3600));
    }

    public function testGetRemainingTimeImmediatelyAfterFailure()
    {
        $this->counter->increment();

        $remaining = $this->counter->getRemainingTime(5, 3600);

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
        $this->assertEquals(0, $this->counter->getRemainingTime(5, 3600));
    }

    public function testGetRemainingTimePartiallyElapsed()
    {
        $this->counter->increment();
        $this->counter->increment(); // timeout = 10 seconds

        // Manipulate the file's mtime to simulate 3 seconds passing
        $store = $this->getInaccessibleProperty($this->counter, 'store');
        touch($store, time() - 3);

        $remaining = $this->counter->getRemainingTime(5, 3600);

        // 10 second timeout, 3 seconds elapsed, ~7 seconds remaining
        $this->assertGreaterThanOrEqual(6, $remaining);
        $this->assertLessThanOrEqual(7, $remaining);
    }
}
