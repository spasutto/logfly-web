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
  L.control.scale().addTo(map);
  map.on('dragstart', function (e) { window.usermoved = true; });
  map.on('zoomend', function (e) { if (firstzoom) { firstzoom = false; return; } window.usermoved = true; });

  if (typeof L.Control.Fullscreen == 'function') {
    let options = {'element' : rootelem};
    map.on('fullscreenchange', function () {
        if (map.isFullscreen()) {
            map.scrollWheelZoom.enable();
            map.dragging.enable();
        } else {
            map.scrollWheelZoom.disable();
            isTouchDevice()?map.dragging.disable():map.dragging.enable();
        }
    });
    map.addControl(new L.Control.Fullscreen(options));
  }

  var mapbox = L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token=pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw', {
    maxZoom: 18,
    attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, ' +
      'Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
    id: 'mapbox/streets-v11',
    tileSize: 512,
    zoomOffset: -1
  });
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
    );
    carteign = L.tileLayer(
        "https://wxs.ign.fr/"+clegeoportail+"/geoportail/wmts?" +
        "&REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0" +
        "&STYLE=normal" +
        "&TILEMATRIXSET=PM" +
        "&FORMAT=image/jpeg"+
        //"&LAYER=GEOGRAPHICALGRIDSYSTEMS.MAPS"+
        "&LAYER=GEOGRAPHICALGRIDSYSTEMS.MAPS.SCAN25TOUR"+
      "&TILEMATRIX={z}" +
        "&TILEROW={y}" +
        "&TILECOL={x}",
      {
      minZoom : 0,
      maxZoom : 16,//18 pour MAPS
        attribution : '<a target="_blank" href="https://www.geoportail.gouv.fr/">IGN-F/Geoportail</a>',
      tileSize : 256 // les tuiles du Géooportail font 256x256px
      }
    );
  }
  var opentopomap = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
    maxZoom: 17,
    attribution: 'Map data: &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, <a href="http://viewfinderpanoramas.org">SRTM</a> | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)'
  });

  var baseMaps = {};
  if (useign) {
    baseMaps["Photos Satellite"] = ignphoto;
    baseMaps["Carte IGN"] = carteign;
  }
  //baseMaps["MapBox"] = mapbox;
  baseMaps["Carte Topo"] = opentopomap;
  for (let basemap in baseMaps) {
    baseMaps[basemap].addTo(map);
  }
  L.control.layers(baseMaps, null, {position: 'topleft'}).addTo(map);

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

  L.Control.DlKMZ = L.Control.extend({
    onAdd: function(map) {
      var img = L.DomUtil.create('img');
      img.src = 'data:image/png;base64, iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAMAAABEpIrGAAADAFBMVEX5+fna8P/I4v7N6f/49vXw8/bC4P/y9fn4+PaYwv+eyP+Uwf6hyf+w0v652f+q0P9alfZSkPVxpvlQjvRYlvpMi/RNjvlPkPxGiPSRvv4xaN06c+RIhvGLsfODq+owZtxKh+tEhvMtaeErY9w+gvJDge0zY8s+euImYNsrZuAlXtopXMooXtEmWspyk9sAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACnehSyAAAAAXRSTlMAQObYZgAAAAlwSFlzAAALEgAACxIB0t1+/AAAAZFJREFUeNqtk9tywiAURRUMNaE3Sg8StE0V2xKt/v/ndR8So+nloTPdYfKyFhtCksnkHzMVUggpxawofsbMEaXU1Wz+jcuyqsoSUAqp1AwZc6Y8dG6QopgXo5LrEhDRuLANJeS4g+ejALTUVdXtBMKw1xsIPJiyIztjPgjMuAPRfLvNHRD6Re6qe8N54I6s9YuchNJmbqztudZCsVH0x/FohnQCziN34ESYC7LWWOPcwhNVF4ZUUk15hQUMh+kEh3qBF1EYEoKzRCZfjsj7XtA4LgkHgl0QkTVkHQTjmNYQsFMhtOYG8BCWFgVYhlZAZa1PgfBEy8ChBTD5FfHDdrSun/kxTOjCVUTOn41a53NwvdBgPle8EKbm+fU6CxasCZtNiBCM8cg2C6V/7c46NDHEEGITPZ7TORjOr6Ft+7f5Fjax4YTIa3SB4N9P7ztGlKAFyRwd6zXG+ZPaxCHddM52cpEYL5XM0/izHnBKbWrbXfrCsVMw0NTGBJr2P/xbH6A7dlI6/PZ7HveHw/74t1/6E+SWLvEQZOGyAAAAAElFTkSuQmCC';
      img.style.width = '32px';
      var btn = L.DomUtil.create('button');
      btn.id = "btnDlKMZ";
      btn.title = "Télécharger la trace au format Google Earth";
      btn.style.display = 'none';
      btn.appendChild(img);

      return btn;
    },
    onRemove: function(map) {}
  });
  new L.Control.DlKMZ({ position: 'topright' }).addTo(map);

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
      divinf.title = 'Informations sur la trace (cliquer pour réduire)';

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