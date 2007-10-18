<?PHP
	require_once("../class.awis.php");
	session_start();

	if(isset($_GET['logout']))
	{
		unset($_SESSION['AMZ_KEY']);
		unset($_SESSION['AMZ_SECRET']);
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
		
	$awis     = new AWIS($_SESSION['AMZ_KEY'], $_SESSION['AMZ_SECRET']);	
	$me     = $_SERVER['PHP_SELF'];
	$bucket = $_SESSION['BUCKET'];

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>AWIS Example</title>
	<style type="text/css" media="screen">
		body { font-family:Verdana, Arial, "sans seif"; font-size:10px; }
		a { color:#00f; }
		#top { position:absolute; top:0; right:0; margin:5px; }
		#main table { border-collapse:collapse; }
		#main table th { text-align:left; padding:5px 20px 5px 0; }
		#main table tr.row1 { background-color:#f1f5fa; }
		#main table td { padding:5px 20px 5px 0; }
		#main a { text-decoration:none; color:#000; }
		#main a:hover { text-decoration:underline; }
		#main a.prefix { font-weight:bold; }
	</style>
</head>

<body>
	<form id="top" acition="" method="get">
		Key: <?PHP echo $_SESSION['AMZ_KEY'];?><br>
		Secret: <?PHP echo $_SESSION['AMZ_SECRET'];?><br>
		URL: <input type="text" name="url" value="<?PHP echo $_GET["url"];?>">
		<input type="submit" name="btnSubmit" value="Go" id="btnSubmit">
		<a href='<?PHP echo $me;?>?logout'>Logout</a>
	</form>
	<div id="main"><pre>
		<?PHP if ($_GET["url"]) {
			$info = $awis->urlInfo($_GET["url"],"Rank");
			print_r($info);
		}
		?>
	</div>
</body>
</html>