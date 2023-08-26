<?php
include("logfilereader.php");

if (isset($_GET['extract_igc'])) {
  if (isset($_GET['id']) && preg_match('/^\d+$/', $_GET['id'])) {
    $id = intval($_GET['id']);
    $lgfr = new LogflyReader();
    $igc = $lgfr->getIGC($id, true);
    if (strlen(trim($igc)) <= 0) {
      echo "Rien à faire";
      exit(0);
    } else {
      echo $lgfr->setIGC($id, $igc) ? "OK" : "KO";
      exit(0);
    }
  } else {
    extract_igc();
  }
} else if (isset($_GET['insert_igc'])) {
  if (isset($_GET['id']) && preg_match('/^\d+$/', $_GET['id']) && isset($_GET['base']) && file_exists(get_temp_base_name($_GET['base']))) {
    $id = intval($_GET['id']);
    $lgfr = new LogflyReader(get_temp_base_name($_GET['base']));
    $igc = $lgfr->getIGC($id, false);
    if (strlen(trim($igc)) <= 0) {
      echo "Rien à faire";
      exit(0);
    } else {
      echo $lgfr->setIGC($id, $igc, true) ? "OK" : "KO";
      exit(0);
    }
  } else if (isset($_GET['dl']) && isset($_GET['base']) && file_exists(get_temp_base_name($_GET['base']))) {
    $lgfr = new LogflyReader(get_temp_base_name($_GET['base']));
    $lgfr->downloadDB();
    exit(0);
  } else {
    insert_igc();
  }
} else if (isset($_GET['recalcul_igc'])) {
  if (isset($_GET['id']) && preg_match('/^\d+$/', $_GET['id'])) {
    $id = intval($_GET['id']);
    $lgfr = new LogflyReader();
    $igc = $lgfr->getIGC($id);
    echo $igc;
  } else {
    recalcul_igc();
  }
} else if (isset($_GET['regen_img'])) {
  $start = isset($_GET['start']) ? intval($_GET['start']) : PHP_INT_MAX;
  $end = isset($_GET['end']) ? intval($_GET['end']) : -1;
  regen_img($start, $end);
} else if (isset($_GET['genzip_tracklogs'])) {
  genzip_tracklogs();
} else if (isset($_GET['viewzip_tracklogs'])) {
  viewzip_tracklogs();
} else {
  clean_tmp_files();
?>
<ul>
  <li><a href="?extract_igc">extraire les fichiers igc de la base</a></li>
  <li><a href="?insert_igc">insérer les fichiers igc dans une base temporaire</a></li>
  <li><a href="?recalcul_igc">calculer les scores igc</a></li>
  <li><a href="?regen_img">regénérer les vignettes</a></li>
  <li><a href="parcours.php?force=1">regénérer la carte globale des parcours</a></li>
  <li><a href="?genzip_tracklogs">générér un nouveau zip des tracklogs</a></li>
  <li><a href="?viewzip_tracklogs">voir les zips des tracklogs</a></li>
</ul>
<?php
}

function clean_tmp_files() {
  foreach (glob(FOLDER_TL.DIRECTORY_SEPARATOR."Logfly_*.db") as $filename) {
    @unlink($filename);
  }
}
function get_temp_name($len = 8) {
  $ret =  "";
  $alphabet = "ABCDEFGHIJKLMNOPRQSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-";
  $alen = strlen($alphabet);
  for ($i=0; $i<$len; $i++) {
    $ret .= substr($alphabet, rand(0, $alen-1), 1);
  }
  return $ret;
}
function get_temp_base_name($base = null) {
  if (!$base) $base = get_temp_name();
  return FOLDER_TL.DIRECTORY_SEPARATOR."Logfly_".$base.".db";
}

function extract_igc()
{
  $vols = [];
  foreach ((new LogflyReader())->getRecords()->vols as $vol) {
    if ($vol->igc)
      $vols[] = $vol->id;
  }
  $vols = "[" . implode (",", $vols) . "]";
?>
<script>
  var vols = <?php echo $vols;?>;
  var volstodo = vols.length;
  function extract_igc(id) {
    var xhttp = new XMLHttpRequest();
    xhttp.responseType = 'text';
    xhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        volstodo--;
        document.body.innerHTML += id + " : " + this.responseText + "<BR/>";
        window.scrollTo(0, document.body.scrollHeight);
        if (volstodo <= 0)
          alert('tout est ok!');
      }
    };
    xhttp.open("GET", "<?php echo $_SERVER['REQUEST_URI'];?>&id="+id, true);
    xhttp.send();
  }
  window.onload = function() {
    for (let i=0; i < vols.length; i++) {
      extract_igc(vols[i])
    }
  };
</script>
  <?php
  }

function insert_igc()
{
  clean_tmp_files();
  $tmpbaseid = get_temp_name();
  $base = get_temp_base_name($tmpbaseid);
  if (!copy(LOGFLYDB, $base)) {
    echo "impossible de créer la base temporaire";
    exit(0);
  }
  $vols = [];
  foreach ((new LogflyReader($base))->getRecords()->vols as $vol) {
    if ($vol->igc)
      $vols[] = $vol->id;
  }
  $vols = "[" . implode (",", $vols) . "]";
?>
insertion des IGC... <span id="inserperc"></span><BR>
<script>
  let vols = <?php echo $vols;?>;
  let base = '<?php echo $tmpbaseid;?>';
  let inserperc = document.getElementById('inserperc');
  let cur = 0;
  function insert_igc(id) {
    return () => fetch("<?php echo $_SERVER['REQUEST_URI'];?>&base="+base+"&id="+id).then(res => res.text()).then(res => {
      //document.body.innerHTML += id + " : " + res + "<BR/>";
      window.scrollTo(0, document.body.scrollHeight);
      inserperc.innerHTML = Math.round(100*(++cur)/vols.length)+'%';
      return res;
    });
  }
  Promise.allsync = async (arrp) => {
    let results = [];
    for (const p of arrp) {
      let promise = p;
      if (typeof p.then !== 'function') promise = p();
      let r = await promise;
      results.push(r);
    }
    return results;
  }
  window.onload = function() {
    Promise.allsync(vols.map(insert_igc)).then(res => {
      document.body.innerHTML += "<a href=\"<?php echo $_SERVER['REQUEST_URI'];?>&base="+base+"&dl\">télécharger la base</a>";
      let errs = res.filter(r => r != 'OK');
      if (errs.length > 0) alert("il semble qu'il y'ai eu une/des erreurs : "+errs.join('\n'));
      else alert('tout est ok!');
    }).catch(err => alert(err));
  };
</script>
  <?php
  }

  function recalcul_igc()
  {
    $vols = [];
    //$i=0;
    foreach ((new LogflyReader())->getRecords()->vols as $vol) {
      if ($vol->igc)
        $vols[] = $vol->id;
      //if ($i++ > 10) break;
    }
    $vols = "[" . implode (",", $vols) . "]";
  ?>
  <script src="lib/igc-xc-score.js"></script>
  <script src="score.js"></script>
  <script>
    var vols = <?php echo $vols;?>;
    var volscore = [];
    var volstodo = vols.length;
    function postFlightScore(id, score) {
      var xhttp = new XMLHttpRequest();
      xhttp.responseType = 'text';
      xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
          document.getElementById('scorepost_'+id).innerHTML = "enregistrement OK";
          document.getElementById('vol'+id).scrollIntoView();
          volstodo--;
          if (volstodo <= 0)
            alert('tout est ok!');
        }
      };
      data = "flightscore="+escape(JSON.stringify(score));
      xhttp.open("POST", "upload.php?id="+id, true);
      xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
      xhttp.send(data);
    }
    var scoring = false;
    function score_igc() {
      if (scoring || volscore.length <= 0) return;
      try {
        scoring = true;
        let currenttrace = volscore.pop();
        if (typeof currenttrace !== 'object') {
          scoring = false;
          return;
        }
        score(currenttrace.igc, (score) => {
          postFlightScore(currenttrace.id, score);
          //document.body.innerHTML += currenttrace.id + " : score OK "+score.score+" pts<BR/>";
          document.getElementById('score_'+currenttrace.id).innerHTML = score.score+" pts, ";
          document.getElementById('scorepost_'+currenttrace.id).innerHTML = "enregistrement...";
          scoring = false;
          setTimeout(score_igc, 0);
        });
      } catch(e) {document.body.innerHTML += id + " : error : "+e+"<BR/>";}
    }
    function recalcul_igc(id) {
      var xhttp = new XMLHttpRequest();
      xhttp.responseType = 'text';
      xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
          if (this.responseText.trim().length <= 0) {
            volstodo--;
            document.body.innerHTML += id + " : IGC vide<BR/>";
            if (volstodo <= 0)
              alert('tout est ok!');
            return;
          }
          score_igc();
          document.body.innerHTML += "<a name=\"vol" + id + "\">vol n°" + id + "</a> : IGC récupéré, <span id=\"score_"+id+"\">calcul...</span> <span id=\"scorepost_"+id+"\"></span><BR/>";
          document.getElementById('vol'+id).scrollIntoView();
          volscore.push({id: id, igc: this.responseText});
        }
      };
      xhttp.open("GET", "<?php echo $_SERVER['REQUEST_URI'];?>&id="+id, true);
      xhttp.send();
    }
    window.onload = function() {
      for (let i=0; i < vols.length; i++) {
        recalcul_igc(vols[i])
      }
    };
  </script>
    <?php
  }
  
  
  function regen_img($start, $end)
  {
    $vols = [];
    //$i=0;
    foreach ((new LogflyReader())->getRecords()->vols as $vol) {
      if ($vol->id <= $start && $vol->id >= $end && $vol->igc)
        $vols[] = $vol->id;
      //if ($i++ > 10) break;
    }
    $vols = "[" . implode (",", $vols) . "]";
  ?>
  <script>
    var vols = <?php echo $vols;?>;
    var volstodo = vols.length;
    function regen_img(id) {
      return new Promise((res,rej) => {
        document.body.innerHTML += "<a name=\"vol" + id + "\" id=\"vol" + id + "\">vol n°" + id + " ("+(vols.length-volstodo+1)+"/"+vols.length+")</a> : <span id=\"regen_img_"+id+"\">génération de la vignette...</span><BR/>";
        var xhttp = new XMLHttpRequest();
        xhttp.responseType = 'text';
        xhttp.onreadystatechange = function() {
          if (this.readyState == 4) {
            volstodo--;
            document.getElementById('regen_img_'+id).innerHTML = this.status == 200 ? '<a href="image.php?id='+id+'" target="_blank">OK</a>':'KO';
            document.getElementById('vol'+id).scrollIntoView();
            if (this.status != 200) rej(id);
            else res(id);
          }
        };
        xhttp.open("GET", "image.php?force=1&id="+id, true);//http://montagne.pasutto.net/Parapente/logfly/image.php?id=512
        xhttp.send();
      });
    }
    window.onload = function() {
      /*let ps = [];
      for (let i=0; i < vols.length; i++) {
        ps.push(regen_img(vols[i]));
      }*/
      vols.reduce((p, x) => p.then(() => regen_img(x)),  Promise.resolve()).then(res => alert('traitement terminé!'));
    };
  </script>
    <?php
  }
  
  function genzip_tracklogs()
  {
    $prefix = 'tracklogs';
    $zip = new ZipArchive();
    $basepath = dirname(__FILE__) . DIRECTORY_SEPARATOR . FOLDER_TL . DIRECTORY_SEPARATOR;
    $matches = array_merge(glob($basepath.'*.igc'), glob($basepath.'*.json'), glob($basepath.'*.jpg'));
    /*header("Content-type: text/plain");
    echo "$basepath\n";
    print_r($matches);
    for ($i=0; $i<count($matches); $i++) {
        $localfname = $matches[$i];
        $j = strrpos($localfname, DIRECTORY_SEPARATOR);
        if ($j !== false) {
            $localfname = substr($localfname, $j+1);
        }
        echo "$localfname\n";
    }
    exit(0);*/
    $filename = $basepath.$prefix.date("Ymd").".zip";
    
    if ($zip->open($filename, ZipArchive::CREATE)!==TRUE) {
      exit("cannot open <$filename>\n");
    }
  	try
	{
		$lgfr = new LogflyReader();
	}
	catch(Exception $e)
	{
		echo "error!!! : ".$e->getMessage();
	}
    $carnetcont = chr(255) . chr(254) . mb_convert_encoding($lgfr->getCSV(FALSE), 'UTF-16LE', 'UTF-8');
    $zip->addFromString("carnet.csv", $carnetcont);
    for ($i=0; $i<count($matches); $i++) {
      $localfname = $matches[$i];
      $j = strrpos($localfname, DIRECTORY_SEPARATOR);
      if ($j !== false) {
          $localfname = substr($localfname, $j+1);
      }
      $zip->addFile($matches[$i],$localfname);
    }

    /*$zip->addFromString("testfilephp.txt" . time(), "#1 This is a test string added as testfilephp.txt.\n");
    $zip->addFromString("testfilephp2.txt" . time(), "#2 This is a test string added as testfilephp2.txt.\n");
    $zip->addFile($thisdir . "/too.php","/testfromfile.php");
    echo "numfiles: " . $zip->numFiles . "\n";
    echo "status:" . $zip->status . "\n";*/
    $zip->close();
    viewzip_tracklogs();
  }
  
  function viewzip_tracklogs()
  {
    $prefix = 'tracklogs';
    $basepath = dirname(__FILE__) . DIRECTORY_SEPARATOR . FOLDER_TL . DIRECTORY_SEPARATOR;
    $matches = array_merge(glob($basepath.$prefix.'*.zip'));
    for ($i=count($matches)-1; $i>=0; $i--) {
      $localfname = $matches[$i];
      $j = strrpos($localfname, DIRECTORY_SEPARATOR);
      if ($j !== false) {
          $localfname = substr($localfname, $j+1);
      }
      echo '<a href="'.FOLDER_TL.'/'.$localfname.'">'.$localfname.'</a><BR>';
    }
  }
?>