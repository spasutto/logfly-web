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
      else if (strlen(trim($igc))>0) {
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

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A==" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js" integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA==" crossorigin=""></script>
    <script src='lib/Leaflet.fullscreen.js'></script>
    <link href='lib/leaflet.fullscreen.css' rel='stylesheet' />
    <script src='lib/leaflet.hotline.min.js'></script>
    <script src="carto.js"></script>
    <script src="graph.js"></script>
    <script src="igc-xc-score.js"></script>

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
  .souligne {
    text-decoration: underline;
  }
  #iinfos {
    display: none;
    margin : 5px;
    font-weight: bolder;
    font-size : 15pt;
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
<h1>Calcul de vol</h1>
<label for="file-selector">Sélectionnez un fichier IGC : </label>
<input type="file" id="file-selector" accept=".igc"><HR>
<label for="formigc">Ou placez directement le contenu du fichier IGC : </label>
<form id="formigc" method="post" action="<?php echo strtok($_SERVER['REQUEST_URI'], '?');?>" onsubmit="loadGPX();return false;">
<div style="max-width: 640px">
  <textarea type="text" id="igccont" style="width:100%;height:80px"></textarea><BR>
  <input type="submit" id="frmsub" style="display:none;float: right;" value="calculer">
</div>
</form>
</div>

<script>
  const fileSelector = document.getElementById('file-selector');
  fileSelector.addEventListener('change', (event) => {
    const fileList = event.target.files;
    const reader = new FileReader();
    reader.addEventListener('load', (event) => {
      document.getElementById('igccont').value = reader.result;
      loadGPX();
      document.getElementById('formcont').style.display = 'none';
    });
    reader.readAsText(fileList[0]);
  });

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

  function isTouchDevice() {
    return (('ontouchstart' in window) ||
      (navigator.maxTouchPoints > 0) ||
      (navigator.msMaxTouchPoints > 0));
  }
  function componentToHex(c) {
    var hex = Math.round(c).toString(16);
    return hex.length == 1 ? "0" + hex : hex;
  }
  function rgbToHex(r, g, b) {
    return "#" + componentToHex(r) + componentToHex(g) + componentToHex(b);
  }

  var istouch = isTouchDevice();
  var disablescroll = <?php echo isset($_GET['disablescroll'])?'true':'false'; ?>;
  var map = loadCarto("<?php if (defined('CLEGEOPORTAIL')) echo CLEGEOPORTAIL;?>", disablescroll, document.getElementById('mapcont'));
  var marker = L.marker([0,0]).addTo(map);
  var graph = new GraphGPX(document.getElementById("graph"), '<?php if (defined('ELEVATIONSERVICE')) echo ELEVATIONSERVICE;?>', disablescroll);
  var flstats = [];
  var gpx_bounds = null;
  var touchtimer = null;
  var launchtimer = function(e) {
    touchtimer = window.setTimeout(function(){map.setView(marker.getLatLng());}, 1000);
  };
  graph.addEventListener('touchstart', launchtimer);
  graph.addEventListener('touchend', function(e) {window.clearTimeout(touchtimer);});
  graph.addEventListener('touchmove', function(e) {window.clearTimeout(touchtimer);launchtimer();});
  graph.addEventListener('ondataloaded', function(e) {
    window.fi = e.detail;
    redrawFlight();
    let t = new Date(Date.UTC(1970, 0, 1));
    t.setUTCSeconds((fi.pts[fi.pts.length-1].time.getTime() - fi.pts[0].time.getTime()) / 1000);
    flstats.unshift(
      ['durée', `${t.toLocaleString('fr-FR', { timeZone: 'UTC' }).substr(-8, 5)}`],
      ['alt max', `${Math.round(fi.maxalt)}m`],
      ['alt min', `${Math.round(fi.minalt)}m`],
      ['vz max', `${Math.round(fi.maxvz*10)/10}m/s`],
      ['vz min', `${Math.round(fi.minvz*10)/10}m/s`],
      ['vx max', `${Math.round(fi.maxvx)}km/h`],
      //['vx min', `${Math.round(fi.minvx)}km/h`],
    );
    updateTraceInfos();
  });
  graph.addEventListener('onposchanged', function(e) {
    marker.bindPopup(e.detail.time.toLocaleString('fr-FR', { timeZone: 'UTC' }).substr(-8, 5)).setLatLng([e.detail.lat, e.detail.lon]).update();
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
    /*if (istouch && !map.getBounds().contains(marker.getLatLng())) {
      map.setView(marker.getLatLng());
    }*/
  });
  graph.addEventListener('onclick', function(e) {
    map.setView(new L.LatLng(e.detail.lat, e.detail.lon));
  });
  graph.addEventListener('onwheel', function(e) {
    let center = marker.getLatLng();
    let zoom = map.getZoom() + (e.detail>0?-1:1);
    map.setView(center, zoom);
  });

  function redrawFlight() {
    window.dispmode = typeof window.dispmode === 'string'?window.dispmode:'alt';
    if (dispmode == 'alt') {
      hotlineLayer.setLatLngs(window.fi.pts.map(pt => ([pt.lat, pt.lon, pt.alt])));
      hotlineLayer.setStyle({'min':fi.minalt, 'max':fi.maxalt});
    } else if (dispmode == 'vz') {
      hotlineLayer.setLatLngs(window.fi.pts.map(pt => ([pt.lat, pt.lon, pt.vz])));
      hotlineLayer.setStyle({'min':fi.minvz, 'max':fi.maxvz});
    } else {
      hotlineLayer.setLatLngs(window.fi.pts.map(pt => ([pt.lat, pt.lon, 0])));
      hotlineLayer.setStyle({'min':0, 'max':0.1});
    }
    /*hotlineLayer.setStyle({
      'palette': {
        0.0: '#0000ff',
        0.4: '#00ff00',
        0.7: '#ffff00',
        1.0: '#ff0000'
      }
    });*/
    hotlineLayer.redraw();
  }
  function updateTraceInfos() {
    let divTraceInfos = document.getElementById('divTraceInfos');
    let binfos = true;
    let date = "?";
    if (typeof fi == 'object' && Array.isArray(fi.pts) && fi.pts.length > 0) {
      date = fi.pts[0].time;
      date = ('0'+date.getDate()).slice(-2)+"/"+('0'+(date.getMonth()+1)).slice(-2)+"/"+date.getFullYear();
    }
    divTraceInfos.innerHTML = '<div id="ctinfos"><p class="gras centre souligne">'+date+'</p>'+
    flstats.map(function(info) {return "<p>"+(info[0].length<=0?"<HR>":"<span class=\"gras\">"+info[0]+"</span>&nbsp;:&nbsp;"+info[1])+"</p>";}).join('') + '</div><p id="iinfos">&#9432;</p>';
    divTraceInfos.style.display = 'block';
    divTraceInfos.onclick = function() {
      document.getElementById('ctinfos').style.display = binfos ? 'none':'block';
      document.getElementById('iinfos').style.display = !binfos ? 'none':'block';
      binfos = !binfos;
    };
  }
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
        graph.setGPX(this.responseXML);
        //maxvz = Math.max(Math.abs(fi.maxvz), Math.abs(fi.minvz))
        //this.responseXML.getElementsByTagName('trkpt')[0].getAttribute('lat')
        //this.responseXML.getElementsByTagName('trkpt')[0].getElementsByTagName('ele')[0].innerHTML
        let points = [...this.responseXML.getElementsByTagName('trkpt')].map(pt => ([parseFloat(pt.getAttribute('lat')), parseFloat(pt.getAttribute('lon')),parseFloat(pt.getElementsByTagName('ele')[0].innerHTML)]));
        window.hotlineLayer = L.hotline(points, {
          min: Math.min.apply(null, points.map(pt => pt[2])),
          max: Math.max.apply(null, points.map(pt => pt[2])),
          'palette': {
            0.0: '#0000ff',
            0.4: '#00ff00',
            0.7: '#ffff00',
            1.0: '#ff0000'
          },
          weight: 2,
          outlineColor: '#000000',
          outlineWidth: 1
        });
        window.gpx_bounds = hotlineLayer.getBounds();
        map.fitBounds(gpx_bounds, {padding: [35,35]});
        hotlineLayer.bindPopup('Thanks for clicking.<br/>Play with me!').addTo(map);

        let btndl = document.getElementById('btnDlTrace');
        btndl.onclick = function() {window.location = "<?php echo strtok($_SERVER['REQUEST_URI'], '?');?>?id="+id+"&igc&dl";};
        btndl.style.display = 'block';
        let divDispMode = document.getElementById('divDispMode');
        divDispMode.appendChild(document.getElementById('dispmodes'))
        divDispMode.style.display = 'block';
      }
    };
    let data = null;
    let url = "<?php echo strtok($_SERVER['REQUEST_URI'], '?');?>?";
    if (id>0) url += "id="+id+"&";
    else
    {
      let igccontent = document.getElementById('igccont').value;
      data = "igccont="+escape(igccontent);
      calcFlightScore(igccontent);
    }
    xhttp.open("POST", url+"gpx", true);
    xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    xhttp.send(data);
  }
  function displayFlightScore(flightscore) {
    flstats.push(['','']);
    if (typeof flightscore.scoreInfo == 'object') {
      flstats.push(['distance', `${Math.round(flightscore.scoreInfo.distance*100)/100}km`]);
      flstats.push(['type', `${flightscore.opt.scoring.name}`]);
      if (Array.isArray(flightscore.scoreInfo.tp)) {
        let tps = flightscore.scoreInfo.tp;
        let pointList = [];
        for (let i=0; i<tps.length; i++) {
          L.marker([tps[i].y, tps[i].x], {icon: turnpointIcon}).addTo(map).bindPopup("TP#"+(i+1));
          pointList.push([tps[i].y, tps[i].x]);
        }
        pointList.push([tps[0].y, tps[0].x]);
        new L.Polyline(pointList, {
          color: 'red',
          weight: 2,
          opacity: 0.5,
          smoothFactor: 1
        }).addTo(map);
      }
      if (flightscore.scoreInfo.cp) {
        L.marker([flightscore.scoreInfo.cp.in.y, flightscore.scoreInfo.cp.in.x], {icon: startIcon}).addTo(map).bindPopup("départ");
        L.marker([flightscore.scoreInfo.cp.out.y, flightscore.scoreInfo.cp.out.x], {icon: finishIcon}).addTo(map).bindPopup("arrivée");
      } else if (flightscore.scoreInfo.ep) {
        L.marker([flightscore.scoreInfo.ep.start.y, flightscore.scoreInfo.ep.start.x], {icon: startIcon}).addTo(map).bindPopup("départ");
        L.marker([flightscore.scoreInfo.ep.finish.y, flightscore.scoreInfo.ep.finish.x], {icon: finishIcon}).addTo(map).bindPopup("arrivée");
      }
    }
    flstats.push(['score', `${Math.round(flightscore.score*10)/10}pts`]);
    updateTraceInfos();
  }
  function calcFlightScore(igccontent) {
    try {
      IGCScore.score(igccontent, (score) => {
        if (score && typeof score.value == 'object') {
          score = score.value;
        }
        if (score && typeof score.opt == 'object' && typeof score.opt.flight == 'object') delete score.opt.flight;
        displayFlightScore(score);
      });
    } catch(e) {finish();}
  }
  function loadFlightScore() {
    let xhttp = new XMLHttpRequest();
    xhttp.responseType = 'json';
    xhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        try {
          if (typeof this.response == 'object') {
            displayFlightScore(this.response);
          }
        } catch(e) {}
      }
    };
    xhttp.open("GET", "Tracklogs/"+id+".json", true);
    xhttp.send();
  }
  if (id>0)
    loadGPX();

  window.onload = function() {
    document.getElementById('frmsub').style.display = 'block';
    if (id>0)
      loadFlightScore();
  };

  window.onresize = function(e) {
    if (map && gpx_bounds && !usermoved)
      map.fitBounds(gpx_bounds, {padding: [35,35]});
  };
</script>

<div id="dispmodes">
  <p class="gras centre souligne">Couleur trace :</p>
  <input type="radio" id="dmAlt" name="dispmodes" value="alt" onclick="window.dispmode=this.value;redrawFlight();" checked><label for="dmAlt">altitude</label><BR>
  <input type="radio" id="dmVz" name="dispmodes" value="vz" onclick="window.dispmode=this.value;redrawFlight();"><label for="dmVz">vz</label><BR>
  <input type="radio" id="dmNone" name="dispmodes" value="none" onclick="window.dispmode=this.value;redrawFlight();"><label for="dmNone">aucune</label>
</div>

</body>
</html>
