<?php

require_once('ElevationService.php');

$request = json_decode(file_get_contents("php://input"));

$elevations = array();
$first = true;
$elev = 0;

header('Content-type: application/json');
echo '[';
if (is_array($request->locations)) {
  if (count($request->locations) % 2 != 0) {
    array_pop($request->locations);
  }
  if (count($request->locations)>1) {
    $es = new ElevationService();
    for($i=0; $i<count($request->locations); $i+=2) {
      $elev = $es->getElevation($request->locations[$i], $request->locations[$i+1]);
      if ($elev > 0) {
        if (!$first) echo ',';
        echo $elev;
        $first = false;
      }
    }
  }
}
echo ']';
?>