<?php

class helper_plugin_captcha_public extends helper_plugin_captcha {

    public function get_field_in() {
        return $this->field_in;
    }
    public function get_field_sec() {
        return $this->field_sec;
    }
    public function get_field_hp() {
        return $this->field_hp;
    }
}

/**
 * @group plugin_captcha
 * @group plugins
 */
class helper_plugin_captcha_test extends DokuWikiTest {

    protected $pluginsEnabled = array('captcha');

    public function testConfig() {
        global $conf;
        $conf['plugin']['captcha']['lettercount'] = 20;

        $helper = new helper_plugin_captcha_public();

        // generateCAPTCHA generates a maximum of 16 chars
        $code = $helper->_generateCAPTCHA("fixed", 0);
        $this->assertEquals(16, strlen($code));
    }

    public function testDecrypt() {

        $helper = new helper_plugin_captcha_public();

        $rand = "12345";
        $secret = $helper->encrypt($rand);
        $this->assertNotSame(false, $secret);
        $this->assertSame($rand, $helper->decrypt($secret));

        $this->assertFalse($helper->decrypt(''));
        $this->assertFalse($helper->decrypt('X'));
    }

    public function testCheck() {

        global $INPUT, $ID;

        $helper = new helper_plugin_captcha_public();

        $INPUT->set($helper->get_field_hp(), '');
        $INPUT->set($helper->get_field_in(), 'X');
        $INPUT->set($helper->get_field_sec(), '');

        $this->assertFalse($helper->check(false));
        $INPUT->set($helper->get_field_sec(), 'X');
        $this->assertFalse($helper->check(false));

        $rand = 0;
        $code = $helper->_generateCAPTCHA($helper->_fixedIdent(), $rand);
        $INPUT->set($helper->get_field_in(), $code);
        $this->assertFalse($helper->check(false));
        $INPUT->set($helper->get_field_sec(), $helper->encrypt($rand));
        $this->assertTrue($helper->check(false));
        $ID = 'test:fail';
        $this->assertFalse($helper->check(false));
    }

    public function testGenerate() {

        $helper = new helper_plugin_captcha_public();

        $rand = 0;
        $code = $helper->_generateCAPTCHA($helper->_fixedIdent(), $rand);
        $newcode = $helper->_generateCAPTCHA($helper->_fixedIdent().'X', $rand);
        $this->assertNotEquals($newcode, $code);
        $newcode = $helper->_generateCAPTCHA($helper->_fixedIdent(), $rand+0.1);
        $this->assertNotEquals($newcode, $code);
    }

}
