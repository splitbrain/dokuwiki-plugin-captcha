/**
 * Autofill and hide the whole captcha stuff in the simple JS mode
 */
jQuery(function(){
    var code = jQuery('#plugin__captcha_code')[0];
    if(!code) return;

    var box  = jQuery('#plugin__captcha_wrapper input[type=text]')[0];
    box.value=code.innerHTML;

    jQuery('#plugin__captcha_wrapper')[0].style.display = 'none';
});
