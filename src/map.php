<?php
  /*if ($_GET['user'] != 'sylvain')
  {
    exit(0);
    return;
  }*/
  //phpinfo();
  require("logfilereader.php");
  @include("config.php");
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
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A==" crossorigin=""/>
  <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js" integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA==" crossorigin=""></script>
  <script src='lib/Leaflet.fullscreen.js'></script>
  <link href='lib/leaflet.fullscreen.css' rel='stylesheet' />
  <script src="carto.js"></script>
<style>
  body {
    padding: 0;
    margin: 0;
  }
  html, body, #map_cont {
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



<div id="map_cont"></div>

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
      sitesko = sites.filter(function(s) { return (typeof s.latitude != 'number') || (typeof s.longitude != 'number')/* || (!isNumeric(s.altitude))*/; }).map(function(s) {return s.nom;});
      sites = sites.filter(function(s) { return (typeof s.latitude == 'number') && (typeof s.longitude == 'number')/* && (isNumeric(s.altitude))*/; });
      siteswarning = sites.filter(function(s) { return !isNumeric(s.altitude) || s.altitude == 0; }).map(function(s) {return s.nom;});
      let msg = '';
      if (sitesko.length > 0)
        msg = "Certains sites ne sont pas affichés car ils comportaient des positions invalides : " + sitesko.join(", ");
      if (siteswarning.length > 0)
        msg = "Certains sites comportent des altitudes invalides : " + siteswarning.join(", ");
      if (msg.trim().length > 0)
        message(msg);
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
      window.close();
    }
  }
  function isInLogflyPopup() {
    return (window.opener != null && typeof window.opener.onchangevoilesite == 'function');
  }

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


  window.onload= async function() {
    window.map = await loadCarto("<?php if (defined('CLEGEOPORTAIL')) echo CLEGEOPORTAIL;?>", document.getElementById('map_cont'));
    window.msg = document.getElementById("infobox");
    getSiteList();
    msg.onclick = function() {message();};
  };
</script>


</body>
</html>
