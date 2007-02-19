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

	$buckets = $s3->getBuckets();
	
	if(isset($_GET['delete']) && !empty($_GET['delete']))
		$s3->recursiveDelete($bucket, $_GET['delete']);

	function human_readable($val, $round = 0)
	{
		$unit = array('','K','M','G','T','P','E','Z','Y');
		while($val >= 1024)
		{
			$val /= 1024;
			array_shift($unit);
		}
		return round($val, 2) . array_shift($unit) . "B";
	}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>S3 File Browser</title>
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
		<select name="bucket" id="bucket">
			<option value="">Choose a bucket</option>
			<?PHP
				foreach($buckets as $b)
				{
					if($bucket == $b)
						echo "<option value='$b' selected='selected'>$b</option>";
					else
						echo "<option value='$b'>$b</option>";
				}
			?>
		</select>
		<input type="submit" name="btnBucket" value="Go" id="btnBucket">
		<a href='<?PHP echo $me;?>?logout'>Logout</a>
	</form>
	<div id="main">
		<?PHP
			if(!empty($bucket))
			{
				echo "<h3>";
				echo "/<a href='$me?path='>$bucket</a>";
				foreach(explode("/", $path) as $x)
				{
					$y .= "/$x";
					echo "/<a href='$me?path=$y'>$x</a>";
				}				
				echo "</h3>";

				$items = $s3->getBucketContents($bucket, str_replace("//", "/", "/$path/"), "/");
				$items = $s3->sortKeys($items);

				echo "<p>" . count($items) . " items</p>";
			
				echo "<table>";
				echo "<tr><th>Size</th><th>Name</th><th>&nbsp;</th></tr>";
				foreach($items as $item)
				{
					$row = ($row == "1") ? "2" : "1";
					echo "<tr class='row$row'>";
					if($item["type"] == "key")
					{
						$link = "http://s3.amazonaws.com/$bucket/" . $item["name"];
						$name = str_replace(trim($path, "/") . "/", "", $item["name"]);

						echo "<td>" . human_readable($item["size"], 2) . "</td>";
						echo "<td><a href='$link' class='key'>$name</a></td>";
						echo "<td><a href='$me?delete={$item['name']}' onclick='return confirm(\"Are you sure you want to delete this item?\");'>delete</td>";
					}
					else
					{
						$link = "$me?path=" . $item['name'];
						$name = trim(str_replace($path, "", $item['name']), "/") . "/";
						echo "<td>&nbsp;</td>";
						echo "<td><a href='$link' class='prefix'>$name</a></td>";
						echo "<td><a href='$me?delete={$item['name']}' onclick='return confirm(\"Are you sure you want to delete this item?\");'>delete</td>";
					}
					echo "</tr>";
				}
				echo "</table>";
			}
		?>
	</div>
</body>
</html>