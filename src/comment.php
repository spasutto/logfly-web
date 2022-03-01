<?php
  header('Content-Type: text/plain; charset=UTF-8');
 if (isset($_GET['id']) && preg_match('/^\d+$/', $_GET['id']))
 {
   $id = intval($_GET['id']);
    require("logfilereader.php");

    try
    {
      $lgfr = new LogflyReader();
      $textevol = $lgfr->getComment($id);
      if (strlen(trim($textevol)) <= 0)
        $textevol = "&nbsp;";
      else
        $textevol = htmlspecialchars($textevol);
      $textevol = preg_replace("/(\w+:\/\/[^\s]+)/","<a href=\"$1\">$1</a>",$textevol);
      $textevol = str_replace("\n", "<BR>", $textevol);
      echo $textevol;
    }
    catch(Exception $e)
    {
      echo "error!!! : ".$e->getMessage();
      exit(0);
    }
 }
?>