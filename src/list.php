<?php
require("config.php");

const ELEVATIONSERVICE = "elevation/getElevation.php";

$url =  "//{$_SERVER['HTTP_HOST']}".dirname($_SERVER['PHP_SELF'])."/";
$root_url = htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
parse_str($_SERVER["QUERY_STRING"]  , $get_array);//print_r($get_array);
//phpinfo();return;
//TODO : WEAK
if (isset($_GET['sites'])) {
  require("tracklogmanager.php");
  header('Content-Type: application/json; charset=utf-8');
  header("Cache-Control: max-age=86400");

  echo TrackLogManager::fetchSitesFFVL(true);
  exit(0);
}

require("logfilereader.php");

try
{
  $lgfr = new LogflyReader();
}
catch(Exception $e)
{
  echo "error!!! : ".$e->getMessage();
  exit(0);
}

$volid=null;
$voile=null;
$site=null;
$datemin=NULL;
$datemax=NULL;
$resperpage=25;
$offset=0;
$text=NULL;
if (isset($_GET['voile'])) {
  $voile = $_GET['voile'];
}
if (isset($_GET['biplace'])) {
  $biplace = $_GET['biplace'] == "1";
}
if (isset($_GET['site'])) {
  $site = $_GET['site'];
}
if (isset($_GET['datemin'])) {
  $datemin = $_GET['datemin'];
  $datemin = DateTime::createFromFormat('Y-m-d', $datemin);
  if (!($datemin instanceof DateTime) || $datemin == FALSE)
  $datemin = FALSE;
}
if (isset($_GET['datemax'])) {
  $datemax = $_GET['datemax'];
  $datemax = DateTime::createFromFormat('Y-m-d', $datemax);
  if (!($datemax instanceof DateTime) || $datemax == FALSE)
    $datemax = FALSE;
  else
    $datemax->add(new DateInterval('P1D'));
}
if (isset($_GET['vol'])) {
  $volid = @intval($_GET['vol']);
  if (!is_int($volid) || $volid < 0) $volid = 0;
} else if (isset($_GET['offset'])) {
  $offset = @intval($_GET['offset']);
  if (!is_int($offset) || $offset < 0) $offset = 0;
}
if (isset($_GET['text'])) {
  $text = $_GET['text'];
}

$gdrive_upload_script = "gdrive/upload.php";
$gdrive = file_exists($gdrive_upload_script);

function url_with_parameter($paramname, $paramvalue, $paramtoremove = null) {
  global $get_array;
  $url = $_SERVER["SCRIPT_NAME"]."?";
  if (!$paramtoremove) $paramtoremove = [];
  else if (!is_array($paramtoremove)) $paramtoremove = [$paramtoremove];
  foreach ($get_array as $key => $value) {
    if (in_array($key, $paramtoremove)) continue;
    if ($key == $paramname) continue;
    $url.=$key."=".urlencode($value)."&";
  }
  $url .= $paramname."=".urlencode($paramvalue);
  return $url;
}
?><!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>Carnet de vol</title>
  <meta name="viewport" content="initial-scale=0.75, maximum-scale=1.0, user-scalable=no" />
  <base href="<?php echo $root_url;?>">
  <style type="text/css">
  html {
    background-color: #1a88a71f;
  }
  body {
    line-height:22px;
    font-family: sans-serif, monospace;
    margin:0;
    padding:0;
    max-width: 1024px;
    background-color: white;
  }
  header {
    background-color: #1a73a7;
    padding:0 3px;
    position: sticky;
    top:0;
  }
  header a {
    color: #beed00;
  }
  .main {
   padding: 3px;
   overflow-y: auto;
  }
  @media only screen and ((min-width: 1024px) and (min-height: 950px)) {
  /*@media only screen and (min-width: 1024px) {*/
    .main {
      max-height: 768px;
    }
  }
  footer {
    text-align: center;
    font-size: 9pt;
  }
  h1 {
    text-decoration: none;
    line-height: 0.9;
  }
  h2 {
    margin: 5px;
  }
  table {
    width: 100%;
    border-collapse: collapse; 
  }
  tr {
    margin:0;
    padding:0;
    border-bottom: solid 1px #e0e0e0;
  }
  tr.lignevol {
    -webkit-transition: all 0.3s ease-out;
    -moz-transition: all 0.3s ease-out;
    -o-transition: all 0.3s ease-out;
    transition: all 0.3s ease-out;
  }
  
  tr.lignevol.flash {
    background: #B4E50F;
  }
  /*.lignevol:nth-child(even)   {background: #CCC}
  tr.lignevol:nth-child(n) {background: #FFF}*/
  td {
    margin:0;
    padding:0;
    border: solid 0px #e0e0e0;
    text-align: center;
  }
  td.desc {
    text-align: left;
  }
  .bloctitre {
    display: inline-block;
  }
  .none {
    display: none;
  }
  .hidden {
    visibility: hidden;
  }
  .ppt_info {
    font-size: 8pt;
  }
  .ppt_info > a,.ppt_info > div {
    border: solid 1px #afafaf;
    padding : 0.5px 1px;
    margin : 0px;
    min-width: 24px;
    display: inline-block;
    text-align: center;
    border-radius: 5px;
  }
  .ppt_info > div {
    user-select: none;
    /*cursor: grab;*/
  }
  .ppt_info > a:hover,.ppt_info > div:hover {
    background : #efefef;
    text-decoration: none;
    /*filter: blur(1px);*/
    font-weight: bolder;
  }
  .editsitetexte {
    position: absolute;
    font-size: 12pt;
    left: 12px;
    /* right: 0px; */
    bottom: 0px;
    /* background: rgb(155,155,155); */
    color: white;
    text-shadow: #FC0 1px 0 10px;
  }
  .lien_gmaps, .lien_ffvl {
    float: right;
  }
  .lien_ffvl {
    font-size:0.6em;
    margin-right:5px;
  }
  a {
    text-decoration : none;
    color: #00A7ED;
  }
  a:hover {
    text-decoration : underline;
  }
  #details {
    font-size : 10pt;
  }
  .small {
    font-size: 9pt;
    margin: 0px;
    padding: 0px;
    color: #aaa;
  }
  .small:hover {
    color: #000;
  }
  .inline {
    display: inline-block;
    margin: 5px;
  }
  .btncomm {
    cursor: pointer;
  }
  .filter-imgcolor{
      /*filter: invert(48%) sepia(79%) saturate(2476%) hue-rotate(86deg) brightness(118%) contrast(119%);*/
      /*filter: invert(68%) sepia(89%) saturate(476%) hue-rotate(340deg) brightness(118%) contrast(119%);*/
      filter: invert(100%) sepia(0%) saturate(7493%) hue-rotate(288deg) brightness(100%) contrast(107%);
  }
  .imgTrace {
    display:none;
    position:fixed;
    max-width:320px;
    border: solid 1px grey;
    box-shadow: 0px 0px 40px 40px white;
  }
  .imgTrace>img {
    float:right;
    max-width:320px;
    max-height:320px;
  }
  .loadingzone {
    opacity: 0.5;
    background: #000;
    width: 100%;
    height: 100%;
    z-index: 10;
    top: 0;
    left: 0;
    position: fixed;
    font-size: 2em;
    color: #00ff80;
    text-align: center;
    vertical-align: middle;
    line-height: 1;
  }
  .loader {
    border: 16px solid #f3f3f3; /* Light grey */
    border-top: 16px solid #3498db; /* Blue */
    border-radius: 50%;
    width: 120px;
    height: 120px;
    animation: spin 2s linear infinite;
    margin: 50px auto;
  }
  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }
  #loadingtext {
      background-color: rgb(0, 0, 0);
      background-color: rgba(0, 0, 0, 0.2);
  }
  .rainbow {
      text-align: center;
      text-decoration: underline;
      font-size: 32px;
      font-family: monospace;
      letter-spacing: 5px;
  }
  .rainbow_text_animated {
      background: linear-gradient(to right, #6666ff, #0099ff , #00ff00, #ff3399, #6666ff);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      animation: rainbow_animation 6s ease-in-out infinite;
      background-size: 400% 100%;
  }
  
  @keyframes rainbow_animation {
      0%,100% {
          background-position: 0 0;
      }
  
      50% {
          background-position: 100% 0;
      }
  }
  </style>
</head>

<body>

<?php
  if ($volid > 0) {
    $offset = max(0, intval(($lgfr->getNbrVols() - $volid)/$resperpage)*$resperpage);
    //echo "<h1><a href=\"".$root_url."vol/$volid\">intval((".$lgfr->getNbrVols()." - $volid)/25)*25 = ".$offset."</a></h1>";
  }
  $vols = $lgfr->getRecords(NULL, FALSE, $resperpage, $offset, $datemin, $datemax, $voile, $site, $text, $biplace);
  $titrefiltre = "";
  if (strlen($voile)>0)
    $titrefiltre .= " pour \"".$voile."\"";
  if (strlen($site)>0) {
    if (strlen($titrefiltre)>0)
      $titrefiltre .= " et";
    $titrefiltre .= " pour \"".$site."\"";
  }
  if ($biplace) {
    if (strlen($titrefiltre)>0)
      $titrefiltre .= " et";
    $titrefiltre .= " biplace";
  }
  if ($text) {
    $titrefiltre .= " filtré par mot clé";
  }
  $titredate = "";
  if ($datemin && $datemax) {
    $datetemp = clone $datemax;
    $datetemp->sub(new DateInterval('P1D'));
    $titredate = " du ".$datemin->format('d/m/Y')." au ".$datetemp->format('d/m/Y');
  }
  else if ($datemin) {
    $titredate = " depuis le ".$datemin->format('d/m/Y');
  }
  else if ($datemax) {
    $datetemp = clone $datemax;
    $datetemp->sub(new DateInterval('P1D'));
    $titredate = " jusqu'au ".$datetemp->format('d/m/Y');
  }

  function create_ln_page($i) {
    global $offset, $resperpage;
    $lnoffset = $i*$resperpage;
    $balise = ($lnoffset != $offset) ? "a":"div";
    $titrepage = ($lnoffset != $offset) ? ('aller à la page '.($i+1)) : 'ceci est la page courante';
    return "&nbsp;<".$balise." href=\"".url_with_parameter("offset", $lnoffset, ['vol'])."\" title=\"".$titrepage."\">".($i+1)."</".$balise.">";
  }
  $nbpages = ceil($vols->nbvols/$resperpage);
  $lnpages = "<div class=\"ppt_info\">page";
  if ($nbpages < 10) {
    for ($i=0; $i<$nbpages; $i++) {
      $lnpages .= create_ln_page($i);
    }
  } else {
    $pagecourante = ceil($offset/$resperpage);;
    $pagesbefore = $pagecourante;
    $pagesafter = $nbpages-$pagecourante;
    if ($pagecourante>4) {
      for ($i=0; $i<3; $i++) {
        $lnpages .= create_ln_page($i);
      }
      if ($pagecourante-2 > 3)
        $lnpages .= " ...";
      for ($i=$pagecourante-2; $i<=$pagecourante; $i++) {
        $lnpages .= create_ln_page($i);
      }
    } else {
      for ($i=0; $i<=$pagecourante; $i++) {
        $lnpages .= create_ln_page($i);
      }
    }
    if ($pagesafter>4) {
      //$lnpages .= create_ln_page($pagecourante+1);
      for ($i=$pagecourante+1; $i<$pagecourante+3; $i++) {
        $lnpages .= create_ln_page($i);
      }
      if ($nbpages-1 > $pagecourante+3)
        $lnpages .= " ...";
      for ($i=$nbpages-1; $i<$nbpages; $i++) {
        $lnpages .= create_ln_page($i);
      }
    } else {
      for ($i=$pagecourante+1; $i<$nbpages; $i++) {
        $lnpages .= create_ln_page($i);
      }
    }
  }
  $lnpages .= "</div>";

  /*$lnpages = "<div class=\"ppt_info\">page";
  echo "";
    $pagecourante = ($offset/$resperpage)+1;
    //var_dump($pages);
  for ($i=0; $i<$nbpages; $i++) {
    $lnoffset = $i*$resperpage;
    $balise = ($lnoffset != $offset) ? "a":"div";
    if ($nbpages>15 && $i > 0 && $i < $nbpages-1) {
      //$i = $nbpages-2;
      $lnpages .= "&nbsp;<select onchange=\"window.location='list.php?offset='+this.value\">";
      //$lnpages .= "&nbsp;<select onchange=\"console.log('list.php?offset='+this.value)\">";
      for (;$i<$nbpages-1; $i++){
        $lnoffset = $i*$resperpage;
        $lnpages .= "<option value=\"".$lnoffset."\" ".(($lnoffset != $offset) ? '':'selected').">".($i+1)."</option>";
      }
      $i--;
      $lnpages .= "</select>";
    } else {
      $titrepage = ($lnoffset != $offset) ? ('aller à la page '.($i+1)) : 'ceci est la page courante';
      $lnpages .= "&nbsp;<".$balise." href=\"".url_with_parameter("offset", $lnoffset, ['vol'])."\" title=\"".$titrepage."\">".($i+1)."</".$balise.">";
    }
  }
  $lnpages .= "</div>";*/
?>
<header>
    <div class="bloctitre">
        <h1><a href="<?php echo $root_url;?>">Carnet<?php echo $titrefiltre;?></a></h1>
    </div>
    <div class="bloctitre">
        <a href="#" onclick="if (confirm('télécharger une version complète?')) window.open('download.php?fulldb','MyWindow','width=320,height=120'); else window.location='download.php';" title="télécharger la base logfly"><img src="download.svg" width="32px" class="filter-imgcolor"></a><a href="download.php?csv"><img src="csv.svg" width="32px" title="télécharger un fichier csv"></a>
        &nbsp;<a href="#" onClick="MyWindow=window.open('stats.php','MyWindow','width=900,height=380'); return false;" title="Statistiques de vol"><img src="stats.svg" width="32px" class="filter-imgcolor"></a>
        &nbsp;<a href="#" onClick="MyWindow=window.open('map.php','MyWindow','width=900,height=380'); return false;" title="Carte des sites de vol"><img src="map.svg" width="32px" class="filter-imgcolor"></a>
        &nbsp;<a href="#" onClick="MyWindow=window.open('upload.php','EditVol','width=900,height=380'); return false;" title="Uploader un fichier IGC pour créer/mettre à jour un vol"><img src="upload.svg" width="32px" class="filter-imgcolor"></a>
    <?php
    if ($gdrive) {?>
      &nbsp;<a href="#" onClick="MyWindow=window.open('<?php echo $gdrive_upload_script;?>','Upload Google Drive','width=640,height=240'); return false;" title="Sauvegarder le carnet CSV sur Google Drive"><svg viewBox="0 0 87.3 78" xmlns="http://www.w3.org/2000/svg" width="32"><path d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8h-27.5c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/><path d="m43.65 25-13.75-23.8c-1.35.8-2.5 1.9-3.3 3.3l-25.4 44a9.06 9.06 0 0 0 -1.2 4.5h27.5z" fill="#00ac47"/><path d="m73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5h-27.502l5.852 11.5z" fill="#ea4335"/><path d="m43.65 25 13.75-23.8c-1.35-.8-2.9-1.2-4.5-1.2h-18.5c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/><path d="m59.8 53h-32.3l-13.75 23.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684fc"/><path d="m73.4 26.5-12.7-22c-.8-1.4-1.95-2.5-3.3-3.3l-13.75 23.8 16.15 28h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/></svg></a>
    <?php
    }?>
        &nbsp;<a href="#" onClick="editvol(); return false;" title="editer le carnet de vol"><img src="edit.svg" width="32px" class="filter-imgcolor"></a>
        &nbsp;<a href="#" style="position: relative;" onClick="MyWindow=window.open('editsite.php','MyWindowSite','width=765,height=260'); return false;" title="editer un site"><span class="editsitetexte">site</span><img src="edit.svg" style="position: absolute;" width="32px" class="filter-imgcolor"></a>
    </div>
</header>
<div class="loadingzone" style="display:none;">
  <div class="loader"></div>
  <div id="loadingtext">
    <h3 class="rainbow rainbow_text_animated"></h3>
  </div>
</div>
<div class="main">
<div class="inline">
<?php
//<p>".implode(", ", array_map(function($x) { return "<a href="".url_with_parameter("voile", $x->nom, "offset")."" class="ppt_info">".$x->nom." (".$x->nombrevols." vols, ".Utils::timeFromSeconds($x->tempsvol, 1).")</a> }, $vols->voiles))."</p>
?>
<h2>Voiles (<?php echo count($vols->voiles);?>) : </h2>
<select class="ppt_info" onchange="onchangevoilesite(this.value, true);"><option value="-1"><b>Aucun filtre de voile</b></option>
<?php
  echo implode("\n", array_map(function($x) {global $voile; return "<option value=\"".$x->nom."\" ".((strlen($voile)>0 && $voile==$x->nom)?" selected":"").">".$x->nom." (".$x->nombrevols." vol".($x->nombrevols>1?"s":"").", ".Utils::timeFromSeconds($x->tempsvol, 1).")</option>"; }, $vols->voiles));
?>
</select>
</div>
<?
  if (!is_array($vols->sites))
    $vols->sites = [$vols->sites];
?>
<div class="inline">
<?php
//<p>".implode(", ", array_map(function($x) { return "<a href="".url_with_parameter("site", $x->nom, "offset")."" class="ppt_info">".str_replace(" ", "&nbsp;", $x->nom)." (".$x->nombrevols." vols, ".Utils::timeFromSeconds($x->tempsvol, 1).")</a>"; }, $vols->sites/*$lgfr->getInfoSite()*/))."</p>";
?>
<h2>Sites (<?php echo count($vols->sites);?>) : </h2>
<select class="ppt_info" onchange="onchangevoilesite(this.value, false);"><option value="-1">Aucun filtre de site</option>
<?php
  echo implode("\n", array_map(function($x) {global $site; return "<option value=\"".$x->nom."\" ".((strlen($site)>0 && $site==$x->nom)?" selected":"").">".$x->nom." (".$x->nombrevols." vol".($x->nombrevols>1?"s":"").", ".Utils::timeFromSeconds($x->tempsvol, 1).")</option>"; }, $vols->sites/*$lgfr->getInfoSite()*/));
?>
</select>
</div>
<h2>Détails (<?php echo $vols->nbvols." vol".($vols->nbvols>1?"s":"").", ".Utils::timeFromSeconds($vols->tempstotalvol, 1)."".$titredate;?>) : </h2>
  <?php echo $lnpages;?>

<TABLE id="details">
<TR><TH></TH><TH>N&deg;</TH><TH>Date</TH><TH>Heure</TH><TH>Duree</TH><TH>Site</TH><TH>Voile</TH><TH></TH><TH>Trace</TH></TR>
<?php
  function getColor($date) {
    $d = intval($date->format('m'))*100+intval($date->format('d'));
    return sprintf('#%06XAA', ($d-100)*0xffffff/1131);
  }
  /*echo "<TR>";
  echo "<TD colspan=\"3\"><b>temps de vol :</b></TD>";
  echo "<TD>".Utils::timeFromSeconds($vols->tempstotalvol)."</TD>";
  echo "</TR>";*/
  foreach ($vols->vols as $vol)
  {
    $ccolor = getColor($vol->date);
    echo "<TR class=\"lignevol\">";
    echo "<TD style=\"background-color:".$ccolor.";width: 10px;\"></TD>";
    echo "<TD name=\"tdid\"><a id=\"v".$vol->id."\" href=\"".$root_url."vol/".$vol->id."\">". $vol->id."</a>";
    echo "<input type=\"hidden\" id=\"hascomment".$vol->id."\" value=\"".($vol->commentaire==1?'true':'false')."\">";
    echo "</TD>";
    $nom_parametredate = "datemin";
    $texte_parametredate = "depuis";
    if ($datemin) {
      $nom_parametredate = "datemax";
      $texte_parametredate = "jusqu'à";
    }
    $nomsjours = ['dim', 'lun', 'mar', 'mer', 'jeu', 'ven', 'sam'];
    $jour = $nomsjours[$vol->date->format('w')];
    echo "<TD><a href=\"".url_with_parameter($nom_parametredate, $vol->date->format('Y-m-d'), "offset")."\" title=\"filtrer les vols ".$texte_parametredate." cette date\"><p class=\"small\">".$jour."</p>". $vol->date->format('d/m/Y')."</a></TD>";
    $datefin = clone $vol->date;
    $vol->duree = is_int($vol->duree) && $vol->duree > 0 ? $vol->duree : 0;
    $datefin = $datefin->add(new DateInterval("PT".$vol->duree."S"));
    echo "<TD><span title=\"heure de décollage\">&#8613;&nbsp;". $vol->date->format('H:i')."</span><p class=\"small\" title=\"heure de posé\">&#8615;&nbsp;".$datefin->format('H:i')."</p></TD>";
    echo "<TD title=\"".Utils::timeFromSeconds($vol->duree, 3)."\">". Utils::timeFromSeconds($vol->duree > 900 ? round($vol->duree/60)*60 : $vol->duree, 2)."</TD>";
    //echo "<TD>". $vol->sduree."</TD>";
    echo "<TD><a href=\"".url_with_parameter("site", $vol->site, "offset")."\" title=\"filtrer les vols pour ce site\">".$vol->site."</a>&nbsp;<a href=\"https://maps.google.com/?q=".$vol->latdeco.",".$vol->londeco."\" target=\"_Blank\" class=\"lien_gmaps\" title=\"google maps\">&#9936;</a>";
    echo "&nbsp;<a href=\"#\" onclick=\"gotoSiteFFVL(".$vol->latdeco.",".$vol->londeco.");return false;\" class=\"lien_ffvl\" title=\"site FFVL\">FFVL</a>";
    echo "</TD>";
    echo "<TD><a href=\"".url_with_parameter("voile", $vol->voile, "offset")."\" title=\"filtrer les vols pour cette voile\">".$vol->voile."</a></TD>";
    echo "<TD><a href=\"".url_with_parameter("biplace", $vol->biplace, "offset")."\" title=\"filtrer les vols en biplace\">".($vol->biplace?"bi":"")."</a></TD>";
    echo "<TD";
    $url = "";
    if ($vol->igc) {
      $tracefileprefix = urlencode((defined('FOLDER_TL')?FOLDER_TL:"")."/" . $vol->id);
      $url = "trace.html?igc=".$tracefileprefix.".igc&start=".$vol->date->getTimestamp()."&finfo=".$tracefileprefix.".json&paraglidername=".urlencode($vol->voile)."&elevationservice=".(urlencode(ELEVATIONSERVICE))."&clegeoportail=".(defined('CLEGEOPORTAIL')?urlencode(CLEGEOPORTAIL):"");
      echo " class=\"zoneimgtrace\" data-id=\"".$vol->id."\"";
      echo " onClick=\"openTrace(this);return false;\" title=\"voir la trace GPS de ce vol\n(ctrl-click pour ouvrir dans un nouvel onglet)\" style=\"cursor: pointer\"";
      echo "><a id=\"traceurl_".$vol->id."\" href=\"".$url."\"><img src=\"map.svg\" width=\"18px\"></a>";
      $url_image = "image.php?id=".$vol->id;
      $fname = "Tracklogs".DIRECTORY_SEPARATOR.$vol->id.".jpg";
      if (file_exists($fname)) {
        $url_image = $fname."?".filemtime($fname);
      }
      echo " <div name=\"imgTrace\" id=\"imgTrace".$vol->id."\" class=\"imgTrace\" data-id=\"".$vol->id."\"><img src=\"".$url_image."\"></div>";
    }
    else {
      echo ">";
    }
    echo "</TD>";
    if ($vol->commentaire || $vol->igc) {
      echo "<TD class=\"btncomm";
      if ($vol->igc) {
        echo " zoneimgtrace";
      }
      echo "\" data-id=\"".$vol->id."\" id=\"btncomm".$vol->id."\" title=\"afficher le commentaire/la trace\" onclick=\"affichComment(".$vol->id.");\" style=\"font-family:Verdana;font-style:italic;font-size:10;\">abc</TD>";
    } else {
      echo "<TD></TD>";
    }
    echo "<TR class=\"lignecomm none\"><TD class=\"hidden\">".$vol->id."</TD><TD id=\"comm".$vol->id."\" colspan=\"8\" class=\"desc\"><div id=\"zonecomm".$vol->id."\"></div>";
    echo "<div>météo de ce jour : ";
    //https://www.infoclimat.fr/fr/cartes/observations-meteo/archives/vent_moyen/18/mai/2022/14h/carte-interactive.html
    $libmois = ['janvier', 'fevrier', 'mars', 'avril', 'mai', 'juin', 'juillet', 'aout', 'septembre', 'octobre', 'novembre', 'decembre'];
    echo "<a href=\"https://www.meteociel.fr/modeles/arome.php?ech=3&mode=35&map=4&heure=6&jour=".$vol->date->format('j')."&mois=".$vol->date->format('n')."&annee=".$vol->date->format('Y')."&archive=1\">prévisions</a>";
    $date = clone $vol->date;
    $date->modify('-1 day');
    echo " / <a href=\"http://78.207.28.106/mto/auto/".$date->format('y').$date->format('m').$date->format('d')."GFS/frog.html\">Caplain</a>";
    echo " / <a href=\"https://www.infoclimat.fr/fr/cartes/observations-meteo/archives/vent_moyen/".$vol->date->format('j')."/".$libmois[intval($vol->date->format('n'))-1]."/".$vol->date->format('Y')."/11h/carte-interactive.html\">relevés</a>";
    echo "</div><div id=\"zonecarto".$vol->id."\"></div></TD></TR>";
    echo "</TR>\n";
  }
  echo "</TABLE>";
  echo $lnpages;
?>
</div>
<?php
  $url_imageparcours = "parcours.php";
  $fname = "Tracklogs".DIRECTORY_SEPARATOR."parcours.jpg";
  if (file_exists($fname)) {
    $url_imageparcours = $fname."?".filemtime($fname);
  }
  echo "<footer><a href=\"".$url_imageparcours."\">carte des parcours</a></footer>";
?>
<script>
var showComment = true;
var volid = <?php echo $volid??0;?>;
function editvol(id, lat, lon, altitude) {
  let url = 'edit.php';
  if (id > 0)
    url += '?id='+parseInt(id);
  if (typeof(lat) !== 'undefined' && !isNaN(parseFloat(lat)))
    url += '&lat='+parseFloat(lat);
  if (typeof(lon) !== 'undefined' && !isNaN(parseFloat(lon)))
    url += '&lon='+parseFloat(lon);
  if (typeof(altitude) !== 'undefined' && !isNaN(parseFloat(altitude)))
    url += '&alt='+parseFloat(altitude);
  var MyWindow=window.open(url,'EditVol','width=600,height=480');
}
function parse_query_string(query) {
  if (query.trim().length <= 0 || query.indexOf("=") == -1) return {};
  var vars = query.split("&");
  var query_string = {};
  for (var i = 0; i < vars.length; i++) {
    var pair = vars[i].split("=");
    var key = decodeURIComponent(pair[0]);
    var value = decodeURIComponent(pair[1]);
    if (key.trim().length <= 0) continue;
    // If first entry with this name
    if (typeof query_string[key] === "undefined") {
      query_string[key] = decodeURIComponent(value);
      // If second entry with this name
    } else if (typeof query_string[key] === "string") {
      var arr = [query_string[key], decodeURIComponent(value)];
      query_string[key] = arr;
      // If third or later entry with this name
    } else {
      query_string[key].push(decodeURIComponent(value));
    }
  }
  return query_string;
}
function onchangevoilesite(nom, voile) {
  var rooturl = "<?php echo $_SERVER["SCRIPT_NAME"];?>";
  var query = window.location.search.substring(1);
  var qs = parse_query_string(query);
  var url = "";
  if (nom != "-1")
    qs[voile?"voile":"site"] = nom;
  var first = true;
  for (var key in qs) {
    if (qs.hasOwnProperty(key)) {
      if (key == "offset" || (nom == "-1" && key == (voile?"voile":"site"))) continue;
      if (!first) url += "&";
      url += key + "=" + encodeURI(qs[key]);
      first = false;
    }
  }
  if (url.length > 0)
    rooturl += "?" + url;
  window.location = rooturl;
}
/*function parseSmileys(text) {
  let smileys = [
    [':)', 0x0a],
    [':)', 0x0a],
    [';)', 0x09],
    [';-)', 0x09],
    [':p', 0x0b],
    [':-p', 0x0b],
    [':D', 0x04],
    [':-D', 0x04],
    ['XD', 0x06],
    ['X-D', 0x06],
    [':(', 0x15],
    [':-(', 0x15],
  ];
  smileys.forEach(s => {text = text.replaceAll(s[0], String.fromCharCode(0xd83d,s[1]+0xde00));});
  return text;
}*/
function loadComment(id) {
  let ligne = document.getElementById('comm'+id).parentElement;
  let zonecomm = document.getElementById('zonecomm'+id);
  let zonecarto = document.getElementById('zonecarto'+id);
  let btncomm = document.getElementById('btncomm'+id);
  try {
    var xhttp = new XMLHttpRequest();
      xhttp.responseType = 'text';
      xhttp.onreadystatechange = function() {
        if (this.readyState == 4) {
          if (this.status < 200 || this.status > 299 || typeof this.response != 'string') {
            zonecomm.innerHTML = "";
            showComment = false; // au 1er échec on arrête de popuper l'utilisateur
            if (!btncomm.previousElementSibling.innerHTML) {
              ligne.style.display = 'none';
              btncomm.style.textDecoration = "";
              btncomm.title="afficher le commentaire";
            }
          } else {
            zonecomm.innerHTML = this.response;//parseSmileys(this.response);
            if (btncomm.previousElementSibling.innerHTML && this.response.trim().length <= 0) {
              zonecomm.innerHTML = '';
            }
          }
        }
      };
      xhttp.open("GET", "comment.php?id="+id, true);
      xhttp.send();
  } catch (e) {
    zonecomm.innerHTML = e;
  }
}
function affichComment(id) {
  hideImagesTrace();
  let ligne = document.getElementById('comm'+id).parentElement;
  let zonecarto = document.getElementById('zonecarto'+id);
  let zonecomm = document.getElementById('zonecomm'+id);
  let btncomm = document.getElementById('btncomm'+id);
  let hascomment = document.getElementById('hascomment'+id).value == 'true';
  if (ligne.style.display != 'table-row') {
    if (hascomment && showComment && zonecomm.innerHTML == "") {
      loadComment(id);
      zonecomm.innerHTML = "<b>Chargement...</b>";
    }
    if (zonecarto.innerHTML == "" && btncomm.previousElementSibling.innerHTML) {
      let url = document.getElementById('traceurl_'+id).href;
      if (url.trim().length > 0) {
        url += "&disablescroll=1";
        zonecarto.innerHTML += "<iframe src=\""+url+"\" width=\"99%\" height=\"658px\"></iframe>";
      }
    }
    ligne.style.display = 'table-row';
    btncomm.style.textDecoration = "line-through";
    btncomm.title="masquer le commentaire";
  } else {
    ligne.style.display = 'none';
    btncomm.style.textDecoration = "";
    btncomm.title="afficher le commentaire";
  }
}
function openTrace(elem) {
  let lnk = elem.getElementsByTagName('a')[0];
  //let tracefileprefix = encodeURI("<?php if (defined('FOLDER_TL')) echo FOLDER_TL;?>/" + id);
  let url = lnk.href;
  MyWindow=window.open(url,'MyWindow','width=900,height=380');
}
function hideImagesTrace(id) {
  let ots = [...document.getElementsByName('imgTrace')];
  ots.forEach(o => {if (o.id=='imgTrace'+id) return;o.style.display='none';});
}
function afficheImageTrace(elem, hide) {
  hide = hide === true;
  let id = elem.dataset.id;
  hideImagesTrace(id);
  //if (hide) return;
  imgTrace = document.getElementById('imgTrace'+id);
  var rect = elem.getBoundingClientRect();
  let img = imgTrace.getElementsByTagName('img')[0];
  if (hide)// && imgTrace.style.display == 'block')
    imgTrace.style.display='none';
  else if (!hide)// && imgTrace.style.display == 'none')
  {
    imgTrace.style.display='block';
    imgTrace.style.top = rect.top+'px';
    let w = img.width || 320;
    if (rect.top+img.height>window.innerHeight-10)
      imgTrace.style.top = window.innerHeight-img.height-10+'px';
    imgTrace.style.left = (rect.left-w-20)+'px';
  }
}
function moveImageTrace() {
  let o = [...document.getElementsByName('imgTrace')].find(o =>o.style.display!='none');
  if (!o) return;
  afficheImageTrace(o.parentElement);
}
function getZoneImgTrace(zone) {
  if (!zone.classList.contains('zoneimgtrace')) {
    zone = zone.closest('.zoneimgtrace');
  }
  return zone;
}
function scrollToVol(id) {
  window.location = 'vol/'+id+'#v'+id;
  window.scrollBy(0,-85);
  let tr = document.getElementById('v'+id).closest("tr");
  tr.classList.toggle("flash");
  window.setTimeout(function(){tr.classList.toggle("flash");},250);
}
function distance(lat1Deg, lon1Deg, lat2Deg, lon2Deg) {
  function toRad(degree) {
      return degree * Math.PI / 180;
  }
  const lat1 = toRad(lat1Deg);
  const lon1 = toRad(lon1Deg);
  const lat2 = toRad(lat2Deg);
  const lon2 = toRad(lon2Deg);
  
  const { sin, cos, sqrt, atan2 } = Math;
  
  const R = 6371000; // earth radius in m 
  const dLat = lat2 - lat1;
  const dLon = lon2 - lon1;
  const a = sin(dLat / 2) * sin(dLat / 2)
          + cos(lat1) * cos(lat2)
          * sin(dLon / 2) * sin(dLon / 2);
  const c = 2 * atan2(sqrt(a), sqrt(1 - a)); 
  const d = R * c;
  return d; // distance in m
}
async function gotoSiteFFVL(lat, lon) {
  if (!window.sitesffvl) {
    if (window.isfetchingffvl) return false;
    loading('Chargement des sites FFVL');
    try {
      window.isfetchingffvl = true;
      const resp = await fetch('?sites');
      window.sitesffvl = await resp.json();
    } catch (e) {
      console.error(e);
      alert('Impossible de charger les sites FFVL. Veuillez réessayer plus tard');
      loading(false);
      window.isfetchingffvl = false;
      return false;
    }
    window.isfetchingffvl = false;
  }
  if (!Array.isArray(window.sitesffvl)) {
    window.sitesffvl = null;
    alert('oups! Réessayer plus tard!');
    [...window.document.querySelectorAll('.lien_ffvl')].forEach(e => e.style.display = 'none');
    loading(false);
    return false;
  }
  loading(true);
  let site = window.sitesffvl.reduce((val,cur) => (distance(cur.latitude, cur.longitude, lat, lon)>distance(val.latitude, val.longitude, lat, lon)) ? val:cur);
  if (site) {
    let d = distance(site.latitude, site.longitude, lat, lon);
    if (d < 1000 && site.suid) {
      window.open('https://federation.ffvl.fr/sites_pratique/voir/'+site.suid, '_blank').focus();
    } else {
      alert('Pas de site FFVL proche trouvé !');
    }
  }
  loading(false);
  return false;
}
function loading() {
  let isloading, message;
  for (let i=0; i<arguments.length && i<2; i++) {
    if (typeof arguments[i] === 'boolean') isloading = arguments[i];
    else if (typeof arguments[i] === 'string') message = arguments[i];
  }
  isloading = typeof isloading === 'boolean' ? isloading : true;
  message = isloading && typeof message === 'string' ? message : '';
  isloading = !(isloading === false);
  document.getElementById('loadingtext').firstElementChild.innerHTML = message;
  document.getElementsByClassName ('loadingzone')[0].style.display = isloading?'initial':'none';
}
window.onload = function() {};

if (volid) scrollToVol(volid);

const lignes = document.querySelectorAll('tr.lignevol,tr.lignecomm');
lignes.forEach(function(ligne) {
  ligne.addEventListener('dblclick', function (e) {
    e.preventDefault();
    editvol(parseInt(e.target.closest("tr").querySelector("td[name='tdid']").textContent));
  });
});
document.addEventListener("scroll", (event) => {moveImageTrace();});
var zonesimgtrace = [...document.querySelectorAll('.zoneimgtrace')];
zonesimgtrace.forEach(z => {
  let action = (hide, event) => {
      let zone = event.target;
      if (!zone.classList.contains('zoneimgtrace')) {
        zone = zone.closest('.zoneimgtrace');
        if (!zone) return;
      }
      afficheImageTrace(zone, hide);
  };
  let affi = action.bind(this, false);
  let hide = action.bind(this, true);
  z.addEventListener("mouseover", affi);
  z.addEventListener("mouseleave", hide);
  z.addEventListener("touchstart", affi);
  z.addEventListener("touchend", hide);
  z.addEventListener("touchmove", moveImageTrace);
});
</script>
</body>
</html>
