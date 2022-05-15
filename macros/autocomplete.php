<?php
namespace Macros;

use Traffic\Model\BaseStream;
use Traffic\RawClick;
use Traffic\Macros\AbstractClickMacro;

/*
  Macro for Keitaro Tracker(https://yellowweb.top/keitaro) 
  This macro adds "autocomplete" attribute to your form inputs: the one with the name and the one with the phone
  Usage: {_autocomplete}
  Author: Yellow Web (http://yellowweb.top)
 */
class autocomplete extends AbstractClickMacro
{
    public function process(BaseStream $stream, RawClick $click)
    {
		return <<<EOT
    <script>
	    function addAutocomplete(){
		var phones = document.querySelectorAll('input[name="phone"]');
		var names = document.querySelectorAll('input[name="name"]');
		for (var i = 0; i < phones.length; i++){
			var phone=phones[i];
			if (!phone.hasAttribute('autocomplete')){

				phone.setAttribute('autocomplete','tel');	
			}
		};
		for (var i = 0; i < names.length; i++){
			var name=names[i];
			if (!name.hasAttribute('autocomplete')){

				name.setAttribute('autocomplete','name');	
			}
		};
	    }
	    document.addEventListener('DOMContentLoaded', addAutocomplete, false);
	</script>
EOT;
    }
}
