<?php
require("logfilereader.php");

if (isset($_REQUEST["listevols"])) {
  header('Content-Type: application/json; charset=utf-8');
  echo '[';
  $first = true;
  foreach ((new LogflyReader())->getRecords()->vols as $vol) {
    if (!$first) {echo ",";}$first = false;
    echo "{\"id\":".$vol->id.", \"date\":\"".$vol->date->format('d/m/Y')."\", \"site\":\"".str_replace("\"", "\\\"", $vol->site)."\"}";
  }
  echo ']';
  exit(0);
}
else if (isset($_REQUEST["listesites"])) {
  header('Content-Type: application/json; charset=utf-8');
  echo '[';
  $first = true;
  foreach ((new LogflyReader())->getSites() as $site) {
    if (!$first) {echo ",";}$first = false;
    echo "{\"site\":\"".str_replace("\"", "\\\"", $site)."\"}";
  }
  echo ']';
  exit(0);
}

$id=FALSE;
if (isset($_POST['id']) && preg_match('/^\d+$/', $_POST['id']))
  $id = intval($_POST['id']);
else if (isset($_GET['id']) && preg_match('/^\d+$/', $_GET['id']))
  $id = intval($_GET['id']);
if ($id <= 0)
  $id = FALSE;
if ($id > 0)
{
  if (isset($_GET["del"]))
  {
    (new LogflyReader())->deleteVol($id);
    echo "OK";
    exit(0);
  }
  else if (isset($_POST['deligc'])) {
    echo (new LogflyReader())->setIGC($id)?"OK":"KO";
    exit(0);
  }
  else if (isset($_REQUEST["vol"])) {
    $vol  = (new LogflyReader())->getRecords($id);
    if($vol)
    {
      $date = $vol->date;
      $vol->date = $date->format('d/m/Y');
      $vol->heure = $date->format('H:i:s');
      echo json_encode($vol);
      exit(0);
    }
  }
  else if (isset($_REQUEST["igc"])) {
    echo (new LogflyReader())->getIGC($id);
    exit(0);
  }
}

$lat = false;
if (isset($_GET['lat']) && preg_match('/^\d+\.?\d*$/', $_GET['lat']))
  $lat = floatval($_GET['lat']);
else if (isset($_POST['lat']) && preg_match('/^\d+\.?\d*$/', $_POST['lat']))
  $lat = floatval($_POST['lat']);
$lon = false;
if (isset($_GET['lon']) && preg_match('/^\d+\.?\d*$/', $_GET['lon']))
  $lon = floatval($_GET['lon']);
else if (isset($_POST['lon']) && preg_match('/^\d+\.?\d*$/', $_POST['lon']))
  $lon = floatval($_POST['lon']);
$alt = false;
if (isset($_GET['alt']) && preg_match('/^\d+\.?\d*$/', $_GET['alt']))
  $alt = floatval($_GET['alt']);
else if (isset($_POST['alt']) && preg_match('/^\d+\.?\d*$/', $_POST['alt']))
  $alt = floatval($_POST['alt']);
  
/*if (isset($_REQUEST["uvol"])) {
    print_r($_POST);
    exit(0);
}*/

if (isset($_POST['site']) && isset($_POST['date']) && isset($_POST['heure']) && isset($_POST['duree']) && isset($_POST['voile']) && isset($_POST['commentaire'])
&& preg_match('/^\d+$/', $_POST['duree']))
{
if ($_POST['site'] != '-1')
  $site = htmlspecialchars($_POST['site']);
else if (isset($_POST['autresite']) && $_POST['autresite'] != '')
  $site = htmlspecialchars($_POST['autresite']);
else
{
  echo "err pas de site renseigné!!!";
  return;
}
if (!$id)
  $ret = @(new LogflyReader())->addVol($site, $_POST['date'], $_POST['heure'], $_POST['duree'], $_POST['voile'], htmlspecialchars($_POST['commentaire']), $lat, $lon, $alt);
else
  $ret = @(new LogflyReader())->updateVol($id, $site, $_POST['date'], $_POST['heure'], $_POST['duree'], $_POST['voile'], htmlspecialchars($_POST['commentaire']), $lat, $lon, $alt);
echo $ret?"OK":"KO";
exit(0);
}

?><!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"/>
  <title>Edition d'un vol</title>
  <script src="lib/igc-xc-score.js"></script>
  <script src="score.js"></script>

  <style>
  .fullwidth {
  width: 100%;
  }
  #infobox {
    position: absolute;
    background-color: #98D8E8E0;
    width: 100%;
    top: 0px;
    left: 0px;
    margin: 0px;
    padding: 0px;
    vertical-align: middle;
    text-align: center;
    border: solid 1px #81B9E1;
  }
  </style>
</head>
<body>

<?php
  /*if ($_GET['user'] != 'sylvain')
  {
  exit(0);
  return;
  }*/
  //phpinfo();
  //htmlspecialchars($_POST['nom']);
  //

?>

<script type="text/javascript">
  var id = <?php echo $id>0?$id:-1; ?>;
  var save = false;
  cursite = "";
  if (window.opener !== window && !window.menubar.visible)
  {
    window.onunload = refreshParent;
    function refreshParent() {
      if (save)
        window.opener.location.reload();
    }
  }
  window.onload = function()
  {
    loadData();
    loadVol(id);
    calcheures();
    calcdate();

    let dureeheures = document.getElementsByName("dureeheures")[0];
    //let duree = document.getElementsByName("duree")[0];
    let heure = document.getElementsByName("heure")[0];
    let date = document.getElementsByName("date")[0];

    dureeheures.onkeypress  = replaceDot;
    heure.onkeypress = replaceDot;

    dureeheures.addEventListener("focus", function() { this.select(); });
    //duree.addEventListener("focus", function() { this.select(); });
    heure.addEventListener("focus", function() { this.select(); });
    date.addEventListener("focus", function() { createSelection(this, 0, 2); });
  };

  function clearList(list)
  {
    let length = list.options.length;
    for (let i=length; i>=0; i--)
      list.options[i] = null;
    addOption(list, 'Nouveau...', -1, false);
  }

  function loadData() {
    loadVols();
    loadSites();
  }

  function loadVols() {
    let xhttp = new XMLHttpRequest();
    xhttp.responseType = 'json';
    message("chargement...");
    xhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        if (!this.response) return;
        let list = document.getElementsByName('vol')[0];
        clearList(list);
        for (let i=0; i<this.response.length; i++)
          addOption(list, this.response[i].id+" ("+this.response[i].date+") " + this.response[i].site, this.response[i].id);
        if (id > 0) {
          list.value = id;
        }
        document.getElementById('delbtn').style.display = id > 0 ? 'initial':'none';
        message("");
      }
    };
    xhttp.open("GET", "<?php echo strtok($_SERVER["REQUEST_URI"], '?');?>?listevols", true);
    xhttp.send();
  }

  function loadSites() {
    let xhttp = new XMLHttpRequest();
    xhttp.responseType = 'json';
    message("chargement...");
    xhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        if (!this.response) return;
        let list = document.getElementsByName('site')[0];
        clearList(list);
        for (let i=0; i<this.response.length; i++)
          addOption(list, this.response[i].site, this.response[i].site);
        if (id > 0)
          list.value = cursite;
        message("");
      }
    };
    xhttp.open("GET", "<?php echo strtok($_SERVER["REQUEST_URI"], '?');?>?listesites", true);
    xhttp.send();
  }

  function loadVol(id) {
    document.getElementById('zonescore').style.display = 'none';
    if (id > 0) document.getElementById('vignette').src = 'image.php?id='+id;
    document.getElementById('zonevignette').style.display = id > 0 ? 'initial' : 'none';
    document.getElementById('score').innerHTML = '';
    if (id <= 0) return;
    loadFlightScore(id).then(score => {
      document.getElementById('zonescore').style.display = 'initial';
      let scoreinfo = 'pas de score';
      if (score && score.scoreInfo && typeof score.scoreInfo.distance === 'number' && typeof score.scoreInfo.score === 'number') {
        scoreinfo = `score: ${score.scoreInfo.score}, distance : ${score.scoreInfo.distance}km`;
      }
      document.getElementById('score').innerHTML = scoreinfo;
    }).catch(console.log);
    let xhttp = new XMLHttpRequest();
    xhttp.responseType = 'json';
    message("chargement...");
    xhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        if (!this.response) return;
        message("");
        document.getElementsByName("date")[0].value = this.response.date;
        document.getElementsByName("heure")[0].value = this.response.heure;
        document.getElementsByName("duree")[0].innerText = this.response.duree;
        document.getElementsByName("dureeheures")[0].value = this.response.duree.toString().toHHMMSS();//this.response.sduree;
        document.getElementsByName("dureeHMS")[0].innerText = this.response.duree.toString().toHMS();//this.response.sduree;
        document.getElementsByName("voile")[0].value = this.response.voile;
        document.getElementsByName("commentaire")[0].value = this.response.commentaire;
        cursite = this.response.site;
        if (this.response.site.trim().length > 0)
            document.getElementsByName("site")[0].value = this.response.site;
        else
            document.getElementsByName("site")[0].selectedIndex  = 0;
        if (this.response.latdeco && this.response.londeco) {
            document.getElementsByName("lat")[0].value = this.response.latdeco;
            document.getElementsByName("lon")[0].value = this.response.londeco;
            document.getElementsByName("alt")[0].value = this.response.altdeco;
        }
        onSiteChange(document.getElementsByName("site")[0].value);
        document.getElementsByName("vol")[0].value = id;
      }
    };
    xhttp.open("GET", "<?php echo strtok($_SERVER["REQUEST_URI"], '?');?>?vol&id="+id, true);
    xhttp.send();
  }

  function saveVol()
  {
    if (!onsubmitVol())
      return false;
    let params = new Object();
    params.id = document.getElementsByName("vol")[0].value;
    params.date = document.getElementsByName("date")[0].value;
    params.heure = document.getElementsByName("heure")[0].value;
    params.duree = document.getElementsByName("duree")[0].innerText;
    params.voile = document.getElementsByName("voile")[0].value;
    params.commentaire = document.getElementsByName("commentaire")[0].value;
    params.site = document.getElementsByName("site")[0].value;
    params.lat = document.getElementsByName("lat")[0].value;
    params.lon = document.getElementsByName("lon")[0].value;
    params.alt = document.getElementsByName("alt")[0].value;
    if (params.site == -1 || params.site.toString().trim().length <= 0) {
      params.site = document.getElementsByName("autresite")[0].value;
    }
    if (document.getElementsByName("deligc")[0].checked)
      params.deligc = 1;
    // Turn the data object into an array of URL-encoded key/value pairs.
    let urlEncodedData = "";
    for( name in params ) {
     urlEncodedData += encodeURIComponent(name)+'='+encodeURIComponent(params[name])+'&';
    }
    let xhttp = new XMLHttpRequest();
    message("chargement...");
    xhttp.onreadystatechange = function() {
      if (this.readyState == 4) {
        message("");
        if (this.status != 200 || this.responseText != "OK") {
            alert("l'enregistrement semble avoir échoué ! " + this.status + ' ' + this.responseText);
        }
        else {
            alert(params.id>0?"updated !!!":"new record ok !!! ");

            if (window.opener) {
                window.opener.location.reload();
                window.close();
            }
        }
      }
    };
    xhttp.open("POST", "<?php echo strtok($_SERVER["REQUEST_URI"], '?');?>?uvol&id="+id, true);
    xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    save = true;
    xhttp.send(urlEncodedData);
  }

  function addOption(list, nom, value, selected)
  {
    let option = document.createElement("option");
    option.text = nom;
    option.value = value;
    if (selected)
      option.selected = true;
    list.add(option);
  }

  function createSelection(field, start, end) {
    if( field.createTextRange ) {
      let selRange = field.createTextRange();
      selRange.collapse(true);
      selRange.moveStart('character', start);
      selRange.moveEnd('character', end);
      selRange.select();
      field.focus();
    } else if( field.setSelectionRange ) {
      field.focus();
      field.setSelectionRange(start, end);
    } else if( typeof field.selectionStart != 'undefined' ) {
      field.selectionStart = start;
      field.selectionEnd = end;
      field.focus();
    }
  }
  function transformTypedChar(charStr) {
    return charStr == "." ? ":" : charStr;
  }
  function replaceDot(evt) {
    let val = this.value;
    evt = evt || window.event;

    // Ensure we only handle printable keys, excluding enter and space
    let charCode = typeof evt.which == "number" ? evt.which : evt.keyCode;
    if (charCode && charCode > 32) {
      let keyChar = String.fromCharCode(charCode);

      // Transform typed character
      let mappedChar = transformTypedChar(keyChar);

      let start, end;
      if (typeof this.selectionStart == "number" && typeof this.selectionEnd == "number") {
        // Non-IE browsers and IE 9
        start = this.selectionStart;
        end = this.selectionEnd;
        this.value = val.slice(0, start) + mappedChar + val.slice(end);

        // Move the caret
        this.selectionStart = this.selectionEnd = start + 1;
      } else if (document.selection && document.selection.createRange) {
        // For IE up to version 8
        let selectionRange = document.selection.createRange();
        let textInputRange = this.createTextRange();
        let precedingRange = this.createTextRange();
        let bookmark = selectionRange.getBookmark();
        textInputRange.moveToBookmark(bookmark);
        precedingRange.setEndPoint("EndToStart", textInputRange);
        start = precedingRange.text.length;
        end = start + selectionRange.text.length;

        this.value = val.slice(0, start) + mappedChar + val.slice(end);
        start++;

        // Move the caret
        textInputRange = this.createTextRange();
        textInputRange.collapse(true);
        textInputRange.move("character", start - (this.value.slice(0, start).split("\r\n").length - 1));
        textInputRange.select();
      }

      return false;
    }
  }

  function message(mesg)
  {
    document.getElementsByName("infobox")[0].innerHTML = mesg;
  }

  function onSiteChange(val)
  {
    let champautresite = document.getElementsByName("autresite")[0];
    if (val == -1)
      champautresite.style.display = 'inline-block';
    else
      champautresite.style.display = 'none';
  }

  function onVolChange(val)
  {
    id = val;
    document.getElementById('delbtn').style.display = id > 0 ? 'initial':'none';
    loadVol(val);
  }

  function calcheures()
  {
    let temps = document.getElementsByName("duree")[0].innerText;
    document.getElementsByName("dureeheures")[0].value = temps.toHHMMSS();
  }

  function calcsecondes()
  {
    let temps = document.getElementsByName("dureeheures")[0].value;
    let dureesecs = temps.toS();
    document.getElementsByName("duree")[0].innerText = dureesecs;
    document.getElementsByName("dureeHMS")[0].innerText = dureesecs.toString().toHMS();
  }

  function calcdate()
  {
    let days = ['Dimanche', 'Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    let dateParts = document.getElementsByName("date")[0].value.split("/");
    let dateVol = new Date(+dateParts[2], dateParts[1] - 1, +dateParts[0]);
    let jrDate = (dateVol.getDay() < 7)?days[dateVol.getDay()]:'?';
    document.getElementsByName("jrsem")[0].innerHTML = '('+jrDate+')';
  }

  function onsubmitVol()
  {
    let siteid = document.getElementsByName("site")[0].value;
    let champautresite = document.getElementsByName("autresite")[0].value;
    if (siteid == -1 && (typeof champautresite != "string" || champautresite.trim() == ""))
    {
      alert('Renseigner un site !');
      return false;
    }
    return true;
  }

  function delVol()
  {
    if (confirm("Êtes-vous sûr?"))
    {
      let xhttp = new XMLHttpRequest();
      message("chargement...");
      xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
          if (this.responseText != "OK")
            alert(this.responseText);
          else {
            document.getElementById('delbtn').style.display = 'none';
            alert('Ce vol vient d\'être supprimé. Revalider la popup pour annuler la suppression');
            if (window.opener !== window && !window.menubar.visible)
              window.opener.location.reload();
          }
          message("");
        }
      };
      xhttp.open("GET", "<?php echo strtok($_SERVER["REQUEST_URI"], '?');?>?del&id="+id, true);
      xhttp.send();
    }
    //window.location = "?del&id=" + document.getElementsByName("id")[0].value;
  }
  function calcFlightScore() {
    let xhttp = new XMLHttpRequest();
    message("chargement...");
    xhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        message("calcul...");
        try {
          score(this.responseText, (score) => {
            message("");
            if (confirm ("Le score calculé est de " + Math.round(score.score*10)/10 + " points pour "+Math.round(score.scoreInfo.distance*10)/10+"km, mettre à jour?")) {
              message("enregistrement...");
              postFlightScore(id, score).then((msg) => {message("");alert(msg == "OK"?"Fait!":"Il semble qu'il y'ai eu un problème : " + msg);});
            }
          });
        } catch(e) {alert(e);}
      }
    };
    xhttp.open("GET", "<?php echo strtok($_SERVER["REQUEST_URI"], '?');?>?igc&id="+id, true);
    xhttp.send();
  }
  function regenVignette() {
    document.getElementById('vigntext').innerHTML = "chargement...";
    document.getElementById('zonevignette').style.display = 'none';
    fetch("image.php?id="+id+"&force=1").then(r=>r.blob()).then(img => {
      document.getElementById('vignette').src = URL.createObjectURL(img);
      document.getElementById('zonevignette').style.display = 'initial';
    }).finally(() => {
      document.getElementById('vigntext').innerHTML = "";
    }).catch(()=>alert('oups'));
  }

  String.prototype.toHMS = function () {
    let sec_num = parseInt(this, 10);
    if (isNaN(sec_num))
      sec_num = 0;
    let hours   = Math.floor(sec_num / 3600);
    let minutes = Math.floor((sec_num - (hours * 3600)) / 60);
    let seconds = sec_num - (hours * 3600) - (minutes * 60);
    let strRet = "";
    if (hours != 0)
      strRet += hours+"h";
    if (minutes != 0)
      strRet += minutes+"mn";
    if (seconds != 0)
      strRet += seconds+"s";
    return strRet;
  }

  String.prototype.toHHMMSS = function () {
    let sec_num = parseInt(this, 10);
    if (isNaN(sec_num))
      sec_num = 0;
    let hours   = Math.floor(sec_num / 3600);
    let minutes = Math.floor((sec_num - (hours * 3600)) / 60);
    let seconds = sec_num - (hours * 3600) - (minutes * 60);

    if (hours   < 10) {hours   = "0"+hours;}
    if (minutes < 10) {minutes = "0"+minutes;}
    if (seconds < 10) {seconds = "0"+seconds;}
    return hours+':'+minutes+':'+seconds;
  }

  String.prototype.toS = function () {
    let secs = 0;
    //let temps = this.replace(/[^0-9:]/g, '').substr(0,8).split(':').filter(function (e){return (e||"").trim().length>0;});
    let temps = this.replace(/[^0-9:]/g, '').split(':').filter(function (e){return (e||"").trim().length>0;});
    switch (temps.length)
    {
      case 3:
        secs += parseInt(temps[2]);
      case 2:
        secs += 60 * parseInt(temps[1]);
        secs += 3600 * parseInt(temps[0]);
        break;
      case 1:
        secs += 60 * parseInt(temps[0]);
        break;
    }
    return isNaN(secs)?0:secs;
  }

  if (!String.prototype.trim) {
    String.prototype.trim = function () {
      return this.replace(/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g, '');
    };
  }
</script>
<h3 id="infobox" name="infobox"></h3>
vol à editer/créer :<BR><select name="vol" onchange="onVolChange(this.value);">
  <option value="-1" selected>Chargement...</option>
<?php
  //foreach ($lgfr->getRecords()->vols as $vol)
  //  echo "  <option value=\"".$vol->id."\">".$vol->id." : (".$vol->date->format('d/m/Y').") ".$vol->site."</option>\n";
?>
</select>
<?php
//if ($id && !isset($_GET["del"]))
  echo "  <input type=\"button\" id=\"delbtn\" value=\"Suppr\" onclick=\"delVol();\" style=\"display:none\">";
?>
<form action="<?php echo $_SERVER['REQUEST_URI'];?>" name="formvol" method="post" onsubmit="return onsubmitVol();">
 <p>Site : <select name="site" onchange="onSiteChange(this.value);">
  <option value="-1" selected>Chargement...</option>
<?php
  //foreach ($lgfr->getSites() as $site)
  //echo "  <option value=\"".$site."\">".$site."</option>\n";
?>
<input type="text" name="autresite"/>
</select>
</p>
  <input type="hidden" name="id" value="<?php echo $id;?>">
  <input type="hidden" name="lat" value="<?php echo $lat;?>">
  <input type="hidden" name="lon" value="<?php echo $lon;?>">
  <input type="hidden" name="alt" value="<?php echo $alt;?>">
  <p>Date : <input type="text" name="date" value="<?php echo date('d/m/Y');?>" onKeyUp="calcdate();"/>&nbsp;&nbsp;<span name="jrsem"></span>
  Heure : <input type="text" name="heure" value="<?php echo date('H:i:s');?>"/></p>
  <p>Durée : <input type="text" name="dureeheures" onKeyUp="calcsecondes()"/>(<span name="dureeHMS"></span>)&nbsp;soit&nbsp;<span name="duree">0</span>&nbsp;secondes</p>
  <p>Voile : <input type="text" name="voile" /></p>
  <p>Commentaire : <textarea name="commentaire" class="fullwidth" rows="10"></textarea></p>
  <div style="height:40px">
    <input type="checkbox" name="deligc" id="cbdeligc" value="1"><label for="cbdeligc">supprimer le fichier IGC</label><BR>
    <span id="vigntext"></span>
    <div id="zonevignette" style="display:none">
      <a href="#" onclick="regenVignette()">regénérer la vignette</a><BR>
      <img id="vignette" style="max-width:256px;max-height:256px" src="image.php?id=<?php echo $id;?>"></div>
    </div>
    <div id="zonescore" style="display:none;float:right">
      <span id="score" style="display:block"></span>
      <a href="#" onclick="calcFlightScore()">recalculer le score</a>
    </div>
  </div>
  <p><input id="btnSave" type="button" value="Enregistrer" onclick="saveVol()" style="float:right"></p>
</form>

</body>
</html>