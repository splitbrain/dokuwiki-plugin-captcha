<?php
/**
 * CAPTCHA antispam plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');
require_once(DOKU_INC.'inc/blowfish.php');

class action_plugin_captcha extends DokuWiki_Action_Plugin {

    /**
     * return some info
     */
    function getInfo(){
        return array(
            'author' => 'Andreas Gohr',
            'email'  => 'andi@splitbrain.org',
            'date'   => '2007-08-14',
            'name'   => 'CAPTCHA Plugin',
            'desc'   => 'Use a CAPTCHA challenge to protect the Wiki against automated spam',
            'url'    => 'http://wiki:splitbrain.org/plugin:captcha',
        );
    }

    /**
     * register the eventhandlers
     */
    function register(&$controller){
        $controller->register_hook('ACTION_ACT_PREPROCESS',
                                   'BEFORE',
                                   $this,
                                   'handle_act_preprocess',
                                   array());

        // old hook
        $controller->register_hook('HTML_EDITFORM_INJECTION',
                                   'BEFORE',
                                   $this,
                                   'handle_editform_output',
                                   array('editform' => true, 'oldhook' => true));

        // new hook
        $controller->register_hook('HTML_EDITFORM_OUTPUT',
                                   'BEFORE',
                                   $this,
                                   'handle_editform_output',
                                   array('editform' => true, 'oldhook' => false));

        if($this->getConf('regprotect')){
            // old hook
            $controller->register_hook('HTML_REGISTERFORM_INJECTION',
                                       'BEFORE',
                                       $this,
                                       'handle_editform_output',
                                       array('editform' => false, 'oldhook' => true));

            // new hook
            $controller->register_hook('HTML_REGISTERFORM_OUTPUT',
                                       'BEFORE',
                                       $this,
                                       'handle_editform_output',
                                       array('editform' => false, 'oldhook' => false));
        }
    }

    /**
     * Will intercept the 'save' action and check for CAPTCHA first.
     */
    function handle_act_preprocess(&$event, $param){
        $act = $this->_act_clean($event->data);
        if(!('save' == $act || ($this->getConf('regprotect') &&
                                'register' == $act &&
                                $_POST['save']))){
            return; // nothing to do for us
        }

        // do nothing if logged in user and no CAPTCHA required
        if(!$this->getConf('forusers') && $_SERVER['REMOTE_USER']){
            return;
        }

        // compare provided string with decrypted captcha
        $rand = PMA_blowfish_decrypt($_REQUEST['plugin__captcha_secret'],auth_cookiesalt());
        $code = $this->_generateCAPTCHA($this->_fixedIdent(),$rand);

        if(!$_REQUEST['plugin__captcha_secret'] ||
           !$_REQUEST['plugin__captcha'] ||
           strtoupper($_REQUEST['plugin__captcha']) != $code){
                // CAPTCHA test failed!
                msg($this->getLang('testfailed'),-1);
                if($act == 'save'){
                    // stay in preview mode
                    $event->data = 'preview';
                }else{
                    // stay in register mode, but disable the save parameter
                    $_POST['save'] = false;
                }
        }
    }

    /**
     * Create the additional fields for the edit form
     */
    function handle_editform_output(&$event, $param){
        // check if source view -> no captcha needed
        if(!$param['oldhook']){
            // get position of submit button
            $pos = $event->data->findElementByAttribute('type','submit');
            if(!$pos) return; // no button -> source view mode
        }elseif($param['editform'] && !$event->data['writable']){
            if($param['editform'] && !$event->data['writable']) return;
        }

        // do nothing if logged in user and no CAPTCHA required
        if(!$this->getConf('forusers') && $_SERVER['REMOTE_USER']){
            return;
        }

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
        }
        $out .= '</div>';

        if($param['oldhook']){
            // old wiki - just print
            echo $out;
        }else{
            // new wiki - insert at correct position
            $event->data->insertElement($pos++,$out);
        }
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
     * Pre-Sanitize the action command
     *
     * Similar to act_clean in action.php but simplified and without
     * error messages
     */
    function _act_clean($act){
         // check if the action was given as array key
         if(is_array($act)){
           list($act) = array_keys($act);
         }

         //remove all bad chars
         $act = strtolower($act);
         $act = preg_replace('/[^a-z_]+/','',$act);

         return $act;
     }

    /**
     * Generates a random 5 char string
     *
     * @param $fixed string - the fixed part, any string
     * @param $rand  float  - some random number between 0 and 1
     */
    function _generateCAPTCHA($fixed,$rand){
        $fixed = hexdec(substr(md5($fixed),5,5)); // use part of the md5 to generate an int
        $rand = $rand * $fixed; // combine both values

        // seed the random generator
        srand($rand);

        // now create the letters
        $code = '';
        for($i=0;$i<5;$i++){
            $code .= chr(rand(65, 90));
        }

        // restore a really random seed
        srand();

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

