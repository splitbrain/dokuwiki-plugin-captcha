/**
 * Autofill and hide the whole captcha stuff in the simple JS mode
 */
addInitEvent(function(){
    var code = $('plugin__captcha_code');
    if(!code) return;

    var box  = $('plugin__captcha');
    box.value=code.innerHTML;

    $('plugin__captcha_wrapper').style.display = 'none';
});
