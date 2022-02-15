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
  var map = L.map('map').setView([45.182471 , 5.725589], 13);

  var mapbox = L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token=pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw', {
    maxZoom: 18,
    attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, ' +
      'Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
    id: 'mapbox/streets-v11',
    tileSize: 512,
    zoomOffset: -1
  }).addTo(map);
  var ignphoto = L.tileLayer(
      "https://wxs.ign.fr/decouverte/geoportail/wmts?" +
      "&REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0" +
      "&STYLE=normal" +
      "&TILEMATRIXSET=PM" +
      "&FORMAT=image/jpeg"+
      "&LAYER=ORTHOIMAGERY.ORTHOPHOTOS"+
    "&TILEMATRIX={z}" +
      "&TILEROW={y}" +
      "&TILECOL={x}",
    {
    minZoom : 0,
    maxZoom : 18,
      attribution : '<a target="_blank" href="https://www.geoportail.gouv.fr/">IGN-F/Geoportail</a>',
    tileSize : 256 // les tuiles du Géooportail font 256x256px
    }
  ).addTo(map);
  var carteign = L.tileLayer(
      "https://wxs.ign.fr//geoportail/wmts?" +
      "&REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0" +
      "&STYLE=normal" +
      "&TILEMATRIXSET=PM" +
      "&FORMAT=image/jpeg"+
      "&LAYER=GEOGRAPHICALGRIDSYSTEMS.MAPS"+
      //"&LAYER=GEOGRAPHICALGRIDSYSTEMS.MAPS.SCAN25TOUR"+
    "&TILEMATRIX={z}" +
      "&TILEROW={y}" +
      "&TILECOL={x}",
    {
    minZoom : 0,
    maxZoom : 18,
      attribution : '<a target="_blank" href="https://www.geoportail.gouv.fr/">IGN-F/Geoportail</a>',
    tileSize : 256 // les tuiles du Géooportail font 256x256px
    }
  ).addTo(map);
  var opentopomap = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
    maxZoom: 17,
    attribution: 'Map data: &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, <a href="http://viewfinderpanoramas.org">SRTM</a> | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)'
  }).addTo(map);

  var baseMaps = {
    "MapBox": mapbox,
    "Photos Satellite": ignphoto,
    "Carte IGN": carteign,
    "Carte Topo": opentopomap
  };
  L.control.layers(baseMaps).addTo(map);
  
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
