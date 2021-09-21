<?php
namespace Macros;

use Traffic\Model\BaseStream;
use Traffic\RawClick;
use Traffic\Macros\AbstractClickMacro;

/*
  Macro for Keitaro Tracker(https://yellowweb.top/keitaro) 
  This macro adds an order form to your prelandings, for example:
  {_form:order.php,sub1,px,4,aabbaa,Our Great Product,Name,Phone,Order Now!}
  
  Parameter #1 should be the name of your php script that sends leads to your affiliate program.
  Parameter #2 is the name of the form's input field that will have Keitaro's unique click id as its value.
  Parameter #3 is the name of the form's input that will have the Facebook's pixel id as its value. 
  Parameter #4 is the index of Keitaro's sub-mark that has the Facebook's pixel id as its value. It will be added as the value for the input field from parameter #3.
  Parameter #5 is the color of the form in hex-code
  Parameter #6 is the header of the form
  Parameters #7&8 are the labels for name and phone fields of the form
  Parameter #9 is the label for submit button.
  
  !!Also this script replaces all the links in your prelanding so that they scroll the page to this form.
  This script also adds country code into the form's hidden input named 'country'.
  Author: Yellow Web (http://yellowweb.top)
 */
class form extends AbstractClickMacro
{
    public function process(BaseStream $stream, RawClick $click, $orderphp,$subidname,$pxsubname,$pxsubid,$color,$header,$nametxt,$phonetxt,$ordertxt)
    {
		return <<<EOT
<script>
document.addEventListener('DOMContentLoaded', function() {

const anchors = document.querySelectorAll('a')

for (let anchor of anchors) {
  anchor.addEventListener('click', function (e) {
    e.preventDefault()
       
    document.getElementById('ywbplform').scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    })
  })
}
});
</script>

<style>
.ywbcontainer {
    margin:auto;
    padding:0px;
    border:1px solid #$color;
    -webkit-border-radius: 5px;-moz-border-radius: 5px;border-radius: 5px; 
	width: 320px;
	height: 240px;}
.ywbwrap {margin:auto;width:100%;height:100%;}
.ywbp{
    margin:0px 0px 5px 0px;
    padding-left:5px;
    font-size:15px;
    font-family: 'PT Sans', sans-serif;}
.ywbh1{
    text-align:center;
    color:#fff;
    background:#$color;
    text-transform: uppercase;
    margin-top:0px;
    margin-bottom:10px;
    height:40px;
    line-height:40px;
    font-size: 18px;
    font-weight:bold;
    font-family: 'PT Sans', sans-serif;}
.ywborderform {
    padding-left: 16px;
    padding-right: 16px;
    margin: 0px;
    font-family: 'PT Sans', sans-serif;}
.ywborderform input{
    width:90%;
    padding:5px;
    margin: 0px 10px 10px 10px;
    border: 2px solid #$color;
    font-size:18px;
    -webkit-border-radius: 5px;-moz-border-radius: 5px;border-radius: 5px; 
    background: #fff;
    display: block; 
    box-sizing: border-box;
    height: 30px;}
.ywborderform .clear{clear: both;}
.ywbbutton{
    display: block;margin: 20px auto; 
    border:none; outline:none;color: #fff;font-size:24px;
    font-family: 'PT Sans', sans-serif;
    text-decoration: none;text-align: center;width: 180px;height: 51px;
    line-height: 48px;background: #$color;font-weight:600;
    text-transform: uppercase;
    -webkit-border-radius: 30px;-moz-border-radius: 3px;border-radius: 30px;cursor: pointer;}	
</style>
<div class='ywbcontainer'>
<div class='ywbwrap' id='ywbplform'>
        <h1 class='ywbh1'>$header</h1>
        <form action='$orderphp' method='post' class='ywborderform'>
            <p class='ywbp'>$nametxt:</p>
            <input value='' name='name' type='text' required='1'>
            <p class='ywbp'>$phonetxt:</p>
            <input value='' name='phone' type='tel' required='1'>
            <div class='clear'></div>
			<input type='hidden' name='$subidname' value='{$click->getSubId()}'/>
            <input type='hidden' name='$pxsubname' value='{$click->getSubIdN($pxsubid)}'/>
			<input type='hidden' name='country' value='{$click->getCountry()}'/>
            <button class='ywbbutton' type='submit'>$ordertxt</button>
        </form>
</div>
</div>
</center>
EOT;
    }
}