<?php
namespace Macros;

use Traffic\Model\BaseStream;
use Traffic\RawClick;
use Traffic\Macros\AbstractClickMacro;

/*
  Macro for Keitaro Tracker(https://yellowweb.top/keitaro) 
  This macro replaces submit button of the form with "Loading" text and image after the user clicks the Submit button
  So the user won't be able to click several times on the button and you won't get trash leads.
  For example: {_formlocker:Loading! Please Wait!}
  Parameter #1 should be the text that will be shown to the user.
  Author: Yellow Web (http://yellowweb.top)
  Original author of the script: Adam (https://t.me/adamusfb)
 */
class formlocker extends AbstractClickMacro
{
    public function process(BaseStream $stream, RawClick $click, $message)
    {
		if (!isset($message) || $message==='') $message='Loading, please wait!';
		return <<<EOT
    <script>
	    function lockform(form){
	        var message = '{$message}'; 
			var imageBase64 = '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';
	        
	        var submit = form.querySelector('input[type=submit]');
	        if (submit===null)
				submit = form.querySelector('button');
			if (submit===null) return;

			var ntf = document.createElement('div');
			ntf.innerHTML = '<center><img src="data:image/png;base64,'+imageBase64+'"><br>'+message+'</center>';
			submit.style = 'display:none';
			submit.parentNode.insertBefore(ntf, submit.nextSibling)
	    }
	    
        document.onreadystatechange = function(){
           if(document.readyState === 'complete'){
                var forms = document.forms;
                for (var i = 0; i < forms.length; i++){
                    forms[i].setAttribute("onSubmit", "lockform(this);");
                };
           }
        }
	</script>
EOT;
    }
}