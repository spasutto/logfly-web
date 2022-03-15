<?php
set_time_limit(1020);
/*
header('Content-Type: text');
require_once('ElevationService.php');
$es = new ElevationService();
$elev = $es->getElevation(44, 5.5);
$elev = $es->getElevation(45, 5.5);
return;
*/
/*
$WIDTH = 3601;
$HEIGHT = 3601;

$gd = imagecreatetruecolor($WIDTH, $HEIGHT);
$fp = fopen("HGT/N45E005.hgt", 'r');
$c = 0;
for ($y=0; $y<$HEIGHT; $y++)
{
  for ($x=0; $x<$WIDTH; $x++)
  {
    $alti = fread($fp, 2);
    $alti = (ord($alti[0])<<8)+ord($alti[1]);
    $c = $alti * 255 / 4000;
    imagesetpixel($gd, $x,$y, imagecolorallocate($gd, $c, $c, $c));
  }
}
header('Content-Type: image/png');
imagepng($gd);
fclose($fp);

return;
*/

$MAXSIZE = 4600;

require_once('ElevationService.php');
$es = new ElevationService();
//$elev = $es->getElevation(44.5, 5.5);

$trkminlat = 44.75;
$trkmaxlat = 44.85;
$trkminlon = 5.53;
$trkmaxlon = 5.63;

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

//$WIDTH = ($trkmaxlat-$trkminlat)*1024;
//$HEIGHT = ($trkmaxlon-$trkminlon)*1024;

$gd = imagecreatetruecolor($WIDTH, $HEIGHT);
$inclat=($trkmaxlat-$trkminlat)/$HEIGHT;
$inclon=($trkmaxlon-$trkminlon)/$WIDTH;

$c = 0;
for ($y=0; $y<$HEIGHT; $y++)
{
  for ($x=0; $x<$WIDTH; $x++)
  {
    $elev = $es->getElevation($trkminlat+(($HEIGHT-$y)*$inclat), $trkminlon+($x*$inclon));
    $c = ($elev-700) * 255 / 2500;
    imagesetpixel($gd, $x,$y, imagecolorallocate($gd, $c, $c, $c));
  }
}
header('Content-Type: image/png');
imagepng($gd);
?>