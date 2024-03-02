<?php
header('Content-Type: text/html; charset=UTF-8');
define("SMTAG", "<!--SMILEY-->");
define("LNTAG", "<!--LINK-->");
$smileys = Array(
  Array(':)',  0x1F6, 0x0a),
  Array(':-)', 0x1F6, 0x0a),
  Array(';)',  0x1F6, 0x09),
  Array(';-)', 0x1F6, 0x09),
  Array(':p',  0x1F6, 0x0b),
  Array(':-p', 0x1F6, 0x0b),
  Array(':D',  0x1F6, 0x04),
  Array(':-D', 0x1F6, 0x04),
  Array('XD',  0x1F6, 0x06),
  Array('X-D', 0x1F6, 0x06),
  Array('B)',  0x1F6, 0x0E),
  Array('B-)', 0x1F6, 0x0E),
  Array(':(',  0x1F6, 0x1F),
  Array(':-(', 0x1F6, 0x1F),
  Array(':|',  0x1F6, 0x2C),
  Array(':-|', 0x1F6, 0x2C),
  Array(':/',  0x1F6, 0x15),
  Array(':-/', 0x1F6, 0x15),
  Array('*)',  0x1F9, 0x29),
  Array('*-)', 0x1F9, 0x29),
);
function parseSmileys($text) {
  global $smileys;
  foreach ($smileys as $s) {
    //$text = $text."\"".$s[0]." : &#x1F6".sprintf('%02X', $s[1]).";\" | ";
    $text = str_ireplace($s[0], SMTAG."&#x".sprintf('%03X', $s[1]).sprintf('%02X', $s[2]).";".SMTAG, $text);
  }
  // utilisé pour éviter ce cas : "(lol :))" traduit en "(lol &#x1F60A;)" puis en "(lol &#x1F60A&#x1F609;"
  return str_replace(SMTAG,"", $text);
}
if (isset($_GET['id']) && preg_match('/^\d+$/', $_GET['id']))
{
  $id = intval($_GET['id']);
  require("logfilereader.php");

  try
  {
    $lgfr = new LogflyReader();
    $textevol = htmlspecialchars($lgfr->getComment($id));
    $textevol = preg_replace_callback("/\[img\](.*)\[\/img\]/",function($match) {
      $urlimg = str_replace("://", ":".LNTAG."//", $match[1]);
      return "<a href=\"".$urlimg."\" target=\"_Blank\"><img src=\"".$urlimg."\" style=\"max-width:100%;max-height:300px\"></a>";
    }, $textevol);
    //$textevol = preg_replace("/(\w+:\/\/[^\s]+)/","<a href=\"$1\">$1</a>",$textevol);
    $textevol = preg_replace_callback("/(\w+:\/\/[^\s]+)/",function($match) use($smileys) {
      $url = $match[1];
      // on cherche s'il n'y aurait pas des semblants smileys dans l'url (on doit évidemment les ignorer)
      foreach ($smileys as $s) {
        // on segmente le smiley avec un LNTAG pour qu'il ne soit pas reconnu dans parseSmileys
        $url = str_ireplace($s[0], $s[0][0].LNTAG.substr($s[0], 1), $url);
      }
      return "<a href=\"".$url."\">".(strlen($url)<28?$url:substr($url, 0, 28)."...")."</a>";
    }, $textevol);
    $textevol = preg_replace_callback("/_([^_]*)_/",function($match) {
      return "<u>".$match[1]."</u>";
    }, $textevol);
    $textevol = preg_replace_callback("/\*([^*]*)\*/",function($match) {
      return "<b>".$match[1]."</b>";
    }, $textevol);
    $textevol = str_replace("\n", "<BR>", $textevol);
    $textevol = parseSmileys($textevol);
    echo str_replace(LNTAG,"", $textevol);
  }
  catch(Exception $e)
  {
    echo "error!!! : ".$e->getMessage();
    exit(0);
  }
}
?>