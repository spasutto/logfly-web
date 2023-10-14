<?php
/*$files = glob('./Tracklogs/*.jpg');
header('Content-Type: text/plain');
//header('Content: image/jpeg');
print_r($files);
*/

require("config.php");
include("logfilereader.php");
$vols = [];
foreach ((new LogflyReader())->getRecords()->vols as $vol) {
if ($vol->igc)
  $vols[] = $vol->id;
}
$vols = "[" . implode (",", $vols) . "]";
$tracefilesprefix = urlencode((defined('FOLDER_TL')?FOLDER_TL:"")."/");
$elevationservice = (defined('ELEVATIONSERVICE')?urlencode(ELEVATIONSERVICE):"");
$clegeoportail = (defined('CLEGEOPORTAIL')?urlencode(CLEGEOPORTAIL):"");
$cletimezonedb = (defined('CLETIMEZONEDB')?urlencode(CLETIMEZONEDB):"");
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="initial-scale=1.0, maximum-scale=2.0, user-scalable=yes">
    <title>Traces du carnet de vol</title>
    <style>
      html, body {
        font-family: sans-serif;
        margin: 2px;
        padding: 2px;
        user-select: none;
        background-color: #e9e9e9;
      }
      .image {
        display: inline-block;
        max-width:320px;
        max-height:240px;
      }
    </style>

    <script>
    var vols = <?php echo $vols;?>;
    var tracefilesprefix = '<?php echo $tracefilesprefix;?>';
    var elevationservice = '<?php echo $elevationservice;?>';
    var clegeoportail = '<?php echo $clegeoportail;?>';
    var cletimezonedb = '<?php echo $cletimezonedb;?>';
function $(id) {
  id = id.trim();
  if (id[0] == '#') return document.getElementById(id.substring(1));
  return [...document.querySelectorAll(id)];
}
function createImages() {
  let cont = $('#zoneImages');
  vols.forEach(v => {
    let tracefileprefix = tracefilesprefix+v;
    const a = document.createElement("a");
    const url = "trace.html?igc="+tracefileprefix+".igc&finfo="+tracefileprefix+".json&elevationservice="+elevationservice+"&clegeoportail="+clegeoportail+"&cletimezonedb="+cletimezonedb;
    a.setAttribute('href', url);
    a.setAttribute('target', '_blank');
    const img = document.createElement("img");
    // newContent = document.createTextNode("Hi there and greetings!");
    //img.appendChild(newContent);
    img.setAttribute('class', 'image');
    img.setAttribute('src', 'image.php?id='+v);
    a.appendChild(img);
    //document.body.insertBefore(img, cont);
    cont.appendChild(a);
  });
}
window.onload = function() {
  createImages();
}
    </script>
  </head>
<body>
  <h1>Traces du carnet</h1>
  <div id="zoneImages"></div>
</body>
</html>