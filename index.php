<?php
require __DIR__ . '/vendor/autoload.php';

$key = '';
$secret = '';
$space = '';
$region = 'nyc3';

$client = new BAG\Spaces\Client($key, $secret, $region);

$space = new BAG\Spaces\Space($space, $key, $secret, $region);

var_dump($space->listFolders(''));

// $url = $client->presignedUpload($space, 'test-presigned-upload.jpg', '+30 minutes');

exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
</head>
<body>
	<form method="post">
		<input type="file">
		<button type="button">Submit</button>
	</form>

	<script type="text/javascript">
		var uploadUrl = '<?=$url?>';
		var button = document.querySelector('button');
		button.addEventListener('click', function() {
			var input = document.querySelector('input');
			if (input.files.length) {
				var data = new FormData();
				data.append('file', input.files[0]);
				var xhr = new XMLHttpRequest();

				xhr.onload = function() {
					if (xhr.status !== 200) {
						console.log('error uploading');
					}
					// const xml = new DOMParser().parseFromString(xhr.responseText, 'text/xml');
					// const url = xml.querySelector('Location').firstChild.nodeValue;
					// console.log(url);
				};

				xhr.upload.onprogress = function(event) {
					const percent = Math.round((event.loaded / event.total) * 100);
					console.log([percent, event.loaded, event.total]);
				};

				xhr.open('PUT', uploadUrl, true);
				xhr.setRequestHeader('Content-Type', 'image/jpeg');
				xhr.setRequestHeader('x-amz-acl', 'public-read');
				xhr.send(input.files[0].slice(0, input.files[0].size));
			}
		});
	</script>
</body>
</html>