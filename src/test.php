<?php
exit(0);return;
require("config.php");
      try
      {
        $latitude = 45.5;
        $longitude = 5.5;
        $date = new DateTime('2000-01-01', new DateTimeZone('Pacific/Nauru'));
        if (defined('CLETIMEZONEDB')) {
          $url = "http://api.timezonedb.com/v2.1/get-time-zone?key=".CLETIMEZONEDB."&format=xml&by=position&lat=".$latitude."&lng=".$longitude."&time=".$date->getTimestamp();
          echo $url." ";
          $xml = new SimpleXMLElement($url, 0, true);
          echo "<pre>".str_replace("<", "&lt;", str_replace(">", "&gt;", (string)$xml->asXML()))."</pre>";
          echo " done";
        } else echo "oups";
      } catch (Exception $e) {print_r($e);}
//echo json_encode("bonjour");
exit(0);return;
$mavar = (object) ['id' => 12, 'libelle' => 'Bonjour'];
$mavar->indice = 29;
print_r($mavar);
exit(0);return;
require('tracklogmanager.php');
$lgfr = new LogflyReader();
$lgfr->permutVol(529, 527);
exit(0);
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