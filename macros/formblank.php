<?php
namespace Macros;

use Traffic\Model\BaseStream;
use Traffic\RawClick;
use Traffic\Macros\AbstractClickMacro;

/*
  Macro for Keitaro Tracker(https://yellowweb.top/keitaro) 
  This macro adds "_blank" attribute to all of the FORMS on the page
  After the user submits the form - it is opened in a new browser tab and the page itself is redirected to the
  url that you pass in the first macro's parameter.
  Use it in your landings. For example: {_formblank:https://yellowweb.top,3000} - second parameter is the timeout 
  in milliseconds after which redirect happens. Put this macro right after the <body> tag.
  Author: Yellow Web (http://yellowweb.top)
 */
class formblank extends AbstractClickMacro
{
    public function process(BaseStream $stream, RawClick $click, $url,$timeout)
    {
		return "
		<script>
		document.addEventListener('DOMContentLoaded', function() {

		 var elements = document.getElementsByTagName('form');

		 for (var i = 0; i < elements.length; i++) {
			elements[i].setAttribute('target','_blank');
			elements[i].addEventListener('submit',redirect,false);
		 }
		});

		function redirect(){
			setTimeout(()=>window.location.replace('".$url."'),".$timeout."); 
		}
		</script>";
    }
}