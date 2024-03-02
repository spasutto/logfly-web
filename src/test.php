<?php
exit(0);return;
const FICHIER_SITES_FFVL = 'sites_ffvl.json';
const URL_SITES_FFVL = 'https://data.ffvl.fr/json/sites.json';
function fetchSitesFFVL() {
  $timestamp = -1;
  $size = 0;
  if (function_exists('curl_version')) {
    try {
      // create a new cURL resource with the url
      $ch = curl_init( URL_SITES_FFVL );     

      // This changes the request method to HEAD
      curl_setopt($ch, CURLOPT_NOBODY, true);
      //stop it from outputting stuff to stdout
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      // attempt to retrieve the modification date
      curl_setopt($ch, CURLOPT_FILETIME, true);
      // Execute curl with the configured options
      $res = curl_exec($ch);
      
      if ($res !== false) {
        // Edit: Fetch the HTTP-code (cred: @GZipp)
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); 

        if ($code >= 200 && $code < 300) {
          //Last-Modified
          $ts = curl_getinfo($ch, CURLINFO_FILETIME);
          if ($ts != -1) { //otherwise unknown
            $timestamp = $ts; 
            //echo date("Y-m-d H:i:s", $timestamp); //etc
          }
          // To check the size/length:
          $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD); 
          
          // To print the content_length:
          //print( "<br>\n $size bytes");
        
          // close cURL resource, and free up system resources
          curl_close($ch);
        }
      }
    } catch(Exception $err) {
      //echo $err;
    }
  }
  $sites = null;
  // si le fichier est différent sur le serveur il faut le mettre à jour en local
  if (!file_exists(FICHIER_SITES_FFVL) || filemtime(FICHIER_SITES_FFVL) != $timestamp || filesize(FICHIER_SITES_FFVL) != $size) {
    if (!file_exists(FICHIER_SITES_FFVL)) echo "le fichier n'existe pas\n";
    else {
      if (filemtime(FICHIER_SITES_FFVL) != $timestamp) echo "le fichier a un timestamp de ".date("Y-m-d H:i:s", filemtime(FICHIER_SITES_FFVL))." alors que celui de la FFVL ".date("Y-m-d H:i:s", $timestamp)."\n";
      if (filesize(FICHIER_SITES_FFVL) != $size) echo "le fichier a un size de ".filesize(FICHIER_SITES_FFVL)." alors que celui de la FFVL ".$size."\n";
    }
    echo "le fichier doit être mis à jour !";
    $sites = @file_get_contents('https://data.ffvl.fr/json/sites.json');
    file_put_contents(FICHIER_SITES_FFVL, $sites);
    touch(FICHIER_SITES_FFVL, $timestamp);
  } else {
    echo "le fichier n'a pas à être mis à jour !";
    $sites = @file_get_contents(FICHIER_SITES_FFVL);
  }
  return @json_decode($sites);
}
header('Content-type: application/json');
fetchSitesFFVL();
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