<?php

$LocationArray = json_decode(file_get_contents('http://ip-get-geolocation.com/api/json/35.188.125.133'), true);

echo $LocationArray['country'];
echo $LocationArray['city'];
echo $LocationArray['region'];
echo $LocationArray['timezone'];
