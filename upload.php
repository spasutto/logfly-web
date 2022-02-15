
<?php
$url = "http".(!empty($_SERVER['HTTPS'])?"s":"")."://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
if (!isset($_FILES['userfile']['tmp_name'])) {
?>
<!-- Le type d'encodage des données, enctype, DOIT être spécifié comme ce qui suit -->
<form enctype="multipart/form-data" action="<?php echo $url;?>" method="post">
  <!-- MAX_FILE_SIZE doit précéder le champ input de type file -->
  <input type="hidden" name="MAX_FILE_SIZE" value="10000000" />
  <!-- Le nom de l'élément input détermine le nom dans le tableau $_FILES -->
  Envoyez ce fichier : <input name="userfile" type="file" />
  <input type="submit" value="Envoyer le fichier" />
</form>

<?php
} else {

  if (!is_file($_FILES['userfile']['tmp_name']))
  {
    echo "Unable to get input file";
  } else {
    //echo "file : \"".$_FILES['userfile']['tmp_name']."\"<BR>";
    
    require("TrackLogManager.php");

    $mgr = new TrackLogManager();
    $ext = $_FILES['userfile']['name'];
    $ext = substr(trim(strtolower($ext)), -3);
    $id = $mgr->uploadIGC($_FILES['userfile']['tmp_name'], $ext);
    if ($id) {
?>
<script>
id = <?php echo $id;?>;
alert('vol no ' + id + ' ajouté');
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
</script>
<?php
    }
  }
}
?>
