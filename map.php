<?php
  /*if ($_GET['user'] != 'sylvain')
  {
    exit(0);
    return;
  }*/
  //phpinfo();
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

  if (isset($_REQUEST['sites']))
  {
    /*foreach ($lgfr->getSites() as $site)
      echo $site.",";*/
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($lgfr->getInfoSite());

    exit(0);
    return;
  }
?>
<!DOCTYPE html>
<html>
<head>
  
  <title>Carte des sites de vol</title>

  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <link rel="shortcut icon" type="image/x-icon" href="docs/images/favicon.ico" />

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A==" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js" integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA==" crossorigin=""></script>

<style>
  body {
    padding: 0;
    margin: 0;
  }
  html, body, #map {
    height: 100%;
    width: 100%;
  }
  #infobox {
    display : none;
    position: absolute;
    left: 0px;
    top: 0px;
    width:100%;
    background-color: white;
    z-index: 1000;
    padding:2px;
  }
</style>
  
</head>
<body>



<div id="map"></div>

<span id="infobox"></span>
<script>
  function getSiteList()
  {
    var xhttp = new XMLHttpRequest();
    xhttp.responseType = 'json';
    message("chargement...");
    xhttp.onload  = function() {
      var sites = xhttp.response;
      if (!Array.isArray(sites)) {
        message("aucun site trouvé...");
        return;
      }
      message();
      sitesko = sites.filter(function(s) { return (typeof s.latitude != 'number') || (typeof s.longitude != 'number') || (!isNumeric(s.altitude)); }).map(function(s) {return s.nom;});
      sites = sites.filter(function(s) { return (typeof s.latitude == 'number') && (typeof s.longitude == 'number') && (isNumeric(s.altitude)); });
      if (sitesko.length > 0)
        message("Certains sites ne sont pas affichés car ils comportaient des positions invalides : " + sitesko.join(", "));
      //sites = sites.filter(function(s) { return (typeof s.latitude != 'number') || (typeof s.longitude != 'number') || (!isNumeric(s.altitude)); });
      sites.forEach(function(s) {
        let sitenom = s.nom;
        if (isInLogflyPopup())
          sitenom = "<a href=\"#\" onclick=\"filtre('"+sitenom.replace('\'', '\\\'')+"');\" title=\"filtrer les vols pour ce site\">"+sitenom+"</a>";
        L.marker([s.latitude, s.longitude]).addTo(map).bindPopup(sitenom);
      });
      let maxlat = Math.max.apply(Math, sites.map(function(o) { return o.latitude; }));
      let minlat = Math.min.apply(Math, sites.map(function(o) { return o.latitude; }));
      let maxlon = Math.max.apply(Math, sites.map(function(o) { return o.longitude; }));
      let minlon = Math.min.apply(Math, sites.map(function(o) { return o.longitude; }));
      let bounds = new L.LatLngBounds([[maxlat, maxlon], [minlat, minlon]]);
      map.fitBounds(bounds, {padding: [5, 5]});
    };
    xhttp.open("GET", "<?php echo $_SERVER['REQUEST_URI'];?>?sites", true);
    xhttp.send();
  }
  function isNumeric(str) {
    if (typeof str != "string") return false // we only process strings!  
    return !isNaN(str) && // use type coercion to parse the _entirety_ of the string (`parseFloat` alone does not do this)...
      !isNaN(parseFloat(str)) // ...and ensure strings of whitespace fail
  }
  function message(mesg) {
    mesg = typeof mesg == 'string' ? mesg : "";
    msg.innerHTML = mesg;
    if (mesg == "")
      msg.style.display = 'none';
    else
      msg.style.display = 'initial';
  }
  function filtre(site) {
    if (isInLogflyPopup()) {
      window.opener.onchangevoilesite(site, false);
      //window.close();
    }
  }
  function isInLogflyPopup() {
    return (window.opener != null && typeof window.opener.onchangevoilesite == 'function');
  }

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

  /*L.marker([51.5, -0.09]).addTo(map)
    .bindPopup("<b>Hello world!</b><br />I am a popup.").openPopup();

  L.circle([51.508, -0.11], 500, {
    color: 'red',
    fillColor: '#f03',
    fillOpacity: 0.5
  }).addTo(map).bindPopup("I am a circle.");

  L.polygon([
    [51.509, -0.08],
    [51.503, -0.06],
    [51.51, -0.047]
  ]).addTo(map).bindPopup("I am a polygon.");


  var popup = L.popup();

  function onMapClick(e) {
    popup
      .setLatLng(e.latlng)
      .setContent("You clicked the map at " + e.latlng.toString())
      .openOn(map);
  }

  map.on('click', onMapClick);*/

  //window.onload= function() {
    window.msg = document.getElementById("infobox");
    getSiteList();
    msg.onclick = function() {message();};
  //};
</script>


</body>
</html>
