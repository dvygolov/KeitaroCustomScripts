<?php
namespace Macros;

use Traffic\Model\BaseStream;
use Traffic\RawClick;
use Traffic\Macros\AbstractClickMacro;

/*
  Macro for Keitaro Tracker(https://yellowweb.top/keitaro) 
  This macro adds a validation rule for all phone fields on the page.
  So the user won't be able to write a wrong number.
  For example: {_intltel:DE}
  Parameter #1 should be the country. 
  Author: Yellow Web (http://yellowweb.top)
 */
class intltel extends AbstractClickMacro
{
    public function process(BaseStream $stream, RawClick $click, $country)
    {
		return <<<EOT
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.15/js/intlTelInput.min.js"></script>
<style>
.iti{
    width: 100%;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', ()=>{
    var phones=document.querySelectorAll('input[name=phone]');
    for (var i = phones.length - 1; i >= 0; i--) {
        processInput(phones[i]);
    }
});

function processInput(phone){
    var intlErrorMap = ["Invalid number", "Invalid country code", "Too short", "Too long", "Invalid number"];
    var iti = window.intlTelInput(phone,{
        allowDropdown: false,
        initialCountry: '{$country}',
        nationalMode: true,
        autoPlaceholder: 'aggressive',
        formatOnDisplay: true,
        separateDialCode: false,
        utilsScript: 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.15/js/utils.min.js'
    });

    phone.addEventListener('input', ()=> {
        phone.setCustomValidity('');
        if (iti.isValidNumber()) return;
        var errorCode = iti.getValidationError();
        phone.setCustomValidity(intlErrorMap[errorCode]);
    });

    phone.addEventListener('blur', function() { 
        if (!iti.isValidNumber()) return;
        phone.value = iti.getNumber();
        console.log(phone.value);
    });
}
</script>
EOT;
    }
}
