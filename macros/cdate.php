<?php
namespace Macros;

use Traffic\Model\BaseStream;
use Traffic\RawClick;
use Traffic\Macros\AbstractClickMacro;

/**
 * This is an example of a macro
 */
class cdate extends AbstractClickMacro
{
    public function process(BaseStream $stream, RawClick $click)
    {
		return "
		<script>
		function dt(days){
			var cs=document.currentScript.parentElement;
			let today = new Date();
			let d = new Date();
			d.setDate(today.getDate() - days);
			let m = (d.getMonth()+1);
			if (m<10) m='0'+m;
			var datestring = d.getDate()  + '.' + m + '.' + d.getFullYear();
			cs.innerHTML= datestring;
		}
		</script>";
    }
}