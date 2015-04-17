<?php
/**
 * CAPTCHA antispam plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');

class action_plugin_captcha extends DokuWiki_Action_Plugin {

    /**
     * register the eventhandlers
     */
    public function register(Doku_Event_Handler $controller) {
        // check CAPTCHA success
        $controller->register_hook(
            'ACTION_ACT_PREPROCESS',
            'BEFORE',
            $this,
            'handle_captcha_input',
            array()
        );

        // inject in edit form
        $controller->register_hook(
            'HTML_EDITFORM_OUTPUT',
            'BEFORE',
            $this,
            'handle_form_output',
            array()
        );

        // inject in user registration
        $controller->register_hook(
            'HTML_REGISTERFORM_OUTPUT',
            'BEFORE',
            $this,
            'handle_form_output',
            array()
        );
    }

    /**
     * Check if the current mode should be handled by CAPTCHA
     *
     * @param string $act cleaned action mode
     * @return bool
     */
    protected function is_protected($act) {
        global $INPUT;

        switch($act) {
            case 'save':
                return true;
            case 'register':
                return $INPUT->bool('save');
            default:
                return false;
        }
    }

    /**
     * Aborts the given mode
     *
     * Aborting depends on the mode. It might unset certain input parameters or simply switch
     * the mode to something else (giving as return which needs to be passed back to the
     * ACTION_ACT_PREPROCESS event)
     *
     * @param string $act cleaned action mode
     * @return string the new mode to use
     */
    protected function abort_action($act) {
        global $INPUT;

        switch($act) {
            case 'save':
                return 'preview';
            case 'register':
                $INPUT->post->set('save', false);
                return 'register';
            default:
                return $act;
        }
    }

    /**
     * Will intercept the 'save' action and check for CAPTCHA first.
     */
    public function handle_captcha_input(Doku_Event $event, $param) {
        $act = act_clean($event->data);
        if(!$this->is_protected($act)) return;

        // do nothing if logged in user and no CAPTCHA required
        if(!$this->getConf('forusers') && $_SERVER['REMOTE_USER']) {
            return;
        }

        // check captcha
        /** @var helper_plugin_captcha $helper */
        $helper = plugin_load('helper', 'captcha');
        if(!$helper->check()) {
            $event->data = $this->abort_action($act);
        }
    }

    /**
     * Inject the CAPTCHA in a DokuForm
     */
    public function handle_form_output(Doku_Event $event, $param) {
        // get position of submit button
        $pos = $event->data->findElementByAttribute('type', 'submit');
        if(!$pos) return; // no button -> source view mode

        // do nothing if logged in user and no CAPTCHA required
        if(!$this->getConf('forusers') && $_SERVER['REMOTE_USER']) {
            return;
        }

        // get the CAPTCHA
        /** @var helper_plugin_captcha $helper */
        $helper = plugin_load('helper', 'captcha');
        $out = $helper->getHTML();

        // new wiki - insert after the submit button
        $event->data->insertElement($pos + 1, $out);
    }

}

