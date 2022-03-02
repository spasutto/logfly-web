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
  @include("config.php");
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
  p {
    margin: 0;
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
  .vz {
    position:absolute;
    right:0;
    top:0;
    bottom:100px;
    width:10px;
    z-index : 20;
  }
  #graph {
    position:fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 100px;
  }
  .gras {
    font-weight: bolder;
  }
  .centre {
    text-align: center;
  }
</style>

</head>
<body>

<div id="mapcont">
  <div id="map"></div>
  <div id="vz" class="vz"></div>
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

  Element.prototype.setGradient = function( from, to, horizontal ){
    this.style.background = 'linear-gradient(to '+(horizontal ? 'left' : 'top')+', '+from+', '+to+' 100%)';
  }
  var mapelem = document.getElementById('map');
  var vzelem = document.getElementById('vz');
  var maxvz = 10;

  function componentToHex(c) {
    var hex = Math.round(c).toString(16);
    return hex.length == 1 ? "0" + hex : hex;
  }
  function rgbToHex(r, g, b) {
    return "#" + componentToHex(r) + componentToHex(g) + componentToHex(b);
  }

  var disablescroll = <?php echo isset($_GET['disablescroll']) && $_GET['disablescroll']=='1'?'true':'false'; ?>;
  var graph = new GraphGPX(document.getElementById("graph"), '<?php if (defined('ELEVATIONSERVICE')) echo ELEVATIONSERVICE;?>', disablescroll);
  graph.addEventListener('onposchanged', function(e) {
    marker.setLatLng([e.detail.lat, e.detail.lon]).update();
    mapelem.offsetHeight
    let vz =  Math.min(maxvz, Math.abs(e.detail.vz));
    let dh = mapelem.offsetHeight/2;
    let hvz = vz * dh / maxvz;
    let r = vz*255/maxvz;
    let g = (maxvz-vz)*255/maxvz;
    if (e.detail.vz > 0) {
      vzelem.style.bottom = (100+dh) + 'px';
      vzelem.style.top = (dh-hvz) + 'px';
    vzelem.setGradient('white',rgbToHex(r,g,0));
    } else {
      vzelem.style.top = dh + 'px';
      vzelem.style.bottom = (100+(dh-hvz)) + 'px';
    vzelem.setGradient(rgbToHex(r,g,0), 'white');
    }
  });
  graph.addEventListener('onclick', function(e) {
    map.setView(new L.LatLng(e.detail.lat, e.detail.lon));
  });
  graph.addEventListener('onwheel', function(e) {
    let center = marker.getLatLng();
    let zoom = map.getZoom() + (e.detail>0?-1:1);
    map.setView(center, zoom);
  });
  var map = loadCarto("<?php if (defined('CLEGEOPORTAIL')) echo CLEGEOPORTAIL;?>", disablescroll);
  var marker = L.marker([0,0]).addTo(map);

  function loadGPX() {
    let xhttp = new XMLHttpRequest();
    //xhttp.responseType = 'text';
    xhttp.responseType = 'document';
    xhttp.overrideMimeType('text/xml');
    xhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        if (!this.response) return;
        document.getElementById('formcont').style.display = 'none';
        xml = new XMLSerializer().serializeToString(this.responseXML);
        window.fi = graph.setGPX(this.responseXML);
        //maxvz = Math.max(Math.abs(fi.maxvz), Math.abs(fi.minvz))
        new L.GPX(xml, {async: true,
          marker_options: {
            startIconUrl: '',
            endIconUrl: '',
            shadowUrl: ''
          }}).on('loaded', function(e) {
            map.fitBounds(e.target.getBounds());
          }).addTo(map);
        let btndl = document.getElementById('btnDlTrace');
        btndl.onclick = function() {window.location = "<?php echo strtok($_SERVER['REQUEST_URI'], '?');?>?id="+id+"&igc&dl";};
        btndl.style.display = 'block';
        let divTraceInfos = document.getElementById('divTraceInfos');
        let stats = [
          `alt. max : ${Math.round(fi.maxalt)}m`,
          `alt. min : ${Math.round(fi.minalt)}m`,
          `vz max : ${Math.round(fi.maxvz*10)/10}m/s`,
          `vz min : ${Math.round(fi.minvz*10)/10}m/s`,
          `vx max : ${Math.round(fi.maxvx)}km/h`,
          //`vx min : ${Math.round(fi.minvx)}km/h`,
        ];
        let date = fi.pts[0].time;
        divTraceInfos.innerHTML = '<p class="gras centre">'+('0'+date.getDate()).slice(-2)+"/"+('0'+(date.getMonth()+1)).slice(-2)+"/"+date.getFullYear()+'</p>'+
        stats.join("<BR>").replaceAll(" ", "&nbsp;");
        divTraceInfos.style.display = 'block';
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
