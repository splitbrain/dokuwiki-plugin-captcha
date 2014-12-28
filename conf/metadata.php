<?php
/**
 * Options for the CAPTCHA plugin
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */

$meta['mode']        = array('multichoice', '_choices' => array('js', 'text', 'math', 'question', 'image', 'audio', 'figlet'));
$meta['regprotect']  = array('onoff');
$meta['forusers']    = array('onoff');
$meta['lettercount'] = array('numeric', '_min' => 3, '_max' => 16);
$meta['width']       = array('numeric', '_pattern' => '/[0-9]+/');
$meta['height']      = array('numeric', '_pattern' => '/[0-9]+/');
$meta['question']    = array('string');
$meta['answer']      = array('string');
$meta['loginprotect'] = array('onoff');
