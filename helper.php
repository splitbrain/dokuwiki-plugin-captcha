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
        $code = $this->_generateCAPTCHA($this->_fixedIdent(),$rand);
        $secret = PMA_blowfish_encrypt($rand,auth_cookiesalt());

        $out  = '';
        $out .= '<div id="plugin__captcha_wrapper">';
        $out .= '<input type="hidden" name="plugin__captcha_secret" value="'.hsc($secret).'" />';
        $out .= '<label for="plugin__captcha">'.$this->getLang('fillcaptcha').'</label> ';
        $out .= '<input type="text" size="5" maxlength="5" name="plugin__captcha" id="plugin__captcha" class="edit" /> ';
        switch($this->getConf('mode')){
            case 'text':
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
        $rand = PMA_blowfish_decrypt($_REQUEST['plugin__captcha_secret'],auth_cookiesalt());
        $code = $this->_generateCAPTCHA($this->_fixedIdent(),$rand);

        if(!$_REQUEST['plugin__captcha_secret'] ||
           !$_REQUEST['plugin__captcha'] ||
           strtoupper($_REQUEST['plugin__captcha']) != $code){
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
     * Create a CAPTCHA image
     */
    function _imageCAPTCHA($text){
        $w = $this->getConf('width');
        $h = $this->getConf('height');

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
            $font  = dirname(__FILE__).'/VeraSe.ttf';
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
