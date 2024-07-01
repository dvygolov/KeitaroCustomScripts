<?php

namespace Macros;

use Traffic\Model\BaseStream;
use Traffic\RawClick;
use Traffic\Macros\AbstractClickMacro;

/*
  Macro for Keitaro Tracker(https://yellowweb.top/keitaro)
  This macro displays a big button that is always at the bottom of the screen.
  Clicking the button scrolls the page to the form.
  Example: {_stickybutton:FFAABB,FFFFFF,Click Me!,orderform}
  First parameter is button's background color in hex-code
  Second parameter is text's color in hex-code
  Third parameter is the button's text
  Author: Yellow Web (http://yellowweb.top)
 */

class stickybutton extends AbstractClickMacro
{
    public function process(BaseStream $stream, RawClick $click, $bcolor, $tcolor, $text, $formid)
    {
        return <<<EOT
<style>
.link-block {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2147483647;
    position: fixed;
}

.link {
    position: fixed;
    width: 90%;
    display: block;
    text-decoration: none;
    text-transform: uppercase;
    text-align: center;
    bottom: 20px;
    color: #{$tcolor};
    background-color: #{$bcolor};
    padding: 20px 0px;
    border-radius: 10px;
    font-weight: 900;
    box-shadow: 0px 0px 8px 1px #6e6e6e;
}

.link:hover {
    box-shadow: none;
    transition: 1s;
}
</style>
<script>
document.addEventListener("DOMContentLoaded",function(){
  let lb=document.querySelector('.link-block');
  lb.onclick=function(){
    lb.style="display:none";
  }
});
</script>
<div class="link-block">
        <a class="link" href="#{$formid}">{$text}</a>
</div>
EOT;
    }
}
