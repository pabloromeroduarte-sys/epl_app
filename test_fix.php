<?php
$str = "MartÃÂ­nez";
echo "ORIGINAL: $str\n";
echo "DECODED: " . mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1') . "\n";
echo "DECODED 2: " . utf8_decode($str) . "\n";
