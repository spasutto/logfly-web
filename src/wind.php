<?php
const BALISES = "balises_ffvl.json";
const MAX_DIST = 35; // km
$force_reload = false;// isset($_REQUEST['force_reload']);
//if ($_REQUEST['action'] == 'lastwind') {
  if (isset($_REQUEST['lat']) && isset($_REQUEST['lon']) && isset($_REQUEST['ts'])) {
    require("tracklogmanager.php");
    $lat = @floatval($_REQUEST['lat']);
    $lon = @floatval($_REQUEST['lon']);
    $ts = @intval($_REQUEST['ts']);
    if ($lat && $lon && $ts > 1e9)
      getListeVentBalisesAUneHeure($lat, $lon, $ts);
  } else if (isset($_GET['id']) && preg_match('/^\d+$/', $_GET['id'])) {
    $id = intval($_GET['id']);
    if (isset($_GET['wind'])) {
      file_put_contents("Tracklogs/w$id.json", file_get_contents('php://input'));
      exit(0);
    }
  }
//}
exit(0);
return;

function getListeVentBalisesAUneHeure($lat, $lon, $timestamp) {
  $balises = getListeBalises();
  $balisesproches = [];
  $N = ceil((abs(time() - $timestamp)) / 3600); // temps depuis lequel récupérer l'historique
  for ($i=0; $i<count($balises); $i++) {
    $d = distance($lat, $lon, $balises[$i]->latitude, $balises[$i]->longitude);
    if($d < MAX_DIST*1000) { // - de 20km
      $data = getVentBalise($balises[$i]->idBalise, $timestamp, $N);
      if (!$data) continue;
      $balisesproches[] = ["nom" => $balises[$i]->nom, "altitude" => $balises[$i]->altitude, "distance" => round($d), "lat" => $balises[$i]->latitude, "lon" => $balises[$i]->longitude, "vent" => $data];
    }
  }
  usort($balisesproches, function($a, $b) {
    return $a['distance']-$b['distance'];
  });
  for ($i=0; $i<count($balisesproches); $i++) {
    $balisesproches[$i]['distance'] = round($balisesproches[$i]['distance']/100)/10;
  }
  header('Content-Type: application/json');
  echo json_encode($balisesproches);
}
function getVentBalise($idbalise, $timestamp, $N) {
  $data = @json_decode(@file_get_contents("https://data.ffvl.fr/api?base=balises&r=histo&hours=".$N."&idbalise=".$idbalise."&mode=json&key=".CLEFFVL));
  if(!is_array($data)) {
    return null;
  }
  $mininterval = PHP_INT_MAX;
  $releve = null;
  for ($i=0; $i<count($data); $i++) {
    //"date": "2025-10-19 18:21:31",
    $date = DateTime::createFromFormat('Y-m-d H:i:s', $data[$i]->date);
    $interval = abs($date->getTimestamp() - $timestamp);
    if ($interval < $mininterval) {
      $mininterval = $interval;
      $releve = $data[$i];
      $releve->timestamp = $date->getTimestamp();
      continue;
    }
    break;
  }
  if ($mininterval > 3600 ) { // relevé > 1h --> périmé
    return null;
  }
  return ["timestamp" => $releve->timestamp,"min" => @intval($releve->vitesseVentMin), "moy" => @intval($releve->vitesseVentMoy), "max" => @intval($releve->vitesseVentMax), "dir" => @intval($releve->directVentInst), "dirm" => @intval($releve->directVentMoy)];
}
function getListeBalises() {
  global $force_reload;
  $data = null;
  if ($force_reload || !file_exists(BALISES) || time()-filemtime(BALISES) > 259200) { // péremption 3 jours
    $data = majListeBalises();
  } else {
    $data = json_decode(file_get_contents(BALISES));
  }
  return $data;
}
function majListeBalises() {
  $data = @json_decode(@file_get_contents("https://data.ffvl.fr/api/?base=balises&r=list&mode=json&key=".CLEFFVL));
  //todo : vérifier contenu $data
  if(!is_array($data)) {
    return json_decode(file_get_contents(BALISES));
  }
  $data = filtrerBalises($data);
  //echo "maj liste balises<BR>";
  file_put_contents(BALISES, json_encode($data));
  return $data;
}
function filtrerBalises($balises) {
  $propkeep = ["idBalise", "nom", "altitude", "latitude", "longitude", "decalageHoraire"];
  for ($i=0; $i<count($balises); $i++) {
    foreach (get_object_vars($balises[$i]) as $key => $value) {
      if (!in_array($key, $propkeep)) {
        unset($balises[$i]->{$key});
      } else if ($key == 'latitude' || $key == 'longitude') {
        $balises[$i]->{$key} = floatval($value);
      }
    }
  }
  return $balises;
}
?>
