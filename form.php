<?php
namespace Macros;

use Traffic\Model\BaseStream;
use Traffic\RawClick;
use Traffic\Macros\AbstractClickMacro;

/*
  Macro for Keitaro Tracker(https://yellowweb.top/keitaro) 
  This macro adds an order form to your prelandings, for example:
  {_form:order.php,sub1,aabbaa,Our Great Product,Name,Phone,Order Now!}
  
  First parameter should be the name of your php script that sends leads to your affiliate program.
  Second parameter is the name of the sub-mark that will have Keitaro's unique click id as the value.
  Third parameter is the color of the form in hex-code
  Fourth parameter is the header of the form
  Fifth and sixth parameters are the labels for name and phone fields
  Seventh parameter is the label for submit button.
  
  !!Also this script replaces all the links in your prelanding so that they scroll the page to this form.
  This script also adds country code into the form's hidden input named 'country'.
  Author: Yellow Web (http://yellowweb.top)
 */
class form extends AbstractClickMacro
{
    public function process(BaseStream $stream, RawClick $click, $orderphp,$subidsub,$color,$header,$nametxt,$phonetxt,$ordertxt)
    {
		return <<<EOT
<script>
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
</script>

<center>
<iframe id="ywbplform" srcdoc="

<!DOCTYPE html>
<html>

<head>
    <meta charset='UTF-8'>
    <style>
body {
    margin:0px;
    padding:0px;
    border:1px solid #$color;
    -webkit-border-radius: 5px;-moz-border-radius: 5px;border-radius: 5px; }
.wrap {margin:auto;width:100%;height:100%;}
p{
    margin:0px 0px 5px 0px;
    padding-left:5px;
    font-size:15px;
    font-family: 'PT Sans', sans-serif;}
h1{
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
.order_form {
    padding-left: 16px;
    padding-right: 16px;
    margin: 0px;
    font-family: 'PT Sans', sans-serif;}
.order_form input{
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
.order_form .clear{clear: both;}
.ifr_button{
    display: block;margin: 20px auto; 
    border:none; outline:none;color: #fff;font-size:24px;
    font-family: 'PT Sans', sans-serif;
    text-decoration: none;text-align: center;width: 180px;height: 51px;
    line-height: 48px;background: #$color;font-weight:600;
    text-transform: uppercase;
    -webkit-border-radius: 30px;-moz-border-radius: 3px;border-radius: 30px;cursor: pointer;}	
    </style>
	</head>
<body>
    <div class='wrap'>
        <h1>$header</h1>
        <form action='$orderphp' method='post' class='order_form'>
            <p>$nametxt:</p>
            <input class='form_input' value='' name='name' type='text' required='1'>
            <p>$phonetxt:</p>
            <input class='form_input' value='' name='phone' type='text' required='1'>
            <div class='clear'></div>
            <input type='hidden' name='$subidsub' value='{$click->getSubId()}'/>
			<input type='hidden' name='country' value='{$click->getCountry()}'/>
            <button class='ifr_button' type='submit'>$ordertxt</button>
        </form>
    </div>
</body>
</html>" height="320" scrolling="no" frameborder="0"></iframe>
</center>
EOT;
    }
}