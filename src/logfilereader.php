<?php
define("LOGFLYDB", "Logfly.db");
define("FOLDER_TL", "Tracklogs");
require("logfileutils.php");

class Vol
{
  public $id, $date, $duree, $sduree, $site, $latdeco, $londeco, $commentaire, $voile;
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
  function __construct($dbname = LOGFLYDB)
  {
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

  function deleteVol($id)
  {
    $sql = "DELETE FROM Vol WHERE V_ID=".$id.";";
    $ret = $this->db->query($sql);
    $igc_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . FOLDER_TL . DIRECTORY_SEPARATOR . $id  .".igc";
    if (file_exists($igc_file.".bak")) @unlink($igc_file.".bak");
    if (file_exists($igc_file)) @rename($igc_file, $igc_file.".bak");
  }

  function existeVol($id)
  {
    $sql = "SELECT 1 FROM Vol WHERE V_ID=".$id.";";
    $ret = $this->db->query($sql);
    return $ret->fetchArray(SQLITE3_ASSOC);
  }

  function updateVol($id, $nomsite, $date, $heure, $duree, $voile, $commentaire)
  {
    if (!$this->existeVol($id))
      return $this->addVol($nomsite, $date, $heure, $duree, $voile, $commentaire, $id);
    $heureformat = "H:i:s";
    if (strlen($heure) != 8)
      $heureformat = "H:i";
    $date =  DateTime::createFromFormat('d/m/Y '.$heureformat, $date." ".$heure);
    $nomsite = strtoupper($nomsite);
    $site = $this->getInfoSite($nomsite);
    //echo "<pre>".print_r($site)."</pre>";
    if (!$site)
      $site = $this->createSite($nomsite);
    //echo "<pre>".print_r($site)."</pre>";
    $sduree = Utils::timeFromSeconds($duree, TRUE);
    //echo print_r($site);
    $sql = "UPDATE Vol SET V_Score=NULL,V_League=NULL,V_Engin='".$voile."',V_CFD=NULL,UTC=0,V_Photos=NULL,V_Commentaire='".str_replace("'", "''", htmlspecialchars_decode($commentaire))."',V_Pays='FRANCE',V_Site='".str_replace("'", "''", $site->nom)."',V_AltDeco='".$site->altitude."',V_LongDeco='".$site->longitude."',V_LatDeco='".$site->latitude."',V_sDuree='".$sduree."',V_Duree=".$duree.",V_Date='".$date->format('Y-m-d H:i:s')."' WHERE V_ID=".$id.";";
    //echo $sql."<BR>\n";
    $ret = $this->db->query($sql);
    if(!$ret)
    {
      echo $this->db->lastErrorMsg();
      return FALSE;
    }
    return TRUE;
  }

  function addVol($nomsite, $date, $heure, $duree, $voile, $commentaire, $id=FALSE)
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
      $nomsite = strtoupper($nomsite);
      $site = $this->getInfoSite($nomsite);
      if (!$site)
        $site = $this->createSite($nomsite);
      $sitealt = $site->altitude;
      $sitelon = $site->longitude;
      $sitelat = $site->latitude;
    } else {
      $nomsite = "";
    }
    $sduree = Utils::timeFromSeconds($duree, TRUE);
    $sql = "INSERT INTO Vol (V_Score,V_League,V_Engin,V_CFD,UTC,V_Photos,V_IGC,V_Commentaire,V_Pays,V_Site,V_AltDeco,V_LongDeco,V_LatDeco,V_sDuree,V_Duree,V_Date,V_ID)\n";
    $sql .= "VALUES (NULL,NULL,'".$voile."',NULL,0,NULL,NULL,'".str_replace("'", "''", htmlspecialchars_decode($commentaire))."','FRANCE','".str_replace("'", "''", $nomsite)."','".$sitealt."','".$sitelon."','".$sitelat."','".$sduree."',".$duree.",'".$date->format('Y-m-d H:i:s')."',".$id.");";
    //echo $sql."<BR>\n";
    $ret = $this->db->query($sql);
    if(!$ret)
    {
      echo $this->db->lastErrorMsg();
      return FALSE;
    }
    return $this->db->lastInsertRowID();
  }

  function getSites()
  {
    $sites = array();
    $sql = "SELECT S_Nom from SITE order by S_Nom;";
    $ret = $this->db->query($sql);
    while($row = $ret->fetchArray(SQLITE3_ASSOC))
    {
      $sites[] = $row['S_Nom'];
    }
    return $sites;
  }

  function createSite($nom)
  {
    $sql = "INSERT INTO SITE (S_Nom) VALUES ('".str_replace("'", "''", strtoupper($nom))."') ";
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
    echo $sql."<BR>\n";
    $ret = $this->db->query($sql);

    return $ret != FALSE;
  }

  function getInfoSite($nom = NULL, $tritemps = FALSE, $datemin=null, $datemax=null, $voile=null)
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

  function getSite($lat, $lon) {
    $sites = $this->getSites();
    $site = "";
    $dist = 1000000000;
    if (is_array($sites)) {
      $distmp = 0;
      for ($i=0; $i<count($sites); $i++) {
        $sitetmp = $this->getInfoSite($sites[$i]);
        $distmp = distance($sitetmp->latitude, $sitetmp->longitude, $lat, $lon);
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

  function getIGC($id, $fromdb=false)
  {
    if ($fromdb)
    {
      $sql = "SELECT V_IGC FROM VOL WHERE V_ID=".intval($id);
      $ret = $this->db->query($sql);
      $row = $ret->fetchArray();
      $igc = $row['V_IGC'];
    }
    else
    {
      $igc_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . FOLDER_TL . DIRECTORY_SEPARATOR . $id  .".igc";
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
      $igc_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . FOLDER_TL . DIRECTORY_SEPARATOR . $id  .".igc";
      if ($igc != null)
        return file_put_contents($igc_file, $igc);
      else
        return unlink($igc_file);
    }
  }

  function getComment($id) {
    $sql = "SELECT V_Commentaire FROM VOL WHERE V_ID=".intval($id);
    $ret = $this->db->query($sql);
    $row = $ret->fetchArray();
    return $row['V_Commentaire'];
  }

  function getRecords($id=null, $tritemps = FALSE, $maxres = null, $offset = null, $datemin=null, $datemax=null, $voile=null, $site=null)
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

    $columns = "V_ID,V_Date,V_Duree,V_sDuree,V_Site,S_Latitude,S_Longitude,V_Engin,(V_IGC IS NOT NULL AND TRIM(V_IGC) != '') AS V_IGC";
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

    $vols->sites = $this->getInfoSite(null, $tritemps, $datemin, $datemax, $voilenom);
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
    $CSV .= ("No".$CSVSEP."date".$CSVSEP."voile".$CSVSEP."site".$CSVSEP."duree (en secondes)".$CSVSEP."duree".$CSVSEP."temps de vol total (en secondes)".$CSVSEP."score".$CSVSEP."distance".$CSVSEP."type de parcours");
    if (!$stats)
      $CSV .= ($CSVSEP."commentaire");
    $CSV .= ("\n");
    foreach ($vols->vols as $vol)
    {
      $tempvol += $vol->duree;
      $CSV .= ($vol->id.$CSVSEP);
      $CSV .= ($vol->date->format('d/m/Y H:i:s').$CSVSEP);
      $CSV .= ($vol->voile.$CSVSEP);
      $CSV .= ($vol->site.$CSVSEP);
      $CSV .= ($vol->duree.$CSVSEP);
      $CSV .= (Utils::timeFromSeconds($vol->duree, TRUE).$CSVSEP);
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
    $readableStream = fopen(LOGFLYDB, 'rb');
    $writableStream = fopen('php://output', 'wb');

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.LOGFLYDB.'"');
    stream_copy_to_stream($readableStream, $writableStream);
    ob_flush();
    flush();
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
}
?>