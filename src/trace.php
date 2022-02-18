<?php
  $id = -1;
  if (isset($_REQUEST['id']) && preg_match('/^\d+$/', $_REQUEST['id']))
    $id = intval($_REQUEST['id']);

  if ((isset($_REQUEST['gpx']) || isset($_REQUEST['igc'])) && ($id>0 || isset($_POST['igccont'])))
  {
    require("logfilereader.php");
    require('Trackfile-Lib/TrackfileLoader.php');
    try
    {
      if ($id>0)
      {
        $lgfr = new LogflyReader();
        $igc = $lgfr->getIGC($id);
      }
      else 
        $igc = $_POST['igccont'];
      if (!isset($_REQUEST['gpx'])) {
        header('Content-type: text/plain');
        if (isset($_REQUEST['dl']))
          header('Content-Disposition: attachment; filename="flightlog.igc"');
        echo $igc;
      }
      else {
        $gpx = TrackfileLoader::toGPX($igc, "igc");
        //header('Content-Type: application/json; charset=utf-8');
        //echo json_encode(array('GPX' => $gpx));
        header("Content-type: text/xml");
        echo $gpx;
      }
    }
    catch(Exception $e)
    {
      echo "error!!! : ".$e->getMessage();
    }
    exit(0);
  }
  @include("keys.php");
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
    <script src="graph.js"></script>

<style>
  body {
    padding: 0;
    margin: 0;
  }
  html, body {
    height: 100%;
    width: 100%;
  }
  #formcont {
    position:fixed;
    left:0;top:0;right:0;bottom:0;    
    background-color:white;
  }
  #map {
    position: fixed;
    top: 0;
    bottom: 100px;
    left: 0;
    right: 0;
    overflow: hidden;
  }
  #graph {
    position:fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 100px;
  }
</style>
  
</head>
<body>

<div id="mapcont">
  <div id="map"></div>
  <div id="graph"></div>
</div>

<div id="formcont">
<form id="formigc" method="post" action="<?php echo strtok($_SERVER['REQUEST_URI'], '?');?>" onsubmit="loadGPX();return false;">
<input type="submit" style="height:100%; width:20%">
<textarea type="text" size="50" id="igccont" style="width:95%;height:95vh"></textarea>
</form>
</div>

<script>
  var id = new URL(window.location.href).searchParams.get("id");
  if (id > 0) {
    document.getElementById('formcont').style.display = 'none';
  }

  var graph = new GraphGPX(document.getElementById("graph"));
  graph.addEventListener('onposchanged', function(e) {
    marker.setLatLng([e.detail.lat, e.detail.lon]).update();
  });

  var map = loadCarto("<?php if (defined('CLEGEOPORTAIL')) echo CLEGEOPORTAIL;?>");

  function loadGPX() {
    var xhttp = new XMLHttpRequest();
    //xhttp.responseType = 'text';
    xhttp.responseType = 'document';
    xhttp.overrideMimeType('text/xml');
    xhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        if (!this.response) return;
        document.getElementById('formcont').style.display = 'none';
        xml = new XMLSerializer().serializeToString(this.responseXML);
        //console.log(this.response, this.responseXML);
        new L.GPX(xml, {async: true,
          marker_options: {
            startIconUrl: '',
            endIconUrl: '',
            shadowUrl: ''
          }}).on('loaded', function(e) {
            map.fitBounds(e.target.getBounds());
          }).addTo(map);
        window.marker = L.marker([0,0]).addTo(map);
        graph.setGPX(this.responseXML);
        let btndl = document.getElementById('btnDlTrace');
        btndl.onclick = function() {window.location = "<?php echo strtok($_SERVER['REQUEST_URI'], '?');?>?id="+id+"&igc&dl";};
        btndl.style.display = 'block';
      }
    };
    let data = null;
    let url = "<?php echo strtok($_SERVER['REQUEST_URI'], '?');?>?";
    if (id>0) url += "id="+id+"&";
    else data = "igccont="+escape(document.getElementById('igccont').value);
    xhttp.open("POST", url+"gpx", true);
    xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    xhttp.send(data);
  }
  if (id>0)
    loadGPX();

</script>


</body>
</html>
