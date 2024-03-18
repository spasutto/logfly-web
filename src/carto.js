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

  L.Control.CalcCone = L.Control.extend({
    onAdd: function(map) {
      let div = L.DomUtil.create('div');
      let img = L.DomUtil.create('img');
      img.src = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAMCAgMCAgMDAwMEAwMEBQgFBQQEBQoHBwYIDAoMDAsKCwsNDhIQDQ4RDgsLEBYQERMUFRUVDA8XGBYUGBIUFRT/2wBDAQMEBAUEBQkFBQkUDQsNFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBT/wAARCAAhACADASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD7H8e2r+DI7KTS0jurzT44LZGuHwyKzsCy45BbgMfbNY2pS+NvF3hb7FqeqQwrK7KROoSchW+8GHrxzx92s/XdPuvEfiG60C5vjcafo75tIRa7XuFADbjzzySRnJ796g1fxCv2hX02acRBcSxkKNrAY3Ip5Puvftz1/G85zedHF/VsBJwjb3tLq/Sz/Xvsb5rmlDhvAU604QnWm1JJt35dLeTs97ejOma81jwsSmn65eXqWzC5fTZJFbzE/iCNjIOeevf3qzq3ieHxbDYWei3lsGuk82fzEMs9v/eXauATkEHt1rgW1SMTwzwzSI8i7450b5iSVHX056e1bWmWllqWpaVdwwQtf3N5J51zHmIyRJHyp29snp3rmwGb1ZpYXFN1k3o29Vp+K6/I+eyPiWjn9R4StS9nV95pxS5Wkm7SWlmujV77G9F4S8PS6it1bzX/AJs6+dPM0jolwCOXGOB2IwegrzW7tLoXOoR38EomgAklMh2GeDI2SFep4YA+teo3+u6b4X0CC6hR7y3sZxHHbxqYy+F527hyAD2449q8ivbDxR4sOq6lBa21qouTNM4tz9qKCTOd7HOzaOFwOB7V35ngvrlL2lkpNtrTfv8ALv5662PezPL6ecYZYLFS00UX/K+jXlumtrN9bDLkzz6haeQkkFuF2SELuiRVJYvGvZmLc/hXW6ba3mv6xZaZeQSWOnSGeS0ms3CSOAoBBkByvGTkc5pVfw5Z3VtNKuoRwyA/I1sQrOMbUHq3TnpXW+HYr7+0W1aeK1j8pWs4LKGUkwZwW7YJzjJyPSvmsswlVYlVJQaUb3clqtLJR21+9WPC4dynMcDUq1sxhBNxjCLjbm91W0celn7zershPiJ/yF9P/wB25/8ASRqxNG/4/vih/wBc/wD2i1FFfo2K/gUf+3vyPsavxL+uw7XP+Ru8F/7sv/oCVteFP+Rd8Qf9fQ/9EpRRXh4P+KvX/wCRIf8AvEvl+p//2Q==';
      img.style.width = '32px';
      let imgloading = L.DomUtil.create('img');
      imgloading.id='imgConeLoading';
      imgloading.src = 'data:image/gif;base64,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';
      imgloading.style.width = '32px';
      imgloading.style.position = 'absolute';
      imgloading.style.top = '0';
      imgloading.style.left = '5px';
      imgloading.style.display = 'none';
      div.appendChild(img);
      div.appendChild(imgloading);
      let btn = L.DomUtil.create('button');
      btn.id = "btnCalcCone";
      btn.title = "Calculer le cone de finesse à la position actuelle";
      btn.appendChild(div);

      return btn;
    },
    onRemove: function(map) {}
  });
  new L.Control.CalcCone({ position: 'topleft' }).addTo(map);

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