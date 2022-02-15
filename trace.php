<?php
  $id = -1;
  if (isset($_REQUEST['id']))
    $id = $_REQUEST['id'];

  if ($id>0 && isset($_REQUEST['gpx']))
  {
    require("logfilereader.php");
    require('Trackfile-Lib/TrackfileLoader.php');
    try
    {
      $lgfr = new LogflyReader();
      $igc = $lgfr->getIGC($id);
      $gpx = TrackfileLoader::toGPX($igc, "igc");
      //header('Content-Type: application/json; charset=utf-8');
      //echo json_encode(array('GPX' => $gpx));
      header("Content-type: text/xml");
      echo $gpx;
    }
    catch(Exception $e)
    {
      echo "error!!! : ".$e->getMessage();
    }
    exit(0);
  }
?>
<!DOCTYPE html>
<html>
<head>
  
  <title>Trace GPS de vol</title>

  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <link rel="shortcut icon" type="image/x-icon" href="docs/images/favicon.ico" />

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A==" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js" integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA==" crossorigin=""></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/1.7.0/gpx.min.js"></script>
    <script src="carto.js"></script>

<style>
  body {
    padding: 0;
    margin: 0;
  }
  html, body, #map {
    height: 100%;
    width: 100%;
  }
</style>
  
</head>
<body>



<div id="map"></div>

<script>
  new L.GPX("<?php echo $_SERVER['REQUEST_URI'];?>&gpx", {async: true,
  marker_options: {
    startIconUrl: '',
    endIconUrl: '',
    shadowUrl: ''
  }}).on('loaded', function(e) {
    map.fitBounds(e.target.getBounds());
  }).addTo(map);
</script>


</body>
</html>
