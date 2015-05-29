<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');


class helper_plugin_captcha extends DokuWiki_Plugin {

    protected $field_in = 'plugin__captcha';
    protected $field_sec = 'plugin__captcha_secret';
    protected $field_hp = 'plugin__captcha_honeypot';

    /**
     * Constructor. Initializes field names
     */
    public function __construct() {
        $this->field_in  = md5($this->_fixedIdent().$this->field_in);
        $this->field_sec = md5($this->_fixedIdent().$this->field_sec);
        $this->field_hp  = md5($this->_fixedIdent().$this->field_hp);
    }

    /**
     * Check if the CAPTCHA should be used. Always check this before using the methods below.
     *
     * @return bool true when the CAPTCHA should be used
     */
    public function isEnabled() {
        if(!$this->getConf('forusers') && $_SERVER['REMOTE_USER']) return false;
        return true;
    }

    /**
     * Returns the HTML to display the CAPTCHA with the chosen method
     */
    public function getHTML() {
        global $ID;

        $rand = (float) (rand(0, 10000)) / 10000;
        if($this->getConf('mode') == 'math') {
            $code = $this->_generateMATH($this->_fixedIdent(), $rand);
            $code = $code[0];
            $text = $this->getLang('fillmath');
        } elseif($this->getConf('mode') == 'question') {
            $text = $this->getConf('question');
        } else {
            $code = $this->_generateCAPTCHA($this->_fixedIdent(), $rand);
            $text = $this->getLang('fillcaptcha');
        }
        $secret = $this->encrypt($rand);

        $txtlen = $this->getConf('lettercount');

        $out = '';
        $out .= '<div id="plugin__captcha_wrapper">';
        $out .= '<input type="hidden" name="'.$this->field_sec.'" value="'.hsc($secret).'" />';
        $out .= '<label for="plugin__captcha">'.$text.'</label> ';

        switch($this->getConf('mode')) {
            case 'math':
            case 'text':
                $out .= $this->_obfuscateText($code);
                break;
            case 'js':
                $out .= '<span id="plugin__captcha_code">'.$this->_obfuscateText($code).'</span>';
                break;
            case 'image':
                $out .= '<img src="'.DOKU_BASE.'lib/plugins/captcha/img.php?secret='.rawurlencode($secret).'&amp;id='.$ID.'" '.
                    ' width="'.$this->getConf('width').'" height="'.$this->getConf('height').'" alt="" /> ';
                break;
            case 'audio':
                $out .= '<img src="'.DOKU_BASE.'lib/plugins/captcha/img.php?secret='.rawurlencode($secret).'&amp;id='.$ID.'" '.
                    ' width="'.$this->getConf('width').'" height="'.$this->getConf('height').'" alt="" /> ';
                $out .= '<a href="'.DOKU_BASE.'lib/plugins/captcha/wav.php?secret='.rawurlencode($secret).'&amp;id='.$ID.'"'.
                    ' class="JSnocheck" title="'.$this->getLang('soundlink').'">';
                $out .= '<img src="'.DOKU_BASE.'lib/plugins/captcha/sound.png" width="16" height="16"'.
                    ' alt="'.$this->getLang('soundlink').'" /></a>';
                break;
            case 'figlet':
                require_once(dirname(__FILE__).'/figlet.php');
                $figlet = new phpFiglet();
                if($figlet->loadfont(dirname(__FILE__).'/figlet.flf')) {
                    $out .= '<pre>';
                    $out .= rtrim($figlet->fetch($code));
                    $out .= '</pre>';
                } else {
                    msg('Failed to load figlet.flf font file. CAPTCHA broken', -1);
                }
                break;
        }
        $out .= ' <input type="text" size="'.$txtlen.'" name="'.$this->field_in.'" class="edit" /> ';

        // add honeypot field
        $out .= '<label class="no">'.$this->getLang('honeypot').'<input type="text" name="'.$this->field_hp.'" /></label>';
        $out .= '</div>';
        return $out;
    }

    /**
     * Checks if the the CAPTCHA was solved correctly
     *
     * @param  bool $msg when true, an error will be signalled through the msg() method
     * @return bool true when the answer was correct, otherwise false
     */
    public function check($msg = true) {
        global $INPUT;

        $code = '';
        $field_sec = $INPUT->str($this->field_sec);
        $field_in  = $INPUT->str($this->field_in);
        $field_hp  = $INPUT->str($this->field_hp);

        // reconstruct captcha from provided $field_sec
        $rand = $this->decrypt($field_sec);

        if($this->getConf('mode') == 'math') {
            $code = $this->_generateMATH($this->_fixedIdent(), $rand);
            $code = $code[1];
        } elseif($this->getConf('mode') == 'question') {
            $code = $this->getConf('answer');
        } else {
            $code = $this->_generateCAPTCHA($this->_fixedIdent(), $rand);
        }

        // compare values
        if(!$field_sec ||
            !$field_in ||
            $rand === false ||
            utf8_strtolower($field_in) != utf8_strtolower($code) ||
            trim($field_hp) !== ''
        ) {
            if($msg) msg($this->getLang('testfailed'), -1);
            return false;
        }
        return true;
    }

    /**
     * Build a semi-secret fixed string identifying the current page and user
     *
     * This string is always the same for the current user when editing the same
     * page revision, but only for one day. Editing a page before midnight and saving
     * after midnight will result in a failed CAPTCHA once, but makes sure it can
     * not be reused which is especially important for the registration form where the
     * $ID usually won't change.
     *
     * @return string
     */
    public function _fixedIdent() {
        global $ID;
        $lm = @filemtime(wikiFN($ID));
        $td = date('Y-m-d');
        return auth_browseruid().
        auth_cookiesalt().
        $ID.$lm.$td;
    }

    /**
     * Adds random space characters within the given text
     *
     * Keeps subsequent numbers without spaces (for math problem)
     *
     * @param $text
     * @return string
     */
    protected function _obfuscateText($text) {
        $new = '';

        $spaces = array(
            "\r",
            "\n",
            "\r\n",
            ' ',
            "\xC2\xA0", // \u00A0    NO-BREAK SPACE
            "\xE2\x80\x80", // \u2000    EN QUAD
            "\xE2\x80\x81", // \u2001    EM QUAD
            "\xE2\x80\x82", // \u2002    EN SPACE
            //         "\xE2\x80\x83", // \u2003    EM SPACE
            "\xE2\x80\x84", // \u2004    THREE-PER-EM SPACE
            "\xE2\x80\x85", // \u2005    FOUR-PER-EM SPACE
            "\xE2\x80\x86", // \u2006    SIX-PER-EM SPACE
            "\xE2\x80\x87", // \u2007    FIGURE SPACE
            "\xE2\x80\x88", // \u2008    PUNCTUATION SPACE
            "\xE2\x80\x89", // \u2009    THIN SPACE
            "\xE2\x80\x8A", // \u200A    HAIR SPACE
            "\xE2\x80\xAF", // \u202F    NARROW NO-BREAK SPACE
            "\xE2\x81\x9F", // \u205F    MEDIUM MATHEMATICAL SPACE

            "\xE1\xA0\x8E\r\n", // \u180E    MONGOLIAN VOWEL SEPARATOR
            "\xE2\x80\x8B\r\n", // \u200B    ZERO WIDTH SPACE
            "\xEF\xBB\xBF\r\n", // \uFEFF    ZERO WIDTH NO-BREAK SPACE
        );

        $len = strlen($text);
        for($i = 0; $i < $len - 1; $i++) {
            $new .= $text{$i};

            if(!is_numeric($text{$i + 1})) {
                $new .= $spaces[array_rand($spaces)];
            }
        }
        $new .= $text{$len - 1};
        return $new;
    }

    /**
     * Generate some numbers from a known string and random number
     *
     * @param $fixed string the fixed part, any string
     * @param $rand  float  some random number between 0 and 1
     * @return string
     */
    protected function _generateNumbers($fixed, $rand) {
        $fixed   = hexdec(substr(md5($fixed), 5, 5)); // use part of the md5 to generate an int
        $rand = $rand * 0xFFFFF; // bitmask from the random number
        return md5($rand ^ $fixed); // combine both values
    }

    /**
     * Generates a random char string
     *
     * @param $fixed string the fixed part, any string
     * @param $rand  float  some random number between 0 and 1
     * @return string
     */
    public function _generateCAPTCHA($fixed, $rand) {
        $numbers = $this->_generateNumbers($fixed, $rand);

        // now create the letters
        $code = '';
        $lettercount = $this->getConf('lettercount') * 2;
        if($lettercount > strlen($numbers)) $lettercount = strlen($numbers);
        for($i = 0; $i < $lettercount; $i += 2) {
            $code .= chr(floor(hexdec($numbers[$i].$numbers[$i + 1]) / 10) + 65);
        }

        return $code;
    }

    /**
     * Create a mathematical task and its result
     *
     * @param $fixed string the fixed part, any string
     * @param $rand  float  some random number between 0 and 1
     * @return array taks, result
     */
    protected function _generateMATH($fixed, $rand) {
        $numbers = $this->_generateNumbers($fixed, $rand);

        // first letter is the operator (+/-)
        $op  = (hexdec($numbers[0]) > 8) ? -1 : 1;
        $num = array(hexdec($numbers[1].$numbers[2]), hexdec($numbers[3]));

        // we only want positive results
        if(($op < 0) && ($num[0] < $num[1])) rsort($num);

        // prepare result and task text
        $res  = $num[0] + ($num[1] * $op);
        $task = $num[0].(($op < 0) ? '-' : '+').$num[1].'=?';

        return array($task, $res);
    }

    /**
     * Create a CAPTCHA image
     *
     * @param string $text the letters to display
     */
    public function _imageCAPTCHA($text) {
        $w = $this->getConf('width');
        $h = $this->getConf('height');

        $fonts = glob(dirname(__FILE__).'/fonts/*.ttf');

        // create a white image
        $img   = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);

        // add some lines as background noise
        for($i = 0; $i < 30; $i++) {
            $color = imagecolorallocate($img, rand(100, 250), rand(100, 250), rand(100, 250));
            imageline($img, rand(0, $w), rand(0, $h), rand(0, $w), rand(0, $h), $color);
        }

        // draw the letters
        $txtlen = strlen($text);
        for($i = 0; $i < $txtlen; $i++) {
            $font  = $fonts[array_rand($fonts)];
            $color = imagecolorallocate($img, rand(0, 100), rand(0, 100), rand(0, 100));
            $size  = rand(floor($h / 1.8), floor($h * 0.7));
            $angle = rand(-35, 35);

            $x       = ($w * 0.05) + $i * floor($w * 0.9 / $txtlen);
            $cheight = $size + ($size * 0.5);
            $y       = floor($h / 2 + $cheight / 3.8);

            imagettftext($img, $size, $angle, $x, $y, $color, $font, $text[$i]);
        }

        header("Content-type: image/png");
        imagepng($img);
        imagedestroy($img);
    }

    /**
     * Encrypt the given string with the cookie salt
     *
     * @param string $data
     * @return string
     */
    public function encrypt($data) {
        if(function_exists('auth_encrypt')) {
            $data = auth_encrypt($data, auth_cookiesalt()); // since binky
        } else {
            $data = PMA_blowfish_encrypt($data, auth_cookiesalt()); // deprecated
        }

        return base64_encode($data);
    }

    /**
     * Decrypt the given string with the cookie salt
     *
     * @param string $data
     * @return string
     */
    public function decrypt($data) {
        $data = base64_decode($data);
        if($data === false || $data === '') return false;

        if(function_exists('auth_decrypt')) {
            return auth_decrypt($data, auth_cookiesalt()); // since binky
        } else {
            return PMA_blowfish_decrypt($data, auth_cookiesalt()); // deprecated
        }
    }
}
