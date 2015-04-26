<?php
	define("host", "mysql:host=localhost;dbname=your_db");
	define("user_name", "your_name");
	define("user_pwd", "your_pwd");
	
	function db_connection()
	{
		try
		{
			$link_db = new PDO(host, user_name, user_pwd);
		}
		catch(PDOEcxception $e)
		{
			$link_db = null;
		}
			
		if($link_db!=null)
			$link_db -> query("SET NAMES utf8");
		return $link_db;
	}
?>