<?PHP
	class AWIS
	{
		var $_key        = "";
		var $_secret     = "";
		var $_server     = "http://awis.amazonaws.com";
		var $_pathToCurl = "";
		var $_date       = null;
		var $_error      = null;
		var $_version    = "2005-07-11";
		
		function AWIS($key = null, $secret = null)
		{
			if($key && $secret)
			{
				$this->_key = $key;
				$this->_secret = $secret;
			}
			
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
	
		function urlInfo($url, $responseGroup)
		{
			$req = array( "Url" => $url, "ResponseGroup" => $responseGroup );
			$result = $this->go("UrlInfo", $req);
			return $result->Response->UrlInfoResult->Alexa;
		}
		

		function trafficHistory($url, $range=30, $start="")
		{
			
			if (!$start) {
				$start = date("Ymd", mktime(0,0,0,date("m"),date("d")-31,date("Y")));
			}
			$req = array( "Url" => $url, "ResponseGroup" => "History", "Start" => $start, "Range" => $range );
			$result = $this->go("TrafficHistory", $req);
			return $result->Response->TrafficHistoryResult->Alexa->TrafficHistory;
		}
		
		function sitesLinkingIn($url, $start=0, $count=10)
		{
			$req = array( "Url" => $url, "ResponseGroup" => "SitesLinkingIn", "Start" => $start, "Count" => $count );
			$result = $this->go("SitesLinkingIn", $req);
			return $result->Response->SitesLinkingInResult->Alexa->SitesLinkingIn;
		}
		
		function go($action, $params, $url = null)
		{
			if(!is_array($params)) $params = array();
			$params['Action'] = $action;
			$params['Timestamp'] = gmdate("Y-m-d\TH:i:s.\\0\\0\\0\\Z", time()); 
			$params['Version'] = $this->_version;
			$params['AWSAccessKeyId'] = $this->_key;
			$params['Signature'] = $this->calculate_RFC2104HMAC($params['Action'] . $params['Timestamp'],$this->_secret);
			if(!isset($url)) $url = $this->_server;

			$url .= "?";
			foreach($params as $key => $val)
				$url .= "$key=" . urlencode($val)."&";
			$xmlstr = $this->geturl($url);
			$xmlstr = preg_replace("/<(\/?)aws:/","<$1",$xmlstr);
			$xml =  simplexml_load_string($xmlstr);
			if(isset($xml->Errors))
				return false;
			else
				return $xml;
		}

		function calculate_RFC2104HMAC($data, $key) {
			return base64_encode (
				pack("H*", sha1((str_pad($key, 64, chr(0x00))
				^(str_repeat(chr(0x5c), 64))) .
				pack("H*", sha1((str_pad($key, 64, chr(0x00))
				^(str_repeat(chr(0x36), 64))) . $data))))
				);
		}
		
		function geturl($url, $username = "", $password = "")
		{
			if(function_exists("curl_init"))
			{
				$ch = curl_init();
				if(!empty($username) && !empty($password)) curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' .  base64_encode("$username:$password")));
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
				$html = curl_exec($ch);
				curl_close($ch);
				return $html;
			}
			elseif(ini_get("allow_url_fopen") == true)
			{
				if(!empty($username) && !empty($password))
				{
					$url = str_replace("http://", "http://$username:$password@", $url);
					$url = str_replace("https://", "https://$username:$password@", $url);
				}
				$html = file_get_contents($url);
				return $html;
			}
			else
			{
				// Cannot open url. Either install curl-php or set allow_url_fopen = true in php.ini
				return false;
			}
		}

	}