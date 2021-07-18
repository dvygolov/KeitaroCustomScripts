<?php
namespace Macros;

use Traffic\Model\BaseStream;
use Traffic\RawClick;
use Traffic\Macros\AbstractClickMacro;

/*
  Macro for Keitaro Tracker(https://yellowweb.top/keitaro) 
  This macro disables Right click button and Ctrl+S in the user's browser.
  Put it right after <body> tag like {_disablecopy}
  Author: Yellow Web (http://yellowweb.top)
 */
class disablecopy extends AbstractClickMacro
{
    public function process(BaseStream $stream, RawClick $click)
    {
		return "
		<script>
		window.onload = function(){
				document.body.oncontextmenu= function(){return false;};
				window.addEventListener('selectstart', function(e){ e.preventDefault(); });
				document.addEventListener('keydown',function(e) {
					if (e.keyCode == 83 && (navigator.platform.match('Mac') ? e.metaKey : e.ctrlKey)) {
						e.preventDefault();
						e.stopPropagation();
					}
				},false);		
		}
		</script>";
    }
}