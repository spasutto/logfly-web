<!DOCTYPE html>
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
  window.close();
}
</script>
<?php
  }
  }
?>

<script type="text/javascript">
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
    calctemps();
  };

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

  function cleRelachee(evt)
  {
    //alert(evt.keyCode);
    calctemps();
  }

  function calctemps()
  {
    let temps = document.getElementsByName("duree")[0].value.toHHMMSS();
    document.getElementById("dureetemps").innerHTML = temps;
  }

  function onsubmitVol()
  {
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
</script>
vol à editer/créer : <select name="vol" onchange="onVolChange(this.value);">
  <option value="-1">Nouveau...</option>
<?php
  foreach ($lgfr->getRecords()->vols as $vol)
  echo "  <option value=\"".$vol->id."\">".$vol->id." : (".$vol->date->format('d/m/Y').") ".$vol->site."</option>\n";
?>
</select>
<form action="<?php echo $_SERVER['REQUEST_URI'];?>" name="formvol" method="post" onsubmit="onsubmitVol();">
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
 <p>Date : <input type="text" name="date" value="<?php echo date('d/m/Y');?>"/></p>
 <p>Heure : <input type="text" name="heure" value="<?php echo date('H:i:s');?>"/></p>
 <p>Durée (secondes) : <input type="text" name="duree" onKeyUp="cleRelachee(event)"/>&nbsp;soit <span id="dureetemps"></span></p>
 <p>Voile : <input type="text" name="voile" /></p>
 <p>Commentaire : <textarea name="commentaire" class="fullwidth" rows="10"/></textarea></p>
 <p><input type="submit" value="OK"></p>
</form>

</body>
</html>