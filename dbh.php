<?php

	$host="localhost";
	$user="webuser";
	$pass="creative";
	$dbname="webdata";
	$conn= new mysqli($host,$user,$pass,$dbname);
	
	if($conn->errno !=0) {
		echo "Connection to database FAILED: " .$conn->error.PHP_EOL ;
		exit(0);
	} 
	
	//echo "Connection SUCCESS";

?>
