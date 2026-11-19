<?php
 namespace Http\Message; use Psr\Http\Message\StreamInterface; interface StreamFactory { public function createStream($body = null); } 