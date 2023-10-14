<?php
require("logfilereader.php");
require('Trackfile-Lib/TrackfileLoader.php');
@include("config.php");
class TrackLogManager
{
  const FOLDER_TL = 'Tracklogs';

  public static function getSiteFFVL($lat, $lon) {
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

  public function uploadIGCs($tmpfnames, $id = null, $concat) {//ret &$destname, &$fpt
    $ret = array();
    if (count($tmpfnames)<=0) return;
    $arrigc = array();
    for ($i=0; $i<count($tmpfnames); $i++) {
      $igc = TrackfileLoader::load($tmpfnames[$i], 'igc');
      $fpt = $igc->getFirstRecord();
      if ($fpt->date instanceof DateTime) {
        $arrigc[] = array($tmpfnames[$i], $igc->datetime->getTimestamp(), $i);
      }
    }
    if (count($arrigc)<=0) return;
    // tri des IGC par date
    usort($arrigc, function($a, $b) {
      return $a[1]-$b[1];
    });
    /*usort($tmpfnames, function($a, $b) {
      $igca = TrackfileLoader::load($a, 'igc');
      $igcb = TrackfileLoader::load($b, 'igc');
      $igca->setDetails();
      $igcb->setDetails();
      $aduration = !is_int($igca->duration) || $igca->duration<0 ? 0 : $igca->duration;
      $bduration = !is_int($igcb->duration) || $igcb->duration<0 ? 0 : $igcb->duration;
      return $bduration-$aduration;
    });*/
    if ($concat) {
      $firstfile = fopen($arrigc[0][0], 'a+');
      for ($i=1; $i<count($arrigc); $i++) {
        $file2 = file_get_contents($arrigc[$i][0]);
        // suppression des lignes autres que B sinon le fichier IGC résultant aura éventuellement une mauvaise date.
        // cas peu probable car il faut concaténer deux IGC de deux jours différents 
        $file2 = implode("\n", array_filter(explode("\n", $file2), function($val) {return substr(strtoupper(trim($val)), 0, 1) == 'B';}));
        fwrite($firstfile, $file2);
      }
      $curret = $this->uploadIGC($arrigc[0][0], $id);
      $curret->indice = 0;
      $ret[] = $curret;
    } else {
      for ($i=0; $i<count($arrigc); $i++) {
        $curret = $this->uploadIGC($arrigc[$i][0], $id);
        $curret->indice = $arrigc[$i][2];
        $ret[] = $curret;
      }
    }
    return $ret;
  }

  public function uploadIGC($tmpfname, $id = null) {
    $ret = null;
    $fpt = null;
    $tfreader = TrackfileLoader::load($tmpfname, 'igc');
    if (!$tfreader || !($fpt = $tfreader->getFirstRecord())) {
      return (object) ['error' => 'bad IGC file!!!'];
    } else {
      $zone = 'Europe/Paris';
      try
      {
        if (defined('CLETIMEZONEDB')) {
          $xml = new SimpleXMLElement("http://api.timezonedb.com/v2.1/get-time-zone?key=".CLETIMEZONEDB."&format=xml&by=position&lat=".$fpt->latitude."&lng=".$fpt->longitude."&time=".$fpt->date->getTimestamp(), 0, true);
          $zone = (string)$xml->zoneName;
          //gmtOffset
        }
      } catch (Exception $e) {}
      $fpt->date->setTimeZone(new DateTimeZone($zone));

      try
      {
        $lgfr = new LogflyReader();
        $previd = $lgfr->existeVol($fpt->date, $fpt->latitude, $fpt->longitude);
        if ($previd > 0) {
          $igcfname = $lgfr->getIGCFileName($previd);
          return (object) ['error' => "vol ".$previd." déjà existant", 'id' => $previd, 'fpt' => $fpt, 'newvol'=> 0, 'igcfname' => $igcfname];
        }
        $duree = 0;
        if (isset($tfreader->duration))
          $duree = $tfreader->duration;
        $osite = $lgfr->getSite($fpt->latitude, $fpt->longitude);
        if (!$osite)
          $osite = TrackLogManager::getSiteFFVL($fpt->latitude, $fpt->longitude);
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
        if (!$id) {
          $id = $lgfr->addVol($sitenom, $fpt->date->format("d/m/Y"), $fpt->date->format("H:i:s"), $duree, $tfreader->glider_type, "");
        } else {
          $vol = $lgfr->getRecords($id);
          $lgfr->updateVol($id, $sitenom, $fpt->date->format("d/m/Y"), $fpt->date->format("H:i:s"), $duree, $vol->voile, $vol->commentaire);
        }
        if (!$id) {
            return (object) ['error' => "Probleme de mise à jour de la trace avec l'igc. Supprimer le dernier vol et réessayer"];
        } else {
          //$igc = file_get_contents($destname);
          //if (!$lgfr->setIGC($id, $igc)) {
          $basepath = dirname(__FILE__) . DIRECTORY_SEPARATOR . self::FOLDER_TL . DIRECTORY_SEPARATOR . $id;
          $destname = $basepath .".igc";
          if (file_exists($destname)) @unlink($destname);
          if (file_exists($basepath.".json")) @unlink($basepath.".json");
          if (file_exists($basepath.".jpg")) @unlink($basepath.".jpg");
          if (!move_uploaded_file($tmpfname, $destname)) {
            return (object) ['error' => "Probleme de mise à jour de la trace avec l'igc. Supprimer le dernier vol et réessayer"];
          }
          $ret = (object) ['id' => $id, 'newvol'=> $id>0, 'igcfname' => $destname, 'fpt' => $fpt];
        }
      }
      catch(Exception $e)
      {
        return (object) ['error' => "error!!! : ".$e->getMessage()];
      }
    }
    return $ret;
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
            $xml = new SimpleXMLElement("http://api.timezonedb.com/v2.1/get-time-zone?key=".CLETIMEZONEDB."&format=xml&by=position&lat=".$fpt->latitude."&lng=".$fpt->longitude."&time=".$fpt->date->getTimestamp(), 0, true);
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

  public function putFlightScore($id, $flightscore) {
    $path = dirname(__FILE__) . DIRECTORY_SEPARATOR . self::FOLDER_TL . DIRECTORY_SEPARATOR . $id . ".json";
    return @file_put_contents($path, $flightscore);
  }
}
?>