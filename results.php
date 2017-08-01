<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>This is the IAPS voting system</title>
<link rel="stylesheet" type="text/css" href="formstyle.css">
<center>
<img src="http://iaps.info/logos/IAPS_logo_small.png">
<h1>Voting Machine: Result Page</h1>
</center>
</head>

<body>
<center>
<?php
if (empty($_GET)) die("Please give the voting event and key to see any results.");

# Connect to the database
include "connect.php";

# Check if the database has an entry with the given key and hash
$table = $_GET["vote"];
$key = $_GET["key"];

$sql = sprintf("SELECT * FROM votingtable WHERE name = '%s'",
				mysqli_real_escape_string($conn,$table));

$result = mysqli_query($conn,$sql);
$row = mysqli_fetch_assoc($result);

if (empty($row)) die("Could not find the voting event, check your url");

# Get the properties of the voting events
$topic = $row["topic"];
$event = $row["event"];
$desc = $row["description"];
$votertable = $row["votertable"];
$starttime = $row["starttime"];
$endtime = $row["endtime"];
$open = $row["open"];
$stillopen = $open;
$timenow = date("Y-m-d H:i:s");

$hash = hash("sha256","$starttime.$votertable");
if (sha1($hash) !== sha1($_GET["key"])) die("Wrong voting key. Cannot show the results.");
echo "<h3>Vote $topic</h3>";
echo "<h3>Vote description</h3>$desc<br><br>";

if ($open) {
	# Check if time has exceeded:
	if ($timenow > $endtime) {
		$sql = sprintf("UPDATE votingtable SET open=false WHERE name='%s'",
					mysqli_real_escape_string($conn,$table));
		if(!mysqli_query($conn,$sql)) die("Error updating the voting session.");
		# Write to logs
		file_put_contents($logfile,$timenow." ".$sql.PHP_EOL , FILE_APPEND | LOCK_EX);
		$stillopen = false;
	}
	# Check if everyone has voted
	if ($stillopen) {
		$result = mysqli_query($conn,"SELECT * from $votertable");
		$voters = mysqli_num_rows($result);
		$votes = 0;
		$n = 0;
		$votesgiven = 0;
		$totalvotes = 0;
		
		while ($row = mysqli_fetch_assoc($result)) {
			if ($row["status"] == "NC") {
				$votes = 7;
			} elseif ($row["status"] == "LC") {
				$votes = 2;
			} else {
				$votes = 1;
			}
			$totalvotes += $votes;
			
			if ($row["voted"]) {
				$n += 1;
				$votesgiven += $votes;
			} 
		}
		# Everyone has voted, close the vote
		if ($n === $voters) {
			$sql = sprintf("UPDATE votingtable SET open=false,endtime='$timenow' WHERE name='%s'",
						mysqli_real_escape_string($conn,$table));
						
			if(!mysqli_query($conn,$sql)) die("Error updating the voting session.");
			$stillopen = false;
			$endtime = $timenow;
		}
	}
}
// Check if we can show the results
if ($open && $stillopen) {
	echo "Voting still in progress. It will close at $endtime (UTC)
	or after all votes are given.<br><br>".
	"Currently $votesgiven / $totalvotes votes given.";
} else {
	echo "The voting has finished at $endtime.";
	echo "<h3>Results</h3>";
	echo "<table rules='all'>";
	echo "<tr><td><b>Answer option</b></td><td><b>Vote count</b></td></tr>";
	
	$sql = sprintf("SELECT * FROM %s ORDER BY votecount DESC",
				mysqli_real_escape_string($conn,$table));
	$result = mysqli_query($conn,$sql);
	$i = 0;
	while($row = mysqli_fetch_row($result)) {
		echo "<tr><td>".($i ? "" : "<b>").$row[1].($i ? "" : "</b>")."</td>
		<td>".($i ? "" : "<b>").$row[2].($i ? "" : "</b>")."</td></tr>";
		$i++;
	}
	echo "</table><br><br>";
	
	# Get viewing password of the event:
	$result = mysqli_query($conn,"SELECT viewpass FROM events WHERE id='$event'");
	$row = mysqli_fetch_row($result);
	$key = sha1($row[0]);
	
	echo "<a href='http://iaps.info/vote/?mode=view&event=$event&key=$key'>
	<b>See all the votes of the event</b></a>";
}

?>

</center>
</body>
</html>
