function loadCarto(clegeoportail) {
  let useign = typeof clegeoportail == "string" && clegeoportail.trim().length > 0;
  var map = L.map('map').setView([45.182471 , 5.725589], 13);
  var mapbox = L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token=pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw', {
    maxZoom: 18,
    attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, ' +
      'Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
    id: 'mapbox/streets-v11',
    tileSize: 512,
    zoomOffset: -1
  }).addTo(map);
  if (useign) {
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

  L.control.dligc = function(opts) {
    return new L.Control.DlIGC(opts);
  }

  L.control.dligc({ position: 'topright' }).addTo(map);
  return map;
}