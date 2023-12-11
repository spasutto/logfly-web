<?php
  class Utils
  {
    public static function timeFromSeconds($seconds, $mode = 0)
    {
      $ret = "";
      $hours = floor($seconds / 3600);
      $mins = floor($seconds / 60 % 60);
      $secs = floor($seconds % 60);
      switch ($mode)
      {
        default:
        case 0:
        $ret = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
          break;
        case 1:
          $ret = sprintf('%dh%dmn', $hours, $mins, $secs);
          if ($mins == 0)
            $ret = substr($ret, 0, -3);
          break;
        case 2:
          $ret = sprintf('%dh%02d\'%02d"', $hours, $mins, $secs);
          if ($hours == 0)
            $ret = substr($ret, 2);
          if ($secs == 0)
          {
            $ret = substr($ret, 0, -3);
            if ($mins == 0)
              $ret = substr($ret, 0, -3);
          }
          if ($ret && $ret[0]=='0')
            $ret = substr($ret, 1);
          break;
        case 3:
          if ($hours>0)
            $ret = sprintf('%d heure%s, ', $hours, $hours>1?'s':'');
          $ret = sprintf('%s%2d minute%s et %2d seconde%s', $ret, $mins, $mins>1?'s':'', $secs, $secs>1?'s':'');
          break;
      }
      return $ret;
    }
  }
?>