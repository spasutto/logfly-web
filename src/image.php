<?php

$id=FALSE;
if (isset($_POST['id']) && preg_match('/^\d+$/', $_POST['id']))
  $id = intval($_POST['id']);
else if (isset($_GET['id']) && preg_match('/^\d+$/', $_GET['id']))
  $id = intval($_GET['id']);

if (!$id || $id <= 0)
{
  exit(0);
}

$TRACKLOGS = "Tracklogs".DIRECTORY_SEPARATOR;
$fname = $TRACKLOGS.$id.".png";
if (false && file_exists($fname))
{
  header('Location: '.$fname);
  /*header('Content-Type: image/png');
  echo file_get_contents($fname);*/
  exit(0);
}
$MAXSIZE = 320;

require_once('elevation/ElevationService.php');
$es = new ElevationService("elevation/HGT");
/*
$es->getElevation(44.000423928572, 6.4993751666667);echo "<BR>";
$es->getElevation(44.000230722912, 6.4379216666667);exit(0);
*/
$pts = getTrack($id);
// Coordonées max de la carte
$trkmaxlat = -200;$trkminlat=200;$trkmaxlon = -200;$trkminlon=200;
foreach ($pts as $pt)
{
  if ($pt->latitude > $trkmaxlat) $trkmaxlat = $pt->latitude;
  if ($pt->latitude < $trkminlat) $trkminlat = $pt->latitude;
  if ($pt->longitude > $trkmaxlon) $trkmaxlon = $pt->longitude;
  if ($pt->longitude < $trkminlon) $trkminlon = $pt->longitude;
}

/*$trkminlat =  45.29;
$trkmaxlat =  45.32;
$trkminlon = 5.87;
$trkmaxlon =  5.91;*/

// Padding
$add = ($trkmaxlat-$trkminlat)*0.15;
$trkmaxlat+=$add;$trkminlat-=$add;
$add = ($trkmaxlon-$trkminlon)*0.3;
$trkmaxlon+=$add;$trkminlon-=$add;

$add = ($trkmaxlon-$trkminlon)/($trkmaxlat-$trkminlat);
if ($add>0)
{
  $WIDTH = $MAXSIZE;
  $HEIGHT = ceil($MAXSIZE/$add);
}
else
{
  $WIDTH = ceil($add*$MAXSIZE);
  $HEIGHT = $MAXSIZE;
}

/*$trkminlat = 44.8;
$trkmaxlat = 45;
$trkminlon = 5.57;
$trkmaxlon = 5.65;*/

//$WIDTH = ($trkmaxlat-$trkminlat)*1024;
//$HEIGHT = ($trkmaxlon-$trkminlon)*1024;

$inclat=($trkmaxlat-$trkminlat)/$HEIGHT;
$inclon=($trkmaxlon-$trkminlon)/$WIDTH;

$elevs = array();
$minalt = 10000;$maxalt = -1000;
for ($y=0; $y<$HEIGHT; $y++)
{
  for ($x=0; $x<$WIDTH; $x++)
  {
    if ($x == 0) $elevs[$y] = array();
    $elev = $es->getElevation($trkminlat+(($HEIGHT-$y)*$inclat), $trkminlon+($x*$inclon));
    if ($elev > $maxalt) $maxalt = $elev;
    if ($elev < $minalt) $minalt = $elev;
    $elevs[$y][$x] = $elev;
  }
}
/*header('Content-Type: text');
print_r($elevs);
return;*/

$gd = imagecreatetruecolor($WIDTH, $HEIGHT);

$c = 0;
for ($y=0; $y<$HEIGHT; $y++)
{
  for ($x=0; $x<$WIDTH; $x++)
  {
    $elev = $elevs[$y][$x];
    $c = ($elev-$minalt) * 255 / $maxalt;
    imagesetpixel($gd, $x,$y, imagecolorallocate($gd, $c, $c, $c));
  }
}

$blue = imagecolorallocate($gd,0,0,255);
foreach ($pts as $pt)
{
  setPixelLatLon($pt->latitude, $pt->longitude);
}
header('Content-Type: image/png');
ob_start();
imagepng($gd);
$imagedata = ob_get_clean();
file_put_contents($fname, $imagedata);
echo $imagedata;

$lastx=-1;
$lasty=-1;
$first = true;
function setPixelLatLon($lat, $lon)
{
  global $gd,$first, $blue, $HEIGHT, $WIDTH, $trkminlon, $trkmaxlon, $trkminlat, $trkmaxlat, $lastx, $lasty;
  $x = $WIDTH*($lon-$trkminlon)/($trkmaxlon-$trkminlon);
  $y = $HEIGHT-($HEIGHT*($lat-$trkminlat)/($trkmaxlat-$trkminlat));
  if ($x == $lastx && $y == $lasty) return;
  if ($lastx+$lasty>0 && (abs($lastx - $x) > 3 || abs($lasty - $y) > 3))
    imageline($gd, $lastx, $lasty, $x, $y, $blue);
  else
    imagesetpixel($gd, $x, $y, $blue);
  $lastx=$x;
  $lasty=$y;
  $first = false;
}

function getTrack($id)
{
  global $TRACKLOGS;
  require("logfilereader.php");
  require('Trackfile-Lib/TrackfileLoader.php');
  try
  {
    $tfreader = TrackfileLoader::load($TRACKLOGS.$id.".igc");
    return $tfreader->getRecords();
  }
  catch(Exception $e)
  {
    echo "error!!! : ".$e->getMessage();
  }
  exit(0);
}
?>