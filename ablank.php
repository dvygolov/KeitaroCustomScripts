<?php
namespace Macros;

use Traffic\Model\BaseStream;
use Traffic\RawClick;
use Traffic\Macros\AbstractClickMacro;

/*
  Macro for Keitaro Tracker(https://yellowweb.top/keitaro) 
  This macro adds "_blank" attribute to all of the links on the page (except those which start with # symbol)
  After the user clicks the link - it is opened in a new browser tab and the page itself is redirected to the
  url that you pass in the first macro's parameter.
  Use it in your prelandings. For example: {_ablank:https://yellowweb.top,3000} - second parameter is the timeout 
  in milliseconds after which redirect happens. Put this macro right after the <body> tag.
  Author: Yellow Web (http://yellowweb.top)
 */
class ablank extends AbstractClickMacro
{
    public function process(BaseStream $stream, RawClick $click, $url,$timeout)
    {
		return "
		<script>
		function redirect(){
			setTimeout(()=>window.location.replace('".$url."'),".$timeout.");
		}
		document.addEventListener('DOMContentLoaded', function() {
			var links = document.getElementsByTagName('a'); 
			for (var i=0;i<links.length;i++) {
				let l=links[i];
				if (!l.getAttribute('href').startsWith('#')){
					l.addEventListener('click',redirect,false);
					l.setAttribute('target', '_blank');
				}
			} 
		});
		</script>";
    }
}