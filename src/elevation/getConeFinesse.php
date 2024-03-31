<?php

require_once('ElevationService.php');

// test sur surface plane : https://montagne.pasutto.net/Parapente/finesse/cone_finesse.php?lat=44.50706493154673&lon=6.3236617231721945&finesse=3&agl=100

const R = 6371000; // rayon de la Terre, en m
const SQUARESIDE = 150; // 100m de côté
const NBRTILES = 129; // nombres de tiles (carré de côté, et doit être impaire)
const FINESSEDEFAULT = 8; // finesse par défaut
$finesse = 8;
$tiles = null;
$dx = 0;
$dy = 0;
$diagtile = SQUARESIDE*sqrt(2);
$altlossft=SQUARESIDE/$finesse;
$altlossdiag=$diagtile/$finesse;
$es = new ElevationService();

function calcStartCone($startpoint, $_finesse = FINESSEDEFAULT, $startalt = 0, $typealt='AGL') {
  global $dx, $dy, $diagtile, $altlossft, $altlossdiag, $tiles, $es, $finesse;
  $finesse = $_finesse;
  $pos1 = posFromDistance($startpoint->lat, $startpoint->lng, SQUARESIDE, 90);
  $dx = abs($pos1->lng-$startpoint->lng);
  $pos1 = posFromDistance($startpoint->lat, $startpoint->lng, SQUARESIDE, 180);
  $dy = abs($pos1->lat-$startpoint->lat);
  $altlossft=SQUARESIDE/$finesse;
  $altlossdiag=$diagtile/$finesse;

  $elev = $es->getElevation($startpoint->lat, $startpoint->lng);
  if ($typealt=='AMSL') {
    $startalt -= $elev;
  }
  $z = (object) ['pos'=>$startpoint, 'altgnd'=>$elev, 'alt'=>$startalt, 'x'=>0, 'y'=>0, 'parent'=>null];
  set_time_limit(350);//5 minutes
  $tiles = possibledestinations($z);
}

function possibledestinations($start) {
  global $es, $dx, $dy, $finesse;
  $frontier = array($start);
  $visited = array();
  $visited[0][0] = $start;
  $prev = null;
  $iter = 0;
  while (count($frontier)>0) {
    $currentNode = array_pop($frontier);
    if ($iter>0 && $currentNode->alt <= 0) continue;
    $neighbors = getneighbours($currentNode, $visited, $prev);
    foreach ($neighbors as $neighbor) {
      $newalt = null;
      if (!isset($neighbor->pos)) {
        $neighbor->pos = (object) ["lat" => $currentNode->pos->lat+($neighbor->y-$currentNode->y)*$dy, "lng" => $currentNode->pos->lng+($neighbor->x-$currentNode->x)*$dx];
        $neighbor->altgnd = $es->getElevation($neighbor->pos->lat, $neighbor->pos->lng);
      }
      if (!isset($neighbor->alt) || $neighbor->alt<($newalt=altfortile($currentNode, $neighbor))) {
        if (isset($newalt)) {
          $neighbor->alt = $newalt;
          removeTile($frontier, $neighbor);
        }
        else {
          $neighbor->alt = altfortile($currentNode, $neighbor);
        }
        $neighbor->parent = (object) ["x" => $currentNode->x, "y" => $currentNode->y];
        $frontier[] = $neighbor;
        $visited[$neighbor->x][$neighbor->y] = $neighbor;
        $prev=$neighbor;
      }
    }
    $iter++;
  }

  return call_user_func_array('array_merge', $visited);
}
function altfortile($prev, $cur) {
  global $es, $dx, $dy, /*$diagtile,*/$altlossft, $altlossdiag, $finesse;
  if (!isset($cur->pos)) {
    $cur->pos = (object) ["lat" => $prev->pos->lat+($cur->y-$prev->y)*$dy, "lng" => $prev->pos->lng+($cur->x-$prev->x)*$dx];
    $cur->altgnd = $es->getElevation($cur->pos->lat, $cur->pos->lng);
  }
  $altloss = ($prev->x==$cur->x || $prev->y==$cur->y) ? $altlossft : $altlossdiag;
  return ($prev->altgnd+$prev->alt) - $altloss - $cur->altgnd;
}
function getneighbours($node, $visited, $prev) {
  $neighbours = array();
  for ($x=-1; $x<=1; $x++) {
    for ($y=-1; $y<=1; $y++) {
      if ($x==0 && $y==0) continue;
      $rx = $node->x+$x;
      $ry = $node->y+$y;
      if ($prev && $prev->x==$rx && $prev->yx==$ry) continue;
      $neighbour = (object) ['x'=>$rx, 'y'=>$ry];
      $neighbour = $visited[$neighbour->x][$neighbour->y] ?? $neighbour;
      $neighbours[] = $neighbour;
    }
  }
  return $neighbours;
}
function removeTile(&$array, $tile) {
  $x=$tile->x;
  $y=$tile->y;
  for ($i=0;$i<count($array);$i++) {
    if ($array[$i]->x==$x && $array[$i]->y==$y) {
      array_splice($array, $i, 1);
      return;
    }
  }
}

// Converts from degrees to radians.
function toRadians($degrees) {
  return $degrees * M_PI / 180;
}
// Converts from radians to degrees.
function toDegrees($radians) {
  return $radians * 180 / M_PI;
}
function posFromDistance($latitude, $longitude, $distance, $bearing) {
  // taken from: https://stackoverflow.com/a/46410871/13549 
  $brng = $bearing * M_PI / 180; // Convert bearing to radian
  $lat = $latitude * M_PI / 180; // Current coords to radians
  $lon = $longitude * M_PI / 180;

  // Do the math magic
  $lat = asin(sin($lat) * cos($distance / R) + cos($lat) * sin($distance / R) * cos($brng));
  $lon += atan2(sin($brng) * sin($distance / R) * cos($lat), cos($distance / R) - sin($lat) * sin($lat));

  // Coords back to degrees and return
  return (object) ['lat'=>($lat * 180 / M_PI), 'lng'=>($lon * 180 / M_PI)];
}

//header("Content-Type: text/plain");
header('Content-Type: application/json; charset=utf-8');
//echo 'Current PHP version: ' . phpversion().'\n';

if (!isset($_REQUEST['lat']) || !isset($_REQUEST['lon']) || !is_numeric($_REQUEST['lat']) || !is_numeric($_REQUEST['lon'])) {
  header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
  die;
}
$lat = floatval($_REQUEST['lat']);
$lng = floatval($_REQUEST['lon']);
$finesse = FINESSEDEFAULT;
if (isset($_REQUEST['finesse']) && is_numeric($_REQUEST['finesse']))
  $finesse=floatval($_REQUEST['finesse']);
$typealt='AGL';
$startalt = 0;
if (isset($_REQUEST['agl']) && is_numeric($_REQUEST['agl'])) {
  $startalt=floatval($_REQUEST['agl']);
} else if (isset($_REQUEST['amsl']) && is_numeric($_REQUEST['amsl'])) {
  $startalt=floatval($_REQUEST['amsl']);
  $typealt='AMSL';
}

$start = microtime(true);
calcStartCone((object) ["lat" => $lat, "lng" => $lng], $finesse, $startalt, $typealt);

foreach ($tiles as $tile)
  $tile->alt = round($tile->alt*10)/10;
/*for ($i=count($tiles); $i>=0; $i--) {
  if ($tiles[$i]->alt <= 0) array_splice($tiles, $i, 1);
  else $tiles[$i]->alt = round($tiles[$i]->alt);
}*/
  
$ret = (object) ["squareside" => SQUARESIDE, "dx" => $dx, "dy" => $dy, "tiles" => $tiles, "time" => floor((microtime(true)-$start) * 1000)];

echo json_encode($ret);
?>