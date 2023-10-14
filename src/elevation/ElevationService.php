<?php

class ElevationService
{
    const DEM_DEFAULT_PATH = './HGT';
    const HGT_SIZE = 3601;
    private $dem_path, $ilat, $ilon, $fp, $curfname, $tilesize;

    public function __construct($dempath = self::DEM_DEFAULT_PATH)
    {
      $this->dem_path = $dempath . "/";
    }

    public function __destruct()
    {
      @fclose($this->fp);
    }
    
    public function getOffset()
    {
      return 1/self::HGT_SIZE;
    }

    public function getElevation($lat, $lon)
    {
      if (!is_numeric($lat) || !is_numeric($lon))
        return -1;

      if ($this->getHgtFile($lat, $lon) < 0)
        return -2;

      //plutôt que intval http://stackoverflow.com/questions/6619377/how-to-get-whole-and-decimal-part-of-a-number
      $offsetx = intval((($lon - $this->ilon)) * self::HGT_SIZE);
      $offsety = intval((($this->maxlat-$lat)) * self::HGT_SIZE);
      $offset = 2*($offsety*self::HGT_SIZE + $offsetx);
      fseek($this->fp, intval($offset));
      $alti = fread($this->fp, 2);
      //fclose($this->fp);
      // DEBUG:
      //echo $offsetx."/".$offsety."(".$this->maxlat.")= @".$lat."/".$lon." @".intval($offset)."/".filesize($this->dem_path.$this->curfname)." in ".$this->curfname." : ".($alti != false?(ord($alti[0])<<8)+ord($alti[1])." m":"err")."\n";
      return (ord($alti[0])<<8)+ord($alti[1]);
    }

    function getHgtFile($lat, $lon)
    {
      $filename = $this->getFileName($lat, $lon);
      if ($this->curfname == $filename)
        return 1;
      if (!is_file($this->dem_path.$filename))
        return -1;
      $this->curfname = $filename;
      @fclose($this->fp);
      $this->fp = fopen($this->dem_path.$filename, 'r');
      $this->maxlat = $this->ilat + ((self::HGT_SIZE-1)/self::HGT_SIZE);
      return 0;
    }

    protected function getFileName($lat, $lon)
    {
      $this->ilat = intval($lat);
      $this->ilon = intval($lon);
      $hemlat = $this->ilat>0?'N':'S';
      $hemlon = $this->ilon>0?'E':'W';
      $this->ilat = abs($this->ilat);
      $this->ilon = abs($this->ilon);

      return $hemlat.str_pad($this->ilat,2,'0',STR_PAD_LEFT).$hemlon.str_pad($this->ilon,3,'0',STR_PAD_LEFT).".hgt";
    }
}
?>