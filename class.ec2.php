<?PHP
	require_once 'Crypt/HMAC.php';

	class EC2
	{
		var $_key        = "";
		var $_secret     = "";
		var $_server     = "http://ec2.amazonaws.com";
		var $_pathToCurl = "curl";
		var $_hasher     = null;
		var $_date       = null;
		var $_error      = null;
		
		function __construct($key = null, $secret = null)
		{
			if($key && $secret)
			{
				$this->_key = $key;
				$this->_secret = $secret;
			}
			$this->_hasher = new Crypt_HMAC($this->_secret, "sha1");
		}

		function getImages($ownerId = null)
		{
			$params = array("Action" => "DescribeImages");
			if(isset($ownerId)) $params['Owner.1'] = $ownerId;
			$xmlstr = $this->sendRequest($params);
			$xml = new SimpleXMLElement($xmlstr);
			
			$images = array();
			foreach($xml->imagesSet->item as $item)
				$images[(string) $item->imageId] =
				            array("location" => (string) $item->imageLocation,
				                  "state"    => (string) $item->imageState,
				                  "owner"    => (string) $item->imageOwnerId,
				                  "public"   => (string) $item->isPublic);
			return $images;
		}
		
		function getInstances()
		{
			$params = array("Action" => "DescribeInstances");
			$xmlstr = $this->sendRequest($params);
			$xml = new SimpleXMLElement($xmlstr);
			
			$instances = array();
			foreach($xml->reservationSet->item as $item)
				$instances[(string) $item->instancesSet->item->instanceId] =
				               array("imageId" => (string) $item->instancesSet->item->imageId,
				                     "state"   => (string) $item->instancesSet->item->instanceState->name,
				                     "dns"     => (string) $item->instancesSet->item->dnsName);
			return $instances;
		}
		
		function runInstances($imageId, $min = 1, $max = 1, $keyName = "gsg-keypair")
		{
			$params = array("Action" => "RunInstances",
			                "ImageId" => $imageId,
			                "MinCount" => $min,
			                "MaxCount" => $max,
			                "KeyName" => $keyName);
			
			$xmlstr = $this->sendRequest($params);
			$xml = new SimpleXMLElement($xmlstr);
			
			$instances = array();
			foreach($xml->instancesSet->item as $item)
				$instances[(string) $item->instanceId] =
				               array("imageId" => (string) $item->imageId,
				                     "state"   => (string) $item->instanceState->name,
				                     "dns"     => (string) $item->dnsName);
			return $instances;
		}
		
		function getKeys()
		{
			$params = array("Action" => "DescribeKeyPairs");
			$xmlstr = $this->sendRequest($params);
			$xml = new SimpleXMLElement($xmlstr);
			
			$keys = array();
			foreach($xml->keySet->item as $item)
				$keys[] = array("name" => (string) $item->keyName, "fingerprint" => (string) $item->keyFingerprint);
			return $keys;
		}
		
		function terminateInstances($toKill)
		{
			$params = array("Action" => "TerminateInstances");
			$toKill = explode(",", $toKill);
			$i = 0;
			foreach($toKill as $id)
				$params['InstanceId.' . ++$i] = $id;
			$xmlstr = $this->sendRequest($params);
			$xml = new SimpleXMLElement($xmlstr);
			
			$instances = array();
			foreach($xml->instancesSet->item as $item)
				$instances[(string) $item->instanceId] =
				               array("shutdownState" => (string) $item->shutdownState,
				                     "previousState" => (string) $item->previousState);
			return $instances;
		}

		function sendRequest($params)
		{
			$params['AWSAccessKeyId'] = $this->_key;
			$params['SignatureVersion'] = 1;
			$params['Timestamp'] = gmdate("Y-m-d\TH:i:s\Z");
			$params['Version'] = "2006-10-01";
			uksort($params, "strnatcasecmp");

			$toSign = "";
			foreach($params as $key => $val)
				$toSign .= $key . $val;
			$sha1 = $this->_hasher->hash($toSign);
			$sig  = $this->base64($sha1);
			$params['Signature'] = $sig;

			$curl = "{$this->_pathToCurl} -s \"{$this->_server}/?";
			
			reset($params);
			foreach($params as $key => $val)
				$curl .= "$key=" . urlencode($val) . "&";
			$curl .= '"';

			return `$curl`;
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