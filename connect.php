<?php

$servername = "localhost";
$username = "votermachine";
$password = "galileo_galilei";
$dbname = "votes";
$conn = mysqli_connect($servername, $username, $password, $dbname);
$logfile = "logs.txt";
if (! $conn) {
	echo "Connection to database failed";
	exit;
}
# Passwords
$adminpass = sha1("a"); #"a";
	//echo "<table>";
    //foreach ($_POST as $key => $value) {
        //echo "<tr>";
        //echo "<td>";
        //echo $key;
        //echo "</td>";
        //echo "<td>";
        //echo $value;
        //echo "</td>";
        //echo "</tr>";
    //}
	//echo "</table>";
?>
