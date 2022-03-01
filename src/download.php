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
  } else {
    $lgfr->downloadDB();
  }
?>