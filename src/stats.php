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
  <script src="lib/chartist-plugin-legend.js"></script>
<style>
  .ct-chart {
    /*width:700px;
    height:200px;*/
  }
  .ct-legend {
           position: relative;
           z-index: 10;
           list-style: none;
           text-align: center;
       }
       .ct-legend li {
           position: relative;
           padding-left: 23px;
           margin-right: 10px;
           margin-bottom: 3px;
           cursor: pointer;
           display: inline-block;
       }
       .ct-legend li:before {
           width: 12px;
           height: 12px;
           position: absolute;
           left: 0;
           content: '';
           border: 3px solid transparent;
           border-radius: 2px;
       }
       .ct-legend li.inactive:before {
           background: transparent;
       }
       .ct-legend.ct-legend-inside {
           position: absolute;
           top: 0;
           right: 0;
       }
       .ct-legend.ct-legend-inside li{
           display: block;
           margin: 0;
       }
       .ct-legend .ct-series-0:before {
           background-color: #d70206;
           border-color: #d70206;
       }
       .ct-legend .ct-series-1:before {
           background-color: #f05b4f;
           border-color: #f05b4f;
       }
       .ct-legend .ct-series-2:before {
           background-color: #f4c63d;
           border-color: #f4c63d;
       }
       .ct-legend .ct-series-3:before {
           background-color: #d17905;
           border-color: #d17905;
       }
       .ct-legend .ct-series-4:before {
           background-color: #453d3f;
           border-color: #453d3f;
       }

       .ct-chart-line-multipleseries .ct-legend .ct-series-0:before {
           background-color: #d70206;
           border-color: #d70206;
       }

       .ct-chart-line-multipleseries .ct-legend .ct-series-1:before {
           background-color: #f4c63d;
           border-color: #f4c63d;
       }

       .ct-chart-line-multipleseries .ct-legend li.inactive:before {
           background: transparent;
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

  //echo "<pre>";print_r($vols);echo "</pre>";exit(0);
  //echo "<pre>";print_r($lgfr->getStats());echo "</pre>";exit(0);
?>
<h2>Temps de vol (h)</h2>
<div class="ct-chart" id="chartYearTime"></div>
<h2>Nombre de vol</h2>
<div class="ct-chart" id="chartYearCount"></div>
<h2>Temps de vol global par année (h)</h2>
<div class="ct-chart" id="chartYearTimeByYear"></div>
<h2>Temps de vol par année (h)</h2>
<div class="ct-chart" id="chartYearTimeByYearMonth"></div>
<h2>Nombre de vol par année</h2>
<div class="ct-chart" id="chartYearCountByYear"></div>
<script type="text/javascript">

<?php
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
echo "labels: dataTime.labels,series:[[".implode(",", $count)."]]";
?>
  };
  var dataTimeByYear = {
<?php
echo "labels: ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],";
echo "series:[";
for ($y=0; $y<count($years); $y++) {
  $curhours = 0;
  $volsy = array_filter($vols->vols, function($vol) {
    global $years, $y;
    return intval($vol->date->format("Y")) == intval(str_replace("'","",$years[$y]));
  });
  if (count($volsy) <= 0)
  {
    echo "[0,0,0,0,0,0,0,0,0,0,0,0,],";
    continue;
  }
  echo "[";
  for ($m=0; $m<12; $m++) {
    $volsm = array_filter($volsy, function($vol) {
      global $m;
      return intval($vol->date->format("m")) == $m;
    });
    $curhours+=array_sum(array_map(function($vol) {
      return $vol->duree/3600;
    }, $volsm));
    echo $curhours.",";
  }
  echo "],";
}
echo "],legends:dataTime.labels";
?>
  };
  var dataTimeByYearMonth = {
<?php
echo "labels: ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],";
echo "series:[";
for ($y=0; $y<count($years); $y++) {
  $volsy = array_filter($vols->vols, function($vol) {
    global $years, $y;
    return intval($vol->date->format("Y")) == intval(str_replace("'","",$years[$y]));
  });
  if (count($volsy) <= 0)
  {
    echo "[0,0,0,0,0,0,0,0,0,0,0,0,],";
    continue;
  }
  echo "[";
  for ($m=0; $m<12; $m++) {
    $volsm = array_filter($volsy, function($vol) {
      global $m;
      return intval($vol->date->format("m")) == $m;
    });
    echo array_sum(array_map(function($vol) {
      return $vol->duree/3600;
    }, $volsm)).",";
  }
  echo "],";
}
echo "],legends:dataTime.labels";
?>
  };
  var dataCountByYear = {
<?php
echo "labels: ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],";
echo "series:[";
for ($y=0; $y<count($years); $y++) {
  $volsy = array_filter($vols->vols, function($vol) {
    global $years, $y;
    return intval($vol->date->format("Y")) == intval(str_replace("'","",$years[$y]));
  });
  if (count($volsy) <= 0)
  {
    echo "[0,0,0,0,0,0,0,0,0,0,0,0,],";
    continue;
  }
  echo "[";
  for ($m=0; $m<12; $m++) {
    $volsm = array_filter($volsy, function($vol) {
      global $m;
      return intval($vol->date->format("m")) == $m;
    });
    echo array_sum(array_map(function($vol) {
      return 1;
    }, $volsm)).",";
  }
  echo "],";
}
echo "],legends:dataTime.labels";
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
              if (typeof options.fontsize === 'string')
                 label.attr({'font-size': options.fontsize});
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
  var options = {
    /*
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
  new Chartist.Line('#chartYearTimeByYear', dataTimeByYear, Object.assign(options, {plugins: [plughours, Chartist.plugins.legend({
            legendNames: dataTimeByYear.legends,
        })]}));
  new Chartist.Line('#chartYearTimeByYearMonth', dataTimeByYearMonth, Object.assign(options, {plugins: [plughours, Chartist.plugins.legend({
            legendNames: dataTimeByYearMonth.legends,
        })]}));
  new Chartist.Line('#chartYearCountByYear', dataCountByYear, Object.assign(options, {plugins: [plugnb, Chartist.plugins.legend({
            legendNames: dataCountByYear.legends,
        })]}));
  });
</script>
<h2>Sites (<?php echo count($vols->sites);?> sites)</h2>

<h3>par temps total :</h3>
<div class="ct-chart full" id="chartSites"></div>
<script type="text/javascript">

<?php
//echo "<pre>";print_r($lgfr->getStats());echo "</pre>";
$sites = array_map(function($s) {return "'".str_replace("'", "\\'", $s->nom)."'";}, $vols->sites);
$count = array_map(function($s) {return $s->tempsvol/3600;}, $vols->sites);
?>
  window.addEventListener('load', function(){
    var data = {
<?php
echo "labels: [".implode(",", $sites)."],series:[[".implode(",", $count)."]]";
?>
    };

    window.plughourssmall = Chartist.plugins.ctBarLabels({suffix : ' h', 'fontsize': '8pt'});
    new Chartist.Bar('#chartSites', data, Object.assign({}, {plugins: [plughourssmall]}));
  });
</script>
<h3>par temps de vol moyen :</h3>
<div class="ct-chart full" id="chartSitesTpsVolMoyen"></div>
<?php
function sort_sites_tempsvol($a, $b)
{
  $a = $a->tempsvol/$a->nombrevols;
  $b = $b->tempsvol/$b->nombrevols;
  if ($a == $b) {
    return 0;
  }
  return ($a > $b) ? -1 : 1;
}
usort($vols->sites, "sort_sites_tempsvol");
$sites = array_map(function($s) {return "'".str_replace("'", "\\'", $s->nom)."'";}, $vols->sites);
$count = array_map(function($s) {return ($s->tempsvol/$s->nombrevols)/3600;}, $vols->sites);
?>
<script type="text/javascript">
  window.addEventListener('load', function(){
    var data = {
<?php
echo "labels: [".implode(",", $sites)."],series:[[".implode(",", $count)."]]";
?>
    };
    new Chartist.Bar('#chartSitesTpsVolMoyen', data, Object.assign({}, {plugins: [plughourssmall]}));
  });
</script>

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