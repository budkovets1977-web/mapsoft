<?php
 header('Content-Type: application/json; charset=utf-8'); $res = safe_json_decode(file_get_contents('php://input')); echo json_encode([ 'cms' => crc32($res->data) ]); 