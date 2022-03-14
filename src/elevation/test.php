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
$MAXSIZE = 640;

require_once('ElevationService.php');
$es = new ElevationService();
//$elev = $es->getElevation(44.5, 5.5);

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

// Padding
$add = ($trkmaxlat-$trkminlat)*0.15;
$trkmaxlat+=$add;$trkminlat-=$add;
$add = ($trkmaxlon-$trkminlon)*0.3;
$trkmaxlon+=$add;$trkminlon-=$add;

$add = ($trkmaxlon-$trkminlon)/($trkmaxlat-$trkminlat);
if ($add<0)
{
  $WIDTH = $MAXSIZE;
  $HEIGHT = ceil($add*$MAXSIZE);
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
imagepng($gd);

$lastx=-1;
$lasty=-1;
function setPixelLatLon($lat, $lon)
{
  global $gd, $blue, $HEIGHT, $WIDTH, $trkminlon, $trkmaxlon, $trkminlat, $trkmaxlat, $lastx, $lasty;
  $x = $WIDTH*($lon-$trkminlon)/($trkmaxlon-$trkminlon);
  $y = $HEIGHT-($HEIGHT*($lat-$trkminlat)/($trkmaxlat-$trkminlat));
  if ($x == $lastx && $y == $lasty) return;
  imagesetpixel($gd, $x, $y, $blue);
  $lastx=$x;
  $lasty=$y;
}

function getTrack($id)
{
  require("../logfilereader.php");
  require('../Trackfile-Lib/TrackfileLoader.php');
  try
  {
    $tfreader = TrackfileLoader::load("../Tracklogs/".$id.".igc");
    return $tfreader->getRecords();
  }
  catch(Exception $e)
  {
    echo "error!!! : ".$e->getMessage();
  }
  exit(0);
}
?>