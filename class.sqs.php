<?PHP
	class SQS
	{
		var $key;
		var $secret;
		var $amazon_url = "http://queue.amazonaws.com/";
		var $version = "2006-04-01";
		var $queue_url;

		function SQS($key, $secret, $queue_url = null)
		{
			$this->key    = $key;
			$this->secret = $secret;
			$this->queue_url = $queue_url;
		}

		function createQueue($queue_name, $default_timeout = 30)
		{
			if(!is_int($default_timeout)) $default_timeout = 30;
			$params = array("QueueName" => $queue_name, "DefaultVisibilityTimeout" => $default_timeout);
			$xml = $this->go("CreateQueue", $params);
			if($xml === false) return false;

			return strval($xml->QueueUrl);
		}

		function listQueues($queue_name_prefix = "")
		{
			$params = ($queue_name_prefix == "") ? array() : array("QueueNamePrefix" => $queue_name_prefix);
			$xml = $this->go("ListQueues", $params);
			if($xml === false) return false;

			$out = array();
			foreach($xml->QueueUrl as $url)
				$out[] = strval($url);
			return $out;
		}

		function deleteQueue($queue_url = null)
		{
			if(!isset($queue_url)) $queue_url = $this->queue_url;
			$xml = $this->go("DeleteQueue", null, $queue_url);
			return $xml ? true : false;
		}

		function sendMessage($message_body, $queue_url = null)
		{
			if(!isset($queue_url)) $queue_url = $this->queue_url;
			$params = array("MessageBody" => $message_body);
			$xml = $this->go("SendMessage", $params, $queue_url);
			if($xml === false) return false;

			return strval($xml->MessageId);
		}

		function receiveMessage($number = 1, $timeout = null, $queue_url = null)
		{
			if(!isset($queue_url)) $queue_url = $this->queue_url;

			$number = intval($number);
			if($number < 1) $number = 1;
			if($number > 256) $number = 256;

			$params = array();
			$params['NumberOfMessages'] = $number;
			if(isset($timeout)) $params['VisibilityTimeout'] = intval($timeout);

			$xml = $this->go("ReceiveMessage", $params, $queue_url);
			if($xml === false) return $false;

			$out = array();
			foreach($xml->Message as $m)
				$out[] = array("MessageId" => strval($m->MessageId), "MessageBody" => urldecode(strval($m->MessageBody)));
			return (count($out) == 1) ? $out[0] : $out;
		}

		function peekMessage($message_id, $queue_url = null)
		{
			if(!isset($queue_url)) $queue_url = $this->queue_url;
			$params = array("MessageId" => $message_id);
			$xml = $this->go("PeekMessage", $params, $queue_url);
			if($xml === false) return false;

			$out = array();
			foreach($xml->Message as $m)
				$out[] = array("MessageId" => strval($m->MessageId), "MessageBody" => urldecode(strval($m->MessageBody)));
			return (count($out) == 1) ? $out[0] : $out;
		}

		function deleteMessage($message_id, $queue_url = null)
		{
			if(!isset($queue_url)) $queue_url = $this->queue_url;
			$params = array("MessageId" => $message_id);
			$xml = $this->go("DeleteMessage", $params, $queue_url);
			return ($xml === false) ? false : true;
		}

		function setTimeout($timeout, $queue_url = null)
		{
			if(!isset($queue_url)) $queue_url = $this->queue_url;
			if(!is_int($timeout)) $timeout = 30;
			$params = array("VisibilityTimeout" => $timeout);
			$xml = $this->go("SetVisibilityTimeout", $params, $queue_url);
			return ($xml === false) ? false : true;
		}

		function getTimeout($queue_url = null)
		{
			if(!isset($queue_url)) $queue_url = $this->queue_url;
			$xml = $this->go("GetVisibilityTimeout", $params, $queue_url);
			return ($xml === false) ? false : strval($xml->VisibilityTimeout);
		}

		function setQueue($queue_url)
		{
			$this->queue_url = $queue_url;
		}

		function go($action, $params, $url = null)
		{
			if(!is_array($params)) $params = array();
			$params['Action'] = $action;
			$params['Version'] = $this->version;
			$params['AWSAccessKeyId'] = $this->key;
			$params['Timestamp'] = gmdate('Y-m-d\TH:i:s\Z');

			$string_to_sign = $params['Action'] . $params['Timestamp'];
			$params['Signature'] = $this->hexTo64($this->hasher($string_to_sign));

			if(!isset($url)) $url = $this->amazon_url;

			$url .= "?";
			foreach($params as $key => $val)
				$url .= "&$key=" . urlencode($val);

			$xmlstr = $this->geturl($url);
			$xml = new SimpleXMLElement($xmlstr);
			if(isset($xml->Errors))
				return false;
			else
				return $xml;
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

		function hexTo64($str)
		{
			$raw = "";
			for($i = 0; $i < strlen($str); $i += 2)
				$raw .= chr(hexdec(substr($str, $i, 2)));
			return base64_encode($raw);
		}
	}
?>