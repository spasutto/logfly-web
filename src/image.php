<?php
require("config.php");

$id=FALSE;
if (isset($_POST['id']) && preg_match('/^\d+$/', $_POST['id']))
  $id = intval($_POST['id']);
else if (isset($_GET['id']) && preg_match('/^\d+$/', $_GET['id']))
  $id = intval($_GET['id']);

if (!$id || $id <= 0)
{
  exit(0);
}

$force = $_GET['force'] === '1';
$debug = $_GET['debug'] === '1';
$fixedzoom = (isset($_GET['zoom']) && preg_match("/^\d+$/i", $_GET['zoom'])) ? intval($_GET['zoom']) : -1;

$TRACKLOGS = "Tracklogs".DIRECTORY_SEPARATOR;
//array_map('unlink', glob( __DIR__.DIRECTORY_SEPARATOR."Tracklogs".DIRECTORY_SEPARATOR."*.jpg"));
$fname = $TRACKLOGS.$id.".jpg";
if (!$debug && !$force && file_exists($fname))
{
  //header('Location: '.$fname);
  printHeaders();
  //header("Access-Control-Allow-Origin: https://*.pasutto.net/");
  echo file_get_contents($fname);
  exit(0);
}
$MAXSIZE = 640;

/*
require_once('elevation/ElevationService.php');
$es = new ElevationService("elevation/HGT");
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


// Padding
$add = ($trkmaxlat-$trkminlat)*0.15;
$trkmaxlat+=$add;$trkminlat-=$add;
$add = ($trkmaxlon-$trkminlon)*0.3;
$trkmaxlon+=$add;$trkminlon-=$add;


//https://wxs.ign.fr/CLEGEOPORTAIL/geoportail/wmts?&REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE=normal&TILEMATRIXSET=PM&FORMAT=image/jpeg&LAYER=GEOGRAPHICALGRIDSYSTEMS.MAPS&TILEMATRIX=16&TILEROW=23637&TILECOL=33801
/*
<TileMatrix>
    <ows:Identifier>10</ows:Identifier>
    <ScaleDenominator>545978.7734655447186469</ScaleDenominator>
    <TopLeftCorner>-20037508.3427892476320267 20037508.3427892476320267</TopLeftCorner>
    <TileWidth>256</TileWidth>
    <TileHeight>256</TileHeight>
    <MatrixWidth>1024</MatrixWidth>
    <MatrixHeight>1024</MatrixHeight>
</TileMatrix>
<TileMatrix>
    <ows:Identifier>12</ows:Identifier>
    <ScaleDenominator>136494.6933663861796617</ScaleDenominator>
    <TopLeftCorner>-20037508.3427892476320267 20037508.3427892476320267</TopLeftCorner>
    <TileWidth>256</TileWidth>
    <TileHeight>256</TileHeight>
    <MatrixWidth>4096</MatrixWidth>
    <MatrixHeight>4096</MatrixHeight>
</TileMatrix>
<TileMatrix>
    <ows:Identifier>14</ows:Identifier>
    <ScaleDenominator>34123.6733415965449154</ScaleDenominator>
    <TopLeftCorner>-20037508.3427892476320267 20037508.3427892476320267</TopLeftCorner>
    <TileWidth>256</TileWidth>
    <TileHeight>256</TileHeight>
    <MatrixWidth>16384</MatrixWidth>
    <MatrixHeight>16384</MatrixHeight>
</TileMatrix>
<TileMatrix>
    <ows:Identifier>16</ows:Identifier>
    <ScaleDenominator>8530.9183353991362289</ScaleDenominator>
    <TopLeftCorner>-20037508.3427892476320267 20037508.3427892476320267</TopLeftCorner>
    <TileWidth>256</TileWidth>
    <TileHeight>256</TileHeight>
    <MatrixWidth>65536</MatrixWidth>
    <MatrixHeight>65536</MatrixHeight>
</TileMatrix>
    */
    
//https://geoservices.ign.fr/documentation/services/api-et-services-ogc/images-tuilees-wmts-ogc
//https://wxs.ign.fr/essentiels/geoportail/wmts?SERVICE=WMTS&REQUEST=GetCapabilities

// TODO : à récupérer avec ScaleDenominator depuis https://wxs.ign.fr/CLEGEOPORTAIL/geoportail/wmts?SERVICE=WMTS&REQUEST=GetCapabilities
// XPATH : //TileMatrix/(concat(Identifier/text(), ' -> ', ScaleDenominator/text()))
// penser à vérifier que la layer supporte l'échelle choisie (cf en dessous minzoom/maxzoom)
$X0 = -20037508.3427892476320267;
$Y0 = 20037508.3427892476320267;
$scaledenominators = [
    559082264.0287178958533332,
    279541132.0143588959472254,
    139770566.0071793960087234,
    69885283.0035897239868063,
    34942641.5017948619934032,
    17471320.7508974309967016,
    8735660.3754487154983508,
    4367830.1877243577491754,
    2183915.0938621788745877,
    1091957.5469310886252288,
    545978.7734655447186469,
    272989.3867327723085907,
    136494.6933663861796617,
    68247.3466831930771477,
    34123.6733415965449154,
    17061.8366707982724577,
    8530.9183353991362289,
    4265.4591676995681144,
    2132.7295838497840572,
    1066.3647919248918304,
    533.1823959624461134,
    266.5911979812228585,
];
//sur la couche GEOGRAPHICALGRIDSYSTEMS.MAPS il y'a 19 niveaux de zoom (cf TileMatrixSetLimits sur https://wxs.ign.fr/CLEGEOPORTAIL/geoportail/wmts?SERVICE=WMTS&REQUEST=GetCapabilities)
$minzoom = 0;
$maxzoom = 18;
// sur GEOGRAPHICALGRIDSYSTEMS.MAPS.SCAN25TOUR c'est 6->16
// donc on fixe le max à 16 car les 17 et 18 de GEOGRAPHICALGRIDSYSTEMS.MAPS sont pas très interessant
$maxzoom = 16;

// 2.4 m/px pour le niveau 16 https://geoservices.ign.fr/documentation/services/api-et-services-ogc/images-tuilees-wmts-ogc#1592
// mais ça se calcule en mutlipliant ScaleDenominator par 0.00028 :
// https://gis.stackexchange.com/a/315989 "The scale denominator is defined with respect to a "standardized rendering pixel size" of 0.28 mm × 0.28 mm (millimeters)."
function getResolution($zoom) {
    global $scaledenominators;
    if ($zoom < 0) $zoom = 0;
    else if ($zoom >= count($scaledenominators)) $zoom = count($scaledenominators)-1;
    return 0.00028 * $scaledenominators[$zoom];
}
function lngLatToXY($lon, $lat)
{
    89.99999 < $lat ? $lat = 89.99999 : -89.99999 > $lat && ($lat = -89.99999);
    $c = .017453292519943 * $lat;
    return [111319.49079327169 * $lon, 3189068.5 * log((1 + sin($c)) / (1 - sin($c)))];
}

function xyTolngLatBak($x, $y){
    $a = 3189068.5;
    return [$x/111319.49079327169, asin((exp($y/$a)-1)/(exp($y/$a)+1))/.017453292519943];
}
function xyTolngLat($x, $y, $c=false) {
    $x = $x / 6378137 * 57.29577951308232;
    return $c ? [$x, 57.29577951308232 * (1.5707963267948966 - 2 * atan(exp(-1 * $y / 6378137)))] : [$x - 360 * floor(($x + 180) / 360), 57.29577951308232 * (1.5707963267948966 - 2 * atan(exp(-1 * $y / 6378137)))];
}
function lngLatToTileXY($lat, $lon, $zoom){
    global $X0, $Y0;
    $xy = lngLatToXY($lat, $lon);
    $x = $xy[0];
    $y = $xy[1];

    $resolution = getResolution($zoom);
    $size = 256*$resolution;
    $x = ($x-$X0)/$size;
    $y = ($Y0-$y)/$size;
    return [floor($x), floor($y)];
}
function tileXYToLngLat($x, $y, $zoom) {
    global $X0, $Y0;
    $resolution = getResolution($zoom);
    $size = 256*$resolution;
    $x = ($x*$size)+$X0;
    $y = $Y0-($y*$size);
    return xyTolngLat($x, $y);
}
$TL = [$trkminlon, $trkmaxlat];
$BR = [$trkmaxlon, $trkminlat];

/*
$zoom = 14;
if ($trkmaxlat-$trkminlat>0.6 || $trkmaxlon-$trkminlon>0.6)
  $zoom = 10;
else if ($trkmaxlat-$trkminlat>0.4 || $trkmaxlon-$trkminlon>0.4)
  $zoom = 12;
*/
if ($fixedzoom >= $minzoom && $fixedzoom<=$maxzoom) {
  $zoom = $fixedzoom;
} else {
  $zoom = $maxzoom;//count($scaledenominators)-1;
}
do {
    $TLxy = lngLatToTileXY($TL[0], $TL[1], $zoom);
    $BRxy = lngLatToTileXY($BR[0], $BR[1], $zoom);
    $heighty = $BRxy[1] - $TLxy[1] + 1;
    $widthx = $BRxy[0] - $TLxy[0] + 1;
    if ($fixedzoom >= $minzoom && $fixedzoom<=$maxzoom) break;
}
while (($widthx>7 || $heighty>7) && --$zoom>=$minzoom);
$TLlnglat = tileXYToLngLat($TLxy[0], $TLxy[1], $zoom);
$BRlnglat = tileXYToLngLat($BRxy[0]+1, $BRxy[1]+1, $zoom);
$trkminlon = $TLlnglat[0];
$trkmaxlat = $TLlnglat[1];
$trkmaxlon = $BRlnglat[0];
$trkminlat = $BRlnglat[1];

$WIDTH=$widthx*256;
$HEIGHT=$heighty*256;
$xfactor=$yfactor=1;
$tilewidth = 256;
$tileheight = 256;

if ($WIDTH>$MAXSIZE || $HEIGHT>$MAXSIZE)
{
    $oldwidth = $WIDTH;
    $oldheight = $HEIGHT;
    //$add = ($trkmaxlon-$trkminlon)/($trkmaxlat-$trkminlat);
    $add = $WIDTH/$HEIGHT;
    if ($add>1)
    {
      $WIDTH = $MAXSIZE;
      $HEIGHT = ceil($MAXSIZE/$add);
    }
    else
    {
      $WIDTH = ceil($add*$MAXSIZE);
      $HEIGHT = $MAXSIZE;
    }
    //if (imagecopyresized($resized, $img, 0, 0, 0, 0, $WIDTH, $HEIGHT, $oldwidth, $oldheight))
    $xfactor = $WIDTH/$oldwidth;
    $yfactor = $HEIGHT/$oldheight;
    $tilewidth = ceil($tilewidth*$xfactor);
    $tileheight = ceil($tileheight*$yfactor);
}
if ($debug) {
  //header('Content-Type: text/plain');
  header('Content-Type: text/html');
  echo("");
  echo("<pre>xfactor=$xfactor\n");
  echo("yfactor=$yfactor\n");
  echo("trkminlon=$trkminlon\n");
  echo("trkmaxlat=$trkmaxlat\n");
  echo("trkmaxlon=$trkmaxlon\n");
  echo("trkminlat=$trkminlat\n");
  echo("lon w =".($trkmaxlon-$trkminlon)."\n");
  echo("lat h =".($trkmaxlat-$trkminlat)."\n");
  echo("TL=");print_r($TL);
  echo("BR=");print_r($BR);
  echo("TLxy=");print_r($TLxy);
  echo("BRxy=");print_r($BRxy);
  echo("TLlnglat=");print_r($TLlnglat);
  echo("BRlnglat=");print_r($BRlnglat);
  echo "zoom: ".$zoom."\n";
  echo "widthx: ".$widthx." heighty: ".$heighty." (".($widthx*$heighty)." tuiles)\n";
  echo "WIDTH: ".$WIDTH." HEIGHT: ".$HEIGHT."\n";
  echo "oldwidth: ".$oldwidth." oldheight: ".$oldheight."\n";
  echo "</pre><table>";
}

$img = imagecreatetruecolor($WIDTH, $HEIGHT);
$layer = 'GEOGRAPHICALGRIDSYSTEMS.MAPS';
if ($zoom >= 14 && $zoom <= 16)
  $layer = 'GEOGRAPHICALGRIDSYSTEMS.MAPS.SCAN25TOUR'; // fond IGN 25 systématiquement
$cle = CLEGEOPORTAIL;
if (defined('CLEGEOPORTAIL2')) $cle = CLEGEOPORTAIL2;
for ($y=0; $y<$heighty; $y++)
{
  if ($debug) echo "<TR>";
  for ($x=0; $x<$widthx; $x++)
  {
    $tilex = $TLxy[0]+$x;
    $tiley = $TLxy[1]+$y;
    $url = 'https://wxs.ign.fr/'.$cle.'/geoportail/wmts?&REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE=normal&TILEMATRIXSET=PM&FORMAT=image/jpeg&LAYER='.$layer.'&TILEMATRIX='.$zoom.'&TILEROW='.$tiley.'&TILECOL='.$tilex;
    if ($debug) {
      echo "<TD><a href=\"".$url."\"><img src=\"".$url."\" width=\"200px\"></a></TD>";
      continue;
    }
    $imgtmp = @imagecreatefromjpeg($url);
    if (!$imgtmp) continue;
    if ($xfactor != 1 || $yfactor != 1)
      /*imagecopyresized*/imagecopyresampled($img, $imgtmp, $x*$tilewidth, $y*$tileheight, 0, 0, $tilewidth, $tileheight, 256, 256);
    else
      imagecopy($img, $imgtmp, $x*256, $y*256, 0, 0, 256, 256);
  }
  if ($debug) echo "</TR>";
}
if ($debug) {
  echo "</table>";
  exit(0);
}

$blue = imagecolorallocate($img,0,0,255);
$thickness = max($WIDTH, $HEIGHT)/4;
//$thickness = $WIDTH/4;
imagesetthickness($img,4);
foreach ($pts as $pt)
{
  setPixelLatLon($pt->latitude, $pt->longitude);
}

//header('Content-Type: text/plain');exit(0);
/*header('Content-Type: image/png');
ob_start();
imagepng($img);*/
printHeaders();
ob_start();
imagejpeg($img);
$imagedata = ob_get_clean();
file_put_contents($fname, $imagedata);
echo $imagedata;

$lastx=-1;
$lasty=-1;
$first = true;
function setPixelLatLon($lat, $lon)
{
  global $img,$first, $blue, $HEIGHT, $WIDTH, $trkminlon, $trkmaxlon, $trkminlat, $trkmaxlat, $lastx, $lasty;
  $x = $WIDTH*($lon-$trkminlon)/($trkmaxlon-$trkminlon);
  $y = $HEIGHT-($HEIGHT*($lat-$trkminlat)/($trkmaxlat-$trkminlat));
  if ($x == $lastx && $y == $lasty) return;
  /*if ($lastx+$lasty>0 && (abs($lastx - $x) > 3 || abs($lasty - $y) > 3))
    imageline($img, $lastx, $lasty, $x, $y, $blue);
  else
    imagesetpixel($img, $x, $y, $blue);*/
  if ($lastx+$lasty>0 && (abs($lastx - $x) > 0 || abs($lasty - $y) > 0))
  {
    imageline($img, $lastx, $lasty, $x, $y, $blue);
  }
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
    header("HTTP/1.0 404 Not Found");
    //echo "error!!! : ".$e->getMessage();
  }
  exit(0);
}

function printHeaders()
{
  $is_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
  @header('Access-Control-Allow-Origin: http'.($is_secure?'s':'').'://montagne.pasutto.net');
  @header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + (365 * 24 * 60 * 60)));
  @header('Content-Type: image/jpeg');
}
?>