<?php

$WIDTH = 640;
$HEIGHT = 640;

$gd = imagecreatetruecolor($WIDTH, $HEIGHT);

require_once('ElevationService.php');
$es = new ElevationService();
//$elev = $es->getElevation(44.5, 5.5);
$lat = 44.5;$inclat=1/$HEIGHT;
$lon = 5.5;$inclon=1/$WIDTH;
$c = 0;
for ($y=0; $y<$HEIGHT; $y++)
{
  for ($x=0; $x<$WIDTH; $x++)
  {
    $elev = $es->getElevation($lat+(($HEIGHT-$y)*$inclat), $lon+($x*$inclon));
    $c = $elev * 255 / 5000;
    imagesetpixel($gd, $x,$y, imagecolorallocate($gd, $c, $c, $c));
  }
}

header('Content-Type: image/png');
imagepng($gd);

?>