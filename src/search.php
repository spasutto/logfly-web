<?php
//search.php?type=voiles
  require("logfilereader.php");
  try
  {
    $lgfr = new LogflyReader();
  }
  catch(Exception $e)
  {
    $msg=str_replace("\"", "\\\"", $e->getMessage());
    echo "{\"text\":\"error!!! : ".$msg."\"}";
    exit(0);
  }

  if (!isset($_REQUEST['type']))
    exit(0);

  $type = $_REQUEST['type'];
  $needle = strtolower($_REQUEST['s']);
  $needles = explode(" ", $needle);

  switch ($type) {
    case 'sites':
      echo "[";
      $so = 0;
      $sites = $lgfr->getSites($needle);
      for ($i=0; $i<count($sites); $i++) {
        //if (strpos(strtolower($sites[$i]), $needle) === false) continue;
        if ($so>0) echo ",";
        echo "\"".$sites[$i]."\"";
        $so++;
      }
      echo "]";
      break;
    case 'voiles':
      echo "[";
      $so = 0;
      $voiles = $lgfr->getVoiles($needles);
      for ($i=0; $i<count($voiles); $i++) {
        //if (strpos(strtolower($voiles[$i]), $needle) === false) continue;
        if ($so>0) echo ",";
        echo "\"".$voiles[$i]."\"";
        $so++;
      }
      echo "]";
      break;
  }
?>