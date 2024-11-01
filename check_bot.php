<?php
/*
FileName: checkbot.php
Description: validates the api key from botscout
Version: 0.0.9
Author: Jake Helbig
Author URI: http://www.jakehelbig.com
License: GPLv3
    Version 3, 29 June 2007
    Copyright (C) 2007 Free Software Foundation, Inc. <http://fsf.org/>
    Everyone is permitted to copy and distribute verbatim copies of this license document, but changing it is not allowed.
*/
$url = urldecode($_GET['url']);
if($url != ""){
    echo file_get_contents($url);
}