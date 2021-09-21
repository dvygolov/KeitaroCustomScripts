<?php
namespace Macros;

use Traffic\Model\BaseStream;
use Traffic\RawClick;
use Traffic\Macros\AbstractClickMacro;

/*
  Macro for Keitaro Tracker(https://yellowweb.top/keitaro) 
  This macro displays a link like a button that leads to the offer.  
  For example: {_button:https://yellowweb.top,Click Me!}   
  First parameter is button's background color in hex-code
  Second parameter is text's color in hex-code
  Third parameter is the button's text
  Author: Yellow Web (http://yellowweb.top)
 */
class button extends AbstractClickMacro
{
    public function process(BaseStream $stream, RawClick $click, $bcolor,$tcolor,$text)
    {
		return <<<EOT
<div style='width:100%;display:flex;justify-content:center;'>
	<a href='{offer}' style='background:#{$bcolor};border:1px solid #556699;border-radius: 11px;box-shadow:1px 1px #444444;padding:20px 45px;color:#{$tcolor};display: inline-block;font:normal bold 26px/1 "Open Sans", sans-serif;text-align:center;'>{$text}</a>
</div>
EOT;
    }
}