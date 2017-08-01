<!DOCTYPE html>
<html>
<head>
<center>
<meta charset="UTF-8">
<title>This is the IAPS voting system</title>
<link rel="stylesheet" type="text/css" href="formstyle.css">
<img src="http://iaps.info/logos/IAPS_logo_small.png">
<h1>Voting Machine</h1>
</head>
</center>
<body>
<script>
	function isNumber(evt) {
    evt = (evt) ? evt : window.event;
    var charCode = (evt.which) ? evt.which : evt.keyCode;
    if (charCode > 31 && (charCode < 48 || charCode > 57)) {
        return false;
    }
    return true;
}
</script>
<center>

<?php
		
# Connect to the database
include "connect.php";

if (!count($_GET) && !count($_POST)) {
	echo "<a href='?&mode=listevents'><h3>View or manage events</h3></a>";
	exit;
} 

if ($_GET["mode"] == "listevents") {
	# Select which event to view an event
	echo "<h3>Inspect an event</h3>";
	echo "<form action='$_SERVER[PHP_SELF]?mode=view' method='POST'>";
	echo "<b>Choose the voting event:</b> ";
	echo "<select name='event' required>";

	$result = mysqli_query($conn,"SELECT id,name,password FROM events");
	while($row = mysqli_fetch_assoc($result)) { 
		echo "<option value='$row[id]'>$row[name] (requires viewing passowrd)</option>";
	}
	echo "<option value='new'>Add new event (requires admin passowrd)</option>";	
	echo "</select><br><br>";
	
	echo "Enter event management password to alter the<br> table of voters
	or to start a new vote.<br>";
	echo "<b>Password: </b>
	<input type='password' name='pass' required><br><br>";
	echo "<input type='submit' name='submit'>";
	echo "</form>";

} 

if ($_GET["mode"] == "view") {
	# View all votes from an event
	
	# Check the inputs and viewing rights
	$view = false;
	$manage = false;
	
	if (!empty($_GET["key"])) $key = $_GET["key"];
	elseif (!empty($_POST["pass"])) $key = $_POST["pass"];
	else die("Wrong event key.");
	
	if (!empty($_GET["event"])) $event = $_GET["event"];
	elseif (!empty($_POST["event"])) $event = $_POST["event"];
	else die("Wrong event ID.");
	
	$sql = sprintf("SELECT * FROM events WHERE id='%s'",
			mysqli_real_escape_string($conn,(int)$event));

	$result = mysqli_query($conn,$sql);
	$row = mysqli_fetch_assoc($result);

	if(empty($row) and $event !== "new") die("Could not find the event");
	if(sha1($row["viewpass"]) === sha1($key) or sha1($row["viewpass"]) === $key) $view = true;
		
	# Check if management mode can be enabled
	$pass = sha1($row["password"]);

	if ($_POST["event"] !== "new" and $_POST["event"]) {
		if (sha1($_POST["pass"]) === $pass and !empty($pass) and !empty($_POST["pass"])) {
			$manage = true;
		}
	}
		
	if($view or $manage) echo "<h3>Event $row[name]</h3>";
	else echo "Wrong password";
	
	if ($manage) {
		$sql = sprintf("SELECT name FROM votertables WHERE longname='%s'",
				mysqli_real_escape_string($conn,$row["votertable"]));
		$result = mysqli_query($conn,$sql);
		$name = mysqli_fetch_assoc($result);
		$name = $name["name"];
				
		# Get the voter table and a link to modify it
		echo "<form action='voters.php' method='POST'>";
		echo "<input type='hidden' name='key' value='".$_POST["pass"]."'>";		
		echo "<input type='hidden' name='votertable' value='".$row["votertable"]."'>";
		echo "<b>Voter table:</b> $name ";
		echo "<input type='submit' name='inspecttable' value='View/modify the voting table'></form>";
		
		# Link to start new votes
		echo "<br><form action='startvote.php' method='POST'>";
		echo "<input type='hidden' name='pass' value='".$_POST["pass"]."'>";
		echo "<input type='hidden' name='eventID' value='".$_POST["event"]."'>";
		echo "<input type='hidden' name='votertablelong' value='".$row["votertable"]."'>";
		echo "<input type='hidden' name='votertableshort' value='$name'>";
		echo "<b>New letter vote:</b> ";
		echo "Number of answer options <input type='text' name='options' onkeypress='return isNumber(event)' size='2' required> ";
		echo "<input type='submit' name='newvote' value='Configure the vote'></form>";
	}
	if ($manage or $view) {
		echo "<h3>Past & ongoing votes</h3>";
		# Show all the voting events
		echo "<table cellspacing='10'><tr><td><b>Topic</b></td>
			<td><b>Status</b></td><td><b>Winner(s)/progress</b></td>
			<td><b>Closing time</b></td><td><b>Details</b></td></tr>";
		$sql = sprintf("SELECT * FROM votingtable WHERE event='%s'",
					mysqli_real_escape_string($conn,(int)$event));
					
		$result = mysqli_query($conn,$sql);

		while($row = mysqli_fetch_assoc($result)) {
			echo "<tr><td><b>$row[topic]</b>:</td>";
			$open = $row["open"];
			
			# Close the vote if the time has exceeded
			if($open) {
				$timenow = date("Y-m-d H:i:s");
				$endtime = $row["endtime"];
				if ($timenow > $endtime) {
					$sql = sprintf("UPDATE votingtable SET open=false WHERE name='%s'",
								mysqli_real_escape_string($conn,$table));
					if(!mysqli_query($conn,$sql)) die("Error updating the voting session.");
					$open = false;
					# Write to logs
					file_put_contents($logfile,$timenow." ".$sql.PHP_EOL , FILE_APPEND | LOCK_EX);
					$stillopen = false;
				}
			}
			
			echo "<td>".($open ? "Open" : "Closed")."</td>";

			if($open) {
				# If the vote is open, show number of given votes and when it closes
				$res = mysqli_query($conn,"SELECT * FROM $row[votertable]");
				$votes = 0;
				$votesgiven = 0;
				$totalvotes = 0;
				
				while ($myrow = mysqli_fetch_assoc($res)) {
					if ($myrow["status"] == "NC") {
						$votes = 7;
					} elseif ($myrow["status"] == "LC") {
						$votes = 2;
					} else {
						$votes = 1;
					}
					$totalvotes += $votes;
					
					if ($myrow["voted"]) {
						$votesgiven += $votes;
					} 		
				}
				echo "<td>$votesgiven / $totalvotes votes given</td>
						<td>$row[endtime] (at latest)</td>";
			} else {
				# If the vote is closed, show the winner(s) and when it was closed
				$sql = "SELECT MAX(votecount) FROM $row[name]";
				$res = mysqli_query($conn,$sql);
				$res = mysqli_query($conn,$sql);
				$myrow = mysqli_fetch_row($res);
				$topvotes = $myrow[0];
				
				# Check if there is a tied win
				$res = mysqli_query($conn,"SELECT answer FROM $row[name] WHERE votecount=$topvotes");
				echo "<td>";	
				$i = 0;
				while($myrow = mysqli_fetch_row($res)) {
					echo ($i ? ", " : "") . $myrow[0];
					$i++;
				}
				echo "</td><td>$row[endtime]</td>";

			}
			echo "</td>";
			$hash = hash("sha256","$row[starttime].$row[votertable]");
			$url = "http://iaps.info/vote/results.php?vote=$row[name]&key=$hash";
			echo "<td><a href='$url'><b>See more</b></a></td></tr>";
		}
		echo "</table>";
	}
} 

# Form has been submitted at least once
if ($_POST["event"] === "new") {
	# New voting event
	# Check password
	$pass = sha1($_POST["pass"]);
	if ($pass !== $adminpass) die("Wrong password!");
			
	# New voting event
	echo "<h3>New voting event</h3>";
	echo "<form action='$_SERVER[PHP_SELF]' method='POST'>";
	
	# Input fields for the new event
	echo "<b>Event name</b> <input type='text' name='eventname' required><br><br>
		<b>Management password</b> <input type='text' name='eventpass' required><br>
		Only known by meeting officials, used to manage the voter table and creating new votes.<br><br>
		<b>Viewing password</b> <input type='text' name='viewpass' required><br>
		Distributed to all voters so that they can see the vote results.<br><br>
		<b>Voter table</b> <select name='votertable' required>";
	
	# List the voter tables
	$result = mysqli_query($conn,"SELECT * FROM votertables");
	while ($row = $result->fetch_assoc()) {
		echo count($row);
		echo "<option value='".$row["longname"]."'>".$row["name"]."</option>";
	}
	echo "<option value='new'>Create a new voter table</option></select><br><br>
	<input name='create' type='submit'>";
	

	# Pass data with hidden fields
	echo "<input name='choose' type='hidden' value='create'>
	<input name='id' type='hidden' value='$id'>
	<input name='pass' type='hidden' value='$pass'>
	</form>";
	
} elseif (isset($_POST["create"])) {
	# Save the voting event to the database
	if (sha1($_POST["pass"]) !== sha1($adminpass)) die("Wrong password!");
	
	$result = mysqli_query($conn,"SELECT id FROM events ORDER BY id DESC LIMIT 1");
	$row = mysqli_fetch_row($result);
	$id = (int)$row[0]+1;
	
	# Check if existing or new votertable was selected
	if ($_POST["votertable"] !== "new") {
		$votertable = $_POST["votertable"];
		$msg = "<br><br><a href='http://www.iaps.info/vote/?mode=listevents><b>Return to manage the event</b></a>"; 
	} else {
		$votertable = "";
		$key = sha1($votepass);	
		$url = "voters.php?key=$key&eventID=$id";
		$msg = "<h3>Crete a new voter table</h3>
				<form action='voters.php' method='POST'>
				Number of voters: <input type='text' name='voters' onkeypress='return isNumber(event)' size='2'><br>
				<input type='hidden' name='key' value='".$_POST["eventpass"]."'>
				<input type='hidden' name='votertable' value='".$_POST["votertable"]."'>
				<input type='hidden' name='createinspect' value='create'>
				<input type='hidden' name='eventID' value='$id'>
				<input type='submit' name='createnew' value='Continue to create the new voter table'></form><br>
				<b>The event won't be accessible until the voter table is created.</b>";
	}
	
	# SQL string for inserting the event into the database
	$sql = sprintf("INSERT INTO events (name,password,viewpass,votertable) 
					VALUES ('%s','%s','%s','%s')",
			mysqli_real_escape_string($conn,$_POST["eventname"]),
			mysqli_real_escape_string($conn,$_POST["eventpass"]),
			mysqli_real_escape_string($conn,$_POST["viewpass"]),
			mysqli_real_escape_string($conn,$_POST["votertable"]));
	//echo $sql;
	if (mysqli_query($conn,$sql)) echo "The event was saved.<br><br>";
	else die("<br>There was an error in storing the event to the database.");
	
	$timenow = date("Y-m-d H:i:s");
	file_put_contents($logfile,$timenow." ".$sql.PHP_EOL , FILE_APPEND | LOCK_EX);
	
	echo $msg;
}

?>
</center>

</body>
</html>
