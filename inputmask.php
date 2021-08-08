<?php
namespace Macros;

use Traffic\Model\BaseStream;
use Traffic\RawClick;
use Traffic\Macros\AbstractClickMacro;

/*
  Macro for Keitaro Tracker(https://yellowweb.top/keitaro) 
  This macro adds a validation rule for all phone fields on the page.
  So the user won't be able to write a wrong number.
  For example: {_inputmask:+7-999-999-99-99}
  Parameter #1 should be the mask. All info about masks is here: https://github.com/RobinHerbots/Inputmask
  You should first create a "common" subfolder in the Keitaro's landing folder and put the inputmask.min.js file there!
  Author: Yellow Web (http://yellowweb.top)
 */
class inputmask extends AbstractClickMacro
{
    public function process(BaseStream $stream, RawClick $click, $mask)
    {
		return <<<EOT
<script src='../common/inputmask.min.js'></script>
<script>
function addinputmask(){
	var tels=document.querySelectorAll('input[type="tel"]');
	var im = new Inputmask({
	  mask: '{$mask}',
	  showMaskOnHover: true,
	  showMaskOnFocus: true,	
	  clearIncomplete: true
	});
	
	for (var i=0; i<tels.length; i++){
		im.mask(tels[i]);
	}
}
document.addEventListener('DOMContentLoaded', addinputmask, false);
</script>

EOT;
    }
}