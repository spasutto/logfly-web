var startIcon = null, finishIcon = null, turnpointIcon = null;

function loadCarto(clegeoportail, disablescrollzoom, rootelem) {
  let useign = typeof clegeoportail == "string" && clegeoportail.trim().length > 0;
  window.usermoved = false;
  let firstzoom = true;
  let options = {};
  if (disablescrollzoom) {
    options.scrollWheelZoom = false;
    options.dragging = !isTouchDevice();
    options.touchzoom = true;
  }
  var map = L.map('map', options).setView([45.182471, 5.725589], 13);
  map.on('dragstart', function (e) { window.usermoved = true; });
  map.on('zoomend', function (e) { if (firstzoom) { firstzoom = false; return; } window.usermoved = true; });

  var mapbox = L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token=pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw', {
    maxZoom: 18,
    attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, ' +
      'Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
    id: 'mapbox/streets-v11',
    tileSize: 512,
    zoomOffset: -1
  }).addTo(map);
  var ignphoto, carteign;
  if (useign) {
    ignphoto = L.tileLayer(
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
    carteign = L.tileLayer(
        "https://wxs.ign.fr/"+clegeoportail+"/geoportail/wmts?" +
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
  }
  var opentopomap = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
    maxZoom: 17,
    attribution: 'Map data: &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, <a href="http://viewfinderpanoramas.org">SRTM</a> | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)'
  }).addTo(map);

  var baseMaps = {};
  if (useign) {
    baseMaps["Photos Satellite"] = ignphoto;
    baseMaps["Carte IGN"] = carteign;
  }
  baseMaps["MapBox"] = mapbox;
  baseMaps["Carte Topo"] = opentopomap;
  L.control.layers(baseMaps).addTo(map);

  if (typeof L.Control.Fullscreen == 'function') {
    let options = {'element' : rootelem};
    map.addControl(new L.Control.Fullscreen(options));
  }

  L.Control.DlIGC = L.Control.extend({
    onAdd: function(map) {
      var img = L.DomUtil.create('img');
      img.src = 'download.svg';
      img.style.width = '32px';
      var btn = L.DomUtil.create('button');
      btn.id = "btnDlTrace";
      btn.title = "Télécharger la trace";
      btn.style.display = 'none';
      btn.appendChild(img);

      return btn;
    },
    onRemove: function(map) {}
  });
  new L.Control.DlIGC({ position: 'topright' }).addTo(map);

  L.Control.TraceInfos = L.Control.extend({
    onAdd: function(map) {
      var divinf = L.DomUtil.create('div');
      divinf.id = "divTraceInfos";

      divinf.style.border = 'solid 1px #15af22';
      divinf.style.borderRadius = '5px';
      divinf.style.backgroundColor = '#99ff00af';
      divinf.style.padding = '2px';
      divinf.style.display = 'none';
      divinf.style.cursor = 'pointer';
      divinf.title = 'Informations sur la trace';

      return divinf;
    },
    onRemove: function(map) {}
  });
  new L.Control.TraceInfos({ position: 'topright' }).addTo(map);

  L.Control.DispMode = L.Control.extend({
    onAdd: function(map) {
      var divinf = L.DomUtil.create('div');
      divinf.id = "divDispMode";

      divinf.style.border = 'solid 1px black';
      divinf.style.borderRadius = '5px';
      divinf.style.backgroundColor = '#ee71fdb3';
      divinf.style.padding = '2px';
      divinf.style.display = 'none';
      divinf.title = 'Mode de dessin de la trace';

      return divinf;
    },
    onRemove: function(map) {}
  });
  new L.Control.DispMode({ position: 'topright' }).addTo(map);

  var LeafIcon = L.Icon.extend({
    options: {
      iconAnchor:   [12, 40], // point of the icon which will correspond to marker's location
      popupAnchor:  [1,-33] // point from which the popup should open relative to the iconAnchor
    }
  });
  startIcon = new LeafIcon({ iconUrl: 'marker-start.png' });
  finishIcon = new LeafIcon({ iconUrl: 'marker-finish.png' });
  turnpointIcon = new LeafIcon({ iconUrl: 'marker-tp.png', iconAnchor: [4, 38], popupAnchor:  [10, -30] });

  return map;
}
function isTouchDevice() {
  return (('ontouchstart' in window) ||
     (navigator.maxTouchPoints > 0) ||
     (navigator.msMaxTouchPoints > 0));
}