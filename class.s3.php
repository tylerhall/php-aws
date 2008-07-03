<?PHP
	class S3
	{
		var $_key        = "";
		var $_secret     = "";
		var $_vhost	 = FALSE;
		var $_server     = "http://s3.amazonaws.com";
		var $_tmpserver  = "";				// holds actual vhost, for later expansion
		var $_pathToCurl = "";
		var $_date       = null;
		var $_error      = null;
		var $_debug	 = FALSE;			// turn debugging on/off
		
		function S3($key = null, $secret = null, $vhost = FALSE)
		{
			if($key && $secret)
			{
				$this->_key = $key;
				$this->_secret = $secret;
			}
			
			$this->_vhost = $vhost;
			
			// If the path to curl isn't set, try and auto-detect it
			if($this->_pathToCurl == "")
			{
				$path = trim(shell_exec("which curl"), "\n ");
				if(is_executable($path))
					$this->_pathToCurl = $path;
				else
				{
					$this->_error = "Couldn't auto-detect path to curl";
					return false;
				}
			}
			
			return true;
		}
		
		function setDebugMode($debug = FALSE)
		{
			if ($debug)
				$this->_debug = TRUE;
			else
				$this->_debug = FALSE;
			return TRUE;
		}
		
		function directorySize($bucket, $prefix = "")
		{
			$total = 0;
			$foo = $this->getBucketContents($bucket, $prefix);
			if(!is_array($foo)) return false;
			foreach($foo as $bar)
				if($bar['type'] == "key")
					$total += $bar['size'];
			return $total;
		}

		function recursiveDelete($bucket, $object)
		{
			$items = $this->getBucketContents($bucket, $object);
			foreach($items as $item)
				$this->deleteObject($bucket, $item["name"]);
		}
		
		function deleteObject($bucket, $object)
		{
			if($object[0] != "/" ) $object = "/$object";
			$req = array(	"verb" => "DELETE",
							"md5" => null,
							"type" => null,
							"headers" => null,
							"resource" => "/$bucket" . $object,
						);
			$result = $this->sendRequest($req);
			return !$this->objectExists($bucket, $object);
		}
		
		function putObject($bucket, $object, $filename, $public = null, $disposition = null)
		{
			$info     = pathinfo($filename);
			$basename = $info['basename'];
			$ext      = $info['extension'];

			$type = isset($this->mime_types[$ext]) ? $this->mime_types[$ext] : "application/octet-stream";

			$acl = ($public === true) ? "public-read" : null;

			if(substr($object, 0, 1) != "/" ) $object = "/$object";
			$req = array(	"verb" => "PUT",
							"md5" => null,
							"type" => null,
							"headers" => null,
							"resource" => "/$bucket" . $object,
							"upload" => $filename,
							"type" => $type,
							"disposition" => $disposition,
							"acl" => $acl,
						);
			$this->sendRequest($req);
			
			$info = $this->getObjectInfo($bucket, $object);
			return ($info['hash'] == md5_file($filename));
		}
		
		function getObject($bucket, $object)
		{
			if($object[0] != "/" ) $object = "/$object";
			$req = array(	"verb" => "GET",
							"md5" => null,
							"type" => null,
							"headers" => null,
							"resource" => "/$bucket" . $object,
						);
			$result = $this->sendRequest($req);
			return $result;
		}
		
		function downloadObject($bucket, $object, $saveTo)
		{
			if(substr($object, 0, 1) != "/" ) $object = "/$object";
			$req = array(	"verb" => "GET",
							"md5" => null,
							"type" => null,
							"headers" => null,
							"resource" => "/$bucket" . $object,
							"download" => $saveTo,
						);
			$result = $this->sendRequest($req);
		}
		
		function bucketExists($bucket)
		{
			return in_array($bucket, $this->getBuckets());
		}
		
		function getBuckets()
		{
			$req = array(	"verb" => "GET",
							"md5" => null,
							"type" => null,
							"headers" => null,
							"resource" => "/",
						);
			$result = $this->sendRequest($req);
			
			preg_match_all("@<Name>(.*?)</Name>@", $result, $matches);
			return $matches[1];
		}
		
		function createBucket($bucket)
		{
			$req = array(	"verb" => "PUT",
							"md5" => null,
							"type" => null,
							"headers" => null,
							"resource" => "/$bucket",
						);
			$result = $this->sendRequest($req);
			return $this->isOk($result);
		}
		
		function deleteBucket($bucket, $force = false)
		{
			$req = array(	"verb" => "DELETE",
							"md5" => null,
							"type" => null,
							"headers" => null,
							"resource" => "/$bucket",
						);
			$result = $this->sendRequest($req);
			return $this->isOk($result);
		}
		
		function isOk($result)
		{
			if(preg_match("@<Error>.*?<Message>(.*?)</Message>.*?</Error>@", $result, $matches))
			{
				$this->_error = $matches[1];
				return false;
			}
			else
				return true;
		}
		
		function objectExists($bucket, $object)
		{
			return ($this->getObjectInfo($bucket, $object) !== false);
		}
		
		function getObjectHead($bucket, $object)
		{
			if(substr($object, 0, 1) != "/" ) $object = "/$object";
			$req = array(	"verb" => "HEAD",
							"md5" => null,
							"type" => null,
							"headers" => null,
							"resource" => "/$bucket" . $object,
						);
			$result = $this->sendRequest($req);
			return $result;
		}
		
		function getObjectInfo($bucket, $object)
		{
			$ret = array();
			$object = $this->getBucketContents($bucket, $object);
			if(count($object) == 0) return false;
			if($object[0]['type'] == "prefix") return false;
			return $object[0];
		}
		
		function getBucketContents($bucket, $prefix = null, $delim = null, $marker = null)
		{
			$req = array(	"verb" => "GET",
							"md5" => null,
							"type" => null,
							"headers" => null,
							"resource" => "/$bucket",
						);

			if(strlen($prefix) > 0 && $prefix[0] == "/") $prefix[0] = "";
			$params = array("prefix" => trim($prefix), "marker" => $marker, "delimiter" => $delim);
			$result = $this->sendRequest($req, $params);		
			preg_match_all("@<Contents>(.*?)</Contents>@", $result, $matches);
			$lastKey = "";
			$keys = array();
			foreach($matches[1] as $match)
			{
				preg_match_all('@<(.*?)>(.*?)</\1>@', $match, $keyInfo);

				list($name, $date, $hash, $size) = $keyInfo[2];
				$hash = str_replace("&quot;", "", $hash);
				$keys[] = array("name" => $name, "date" => $date, "hash" => $hash, "size" => $size, "type" => "key");
				if(trim($name) != "") $lastKey = $name;
			}

			
			preg_match_all("@<Prefix>(.*?)</Prefix>@", $result, $matches);
			array_shift($matches[1]);
			foreach($matches[1] as $match)
				$keys[] = array("name" => $match, "type" => "prefix");

			preg_match('@<NextMarker>(.*?)</NextMarker>@', $result, $matches);

			if(isset($matches[1]) && strlen($matches[1]) > 0)
			{
				preg_match('@<NextMarker>(.*?)</NextMarker>@', $result, $matches);
				$keys = array_merge($keys, $this->getBucketContents($bucket, $prefix, $delim, $matches[1]));
			}

			return $keys;
		}
		
		// Get Auth Link
		function getAuthLink($bucket, $object, $timeout = 120)
		{
			if($object[0] <> "/") $object = "/$object";

			if($this->_debug) $this->outputDebug("Auth: Bucket", $bucket);
			if($this->_debug) $this->outputDebug("Auth: Object", $object);
			$req = array(	"verb"		=> "GET",
					"md5"		=> null,
					"type"		=> null,
					"expires"	=> time() + $timeout,
					"resource"	=> "/$bucket$object",
				    );
				    
			$sig = $this->signature($req);
			// If it's not a real vhost, add the amazonaws url part
			if (!$this->_vhost)
				$bucket = "$bucket.s3.amazonaws.com";
				
			if (strpos($object, "?torrent") !== FALSE)
				$authlink = "http://$bucket$object&AWSAccessKeyId=" . $this->_key . "&Expires=" . $req['expires'] . "&Signature=" . $sig;
			else
				$authlink = "http://$bucket$object?AWSAccessKeyId=" . $this->_key . "&Expires=" . $req['expires'] . "&Signature=" . $sig;
			if ($this->_debug) $this->outputDebug("AuthLink:",$authlink);
			return $authlink;
			
		}
				
		// Return Location of specified item
		function getLocation($bucket, $prefix = null, $delim = null, $marker = null)
		{
			if($prefix[0] <> "/") $prefix = "/$prefix";
			$req = array(	"verb" => "GET",
						"md5" => null,
						"type" => null,
						"headers" => null,
						"resource" => "/$bucket" . $prefix,
						"location" => TRUE,
					);
			$params = array("location" => "");
			$result = $this->sendRequest($req, $params);		
			if ($this->_debug) $this->outputDebug("Location Req-Result", htmlentities($result));
			preg_match('@<Location.*>(.*)</LocationConstraint>@', $result, $matches);
			if ($this->_debug) $this->outputDebug("Location: Matched Strings", $matches);
			if(!isset($matches[1]))
				$matches[1] = "US";
				
			return $matches[1];
		}
		
		function sendRequest($req, $params = null)
		{
			if(isset($req['resource']))
			{
				$req['resource'] = urlencode($req['resource']);
				$req['resource'] = str_replace("%2F", "/", $req['resource']);
			}

			if ($this->_debug) $this->outputDebug("$req in sendRequest", $req);
			if ($req['resource'] <> "/") {		// not in 'root dir'
				$sru_req = explode("/", $req['resource']);
				if ($this->_debug) $this->outputDebug("SendRequest: Split Resource", $sru_req);
				// check whether the user wants to use an alternate hostname like downloads.whatever.tld
				// TODO TODO TODO
				// check whether this is actually needed
				// TODO TODO TODO
				if ($this->_vhost) {
				 	$this->_tmpserver = "http://" . $sru_req[1];
				} else {
					$this->_tmpserver = "http://" . $sru_req[1] . ".s3.amazonaws.com";
				}
				$sru_tmp = strpos($req['resource'], "/", 1);
				if ($sru_tmp === FALSE) {
					$req['resource'] = "/";
					// Virtual Hosts need a Host: header
					$req['headers']['Host'] = $sru_req[1];
				} else {
					$req['resource'] = substr($req['resource'], $sru_tmp);
					// Virtual Hosts need a Host: header
					$req['headers']['Host'] = $sru_req[1] . ".s3.amazonaws.com";
				}
			} else {
				$this->_tmpserver = $this->_server;
			}
			
			if ($this->_debug) $this->outputDebug("SendRequest: Request struct", $req);
			$sig = $this->signature($req);
			
			$args = array();
			$args[] = array("-H", "Date: " . $this->_date);
			$args[] = array("-H", "Authorization: AWS {$this->_key}:$sig");
			
			if(isset($req['acl'])) $args[] = array("-H", "x-amz-acl: " . $req['acl']);
			if(isset($req['type'])) $args[] = array("-H", "Content-Type: " . $req['type']);
			if(isset($req['md5'])) $args[] = array("-H", "Content-Md5: " . $req['md5']);
			if(isset($req['disposition'])) $args[] = array("-H", 'Content-Disposition: attachment; filename=\"' . $req['disposition'] . '\"');
			if(isset($req['upload'])) $args[] = array("-T", $req['upload']);

			if(is_array($req['headers']))
				foreach($req['headers'] as $key => $val)
					$args[] = array("-H", "$key: $val");

			if(strtolower($req['verb']) != "head")
				$args[] = array("-X", $req['verb']);

			// always make requests follow Temporary Redirects
			$curl = $this->_pathToCurl . " -s -L ";

			foreach($args as $arg)
			{
				list($key, $val) = $arg;
				$curl .= "$key \"$val\" ";
			}
			
			$curl .= '"' . $this->_tmpserver . $req['resource'];
			
			if(is_array($params))
			{
				$curl .= "?";
				foreach($params as $key => $val) {
					$curl .= "$key=" . urlencode($val) . "&";
					if($key == "location")
						$curl = substr($curl, 0, -1);
				}
			}
			
			// remove trailing "&"
			if (substr($curl, -1) == "&") $curl = substr($curl, 0, -1);
			$curl .= '"';
			
			if(strtolower($req['verb']) == "head")
				$curl .= " -I ";

			if(isset($req['download'])) $curl .= ' -o "' . $req['download'] . '"';
			if ($this->_debug) $this->outputDebug("SendRequest: Curl Call", $curl);
			return `$curl`;
		}
		
		function signature($req)
		{
			// Format and sort x-amz headers
			$arrHeaders = array();
			if(!is_array($req['headers'])) $req['headers'] = array();
			if(isset($req['acl'])) $req['headers']["x-amz-acl"] = $req['acl'];
			foreach($req['headers'] as $key => $val)
			{
				$key = trim(strtolower($key));
				$val = trim($val);
				if(strpos($key, "x-amz") !== false)
				{
					if(isset($arrHeaders[$key]))
						$arrHeaders[$key] .= ",$val";
					else
						$arrHeaders[$key] = "$key:$val";
				}
			}
			ksort($arrHeaders);

			$headers = implode("\n", $arrHeaders);
			if(!empty($headers)) $headers = "\n$headers";
			
			// Construct "bucket" name for virtual Host requests
			if (isset($req['headers']['Host'])) {
				// for virtual hosts it's "/" . Vhost . RequestPath
				if(strpos($req['headers']['Host'], "s3.amazonaws.com") === FALSE) {
					// private vhost like downloads.mydomain.tld
					$my_resource = "/" . $req['headers']['Host'] . $req['resource'];
				} else {
					// vhost.s3.amazonaws.com -> strip .s3.amazonaws.com for Signature calculation
					$my_resource = "/" . str_replace(".s3.amazonaws.com", "", $req['headers']['Host']) . $req['resource'];
				}
			} else {
				// else it's just a request path
				$my_resource = $req['resource'];
			}
			if(isset($req['date']))
				$this->_date = gmdate("D, d M Y H:i:s T", strtotime($req['date']));
			else
				if(isset($req['expires']))
					$this->_date = $req['expires'];
				else
					$this->_date = gmdate("D, d M Y H:i:s T");
				
			// Location Hack
			if(isset($req["location"]))
				$my_resource .= "?location";
				
			// Build and sign the string
			$str  = strtoupper($req['verb']) . "\n" . $req['md5']  . "\n" . $req['type'] . "\n" . $this->_date . $headers . "\n" . $my_resource;
			if ($this->_debug) $this->outputDebug("Signature: Calcbase", $str);
			$sha1 = $this->hasher($str);
			$sig  = $this->base64($sha1);
			return $sig;
		}
		
		function hasher($data)
		{
			// Algorithm adapted (stolen) from http://pear.php.net/package/Crypt_HMAC/)
			$key = $this->_secret;
			if(strlen($key) > 64)
				$key = pack("H40", sha1($key));
			if(strlen($key) < 64)
				$key = str_pad($key, 64, chr(0));
			$ipad = (substr($key, 0, 64) ^ str_repeat(chr(0x36), 64));
			$opad = (substr($key, 0, 64) ^ str_repeat(chr(0x5C), 64));
			return sha1($opad . pack("H40", sha1($ipad . $data)));
		}

		function base64($str)
		{
			$ret = "";
			for($i = 0; $i < strlen($str); $i += 2)
				$ret .= chr(hexdec(substr($str, $i, 2)));
			return base64_encode($ret);
		}
		
		function sortKeys($keys, $first = null)
		{	
			if(is_null($first))
				usort($keys, create_function('$a,$b', 'return strcmp($a["name"], $b["name"]);'));
			elseif($first == "key")
				usort($keys, create_function('$a,$b', '
					if($a["type"] == $b["type"]) return strcmp($a["name"], $b["name"]);
					if($a["type"] == "key") return -1;
					return 1;
				'));
			elseif($first == "prefix")
				usort($keys, create_function('$a,$b', '
					if($a["type"] == $b["type"]) return strcmp($a["name"], $b["name"]);
					if($a["type"] == "prefix") return -1;
					return 1;
				'));
			return $keys;
		}

		function outputDebug($title = "", $var)
		{
			echo "Where: $title<br /><br />\n";
			echo "<pre>";
			var_dump($var);
			echo "</pre>";
		}
		
		var $mime_types = array("323" => "text/h323", "acx" => "application/internet-property-stream", "ai" => "application/postscript", "aif" => "audio/x-aiff", "aifc" => "audio/x-aiff", "aiff" => "audio/x-aiff",
							"asf" => "video/x-ms-asf", "asr" => "video/x-ms-asf", "asx" => "video/x-ms-asf", "au" => "audio/basic", "avi" => "video/quicktime", "axs" => "application/olescript", "bas" => "text/plain", "bcpio" => "application/x-bcpio", "bin" => "application/octet-stream", "bmp" => "image/bmp",
							"c" => "text/plain", "cat" => "application/vnd.ms-pkiseccat", "cdf" => "application/x-cdf", "cer" => "application/x-x509-ca-cert", "class" => "application/octet-stream", "clp" => "application/x-msclip", "cmx" => "image/x-cmx", "cod" => "image/cis-cod", "cpio" => "application/x-cpio", "crd" => "application/x-mscardfile",
							"crl" => "application/pkix-crl", "crt" => "application/x-x509-ca-cert", "csh" => "application/x-csh", "css" => "text/css", "dcr" => "application/x-director", "der" => "application/x-x509-ca-cert", "dir" => "application/x-director", "dll" => "application/x-msdownload", "dms" => "application/octet-stream", "doc" => "application/msword",
							"dot" => "application/msword", "dvi" => "application/x-dvi", "dxr" => "application/x-director", "eps" => "application/postscript", "etx" => "text/x-setext", "evy" => "application/envoy", "exe" => "application/octet-stream", "fif" => "application/fractals", "flr" => "x-world/x-vrml", "gif" => "image/gif",
							"gtar" => "application/x-gtar", "gz" => "application/x-gzip", "h" => "text/plain", "hdf" => "application/x-hdf", "hlp" => "application/winhlp", "hqx" => "application/mac-binhex40", "hta" => "application/hta", "htc" => "text/x-component", "htm" => "text/html", "html" => "text/html",
							"htt" => "text/webviewhtml", "ico" => "image/x-icon", "ief" => "image/ief", "iii" => "application/x-iphone", "ins" => "application/x-internet-signup", "isp" => "application/x-internet-signup", "jfif" => "image/pipeg", "jpe" => "image/jpeg", "jpeg" => "image/jpeg", "jpg" => "image/jpeg",
							"js" => "application/x-javascript", "latex" => "application/x-latex", "lha" => "application/octet-stream", "lsf" => "video/x-la-asf", "lsx" => "video/x-la-asf", "lzh" => "application/octet-stream", "m13" => "application/x-msmediaview", "m14" => "application/x-msmediaview", "m3u" => "audio/x-mpegurl", "man" => "application/x-troff-man",
							"mdb" => "application/x-msaccess", "me" => "application/x-troff-me", "mht" => "message/rfc822", "mhtml" => "message/rfc822", "mid" => "audio/mid", "mny" => "application/x-msmoney", "mov" => "video/quicktime", "movie" => "video/x-sgi-movie", "mp2" => "video/mpeg", "mp3" => "audio/mpeg",
							"mpa" => "video/mpeg", "mpe" => "video/mpeg", "mpeg" => "video/mpeg", "mpg" => "video/mpeg", "mpp" => "application/vnd.ms-project", "mpv2" => "video/mpeg", "ms" => "application/x-troff-ms", "mvb" => "application/x-msmediaview", "nws" => "message/rfc822", "oda" => "application/oda",
							"p10" => "application/pkcs10", "p12" => "application/x-pkcs12", "p7b" => "application/x-pkcs7-certificates", "p7c" => "application/x-pkcs7-mime", "p7m" => "application/x-pkcs7-mime", "p7r" => "application/x-pkcs7-certreqresp", "p7s" => "application/x-pkcs7-signature", "pbm" => "image/x-portable-bitmap", "pdf" => "application/pdf", "pfx" => "application/x-pkcs12",
							"pgm" => "image/x-portable-graymap", "pko" => "application/ynd.ms-pkipko", "pma" => "application/x-perfmon", "pmc" => "application/x-perfmon", "pml" => "application/x-perfmon", "pmr" => "application/x-perfmon", "pmw" => "application/x-perfmon", "png" => "image/png", "pnm" => "image/x-portable-anymap", "pot" => "application/vnd.ms-powerpoint", "ppm" => "image/x-portable-pixmap",
							"pps" => "application/vnd.ms-powerpoint", "ppt" => "application/vnd.ms-powerpoint", "prf" => "application/pics-rules", "ps" => "application/postscript", "pub" => "application/x-mspublisher", "qt" => "video/quicktime", "ra" => "audio/x-pn-realaudio", "ram" => "audio/x-pn-realaudio", "ras" => "image/x-cmu-raster", "rgb" => "image/x-rgb",
							"rmi" => "audio/mid", "roff" => "application/x-troff", "rtf" => "application/rtf", "rtx" => "text/richtext", "scd" => "application/x-msschedule", "sct" => "text/scriptlet", "setpay" => "application/set-payment-initiation", "setreg" => "application/set-registration-initiation", "sh" => "application/x-sh", "shar" => "application/x-shar",
							"sit" => "application/x-stuffit", "snd" => "audio/basic", "spc" => "application/x-pkcs7-certificates", "spl" => "application/futuresplash", "src" => "application/x-wais-source", "sst" => "application/vnd.ms-pkicertstore", "stl" => "application/vnd.ms-pkistl", "stm" => "text/html", "svg" => "image/svg+xml", "sv4cpio" => "application/x-sv4cpio",
							"sv4crc" => "application/x-sv4crc", "t" => "application/x-troff", "tar" => "application/x-tar", "tcl" => "application/x-tcl", "tex" => "application/x-tex", "texi" => "application/x-texinfo", "texinfo" => "application/x-texinfo", "tgz" => "application/x-compressed", "tif" => "image/tiff", "tiff" => "image/tiff",
							"tr" => "application/x-troff", "trm" => "application/x-msterminal", "tsv" => "text/tab-separated-values", "txt" => "text/plain", "uls" => "text/iuls", "ustar" => "application/x-ustar", "vcf" => "text/x-vcard", "vrml" => "x-world/x-vrml", "wav" => "audio/x-wav", "wcm" => "application/vnd.ms-works",
							"wdb" => "application/vnd.ms-works", "wks" => "application/vnd.ms-works", "wmf" => "application/x-msmetafile", "wps" => "application/vnd.ms-works", "wri" => "application/x-mswrite", "wrl" => "x-world/x-vrml", "wrz" => "x-world/x-vrml", "xaf" => "x-world/x-vrml", "xbm" => "image/x-xbitmap", "xla" => "application/vnd.ms-excel",
							"xlc" => "application/vnd.ms-excel", "xlm" => "application/vnd.ms-excel", "xls" => "application/vnd.ms-excel", "xlt" => "application/vnd.ms-excel", "xlw" => "application/vnd.ms-excel", "xof" => "x-world/x-vrml", "xpm" => "image/x-xpixmap", "xwd" => "image/x-xwindowdump", "z" => "application/x-compress", "zip" => "application/zip");
	}
