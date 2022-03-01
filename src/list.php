<?php
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
.hidden {
  display: none;
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
function editvol(id) {
  let url = 'edit.php';
  if (id > 0)
    url += '?id='+parseInt(id);
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
function loadComment(id) {
  let ligne = document.getElementById('comm'+id);
  let btncomm = document.getElementById('btncomm'+id);
  let hidefn = function() { ligne.firstChild.innerHTML = ""; ligne.style.display = 'none'; btncomm.firstChild.style.transform = "rotate(0deg)";};
  try {
    var xhttp = new XMLHttpRequest();
      xhttp.responseType = 'text';
      xhttp.onreadystatechange = function() {
        if (this.readyState == 4) {
          if (this.status == 401) {
            hidefn();
          } else if (this.status == 200) {
            if (typeof this.response != 'string') {
              hidefn();
            } else {
              ligne.firstChild.innerHTML = this.response;
            }
          }
        }
      };
      xhttp.open("GET", "comment.php?id="+id, true);
      xhttp.send();
  } catch (e) {
    ligne.firstChild.innerHTML = e;
  }
}
function affichComment(id) {
  let ligne = document.getElementById('comm'+id);
  let btncomm = document.getElementById('btncomm'+id);
  if (ligne.firstChild.innerHTML == "") {
    loadComment(id);
    ligne.firstChild.innerHTML = "<b>Chargement...</b>";
  }
  if (ligne.style.display != 'table-row') {
    ligne.style.display = 'table-row';
    btncomm.firstChild.style.transform = "rotate(90deg)";
  } else {
    ligne.style.display = 'none';
    btncomm.firstChild.style.transform = "rotate(0deg)";
  }
}
window.onload = function() {
    const lignes = document.querySelectorAll('tr.lignevol');
    lignes.forEach(function(ligne) {
        ligne.addEventListener('dblclick', function (e) {
          e.preventDefault();
          editvol(e.target.closest("tr").querySelector("td").innerText);
        });
    });
};
</script>
</head>

<body>
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
  echo "&nbsp;<a href=\"#\" onClick=\"MyWindow=window.open('upload.php','Upload','width=900,height=380'); return false;\" title=\"Uploader un fichier IGC pour créer/mettre à jour un vol\"><img src=\"upload.svg\" width=\"32px\"></a>";
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
    echo "<TR class=\"lignevol\">";
    echo "<TD><a id=\"v".$vol->id."\" href=\"#v".$vol->id."\">". $vol->id."</a></TD>";
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
    echo "<TD>". Utils::timeFromSeconds($vol->duree, 2)."</TD>";
    //echo "<TD>". $vol->sduree."</TD>";
    echo "<TD><a href=\"".url_with_parameter("site", $vol->site, "offset")."\" title=\"filtrer les vols pour ce site\">".$vol->site."</a>&nbsp;<a href=\"https://maps.google.com/?q=".$vol->latdeco.",".$vol->londeco."\" target=\"_Blank\" class=\"lien_gmaps\" title=\"google maps\">&#9936;</a></TD>";
    echo "<TD><a href=\"".url_with_parameter("voile", $vol->voile, "offset")."\" title=\"filtrer les vols pour cette voile\">".$vol->voile."</a></TD>";
    echo "<TD>";
    if ($vol->igc) {
      echo "<a href=\"#\" onClick=\"MyWindow=window.open('trace.php?id=".$vol->id."','MyWindow','width=900,height=380'); return false;\" title=\"voir la trace GPS de ce vol\"><img src=\"map.svg\" width=\"18px\"></a>";
    }
    echo "</TD>";
    echo "<TD class=\"btncomm\" id=\"btncomm".$vol->id."\" title=\"afficher le commentaire\" onclick=\"affichComment(".$vol->id.");\"><svg viewbox=\"0 0 6 6\" height=\"18px\" width=\"18px\" style=\"transform-origin: center;\" xmlns=\"http://www.w3.org/2000/svg\"><g><path d=\"M1 1 l0 4 l3 -2 l-3 -2 Z\" fill-rule=\"nonzero\" stroke=\"black\"/></g></svg></TD>";
    echo "<TR id=\"comm".$vol->id."\" class=\"hidden\"><TD colspan=\"8\" class=\"desc\"></TD></TR>";
    echo "</TR>";
  }
  echo "<TABLE>";
  echo $lnpages;
?>

</body>
</html>