<?php
require("logfilereader.php");
require('Trackfile-Lib/TrackfileLoader.php');
class TrackLogManager
{
  const FOLDER_TL = 'Tracklogs';
  
  public function uploadIGC($tmpfname, $ext, $id = null) {
    $tfreader = TrackfileLoader::load($tmpfname, $ext);
    if (!$tfreader || !($fpt = $tfreader->getFirstRecord())) {
      echo "bad IGC file!!!";
      return FALSE;
    } else {
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
        // TODO : insérer le vol dans la base
        //$fpt->date
        //addVol($nomsite, $date, $heure, $duree, $voile, $commentaire, $id=FALSE)

        try
        {
          $lgfr = new LogflyReader();
          $duree = 0;
          if (isset($tfreader->duration))
            $duree = $tfreader->duration;
          if (!$id) {
            $id = $lgfr->addVol(null, $fpt->date->format("d/m/Y"), $fpt->date->format("H:i:s"), $duree, "", "");
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

  public function getIGC($date, $pt = null) {
    //echo $date->format('Y-m-d H:i:s')."\n"; // DEBUG
    if (!($date instanceof DateTime)) {
      return null;
    }
    $igcs = $this->getIGCs($date, $pt);
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
  
  protected function getIGCs($date, $pt = null) {
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
        $localdate->setTimeZone(new DateTimeZone('Europe/Paris')); // TODO récupérer ça sur le webservice geo via $pt
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