<?php
 require_once(__DIR__ . '/../Dicom.php'); foreach (Dicom\DICTIONARY as $k => $v) { $val = strtoupper($v[1]); echo "'$val' => '$k',\n"; }