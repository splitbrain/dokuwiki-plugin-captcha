<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_INC.'inc/blowfish.php');

class helper_plugin_captcha extends DokuWiki_Plugin {

    protected $field_in  = 'plugin__captcha';
    protected $field_sec = 'plugin__captcha_secret';
    protected $field_hp  = 'plugin__captcha_honeypot';

    /**
     * Constructor. Initializes field names
     */
    function __construct(){
        $this->field_in  = md5($this->_fixedIdent() . $this->field_in);
        $this->field_sec = md5($this->_fixedIdent() . $this->field_sec);
        $this->field_hp  = md5($this->_fixedIdent() . $this->field_hp);
    }


    /**
     * Check if the CAPTCHA should be used. Always check this before using the methods below.
     *
     * @return bool true when the CAPTCHA should be used
     */
    function isEnabled(){
        if(!$this->getConf('forusers') && $_SERVER['REMOTE_USER']) return false;
        return true;
    }

    /**
     * Returns the HTML to display the CAPTCHA with the chosen method
     */
    function getHTML(){
        global $ID;

        $rand = (float) (rand(0,10000))/10000;
        if($this->getConf('mode') == 'math'){
            $code = $this->_generateMATH($this->_fixedIdent(),$rand);
            $code = $code[0];
            $text = $this->getLang('fillmath');
        } else {
            $code = $this->_generateCAPTCHA($this->_fixedIdent(),$rand);
            $text = $this->getLang('fillcaptcha');
        }
        $secret = PMA_blowfish_encrypt($rand,auth_cookiesalt());

        $out  = '';
        $out .= '<div id="plugin__captcha_wrapper">';
        $out .= '<input type="hidden" name="'.$this->field_sec.'" value="'.hsc($secret).'" />';
        $out .= '<label for="plugin__captcha">'.$text.'</label> ';

        switch($this->getConf('mode')){
            case 'text':
            case 'math':
                $out .= $code;
                break;
            case 'js':
                $out .= '<span id="plugin__captcha_code">'.$code.'</span>';
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
                if($figlet->loadfont(dirname(__FILE__).'/figlet.flf')){
                    $out .= '<pre>';
                    $out .= rtrim($figlet->fetch($code));
                    $out .= '</pre>';
                }else{
                    msg('Failed to load figlet.flf font file. CAPTCHA broken',-1);
                }
                break;
        }
        $out .= ' <input type="text" size="5" maxlength="5" name="'.$this->field_in.'" class="edit" /> ';

        // add honeypot field
        $out .= '<label class="no">Please keep this field empty: <input type="text" name="'.$this->field_hp.'" /></label>';
        $out .= '</div>';
        return $out;
    }

    /**
     * Checks if the the CAPTCHA was solved correctly
     *
     * @param  bool $msg when true, an error will be signalled through the msg() method
     * @return bool true when the answer was correct, otherwise false
     */
    function check($msg=true){
        // compare provided string with decrypted captcha
        $rand = PMA_blowfish_decrypt($_REQUEST[$this->field_sec],auth_cookiesalt());

        if($this->getConf('mode') == 'math'){
            $code = $this->_generateMATH($this->_fixedIdent(),$rand);
            $code = $code[1];
        }else{
            $code = $this->_generateCAPTCHA($this->_fixedIdent(),$rand);
        }

        if(!$_REQUEST[$this->field_sec] ||
           !$_REQUEST[$this->field_in] ||
           strtoupper($_REQUEST[$this->field_in]) != $code ||
           trim($_REQUEST[$this->field_hp]) !== ''
          ){
            if($msg) msg($this->getLang('testfailed'),-1);
            return false;
        }
        return true;
    }

    /**
     * Build a semi-secret fixed string identifying the current page and user
     *
     * This string is always the same for the current user when editing the same
     * page revision.
     */
    function _fixedIdent(){
        global $ID;
        $lm = @filemtime(wikiFN($ID));
        return auth_browseruid().
               auth_cookiesalt().
               $ID.$lm;
    }

    /**
     * Generates a random 5 char string
     *
     * @param $fixed string - the fixed part, any string
     * @param $rand  float  - some random number between 0 and 1
     * @return string
     */
    function _generateCAPTCHA($fixed,$rand){
        $fixed = hexdec(substr(md5($fixed),5,5)); // use part of the md5 to generate an int
        $numbers = md5($rand * $fixed); // combine both values

        // now create the letters
        $code = '';
        for($i=0;$i<10;$i+=2){
            $code .= chr(floor(hexdec($numbers[$i].$numbers[$i+1])/10) + 65);
        }

        return $code;
    }

    /**
     * Create a mathematical task and its result
     *
     * @param $fixed string - the fixed part, any string
     * @param $rand  float  - some random number between 0 and 1
     * @return string
     */
    function _generateMATH($fixed, $rand){
        $fixed = hexdec(substr(md5($fixed),5,5)); // use part of the md5 to generate an int
        $numbers = md5($rand * $fixed); // combine both values

        // first letter is the operator (+/-)
        $op  = (hexdec($numbers[0]) > 8 ) ? -1 : 1;
        $num = array(hexdec($numbers[1].$numbers[2]), hexdec($numbers[3]));

        // we only want positive results
        if(($op < 0) && ($num[0] < $num[1])) rsort($num);

        // prepare result and task text
        $res  = $num[0] + ($num[1] * $op);
        $task = $num[0] . (($op < 0) ? '&nbsp;-&nbsp;' : '&nbsp;+&nbsp;') . $num[1] . '&nbsp;=&nbsp;?';

        return array($task, $res);
    }

    /**
     * Create a CAPTCHA image
     */
    function _imageCAPTCHA($text){
        $w = $this->getConf('width');
        $h = $this->getConf('height');

        $fonts = glob(dirname(__FILE__).'/fonts/*.ttf');

        // create a white image
        $img = imagecreate($w, $h);
        imagecolorallocate($img, 255, 255, 255);

        // add some lines as background noise
        for ($i = 0; $i < 30; $i++) {
            $color = imagecolorallocate($img,rand(100, 250),rand(100, 250),rand(100, 250));
            imageline($img,rand(0,$w),rand(0,$h),rand(0,$w),rand(0,$h),$color);
        }

        // draw the letters
        for ($i = 0; $i < strlen($text); $i++){
            $font  = $fonts[array_rand($fonts)];
            $color = imagecolorallocate($img, rand(0, 100), rand(0, 100), rand(0, 100));
            $size  = rand(floor($h/1.8),floor($h*0.7));
            $angle = rand(-35, 35);

            $x = ($w*0.05) +  $i * floor($w*0.9/5);
            $cheight = $size + ($size*0.5);
            $y = floor($h / 2 + $cheight / 3.8);

            imagettftext($img, $size, $angle, $x, $y, $color, $font, $text[$i]);
        }

        header("Content-type: image/png");
        imagepng($img);
        imagedestroy($img);
    }


}
