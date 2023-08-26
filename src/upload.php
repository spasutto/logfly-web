<?php
if (isset($_POST['flightscore']) && isset($_GET['id']) && preg_match('/^\d+$/', $_GET['id'])) {
  $id = intval($_GET['id']);
  //echo $_POST['flightscore'];
  require("tracklogmanager.php");
  $mgr = new TrackLogManager();
  echo $mgr->putFlightScore($id, $_POST['flightscore']) ? "OK":"KO";
  exit(0);
}
?>
<?php
$url = "http".(!empty($_SERVER['HTTPS'])?"s":"")."://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];

if (isset($_FILES['userfile']['tmp_name'])) {
  header('Content-Type: application/json; charset=utf-8');
  if ((is_string($_FILES['userfile']['tmp_name']) && !is_file($_FILES['userfile']['tmp_name'])) || (is_array($_FILES['userfile']['tmp_name']) && count(array_filter($_FILES['userfile']['tmp_name'], function($e) {return is_file($e);}))<=0))
  {
?>
{
  "result": "Unable to get input file"
}
<?php
  } else {
    /*header('Content-Type: text/plain; charset=UTF-8');
    print_r($_FILES);
    echo "\n";
    print_r($_REQUEST);
return;*/
    $concat = $_REQUEST["concat"] == 'true';
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
    $fpt = false;
    $igcs = $_FILES['userfile']['tmp_name'];
    if (!is_array($igcs)) $igcs = array($igcs);
    $tracks = $mgr->uploadIGCs($igcs, $id, $concat);
    if (!$tracks) {
?>
{
  "result": "no files"
}
<?php
    } else {
      $err = 0;
      foreach ($tracks as $track) {
        if (isset($track->error)) {
          $err++;
        }
      }
      if ($err >= count($tracks)) $err = "ERROR";
      else if ($err > 0) $err = "WARNING";
      else $err = "OK";
      echo '{"result": "'.$err.'", "tracks":[';
      $first = true;
      foreach ($tracks as $track) {
        //print_r($track);
        if (!$first) echo ",";
        echo '{';
        if (isset($track->error)) {
          echo '"error": '.json_encode($track->error).',';
        }
        echo '"indice": '.$track->indice;
        if (isset($track->id)) {
          echo ',"id": '.$track->id.',';
          echo '"newvol": '.$track->newvol.',';
          echo '"fptlat": '.$track->fpt->latitude.',';
          echo '"fptlon": '.$track->fpt->longitude.',';
          echo '"fptalt": '.$track->fpt->altitude;
        }
//  "igccontent": if (!is_file($track->igcfname)) echo "\"\""; else echo json_encode(file_get_contents($track->igcfname)),
        echo '}';
        $first = false;
      }
      echo "]}";
    }
  }
  exit(0);
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Upload de vol</title>
  <script src="lib/igc-xc-score.js"></script>
  <script src="score.js"></script>
</head>
<body>
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
    igcs.addEventListener('change', handleFileSelect, false);
  }
  function handleFileSelect(evt) {
    let btnsubmit = document.getElementById('submit');
    let files = evt.target.files;
    let totfiles = files.length;
    let plur = totfiles > 1 ? 's':'';
    btnsubmit.value = `Envoyer le${plur} fichier${plur}`;
    btnsubmit.disabled = totfiles <= 0;
    window.filestoupload = [];
    [...files].forEach(file => {
      const reader = new FileReader();
      reader.onload = (e) => {
        filestoupload.push(e.target.result);
        totfiles--;
        if (totfiles <= 0) btnsubmit.disabled = evt.target.files.length > window.filestoupload.length;
      };
      reader.readAsText(file);
    });
  }
  function onSubmit() {
    try {
      let btnsubmit = document.getElementById('submit');
      btnsubmit.disabled = true;
      const formData = new FormData();
      let id = document.getElementsByName("id")[0].value;
      concat.value = false;
      if (igcs.files.length > 1) {
        concat.value = !!confirm('Concaténer les fichiers?');
      }
      formData.append("id", id);
      formData.append("concat", concat.value);
      for (let i=0; i<igcs.files.length; i++) {
        //formData.append("userfile[]", igcs.files[i]);
        const blob = new Blob([window.filestoupload[i]], { type: "text/plain" });
        formData.append("userfile[]", blob);
      }
      const xhr = new XMLHttpRequest();
      xhr.open("POST", "<?php echo $url;?>");
      xhr.onreadystatechange = () => {
        if (xhr.readyState === XMLHttpRequest.DONE) {
          if (xhr.status >= 200 && xhr.status < 300) {
            igcs.value='';
            let result = xhr.responseText;
            try {
              window.ret = JSON.parse(result);
              if (!Array.isArray(ret.tracks)) {
                throw new Error();
              } else if (typeof ret.result === 'string' && ret.result !== 'OK') {
                alert(ret.tracks.filter(t => typeof t.error === 'string').map(t => t.error).join('\n'));
              }
              ret.tracks = ret.tracks.filter(t => typeof t.id === 'number');
              if (ret.tracks.length > 0) {
                scoreigcs(ret.tracks);
              }
            } catch (e) {console.log(e);alert('oups !!! '+result);}
          } else {
            alert(xhr.responseText);
          }
        }
      };
      xhr.send(formData);
    } catch (e) {console.log(e);}
    return false;
  }
  function scoreigcs(tracks) {
    Promise.all(tracks.map(t => scoreigc(t.id, t.indice))).then(finish).catch(err => {alert('attention, le score n\'a pas pu être calculé');finish();})
    document.body.innerHTML += 'Vol '+(ret.newvol?"ajouté":"mis à jour")+'<BR>Ne pas fermer cette fenêtre, scoring en cours...';
  }
  function scoreigc(id, indice) {
    return new Promise((resolve) => {
      try {
        let igccontent = '';
        if (indice < window.filestoupload.length) {
          igccontent = window.filestoupload[indice];
        }
        score(igccontent, (score) => {
          if (score && typeof score.value == 'object') {
            score = score.value;
          }
          if (score && typeof score.opt == 'object' && typeof score.opt.flight == 'object') delete score.opt.flight;
          postFlightScore(score, id).then(resolve);
        });
      } catch(e) {console.log(e);alert('attention, le score n\'a pas pu être calculé');resolve();}
    });
  };
  function finish() {
    if (ret.tracks.length <= 0) return;
    // on trie pour éditer éventuellement le premier vol qui n'a pas d'erreur
    ret.tracks = ret.tracks.sort((a,b) => (a.error||'').localeCompare(b.error||''))
    if (window.opener !== window && !window.menubar.visible) {
      window.opener.location.reload();
      setTimeout(function(){
        if (typeof window.opener.editvol == 'function') {
          window.opener.editvol(ret.tracks[0].id, ret.tracks[0].fptlat, ret.tracks[0].fptlon, ret.tracks[0].fptalt);
        }
      }, 500);
    } else {
      window.location = `edit.php?id=${ret.tracks[0].id}&lat=${encodeURIComponent(ret.tracks[0].fptlat)}&lon=${encodeURIComponent(ret.tracks[0].fptlon)}&alt=${encodeURIComponent(ret.tracks[0].fptalt)}`;
    }
  }
  function postFlightScore(score, id) {
    return new Promise((resolve) => {
      try {
        var xhttp = new XMLHttpRequest();
        xhttp.responseType = 'text';
        xhttp.onreadystatechange = function() {
          if (this.readyState == 4) {
            if (this.status < 200 && this.status >= 300) {
              alert('attention, le score n\'a pas pu être calculé');
            }
            resolve();
          }
        };
        data = "flightscore="+escape(JSON.stringify(score));
        xhttp.open("POST", "<?php echo $url;?>?id="+id, true);
        xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        xhttp.send(data);
      } catch(e) {console.log(e);alert('attention, le score n\'a pas pu être calculé');resolve();}
    });
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
<form enctype="multipart/form-data" action="<?php echo $url;?>" onsubmit="return onSubmit()" method="post">
    vol à editer/créer :<BR><select name="id">
  <option value="-1">Nouveau...</option>
</select><BR>
  <!-- MAX_FILE_SIZE doit précéder le champ input de type file -->
  <input type="hidden" name="MAX_FILE_SIZE" value="10000000" />
  <input type="hidden" id="concat" value="0" />
  <!-- Le nom de l'élément input détermine le nom dans le tableau $_FILES -->
  Envoyez le ou les fichiers IGC : <input id="igcs" name="userfile[]" type="file" multiple/><BR><BR>
  <center><input type="submit" id="submit" value="Envoyer le(s) fichier(s)" disabled/></center>
</form>

</body>
</html>