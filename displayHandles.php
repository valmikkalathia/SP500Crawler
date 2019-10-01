<?php

	include_once 'dbh.php';

	if(isset($_POST['get_company']))
	{

		$comp = $_POST['get_company'];
		$options2=$conn->query("select handle from 500List where CompanyName='$comp';");

		while($row=$options2->fetch_assoc())
		{
		   echo "<option>".$row['handle']."</option>";
		}

		exit;

	}
	
?>

