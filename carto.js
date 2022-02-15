function loadCarto() {
  var map = L.map('map').setView([45.182471 , 5.725589], 13);
  var mapbox = L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token=pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw', {
    maxZoom: 18,
    attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, ' +
      'Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
    id: 'mapbox/streets-v11',
    tileSize: 512,
    zoomOffset: -1
  }).addTo(map);
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
      "https://wxs.ign.fr/daot2przp2b28i1af5yeia1a/geoportail/wmts?" +
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
  var opentopomap = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
    maxZoom: 17,
    attribution: 'Map data: &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, <a href="http://viewfinderpanoramas.org">SRTM</a> | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)'
  }).addTo(map);

  var baseMaps = {
    "MapBox": mapbox,
    "Photos Satellite": ignphoto,
    "Carte IGN": carteign,
    "Carte Topo": opentopomap
  };
  L.control.layers(baseMaps).addTo(map);
  return map;
}