<?php
require("logfilereader.php");
require('Trackfile-Lib/TrackfileLoader.php');
@include("config.php");
class TrackLogManager
{
  const FOLDER_TL = 'Tracklogs';

  public static function getSite($lat, $lon) {
    $sites = @json_decode(@file_get_contents('https://data.ffvl.fr/json/sites.json'));
    $site = "";
    $dist = 1000000000;
    if (is_array($sites)) {
      $distmp = 0;
      for ($i=0; $i<count($sites); $i++) {
        //lat="44.91205" lon="5.5913"
        $distmp = distance($sites[$i]->lat, $sites[$i]->lon, $lat, $lon);
        if ($distmp<$dist) {
          $dist = $distmp;
          $site = $sites[$i];
        }
      }
    }
    if ($dist > 1000) {
      return NULL;
    }
    return ["nom"=> $site->nom, "site"=> $site, "dist"=>$dist];
  }

  public function uploadIGC($tmpfname, $ext, $id = null) {
    $tfreader = TrackfileLoader::load($tmpfname, $ext);
    if (!$tfreader || !($fpt = $tfreader->getFirstRecord())) {
      echo "bad IGC file!!!";
      return FALSE;
    } else {
      $zone = 'Europe/Paris';
      try
      {
        if (defined('CLETIMEZONEDB')) {
          $xml = new SimpleXMLElement("http://api.timezonedb.com/v2.1/get-time-zone?key=".CLETIMEZONEDB."&format=xml&by=position&lat=".$fpt->latitude."&lng=".$fpt->longitude, 0, true);
          $zone = (string)$xml->zoneName;
        }
      } catch (Exception $e) {}
      $fpt->date->setTimeZone(new DateTimeZone($zone));
      $cmpt = 0;
      do {
        $cmpt++;
        $destname = dirname(__FILE__) . DIRECTORY_SEPARATOR . self::FOLDER_TL . DIRECTORY_SEPARATOR . $fpt->date->format("Y-m-d").'-UPL-'.str_pad($cmpt, 2, '0', STR_PAD_LEFT).".igc";
      }
      while (is_file($destname));
      //echo "<pre>$tmpfname\n$destname\n</pre>";
      if (!move_uploaded_file($tmpfname, $destname)) {
        echo "impossible d'uploader le fichier IGC";
      } else {

        try
        {
          $lgfr = new LogflyReader();
          $duree = 0;
          if (isset($tfreader->duration))
            $duree = $tfreader->duration;
          if (!$id) {
            $osite = $lgfr->getSite($fpt->latitude, $fpt->longitude);
            if (!$osite)
              $osite = TrackLogManager::getSite($fpt->latitude, $fpt->longitude);
            $dist = 0;
            $sitenom = NULL;
            $site = NULL;
            if ($osite) {
              $dist = $osite['dist'];
              $site = $osite['site'];
              $sitenom = $osite['nom'];
            }
            if ($sitenom && $lgfr->getInfoSite($sitenom) === FALSE) {
              $lgfr->createSite($sitenom);
              $lgfr->editSite($sitenom, $sitenom, $site->lat, $site->lon, $site->alt);
            }
            $id = $lgfr->addVol($sitenom, $fpt->date->format("d/m/Y"), $fpt->date->format("H:i:s"), $duree, "", "");
          }
          if (!$id) {
              echo "Probleme de mise à jour de la trace avec l'igc. Supprimer le dernier vol et réessayer";
              return FALSE;
          } else {
            $igc = file_get_contents($destname);
            if (!$lgfr->setIGC($id, $igc)) {
              echo "Probleme de mise à jour de la trace avec l'igc. Supprimer le dernier vol et réessayer";
              return FALSE;
            }
            return $id;
          }
        }
        catch(Exception $e)
        {
          echo "error!!! : ".$e->getMessage();
          return FALSE;
        }
      }
    }
  }

  public function getIGC($date) {
    //echo $date->format('Y-m-d H:i:s')."\n"; // DEBUG
    if (!($date instanceof DateTime)) {
      return null;
    }
    $igcs = $this->getIGCs($date);
    if (count($igcs)<=0)
      return null;
    usort($igcs, array( $this, 'cmpigc' ));
    /*foreach ($igcs as &$igc) {
      //return $igc['date'];
      echo $igc['date']->format('Y-m-d H:i:s')." -> ";
      echo $igc['diff']."\n";
    }*/
    return $igcs[0];
  }

  protected function getIGCs($date) {
    $results = [];
    $datefmt = $date->format('Y-m-d');
    //$igcs = glob(FOLDER_TL.'/'.$datefmt.'*.igc', GLOB_BRACE); // pas case insensitive
    $files = scandir(dirname(__FILE__) . DIRECTORY_SEPARATOR . self::FOLDER_TL);

    foreach ($files as $key => $value) {
      $value = trim(strtolower($value));
      if (substr($value, -3) != "igc")
        continue;
      if (substr($value, 0, 10) != $datefmt)
        continue;
      $path = realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . self::FOLDER_TL . DIRECTORY_SEPARATOR . $value);
      if (!is_dir($path)) {
        $tfreader = TrackfileLoader::load($path);
        if (!$tfreader) {
          continue;
        }
        $fpt = $tfreader->getFirstRecord();
        if (!$fpt) {
          continue;
        }
        //$tz = $igc['date']->getTimezone();echo $tz->getName()."\n";
        $localdate = $fpt->date;
        $zone = 'Europe/Paris';
        try
        {
          if (defined('CLETIMEZONEDB')) {
            $xml = new SimpleXMLElement("http://api.timezonedb.com/v2.1/get-time-zone?key=".CLETIMEZONEDB."&format=xml&by=position&lat=".$fpt->latitude."&lng=".$fpt->longitude, 0, true);
            $zone = (string)$xml->zoneName;
          }
        } catch (Exception $e) {}
        $localdate->setTimeZone(new DateTimeZone($zone));
        $results[] = ["path"=>$path, "date"=>$localdate, "diff"=>abs($localdate->getTimestamp()-$date->getTimestamp())];
      }
    }

    return $results;
  }

  private function cmpigc($a, $b) {
    return $a['diff']-$b['diff'];
  }
}
?>