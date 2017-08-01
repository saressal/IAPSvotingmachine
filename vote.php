<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>This is the IAPS voting system</title>
<link rel="stylesheet" type="text/css" href="formstyle.css">
<center>
<img src="http://iaps.info/logos/IAPS_logo_small.png">
<h1>Voting Machine</h1>
</center>
</head>
<body>
<center>
<h1>IAPS voting machine</h1>
<?php

# Connect to the database
require "connect.php";

if (!empty($_GET["vote"]) and !empty($_GET["key"]) and empty($_POST)) {

	$sql = sprintf("SELECT * FROM votingtable where name='%s'",
					mysqli_real_escape_string($conn,$_GET["vote"]));
					
	$result = mysqli_query($conn,$sql);
	$row = mysqli_fetch_assoc($result);
	
	if (empty($row)) die("Wrong voting event");

	# Retrieve the details of the voting event
	$table = $_GET["vote"];
	$topic = $row["topic"];
	$eventID = $row["event"];
	$desc = $row["description"];
	$votertable = $row["votertable"];
	$starttime = $row["starttime"];
	$endtime = $row["endtime"];
	$open = $row["open"];
	$timenow = date("Y-m-d H:i:s");
	$key = $_GET["key"];

	echo "<h2>IAPS voting event: $topic</h2>";

	# Check if person already voted
	$sql = sprintf("SELECT * FROM $votertable WHERE idkey='%s'",
					mysqli_real_escape_string($conn,$key));
	
	$result = mysqli_query($conn,$sql);
	$row = mysqli_fetch_assoc($result);
	if(empty($row)) die("Wrong voting key!");
	if($row["voted"]) die("You have already given your vote!");

	# Check if the voting is still open
	$hash = hash("sha256",$_POST["starttime"].$_POST["votertable"]);
	if ($timenow>date($endtime) or !$open) die("The voting has ended. See the results 
	<a href='<a href='http://iaps.info/vote/results.php?vote=".$_POST["table"]."&key=$hash'>
	<b>here</b></a>");

	echo "<h3>Vote description</h3>$desc<br><br>";

	# Check the voter status (NC/LC/IM)

	if ($row["status"] == "NC") {
		$votes = 7;
		$status = "National Committee";
	} elseif ($row["status"] == "LC") {
		$votes = 2;
		$status = "Local Committee";
	} elseif ($row["status"] == "IM"){
		$votes = 1;
		$status = "Individual member";
	}

	echo "Welcome <b>".$row["name"]."</b>!<br>";
	echo "You are a <b>$status</b>, therefore you have <b>$votes votes</b>.<br>";
	echo "The voting is possible until <b>$endtime</b>. (UTC time now: $timenow)";

	echo "<form action='$_SERVER[PHP_SELF]' method='POST'>";
	echo "<input type='hidden' name='key' value='$key'>";
	echo "<input type='hidden' name='votes' value='$votes'>";
	echo "<input type='hidden' name='table' value='$table'>";
	echo "<input type='hidden' name='status' value='$status'>";
	echo "<input type='hidden' name='votertable' value='$votertable'>";
	echo "<input type='hidden' name='starttime' value='$starttime'>";
	echo "<input type='hidden' name='endtime' value='$endtime'>";

	for($i=1; $i<= $votes; $i++) {
		echo "<br><fieldset><legend><b>Voting slip $i</b></legend>";
		$sql = sprintf("SELECT Id,answer FROM %s",
				mysqli_real_escape_string($conn,$table));

		$result = mysqli_query($conn,$sql);
		
		while($row=mysqli_fetch_row($result)) {
			echo "<label>";
			echo "<input type='radio' name='answer$i' value='$row[0]' 
			class='option-input radio' required>$row[1]<br>";
			echo "</label>";
		}
		echo "</fieldset>";
	}
	echo "<h3>Submit answers</h3>";
	echo "<input type='submit' name='vote' value='Vote'><br><br>";
	echo "<b>Remember: you can vote once!</b>";
	echo "</form>";
}

if (isset($_POST["vote"])) {
# Voter has given the answers
	
	# Check if person already voted (in a different window)
	$sql = sprintf("SELECT voted FROM %s WHERE idkey='%s'",
					mysqli_real_escape_string($conn,$_POST["votertable"]),
					mysqli_real_escape_string($conn,$_POST["key"]));
					
	$result = mysqli_query($conn,$sql);
	$row = mysqli_fetch_row($result);
	if(empty($row)) die("Wrong voting key");
	if($row[0]) die("You have already given your vote!");	
	
	# Check that the voting is still open
	
	if(strtotime(date("Y-m-d H:i:s"))>strtotime($_POST["endtime"]))
		die("The time limit was exceeded while you were choosing the answer.");
	
	# Store the given votes
	for($i=1; $i<= (int)$_POST["votes"]; $i++) {
		$answername = "answer" . $i;
		$answer = $_POST[$answername];
		
		$sql = sprintf("UPDATE %s SET votecount=votecount + 1 WHERE Id='%s'",
					mysqli_real_escape_string($conn,$_POST["table"]),
					mysqli_real_escape_string($conn,$answer));
		if(!mysqli_query($conn,$sql)) die("Could not store the vote(s).");
		# Write to logs
		$timenow = date("Y-m-d H:i:s");
		file_put_contents($logfile,$timenow." ".$sql.PHP_EOL , FILE_APPEND | LOCK_EX);
	}
	
	# The person has voted
	$sql = sprintf("UPDATE %s SET voted=true WHERE idkey='%s'",
					mysqli_real_escape_string($conn,$_POST["votertable"]),
					mysqli_real_escape_string($conn,$_POST["key"]));	
	if (!mysqli_query($conn,$sql)) die("Error updating the voter status.");
	
	$hash = hash("sha256","$_POST[starttime].$_POST[votertable]");
	echo "<br><br>Thank you for voting, see the results (after the vote has closed)
	<a href='http://iaps.info/vote/results.php?vote=".$_POST["table"]."&key=$hash'>here</a>";
} 

?>
</center>
</center>
</body>
</html>
