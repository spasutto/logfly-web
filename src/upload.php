<?php
if (isset($_POST['flightscore']) && isset($_GET['id']) && preg_match('/^\d+$/', $_GET['id'])) {
  $id = intval($_GET['id']);
  //echo $_POST['flightscore'];
  require("tracklogmanager.php");
  $mgr = new TrackLogManager();
  echo $mgr->putFlightScore($id, $_POST['flightscore']) ? "OK":"KO";
  exit(0);
}
?><!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Upload de vol</title>
  <script src="igc-xc-score.js"></script>
</head>
<body>
<?php
$url = "http".(!empty($_SERVER['HTTPS'])?"s":"")."://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
if (!isset($_FILES['userfile']['tmp_name'])) {
?>
<script>
  function loadVols() {
    var xhttp = new XMLHttpRequest();
    xhttp.responseType = 'json';
    xhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        if (!this.response) return;
        var list = document.getElementsByName('id')[0];
        clearList(list);
        for (let i=0; i<this.response.length; i++)
          addOption(list, this.response[i].id+" ("+this.response[i].date+") " + this.response[i].site, this.response[i].id);
      }
    };
    xhttp.open("GET", "edit.php?listevols", true);
    xhttp.send();
  }
  function addOption(list, nom, value, selected)
  {
    var option = document.createElement("option");
    option.text = nom;
    option.value = value;
    if (selected)
      option.selected = true;
    list.add(option);
  }
  function clearList(list)
  {
    let length = list.options.length;
    for (let i=length; i>=0; i--)
      list.options[i] = null;
    addOption(list, 'Nouveau...', -1, false);
  }
  window.onload = loadVols;
</script>
<!-- Le type d'encodage des données, enctype, DOIT être spécifié comme ce qui suit -->
<form enctype="multipart/form-data" action="<?php echo $url;?>" method="post">
    vol à editer/créer :<select name="id">
  <option value="-1">Nouveau...</option>
</select><BR>
  <!-- MAX_FILE_SIZE doit précéder le champ input de type file -->
  <input type="hidden" name="MAX_FILE_SIZE" value="10000000" />
  <!-- Le nom de l'élément input détermine le nom dans le tableau $_FILES -->
  Envoyez ce fichier : <input name="userfile" type="file" /><BR><BR>
  <center><input type="submit" value="Envoyer le fichier" /></center>
</form>

<?php
} else {

  if (!is_file($_FILES['userfile']['tmp_name']))
  {
    echo "Unable to get input file";
  } else {
    //echo "file : \"".$_FILES['userfile']['tmp_name']."\"<BR>";

    $newvol = true;
    $id = null;
    if (isset($_REQUEST["id"])) {
        $id = intval($_REQUEST["id"]);
    }
    if ($id <= 0) {
        $id = null;
    } else {
        $newvol = false;
    }
    require("tracklogmanager.php");
    $mgr = new TrackLogManager();
    $ext = $_FILES['userfile']['name'];
    $ext = substr(trim(strtolower($ext)), -3);
    $id = $mgr->uploadIGC($_FILES['userfile']['tmp_name'], $ext, $id, $igcfname);
    if ($id) {
?>
<script>
function finish() {
  if (window.opener !== window && !window.menubar.visible) {
    window.opener.location.reload();
    // TODO ouvrir popup d'edit
    setTimeout(function(){
      if (typeof window.opener.editvol == 'function') {
        window.opener.editvol(id);
      }
    }, 500);
  } else {
    window.location = 'edit.php?id=' + id;
  }
}
function postFlightScore(id, score) {
  var xhttp = new XMLHttpRequest();
  xhttp.responseType = 'text';
  xhttp.onreadystatechange = function() {
    if (this.readyState == 4 && this.status == 200) {
      finish();
    }
  };
  data = "flightscore="+escape(JSON.stringify(score));
  xhttp.open("POST", "<?php echo strtok($_SERVER['REQUEST_URI'], '?');?>?id="+id, true);
  xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
  xhttp.send(data);
}
window.onload = function(){
  id = <?php echo $id;?>;
  igccontent = `<?php echo file_get_contents($igcfname); ?>`;
  try {
    IGCScore.score(igccontent, (score) => {
      if (score && typeof score.value == 'object') {
        score = score.value;
      }
      if (score && typeof score.opt == 'object' && typeof score.opt.flight == 'object') delete score.opt.flight;
      postFlightScore(id, score);
    });
  } catch(e) {finish();}
  document.body.innerHTML = 'Vol no ' + id + ' <?php echo $newvol?"ajouté":"mis à jour";?><BR>Ne pas fermer cette fenêtre, scoring en cours...';
};
</script>
<?php
    }
  }
}
?>
</body>
</html>