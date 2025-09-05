<?php

$query = $useragent = 'Opera/9.80 (J2ME/MIDP; Opera Mini/4.2.14912/870; U; id) Presto/2.4.15';
$ch    = curl_init('');
curl_setopt($ch, CURLOPT_URL, 'http://www.google.com/search?hl=en&tbo=d&site=&source=hp&q=' . $query);
curl_setopt($ch, CURLOPT_USERAGENT, $useragent); // set user agent
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
echo $output = curl_exec($ch);
curl_close($ch);
