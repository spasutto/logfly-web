<?php
/*if (file_exists('sites_ffvl.json')) {
  header("Location: sites_ffvl.json");
  exit(0);
}*/
header('Content-Type: application/json; charset=utf-8');
require("tracklogmanager.php");
echo TrackLogManager::fetchSitesFFVL();
?>