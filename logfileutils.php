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
			        break;
			    case 2:
			        $ret = sprintf('%dh%02d\'%02d"', $hours, $mins, $secs);
			        if ($hours == 0)
			            $ret = substr($ret, 2);
			        if ($secs == 0)
			            $ret = substr($ret, 0, -3);
			        break;
			}
			return $ret;
		}
	}
?>