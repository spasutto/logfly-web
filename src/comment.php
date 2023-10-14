<?php
  header('Content-Type: text/plain; charset=UTF-8');
 if (isset($_GET['id']) && preg_match('/^\d+$/', $_GET['id']))
 {
   $id = intval($_GET['id']);
    require("logfilereader.php");

    try
    {
      $lgfr = new LogflyReader();
      $textevol = htmlspecialchars($lgfr->getComment($id));
      //$textevol = preg_replace("/(\w+:\/\/[^\s]+)/","<a href=\"$1\">$1</a>",$textevol);
      $textevol = preg_replace_callback("/(\w+:\/\/[^\s]+)/",function($match) {
        return "<a href=\"".$match[1]."\">".(strlen($match[1])<28?$match[1]:substr($match[1], 0, 28)."...")."</a>";
      },$textevol);
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