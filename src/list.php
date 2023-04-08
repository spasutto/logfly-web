<?php
require("config.php");
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

parse_str($_SERVER["QUERY_STRING"]  , $get_array);//print_r($get_array);
//phpinfo();return;
$voile=null;
$site=null;
$datemin=NULL;
$datemax=NULL;
$resperpage=25;
$offset=0;
//TODO : WEAK
if (isset($_GET['voile'])) {
  $voile = $_GET['voile'];
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
if (isset($_GET['offset'])) {
  $offset = @intval($_GET['offset']);
  if (!is_int($offset) || $offset < 0)
  $offset = 0;
}

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
<style type="text/css">
body {
  line-height:22px;
  font-family: sans-serif, monospace;
}
table {
  width: 100%;
}
tr {
  margin:0;
  padding:0;
}
td {
  margin:0;
  padding:0;
  border: solid 1px #afafaf;
  text-align: center;
}
td.desc {
  text-align: left;
}
.none {
  display: none;
}
.hidden {
  visibility: hidden;
}
.ppt_info{
  border: solid 1px #afafaf;
  padding : 1px 2px;
  margin : 0px;
  min-width: 24px;
  display: inline-block;
  text-align: center;
  border-radius: 5px;
}
.ppt_info:hover {
  background : #efefef;
}
.editsitetexte {
  position: absolute;
  font-size: 12pt;
  left: 12px;
  /* right: 0px; */
  bottom: 0px;
  /* background: rgb(155,155,155); */
  color: red;
  text-shadow: #FC0 1px 0 10px;
}
.lien_gmaps {
  float: right;
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
</style>

<script>
var showComment = true;
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
function parseSmileys(text) {
  /*document.body.innerHTML = '';
  Array.from(Array(0xde4f-0xde00+1)).forEach((x, i) => {
    document.body.innerHTML += String.fromCharCode(0xd83d,i+0xde00)+" : 0x"+(i+0xde00).toString(16).padStart(4,0)+"<BR>";
  });*/
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
}
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
            zonecomm.innerHTML = parseSmileys(this.response);
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
  let ligne = document.getElementById('comm'+id).parentElement;
  let zonecarto = document.getElementById('zonecarto'+id);
  let zonecomm = document.getElementById('zonecomm'+id);
  let btncomm = document.getElementById('btncomm'+id);
  if (ligne.style.display != 'table-row') {
    if (showComment && zonecomm.innerHTML == "") {
      loadComment(id);
      zonecomm.innerHTML = "<b>Chargement...</b>";
    }
    if (zonecarto.innerHTML == "" && btncomm.previousElementSibling.innerHTML) {
      let url = document.getElementById('traceurl_'+id).value;
      url += "&disablescroll=1";
      zonecarto.innerHTML += "<iframe src=\""+url+"\" width=\"100%\" height=\"555px\"></iframe>";
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
function openTrace(lnk) {
  //let tracefileprefix = encodeURI("<?php if (defined('FOLDER_TL')) echo FOLDER_TL;?>/" + id);
  let url = lnk.href;//"trace.html?igc="+tracefileprefix+".igc&finfo="+tracefileprefix+".json&elevationservice="+encodeURI('<?php if (defined('ELEVATIONSERVICE')) echo ELEVATIONSERVICE;?>')+"&clegeoportail="+encodeURI('<?php if (defined('CLEGEOPORTAIL')) echo CLEGEOPORTAIL;?>');
  MyWindow=window.open(url,'MyWindow','width=900,height=380');
}
function afficheImageTrace(elem,id, hide) {
  hide = hide === true;
  var rect = elem.getBoundingClientRect();
  if (hide && imgTrace.style.display == 'block')
  {
    imgTrace.style.display='none';
  }
  else if (!hide && imgTrace.style.display == 'none')
  {
    imgTrace.getElementsByTagName('img')[0].src= "image.php?id="+id;
    imgTrace.style.display='block';
    imgTrace.style.top = rect.top+'px';
    imgTrace.style.left = (rect.left-340)+'px';
  }
}
window.onload = function() {
  window.imgTrace = document.getElementById('imgTrace');
  const lignes = document.querySelectorAll('tr.lignevol,tr.lignecomm');
  lignes.forEach(function(ligne) {
    ligne.addEventListener('dblclick', function (e) {
      e.preventDefault();
      editvol(parseInt(e.target.closest("tr").querySelector("td").textContent));
    });
  });
};
</script>
</head>

<body>
<div id="imgTrace" style="display:none;position:fixed;width:320px">
<img src="csv.svg" style="float:right;max-width:320px;max-height:320px;">
</div>

<?php
  $vols = $lgfr->getRecords(NULL, FALSE, $resperpage, $offset, $datemin, $datemax, $voile, $site);
  $titrevoile = "";
  if (strlen($voile)>0)
    $titrevoile .= " pour \"".$voile."\"";
  if (strlen($site)>0) {
    if (strlen($titrevoile)>0)
      $titrevoile .= " et";
    $titrevoile .= " pour \"".$site."\"";
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

  $nbpages = ceil($vols->nbvols/$resperpage);
  $lnpages = "page";
  for ($i=0; $i<$nbpages; $i++) {
    $lnoffset = $i*$resperpage;
    $balise = ($lnoffset != $offset) ? "a":"div";
    $lnpages .= "&nbsp;<".$balise." href=\"".url_with_parameter("offset", $lnoffset)."\" class=\"ppt_info\">".($i+1)."</".$balise.">";
  }
  $lnpages .= "<BR>";

  echo "<h1><a href=\"".$_SERVER["SCRIPT_NAME"]."\" style=\"text-decoration:none;\">Carnet de vol".$titrevoile." (".$vols->nbvols." vol".($vols->nbvols>1?"s":"").", ".Utils::timeFromSeconds($vols->tempstotalvol, 1)."".$titredate.")</a> : <a href=\"download.php\" title=\"télécharger la base logfly\"><img src=\"download.svg\" width=\"32px\"></a><a href=\"download.php?csv\"><img src=\"csv.svg\" width=\"32px\" title=\"télécharger un fichier csv\"></a>";
  echo "&nbsp;<a href=\"#\" onClick=\"MyWindow=window.open('stats.php','MyWindow','width=900,height=380'); return false;\" title=\"Statistiques de vol\"><img src=\"stats.svg\" width=\"32px\"></a>";
  echo "&nbsp;<a href=\"#\" onClick=\"MyWindow=window.open('map.php','MyWindow','width=900,height=380'); return false;\" title=\"Carte des sites de vol\"><img src=\"map.svg\" width=\"32px\"></a>";
  echo "&nbsp;<a href=\"#\" onClick=\"MyWindow=window.open('upload.php','EditVol','width=900,height=380'); return false;\" title=\"Uploader un fichier IGC pour créer/mettre à jour un vol\"><img src=\"upload.svg\" width=\"32px\"></a>";
  echo "&nbsp;<a href=\"#\" onClick=\"editvol(); return false;\" title=\"editer le carnet de vol\"><img src=\"edit.svg\" width=\"32px\"></a>";
  echo "&nbsp;<a href=\"#\" style=\"position: relative;\" onClick=\"MyWindow=window.open('editsite.php','MyWindowSite','width=600,height=380'); return false;\" title=\"editer un site\"><span class=\"editsitetexte\">site</span><img src=\"edit.svg\" style=\"position: absolute;\" width=\"32px\"></a>";
  echo "</h1>";
  echo "<div class=\"inline\">";
  echo "<h2>Voiles  (".count($vols->voiles).") : </h2>";//<p>".implode(", ", array_map(function($x) { return "<a href=\"".url_with_parameter("voile", $x->nom, "offset")."\" class=\"ppt_info\">".$x->nom." (".$x->nombrevols." vols, ".Utils::timeFromSeconds($x->tempsvol, 1).")</a>"; }, $vols->voiles))."</p>";
  echo "<select class=\"ppt_info\" onchange=\"onchangevoilesite(this.value, true);\"><option value=\"-1\">Aucun filtre de voile</option>";
  echo implode("\n", array_map(function($x) {global $voile; return "<option value=\"".$x->nom."\" ".((strlen($voile)>0 && $voile==$x->nom)?" selected":"").">".$x->nom." (".$x->nombrevols." vol".($x->nombrevols>1?"s":"").", ".Utils::timeFromSeconds($x->tempsvol, 1).")</option>"; }, $vols->voiles));
  echo "</select>";
  echo "</div>";
  if (!is_array($vols->sites))
    $vols->sites = [$vols->sites];
  echo "<div class=\"inline\">";
  echo "<h2>Sites (".count($vols->sites).") : </h2>";//<p>".implode(", ", array_map(function($x) { return "<a href=\"".url_with_parameter("site", $x->nom, "offset")."\" class=\"ppt_info\">".str_replace(" ", "&nbsp;", $x->nom)." (".$x->nombrevols." vols, ".Utils::timeFromSeconds($x->tempsvol, 1).")</a>"; }, $vols->sites/*$lgfr->getInfoSite()*/))."</p>";
  echo "<select class=\"ppt_info\" onchange=\"onchangevoilesite(this.value, false);\"><option value=\"-1\">Aucun filtre de site</option>";
  echo implode("\n", array_map(function($x) {global $site; return "<option value=\"".$x->nom."\" ".((strlen($site)>0 && $site==$x->nom)?" selected":"").">".$x->nom." (".$x->nombrevols." vol".($x->nombrevols>1?"s":"").", ".Utils::timeFromSeconds($x->tempsvol, 1).")</option>"; }, $vols->sites/*$lgfr->getInfoSite()*/));
  echo "</select>";
  echo "</div>";
  echo "<h2>Détails : </h2>";
  echo $lnpages;
  echo "<TABLE id=\"details\">";
  echo "<TR><TH>N&deg;</TH><TH>Date</TH><TH>Heure</TH><TH>Duree</TH><TH>Site</TH><TH>Voile</TH><TH>Trace</TH></TR>";
  /*echo "<TR>";
  echo "<TD colspan=\"3\"><b>temps de vol :</b></TD>";
  echo "<TD>".Utils::timeFromSeconds($vols->tempstotalvol)."</TD>";
  echo "</TR>";*/
  foreach ($vols->vols as $vol)
  {
    $gmtoffset = 0;
    try {
      $gmtoffset = @$vol->date->getTimezone()->getOffset($vol->date);
    } catch (Exception $e) {}
    echo "<TR class=\"lignevol\">";
    echo "<TD><a id=\"v".$vol->id."\" href=\"#v".$vol->id."\">". $vol->id."</a>";
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
    $datefin = $datefin->add(new DateInterval("PT".$vol->duree."S"));
    echo "<TD><span title=\"heure de décollage\">&#8613;&nbsp;". $vol->date->format('H:i')."</span><p class=\"small\" title=\"heure de posé\">&#8615;&nbsp;".$datefin->format('H:i')."</p></TD>";
    echo "<TD>". Utils::timeFromSeconds($vol->duree > 900 ? round($vol->duree/60)*60 : $vol->duree, 2)."</TD>";
    //echo "<TD>". $vol->sduree."</TD>";
    echo "<TD><a href=\"".url_with_parameter("site", $vol->site, "offset")."\" title=\"filtrer les vols pour ce site\">".$vol->site."</a>&nbsp;<a href=\"https://maps.google.com/?q=".$vol->latdeco.",".$vol->londeco."\" target=\"_Blank\" class=\"lien_gmaps\" title=\"google maps\">&#9936;</a></TD>";
    echo "<TD><a href=\"".url_with_parameter("voile", $vol->voile, "offset")."\" title=\"filtrer les vols pour cette voile\">".$vol->voile."</a></TD>";
    echo "<TD";
    $url = "";
    if ($vol->igc) {
      $tracefileprefix = urlencode((defined('FOLDER_TL')?FOLDER_TL:"")."/" . $vol->id);
      $url = "trace.html?igc=".$tracefileprefix.".igc&tzoffset=".$gmtoffset."&finfo=".$tracefileprefix.".json&elevationservice=".(defined('ELEVATIONSERVICE')?urlencode(ELEVATIONSERVICE):"")."&clegeoportail=".(defined('CLEGEOPORTAIL')?urlencode(CLEGEOPORTAIL):"");
      echo " onMouseOver=\"afficheImageTrace(this, ".$vol->id.")\" onmouseleave=\"afficheImageTrace(this, ".$vol->id.", true)\"><a href=\"".$url."\" onClick=\"openTrace(this);return false;\" title=\"voir la trace GPS de ce vol\"><img src=\"map.svg\" width=\"18px\"></a>";
    }
    else {
      echo ">";
    }
    echo "<input type=\"hidden\" id=\"traceurl_".$vol->id."\" value=\"".$url."\">";
    echo "</TD>";
    if ($vol->commentaire || $vol->igc)
      echo "<TD class=\"btncomm\" id=\"btncomm".$vol->id."\" title=\"afficher le commentaire/la trace\" onclick=\"affichComment(".$vol->id.");\" style=\"font-family:Verdana;font-style:italic;font-size:10;\">abc</TD>";
    else
      echo "<TD></TD>";
    echo "<TR class=\"lignecomm none\"><TD class=\"hidden\">".$vol->id."</TD><TD id=\"comm".$vol->id."\" colspan=\"7\" class=\"desc\"><div id=\"zonecomm".$vol->id."\"></div>";
    echo "<div>météo de ce jour : ";
    //https://www.infoclimat.fr/fr/cartes/observations-meteo/archives/vent_moyen/18/mai/2022/14h/carte-interactive.html
    $libmois = ['janvier', 'fevrier', 'mars', 'avril', 'mai', 'juin', 'juillet', 'aout', 'septembre', 'octobre', 'novembre', 'decembre'];
    echo "<a href=\"https://www.meteociel.fr/modeles/arome.php?ech=3&mode=35&map=4&heure=6&jour=".$vol->date->format('j')."&mois=".$vol->date->format('n')."&annee=".$vol->date->format('Y')."&archive=1\">prévisions</a>";
    echo " / <a href=\"http://78.207.28.106/mto/auto/".$vol->date->format('y').$vol->date->format('m').(intval($vol->date->format('d'))-1)."GFS/frog.html\">Caplain</a>";
    echo " / <a href=\"https://www.infoclimat.fr/fr/cartes/observations-meteo/archives/vent_moyen/".$vol->date->format('j')."/".$libmois[intval($vol->date->format('n'))-1]."/".$vol->date->format('Y')."/14h/carte-interactive.html\">relevés</a>";
    echo "</div><div id=\"zonecarto".$vol->id."\"></div></TD></TR>";
    echo "</TR>";
  }
  echo "<TABLE>";
  echo $lnpages;
?>
</body>
</html>