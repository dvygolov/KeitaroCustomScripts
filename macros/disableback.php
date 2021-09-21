<?php
namespace Macros;

use Traffic\Model\BaseStream;
use Traffic\RawClick;
use Traffic\Macros\AbstractClickMacro;

/*
  Macro for Keitaro Tracker(https://yellowweb.top/keitaro) 
  This macro disables Back button in the user's browser.
  Put it right after <body> tag like {_disableback}
  Author: Yellow Web (http://yellowweb.top)
 */
class disableback extends AbstractClickMacro
{
    public function process(BaseStream $stream, RawClick $click)
    {
		return "
		<script> 
		  history.pushState(null, null, location.href); 
		  history.back(); 
		  history.forward(); 
		  window.onpopstate = function () { history.go(1); }; 
		</script>";
    }
}