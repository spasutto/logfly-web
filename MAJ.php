<?php

if ($_GET['pouetpwd'] != "toto")
{
    http_response_code(404);
    exit(0);
}
/*

	require("logfilereader.php");

	try
	{
		$lgfr = new LogflyReader();
	}
	catch(Exception $e)
	{
		echo "error!!! : ".$e->getMessage();
		exit(0);
	}
$db = new LogFlyDB();
if(!$db)
  throw new Exception($db->lastErrorMsg());
$sql = "UPDATE VOL SET V_ID=156 WHERE V_ID=1580";
$ret = $db->query($sql);
while($row = $ret->fetchArray(SQLITE3_ASSOC))
echo intval($row['V_ID']).";";
if($db)
  $db->close();
*/
?>
