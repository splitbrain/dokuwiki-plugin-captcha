/**
 * Autofill and hide the whole captcha stuff in the simple JS mode
 */
jQuery(function () {
    var $code = jQuery('#plugin__captcha_code');
    if (!$code.length) return;

    var $box = jQuery('#plugin__captcha_wrapper input[type=text]');
    $box.first().val($code.text().replace(/([^A-Z])+/g, ''));

    jQuery('#plugin__captcha_wrapper').hide();
});
