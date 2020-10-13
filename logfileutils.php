<?php
	class Utils
	{
		public static function timeFromSeconds($seconds, $lit = FALSE)
		{
			$hours = floor($seconds / 3600);
			$mins = floor($seconds / 60 % 60);
			$secs = floor($seconds % 60);
			if (!$lit)
				return sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
			else
				return sprintf('%dh%dmn', $hours, $mins, $secs);
		}
	}
?>