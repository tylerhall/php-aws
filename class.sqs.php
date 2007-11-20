<?PHP

	class SQS
	{
		
		var $_key        = "";
		var $_secret     = "";
		var $_server     = "http://queue.amazonaws.com/";
		var $_pathToCurl = "";
		var $_date       = null;
		var $_error      = null;
				
		var $queue_url;

		function SQS($key, $secret, $queue_url = null)
		{
			$this->_key    = $key;
			$this->_secret = $secret;
			$this->queue_url = $queue_url;
			
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
			return $out;
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
		
		function clearQueue($limit = 100, $queue_url)
		{
			$m = $this->receiveMessage($limit, null, $queue_url);
			foreach($m as $n)
				$this->deleteMessage($n['MessageId'], $queue_url);
		}

		function setTimeout($timeout, $queue_url = null)
		{
			$timeout = intval($timeout);
			if(!isset($queue_url)) $queue_url = $this->queue_url;
			if(!is_int($timeout)) $timeout = 30;
			$params = array("Attribute" => "VisibilityTimeout", "Value" => $timeout);
			$xml = $this->go("SetQueueAttributes", $params, $queue_url);
			return ($xml === false) ? false : true;
		}

		function getTimeout($queue_url = null)
		{
			if(!isset($queue_url)) $queue_url = $this->queue_url;
			$params = array("Attribute" => "VisibilityTimeout");
			$xml = $this->go("GetQueueAttributes", $params, $queue_url);
			return ($xml === false) ? false : strval($xml->AttributedValue->Value);
		}
		function getSize($queue_url = null)
		{
			if(!isset($queue_url)) $queue_url = $this->queue_url;
			$params = array("Attribute" => "ApproximateNumberOfMessages");			
			$xml = $this->go("GetQueueAttributes", $params, $queue_url);
			return ($xml === false) ? false : strval($xml->AttributedValue->Value);
		}
		function setQueue($queue_url)
		{
			$this->queue_url = $queue_url;
		}

		function go($action, $params, $url = null)
		{
			$params['Action'] = $action;
			if(!isset($url)) $url = $this->_server;
			
			$params['AWSAccessKeyId'] = $this->_key;
			$params['SignatureVersion'] = 1;
			$params['Timestamp'] = gmdate("Y-m-d\TH:i:s\Z");
			$params['Version'] = "2007-05-01";
			uksort($params, "strnatcasecmp");

			$toSign = "";
			foreach($params as $key => $val)
				$toSign .= $key . $val;
			$sha1 = $this->hasher($toSign);
			$sig  = $this->base64($sha1);
			$params['Signature'] = $sig;

			$curl = "{$this->_pathToCurl} -s \"{$url}?";
			reset($params);
			foreach($params as $key => $val)
				$curl .= "$key=" . urlencode($val) . "&";
			$curl .= '"';

			$xmlstr = `$curl`;

			$xml = new SimpleXMLElement($xmlstr);
			if(isset($xml->Errors))
				return false;
			else
				return $xml;
			
			
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

	}
?>