<?php
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

  if (isset($_GET['csv'])) {
    $lgfr->downloadCSV(FALSE);
  } else if (isset($_GET['fulldb'])) {
    header("Location: admin.php?insert_igc");
    exit(0);
  } else {
    $lgfr->downloadDB();
  }
?>