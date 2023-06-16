<?php

namespace Macros;

use Traffic\Model\BaseStream;
use Traffic\RawClick;
use Traffic\Macros\AbstractClickMacro;

/*
  Macro for Keitaro Tracker(https://yellowweb.top/keitaro) 
  This macro gets the parameter from querystring by its name and sets the cookie with the same name and value
  For example: {_cookie:fbpx}
  Put this macro right after the <body> tag.
  Author: Yellow Web (http://yellowweb.top)
 */

class cookie extends AbstractClickMacro
{
  public function process(BaseStream $stream, RawClick $click, $paramName)
  {
    return "
<script>
document.addEventListener('DOMContentLoaded', function() {
    const urlSearchParams = new URLSearchParams(window.location.search);
    const pxValue = urlSearchParams.get('$paramName');
    const date = new Date();
    date.setTime(date.getTime() + (7 * 24 * 60 * 60 * 1000));
    const expires = 'expires=' + date.toUTCString();
    document.cookie = '$paramName=' + pxValue + ';' + expires + ';path=/';
});
</script>";
  }
}
