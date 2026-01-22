<?php

namespace dokuwiki\plugin\captcha;

/**
 * A simple mechanism to count login failures for IP addresses
 */
class IpCounter
{
    protected $ip;
    protected $store;

    /**
     * Initialize the counter
     */
    public function __construct()
    {
        $this->ip = clientIP(true);
        $this->store = getCacheName($this->ip, '.captchaip');
    }

    /**
     * Increases the counter by adding a byte
     *
     * @return void
     */
    public function increment()
    {
        io_saveFile($this->store, '1', true);
    }

    /**
     * Return the current counter
     *
     * @return int
     */
    public function get()
    {
        return (int)@filesize($this->store);
    }

    /**
     * Reset the counter to zero
     *
     * @return void
     */
    public function reset()
    {
        @unlink($this->store);
    }

    /**
     * Get timestamp of last failed attempt
     *
     * @return int Unix timestamp, 0 if no attempts
     */
    public function getLastAttempt()
    {
        return (int)@filemtime($this->store);
    }

    /**
     * Calculate required timeout in seconds based on failure count
     *
     * First failed attempt is okay, second requires $base seconds wait.
     *
     * @param int $base Base delay in seconds
     * @param int $max Maximum delay in seconds
     * @return int Timeout in seconds (0 if no failures)
     */
    public function calculateTimeout($base = 5, $max = 3600)
    {
        $count = $this->get();
        if ($count < 1) return 0;
        $timeout = $base * pow(2, $count - 1); // -1 because first failure is free
        return (int)min($timeout, $max);
    }

    /**
     * Get remaining wait time in seconds
     *
     * @param int $base Base delay in seconds
     * @param int $max Maximum delay in seconds
     * @return int Seconds remaining (0 if no wait needed)
     */
    public function getRemainingTime($base = 5, $max = 3600)
    {
        $timeout = $this->calculateTimeout($base, $max);
        if ($timeout === 0) return 0;
        $elapsed = time() - $this->getLastAttempt();
        return max(0, $timeout - $elapsed);
    }
}
