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
  $lgfr->downloadCSV(TRUE);
  exit(0);
}
?><!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>Statistiques de vol</title>

  <link rel="stylesheet" href="//cdn.jsdelivr.net/chartist.js/latest/chartist.min.css">
  <script src="//cdn.jsdelivr.net/chartist.js/latest/chartist.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/locale/fr.min.js"></script>
<style>
  .ct-chart {
    /*width:700px;
    height:200px;*/
  }

  .full {
    width : 100%;
  }

  .zoom:hover{
    transform: scale(2.5);
  }

  svg.ct-chart-bar, svg.ct-chart-line{
    overflow: visible;
  }
  svg text {
      text-shadow: 0px 0px 9px rgba(255,0,0,1);
    fill: #ff00cf;
    font-family:sans-serif;
    font-weight:600;
  }
  .ct-label.ct-label.ct-horizontal.ct-end {
    position: relative;
    justify-content: flex-end;
    text-align: right;
    transform-origin: 100% 0;
    transform: translate(-50%) rotate(-45deg);
    white-space:nowrap;
  }
</style>
<script type="text/javascript">
  function init() {
  }
  </script>

</head>

<body onload="init();">

<?php
  $vols = $lgfr->getRecords(null, TRUE);
  $nbrvols = count($vols->vols);
  if ($nbrvols <= 0) {
    exit(0);
  }
  $d1 = $vols->vols[0]->date;
  $d2 = $vols->vols[$nbrvols-1]->date;
  $monthsdiff = $d1->diff($d2)->m + ($d1->diff($d2)->y*12);
  if ($monthsdiff <= 0)
  $monthsdiff = 1;
  echo "<h1>Statistiques de vol (".$nbrvols." vols, ".Utils::timeFromSeconds($vols->tempstotalvol, TRUE).") :<a href=\"?dl\"><img src=\"csv.svg\" width=\"32px\" title=\"télécharger un fichier csv\"></a></h1>";
  echo "moyenne : ".round($nbrvols/$monthsdiff)." vols par mois, ".Utils::timeFromSeconds($vols->tempstotalvol/$nbrvols, TRUE)." par vol<BR>";

  //echo "<pre>";print_r($vols);echo "</pre>";
?>
<h2>Temps de vol (h)</h2>
<div class="ct-chart" id="chartYearTime"></div>
<h2>Nombre de vol</h2>
<div class="ct-chart" id="chartYearCount"></div>
<script type="text/javascript">

<?php
//echo "<pre>";print_r($lgfr->getStats());echo "</pre>";
$stats = $lgfr->getStats();
$years = array_map(function($y) {return "'".$y."'";}, array_keys($stats));
$count = array_map(function($stat) {return $stat->NombreVols;}, array_values($stats));
$time = array_map(function($stat) {return $stat->TempsVol/3600;}, array_values($stats));
//</b> : ".Utils::timeFromSeconds($stat->TempsVol, True)." (".$stat->NombreVols." vols)</p>";
?>
window.addEventListener('load', function(){
  var dataTime = {
<?php
echo "labels: [".implode(",", $years)."],series:[[".implode(",", $time)."]]";
?>
  };
  var dataCount = {
<?php
echo "labels: [".implode(",", $years)."],series:[[".implode(",", $count)."]]";
?>
  };
  
  function isNumeric(str) {
      if (typeof str != "string") return false // we only process strings!  
      return !isNaN(str) && // use type coercion to parse the _entirety_ of the string (`parseFloat` alone does not do this)...
             !isNaN(parseFloat(str)) // ...and ensure strings of whitespace fail
    }

  Chartist.plugins = Chartist.plugins || {};
  Chartist.plugins.ctBarLabels = function (options) {

    options = Chartist.extend({}, options);

    return function ctBarLabels(chart) {
      if (chart instanceof Chartist.Bar) {
        chart.on('draw', function (data) {
          var barHorizontalCenter, barVerticalCenter, label, value;
          if (data.type === "bar") {
            barHorizontalCenter = data.x1 + (data.element.width() * .5);// + 20;
            barVerticalCenter = data.y1 + (data.element.height() * -1) - 10;
            value = data.element.attr('ct:value');
            if (isNumeric(value)) {
              title = new Chartist.Svg('title');
              if (typeof options.suffix === 'string')
                title.text(("" + Math.round(value*10)/10) + options.suffix);
              else
                title.text(("" + Math.round(value*10)/10));
              value = Math.round(value);
              if (typeof options.suffix === 'string')
                value = "" + value + options.suffix;
              label = new Chartist.Svg('text');
              label.append(title);
              label.text(value);
              label.addClass("ct-barlabel");
              label.attr({
                x: barHorizontalCenter,
                y: barVerticalCenter,
                'text-anchor': 'middle'
              });
              return data.group.append(label);
            }
          }
        });
      }
    };
  };
  var options = {/*
    high: 10,
    low: -10,
    axisX: {
    labelInterpolationFnc: function(value, index) {
      return index % 2 === 0 ? value : null;
    }
    }*/
  };
  
  window.plughours = Chartist.plugins.ctBarLabels({suffix : ' h'});
  window.plugnb = Chartist.plugins.ctBarLabels();

  new Chartist.Bar('#chartYearTime', dataTime, Object.assign(options, {plugins: [plughours]}));
  new Chartist.Bar('#chartYearCount', dataCount, Object.assign(options, {plugins: [plugnb]}));
  });
</script>
<h2>Sites (<?php echo count($vols->sites);?> sites, par temps de vol)</h2>

<div class="ct-chart full" id="chartSites"></div>
<script type="text/javascript">

<?php
//echo "<pre>";print_r($lgfr->getStats());echo "</pre>";
$sites = array_map(function($s) {return "'".str_replace("'", "\\'", $s->nom)."'";}, $vols->sites);
$count = array_map(function($s) {return $s->tempsvol/3600;}, $vols->sites);
$tempsparvol = "<ul>".implode("\n",array_map(function($v) {return "<li>".$v->nom." =&gt; ".Utils::timeFromSeconds(60*round(($v->tempsvol/$v->nombrevols)/60), 2)."</li>";}, $vols->sites))."</ul>";
//$countparvols = array_map(function($s) {return ($s->tempsvol/$s->nombrevols)/3600;}, $vols->sites);
//$maxvols = max(array_map(function($o) {return $o->tempsvol;}, $vols->sites));
//$sumvols = array_sum(array_map(function($o) {return $o->tempsvol;}, $vols->sites));
//$percent = round($site->tempsvol*100/$maxvols);
//$percentsum = round($site->tempsvol*100/$sumvols);
//echo "\t<div style=\"width: ".$percent."%;\" title=\"".$site->nombrevols." vols pour ".$sduree = Utils::timeFromSeconds($site->tempsvol, TRUE)." soit ".$percentsum."% du total des vols\">".$site->nom."</div>";
?>
  window.addEventListener('load', function(){
    var data = {
<?php
echo "labels: [".implode(",", $sites)."],series:[[".implode(",", $count)."]]";
?>
    };

    new Chartist.Bar('#chartSites', data);
  });
</script>
<h3>temps par vol moyen :</h3>
<div style="max-height:250px;overflow-y:scroll;border:solid 1px lightgray;">
<?php
echo $tempsparvol;
?>
</div>

<h2>Voiles (<?php echo count($vols->voiles);?> voiles, par temps de vol)</h2>

<div class="ct-chart zoom" id="chartVoiles"></div>
<script type="text/javascript">

<?php
//echo "<pre>";print_r($lgfr->getStats());echo "</pre>";
$voiles = array_map(function($v) {return "'".$v->nom."'";}, $vols->voiles);
$count = array_map(function($v) {return $v->tempsvol/3600;}, $vols->voiles);
$tempsparvol = "<ul>".implode("\n",array_map(function($v) {return "<li>".$v->nom." =&gt; ".Utils::timeFromSeconds(60*round(($v->tempsvol/$v->nombrevols)/60), 2)."</li>";}, $vols->voiles))."</ul>";
//$maxvols = max(array_map(function($o) {return $o->tempsvol;}, $vols->voiles));
//$sumvols = array_sum(array_map(function($o) {return $o->tempsvol;}, $vols->voiles));
//$percent = round($voile->tempsvol*100/$maxvols);
//$percentsum = round($voile->tempsvol*100/$sumvols);
//echo "\t<div style=\"width: ".$percent."%;\" title=\"".$voile->nombrevols." vols pour ".$sduree = Utils::timeFromSeconds($voile->tempsvol, TRUE)." soit ".$percentsum."% du total des vols\">".$voile->nom."</div>";
?>
  window.addEventListener('load', function(){
    var data = {
<?php
echo "labels: [".implode(",", $voiles)."],series:[".implode(",", $count)."]";
?>
    };

new Chartist.Pie('#chartVoiles', data);
  });
</script>
<h3>temps par vol moyen :</h3>
<div style="max-height:250px;overflow-y:scroll;border:solid 1px lightgray;">
<?php
echo $tempsparvol;
?>
</div>

<h2>Evolution du temps de vol</h2>

<div class="ct-chart" id="chartTemps"></div>
<script type="text/javascript">

<?php
/*
function calcTemps()
{
  global $vols;
  $tempvol = 0;
  $curmois = NULL;
  $temps = ["mois" => [], "total" => []];

  foreach ($vols->vols as $vol)
  {
    $mois = $vol->date->format('M Y');
    if ($mois != $curmois)
    {
      $curmois = $mois;
      $temps["mois"][$curmois] = 0;
    }
    $temps["mois"][$curmois] += $vol->duree;
    $temps["total"][$vol->date->format('d/m/Y')] = $vol->duree;
  }
  return $temps;
}

$temps = calcTemps();
$moisvol = array_map(function($v) {return "'".$v."'";}, array_keys($temps["mois"]));
$tempsvol = array_map(function($v) {return $v/3600;}, array_values($temps["mois"]));
$tempsvoltotal = array_map(function($v) {return $v;}, array_values($temps["total"]));
*/

function calcTemps()
{
  global $vols;
  $tempvol = 0;
  $temps = [];

  foreach ($vols->vols as $vol)
  {
    $tempvol += $vol->duree;
    $temps[$vol->date->format('d/m/Y')] = $tempvol;
  }
  return $temps;
}

$temps = calcTemps();
$moisvol = array_map(function($v) {return DateTime::createFromFormat('d/m/Y', $v)->getTimestamp()*1000;}, array_keys($temps));
$tempsvol = array_map(function($v) {return $v/3600;}, array_values($temps));
?>
  window.addEventListener('load', function(){
    var data = [
<?php
for ($i=0; $i<count($moisvol); $i++)
  echo "{x: new Date(".$moisvol[$i]."), y:".$tempsvol[$i]."},";
//echo "labels: [".implode(",", $moisvol)."],series:[[".implode(",", $tempsvol)."]]";
?>
    ];

    new Chartist.Line('#chartTemps', {
  series: [
  {
    name: 'series-1',
    data: data
  }
  ]
}, {
  axisX: {
  type: Chartist.FixedScaleAxis,
  divisor: 10,
  labelInterpolationFnc: function(value) {
    return moment(value).format('MMM Y');
  }
  }
});
  });
</script>

</body>
</html>