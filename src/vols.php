
<style>
    * {
        margin: 0px;
        padding: 0px;
        background-color: black;
    }
    a {
        border:solid 1px black;
    }
    a:hover {
        border-color: white;
    }
    img {
        max-width:64px;
        max-height:64px;
    }
    #imgzoom {
        display:none;
        position: absolute;left:0;top:0;
        max-width:1024px;
        max-height:1024px;
    }
</style>
<img id="imgzoom" onclick="this.style.display='none'">
<?php
include("logfilereader.php");
foreach ((new LogflyReader())->getRecords()->vols as $vol) {
    if (!$vol->igc)
        continue;
    $src = "Tracklogs".DIRECTORY_SEPARATOR.$vol->id.".png";
    if (!file_exists(realpath($src)))
        $src = "image.php?id=".$vol->id;
?><a href="trace.php?id=<?php echo $vol->id;?>" onclick="return click_image(this)"><img src="<?php echo $src;?>"></a><?php
}
?>
<script>
    var imgzoom = document.getElementById('imgzoom');
    function click_image(e) {
        imgzoom.src=  e.firstChild.src;
        imgzoom.style.top = window.scrollY;
        imgzoom.style.display = 'initial';
        return false;
    }
</script>