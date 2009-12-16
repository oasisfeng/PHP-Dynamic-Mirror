<?php
$mirror = "the.site-you-want-to-mirror.com";		// Change this value to the site you want to mirror.

$req = $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . " HTTP/1.0\r\n";
$length = 0;
foreach ($_SERVER as $k => $v) {
	if (substr($k, 0, 5) == "HTTP_") {
		$k = str_replace('_', ' ', substr($k, 5));
		$k = str_replace(' ', '-', ucwords(strtolower($k)));
		if ($k == "Host")
			$v = $mirror;						# Alter "Host" header to mirrored server
		if ($k == "Accept-Encoding")
			$v = "identity;q=1.0, *;q=0";		# Alter "Accept-Encoding" header to accept unencoded content only
		if ($k == "Keep-Alive")
			continue;							# Drop "Keep-Alive" header
		if ($k == "Connection" && $v == "keep-alive")
			$v = "close";						# Alter value of "Connection" header from "keep-alive" to "close"
		$req .= $k . ": " . $v . "\r\n";
	}
}
$body = @file_get_contents('php://input');
$req .= "Content-Type: " . $_SERVER['CONTENT_TYPE'] . "\r\n";
$req .= "Content-Length: " . strlen($body) . "\r\n";
$req .= "\r\n";
$req .= $body;

#print $req;

$fp = fsockopen($mirror, 80, $errno, $errmsg, 30);
if (!$fp) {
	print "HTTP/1.0 502 Failed to connect remote server\r\n";
	print "Content-Type: text/html\r\n\r\n";
	print "<html><body>Failed to connect to $mirror due to:<br>[$errno] $errstr</body></html>";
	exit;
}

fwrite($fp, $req);

$headers_processed = 0;
$reponse = '';
while (!feof($fp)) {
	$r = fread($fp, 8192);
	if (!$headers_processed) {
		$response .= $r;
		$nlnl = strpos($response, "\r\n\r\n");
		$add = 4;
		if (!$nlnl) {
			$nlnl = strpos($response, "\n\n");
			$add = 2;
		}
		if (!$nlnl)
			continue;
		$headers = substr($response, 0, $nlnl);
		$cookies = 'Set-Cookie: ';
		if (preg_match_all('/^(.*?)(\r?\n|$)/ims', $headers, $matches))
			for ($i = 0; $i < count($matches[0]); ++$i) {
				$ct = $matches[1][$i];
#				if (substr($ct, 0, 12) == "Set-Cookie: ") {
#					$cookies .= substr($ct, 12) . ',';
#					header($cookies);
#				} else
					header($ct, false);
#				print '>>' . $ct . "\r\n";
			}
		print substr($response, $nlnl + $add);
		$headers_processed = 1;
	} else
		print $r;
}
fclose ($fp);
?>
