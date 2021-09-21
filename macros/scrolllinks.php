<?php
namespace Macros;

use Traffic\Model\BaseStream;
use Traffic\RawClick;
use Traffic\Macros\AbstractClickMacro;

/*
  Macro for Keitaro Tracker(https://yellowweb.top/keitaro) 
  This macro replaces all the links in your page so that they scroll the page to the id that you choose.
  For example: {_scrolllinks:ywbform}
  Author: Yellow Web (http://yellowweb.top)
 */
class scrolllinks extends AbstractClickMacro
{
    public function process(BaseStream $stream, RawClick $click, $id)
    {
		return <<<EOT
<script>
document.addEventListener('DOMContentLoaded', function() {

const anchors = document.querySelectorAll('a')

for (let anchor of anchors) {
  anchor.addEventListener('click', function (e) {
    e.preventDefault()
       
    document.getElementById('{$id}').scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    })
  })
}
});
</script>
EOT;
    }
}