<?php
  try
  if (isset($_REQUEST['sites']))
    exit(0);
  <title>Carte des sites de vol</title>
  <meta charset="utf-8" />
  <link rel="shortcut icon" type="image/x-icon" href="docs/images/favicon.ico" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A==" crossorigin=""/>

<style>
</head>

<div id="map"></div>
<span id="infobox"></span>
  /*L.marker([51.5, -0.09]).addTo(map)
  L.circle([51.508, -0.11], 500, {
  L.polygon([

  function onMapClick(e) {
  map.on('click', onMapClick);*/

  var map = loadCarto("<?php if (defined('CLEGEOPORTAIL')) echo CLEGEOPORTAIL;?>");
  //window.onload= function() {
