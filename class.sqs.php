<?PHP
    class SQS
    {
        public $_key        = "";
        public $_secret     = "";
        public $_server     = "http://queue.amazonaws.com/";
        public $_date       = null;
        public $_error      = null;
        public $queue_url   = null;

        public function __construct($key, $secret, $queue_url = null)
        {
            $this->_key    = $key;
            $this->_secret = $secret;
            $this->queue_url = $queue_url;
        }

        public function createQueue($queue_name, $default_timeout = 30)
        {
            if ($default_timeout < 30) { $default_timeout = 30; }
            $params = array("QueueName" => $queue_name, "DefaultVisibilityTimeout" => $default_timeout);
            $xml = $this->go("CreateQueue", $params);
            if($xml === false) return false;

            return strval($xml->CreateQueueResult->QueueUrl);
        }

        public function listQueues($queue_name_prefix = "")
        {
            $params = ($queue_name_prefix == "") ? array() : array("QueueNamePrefix" => $queue_name_prefix);
            $xml = $this->go("ListQueues", $params);
            if($xml === false) return false;
            $out = array();
            foreach($xml->ListQueuesResult->QueueUrl as $url)
                $out[] = strval($url);
            return $out;
        }

        public function deleteQueue($queue_url = null)
        {
            if(!isset($queue_url)) $queue_url = $this->queue_url;
            $xml = $this->go("DeleteQueue", null, $queue_url);
            return $xml ? true : false;
        }

        public function sendMessage($message_body, $queue_url = null)
        {
            if(!isset($queue_url)) $queue_url = $this->queue_url;
            $params = array("MessageBody" => $message_body);
            $xml = $this->go("SendMessage", $params, $queue_url);

            if($xml === false) return false;

            return strval($xml->SendMessageResult->MessageId);
        }

        public function receiveMessage($number = 1, $timeout = null, $queue_url = null)
        {
            if(!isset($queue_url)) $queue_url = $this->queue_url;

            $number = intval($number);
            if($number < 1) $number = 1;
            if($number > 256) $number = 256;

            $params = array();
            $params['MaxNumberOfMessages'] = $number;
            if(isset($timeout)) $params['VisibilityTimeout'] = intval($timeout);

            $xml = $this->go("ReceiveMessage", $params, $queue_url);

            if($xml === false) return false;

            $out = array();
            foreach($xml->ReceiveMessageResult->Message as $m)
                $out[] = $m;
            return $out;
        }

        public function deleteMessage($receipt_handle, $queue_url = null)
        {
            if(!isset($queue_url)) $queue_url = $this->queue_url;
            $params = array("ReceiptHandle" => $receipt_handle);
            $xml = $this->go("DeleteMessage", $params, $queue_url);
            return ($xml === false) ? false : true;
        }

        public function clearQueue($limit = 100, $queue_url)
        {
            $m = $this->receiveMessage($limit, null, $queue_url);
            foreach($m as $n)
                $this->deleteMessage($n['MessageId'], $queue_url);
        }

        public function setTimeout($timeout, $queue_url = null)
        {
            $timeout = intval($timeout);
            if(!isset($queue_url)) $queue_url = $this->queue_url;
            if(!is_int($timeout)) $timeout = 30;
            $params = array("Attribute.Name" => "VisibilityTimeout", "Attribute.Value" => $timeout);
            $xml = $this->go("SetQueueAttributes", $params, $queue_url);
            return ($xml === false) ? false : true;
        }

        public function getTimeout($queue_url = null)
        {
            if(!isset($queue_url)) $queue_url = $this->queue_url;
            $params = array("AttributeName" => "VisibilityTimeout");
            $xml = $this->go("GetQueueAttributes", $params, $queue_url);
            return ($xml === false) ? false : strval($xml->GetQueueAttributesResult->Attribute->Value);
        }
        public function getSize($queue_url = null)
        {
            if(!isset($queue_url)) $queue_url = $this->queue_url;
            $params = array("AttributeName" => "ApproximateNumberOfMessages");
            $xml = $this->go("GetQueueAttributes", $params, $queue_url);
            return ($xml === false) ? false : strval($xml->GetQueueAttributesResult->Attribute->Value);
        }
        public function setQueue($queue_url)
        {
            $this->queue_url = $queue_url;
        }

        public function go($action, $params, $url = null)
        {
            $params['Action'] = $action;

            if(!$url) $url = $this->_server;

            $params['AWSAccessKeyId'] = $this->_key;
            $params['SignatureVersion'] = 1;
            $params['Timestamp'] = gmdate("Y-m-d\TH:i:s\Z");
            $params['Version'] = "2008-01-01";
            uksort($params, "strnatcasecmp");

            $toSign = "";
            foreach($params as $key => $val)
                $toSign .= $key . $val;
            $sha1 = $this->hasher($toSign);
            $sig  = $this->base64($sha1);
            $params['Signature'] = $sig;

            $url .= '?';
            foreach($params as $key => $val)
                $url .= "$key=" . urlencode($val) . "&";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $xmlstr = curl_exec($ch);

            $xml = new SimpleXMLElement($xmlstr);

            return (isset($xml->Errors) || isset($xml->Error)) ? false : $xml;
        }

        public function hasher($data)
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

        public function base64($str)
        {
            $ret = "";
            for($i = 0; $i < strlen($str); $i += 2)
                $ret .= chr(hexdec(substr($str, $i, 2)));
            return base64_encode($ret);
        }
    }
