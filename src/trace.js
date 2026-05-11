var tiles = [], tilepaths = [], finesse = 8, dx=0, dy=0, ddx=0, ddy=0, squareside=100;
const tileopacity = 0.5;
const urlParams = new URLSearchParams(window.location.search);

const fileSelector = document.getElementById('file-selector');
fileSelector.addEventListener('change', (event) => {
  const fileList = event.target.files;
  let filecount = fileList.length;
  let igccont = '';
  [...fileList].forEach(file => {
      const reader = new FileReader();
      reader.addEventListener('load', (event) => {
        igccont += (reader.result + '\n');
        document.getElementById('igccont').value = igccont;
        filecount--;
        document.getElementById('formcont').style.display = 'none';
        if (filecount <= 0) computeIGC(igccont, true);
      });
      reader.readAsText(file);
  });
  event.target.value=null;
});

Element.prototype.setGradient = function( from, to, horizontal ){
  this.style.background = 'linear-gradient(to '+(horizontal ? 'left' : 'top')+', '+from+', '+to+' 100%)';
}
var mapelem = document.getElementById('map_elem');
var vzelem = document.getElementById('vz');
var maxvz = 10;
//http://192.168.1.16/web/logfly-web/src/trace.php?igc=http%3A%2F%2F192.168.1.16%2Fweb%2Flogfly-web%2Fsrc%2FTracklogs%2F392.igc&finfo=http%3A%2F%2F192.168.1.16%2Fweb%2Flogfly-web%2Fsrc%2FTracklogs%2F392.json
var url = new URL(window.location.href);

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
function getParameter(param, clean) {
  let val = url.searchParams.get(param);
  if (val && clean)
    val = val.trim().toLowerCase();
  return val;
}

var istouch = isTouchDevice();
var igc = getParameter("igc");
var tzoffset = parseInt(getParameter("tzoffset", true));
tzoffset = typeof tzoffset === 'number' && !isNaN(tzoffset) ? tzoffset : null;
var launchtime = parseInt(getParameter("start", true), 10);
launchtime = !!launchtime && typeof launchtime === 'number' && !isNaN(launchtime) ? new Date(launchtime*1000) : null;
var duration = parseInt(getParameter("duration", true), 10);
if (duration <= 0 || isNaN(duration)) duration = -1;
var finfo = getParameter("finfo");
var disablescroll = getParameter("disablescroll", true);disablescroll = disablescroll == "true" || disablescroll == "1";
var clegeoportail = getParameter("clegeoportail");
var cletimezonedb = getParameter("cletimezonedb") || '';

window.graph = new GraphGPX(document.getElementById("graphelm"), {disablescrollzoom: disablescroll});
graph.addAnalyser(new AnaTrace(graph));
//graph.addAnalyser(new AnaDiffTrace(graph));
loadCarto(clegeoportail, document.getElementById('map_elem'), disablescroll, document.getElementById('mapcont')).then(map => {
  window.map = map;

  window.marker = L.marker([0,0]).addTo(map);
  //var svg = '<svg viewBox="0 0 24 24" height="32" width="32" fill="transparent" stroke="black" stroke-width="2" stroke-linecap="round" xmlns="http://www.w3.org/2000/svg"> <g> <path d="M1 1 l22 22"/> <path d="M1 23 l22 -22"/> </g> </svg>';
  //var iconUrl = 'data:image/svg+xml;base64,' + btoa(svg);
  origIconSize = [32, 32];
  origIconAnchor = [16, 32];
  let iconUrl = 'paraglider.svg';
  //let iconUrl = 'crosshair.svg';
  fetch(iconUrl)
  .then(response => response.text())
  .then((svg) => {
    //let iconUrl = 'data:image/svg+xml;base64,' + btoa(svg);
    //var icon = L.icon({iconUrl: iconUrl, iconSize: origIconSize,iconAnchor: origIconAnchor});
    let icon = L.divIcon({html: svg, className: "",iconSize: origIconSize,iconAnchor: origIconAnchor});
    marker.setIcon(icon);
  })
  .catch(err => {
    console.log(err);
    var icon = L.icon({iconUrl: iconUrl, iconSize: origIconSize,iconAnchor: origIconAnchor});
    marker.setIcon(icon);
  });
  let closegraphcfg = function() {graph.opencfg(true);};
  var elevtimer = null;
  var launchelevtimer = function(e) {
    elevtimer = window.setTimeout(async function(){
      closegraphcfg();
      //if (setCursorFromMap(e.latlng) < (istouch?10:100)) return;
      if (!e.latlng) return;
      let alt = await getElevation(e.latlng.lat, e.latlng.lng);
      let removePopup = _ => {window.posmarker?.remove();window.posmarker=null;};
      if (!window.posmarker) {
        window.posmarker = L.marker(e.latlng).addTo(map).on('click', removePopup);
      } else {
        window.posmarker.setLatLng(e.latlng);
      }
      window.posmarker.bindPopup('<p>altitude : ' + alt + 'm</p>').openPopup();
    }, 1000);
  };
  var clearelevtimer = function(e) {
    //e.originalEvent.stopPropagation();
    window.setTimeout(function() {window.posmarker?.openPopup();}, 200);
    window.clearTimeout(elevtimer);
  };
  map.on('mousedown', launchelevtimer);
  map.on('mouseup', clearelevtimer);
  /*async function(e) {
    e.originalEvent.stopPropagation();
  });*/
  map.on('mousemove', function(e) {
    setCursorFromMap(e.latlng);
  });
  map.on('fullscreenchange', function () {
    closegraphcfg();
    if (map.isFullscreen()) {
        graph.disableScrollZoom = false;
    } else {
        graph.disableScrollZoom = disablescroll;
    }
  });
  map.on('movestart', closegraphcfg);
  map.on('zoomstart', closegraphcfg);
  map.on('contextmenu',function(e){
    //e.latlng
    map.closePopup();
    if (!window.curpoint) return;
    if (!window.contextpopup) window.contextpopup = L.popup();
    contextpopup.setLatLng(L.latLng(curpoint.lat, curpoint.lon)).setContent(`<button onclick=\"clearCone();clickCalcCone(${JSON.stringify(curpoint).replace(/"/g, '&quot;')});map.closePopup();\">calculer le c&ocirc;ne de finesse (8)<BR> à cet endroit</button>`).openOn(map);
  });
});
var binfos = false;

var flstats = {};
var gpx_bounds = null;
var touchtimer = null;
var launchtouchtimer = function(e) {
  touchtimer = window.setTimeout(function(){map.setView(marker.getLatLng());}, 1000);
};
document.getElementById('btnClose').addEventListener('click', function(e) {
  e.srcElement.parentElement.style.display = 'none';
});
graph.addEventListener('touchstart', launchtouchtimer);
graph.addEventListener('touchend', function(e) {window.clearTimeout(touchtimer);});
graph.addEventListener('touchmove', function(e) {window.clearTimeout(touchtimer);launchtouchtimer();});
graph.addEventListener('ondataloaded', function(e) {
  window.fi = e.detail;
  redrawFlight();
  constructAnalysersOptions();
  let s = duration;
  if (s <= 0) s = (fi.pts[fi.pts.length-1].time.getTime() - fi.pts[0].time.getTime()) / 1000;
  let t = new Date(Date.UTC(1970, 0, 1));
  t.setUTCSeconds(s);
  flstats['durée'] = `${dateToTime(t)}`;
  flstats['alt max'] = `<a href="#" onclick="event.stopPropagation();graph.goto(fi.maxaltt);setViewIfNotVisible(graph.curLatLon);return false;" title="aller vers ce point">${Math.round(fi.maxalt)}m</a>`;
  flstats['alt min'] = `<a href="#" onclick="event.stopPropagation();graph.goto(fi.minaltt);setViewIfNotVisible(graph.curLatLon);return false;" title="aller vers ce point">${Math.round(fi.minalt)}m</a>`;
  flstats['vz max'] = `<a href="#" onclick="event.stopPropagation();graph.goto(fi.maxvzt);setViewIfNotVisible(graph.curLatLon);return false;" title="aller vers ce point">${Math.round(fi.maxvz*10)/10}m/s</a>`;
  flstats['vz min'] = `<a href="#" onclick="event.stopPropagation();graph.goto(fi.minvzt);setViewIfNotVisible(graph.curLatLon);return false;" title="aller vers ce point">${Math.round(fi.minvz*10)/10}m/s</a>`;
  let i=0;
  let moyvzpos = fi.pts.reduce((acc, cur) => {if (cur.vz > 0){i++;acc.vz+=cur.vz;} return acc;}, {vz:0}).vz / (i||1);
  i = 0;
  let moygr = fi.pts.reduce((acc, cur) => {if (cur.gr > 0 && Number.isFinite(cur.gr)){i++;acc.gr+=cur.gr;} return acc;}, {gr:0}).gr / (i||1);
  flstats['vz+ moy'] = `${Math.round(moyvzpos*10)/10}m/s`;
  flstats['fin. moy'] = `${Math.round(moygr*10)/10}`;
  flstats['vx max'] = `<a href="#" onclick="event.stopPropagation();graph.goto(fi.maxvxt);setViewIfNotVisible(graph.curLatLon);return false;" title="aller vers ce point">${Math.round(fi.maxvx)}km/h</a>`;
  flstats['deniv'] = [`${Math.round(fi.pts[0].alt-fi.pts[fi.pts.length-1].alt)}m`, 'dénivelé entre le décollage et l\'atterissage'];
  let totaltgain = Math.round(fi.totaltgain);
  if (totaltgain > 10000) {
    totaltgain = Math.round(fi.totaltgain/100)/10;
    totaltgain = `${totaltgain}km`;
  } else {
    totaltgain = `${totaltgain}m`;
  }
  flstats['tot. gain'] = [totaltgain, 'gain total en altitude'];
    //flstats['vx min'] = `${Math.round(fi.minvx)}km/h`;
  updateTraceInfos();
});
graph.addEventListener('onposchanged', function(e) {
  window.curpoint = e.detail;
  let mktxt = e.detail.time.toLocaleString('fr-FR', { /*timeZone: 'UTC'*/ }).substr(-8, 5);
  mktxt+='&nbsp;:&nbsp;'+e.detail.alt+'m';
  mktxt+='<BR>' + e.detail.vz+'m/s&nbsp;&nbsp;&nbsp;'+e.detail.vx+'km/h';
  let curgr = Math.round(Math.min(12, Math.max(1, e.detail.gr)));
  //mktxt+=`<BR><a href="#" onclick="calcStartCone(${e.detail.lat, e.detail.lon, curgr, e.detail.alt})">cone (finesse ${curgr})</a>`;
  if (!istouch)
    marker.bindPopup(mktxt);
  marker.setLatLng([e.detail.lat, e.detail.lon]).update();
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
  updateIcon(e.detail);
  window.cursorpt = e.detail;
  /*if (istouch && !map.getBounds().contains(marker.getLatLng())) {
    map.setView(marker.getLatLng());
  }*/
});
graph.addEventListener('onclick', function(e) {
  map.setView(new L.LatLng(e.detail.lat, e.detail.lon));
});
graph.addEventListener('ondblclick', function(e) {
  let center = marker.getLatLng();
  let zoom = map.getZoom() + 1;
  map.setView(center, zoom);
});
graph.addEventListener('onwheel', function(e) {
  let center = marker.getLatLng();
  let zoom = map.getZoom() + (e.detail>0?-1:1);
  map.setView(center, zoom);
});
graph.addEventListener('onselectionchanged', function(e) {
  let graphsel = e.detail;
  if (window.selectionline) map.removeLayer(window.selectionline);
  delete flstats.selection;
  updateTraceInfos();

  if (typeof graphsel == 'object' && Array.isArray(window.graph?.fizoom?.pts) && Array.isArray(graphsel) && graphsel.length === 2 && graphsel[1] < graph.fizoom.pts.length && graphsel[0] != graphsel[1]) {
    let stpt = graph.fizoom.pts[graphsel[0]],
      endpt = graph.fizoom.pts[graphsel[1]];
    let portion = graph.fizoom.pts.slice(graphsel[0], graphsel[1]);
    let prevpt = {lat: stpt.lat, lon: stpt.lon};
    let vxmoy = 0;
    let vzmoy = 0;
    let dist = Math.round(GraphGPX.distance(stpt.lat, stpt.lon, endpt.lat, endpt.lon)/100)/10;
    let totaldist = portion.reduce((total, currentValue, currentIndex, arr) => 
    {
      let tdist = total+GraphGPX.distance(prevpt.lat, prevpt.lon, currentValue.lat, currentValue.lon);
      vxmoy += currentValue.vx;
      vzmoy += currentValue.vz;
      prevpt = currentValue;
      return tdist;
    }, 0);
    vxmoy = Math.round(vxmoy/portion.length);
    vzmoy = Math.round(10*vzmoy/portion.length)/10;
    let deniv = stpt.alt - endpt.alt;
    let finesse = Math.round(100*totaldist / deniv)/100;
    let seconds = (endpt.time.getTime()-stpt.time.getTime())/1000;
    let elapsed = new Date(0);
    elapsed.setSeconds(seconds);
    elapsed = elapsed.toISOString().substring(11, 19);
    let he = parseInt(elapsed.substring(0,2));
    let me = parseInt(elapsed.substring(3,5));
    let se = parseInt(elapsed.substring(6,8));
    elapsed = ((he>0?he+'h':'')+(me>0?me+'\'':'')+(se>0?se+'"':'')).trim();
    totaldist /= 1000;
    deniv *= -1;
    totaldist = Math.round(totaldist*10)/10;
    let vmoy = Math.round((GraphGPX.distance(endpt.lat, endpt.lon, stpt.lat, stpt.lon)/1000)/(seconds/3600));
    if (finesse < 0) {
      finesse = '&infin;';
      deniv = '+' + deniv;
    }
    //<a title="afficher la polaire pour la sélection" href="#" onclick="event.stopPropagation();showpolaire(${JSON.stringify(portion.map(p => ({'vx': p.vx, 'vz': p.vz}))).replaceAll("\"", "'")});">
    let seltext = `<u>Finesse :</u> ${finesse} <i>(${deniv}m en ${totaldist}km à ${vxmoy}km/h, vz moy: ${vzmoy}m/s)<BR><u>Altitude de départ :</u> ${stpt.alt}m<BR><u>Altitude d'arrivée :</u> ${endpt.alt}m<BR><u>Vitesse sur parcours :</u> ${vmoy}km/h en ${elapsed}<BR><u>Distance linéaire :</u> ${dist}km</i>`;
    let selinfos = {'finesse':finesse, 'deniv':deniv, 'distance_totale':totaldist, 'distance_lineaire':dist, 'vxmoy':vxmoy, 'vzmoy':vzmoy, 'altitude_depart':stpt.alt, 'vitesse_parcours':vmoy, 'elapsed':elapsed};
    let csvinfosbtn  = ` <a href="#" onclick="return cpInfosPortion(event, ${JSON.stringify(selinfos).replaceAll("\"", "&quot;")});" title="copier les informations sur la sélection dans le presse-papier">&#x1F4CB;</a>`;
    let csvportionbtn  = ` <a href="#" onclick="return dlPortion(event, ${graphsel[0]}, ${graphsel[1]});" title="télécharger la portion de vol en CSV">&#x1F4BE;</a>`;
    flstats['selection'] = seltext + csvinfosbtn + csvportionbtn;
    updateTraceInfos();
    let popup = L.popup().setContent(seltext);
    window.selectionline = L.polyline(portion.map(pt => [pt.lat, pt.lon, pt.alt]),{
        color: '#ff00ff',
        weight: 6,
        opacity: 0.85,
        smoothFactor: 1
      }).addTo(map);
    window.selectionline.bindPopup(popup).openPopup();
  }
});
graph.addEventListener('onzoom', function(e) {
  if (e.detail.reset) {
    map.fitBounds(gpx_bounds/*, {padding: [35,35]}*/);
  } else {
    map.fitBounds(L.latLngBounds(L.latLng(e.detail.minlat, e.detail.minlon), L.latLng(e.detail.maxlat, e.detail.maxlon))/*, {padding: [35,35]}*/);
  }
});

function constructAnalysersOptions() {
  let opts_dispanalysers = document.getElementById('opts_dispanalysers');
  opts_dispanalysers.innerHTML = '';
  graph.analysers.forEach(a => {
    if (Array.isArray(a.dispModes)) {
      a.dispModes.forEach(dm => {
        opts_dispanalysers.innerHTML += `<input type="radio" id="dm${dm.id}" name="dispmodes" value="${dm.id}" onclick="window.dispmode=this.value;redrawFlight();"><label for="dm${dm.id}">${dm.name}</label><BR>\n`;
      });
    }
  });
}
function fallbackCopyTextToClipboard(text, then, err) {
  var textArea = document.createElement("textarea");
  textArea.value = text;
  
  // Avoid scrolling to bottom
  textArea.style.top = "0";
  textArea.style.left = "0";
  textArea.style.position = "fixed";
  
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();
  
  try {
    var successful = document.execCommand('copy');
    successful ? then() : err();
  } catch (e) {
    err(e);
  }
  document.body.removeChild(textArea);
}
function copyTextToClipboard(text, then, err) {
  if (!navigator.clipboard) return fallbackCopyTextToClipboard(text, then, err);
  navigator.clipboard.writeText(text).then(then||(_=>{}), err||(_=>{}));
}

function cpInfosPortion(event, infos) {
  const csvsep = '\t';
  event.stopPropagation();
  let ks = Object.keys(infos);
  let csv = ks.join(csvsep) + '\n' + ks.map(k => infos[k].toString().replaceAll('.', ',')).join(csvsep);
  copyTextToClipboard(csv, _=>alert('infos copiées en CSV dans le presse papier'), _=>alert('impossible d\'accéder au presse papier'));
}

function dlPortion(event, start, end) {
  const csvsep = ';';
  event.stopPropagation();
  let csv = `time${csvsep}alt${csvsep}ground alt${csvsep}lat${csvsep}lon${csvsep}horizontal speed${csvsep}vertical speed${csvsep}bearing\n`;
  let portion = fi.pts.slice(start, end);
  csv += portion.map(pt => {
    return pt.time.toISOString().replace('T',' ').replaceAll('-', '/').slice(0, -1)+csvsep+
      pt.alt+csvsep+
      pt.altgnd+csvsep+
      pt.lat+csvsep+
      pt.lon+csvsep+
      pt.vx+csvsep+
      pt.vz+csvsep+
      pt.bearing;
  }).join('\n');
  let element = document.createElement('a');
  element.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv));
  element.setAttribute('download', 'selection.csv');
  element.style.display = 'none';
  document.body.appendChild(element);
  element.click();
  document.body.removeChild(element);
  return false;
}

function setCursorFromMap(latlng) {
  if (typeof window.fi === 'undefined' || !Array.isArray(window.fi.pts)) return -1;//Number.MAX_SAFE_INTEGER;
  /*// slice pour éviter de modifier l'ordre du tableau originel
  let nearest = window.fi.pts.slice().sort(function(a, b) {
    let la = L.latLng(a.lat, a.lon);
    let lb = L.latLng(b.lat, b.lon);
    return la.distanceTo(latlng) - lb.distanceTo(latlng);
  })[0];*/
  let nearest = window.fi.pts.reduce(function(a, b) {
    let la = L.latLng(a.lat, a.lon);
    let lb = L.latLng(b.lat, b.lon);
    return la.distanceTo(latlng) > lb.distanceTo(latlng) ? b : a;
  });
  let c1 = map.latLngToLayerPoint(latlng);
  let c2 = map.latLngToLayerPoint(L.latLng(nearest.lat, nearest.lon));
  let dist = Math.sqrt(Math.pow(c2.x-c1.x, 2) + Math.pow(c2.y-c1.y, 2));
  if (dist>100) return dist;
  window.curpoint = nearest;
  //if (L.latLng(nearest.lat, nearest.lon).distanceTo(latlng)>5000) return;
  let mktxt = nearest.time.toLocaleString('fr-FR', { /*timeZone: 'UTC'*/ }).substr(-8, 5);
  mktxt+='&nbsp;:&nbsp;'+nearest.alt+'m ('+Math.round(nearest.alt-nearest.altgnd)+'m AGL)';
  mktxt+='<BR>' + nearest.vz+'m/s&nbsp;&nbsp;&nbsp;'+nearest.vx+'km/h';
  //let gr = Math.round(Math.min(12, Math.max(1, nearest.gr)));
  //let gr = 8;
  //mktxt+=`<BR><a href="#" onclick="calcStartCone(${nearest.lat}, ${nearest.lon}, ${gr}, ${nearest.alt})">cone (finesse ${gr})</a>`;
  if (!istouch)
    marker.bindPopup(mktxt);
  marker.setLatLng([nearest.lat, nearest.lon]).update();
  graph.setPos(nearest);
  updateIcon(nearest);
  window.cursorpt = nearest;
  return dist;
}

function updateIcon(pt) {
  pt = pt ?? window.cursorpt;
  if (typeof window.fi === 'undefined' || !Array.isArray(window.fi.pts) || typeof pt !== 'object') return;
  let dispmode = window.dispmode ?? 'alt';
  let iconText = '';
  switch (dispmode) {
    case 'vz':
      iconText = pt.vz + ' m/s';
      break;
    case 'vx':
      iconText = pt.vx + ' km/h';
      break;
    case 'GR':
      iconText = pt.gr;
      break;
    case 'altsol':
      iconText = (pt.alt - pt.altgnd) + ' m';
      break;
    case 'alt':
    default:
      if (graph.analysers.some(a => Array.isArray(a.dispModes) && a.dispModes.some(dm => dm.id == dispmode))) {
        let analyser = graph.analysers.find(a => Array.isArray(a.dispModes) && a.dispModes.some(dm2 => dm2.id == dispmode));
        iconText = analyser.getDispModeCurrentValue(pt);
      } else {
        iconText = parseInt(pt.alt) + ' m';
      }
      break;
  }
  let ratio = 0.5*(pt.alt-window.fi.minalt)/(window.fi.maxalt-window.fi.minalt);
  //ratio += 0.75; // ratio est contenu dans [0.75, 1.75]
  ratio += 1.3; // ratio est contenu dans [1.3, 1.8]
  let icon = marker.options.icon;
  icon.options.iconSize = origIconSize.map(s => s * ratio);
  icon.options.iconAnchor = origIconAnchor.map((s,i) => s/origIconSize[i] * icon.options.iconSize[i]);
  marker.setIcon(icon);
  //marker.setRotationOrigin("center");
  marker.setRotationOrigin(icon.options.iconAnchor.map(v => v+"px").join(' '));
  marker.setRotationAngle(pt.bearing+180);
  let iconelem = document.getElementById('icon-paraglider-text');
  if (iconelem) iconelem.textContent = iconText;
}

function redrawFlight() {
  window.dispmode = typeof window.dispmode === 'string'?window.dispmode:'vz';
  hotlineLayer.setStyle({
    'palette': {
      0.0: '#0000ff',
      0.4: '#00ff00',
      0.7: '#ffff00',
      1.0: '#ff0000'
    }
  });
  if (dispmode == 'alt') {
    hotlineLayer.setLatLngs(window.fi.pts.map(pt => ([pt.lat, pt.lon, pt.alt])));
    hotlineLayer.setStyle({'min':fi.minalt, 'max':fi.maxalt});
  } else if (dispmode == 'vz') {
    hotlineLayer.setLatLngs(window.fi.pts.map(pt => ([pt.lat, pt.lon, pt.vz])));
    hotlineLayer.setStyle({'min':-3/*fi.minvz*/, 'max':5/*fi.maxvz*/});
  } else if (dispmode == 'vx') {
    hotlineLayer.setLatLngs(window.fi.pts.map(pt => ([pt.lat, pt.lon, pt.vx])));
    hotlineLayer.setStyle({'min':0/*fi.minvx*/, 'max':fi.maxvx/*55*/});
  } else if (dispmode == 'GR') {
    hotlineLayer.setLatLngs(window.fi.pts.map(pt => ([pt.lat, pt.lon, pt.gr])));
    hotlineLayer.setStyle({'min':0/*fi.minvx*/, 'max':40/*55*/});
  } else if (dispmode == 'altsol') {
    let pts = window.fi.pts.map(pt => ([pt.lat, pt.lon, pt.alt - pt.altgnd]));
    let ptsr = pts.map(p => p[2]);
    let min = Number.MAX_SAFE_INTEGER;
    let max = Number.MIN_SAFE_INTEGER;
    ptsr.forEach(p => {if (p<min) min = p;if (p>max) max = p;})
    hotlineLayer.setLatLngs(pts);
    hotlineLayer.setStyle({'min':min, 'max':max});
  } else if (graph.analysers.some(a => Array.isArray(a.dispModes) && a.dispModes.some(dm => dm.id == dispmode))) {
    let analyser = graph.analysers.find(a => Array.isArray(a.dispModes) && a.dispModes.some(dm2 => dm2.id == dispmode));
    graph.activateAnalyser(analyser);
    //let dmid = analyser.dispModes.find(dm2 => dm2.name == dispmode).id;
    let dm = analyser.getDispModeData(dispmode);
    hotlineLayer.setLatLngs(dm.pts);
    if (typeof dm.min === 'number') {
      hotlineLayer.setStyle({'min':dm.min, 'max':dm.max});
    }
    if (typeof dm.palette === 'object') {
      hotlineLayer.setStyle({'palette': dm.palette});
    }
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
  updateIcon();
}
function redrawAltData(dmalt) {
  window.dispmodealt = typeof window.dispmodealt === 'string'?window.dispmodealt:'baro';
  if (dmalt === dispmodealt) return;
  else dispmodealt = dmalt ?? 'gps';
  if (dispmodealt == 'baro') {
    points = points.map(pt => {pt.alt=pt.altbaro;return pt})
  } else {
    points = points.map(pt => {pt.alt=pt.altgps;return pt})
  }
  let pointshotline = points.map(pt => [pt.lat, pt.lon, pt.alt]);
  if (window.hotlineLayer) hotlineLayer.remove(map);
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
  graph.setData(points);
  hotlineLayer.addTo(map);
}
function updateTraceInfos() {
  let divTraceInfos = document.getElementById('divTraceInfos');
  let date = "?";
  if (typeof fi == 'object' && Array.isArray(fi.pts) && fi.pts.length > 0) {
    date = fi.pts[0].time;
    date = ('0'+date.getDate()).slice(-2)+"/"+('0'+(date.getMonth()+1)).slice(-2)+"/"+date.getFullYear();
  }
  let dname = typeof window.pilotname === 'string' && window.pilotname.trim().length > 0 ? '<BR>'+window.pilotname:'';
  let pname = typeof window.paraglidername === 'string' && window.paraglidername.trim().length > 0 ?window.paraglidername:'';
  if (pname.length > 16) pname = `<span title="${pname}">${pname.substring(0,15)}&#x2026;</span>`;
  if (pname) pname = '<BR>'+pname;
  let htmlinfos = '<div id="ctinfos"><p class="gras centre souligne">'+date+dname+pname+'</p>';
  for (let prop in flstats) {
    let propval = flstats[prop];
    htmlinfos += "<p";
    if (Array.isArray(propval) && propval.length > 1) {
      htmlinfos += " title=\""+propval[1]+"\"";
      propval = propval[0];
    }
    htmlinfos += ">";
    if (flstats[prop].length == 0) {
      htmlinfos += "<HR>";
    } else {
      htmlinfos += "<span class=\"gras\">"+prop+"</span>&nbsp;:&nbsp;"+propval;
    }
    htmlinfos += "</p>";
  }
  htmlinfos += '</div><p id="iinfos">&#9432;</p>';
  divTraceInfos.innerHTML = htmlinfos;
  divTraceInfos.style.display = 'block';
  divTraceInfos.onclick = updateInfoVisibility;
  updateInfoVisibility(true);
}
async function safecalcFlightScore(igccont, calcfs) {
  if (calcfs === true) {
    try {
      return await calcFlightScore(igccont);
    } catch (e) {
      alert(e);
    }
  }
}
function updateInfoVisibility(notoggle) {
  if (notoggle instanceof Event) notoggle.stopImmediatePropagation();
  if (notoggle !== true) binfos = !binfos;
  document.getElementById('ctinfos').style.display = binfos ? 'none':'block';
  document.getElementById('iinfos').style.display = !binfos ? 'none':'block';
}
async function parseIGCAndGetElevation(igccont) {
  let igcres = await parseIGC(igccont, launchtime, urlParams.get('paraglidername'), cletimezonedb, tzoffset);
  window.points = igcres.points;
  window.startdate = igcres.startdate;
  window.pilotname= igcres.pilotname;
  window.paraglidername = igcres.paraglidername;
  window.tzoffset = igcres.tzoffset;
  window.ptinterval = 1;
  try {
    ptinterval = (points[1].time.getTime()-points[0].time.getTime())/1000;
  } catch (e) {}
  if (duration>0) {
    //points = points.slice(0, duration/ptinterval);
    points.splice(1+duration/ptinterval);
  }
  let ptalts = points.reduce((acc, cur) => {
    acc.push(cur.lat, cur.lon);
    return acc;
  }, []);
  try {
    let gndalts = await getElevations(ptalts);
    for (let i=0; i<gndalts.length; i++) {
      points[i].altgnd = gndalts[i];
    }
  } catch(e) {
    console.error(e);
  }
  return igcres;
}
async function computeIGC(igccont, calcfs) {
  if (typeof igccont !== 'string') {
    igccont = document.getElementById('igccont').value;
    document.getElementById('formcont').style.display = 'none';
  }
  let [score, _] = await Promise.all([safecalcFlightScore(igccont, calcfs), parseIGCAndGetElevation(igccont)]);
  redrawAltData();
  window.gpx_bounds = hotlineLayer.getBounds();
  map.fitBounds(gpx_bounds/*, {padding: [35,35]}*/);
  if (score)
    displayFlightScore(score);

  let btndl = document.getElementById('btnDlTrace');
  btndl.onclick = function(e) {e.stopImmediatePropagation();downloadIGC(igccont, startdate);/*window.location = "data:application/octet-stream,"+encodeURI(igccont);*/};
  btndl.style.display = 'block';
  let btnkmz = document.getElementById('btnDlKMZ');
  btnkmz.onclick = function(e) {e.stopImmediatePropagation();downloadKMZ(igccont, startdate);};
  btnkmz.style.display = 'block';
  let divDispMode = document.getElementById('divDispMode');
  divDispMode.appendChild(document.getElementById('dispmodes'))
  divDispMode.style.display = 'block';
  window.btnCalcCone = document.getElementById('btnCalcCone');
  btnCalcCone.onclick = clickCalcCone.bind(null, null);
  btnCalcCone.style.display = 'block';
}
async function clickCalcCone(startpoint=null, e) {
  if (e instanceof Event) e.stopImmediatePropagation();
  startpoint = startpoint || window.curpoint;
  if (window.conecalculating) {
    window.conecalculating = false;
    imgConeLoading.style.display='none';
    if (window.conecontroller) {
      conecontroller.abort();
    }
    clearCone();
    window.curpointcone=null;
  }
  if (startpoint) {
    if (window.curpointcone) {
      clearCone();
      btnCalcCone.title = "Calculer le cone de finesse à la position actuelle";
    } else {
      window.conecalculating=true;
      imgConeLoading.style.display='block';
      let ret = await calcStartCone(startpoint.lat, startpoint.lon, 8, startpoint.alt);
      if (ret) {
        window.conecalculating=false;
        window.curpointcone = startpoint;
        imgConeLoading.style.display='none';
        btnCalcCone.title = "Masquer le cone de finesse";
      }
    }
  }
}
function downloadIGC(igccont, startdate) {
  const a = document.createElement("a");
  a.setAttribute('href', "data:application/octet-stream,"+encodeURI(igccont));
  if (typeof startdate.toISOString === 'function') {
    a.setAttribute('download', startdate.toISOString().substring(0,10)+".igc");
  } else {
    a.setAttribute('download', "track.igc");
  }
  const textnode = document.createTextNode("télécharger");
  a.appendChild(textnode);
  a.style.display = 'none';
  document.body.appendChild(a);
  a.click();
}
function downloadKMZ(igccont, startdate) {
  let filename = "track.kmz";
  if (typeof startdate.toISOString === 'function') {
    filename = startdate.toISOString().substring(0,10)+".kmz";
  }
  igc2kmz(igccont, filename).catch(err => {
    alert(err);
    throw err;
  });
}
function getIGC(igc) {
  return new Promise((res, rej) => {
    let xhttp = new XMLHttpRequest();
    xhttp.responseType = 'text';
    xhttp.onreadystatechange = async function() {
      if (this.readyState == 4) {
        if (this.status == 200 && this.responseText) {
          window.igccontent = this.response;
          document.getElementById('formcont').style.display = 'none';
          await computeIGC(this.response, typeof finfo !== 'string');
          document.getElementById('igccont').value = this.response;
          chargement(false);
          res();
        } else {
          rej();
        }
      }
    };
    xhttp.onerror=function(e) {
      let msgerr = "Error fetching " + url;
      chargement(true, msgerr, true);
      rej(msgerr);
    };
    xhttp.open("GET", igc, true);
    xhttp.send(null);
  });
}
function displayFlightScore(flightscore) {
  window.flightscore = flightscore;
  graph.setFlightInfo(flightscore);
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
  flstats['vit.'] = `${Math.round(36000*flightscore.scoreInfo.distance/((flightscore.opt.landing-flightscore.opt.launch)/ptinterval))/10}km/h`;
  calcParcoursSpeed(); // on recalcule la vraie vitesse si on a déjà l'IGC
  updateTraceInfos();
}
function calcParcoursSpeed() {
  if (!window.points || !window.flightscore) return;
  try {
    let legs = flightscore.scoreInfo.legs;
    let distp = legs.map(l => l.d).reduce((sum, num) => sum + num);
    // on trouve les points dans la trace correspondant au plus près du départ de la première branche et de l'arrivée de la dernière pour calculer le temps sur le parcours
    let timep = (window.points.sort(pt => GraphGPX.distance(pt.lat, pt.lon, legs[legs.length-1].finish.y, legs[legs.length-1].finish.x)).reverse()[0].time.valueOf()
     -window.points.sort(pt => GraphGPX.distance(pt.lat, pt.lon, legs[0].start.y, legs[0].start.x)).reverse()[0].time.valueOf())/1000;
    let speedp = distp / (timep / 3600);
    flstats['vit.'] = `${Math.round(speedp*10)/10}km/h`;
  } catch(e) {console.error(e);}
}
function calcFlightScore(igccontent) {
  return new Promise((res, rej) => {
    if (typeof igccontent !== 'string') {
      res();
      return;
    }
    try {
      IGCScore.score(igccontent, (score) => {
        if (score && typeof score.value == 'object') {
          score = score.value;
        }
        if (score && typeof score.opt == 'object' && typeof score.opt.flight == 'object') delete score.opt.flight;
        res(score);
      });
    } catch(e) {console.error(e);rej(e);}
  });
}
function loadFlightScore(finfo) {
  return new Promise((res, rej) => {
    let xhttp = new XMLHttpRequest();
    xhttp.responseType = 'json';
    xhttp.onreadystatechange = function() {
      if (this.readyState == 4) {
        if (this.status == 200) {
          try {
            if (typeof this.response == 'object') {
              res(this.response);
            } else throw new Error('impossible de charger le score');
          } catch(e) {window.finfo=null;calcFlightScore(window.igccontent).then(res).catch(rej);}
        } else {
          window.finfo=null;
          calcFlightScore(window.igccontent).then(res).catch(rej);
        }
      }
    };
    xhttp.onerror=function(e) {
      chargement(true, "Error fetching " + url, true);
    };
    xhttp.open("GET", finfo, true);
    xhttp.send();
  });
}
function showpolaire(portion) {
  portion.sort((p1, p2) => p1.vx - p2.vx);
  var trace1 = {
    x: portion.map(p => p.vx),
    y: portion.map(p => p.vz),
    mode: 'lines+markers'
  };
  Plotly.newPlot('graphPolaire', [ trace1 ], { title:'Polaire des vitesses pour la zone sélectionnée'});
  document.getElementById('popupPolaire').style.display='block';
}
function dateToTime(dt) {
  let time = dt.toLocaleString('fr-FR', { timeZone: 'UTC' }).substr(-8, 8);
  let hours = parseInt(time.substring(0,2));
  let minutes = parseInt(time.substring(3,5));
  let seconds = parseInt(time.substring(6,8));
  time = '';
  if (hours != 0) time += hours + 'h';
  if (minutes >= 5 || (minutes > 0 && hours == 0)) time += minutes + 'm'; // si vol > 1h => pas de minutes en dessous de 5
  if (hours == 0 && seconds != 0) time += seconds + 's';
  return time;
}
function zeroPad(num, places) {
  return String(num).padStart(places, '0');
}
function chargement(load=true, message='Chargement...', add=false) {
  loading.style.display = load?'block':'none';
  loading.innerHTML = (add?loading.innerHTML+'<BR>':'')+message;
}
window.onload = function() {
  if (igc) {
    chargement();
    document.getElementById('formcont').style.display = 'none';
    Promise.all([getIGC(igc), finfo?loadFlightScore(finfo):null]).then(([_, score]) => {
      if (score)
        displayFlightScore(score);
      calcParcoursSpeed();
      updateTraceInfos();
    }).catch(console.error);
  } else {
    chargement(false);
    document.getElementById('formcont').style.display = 'block';
  }
  document.getElementById('frmsub').style.display = 'block';
};

window.onresize = function(e) {
  if (map && gpx_bounds && !usermoved)
    map.fitBounds(gpx_bounds/*, {padding: [35,35]}*/);
};
window.onhashchange = function(e) {
  let hash = location.hash.substring(12).trim();
  let timetoseek = parseInt(hash);
  if (/^\d+$/g.test(hash) && Array.isArray(points) && points[0].time.getTime() <= timetoseek && points[points.length-1].time.getTime() >= timetoseek) {
    console.log(points.find(p => p.time.getTime() == timetoseek));
  }
};

function clearCone() {
    if (window.startconept) {
      map.removeLayer(startconept);
      startconept = null;
    }
    tiles?.forEach(z => {
      if (z.marker) map.removeLayer(z.marker);
    });
    tilepaths?.forEach(z => {
      map.removeLayer(z);
    });
    tiles=[];
    tilepaths=[];
    window.curpointcone = null;
}
async function calcStartCone(lat, lon, finesse, amsl) {
  try {
    if (window.conecontroller) {
      conecontroller.abort();
    }
    window.conecontroller = new AbortController();
    let conesignal = conecontroller.signal;
    clearCone();
    window.startconept = L.marker([lat, lon], {draggable:'false'}).addTo(map);
    let now = Date.now();
    window.curfinessecalc = now;
    const response = await fetch(`elevation/getConeFinesse.php?lat=${lat}&lon=${lon}&finesse=${finesse}&amsl=${amsl}`, {signal: conesignal});
    const res = await response.json();
    if (now == window.curfinessecalc) {
      let totaltime = Date.now()-now;
      console.log(`${res.tiles.length} tiles in ${totaltime}ms (${res.time}ms calculation/${totaltime-res.time}ms network)`, res);
      squareside = res.squareside;
      dx = res.dx;
      dy = res.dy;
      ddx = dx/2;
      ddy = dy/2;
      res.tiles.forEach(drawTile);
    } else {
      console.log('outdated calc :', res);
    }
  } catch (err) {
    console.error(err);
    return false;
  }
  return true;
}
function color(val, max=300, min=0) {
  let bval = Math.max(min, Math.min(val, max));
  let moy = min + (max-min) / 2;
  let r = bval<moy?0xff:Math.round(((moy-(bval-moy))*0xff/moy));
  let g = bval>moy?0xff:Math.round((bval*0xff/moy));
  let b = 0;
  if (val > max) {
    let bmax = (max-min)*2;
    val -= max;
    val = Math.min(bmax, val);
    b = Math.round((val*0xff/bmax));
  }
  let a = tileopacity;
  let clr = `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`;
  return {color: clr, fillColor: clr, opacity: a, fillOpacity: a};
}
function drawTile(tile) {
  if (tile.alt <= 0 && (tile.x!=0 || tile.y!=0)) return;
  let latlngs = [
    [tile.pos.lat-ddy,tile.pos.lng-ddx],
    [tile.pos.lat+ddy,tile.pos.lng-ddx],
    [tile.pos.lat+ddy,tile.pos.lng+ddx],
    [tile.pos.lat-ddy,tile.pos.lng+ddx]
  ];
  //https://leafletjs.com/reference.html#path
  let alt = tile.alt<1?Math.round(tile.alt*10)/10:Math.round(tile.alt);
  let tttext = `${alt}m AGL`;
  let polygon = L.polygon(latlngs, {opacity:tileopacity, fillOpacity:tileopacity}).bindTooltip(tttext).addTo(map);
  clroptions = color(tile.alt);
  tile.marker=polygon;
  polygon.setStyle({weight:1, lineJoin:'bevel', ...clroptions});
  //polygon.on('mouseover',() => drawPath(tile));
  polygon.on('mouseover', function () {
      drawPath(tile);
    });
  //polygon.on('mouseout', clearPaths);
    tiles.push(tile);
  }
function clearPaths(origtile) {
  // bug de tooltips qui restent ouverts si on déplace la carte en même temps
  tiles.forEach(tile => {
    if (tile == origtile) return;
    tile.marker.closeTooltip();
  });
    tilepaths?.forEach(z => {
      map.removeLayer(z);
    });
  tilepaths=[];
}
function drawPath(tile) {
  clearPaths(tile);
  let pathcoord = [];
  let origtile = tile;
  do {
    pathcoord.push([tile.pos.lat, tile.pos.lng]);
    //tilepaths.push(L.marker([tile.pos.lat, tile.pos.lng], {draggable:'true'}).addTo(map));
    tile = tile.parent?tileAt(tile.parent.x, tile.parent.y):null;
  } while (tile);
  let d = pathcoord.reduce((acc, cur, i) => {return (i>0)?acc+GraphGPX.distance(pathcoord[i-1][0], pathcoord[i-1][1], cur[0], cur[1]):0;}, 0);
  let tttext = `${formatAlt(origtile.alt)}m AGL (${formatAlt(origtile.alt+origtile.altgnd)}m), ${formatDistance(d)}`;
  origtile.marker.setTooltipContent(tttext);
  tilepaths.push(new L.Polyline(pathcoord, {
    color: 'black',
    weight: 3,
    opacity: 1,
    smoothFactor: 1
  })/*.bindTooltip(tttext)*/.addTo(map));
  tilepaths.push(new L.circle([origtile.pos.lat, origtile.pos.lng], {color: 'black', fillOpacity: 1, radius: 0.1*squareside}).bindTooltip(tttext).addTo(map));
}
function tileAt(x, y) {
  return tiles.find(t => t.x == x && t.y == y);
}
function setViewIfNotVisible(latlon) {
  if ((latlon.lat==0 && latlon.lng==0) || map.getBounds().contains(latlon)) return;
  map.panTo(latlon);
}
function formatAlt(alt) {
  return alt<1?Math.round(alt*10)/10:Math.round(alt);
}
function formatDistance(d) {
  let unit='m';
  if (d>=1000) {
    d /= 1000;
    unit='km';
  }
  if (d<100) {
    d = Math.round(d*10)/10;
  } else {
    d = Math.round(d);
  }
  return d+unit;
}
