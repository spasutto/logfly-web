<?php
exit(0);
require("tracklogmanager.php");

try
{
  $lgfr = new LogflyReader();
  $mgr = new TrackLogManager();
}
catch(Exception $e)
{
  echo "error!!! : ".$e->getMessage();
  exit(0);
}

  $vols = $lgfr->getRecords();
  //print_r($vols);
  for ($i=0; $i<$vols->nbvols; $i++) {
    $vol = $vols->vols[$i];
    $igc = $lgfr->getIGC($vol->id);
    $lgfr->setIGC($vol->id, $igc);
/*    if (strlen(trim($igc))>0) {
      echo "already IGC for ".$vol->id."<BR>";
      continue;
    }
    //print_r($vol->date)."\t";
    //print_r($mgr->getIGC($vol->date))."\n";
    $igc = $mgr->getIGC($vol->date);
    if (!$igc) {
      echo "IGC not found for ".$vol->id."<BR>";
      continue;
    }
    $igc = file_get_contents($igc['path']);
    if (strlen(trim($igc))<=0) {
      echo "unable to read IGC for ".$vol->id."<BR>";
      continue;
    }
    echo "set for ".$vol->id." ".($lgfr->setIGC($vol->id, $igc)?"":"X")."<BR>";
*/
  }

?>