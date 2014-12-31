<?php
/**
 * CAPTCHA antispam plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_captcha extends DokuWiki_Action_Plugin {

    /**
     * register the eventhandlers
     */
    function register(&$controller) {
        $controller->register_hook(
            'ACTION_ACT_PREPROCESS',
            'BEFORE',
            $this,
            'handle_act_preprocess',
            array()
        );

        // old hook
        $controller->register_hook(
            'HTML_EDITFORM_INJECTION',
            'BEFORE',
            $this,
            'handle_editform_output',
            array('editform' => true, 'oldhook' => true)
        );

        // new hook
        $controller->register_hook(
            'HTML_EDITFORM_OUTPUT',
            'BEFORE',
            $this,
            'handle_editform_output',
            array('editform' => true, 'oldhook' => false)
        );

        if($this->getConf('regprotect')) {
            // old hook
            $controller->register_hook(
                'HTML_REGISTERFORM_INJECTION',
                'BEFORE',
                $this,
                'handle_editform_output',
                array('editform' => false, 'oldhook' => true)
            );

            // new hook
            $controller->register_hook(
                'HTML_REGISTERFORM_OUTPUT',
                'BEFORE',
                $this,
                'handle_editform_output',
                array('editform' => false, 'oldhook' => false)
            );
        }
        if($this->getConf('loginprotect')) {        
            $controller->register_hook(
                'HTML_LOGINFORM_OUTPUT',
                'BEFORE',
                $this,
                'handle_login_form',
                array()
            ); 
      }          
    }

    /**
     * Will intercept the 'save' action and check for CAPTCHA first.
     */
    function handle_act_preprocess(&$event, $param) {
        $act = $this->_act_clean($event->data);
        
        if($this->getConf('loginprotect')) {        
            $this->handle_login();
            return;
        }
        
        if(!('save' == $act || ($this->getConf('regprotect') &&
                'register' == $act &&
                $_POST['save']))
        ) {
            return; // nothing to do for us
        }

        // do nothing if logged in user and no CAPTCHA required
        if(!$this->getConf('forusers') && $_SERVER['REMOTE_USER']) {
            return;
        }

        // check captcha
        $helper = plugin_load('helper', 'captcha');
        if(!$helper->check()) {
            if($act == 'save') {
                // stay in preview mode
                $event->data = 'preview';
            } else {
                // stay in register mode, but disable the save parameter
                $_POST['save'] = false;
            }
        }
    }

    /**
     * Create the additional fields for the edit form
     * @author Myron Turner <turnermm02@shaw.ca>
     */
    function handle_editform_output(&$event, $param) {
        // check if source view -> no captcha needed
        if(!$param['oldhook']) {
            // get position of submit button
            $pos = $event->data->findElementByAttribute('type', 'submit');
            if(!$pos) return; // no button -> source view mode
        } elseif($param['editform'] && !$event->data['writable']) {
            if($param['editform'] && !$event->data['writable']) return;
        }

        // do nothing if logged in user and no CAPTCHA required
        if(!$this->getConf('forusers') && $_SERVER['REMOTE_USER']) {
            return;
        }

        // get the CAPTCHA
        $helper = plugin_load('helper', 'captcha');
        $out    = $helper->getHTML();

        if($param['oldhook']) {
            // old wiki - just print
            echo $out;
        } else {
            // new wiki - insert at correct position
            $event->data->insertElement($pos++, $out);
        }
    }
     /**
      *  Insert captcha into login form if loginprotect is true
      *  @url parameter: chk=captcha_check, identifies login mode
      * @author Myron Turner <turnermm02@shaw.ca>
    */
    function handle_login_form(&$event, $param) {           
    	$pos = $event->data->findElementByAttribute('type', 'submit');      
        $helper = plugin_load('helper', 'captcha');
        $out    = $helper->getHTML(); 
        $event->data->_hidden['chk'] = 'captcha_check';  
        $event->data->insertElement($pos+1, $out);
    }     
    
    /**
     *   Redirect with additional parameters if captcha fails and
     *   output  'testfailed' message on re-load      
     *
     *   @url_params
     *         do=logout => to force logout
     *         capt=r  => to identify on reload that the captcha has failed
     *  
     * @author  Myron Turner <turnermm02@shaw.ca>     
    */    
    function handle_login() {
        if(isset($_REQUEST['chk'])) {        
            $helper = $this->loadHelper('captcha', true);
            if(!$helper->check()) {
                 msg($helper->getLang('testfailed'), -1);
                $url = wl('',array('do'=>'logout'), true, '&') ;             
                send_redirect($url);
                exit();
            }
        }
   }
    /**
     * Pre-Sanitize the action command
     *
     * Similar to act_clean in action.php but simplified and without
     * error messages
     */
    function _act_clean($act) {
        // check if the action was given as array key
        if(is_array($act)) {
            list($act) = array_keys($act);
        }

        //remove all bad chars
        $act = strtolower($act);
        $act = preg_replace('/[^a-z_]+/', '', $act);

        return $act;
    }

}

