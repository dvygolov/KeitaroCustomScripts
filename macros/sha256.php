<?php

namespace Macros;

use Traffic\Model\BaseStream;
use Traffic\RawClick;
use Traffic\Macros\AbstractClickMacro;

/*
  Macro for Keitaro Tracker(https://yellowweb.top/keitaro) 
  This macro is useful for S2S-postbacks when you do not need to send raw values but their hashes.
  Macro has 1 parameter - sub id number.
  Usage: {sha256:1} will return sha256 hash for sub_id_1
  Author: Yellow Web (http://yellowweb.top)
 */

class sha256 extends AbstractClickMacro
{
  public function process(BaseStream $stream, RawClick $click, $subNumber)
  {
    if ($subNumber=="country")
      return hash("sha256", strtolower($click->getCountry()));
    if ($subNumber=="city")
      return hash("sha256", $click->getCity());
    return hash("sha256", $click->getSubIdN($subNumber));
  }
}
