<?php
parse_str($_SERVER["QUERY_STRING"]	, $get_array);//print_r($get_array);
//phpinfo();return;
$voile=null;
$site=null;
$datemin=NULL;
$datemax=NULL;
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

function url_with_parameter($paramname, $paramvalue) {
    global $get_array;
    $url = $_SERVER["SCRIPT_NAME"]."?";
    foreach ($get_array as $key => $value) {
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
  <meta name="viewport" content="initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<style type="text/css">
body {
    line-height:22px;
    font-family: sans-serif, monospace;
}
td {
	border: solid 1px #afafaf;
}
.ppt_info{
	border: solid 1px #afafaf;
	padding : 1px;
	margin : 2px;
	cursor : pointer;
}
.ppt_info:hover {
    background : #efefef;
}
.editsitetexte {	
    position: absolute;
    font-size: 12pt;
    left: 5px;
    /* right: 0px; */
    top: 5px;
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
a:visited {
    color: #984695;
}
#details {
    font-size : 10pt;
}
</style>

</head>

<body>
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
	$vols = $lgfr->getRecords(NULL, FALSE, $datemin, $datemax, $voile, $site);
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
	echo "<h1><a href=\"".$_SERVER["SCRIPT_NAME"]."\" style=\"text-decoration:none;\">Carnet de vol".$titrevoile." (".count($vols->vols)." vols, ".Utils::timeFromSeconds($vols->tempstotalvol, TRUE)."".$titredate.")</a> : <a href=\"download.php\" title=\"télécharger la base logfly\"><img src=\"download.svg\" width=\"32px\"></a>";
	echo "&nbsp;<a href=\"#\" onClick=\"MyWindow=window.open('stats.php','MyWindow','width=900,height=380'); return false;\" title=\"Statistiques de vol\"><img src=\"stats.svg\" width=\"32px\"></a>";
	echo "&nbsp;<a href=\"#\" onClick=\"MyWindow=window.open('edit.php','MyWindow','width=600,height=380'); return false;\" title=\"editer le carnet de vol\"><img src=\"edit.svg\" width=\"32px\"></a>";
	echo "&nbsp;<a href=\"#\" style=\"position: relative;\" onClick=\"MyWindow=window.open('editsite.php','MyWindowSite','width=600,height=380'); return false;\" title=\"editer un site\"><span class=\"editsitetexte\">site</span><img src=\"edit.svg\" style=\"position: absolute;\" width=\"32px\"></a>";
	echo "</h1>";
	echo "<h2>Voiles  (".count($vols->voiles).") : </h2><p>".implode(", ", array_map(function($x) { return "<span class=\"ppt_info\"><a href=\"".url_with_parameter("voile", $x->nom)."\">".$x->nom." (".$x->nombrevols." vols, ".Utils::timeFromSeconds($x->tempsvol, TRUE).")</a></span>"; }, $vols->voiles))."</p>";
	if (!is_array($vols->sites))
	    $vols->sites = [$vols->sites];
	echo "<h2>Sites (".count($vols->sites).") : </h2><p>".implode(", ", array_map(function($x) { return "<span class=\"ppt_info\"><a href=\"".url_with_parameter("site", $x->nom)."\">".$x->nom." (".$x->nombrevols." vols, ".Utils::timeFromSeconds($x->tempsvol, TRUE).")</a></span>"; }, $vols->sites/*$lgfr->getInfoSite()*/))."</p>";
	echo "<h2>Détails : </h2>";
	echo "<TABLE id=\"details\">";
	echo "<TR><TH>ID</TH><TH>Date</TH><TH>Heure</TH><TH>Duree</TH><TH>Site</TH><TH>Commentaire</TH><TH>Voile</TH></TR>";
	/*echo "<TR>";
	echo "<TD colspan=\"3\"><b>temps de vol :</b></TD>";
	echo "<TD>".Utils::timeFromSeconds($vols->tempstotalvol)."</TD>";
	echo "</TR>";*/
	foreach ($vols->vols as $vol)
	{
		echo "<TR>";
		echo "<TD><a id=\"v".$vol->id."\" href=\"#v".$vol->id."\">". $vol->id."</a></TD>";
		$nom_parametredate = "datemin";
		$texte_parametredate = "depuis";
		if ($datemin) {
		    $nom_parametredate = "datemax";
		    $texte_parametredate = "jusqu'à";
		}
		echo "<TD><a href=\"".url_with_parameter($nom_parametredate, $vol->date->format('Y-m-d'))."\" title=\"filtrer les vols ".$texte_parametredate." cette date\">". $vol->date->format('d/m/Y')."</a></TD>";
		echo "<TD>". $vol->date->format('H:i:s')."</TD>";
		echo "<TD>". Utils::timeFromSeconds($vol->duree)."</TD>";
		//echo "<TD>". $vol->sduree."</TD>";
		echo "<TD><a href=\"".url_with_parameter("site", $vol->site)."\" title=\"filtrer les vols pour ce site\">".$vol->site."</a>&nbsp;<a href=\"https://maps.google.com/?q=".$vol->latdeco.",".$vol->londeco."\" target=\"_Blank\" class=\"lien_gmaps\" title=\"google maps\">&#9936;</a></TD>";
		echo "<TD>". $vol->commentaire."</TD>";
		echo "<TD><a href=\"".url_with_parameter("voile", $vol->voile)."\" title=\"filtrer les vols pour cette voile\">".$vol->voile."</a></TD>";
		echo "</TR>";
	}
	echo "<TABLE>";
?>

</body>
</html>