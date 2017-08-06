<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>This is the IAPS voting system</title>
<link rel="stylesheet" type="text/css" href="formstyle.css">
<center>
<img src="http://iaps.info/logos/IAPS_logo_small.png">
<h1>Voting Machine: Start a Voting Event</h1>
</center>
</head>
<body>
<center>
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
<?php

// Connect to the database
require "connect.php";
if(!count($_POST)) die();

if (isset($_POST["newvote"])) {
	# Get the event details
	$sql = sprintf("SELECT * FROM events WHERE id='%s'",
			mysqli_real_escape_string($conn,$_POST["eventID"]));
	$result = mysqli_query($conn,$sql);
	$row = mysqli_fetch_assoc($result);
	$pass = sha1($row["password"]);
	
	# Check the password
	if (sha1($_POST["pass"]) !== $pass or empty($pass) or empty($_POST["pass"]))
		die("Wrong password or event id!");

	echo "<h3>Voting topic</h3>";
	echo "<form action='$_SERVER[PHP_SELF]' method='POST'>";
	echo "<input type='text' name='topic' maxlength='50' required />";
	
	echo "<h3>Vote description/explanation</h3>";
	echo "<textarea rows='5' cols='70' name='desc' maxlength='1000'></textarea>";
	
	echo "<h3>Vote duration</h3>";
	echo "<input type='text' name='duration' onkeypress='return isNumber(event)' size='2'>";
	echo "<select name='timeunit'>
		<option value='minutes'>minutes</option>
		<option value='hours'>hours</option>
		<option value='days'>days</option>
		</select>";
	
	# Only one voter table allowed per event
	echo "<h3>Voter table</h3>";
	echo "<select name='votertable' disabled>>
		<option value='".$_POST["votertablelong"]."'>".$_POST["votertableshort"]."</option>
		</select>";
	
	# Generate N fields for answer options
	echo "<h3>Answer options</h3>";
	echo "'Abstain' is always added as the last answer option.";
	echo "<table>";
	
	$n = (int)$_POST["options"];
	if ($n > 20 or $n < 1) {
		echo "Number of answer options must be between 1 and 20. Using default value of 3.";
		$n = 3;
	} else $n = (int)$_POST["options"];
	
	for ($i=1; $i <= $n; $i++) {
		echo "<tr><td>Option $i</td><td><input type='text' name='option$i' maxlength='90'></td></tr>";
	}
	echo "</table>";
	echo "<input type='hidden' name='pass' value='".$_POST["pass"]."'>";
	echo "<input type='hidden' name='votertable' value='".$_POST["votertablelong"]."'>";
	echo "<input type='hidden' name='options' value='$n'>";
	echo "<input type='hidden' name='eventID' value='".$_POST["eventID"]."'>";
	echo "<input type='submit' name='startvote' value='Start the vote'></form>";
}

if (isset($_POST["startvote"])) {
	
	# Get the event details
	$sql = sprintf("SELECT * FROM events WHERE id='%s'",
			mysqli_real_escape_string($conn,$_POST["eventID"]));
	$result = mysqli_query($conn,$sql);
	$row = mysqli_fetch_assoc($result);
	$pass = sha1($row["password"]);
	$viewpass = sha1($row["viewpass"]);
	
	# Check the password
	if (sha1($_POST["pass"]) !== $pass or empty($pass) or empty($_POST["pass"]))
		die("Wrong password or event id!");
		
	# Read the given vote information
	$event = $row["name"];
	$votertable = $_POST["votertable"];
	$subject = $_POST["topic"];
	$desc = $_POST["desc"];
	$time = (int)$_POST["duration"];
	$unit = $_POST["timeunit"];
	$mailbody = "Welcome to use the electronic voting system of IAPS
				in event $event.<br>
				The title for vote is:<br>	$subject<br><br>
				Description/explanation:<br>$desc<br><br>";
	
	# Create the mail headers and body
	$headers = array();
	$headers[] = "MIME-version: 1.0";
	$headers[] = "From: IAPS Voting Machine <voting@iaps.info>";
	$headers[] = "Subject: IAPS Vote, $event: " . $subject;
	$headers[] = "Content-type:text/html;charset=UTF-8";
	$headers = implode("\r\n",$headers);

	# Create tables to the database according to the given names
	$tablename = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/','_',$subject)));
	
	# Index of the last voting event
	$result = mysqli_query($conn,"SELECT id FROM votingtable ORDER BY id DESC LIMIT 1");
	$row = mysqli_fetch_row($result);
	$id = (int)$row[0]+1;
	
	$tablename = "vote".$id."_".$tablename;
	# Get start and end time for the voting event
	$starttime = date("Y-m-d H:i:s");
	$endtime = date("Y-m-d H:i:s",strtotime("+$time $unit"));
		
	# Create the table where to store the results
	$sql = sprintf("CREATE TABLE %s (id INT NOT NULL AUTO_INCREMENT,
				answer varchar(100) NOT NULL,
				votecount INT NOT NULL,
				PRIMARY KEY ( id ))",
				mysqli_real_escape_string($conn,$tablename));
   	if(!mysqli_query($conn,$sql)) die("Error creating the result table");

	# Hash is required to see the results
   	$hash = hash("sha256","$starttime.$votertable");
 	$resulturl = "http://iaps.info/vote/results.php?vote=$tablename&key=$hash";
  	
	# Create a voting event
	$sql = sprintf("INSERT INTO votingtable 
	(name,event,topic,description,votertable,starttime, endtime, open) VALUES ".
	"('%s','%s','%s','%s','%s','%s','%s',true)",
					mysqli_real_escape_string($conn,$tablename),
					mysqli_real_escape_string($conn,(int)$_POST["eventID"]),
					mysqli_real_escape_string($conn,$subject),
					mysqli_real_escape_string($conn,$desc),
					mysqli_real_escape_string($conn,$votertable),
					mysqli_real_escape_string($conn,$starttime),
					mysqli_real_escape_string($conn,$endtime));	
	
   	if(!mysqli_query($conn,$sql)) die("Error modifying the voting table");
   	# Write to logs
   	file_put_contents($logfile,$starttime." ".$sql.PHP_EOL , FILE_APPEND | LOCK_EX);

	# Insert the answer options
	$answeroptions = "";
	for ($i=1; $i <= (int)$_POST["options"]; $i++) {;
		$option = "option" . $i;
		if (!empty($_POST[$option])) {
			$answeroptions .= $_POST[$option] . "<br>";
			$sql = sprintf("INSERT INTO %s (answer, votecount) VALUES ('%s',0)",
							mysqli_real_escape_string($conn,$tablename),
							mysqli_real_escape_string($conn,$_POST[$option]));
			if(!mysqli_query($conn,$sql)) die("Error inserting option $_POST[$option]");
			# Write to logs
			file_put_contents($logfile,$starttime." ".$sql.PHP_EOL , FILE_APPEND | LOCK_EX);
		}
	}
	
	# Inseert option abstain
	$answeroptions .= "Abstain";
	$sql = sprintf("INSERT INTO %s (answer, votecount) VALUES ('Abstain','0')",
					mysqli_real_escape_string($conn,$tablename));
	if(!mysqli_query($conn,$sql)) die("Error inserting option Abstain");
	 # Write to logs
   	file_put_contents($logfile,$starttime." ".$sql.PHP_EOL , FILE_APPEND | LOCK_EX);
				
	
	# Send the voting mail to each voter
	$mailbody .= "Answering options:<br>$answeroptions";
	$mailbody .= "<br>Please vote within $time $unit via link:<br>";
	
	# Update voting keys and given votes in the voter table
	$sql = sprintf("SELECT * FROM %s",
					mysqli_real_escape_string($conn,$votertable));
	$result = mysqli_query($conn,$sql);
	
	while ($row = mysqli_fetch_assoc($result)) {
		# Generate a new id for each voter and each vote
		$key =  rand(1000,9999) . uniqid();
		# URL's to vote as well as see the results
		$url = "http://www.iaps.info/vote/vote.php?vote=$tablename&key=$key";
		$eventurl = "http://iaps.info/vote/?mode=view&event=$_POST[eventID]&key=$viewpass";
		
		$body = $mailbody . "<a href='$url'>$url</a>
		<br><br>After the voting is closed, the results can be seen at:
		<br><a href='$resulturl'>$resulturl</a><br><br>
		See all the votes of the event at link <a href='$eventurl'>$eventurl</a>";
		
		$id = $row["id"];
		$email = $row["email"];
		$sql = "UPDATE $votertable SET voted=false, idkey='$key' WHERE id='$id'";
		if (!mysqli_query($conn,$sql)) die ("Error updating ".$row["name"]);
		
		# Send the voting email to this particular voter
		mail($email,"IAPS Vote: " . $subject,$body,$headers);
	}
	
	# Print the results and instructions
	echo "Voting has started on time $starttime (UTC +0) <br>";
	echo "Voting will end at $endtime (UTC +0) <br>";
	echo "After the closing time, see the results <a href='$resulturl'>here</a>.<br><br>";
	
	echo "<br><form action='index.php?mode=view' method='POST'>";
	echo "<input type='hidden' name='pass' value='".$_POST["pass"]."'>";
	echo "<input type='hidden' name='event' value='".$_POST["eventID"]."'>";
	echo "<input type='submit' name='inspectevent' value='Return to managing the event'></form>";
}

?>

</center>
</body>
</html>
