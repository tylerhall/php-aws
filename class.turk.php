<?PHP
	// This class is still in progress
	class Turk
	{
		var $_key        = "";
		var $_secret     = "";
		var $_server     = "http://ec2.amazonaws.com";
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
		}

		function getAccountBalance()
		{
			$xmlstr = $this->sendRequest("GetAccountBalance");
			$xml = new SimpleXMLElement($xmlstr);
			return array("available" => (string) $xml->GetAccountBalanceResult->AvailableBalance->Amount, "onHold" => (string) $xml->GetAccountBalanceResult->OnHoldBalance->Amount);
		}

		function sendRequest($operation, $params = null)
		{
			$timestamp = gmdate("Y-m-d\TH:i:s\Z");
			$SERVICE_NAME = "AWSMechanicalTurkRequester";
			$SERVICE_VERSION = "2006-10-31";

			$signature = $this->generate_signature($SERVICE_NAME, $operation, $timestamp, $this->_secret);

			$url = "http://mechanicalturk.amazonaws.com/onca/xml"
			  . "?Service=" . urlencode($SERVICE_NAME)
			  . "&Operation=" . urlencode($operation)
			  . "&Version=" . urlencode($SERVICE_VERSION)
			  . "&Timestamp=" . urlencode($timestamp)
			  . "&AWSAccessKeyId=" . urlencode($this->_key)
			  . "&Signature=" . urlencode($signature);			

			return file_get_contents($url);
		}

		function hmac_sha1($key, $s)
		{
			return pack("H*", sha1((str_pad($key, 64, chr(0x00)) ^ (str_repeat(chr(0x5c), 64))) .
					pack("H*", sha1((str_pad($key, 64, chr(0x00)) ^ (str_repeat(chr(0x36), 64))) . $s))));
		}

		function generate_signature($service, $operation, $timestamp, $secret_access_key)
		{
			$string_to_encode = $service . $operation . $timestamp;
			$hmac = $this->hmac_sha1($secret_access_key, $string_to_encode);
			$signature = base64_encode($hmac);
			return $signature;
		}
	}
	
	$turk = new Turk("1TGRVYS5Q5PFDY9GZG82", "NqySNeM84s7/DR+9jakeeLrTSzxx/RIXkPrsXlRR");
	print_r($turk->getAccountBalance());
?>