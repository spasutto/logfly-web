<?php
const MAX_FILE_SIZE = 10000000;
if (isset($_GET['id']) && preg_match('/^\d+$/', $_GET['id'])) {
  $id = intval($_GET['id']);
  if (isset($_POST['flightscore'])) {
    //echo $_POST['flightscore'];
    require("tracklogmanager.php");
    $mgr = new TrackLogManager();
    echo $mgr->putFlightScore($id, $_POST['flightscore']) ? "OK":"KO";
    exit(0);
  }
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
    $totsize = 0;
    foreach ($igcs as $igc) {
      $igcsize = @filesize($igc);
      $totsize += $igcsize;
      if ($igcsize > MAX_FILE_SIZE) {
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
        echo "fichier uploadé trop gros";
        die();
      }
    }
    if ($concat && $totsize > MAX_FILE_SIZE) {
      header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
      echo "fichier concaténé trop gros";
      die();
    }
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
  <style>
    * {
      font-family: sans-serif;
    }
    input, select {
      height:50px;
    }
    input[type='button'] {
      margin-top: 10px;
    }
    #zoneigc {
      text-align:center;
    }
    @media (min-width: 768px) {
      ul {
        max-width: 650px;
      }
      li>span {
        width: 450px;
      }
    }
    @media (max-width: 768px) {
      ul {
        width: 100%;
      }
      li>span {
        width: 80%;
      }
    }
    ul {
      display: inline-block;
      list-style-type: none;
      padding : 0;
      font-weight: bold;
    }
    li {
      padding: 5px 0px;
    }
    li:not(:last-child) {
      border-bottom: solid 1px black;
    }
    li:hover {
      background-color: #ebebeb;
    }
    li>span {
      display: inline-block;
    }
    li::before {
      content: '\1F4DF ';
    }
    li.ok::after {
      content: ' \2705';
    }
    li.ko::after {
      content: ' \274C';
    }
    .wrong {
      text-decoration: line-through;
      color: red;
      font-weight: bolder;
    }
  </style>
</head>
<body>
<script>
  const browseForFile = () => {
    let lock = false
    return new Promise((resolve, reject) => {
      // create input file
      const el = document.createElement('input');
      el.id = +new Date();
      el.style.display = 'none';
      el.setAttribute('type', 'file');
      el.setAttribute('multiple', '');
      el.setAttribute('accept', '.igc');// accept=".gif,.jpg,.jpeg,.png,.doc,.docx"
      document.body.appendChild(el)

      el.addEventListener('change', () => {
        lock = true
        resolve(el.files)
        // remove dom
        document.body.removeChild(document.getElementById(el.id))
      }, { once: true });

      // open file select box
      el.click();

      // file blur
      window.addEventListener('focus', () => {
        setTimeout(() => {
          if (!lock && document.getElementById(el.id)) {
            try {
              //reject(new Error('onblur'))
              resolve(null);
              // remove dom
              document.body.removeChild(document.getElementById(el.id))
            } catch(err){resolve(null);}
          }
        }, 300);
      }, { once: true })
    })
  }
  function trygetigcinfos(igc) {
    let ret = {'date': null};
    if (typeof igc === 'string') {
      igc = igc.split(/\r?\n/g).map(l => l.trim());
      let dte = null, fp = null;
      if (dte = igc.find(l => l[0]=='H' && l.substring(2,5)=='DTE')) {
        if (/\d{6}/.test(dte.substring(5, 11))) dte = dte.substring(5, 11);
        else if (/\d{6}/.test(dte.substring(10, 16))) dte = dte.substring(10, 16);
        else dte = null;
        if (dte && (fp = igc.find(l => l[0]=='B')) && /\d{6}/.test(fp.substring(1, 7))) {
          ret.date = new Date(2000+parseInt(dte.substring(4, 6)), parseInt(dte.substring(2, 4))-1, dte.substring(0, 2), fp.substring(1, 3), fp.substring(3, 5), fp.substring(5, 7));
        }
      }
    }
    return ret;
  }

  function loadVols() {
    var xhttp = new XMLHttpRequest();
    xhttp.responseType = 'json';
    xhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        if (!this.response) return;
        clearList(vol_id);
        this.response.forEach(vol => addOption(vol_id, vol.id+" ("+vol.date+") " + vol.site, vol.id));
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
  function seligc() {
    btn_seligc.disabled = true;
    browseForFile().then(handleFileSelect).catch(err=> {
      btn_seligc.disabled = false;
      alert('Erreur durant la sélection du fichier!');
      console.error(err);
    });
  }
  function displayigcinfos(files) {
    listfiles.innerHTML = "";
    files = files.map(file => {
      let igctext = file.name;
      let fp = trygetigcinfos(file.igc);
      file.date = -1;
      if (typeof fp.date?.getMonth === 'function') {
        file.date = fp.date;
      }
      return file;
    }).sort((a,b) => a.date-b.date);
    files.forEach(file => {
      let igctext = file.name;
      let fp = trygetigcinfos(file.igc);
      let li = document.createElement("li");
      let span = document.createElement("span");
      li.appendChild(span);
      if (typeof fp.date?.getMonth === 'function') {
        igctext = `${igctext} (${fp.date.toLocaleString()} UTC)`;
        filestoupload.push(file.igc);
        li.classList.add("ok");
      } else {
        let err = 'le fichier semble invalide !';
        if (typeof file.error === 'string') err = file.error;
        igctext = `<span class="wrong">${igctext}</span> (${err})`;
        li.classList.add("ko");
      }
      span.innerHTML = igctext;
      listfiles.appendChild(li);
    });
    let plur = filestoupload.length > 1 ? 's':'';
    submit.value = `Envoyer le${plur} fichier${plur}`;
    submit.disabled = filestoupload.length <= 0;
  }
  function handleFileSelect(files) {
    files = files || [];
    let totfiles = files.length || 0;
    submit.disabled = true;
    window.filestoupload = [];
    listfiles.innerHTML = totfiles<=0?"":"vérification des traces...";
    let displayfiles = [];
    let testfinish = () => {
      if (totfiles <= 0) {
        displayigcinfos(displayfiles);
      }
    }
    [...files].forEach(file => {
      if (file.size <= <?php echo MAX_FILE_SIZE;?>) {
        const reader = new FileReader();
        reader.onload = (e) => {
          totfiles--;
          displayfiles.push({'name':file.name, 'igc':e.target.result})
          testfinish();
        };
        try {
          reader.readAsText(file);
        } catch (err) {
          console.error(err);
          totfiles--;
          displayfiles.push({'name':file.name, 'igc':null, 'error': 'impossible de charger le fichier'})
          testfinish();
        }
      } else {
        totfiles--;
        displayfiles.push({'name':file.name, 'igc':null, 'error': 'fichier trop gros'})
        testfinish();
      }
    });
    //if (totfiles <= 0)
      btn_seligc.disabled = false;
  }
  function submitfiles() {
    try {
      submit.disabled = true;
      const formData = new FormData();
      let id = vol_id.value;
      concat.value = false;
      window.filestoupload = window.filestoupload || [];
      if (filestoupload.length > 1) {
        concat.value = !!confirm('Concaténer les fichiers?');
      }
      formData.append("id", id);
      formData.append("concat", concat.value);
      for (let i=0; i<filestoupload.length; i++) {
        //formData.append("userfile[]", filestoupload[i]);
        const blob = new Blob([window.filestoupload[i]], { type: "text/plain" });
        formData.append("userfile[]", blob);
      }
      const xhr = new XMLHttpRequest();
      xhr.open("POST", "<?php echo $url;?>");
      xhr.onreadystatechange = () => {
        if (xhr.readyState === XMLHttpRequest.DONE) {
          if (xhr.status >= 200 && xhr.status < 300) {
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
            } catch (e) {console.error(e);alert('oups !!! '+result);}
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
    let errfn = err => {console.error(err);alert('attention, le score n\'a pas pu être calculé');finish();};
    try {
      Promise.all(tracks.map(t => scoreigc(t.id, t.indice))).then(finish).catch(errfn);
      document.body.innerHTML += 'Vol '+(ret.newvol?"ajouté":"mis à jour")+'<BR>Ne pas fermer cette fenêtre, scoring en cours...';
    } catch (err) {
      errfn(err);
    }
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
    document.body.innerHTML = ret.tracks.length+' score(s) calculé(s)';
    //handleFileSelect(null); // reinit file chooser
    window.filestoupload = null;
    if (ret.tracks.length <= 0) return;
    let editlnk = `edit.php?id=${ret.tracks[0].id}&lat=${encodeURIComponent(ret.tracks[0].fptlat)}&lon=${encodeURIComponent(ret.tracks[0].fptlon)}&alt=${encodeURIComponent(ret.tracks[0].fptalt)}`;
    document.body.innerHTML += `<BR><a href="${editlnk}">&eacute;diter le vol</a>`;
    // on trie pour éditer éventuellement le premier vol qui n'a pas d'erreur
    ret.tracks = ret.tracks.sort((a,b) => (a.error||'').localeCompare(b.error||''));
    if (window.opener !== window && !window.menubar.visible) {
      window.opener.location.reload();
      setTimeout(function(){
        if (typeof window.opener.editvol == 'function') {
          window.opener.editvol(ret.tracks[0].id, ret.tracks[0].fptlat, ret.tracks[0].fptlon, ret.tracks[0].fptalt);
        }
      }, 500);
    } else {
      window.location = editlnk;
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
  function changevol() {
    zoneremarquevol.innerHTML = (vol_id.value < 0)?'':'Vous allez mettre à jour la trace du vol no ' + vol_id.options[vol_id.selectedIndex].text;
  }

  window.onload = loadVols;
</script>
  <div id="zoneigc">
    <h3>Vol à editer/créer :</h3>
    <select id="vol_id" onchange="changevol()"><option value="-1">Nouveau...</option></select><BR>
    <span id="zoneremarquevol"></span><BR>
    <input type="hidden" id="concat" value="0" />
    <input type="button" id="btn_seligc" onclick="seligc()" value="&#x1F4C1;Sélectionner le ou les fichiers IGC"/><BR><ul id="listfiles"></ul><BR>
    <input type="button" id="submit" onclick="submitfiles();" value="Envoyer le(s) fichier(s)" disabled/>
  </div>

</body>
</html>