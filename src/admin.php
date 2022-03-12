<?php
include("logfilereader.php");

if (isset($_GET['extract_igc'])) {
  if (isset($_GET['id']) && preg_match('/^\d+$/', $_GET['id'])) {
    $id = intval($_GET['id']);
    $lgfr = new LogflyReader();
    $igc = $lgfr->getIGC($id, true);
    if (strlen(trim($igc)) <= 0) {
      echo "Rien à faire";
      return;
    } else {
      echo $lgfr->setIGC($id, $igc) ? "OK" : "KO";
    }
  } else {
    extract_igc();
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
} else {
?>
<ul>
  <li><a href="?extract_igc">extraire les fichiers igc de la base</a></li>
  <li><a href="?recalcul_igc">calculer les scores igc</a></li>
</ul>
<?php
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
  <script src="igc-xc-score.js"></script>
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
        IGCScore.score(currenttrace.igc, (score) => {
          if (score && typeof score.value == 'object') {
            score = score.value;
          }
          if (score && typeof score.opt == 'object' && typeof score.opt.flight == 'object') delete score.opt.flight;
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
          document.body.innerHTML += "vol n°" + id + " : IGC récupéré, <span id=\"score_"+id+"\">calcul...</span> <span id=\"scorepost_"+id+"\"></span><BR/>";
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
?>