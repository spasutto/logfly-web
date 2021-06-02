<?php
require("logfilereader.php");
try
{
  $lgfr = new LogflyReader();
}
catch(Exception $e)
{
  echo "error!!! : ".$e->getMessage();
  exit(0);
}
$id=FALSE;
if (isset($_POST['id']) && preg_match('/^\d+$/', $_POST['id']))
  $id = intval($_POST['id']);
else if (isset($_GET['id']) && preg_match('/^\d+$/', $_GET['id']))
  $id = intval($_GET['id']);
if ($id <= 0)
  $id = FALSE;
if ($id && isset($_GET["del"]))
{
  $lgfr->deleteVol($id);
  echo "OK";
  exit(0);
}
?><!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"/>
  <title>Edition d'un vol</title>

  <style>
  .fullwidth {
  width: 100%;
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
      $ret = $lgfr->addVol($site, $_POST['date'], $_POST['heure'], $_POST['duree'], $_POST['voile'], htmlspecialchars($_POST['commentaire']));
    else
      $ret = $lgfr->updateVol($id, $site, $_POST['date'], $_POST['heure'], $_POST['duree'], $_POST['voile'], htmlspecialchars($_POST['commentaire']));
    if ($ret)
    {
      //header("Location : /list.php");
?>
<script type="text/javascript">
alert('<?php echo $id?"updated !!!":"new record ok !!! ";?>');
if (window.opener !== window && !window.menubar.visible)
{
  window.onunload = refreshParent;
  function refreshParent() {
  window.opener.location.reload();
  }
}
window.close();
</script>
<?php
    }
  }
?>

<script type="text/javascript">
  if (window.opener !== window && !window.menubar.visible)
  {
    window.onunload = refreshParent;
    function refreshParent() {
      window.opener.location.reload();
    }
  }
  window.onload = function()
  {
<?php
function decode_dbstring($dbstring)
{
  return htmlspecialchars_decode(str_replace("'", "\\'", str_replace("\r", "", str_replace("\n", "\\n", $dbstring))));
}
  if ($id)
  {
    $vol  = $lgfr->getRecords($id);
    if($vol)
    {
    //echo"<pre>".print_r($vol)."</pre>";
?>
    document.getElementsByName("date")[0].value = '<?php echo $vol->date->format('d/m/Y');?>';
    document.getElementsByName("heure")[0].value = '<?php echo $vol->date->format('H:i:s');?>';
    document.getElementsByName("duree")[0].value = <?php echo $vol->duree;?>;
    document.getElementsByName("voile")[0].value = '<?php echo $vol->voile;?>';
    document.getElementsByName("commentaire")[0].value = '<?php echo decode_dbstring($vol->commentaire);?>'
    document.getElementsByName("site")[0].value = '<?php echo decode_dbstring($vol->site);?>'
    onSiteChange(document.getElementsByName("site")[0].value);
    document.getElementsByName("vol")[0].value = <?php echo $id;?>;
<?php
    }
    else
      $id = -1;
  }
?>
    calcheures();
    calcdate();
    
    let dureeheures = document.getElementsByName("dureeheures")[0];
    let duree = document.getElementsByName("duree")[0];
    let heure = document.getElementsByName("heure")[0];
    let date = document.getElementsByName("date")[0];
    
    dureeheures.onkeypress  = replaceDot;
    heure.onkeypress = replaceDot;

    dureeheures.addEventListener("focus", function() { this.select(); });
    duree.addEventListener("focus", function() { this.select(); });
    heure.addEventListener("focus", function() { this.select(); });
    date.addEventListener("focus", function() { createSelection(this, 0, 2); });
  };
  
  function createSelection(field, start, end) {
    if( field.createTextRange ) {
      var selRange = field.createTextRange();
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
    var val = this.value;
    evt = evt || window.event;
    
    // Ensure we only handle printable keys, excluding enter and space
    var charCode = typeof evt.which == "number" ? evt.which : evt.keyCode;
    if (charCode && charCode > 32) {
      var keyChar = String.fromCharCode(charCode);
      
      // Transform typed character
      var mappedChar = transformTypedChar(keyChar);
      
      var start, end;
      if (typeof this.selectionStart == "number" && typeof this.selectionEnd == "number") {
        // Non-IE browsers and IE 9
        start = this.selectionStart;
        end = this.selectionEnd;
        this.value = val.slice(0, start) + mappedChar + val.slice(end);
        
        // Move the caret
        this.selectionStart = this.selectionEnd = start + 1;
      } else if (document.selection && document.selection.createRange) {
        // For IE up to version 8
        var selectionRange = document.selection.createRange();
        var textInputRange = this.createTextRange();
        var precedingRange = this.createTextRange();
        var bookmark = selectionRange.getBookmark();
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
    var champautresite = document.getElementsByName("autresite")[0];
    if (val == -1)
      champautresite.style.display = 'inline-block';
    else
      champautresite.style.display = 'none';
  }

  function onVolChange(val)
  {
    let url = '<?php echo $_SERVER['SCRIPT_NAME'];?>';
    if (val > 0)
      url += '?id=' + val;
    window.location = url;
  }

  function calcheures()
  {
    let temps = document.getElementsByName("duree")[0].value;
    document.getElementsByName("dureeheures")[0].value = temps.toHHMMSS();
  }

  function calcsecondes()
  {
    let temps = document.getElementsByName("dureeheures")[0].value;
    document.getElementsByName("duree")[0].value = temps.toS();
  }

  function calcdate()
  {
    var days = ['Dimanche', 'Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
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
  }

  function delVol()
  {
    if (confirm("Êtes-vous sûr?"))
    {
      var xhttp = new XMLHttpRequest();
      message("loading...");
      xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
          if (this.responseText != "OK")
            alert(this.responseText);
          else {
            document.getElementById('delbtn').style.display = 'none';
            alert('Ce vol vient d\'être supprimé. Appuyer sur OK pour annuler la suppression');
            if (window.opener !== window && !window.menubar.visible)
              window.opener.location.reload();
          }
          message("");
        }
      };
      xhttp.open("GET", "<?php echo $_SERVER['REQUEST_URI'];?>&del", true);
      xhttp.send();
    }
    //window.location = "?del&id=" + document.getElementsByName("id")[0].value;
  }

  String.prototype.toHHMMSS = function () {
    var sec_num = parseInt(this, 10); // don't forget the second param
    if (isNaN(sec_num))
      sec_num = 0;
    var hours   = Math.floor(sec_num / 3600);
    var minutes = Math.floor((sec_num - (hours * 3600)) / 60);
    var seconds = sec_num - (hours * 3600) - (minutes * 60);

    if (hours   < 10) {hours   = "0"+hours;}
    if (minutes < 10) {minutes = "0"+minutes;}
    if (seconds < 10) {seconds = "0"+seconds;}
    return hours+':'+minutes+':'+seconds;
  }

  String.prototype.toS = function () {
    var secs = 0;
    var temps = this.replace(/[^0-9:]/g, '').substr(0,8).split(':').filter(function (e){return (e||"").trim().length>0;});
    switch (temps.length)
    {
      case 3:
        secs += parseInt(temps[2].substr(-2));
      case 2:
        secs += 60 * parseInt(temps[1].substr(-2));
        secs += 3600 * parseInt(temps[0].substr(-2));
        break;
      case 1:
        secs += 60 * parseInt(temps[0].substr(-2));
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
<h3 name="infobox"></h3>
vol à editer/créer : <select name="vol" onchange="onVolChange(this.value);">
  <option value="-1">Nouveau...</option>
<?php
  foreach ($lgfr->getRecords()->vols as $vol)
  echo "  <option value=\"".$vol->id."\">".$vol->id." : (".$vol->date->format('d/m/Y').") ".$vol->site."</option>\n";
?>
</select>
<?php
if ($id && !isset($_GET["del"]))
  echo "  <input type=\"button\" id=\"delbtn\" value=\"Suppr\" onclick=\"delVol();\">";
?>
<form action="<?php echo $_SERVER['REQUEST_URI'];?>" name="formvol" method="post" onsubmit="return onsubmitVol();">
 <p>Site : <select name="site" onchange="onSiteChange(this.value);">
  <option value="-1">Nouveau...</option>
<?php
  foreach ($lgfr->getSites() as $site)
  echo "  <option value=\"".$site."\">".$site."</option>\n";
?>
<input type="text" name="autresite"/>
</select>
</p>
  <input type="hidden" name="id" value="<?php echo $id;?>">
 <p>Date : <input type="text" name="date" value="<?php echo date('d/m/Y');?>" onKeyUp="calcdate();"/>&nbsp;&nbsp;<span name="jrsem"></span></p>
 <p>Heure : <input type="text" name="heure" value="<?php echo date('H:i:s');?>"/></p>
 <p>Durée (secondes) : <input type="text" name="duree" value="0" onKeyUp="calcheures();"/>&nbsp;soit&nbsp;<input type="text" name="dureeheures" onKeyUp="calcsecondes()"/></p>
 <p>Voile : <input type="text" name="voile" /></p>
 <p>Commentaire : <textarea name="commentaire" class="fullwidth" rows="10"></textarea></p>
 <p><input type="submit" value="OK"></p>
</form>

</body>
</html>