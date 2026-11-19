<?php
$hdr = getallheaders();
$ua = isset($hdr['User-Agent']) ? $hdr['User-Agent'] : '';
$enc = isset($hdr['Accept-Encoding']) ? $hdr['Accept-Encoding'] : '';
$m = isset($hdr['If-Modified-Since']) ? $hdr['If-Modified-Since'] : '-';
header('Content-Type: text/plain; charset=utf-8');
echo "encoding: $enc\n";
echo "ims: $m\n";
echo "ua: $ua\n";