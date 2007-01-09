<?php
/**
 * CAPTCHA antispam plugin - Image generator
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../../').'/');
define('NOSESSION',true);
define('DOKU_DISABLE_GZIP_OUTPUT', 1);
require_once(DOKU_INC.'inc/init.php');
require_once(DOKU_INC.'inc/auth.php');
require_once(dirname(__FILE__).'/action.php');

$ID = $_REQUEST['id'];
$plugin = new action_plugin_captcha();
$rand = PMA_blowfish_decrypt($_REQUEST['secret'],auth_cookiesalt());
$code = $plugin->_generateCAPTCHA($plugin->_fixedIdent(),$rand);
$plugin->_imageCAPTCHA($code);

//Setup VIM: ex: et ts=4 enc=utf-8 :
