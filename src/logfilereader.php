<?php
define("LOGFLYDB", "Logfly.db");
if (!defined('FOLDER_TL')) define("FOLDER_TL", "Tracklogs");
require("logfileutils.php");

class Vol
{
  public $id, $date, $duree, $sduree, $site, $latdeco, $londeco, $commentaire, $voile, $biplace;
}
class Voile
{
  public $nom, $tempsvol, $nombrevols;
}
class InfoVols
{
  public $vols;
  public $nbvols;
  public $tempstotalvol;
  public $voiles;
  public $sites;
  public $datemin;
  public $datemax;
}
class InfoSite
{
  public $nom, $altitude, $latitude, $longitude, $tempsvol, $nombrevols;
}
class LogFlyDB extends SQLite3
{
  function __construct($dbname = LOGFLYDB)
  {
    if (!file_exists($dbname))
      throw new Exception($dbname." not found");
    $this->open($dbname);
  }
}
class LogflyReader
{
  protected $db = FALSE;
  protected $dbname = LOGFLYDB;
  function __construct($dbname = LOGFLYDB)
  {
    $this->dbname = $dbname;
    $this->db = new LogFlyDB($dbname);
    if(!$this->db)
        throw new Exception($this->db->lastErrorMsg());
  }
  function __destruct ()
  {
    if($this->db)
      $this->db->close();
  }

  function getNextID()
  {
    $sql = "SELECT MAX(V_ID)+1 as ID from VOL;";
    $ret = $this->db->query($sql);
    if($row = $ret->fetchArray(SQLITE3_ASSOC))
      return intval($row['ID']);
  }

  function updateVolId($id, $newid)
  {
    $sql = "UPDATE Vol SET V_ID=".$newid." WHERE V_ID=".$id.";";
    //echo $sql."<BR>\n";
    $ret = $this->db->query($sql);
  }

  function deleteVol($id)
  {
    $sql = "DELETE FROM Vol WHERE V_ID=".$id.";";
    $ret = $this->db->query($sql);
    $filename = dirname(__FILE__) . DIRECTORY_SEPARATOR . FOLDER_TL . DIRECTORY_SEPARATOR . $id;
    if (file_exists($filename.".igc.bak")) @unlink($filename.".igc.bak");
    if (file_exists($filename.".igc")) @rename($filename.".igc", $filename.".igc.bak");
    @unlink($filename.".json");
    @unlink($filename.".png");
  }

  function existeVolId($id)
  {
    $sql = "SELECT 1 FROM Vol WHERE V_ID=".$id.";";
    $ret = $this->db->query($sql);
    return $ret->fetchArray(SQLITE3_ASSOC);
  }

  function existeVol($date, $lat, $lon)
  {
    $vols = array();
    $sql = "SELECT V_ID FROM Vol WHERE V_Date='".$date->format('Y-m-d H:i:s')."';";
    $ret = $this->db->query($sql);
    while($row = $ret->fetchArray(SQLITE3_ASSOC))
    {
      $vols[] = $row['V_ID'];
    }
    foreach($vols as $vol) {
      $igc = $this->getIGC($vol);
      if ($igc == "") $igc = $this->getIGC($vol, true);
      if ($igc == "") continue;
      $igc = TrackfileLoader::load($igc, 'igc');
      $fpt = $igc->getFirstRecord();
      if ($fpt->latitude == $lat && $fpt->longitude == $lon) {
        return $vol;
      }
    }
    return -1;
  }
  
  function cleanField($field) {
    return str_replace(array('\\', '\'', '"'), '', $field);
  }

  function updateVol($id, $nomsite, $date, $heure, $duree, $voile, $commentaire, $lat = 0.0, $lon = 0.0, $alt = 0.0, $biplace=0)
  {
    if (!$this->existeVolId($id))
      return $this->addVol($nomsite, $date, $heure, $duree, $voile, $commentaire, $id);
    $heureformat = "H:i:s";
    if (strlen($heure) != 8)
      $heureformat = "H:i";
    $date =  DateTime::createFromFormat('d/m/Y '.$heureformat, $date." ".$heure);
    $nomsite = trim(strtoupper($nomsite));
    $site = $this->getInfoSite($nomsite);
    //echo "<pre>".print_r($site)."</pre>";
    if (!$site)
      $site = $this->createSite($nomsite, $lat, $lon, $alt);
    //echo "<pre>".print_r($site)."</pre>";
    $sduree = Utils::timeFromSeconds($duree, 1);
    //echo print_r($site);
    $voile = trim($this->cleanField($voile));
    $sql = "UPDATE Vol SET V_Score=NULL,V_League=NULL,V_Engin='".$voile."',V_Biplace='".$biplace."',V_CFD=NULL,UTC=0,V_Photos=NULL,V_Commentaire='".str_replace("'", "''", htmlspecialchars_decode($commentaire))."',V_Pays='FRANCE',V_Site='".str_replace("'", "''", $site->nom)."',V_AltDeco='".$site->altitude."',V_LongDeco='".$site->longitude."',V_LatDeco='".$site->latitude."',V_sDuree='".$sduree."',V_Duree=".$duree.",V_Date='".$date->format('Y-m-d H:i:s')."' WHERE V_ID=".$id.";";
    //echo $sql."<BR>\n";
    $ret = $this->db->query($sql);
    if(!$ret)
    {
      echo $this->db->lastErrorMsg();
      return FALSE;
    }
    return TRUE;
  }

  function addVol($nomsite, $date, $heure, $duree, $voile, $commentaire, $id=FALSE, $lat = 0.0, $lon = 0.0, $alt = 0.0, $biplace=0)
  {
    $heureformat = "H:i:s";
    if (strlen($heure) < 8)
      $heureformat = "H:i";
    if (!$id)
      $id = $this->getNextID();
    $date =  DateTime::createFromFormat('d/m/Y '.$heureformat, $date." ".$heure);
    $sitealt = 0;
    $sitelon = 0;
    $sitelat = 0;
    if ($nomsite != null) {
      $nomsite = trim(strtoupper($nomsite));
      $site = $this->getInfoSite($nomsite);
      if (!$site)
        $site = $this->createSite($nomsite, $lat, $lon, $alt);
      $sitealt = $site->altitude;
      $sitelon = $site->longitude;
      $sitelat = $site->latitude;
    } else {
      $nomsite = "";
    }
    $voile = trim($this->cleanField($voile));
    $sduree = Utils::timeFromSeconds($duree, 1);
    $sql = "INSERT INTO Vol (V_Score,V_League,V_Engin,V_Biplace,V_CFD,UTC,V_Photos,V_IGC,V_Commentaire,V_Pays,V_Site,V_AltDeco,V_LongDeco,V_LatDeco,V_sDuree,V_Duree,V_Date,V_ID)\n";
    $sql .= "VALUES (NULL,NULL,'".$voile."',".$biplace.",NULL,0,NULL,NULL,'".str_replace("'", "''", htmlspecialchars_decode($commentaire))."','FRANCE','".str_replace("'", "''", $nomsite)."','".$sitealt."','".$sitelon."','".$sitelat."','".$sduree."',".$duree.",'".$date->format('Y-m-d H:i:s')."',".$id.");";
    //echo $sql."<BR>\n";
    $ret = $this->db->query($sql);
    if(!$ret)
    {
      echo $this->db->lastErrorMsg();
      return FALSE;
    }
    return $this->db->lastInsertRowID();
  }
  
  function permutVol($id1, $id2)
  {
    $this->updateVolId($id1, -1);
    $this->updateVolId($id2, $id1);
    $this->updateVolId(-1, $id2);
    unlink("Tracklogs/".$id1.".jpg");
    unlink("Tracklogs/".$id2.".jpg");
    rename("Tracklogs/".$id1.".json", "Tracklogs/tempid.json");
    rename("Tracklogs/".$id2.".json", "Tracklogs/".$id1.".json");
    rename("Tracklogs/tempid.json", "Tracklogs/".$id2.".json");
    rename("Tracklogs/".$id1.".igc", "Tracklogs/tempid.igc");
    rename("Tracklogs/".$id2.".igc", "Tracklogs/".$id1.".igc");
    rename("Tracklogs/tempid.igc", "Tracklogs/".$id2.".igc");
  }

  function getVoiles($needles='')
  {
    $data = array();
    $sql = "SELECT DISTINCT V_Engin from VOL";
    if (is_array($needles)) {
      $sql .= " WHERE 1=0";
      $cmpt = 0;
      foreach ($needles as $needle) {
        $needle = trim($needle);
        if (strlen($needle)>0) {
          $sql .= " OR V_Engin LIKE '%".str_replace("'", "''", strtoupper($needle))."%'";
          if ($cmpt++ > 5) break;
        }
      }
    } else if (is_string($needles)) {
      $needle = trim($needles);
      if (strlen($needle)>0) {
        $sql .= " WHERE V_Engin LIKE '%".str_replace("'", "''", strtoupper($needle))."%'";
      }
    }
    $sql .= " order by V_Engin;";
    $ret = $this->db->query($sql);
    while($row = $ret->fetchArray(SQLITE3_ASSOC))
    {
      $data[] = $row['V_Engin'];
    }
    return $data;
  }
  function getSites($text='')
  {
    $sites = array();
    $sql = "SELECT DISTINCT S_Nom from SITE";
    if (strlen($text)>0) {
      $sql .= " WHERE S_Nom LIKE '%".str_replace("'", "''", strtoupper($text))."%'";
    }
    $sql .= " order by S_Nom;";
    $ret = $this->db->query($sql);
    while($row = $ret->fetchArray(SQLITE3_ASSOC))
    {
      $sites[] = $row['S_Nom'];
    }
    return $sites;
  }

  function createSite($nom, $lat = 0.0, $lon = 0.0, $alt = 0.0)
  {
    if (!is_float($lat)) $lat = 0.0;
    if (!is_float($lon)) $lon = 0.0;
    if (!is_float($alt)) $alt = 0.0;
    $sql = "INSERT INTO SITE (S_Nom,S_Latitude,S_Longitude,S_Alti) VALUES ('".str_replace("'", "''", strtoupper($nom))."', '".$lat."', '".$lon."', '".$alt."') ";
    //echo $sql."<BR>\n";
    $ret = $this->db->query($sql);

    return $this->getInfoSite($nom);
  }

  function editSite($nom, $newnom, $lat, $lon, $alt)
  {
    $ret2 = FALSE;
    $nom = str_replace("'", "''", strtoupper($nom));
    $newnom = strtoupper(str_replace("'", "''", $newnom));
    $sql = "UPDATE SITE set S_Latitude='".$lat."', S_Longitude='".$lon."', S_Alti='".$alt."', S_Nom='".$newnom."' WHERE S_Nom='".$nom."';";
    //echo $sql."<BR>\n";
    $ret1 = $this->db->query($sql);
    if ($ret1 != FALSE)
    {
      if ($nom != $newnom)
      {
        $sql = "UPDATE VOL set V_Site='".$newnom."' WHERE V_Site='".$nom."';";
        //echo $sql."<BR>\n";
        $ret2 = $this->db->query($sql);
      }
      else
        $ret2 = TRUE;
    }

    return $ret1 != FALSE && $ret2 != FALSE;
  }

  function deleteSite($nom)
  {
    $sql = "DELETE FROM SITE WHERE S_Nom='".str_replace("'", "''", $nom)."';";
    //echo $sql."<BR>\n";
    $ret = $this->db->query($sql);

    return $ret != FALSE;
  }

  function getInfoSite($nom = NULL, $tritemps = FALSE, $datemin=null, $datemax=null, $voile=null, $biplace=null)
  {
    $sites = array();
    //$sql = "SELECT S_Alti, S_Nom, S_Latitude, S_Longitude from SITE WHERE S_Nom= '".$nom."';";
    $sql = "SELECT s.S_Alti, s.S_Nom, s.S_Latitude, s.S_Longitude, SUM(V_Duree) AS TempsVol, COUNT(V_Site) AS NombreVols from SITE s, VOL v WHERE 1=1";
    if ($nom != NULL && is_string($nom))
      $sql.= " AND s.S_Nom='".str_replace("'", "''", strtoupper($nom))."'";
    else
      $sql .= " AND s.S_Nom=v.V_Site";
    if ($voile !== null)
      $sql .= " AND v.V_Engin='".str_replace("'", "''", $voile)."'";
    if ($biplace !== null) {
      $biplace = $biplace>0?1:0;
      $sql .= " AND v.V_Biplace=".$biplace."";
    }
    if ($datemin instanceof DateTime && $datemin !== FALSE)
      $sql .= " AND V_Date>='".$datemin->format('Y-m-d')."'";
    if ($datemax instanceof DateTime && $datemax !== FALSE)
      $sql .= " AND V_Date<='".$datemax->format('Y-m-d')."'";
    $sql .= " group by v.V_Site";
    if ($tritemps)
      $sql .= " ORDER BY TempsVol DESC";
    else
      $sql .= " ORDER BY s.S_Nom";
    if ($nom != NULL && is_string($nom))
      $sql .= " LIMIT 1";
    $sql .= ";";
    //echo $sql."<BR>\n";
    $ret = $this->db->query($sql);
    while($row = $ret->fetchArray(SQLITE3_ASSOC))
    {
      if ($row['S_Nom'] == null)
        continue;
      $site = new InfoSite();
      //$nom, $altitude, $latitude, $longitude;
      $site->nom = $row['S_Nom'];
      $site->altitude = $row['S_Alti'];
      $site->latitude = $row['S_Latitude'];
      $site->longitude = $row['S_Longitude'];
      $site->tempsvol = $row['TempsVol'];
      $site->nombrevols = $row['NombreVols'];
      $sites[] = $site;
    }
    if (count($sites) > 1)
      return $sites;
    else if (count($sites) == 1)
      return $sites[0];
    return FALSE;
  }

  function getSite($lat, $lon, $distance) {
    $sites = $this->getSites();
    $site = "";
    $dist = 1000000000;
    if (is_array($sites)) {
      $distmp = 0;
      for ($i=0; $i<count($sites); $i++) {
        $sitetmp = $this->getInfoSite($sites[$i]);
        if (is_float($sitetmp->latitude) && is_float($sitetmp->longitude))
          $distmp = $distance($sitetmp->latitude, $sitetmp->longitude, $lat, $lon);
        if ($distmp<$dist) {
          $dist = $distmp;
          $site = new \StdClass();
          $site->lat = $sitetmp->latitude;
          $site->lat = $sitetmp->longitude;
          $site->alt = $sitetmp->altitude;
          $site->nom = $sitetmp->nom;
        }
      }
    }
    if ($dist > 1000) {
      return NULL;
    }
    return ["nom"=> $site->nom, "site"=> $site, "dist"=>$dist];
  }

  function getFlightsInfos($id)
  {
    $fi_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . FOLDER_TL . DIRECTORY_SEPARATOR . $id  .".json";
    if (file_exists($fi_file))
      return file_get_contents($fi_file);
    return "";
  }
  
  function getIGCFileName($id) 
  {
    return dirname(__FILE__) . DIRECTORY_SEPARATOR . FOLDER_TL . DIRECTORY_SEPARATOR . $id  .".igc";
  }

  function getIGC($id, $fromdb=false)
  {
    $igc = "";
    if ($fromdb)
    {
      $sql = "SELECT V_IGC FROM VOL WHERE V_ID=".intval($id);
      $ret = $this->db->query($sql);
      $row = $ret->fetchArray();
      $igc = $row['V_IGC'];
    }
    else
    {
      $igc_file = $this->getIGCFileName($id);
      if (file_exists($igc_file))
        $igc = file_get_contents($igc_file);
    }
    return $igc;
  }

  function setIGC($id, $igc = null, $fromdb=false)
  {
    if ($fromdb)
    {
      if ($igc)
      {
        $igc = str_replace("'", "''", $igc);
        $sql = "UPDATE VOL set V_IGC='".$igc."' WHERE V_ID=".$id;
      }
      else
      {
        $sql = "UPDATE VOL set V_IGC=NULL WHERE V_ID=".intval($id);
      }
      $ret = $this->db->query($sql);
      return $ret != FALSE;
    }
    else
    {
      $sql = "UPDATE VOL set V_IGC=NULL WHERE V_ID=".intval($id);
      $ret = $this->db->query($sql);
      $basepath = dirname(__FILE__) . DIRECTORY_SEPARATOR . FOLDER_TL . DIRECTORY_SEPARATOR . $id;
      $igc_file = $basepath  .".igc";
      if ($igc != null)
        return file_put_contents($igc_file, $igc);
      else {
        @unlink($igc_file)&&@unlink($basepath.".json")&&@unlink($basepath.".jpg");
        return true;
      }
    }
  }

  function getComment($id) {
    $sql = "SELECT V_Commentaire FROM VOL WHERE V_ID=".intval($id);
    $ret = $this->db->query($sql);
    $row = $ret->fetchArray();
    return $row['V_Commentaire'];
  }

  function getRecords($id=null, $tritemps = FALSE, $maxres = null, $offset = null, $datemin=null, $datemax=null, $voile=null, $site=null, $text=null, $biplace=null)
  {
    //$this->db->query("delete from SITE where s_nom=''");
    $unvol = FALSE;
    $vols = new InfoVols();
    $vol = FALSE;
    $voilenom = $voile;

    $vols->vols = array();
    $sql = "SELECT %COLUMNS% from VOL LEFT JOIN SITE ON V_Site=S_Nom";
    $cond = "1=1";
    if ($id !== null && preg_match('/^\d+$/', $id))
    {
      $cond .= " AND V_ID=".$id;
      $unvol = TRUE;
    }
    if ($datemin !== null && is_string($datemin))
      $datemin = DateTime::createFromFormat('Y-m-d', $datemin);
    if ($datemax !== null && is_string($datemax)) {
      $datemax = DateTime::createFromFormat('Y-m-d', $datemax);
      $datemax->add(new DateInterval('P1D'));
    }
    if ($datemin instanceof DateTime && $datemin !== FALSE)
      $cond .= " AND V_Date>='".$datemin->format('Y-m-d')."'";
    if ($datemax instanceof DateTime && $datemax !== FALSE)
      $cond .= " AND V_Date<='".$datemax->format('Y-m-d')."'";
    if ($voile !== null)
      $cond .= " AND V_Engin='".str_replace("'", "''", $voile)."'";
    if ($site !== null)
      $cond .= " AND V_Site='".str_replace("'", "''", $site)."'";
    if ($text !== null) {
      $text = $this->db->escapeString($text);
      $text = str_replace("*", "", $text);
      $text = str_replace("%", "", $text);
      $text = str_replace("'", "", $text);
      $text = str_replace("\\", "", $text);
      $texts = explode(" ", $text);
      if (count($texts)>0) {
        $cond .= " AND (";
        $first = true;
        foreach ($texts as &$text) {
          if (!$first) $cond .= " AND "; // OR
          $cond .= "V_Commentaire LIKE '%".$text."%'";
          $first = false;
        }
        $cond .= " )";
      }
    }
    if ($biplace !== null) {
      $biplace = $biplace>0?1:0;
      $cond .= " AND V_Biplace=".$biplace."";
    }
    if (strlen($cond)>3)
      $sql .= " WHERE ".$cond;
    if ($tritemps)
      $sql .= " order by V_ID";
    else
      $sql .= " order by V_Date desc";
    $sqllimit = "";
    if ($maxres>0) {
      $sqllimit = " LIMIT ".$maxres;
      if ($offset>0)
        $sqllimit .= " OFFSET ".$offset;
    }
    //echo str_replace("%COLUMNS%","*",$sql.$sqllimit)."<BR>\n";

    $ret = $this->db->query(str_replace("%COLUMNS%","COUNT(*) as count",$sql));
    $row = $ret->fetchArray();
    $vols->nbvols = $numRows = $row['count'];

    $ret = $this->db->query(str_replace("%COLUMNS%","SUM(V_Duree) as sum",$sql));
    $row = $ret->fetchArray();
    $vols->tempstotalvol = $numRows = $row['sum'];

    $columns = "V_ID,V_Date,V_Duree,V_sDuree,V_Site,S_Latitude,S_Longitude,V_Engin,(V_IGC IS NOT NULL AND TRIM(V_IGC) != '') AS V_IGC, V_Biplace";
    if ($id > 0)
      $columns = $columns.",V_Commentaire";
    else
      $columns = $columns.",(V_Commentaire IS NOT NULL AND TRIM(V_Commentaire) != '') AS V_Commentaire";
    $ret = $this->db->query(str_replace("%COLUMNS%",$columns,$sql.$sqllimit));    $vols->datemin = new DateTime("99999/12/31 00:00:00");
    $vols->datemax = new DateTime("1950/01/01 00:00:00");
    while($row = $ret->fetchArray(SQLITE3_ASSOC))
    {
      $vol = new Vol();
      $vol->id = intval($row['V_ID']);
      $vol->date = DateTime::createFromFormat('Y-m-d H:i:s', $row['V_Date']);
      $vol->duree = intval($row['V_Duree']);
      $vol->sduree = $row['V_sDuree'];
      $vol->site = $row['V_Site'];
      $vol->latdeco = $row['S_Latitude'];//$row['V_LatDeco'];
      $vol->londeco = $row['S_Longitude'];//$row['V_LongDeco'];
      $vol->commentaire = $row['V_Commentaire'];
      $vol->voile = $row['V_Engin'];
      $vol->igc = $row['V_IGC'];
      $vol->biplace = $row['V_Biplace']>0;

      if (!$vol->igc) {
        $igc_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . FOLDER_TL . DIRECTORY_SEPARATOR . $vol->id .".igc";
        $vol->igc = file_exists($igc_file);
      }

      if ($unvol)
        return $vol;

      $vols->vols[] = $vol;
      if ($vol->date < $vols->datemin)
        $vols->datemin = $vol->date;
      if ($vol->date > $vols->datemax)
        $vols->datemax = $vol->date;
    }
    // on arrive ici si l'id n'existe pas
    if ($unvol)
      return FALSE;

    $vols->voiles = array();
    $sql = "SELECT V_Engin, SUM(V_Duree) AS TempsVol, COUNT(1) AS NombreVols from VOL WHERE 1=1";
    if ($site !== null)
      $sql .= " AND V_Site='".str_replace("'", "''", $site)."'";
    if ($biplace !== null) {
      $biplace = $biplace>0?1:0;
      $sql .= " AND V_Biplace=".$biplace."";
    }
    if ($datemin instanceof DateTime && $datemin !== FALSE)
      $sql .= " AND V_Date>='".$datemin->format('Y-m-d')."'";
    if ($datemax instanceof DateTime && $datemax !== FALSE)
      $sql .= " AND V_Date<='".$datemax->format('Y-m-d')."'";
    $sql .= " group by V_Engin order by TempsVol DESC, V_Engin;";
    $ret = $this->db->query($sql);
    while($row = $ret->fetchArray(SQLITE3_ASSOC))
    {
      $voile = new Voile();
      $voile->nom = $row['V_Engin'];
      $voile->tempsvol = intval($row['TempsVol']);
      $voile->nombrevols = intval($row['NombreVols']);
      $vols->voiles[] = $voile;
    }

    $vols->sites = $this->getInfoSite(null, $tritemps, $datemin, $datemax, $voilenom, $biplace);
    return $vols;
  }

  function toUTF16($str)
  {
    return mb_convert_encoding($str, 'UTF-16LE', 'UTF-8');
  }

  function getCSV($stats = FALSE)
  {
    $vols = $this->getRecords(null, TRUE);
    $tempvol = 0;
    $CSVSEP = "\t";
    $CSV = "";
    $CSV .= ("No".$CSVSEP."date".$CSVSEP."voile".$CSVSEP."biplace".$CSVSEP."site".$CSVSEP."duree (en secondes)".$CSVSEP."duree".$CSVSEP."temps de vol total (en secondes)".$CSVSEP."score".$CSVSEP."distance".$CSVSEP."type de parcours");
    if (!$stats)
      $CSV .= ($CSVSEP."commentaire");
    $CSV .= ("\n");
    foreach ($vols->vols as $vol)
    {
      $tempvol += $vol->duree;
      $CSV .= ($vol->id.$CSVSEP);
      $CSV .= ($vol->date->format('d/m/Y H:i:s').$CSVSEP);
      $CSV .= ($vol->voile.$CSVSEP);
      $CSV .= (($vol->biplace?'oui':'non').$CSVSEP);
      $CSV .= ($vol->site.$CSVSEP);
      $CSV .= ($vol->duree.$CSVSEP);
      $CSV .= (Utils::timeFromSeconds($vol->duree, 1).$CSVSEP);
      $CSV .= ($tempvol.$CSVSEP);
      $fi = json_decode($this->getFlightsInfos($vol->id));
      if ($fi)
      {
        $CSV .= (str_replace(".", ",", strval($fi->{'scoreInfo'}->{'score'})).$CSVSEP);
        $CSV .= (str_replace(".", ",", strval($fi->{'scoreInfo'}->{'distance'})).$CSVSEP);
        $CSV .= ($fi->{'opt'}->{'scoring'}->{'name'}.$CSVSEP);
      }
      else
      {
        $CSV .= ($CSVSEP.$CSVSEP.$CSVSEP);
      }
      if (!$stats)
      {
        $textevol = $vol->commentaire?$this->getComment($vol->id):"";
        if (preg_match('/[\n'.$CSVSEP.']/', $textevol))
        {
          $textevol = str_replace($CSVSEP, " ", $textevol);
          $textevol = str_replace("\"", "\"\"", $textevol);
          $textevol = "\"".$textevol."\"";
        }
        $CSV .= ($textevol);
      }
      $CSV .= ("\n");
    }
    return $CSV;
  }

  function downloadCSV($stats = FALSE)
  {
    //header('Content-Type: text');
    header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="carnet'.($stats?"_stats":"").'.csv');
    echo chr(255) . chr(254);
    echo $this->toUTF16($this->getCSV($stats));
    flush();
  }

  function downloadDB()
  {
    $dbsize = filesize($this->dbname);
    $readableStream = fopen($this->dbname, 'rb');
    $writableStream = fopen('php://output', 'wb');

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.LOGFLYDB.'"');
    header('Content-Length: '.$dbsize);
    stream_copy_to_stream($readableStream, $writableStream);
    ob_flush();
    flush();
    @fclose($readableStream);
    @fclose($writableStream);
  }

  function getNbrVols()
  {
    $sql = "SELECT COUNT(1) AS NombreVols from VOL v";
    $ret = $this->db->query($sql);
    if($row = $ret->fetchArray(SQLITE3_ASSOC))
      return intval($row['NombreVols']);
    return null;
  }
  function getStats()
  {
    $stats = array();
    $sql = "SELECT SUM(V_Duree) AS TempsVol, COUNT(1) AS NombreVols, strftime(\"%Y\", V_Date) AS Annee from VOL v GROUP BY strftime(\"%Y\", V_Date)";
    $ret = $this->db->query($sql);
    while($row = $ret->fetchArray(SQLITE3_ASSOC))
      $stats[$row['Annee']] = (object) ['TempsVol' => intval($row['TempsVol']), 'NombreVols' => intval($row['NombreVols'])];
    return $stats;
  }
  
  function upgradeDB() {
    //$this->db->query("UPDATE VOL SET V_Biplace=1 WHERE V_engin='Dual 2 42'");
    $found = false;
    $ret = $this->db->query("PRAGMA table_info('VOL');");
    while($row = $ret->fetchArray(SQLITE3_ASSOC)) {
      if ($row["name"] == "V_Biplace") {
        $found = true;
        break;
      }
      //var_dump($row);
    }
    if ($found) {
      echo "carnet déjà mis à jour";
      exit(0);
    } else {
      $this->db->query("ALTER TABLE VOL ADD V_Biplace INTEGER;");
      echo "carnet mis à jour";
    }
  }
  
  function downgradeDB() {
    //CREATE TABLE Vol (V_ID integer NOT NULL PRIMARY KEY, V_Date TimeStamp, V_Duree integer, V_sDuree varchar(20), V_LatDeco double, V_LongDeco double, V_AltDeco integer, V_Site varchar(100), V_Pays varchar(50), V_Commentaire Long Text, V_IGC Long Text, V_Photos Long Text,UTC integer, V_CFD integer,V_Engin Varchar(10), V_League integer, V_Score Long Text, V_Biplace INTEGER)
    //CREATE TABLE Site(S_ID integer NOT NULL primary key,S_Nom varchar(50),S_Localite varchar(50),S_CP varchar(8),S_Pays varchar(50),S_Type varchar(1),S_Orientation varchar(20),S_Alti varchar(12),S_Latitude double,S_Longitude double,S_Commentaire Long Text,S_Maj varchar(10))
  }
}
?>