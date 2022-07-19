<?php
exit(0);
require('tracklogmanager.php');
$lgfr = new LogflyReader();
$lgfr->updateVolId(413, 2000);
$lgfr->updateVolId(414, 413);
$lgfr->updateVolId(2000, 414);
exit(0);
require('tracklogmanager.php');
header("Content-Type: text/plain");
$lat = 44.567933333333;
$lon = 6.0191666666667;
//$site = TrackLogManager::getSite(44.91205, 5.5913);
$lgfr = new LogflyReader();
$site = $lgfr->getSite($lat, $lon);
if (!$site)
  $site = TrackLogManager::getSite($lat, $lon);
$dist = 0;
if ($site) {
  $dist = $site['dist'];
  $site = $site['nom'];
}
if ($dist > 1000) {
  echo "site non trouvé";
} else {
  echo "site : " . $site. " à ".round($dist)." m";
}
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