<?php
require_once('ElevationService.php');

header('Content-type: application/octet-stream');
$fi = fopen("php://input", "rb");
$es = new ElevationService();
$curidx = 0;
//echo '<'.'?xml version="1.0" encoding="UTF-8" standalone="no" ?'.'><gpx xmlns="http://www.topografix.com/GPX/1/1" xmlns:gpxtpx="http://www.garmin.com/xmlschemas/TrackPointExtension/v1" creator="OruxMaps v.9.0.4 GP" version="1.1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd"><trk><trkseg>';
while( $buf = fread($fi, 8) ) { // 16 pour double, 8 pour float
  $idx = ftell($fi);
  $elev = 0;
  if ($idx-$curidx>=8) { // 16 pour double, 8 pour float
    //echo '<trkpt lat="'.unpack('f', substr($buf, 0, 4))[1].'" lon="'.unpack('f', substr($buf, 4, 4))[1].'"/>';
    
    // attention au conflits d'endianness, graph.js envoie en fonction de la machine cliente, ici on décode en little endian
    // float (32 bits)
    $elev = $es->getElevation(@unpack('g', substr($buf, 0, 4))[1], @unpack('g', substr($buf, 4, 4))[1]);
    // double float (64 bits) (passer en Float64Array dans graph.js)
    //$elev = $es->getElevation(@unpack('e', substr($buf, 0, 8))[1], @unpack('e', substr($buf, 8, 8))[1]);
  }
  echo pack('v', $elev);
  $curidx = $idx;
}
fclose($fi);
//echo '</trkseg></trk></gpx>';
?>