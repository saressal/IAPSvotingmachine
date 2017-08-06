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
<h3>IAPS voting machine: Create/inspect tables of voters</h3>
<?php

// Connect to the database
require "connect.php";

function checkPass($conn,$adminpass,$pass,$table,$mode) {
	# A function for checking the password
	# Either an admin passoword or a corresponding event password is required
	
	$ok = false;
	
	if ($pass === sha1($adminpass)) $ok = true;

	if (!empty($table)) {	
		if ($mode) {
			# Check by event ID
			$sql = sprintf("SELECT password FROM events where id = '%s'",
				mysqli_real_escape_string($conn,$table));
			$result = mysqli_query($conn,$sql);
			$row = mysqli_fetch_row($result);
			
			if ($pass === sha1($row[0])) $ok = true;
		} else {
			# Check by voter table name
			$sql = sprintf("SELECT password FROM events where votertable = '%s'",
					mysqli_real_escape_string($conn,$table));
			$result = mysqli_query($conn,$sql);
				
			while ($row = mysqli_fetch_row($result)) {
				if ($pass === sha1($row[0])) $ok = true;
			}
		}
	}
	return $ok;
}


if(!count($_POST)) die("Wrong input");
else {
	if ($_POST["createinspect"] === "inspect") {
		# Select the voter table to inspect

		if (!checkPass($conn,$adminpass,sha1($_POST["key"]),$_POST["votertable"],false)) die("Wrong password!");
		
		echo "<b>Select the voter table to inspect</b>";
		echo "<form action='$_SERVER[PHP_SELF]' method='POST'>";
		echo "<select name='votertable' required>";
		
		# Get the table names from the database
		$result = mysqli_query($conn,"SELECT * FROM votertables");
		while($row = mysqli_fetch_assoc($result)) {
			echo $row . "<br>";
			echo "<option value='".$row["longname"]."'>".$row["name"]."</option>";
		}
		echo "</select><br><br>";
		
		# Pass the hidden values
		echo "<input type='hidden' name='key' value='".$_POST["key"]."'>";		
		echo "<input type='submit' name='inspecttable'></form>";
	}
	
	if (isset($_POST["inspecttable"])) {
		# Showing the voter table and a possibility to modify it
		if (!checkPass($conn,$adminpass,sha1($_POST["key"]),$_POST["votertable"],false)) die("Wrong password!");

		$sql = sprintf("SELECT name, email, status,idkey FROM %s",
			mysqli_real_escape_string($conn,$_POST["votertable"]));
			
		$result = mysqli_query($conn,$sql);
		echo "<form action='$_SERVER[PHP_SELF]' method='POST'>";
		echo "<table rules='all'>";
		echo "<tr><td><b>Name</b></td><td><b>Email</b></td><td><b>Status</b></td>
			<td><b>Modify</b></td></tr></tr>";
		while ($row = mysqli_fetch_row($result)) {
			echo "<tr><td>$row[0]</td><td>$row[1]</td><td>$row[2]</td>
			<td><input type='radio' value='$row[3]' name='modifyID' required></td></tr>";
		}
		echo "</table>";
		echo "Add a new voter <input type='radio' value='new' name='modifyID' required><br><br>";
		echo "<input type='hidden' name='key' value='".$_POST["key"]."'>";		
		echo "<input type='hidden' name='votertable' value='".$_POST["votertable"]."'>";		
		echo "<input type='submit' name='modify' value='Modify'></form>";
	
		echo "<br><br><a href='http://iaps.info/vote/?mode=listevents'><b>Back to the event management</b></a>";
	}
	
	if (isset($_POST["modify"])) {
		# Modify a voter
		if (!checkPass($conn,$adminpass,sha1($_POST["key"]),$_POST["votertable"],false)) die("Wrong password!");
		
		echo "<b>Edit a voter</b><br>";
		echo "Set the name to DELETE to remove the voter.";
		if($_POST["modifyID"] === "new") {
			# New voter, no need to 
			$name = "";
			$email = "";
			$status = "";
		} else {
			$sql = sprintf("SELECT name, email, status FROM %s WHERE idkey = '%s'",
				mysqli_real_escape_string($conn,$_POST["votertable"]),
				mysqli_real_escape_string($conn,$_POST["modifyID"]));	
			$result = mysqli_query($conn,$sql);
			$row = mysqli_fetch_assoc($result);
			$name = $row["name"];
			$email = $row["email"];
			$status = $row["status"];
		}
		
		echo "<form action='$_SERVER[PHP_SELF]' method='POST'>";
		echo "<table rules='all'>";
		echo "<tr><td><b>Name</b></td><td><b>Email</b></td><td><b>Status</b></td></tr>";
		echo "<tr><td><input type='text' name='modifyName' value='$name' size='40' maxlength='90' required></td>
				<td><input type='email' name='modifyEmail' value='$email' size='40' maxlength='90' required></td>
				<td><select name='modifyStatus'>";
		echo "<option value='NC'" . ($status === 'NC' ? "selected": "") .">National Committee</option>";
		echo "<option value='LC'" . ($status === 'LC' ? "selected": "") .">Local Committee</option>";
		echo "<option value='IM'" . ($status === 'IM' ? "selected": "") .">Individual Member</option>";
		echo "</select></td></tr>";

		echo "</table><br><br>";
		echo "<input type='hidden' name='key' value='".$_POST["key"]."'>";		
		echo "<input type='hidden' name='votertable' value='".$_POST["votertable"]."'>";
		echo "<input type='hidden' name='modifyID' value='".$_POST["modifyID"]."'>";
		echo "<input type='submit' name='modified' value='Save changes'></form>";
	}
	
	if (isset($_POST["modified"])) {
		# Save the modification in the table
		if (!checkPass($conn,$adminpass,sha1($_POST["key"]),$_POST["votertable"],false)) die("Wrong password!");
		
		if ($_POST["modifyID"] === "new") {
			$key = rand(1000,9999) . uniqid();
			$sql = sprintf("INSERT INTO %s (name,email,status,idkey) VALUES ('%s','%s','%s','%s')",			
				mysqli_real_escape_string($conn,$_POST["votertable"]),
				mysqli_real_escape_string($conn,$_POST["modifyName"]),
				mysqli_real_escape_string($conn,$_POST["modifyEmail"]),
				mysqli_real_escape_string($conn,$_POST["modifyStatus"]),
				mysqli_real_escape_string($conn,$key));

		} else {
			if ($_POST["modifyName"] === "DELETE") {
				$sql = sprintf("DELETE FROM %s WHERE idkey='%s'",
					mysqli_real_escape_string($conn,$_POST["votertable"]),
					mysqli_real_escape_string($conn,$_POST["modifyID"]));
			} else {
				$sql = sprintf("UPDATE %s SET name='%s', email='%s', status='%s' WHERE idkey='%s'",
					mysqli_real_escape_string($conn,$_POST["votertable"]),
					mysqli_real_escape_string($conn,$_POST["modifyName"]),
					mysqli_real_escape_string($conn,$_POST["modifyEmail"]),
					mysqli_real_escape_string($conn,$_POST["modifyStatus"]),
					mysqli_real_escape_string($conn,$_POST["modifyID"]));
				}
		}

		if (!mysqli_query($conn,$sql)) die("Something went wrong in saving the modification.");
		$timenow = date("Y-m-d H:i:s");
		file_put_contents($logfile,$timenow." ".$sql.PHP_EOL , FILE_APPEND | LOCK_EX);
		
		echo "The modification was saved.<br><br>";
		echo "<form action='$_SERVER[PHP_SELF]' method='POST'>";
		echo "<input type='hidden' name='key' value='".$_POST["key"]."'>";		
		echo "<input type='hidden' name='votertable' value='".$_POST["votertable"]."'>";
		echo "<input type='submit' name='inspecttable' value='Return to the voter table'></form>";
	}	
	
	if ($_POST["createinspect"] === "create" and isset($_POST["createnew"])) {
		if (!checkPass($conn,$adminpass,sha1($_POST["key"]),$_POST["eventID"],true)) die("Wrong password!");

		if (isset($_POST["voters"]) and $_POST["voters"] <= 100) {
			$n = (int)$_POST["voters"];
		} else {
			echo "Number of voters not set, using default value of 20.";
			$n = 20;
		}
		echo "<form action='$_SERVER[PHP_SELF]' method='POST'>";
		echo "<fieldset><legend><b>Input each voter individually</b></legend>";
		echo "The voter will be registered only if both name and email fields are filled.<br><br>";
		echo "<b>Name of the voter table</b> <input type='text' name='tablename' required><br>";
		echo "<table>";
		echo "<tr><td><b>Name</b></td><td><b>Email</b></td><td><b>Status</b></td></tr>";

		for ($i=1;$i<=$n;$i++) {
			echo "<tr><td><input type='text' name='voter".$i."name' size='40' maxlength='90'></td>";
			echo "<td><input type='email' name='voter".$i."email' size='40' maxlength='90'></td>";
			echo "<td><select name='voter".$i."status'>";
			echo "<option value='NC'>National Committee</option>";
			echo "<option value='LC'>Local Committee</option>";
			echo "<option value='IM'>Individual Member</option></select>";
			echo "</td></tr>";
		}
		echo "</table></fieldset><br>";
		echo "<input type='hidden' name='eventID' value='".$_POST["eventID"]."'>";
		echo "<input type='hidden' name='key' value='".$_POST["key"]."'>";
		echo "<input type='hidden' name='maxvoters' value='$n'>";
		echo "<input type='submit' name='createtable'></form>";
	}
	
	if (isset($_POST["createtable"])) {
		if (!checkPass($conn,$adminpass,sha1($_POST["key"]),$_POST["eventID"],true)) die("Wrong password!");

		$tablename = $_POST["tablename"];
		
		$result = mysqli_query($conn,"SELECT id FROM votertables ORDER BY id DESC LIMIT 1");
		$row = mysqli_fetch_row($result);
		$id = (int)$row[0]+1;
		
		$longname = "voters".$id."_".$tablename;

		$sql = sprintf("CREATE TABLE %s (id INT NOT NULL AUTO_INCREMENT,
					   name VARCHAR(100) NOT NULL,
					   email VARCHAR(100) NOT NULL,
					   status VARCHAR(2) NOT NULL,
					   idkey VARCHAR(26) NOT NULL,
					   voted BOOL NOT NULL,
					   PRIMARY KEY ( id ))",
					   	mysqli_real_escape_string($conn,$longname));		
		if (!mysqli_query($conn,$sql)) die("Error creating the voter table");
		
		# Insert the entry into list of voter tables
		$sql = sprintf("INSERT INTO votertables (name,longname) VALUES ('%s','%s')",
						mysqli_real_escape_string($conn,$tablename),		
						mysqli_real_escape_string($conn,$longname));				
		if (!mysqli_query($conn,$sql)) die("Error inserting the voter table into list of tables");
		$timenow = date("Y-m-d H:i:s");
		file_put_contents($logfile,$timenow." ".$sql.PHP_EOL , FILE_APPEND | LOCK_EX);
		
		# Insert the voter table name into the event
		$sql = sprintf("SELECT votertable FROM events WHERE id='%s'",
						mysqli_real_escape_string($conn,$_POST["eventID"]));
		$result = mysqli_query($conn,$sql);
		$row = mysqli_fetch_row($result);
		# Only change the name if it has not been set earlier
		if ($row[0] === "new") {
			$sql = sprintf("UPDATE events SET votertable='%s' WHERE id='%s'",
							mysqli_real_escape_string($conn,$longname),		
							mysqli_real_escape_string($conn,$_POST["eventID"]));
			echo $sql . "<br>";			
			if (!mysqli_query($conn,$sql)) die("Error modifying the event");
		}
		
		# Insert each voter into the table separately
		$maxvoters = (int)$_POST["maxvoters"];
		for ($i=1; $i <= $maxvoters; $i++) {;
			if (!empty($_POST["voter" . $i . "name"]) and 
				!empty($_POST["voter" . $i . "email"])) {
					
				$name = $_POST["voter" . $i . "name"];
				$email = $_POST["voter" . $i . "email"];
				$status = $_POST["voter" . $i . "status"];
				$key =  rand(1000,9999) . uniqid();
				
				$sql = sprintf("INSERT INTO %s (name,email,status,idkey,voted) 
				VALUES ('%s','%s','%s','%s',false)",
						mysqli_real_escape_string($conn,$longname),	
						mysqli_real_escape_string($conn,$name),	
						mysqli_real_escape_string($conn,$email),	
						mysqli_real_escape_string($conn,$status),	
						mysqli_real_escape_string($conn,$key));
				
				if(!mysqli_query($conn,$sql)) die("Error inserting $name into the voter table-");
			}
		}
		echo "Voting table created successfully.";
		echo "<form action='$_SERVER[PHP_SELF]' method='POST'>";
		echo "<input type='hidden' name='key' value='".$_POST["key"]."'>";
		echo "<input type='hidden' name='eventID' value='".$_POST["eventID"]."'>";		
		echo "<input type='hidden' name='votertable' value='".$longname."'>";
		echo "<input type='submit' name='inspecttable' value='Check the table'></form>";
	}
}

?>

</center>
</body>
</html>
