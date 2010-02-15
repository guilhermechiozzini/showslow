<?php 
require_once('../../global.php');

function getUrlId($url)
{
	global $limitURLs;

	if ($limitURLs !== false && is_array($limitURLs)) {
		$matched = false;

		foreach ($limitURLs as $prefix) {
			if (substr($url, 0, strlen($prefix)) == $prefix) {
				$matched = true;
				break;
			}
		}

		if (!$matched) {
			header('HTTP/1.0 400 Bad Request');

			?><html>
<head>
<title>Bad Request: YSlow beacon</title>
</head>
<body>
<h1>Bad Request: YSlow beacon</h1>
<p>URL doesn't match any of the prefixes.</p>
</body></html>
<?php 
			exit;
		}
	}

	# get URL id
	$query = sprintf("SELECT id FROM urls WHERE url = '%s'", mysql_real_escape_string($url));
	$result = mysql_query($query);

	if (!$result) {
		error_log(mysql_error());
		exit;
	}

	if (mysql_num_rows($result) == 1) {
		$row = mysql_fetch_assoc($result);
		return $row['id'];
	} else if (mysql_num_rows($result) == 0) {
		$query = sprintf("INSERT INTO urls (url) VALUES ('%s')", mysql_real_escape_string($url));
		$result = mysql_query($query);

		if (!$result) {
			error_log(mysql_error());
			exit;
		}

		return mysql_insert_id();
	} else {
		error_log('more then one entry found for the URL');
		exit;
	}

}

if (array_key_exists('metric', $_REQUEST) && array_key_exists($_REQUEST['metric'], $metrics)
	&& array_key_exists('value', $_REQUEST) && is_numeric($_REQUEST['value']) !== false
	&& array_key_exists('u', $_REQUEST) && filter_var($_REQUEST['u'], FILTER_VALIDATE_URL) !== false
	)
{
	$url_id = getUrlId($_REQUEST['u']);

	if (array_key_exists('timestamp', $_REQUEST) && $_REQUEST['timestamp']) {
		# adding new entry
		$query = sprintf("INSERT INTO metric (timestamp, url_id, metric_id, value) VALUES ('%s', '%d', '%d', '%f')",
			mysql_real_escape_string($_REQUEST['timestamp']),
			mysql_real_escape_string($url_id),
			mysql_real_escape_string($metrics[$_REQUEST['metric']]['id']),
			mysql_real_escape_string($_REQUEST['value'])
		);
	} else {
		# adding new entry
		$query = sprintf("INSERT INTO metric (url_id, metric_id, value) VALUES ('%d', '%d', '%f')",
			mysql_real_escape_string($url_id),
			mysql_real_escape_string($metrics[$_REQUEST['metric']]['id']),
			mysql_real_escape_string($_REQUEST['value'])
		);
	}

#	error_log($query);

	if (!mysql_query($query))
	{
		error_log(mysql_error());
		exit;
	}

} else {
	header('HTTP/1.0 400 Bad Request');

	?><html>
<head>
<title>Bad Request: Custom Metric beacon</title>
</head>
<body>
<h1>Bad Request: Custom Metric beacon</h1>
<p>This is custom metric beacon for ShowSlow.</p>
<p>You can use automated script to publish events using GET call to this URL:</p>
<b><pre><?php echo $showslow_base?>beacon/metric/?metric=<i>metricname</i>&amp;u=<i>url</i>&amp;value=<i>integer_value</i>&amp;timestamp=<i>mysql_timestamp</i></pre></b>
or use form below to manually enter metric values.

<h2>Add metric</h2>
<form action="" method="GET">
<table>
<tr valign="top"><td>Time:</td><td><input type="text" name="timestamp" size="25" value="<?php echo date("Y-m-d H:i:s");?>"/><br/>Time in MySQL <a href="http://dev.mysql.com/doc/refman/5.1/en/datetime.html">timestamp format</a></td></tr>
<tr><td>Metric:</td><td><select name="metric">
<option value="">-- pick metric --</option>
<?php
foreach ($metrics as $name => $metric) {
	?><option value="<?php echo $name?>"><?php echo $metric['title']?></option><?php
}
?>
</select></td></tr>
<tr><td>URL:</td><td><input type="text" name="u" value="http://www.example.com/" size="80"/></td></tr>
<tr><td>Value:</td><td><input type="text" name="value" size="80"/> (integer value)</td></tr>
<tr><td></td><td><input type="submit" value="add"/></td></tr>

</table>
</form>
</body></html>
<?php 
}