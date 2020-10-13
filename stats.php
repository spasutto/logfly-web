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
if (isset($_GET['dl'])) {
    $lgfr->downloadCSV();
    exit(0);
}
?><!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>Statistiques de vol</title>
<style>

.chart div {
  font: 10px sans-serif;
  background-color: steelblue;
  text-align: right;
  padding: 3px;
  margin: 1px;
  color: tan;
}
.chart .zonebarre {
  background-color: transparent;
  margin:0px;
  padding: 0;
}

.zonestat {
	width : 800px;
}
.chartlegend {
  background-color: white !important;
	border: 0px solid steelblue;
	border-left: 1px solid black;
	border-right: 1px solid black;
}
</style>


</head>

<body>

<?php
	$vols = $lgfr->getRecords(null, TRUE);
	$nbrvols = count($vols->vols);
	if ($nbrvols <= 0) {
	    exit(0);
	}
    $d1 = $vols->vols[0]->date;
    $d2 = $vols->vols[$nbrvols-1]->date;
    $monthsdiff = $d1->diff($d2)->m + ($d1->diff($d2)->y*12);
	echo "<h1>Statistiques de vol (".$nbrvols." vols, ".Utils::timeFromSeconds($vols->tempstotalvol, TRUE).") :<a href=\"?dl\"><img src=\"csv.svg\" width=\"32px\" title=\"télécharger un fichier csv\"></h1></a>";
    echo "moyenne : ".round($nbrvols/$monthsdiff)." vols par mois, ".Utils::timeFromSeconds($vols->tempstotalvol/$nbrvols, TRUE)." par vol<BR>";

	//echo "<pre>";print_r($vols);echo "</pre>";
?>
<h2>Temps de vol</h2>
<?php
//echo "<pre>";print_r($lgfr->getStats());echo "</pre>";
$stats = $lgfr->getStats();
foreach ($stats as $statyear => $stat)
    echo "<p><b>".$statyear."</b> : ".Utils::timeFromSeconds($stat->TempsVol, True)." (".$stat->NombreVols." vols)</p>";
?>
<h2>Voiles</h2>
<div class="chart zonestat">
	<div style="width: 100%;" class="chartlegend">max</div>
<?php
if (count($vols->sites) > 0)
{
    $maxvols = max(array_map(function($o) {return $o->tempsvol;}, $vols->voiles));
    $sumvols = array_sum(array_map(function($o) {return $o->tempsvol;}, $vols->voiles));
    foreach ($vols->voiles as $voile)
    {
        $percent = round($voile->tempsvol*100/$maxvols);
        $percentsum = round($voile->tempsvol*100/$sumvols);
    	echo "\t<div style=\"width: ".$percent."%;\" title=\"".$voile->nombrevols." vols pour ".$sduree = Utils::timeFromSeconds($voile->tempsvol, TRUE)." soit ".$percentsum."% du total des vols\">".$voile->nom."</div>";
    }
}
?>
</div>

<h2>Sites</h2>
<div class="chart zonestat">
	<div style="width: 100%;" class="chartlegend">max</div>
<?php
if (count($vols->sites) > 0)
{
    $maxvols = max(array_map(function($o) {return $o->tempsvol;}, $vols->sites));
    $sumvols = array_sum(array_map(function($o) {return $o->tempsvol;}, $vols->sites));
    foreach ($vols->sites as $site)
    {
        $percent = round($site->tempsvol*100/$maxvols);
        $percentsum = round($site->tempsvol*100/$sumvols);
    	echo "\t<div style=\"width: ".$percent."%;\" title=\"".$site->nombrevols." vols pour ".$sduree = Utils::timeFromSeconds($site->tempsvol, TRUE)." soit ".$percentsum."% du total des vols\">".$site->nom."</div>";
    }
}
?>
</div>


</body>
</html>