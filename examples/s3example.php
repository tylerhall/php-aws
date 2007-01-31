<?PHP
	require_once("../class.s3.php");

	// Fill in  your Amazon keys here...
	$AMAZON_KEY = "";
	$AMAZON_PRIVATE_KEY = "";
	
	$s3 = new S3($AMAZON_KEY, $AMAZON_PRIVATE_KEY);
	
	// Display a list of buckets we own
	echo "<strong>Here's a list of your buckets</strong>";
	echo "<ul>";
	$buckets = $s3->getBuckets();
	if(count($buckets) == 0)
		echo "<li>You don't have any buckets!</li>";
	else	
		foreach($buckets as $bucket_name)
			echo "<li>$bucket_name</li>";
	echo "</ul>";
	
	// Create a new bucket (using a random name)
	$new_bucket = "test-" . md5(rand(1,9999) . time());
	echo "<strong>We're going to create a new bucket named $new_bucket</strong><br/>";
	if($s3->createBucket($new_bucket))
		echo "Bucket created successfully!<br/><br/>";
	else
	{
		echo "Something went wrong! We couldn't create a new bucket!<br/><br/>";
		die();
	}
	
	// Upload an example image into the new bucket
	echo "<strong>Now we're going to upload <a href='example.jpg'>this file</a> to the new bucket</strong><br/>";
	if($s3->putObject($new_bucket, "somefilename.jpg", "example.jpg", true)) // The "true" makes our file publicly accessible
		echo "File uploaded! You should see it below...<br/><img src='http://s3.amazonaws.com/$new_bucket/somefilename.jpg'/><br/><br/>";
	else
	{
		echo "We couldn't upload the file!";
		die();
	}
	
	// How much space are we using?
	$dir_size = $s3->directorySize($new_bucket);
	echo "<strong>You're now using $dir_size bytes in S3.</strong><br/><br/>";	
?>