<?php
/**
 * Options for the CAPTCHA plugin
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */

$meta['mode']       = array('multichoice','_choices' => array('js','text','image','audio'));
$meta['regprotect'] = array('onoff');
$meta['forusers']   = array('onoff');
$meta['width']      = array('numeric','_pattern' => '/[0-9]+/');
$meta['height']     = array('numeric','_pattern' => '/[0-9]+/');

