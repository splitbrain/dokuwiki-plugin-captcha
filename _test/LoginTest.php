<?php

namespace dokuwiki\plugin\captcha\test;

use dokuwiki\plugin\captcha\IpCounter;
use dokuwiki\Remote\ApiCore;
use DokuWikiTest;

/**
 * Tests for the login protection of the action component
 *
 * @group plugin_captcha
 * @group plugins
 */
class LoginTest extends DokuWikiTest
{
    protected $pluginsEnabled = ['captcha'];

    /** @var IpCounter */
    protected $counter;

    public function setUp(): void
    {
        parent::setUp();
        global $conf;
        $conf['plugin']['captcha']['loginprotect'] = 1;
        $conf['plugin']['captcha']['logindenial'] = 5;
        $this->counter = new IpCounter();
        $this->counter->reset();
    }

    public function tearDown(): void
    {
        $this->counter->reset();
        parent::tearDown();
    }

    /**
     * API logins do not need a CAPTCHA
     */
    public function testApiLoginWithoutCaptcha()
    {
        $this->assertTrue((new ApiCore())->login('testuser', 'testpass'));
    }

    /**
     * API logins are refused while the IP is blocked
     */
    public function testApiLoginBlocked()
    {
        $this->counter->increment();
        $this->assertFalse((new ApiCore())->login('testuser', 'testpass'));
    }
}
