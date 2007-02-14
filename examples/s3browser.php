<?PHP
	require_once("../class.s3.php");
	session_start();

	if(isset($_GET['logout']))
	{
		unset($_SESSION['AMZ_KEY']);
		unset($_SESSION['AMZ_SECRET']);
		unset($_SESSION['BUCKET']);
		session_destroy();
	}
	
	if(isset($_POST['key']) && isset($_POST['secret']))
	{
		$_SESSION['AMZ_KEY'] = $_POST['key'];
		$_SESSION['AMZ_SECRET'] = $_POST['secret'];
		header("Location: " . $_SERVER['PHP_SELF']);
		die();
	}

	if(!isset($_SESSION['AMZ_KEY']) || !isset($_SESSION['AMZ_SECRET']))
	{
		echo "<form action='' method='post'>";
		echo "<label for='key'>Amazon Key</label> <input type='text' name='key' id='key'/><br/>";
		echo "<label for='secret'>Amazon Private Key</label> <input type='text' name='secret' id='secret'/><br/>";
		echo "<input type='submit' value='Submit' name='btnSubmit' id='btnSubmit'/></form>";
		die();
	}
	
	if(isset($_GET['bucket'])) { $_SESSION['BUCKET'] = $_GET['bucket']; $_SESSION['PATH'] = ""; }
	if(isset($_GET['path'])) $_SESSION['PATH'] = $_GET['path'];
	
	$s3     = new S3($_SESSION['AMZ_KEY'], $_SESSION['AMZ_SECRET']);	
	$me     = $_SERVER['PHP_SELF'];
	$bucket = $_SESSION['BUCKET'];

	$path = trim($_SESSION['PATH'], "/");
	$_SESSION['PATH'] = $path;
	
	if(isset($_GET['delete']) && !empty($_GET['delete']))
		$s3->recursiveDelete($bucket, $_GET['delete']);

	function human_readable($val, $round = 0)
	{
		$unit = array('','K','M','G','T','P','E','Z','Y');
		while($val >= 1000)
		{
			$val /= 1024;
			array_shift($unit);
		}
		return round($val, $round) . array_shift($unit) . "B";
	}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>S3 File Browser</title>
	<style type="text/css" media="screen">
		a { color:#00f; }
		#logout { position:absolute; top:0; right:0; margin:5px; }
		#main h1 a { color:#000; text-decoration:underline; }
		#main pre a { text-decoration:none; }
		#main pre a:hover { text-decoration:underline; }
		#main pre a.prefix { font-weight:bold; }
	</style>
</head>

<body>
	<div id="logout">
		<a href='<?PHP echo $me;?>?bucket='>Change Buckets</a>
		<a href='<?PHP echo $me;?>?logout'>Logout</a>
	</div>
	<div id="main">
		<?PHP
			if(empty($bucket))
			{
				$buckets = $s3->getBuckets();
				echo "<h1>Choose a Bucket</h1><ul>";
				foreach($buckets as $name)
					echo "<li><a href='$me?bucket=$name'>$name</a></li>";
				echo "</ul>";
			}
			else
			{
				echo "<h1>Browsing <a href='$me?path=/'>$bucket</a></h1>";
				
				$uppath = str_replace(array_pop(explode("/", trim($path, "/"))), "", trim($path, "/"));
				echo "<h2><a href='$me?path=$uppath'>$path/</a></h2>";

				$items = $s3->getBucketContents($bucket, str_replace("//", "/", "/$path/"), "/");
				$items = $s3->sortKeys($items);

				echo "<p>" . count($items) . " items</p>";
			
				echo "<pre>";
				foreach($items as $item)
				{
					if($item["type"] == "key")
					{
						$link = "http://s3.amazonaws.com/$bucket/" . $item["name"];
						$name = str_replace(trim($path, "/") . "/", "", $item["name"]);
						printf("%s %-10s %s\n", "<a href='$me?delete={$item['name']}' onclick='return confirm(\"Are you sure you want to delete this item?\");'>[x]</a>", human_readable($item["size"], 2), "<a href='$link' class='key'>$name</a>");
					}
					else
					{
						$link = "$me?path=" . $item['name'];
						$name = trim(str_replace($path, "", $item['name']), "/") . "/";
						printf("%s %10s %s\n", "<a href='$me?delete={$item['name']}' onclick='return confirm(\"Are you sure you want to delete this item?\");'>[x]</a>", " ", "<a href='$link' class='prefix'>$name</a>");
					}
				}
				echo "</pre>";
			}
		?>
	</div>
</body>
</html>