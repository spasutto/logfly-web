<?php
  $id = -1;
  if (isset($_REQUEST['id']) && preg_match('/^\d+$/', $_REQUEST['id']))
    $id = intval($_REQUEST['id']);
  $igc = "";
  if ($id>0 && isset($_REQUEST['igc']))
  {
    require("logfilereader.php");
    try
    {
      $lgfr = new LogflyReader();
      $igc = $lgfr->getIGC($id);
      if (isset($_REQUEST['dl'])) {
        header('Content-type: text/plain');
        header('Content-Disposition: attachment; filename="flightlog.igc"');
      }
      echo $igc;
      exit(0);
    }
    catch(Exception $e)
    {
      echo "error!!! : ".$e->getMessage();
      exit(0);
    }
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
<form id="formigc" method="post" action="<?php echo strtok($_SERVER['REQUEST_URI'], '?');?>" onsubmit="loadIGC(undefined, true);return false;">
<div style="max-width: 640px">
  <textarea type="text" id="igccont" style="width:100%;height:80px"></textarea><BR>
  <input type="submit" id="frmsub" style="display:none;float: right;" value="afficher">
</div>
</form>
</div>

<div id="dispmodes">
  <p class="gras centre souligne">Couleur trace :</p>
  <input type="radio" id="dmVz" name="dispmodes" value="vz" onclick="window.dispmode=this.value;redrawFlight();" checked><label for="dmVz">vz</label><BR>
  <input type="radio" id="dmAlt" name="dispmodes" value="alt" onclick="window.dispmode=this.value;redrawFlight();"><label for="dmAlt">altitude</label><BR>
  <input type="radio" id="dmNone" name="dispmodes" value="none" onclick="window.dispmode=this.value;redrawFlight();"><label for="dmNone">aucune</label>
</div>

<script>
  const fileSelector = document.getElementById('file-selector');
  fileSelector.addEventListener('change', (event) => {
    const fileList = event.target.files;
    const reader = new FileReader();
    reader.addEventListener('load', (event) => {
      document.getElementById('igccont').value = reader.result;
      loadIGC(reader.result, true);
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
  var graph = new GraphGPX(document.getElementById("graph"), {elevationservice:'<?php if (defined('ELEVATIONSERVICE')) echo ELEVATIONSERVICE;?>', disablescroll: disablescroll});
  var flstats = {};
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
    let s = (fi.pts[fi.pts.length-1].time.getTime() - fi.pts[0].time.getTime()) / 1000;
    let t = new Date(Date.UTC(1970, 0, 1));
    t.setUTCSeconds(s);
    flstats['durée'] = `${t.toLocaleString('fr-FR', { timeZone: 'UTC' }).substr(-8, 5)}`,
    flstats['alt max'] = `${Math.round(fi.maxalt)}m`,
    flstats['alt min'] = `${Math.round(fi.minalt)}m`,
    flstats['vz max'] = `${Math.round(fi.maxvz*10)/10}m/s`,
    flstats['vz min'] = `${Math.round(fi.minvz*10)/10}m/s`,
    flstats['vx max'] = `${Math.round(fi.maxvx)}km/h`,
      //flstats['vx min'] = `${Math.round(fi.minvx)}km/h`,
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
  graph.addEventListener('onselectionchanged', function(e) {
    window.graphsel = e.detail;
    if (typeof graphsel == 'object' && Array.isArray(graphsel) && graphsel.length == 2 && graphsel[1] < fi.pts.length && graphsel[0] != graphsel[1]) {
      let stpt = fi.pts[graphsel[0]],
        endpt = fi.pts[graphsel[1]];
      let dist = GraphGPX.distance(stpt.lat, stpt.lon, endpt.lat, endpt.lon);
      let deniv = stpt.alt - endpt.alt;
      let finesse = Math.round(100*dist / deniv)/100;
      dist /= 1000;
      deniv *= -1;
      dist = Math.round(dist*10)/10;
      if (finesse < 0) {
        finesse = '&infin;';
        deniv = '+' + deniv;
      }
      flstats['finesse'] = `${finesse}<BR><i>(${deniv}m en ${dist}km)</i>`;
      updateTraceInfos();
    }
  });

  function redrawFlight() {
    window.dispmode = typeof window.dispmode === 'string'?window.dispmode:'vz';
    if (dispmode == 'alt') {
      hotlineLayer.setLatLngs(window.fi.pts.map(pt => ([pt.lat, pt.lon, pt.alt])));
      hotlineLayer.setStyle({'min':fi.minalt, 'max':fi.maxalt});
    } else if (dispmode == 'vz') {
      hotlineLayer.setLatLngs(window.fi.pts.map(pt => ([pt.lat, pt.lon, pt.vz])));
      hotlineLayer.setStyle({'min':-5/*fi.minvz*/, 'max':5/*fi.maxvz*/});
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
    divTraceInfos.innerHTML = '<div id="ctinfos"><p class="gras centre souligne">'+date+'</p>';
    //flstats.map(function(info) {return "<p>"+(info[0].length<=0?"<HR>":"<span class=\"gras\">"+info[0]+"</span>&nbsp;:&nbsp;"+info[1])+"</p>";}).join('') + '</div><p id="iinfos">&#9432;</p>';
    for (let prop in flstats)
      divTraceInfos.innerHTML += "<p>"+(flstats[prop].length<=0?"<HR>":"<span class=\"gras\">"+prop+"</span>&nbsp;:&nbsp;"+flstats[prop])+"</p>";
    divTraceInfos.innerHTML += '</div><p id="iinfos">&#9432;</p>';
    divTraceInfos.style.display = 'block';
    divTraceInfos.onclick = function() {
      document.getElementById('ctinfos').style.display = binfos ? 'none':'block';
      document.getElementById('iinfos').style.display = !binfos ? 'none':'block';
      binfos = !binfos;
    };
  }
  function loadIGC(igccont, calcfs) {
    if (typeof igccont !== 'string') {
      igccont = document.getElementById('igccont').value;
      document.getElementById('formcont').style.display = 'none';
    }
    if (calcfs === true) calcFlightScore(igccont);
    let lines = igccont.split(/\r?\n/);
    let records = lines.filter(l => l.trim().startsWith('B'));
    let points = [];
    //0000000000111111111
    //0123456789012345678
    //HFDTEDATE:100522,01
    let startdateline = lines.find(l => l.trim().startsWith('HFDTEDATE'));
    let startdate = new Date();
    if (startdateline) {
      startdate.setDate(parseInt(startdateline.substr(10,2)));
      startdate.setMonth(parseInt(startdateline.substr(12,2))-1);
      let year = 2000+parseInt(startdateline.substr(14,2));
      if (year > startdate.getFullYear()) year -= 100;
      startdate.setFullYear(year);
    }
    records.forEach(r => {
      //0000000000111111111122222222223333333
      //0123456789012345678901234567890123456
      //B0925064454728N00535480EA016100161528
      let date = new Date(startdate);
      date.setHours(parseInt(r.substr(1,2)));
      date.setMinutes(parseInt(r.substr(3,2)));
      date.setSeconds(parseInt(r.substr(5,2)));
      let alt = parseInt(r.substr(25,5));
      if (alt == 0) alt = parseInt(r.substr(30,5));
      let lat = parseFloat(r.substr(7,2));
      lat += parseFloat((r.substr(9,2)+'.'+r.substr(11,3)))/60;
      lat *=  r[14]=='N'?1:-1;
      let lon = parseFloat(r.substr(15,3));
      lon += parseFloat((r.substr(18,2)+'.'+r.substr(20,3)))/60;
      lon *=  r[23]=='E'?1:-1;
      points.push({
        lat: lat,
        lon: lon,
        time: date,
        /*alt: {
          baro: parseInt(r.substr(25,5)),
          gps: parseInt(r.substr(30,5))
        },*/
        alt: alt
      });
    });
    graph.setData(points);
    let pointshotline = points.map(pt => [pt.lat, pt.lon,pt.alt]);
    window.hotlineLayer = L.hotline(pointshotline, {
      min: -5,//Math.min.apply(null, pointshotline.map(pt => pt[2])),
      max: 8,//Math.max.apply(null, pointshotline.map(pt => pt[2])),
      'palette': {
        0.0: '#0000ff',
        0.4: '#00ff00',
        0.7: '#ffff00',
        1.0: '#ff0000'
      },
      weight: 2,
      outlineColor: '#000000',
      outlineWidth: 0.5
    });
    window.gpx_bounds = hotlineLayer.getBounds();
    map.fitBounds(gpx_bounds/*, {padding: [35,35]}*/);
    hotlineLayer.addTo(map);

    let btndl = document.getElementById('btnDlTrace');
    btndl.onclick = function() {window.location = "<?php echo strtok($_SERVER['REQUEST_URI'], '?');?>?id="+id+"&igc&dl";};
    btndl.style.display = 'block';
    let divDispMode = document.getElementById('divDispMode');
    divDispMode.appendChild(document.getElementById('dispmodes'))
    divDispMode.style.display = 'block';
  }
  function getIGC(id) {
    let xhttp = new XMLHttpRequest();
    xhttp.responseType = 'text';
    xhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        if (!this.responseText) return;
        document.getElementById('formcont').style.display = 'none';
        loadIGC(this.response, false);
        document.getElementById('igccont').value = this.response;
      }
    };
    let data = null;
    let url = "<?php echo strtok($_SERVER['REQUEST_URI'], '?');?>?id="+id+"&";
    xhttp.open("POST", url+"igc", true);
    xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    xhttp.send(data);
  }
  function displayFlightScore(flightscore) {
    let isTriangle = function() { return (flightscore.opt.scoring.code == 'tri' || flightscore.opt.scoring.code == 'fai'); };
    flstats['flightscore'] = '';
    if (typeof flightscore.scoreInfo == 'object') {
      flstats['distance'] = `${Math.round(flightscore.scoreInfo.distance*100)/100}km`;
      flstats['type'] = `${flightscore.opt.scoring.name}`;
      if (Array.isArray(flightscore.scoreInfo.tp)) {
        scorepointlist = [];
        let tps = flightscore.scoreInfo.tp;
        if (flightscore.scoreInfo.cp) {
          let tmpmarker = L.marker([flightscore.scoreInfo.cp.in.y, flightscore.scoreInfo.cp.in.x], {icon: startIcon});
          tmpmarker.addTo(map).bindPopup("start");
          if (!isTriangle()) {
            scorepointlist.push(tmpmarker);
          }
        } else if (flightscore.scoreInfo.ep) {
          let tmpmarker = L.marker([flightscore.scoreInfo.ep.start.y, flightscore.scoreInfo.ep.start.x], {icon: startIcon});
          tmpmarker.addTo(map).bindPopup("start");
          if (!isTriangle()) {
            scorepointlist.push(tmpmarker);
          }
        }
        for (let i=0; i<tps.length; i++) {
          let markertp = L.marker([tps[i].y, tps[i].x], {icon: turnpointIcon});
          markertp.addTo(map).bindPopup("TP#"+(i+1));
          scorepointlist.push(markertp);
        }
        if (isTriangle()) {
          scorepointlist.push(scorepointlist[scorepointlist.length-tps.length]);
        }
        if (flightscore.scoreInfo.cp) {
          let tmpmarker = L.marker([flightscore.scoreInfo.cp.out.y, flightscore.scoreInfo.cp.out.x], {icon: finishIcon});
          tmpmarker.addTo(map).bindPopup("finish");
          if (!isTriangle()) {
            scorepointlist.push(tmpmarker);
          }
        } else if (flightscore.scoreInfo.ep) {
          let tmpmarker = L.marker([flightscore.scoreInfo.ep.finish.y, flightscore.scoreInfo.ep.finish.x], {icon: finishIcon});
          tmpmarker.addTo(map).bindPopup("finish");
          if (!isTriangle()) {
            scorepointlist.push(tmpmarker);
          }
        }
        scorelinepath = new L.Polyline(scorepointlist.map(pt => pt.getLatLng()), {
          color: 'red',
          weight: 2,
          opacity: 0.5,
          smoothFactor: 1
        }).addTo(map);
      }
    }
    flstats['score'] = `${Math.round(flightscore.score*10)/10}pts`;
    flstats['vit.'] = `${Math.round(36000*flightscore.scoreInfo.distance/(flightscore.opt.landing-flightscore.opt.launch))/10}km/h`;
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
  if (id>0) {
    getIGC(id);
    loadFlightScore();
  }

  window.onload = function() {
    document.getElementById('frmsub').style.display = 'block';
  };

  window.onresize = function(e) {
    if (map && gpx_bounds && !usermoved)
      map.fitBounds(gpx_bounds/*, {padding: [35,35]}*/);
  };
</script>

</body>
</html>
