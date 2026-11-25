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

    /**
     * Failed API logins are counted
     */
    public function testApiLoginFailureCounted()
    {
        $this->assertFalse((new ApiCore())->login('testuser', 'wrong'));
        $this->assertEquals(1, $this->counter->get());
    }

    /**
     * Successful API logins reset the counter
     */
    public function testApiLoginSuccessResets()
    {
        global $conf;
        $conf['plugin']['captcha']['logindenial'] = 0;
        $this->counter->increment();

        $this->assertTrue((new ApiCore())->login('testuser', 'testpass'));
        $this->assertEquals(0, $this->counter->get());
    }

    /**
     * Logins refused while the IP is blocked are not counted
     */
    public function testBlockedLoginNotCounted()
    {
        $this->counter->increment();
        $this->counter->increment();

        $this->assertFalse((new ApiCore())->login('testuser', 'testpass'));
        clearstatcache();
        $this->assertEquals(2, $this->counter->get());

        $this->formLogin('testuser', 'testpass');
        clearstatcache();
        $this->assertEquals(2, $this->counter->get());
    }

    /**
     * Failed logins through the login form are counted
     */
    public function testFormLoginFailureCounted()
    {
        global $conf;
        $conf['plugin']['captcha']['loginprotect'] = 0;

        $this->formLogin('testuser', 'wrong');
        clearstatcache();
        $this->assertEquals(1, $this->counter->get());
    }

    /**
     * Run the login part of a request with credentials from the login form
     *
     * @param string $user
     * @param string $pass
     * @return void
     */
    protected function formLogin($user, $pass)
    {
        global $INPUT;
        $INPUT->set('u', $user);
        $INPUT->set('p', $pass);
        auth_setup();
    }
}
