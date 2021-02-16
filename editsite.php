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
	
	if (isset($_REQUEST['sites']))
	{
	    foreach ($lgfr->getSites() as $site)
		    echo $site.",";

		exit(0);
		return;
	}
	else if (isset($_REQUEST['site']))
	{
    	$site = htmlspecialchars(urldecode($_REQUEST['site']));
	    $site = $lgfr->getInfoSite($site);
        echo $site->nom.",".$site->latitude.",".$site->longitude.",".$site->altitude;

		exit(0);
		return;
	}
	else if (isset($_REQUEST['action']))
	{
    	if (isset($_REQUEST['nom']) && isset($_REQUEST['newnom']) && isset($_REQUEST['lat']) && isset($_REQUEST['lon']) && isset($_REQUEST['alt']))
    	{
    	    $site = htmlspecialchars(urldecode($_REQUEST['nom']));
    	    $newnom = htmlspecialchars(urldecode($_REQUEST['newnom']));
    	    if ($_REQUEST['action'] == 'delete')
    	    {
    	    	$ret = $lgfr->deleteSite($site);
    		    if ($ret)
    		    	echo "OK";
    	    }
    	    else
    	    {
    	        if ($_REQUEST['action'] == 'create')
    	    	    $ret = $lgfr->createSite($site);
    	        $ret = $lgfr->editSite($site, $newnom, $_REQUEST['lat'], $_REQUEST['lon'], $_REQUEST['alt']);
    		    if ($ret)
    		    	echo "OK";
    	    }
    	}
		exit(0);
		return;
	}

?>

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

<script type="text/javascript">

    window.onload = function()
    {
        window.frmsite = document.getElementsByName("site")[0];
        getSiteList();
    };
    
    function message(mesg)
    {
        document.getElementsByName("infobox")[0].innerHTML = mesg;
    }
    
    function clearList()
    {
        let length = frmsite.options.length;
        for (let i=length; i>=0; i--)
            frmsite.options[i] = null;
        addOption('Nouveau...', -1, false);
    }
    
    function addOption(nom, value, selected)
    {
        var option = document.createElement("option");
        option.text = nom;
        option.value = value;
        if (selected)
            option.selected = true;
        frmsite.add(option);
    }
    
    function getSiteList(nom)
    {
        var xhttp = new XMLHttpRequest();
        message("loading...");
        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                clearList();
                let sites = this.responseText.split(',');
                for (let i=0; i<sites.length; i++)
                {
                    if (sites[i].trim() == '')
                        continue;
                    addOption(sites[i], sites[i], nom == sites[i]);
                }
                onSiteChange();
                message("");
            }
        };
        xhttp.open("GET", "<?php echo $_SERVER['REQUEST_URI'];?>?sites", true);
        xhttp.send();
    }
    
    function loadSite(name)
    {
        if (typeof(name) !== "string")
        {
            document.getElementsByName("nom")[0].value = "";
            document.getElementsByName("lat")[0].value = "";
            document.getElementsByName("lon")[0].value = "";
            document.getElementsByName("alt")[0].value = "";
            return;
        }
        var xhttp = new XMLHttpRequest();
        message("loading...");
        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                let sites = this.responseText.split(',');
                document.getElementsByName("nom")[0].value = sites[0];
                document.getElementsByName("lat")[0].value = sites[1];
                document.getElementsByName("lon")[0].value = sites[2];
                document.getElementsByName("alt")[0].value = sites[3];
                message("");
            }
        };
        xhttp.open("GET", "<?php echo $_SERVER['REQUEST_URI'];?>?site="+name, true);
        xhttp.send();
    }
    
    function onSiteChange()
    {
        if (frmsite.value == -1)
            loadSite();
        else
            loadSite(frmsite.value);
    }
    
    function onsubmitSite(del, delconfirm)
    {
        let action = 'create';
        if (del == true)
        {
            if (delconfirm != true)
            {
                if (confirm("êtes vous sûr???"))
                    return onsubmitSite(del, true);
                else
                    return false;
            }
            action = 'delete';
        }
        else if (frmsite.value != -1)
            action = 'update';
        var xhttp = new XMLHttpRequest();
        message("loading...");
        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                if (this.responseText != "OK")
                    alert(this.responseText);
                else
                    getSiteList(newnom);
                message("");
            }
        };
        let nom = frmsite.value;
        let newnom = document.getElementsByName("nom")[0].value;
        let lat = document.getElementsByName("lat")[0].value;
        let lon = document.getElementsByName("lon")[0].value;
        let alt = document.getElementsByName("alt")[0].value;
        xhttp.open("GET", "<?php echo $_SERVER['REQUEST_URI'];?>?action="+action+"&nom="+encodeURIComponent(nom)+"&newnom="+encodeURIComponent(newnom)+"&lat="+encodeURIComponent(lat)+"&lon="+encodeURIComponent(lon)+"&alt="+encodeURIComponent(alt), true);
        xhttp.send();
        return false;
    }
</script>

</head>
<body>
    
<form action="<?php echo $_SERVER['REQUEST_URI'];?>" name="formvol" method="post" onsubmit="return onsubmitSite();">
 <p>Site : <select name="site" onchange="onSiteChange(this);"></select><span name="infobox"></span>
</p>
 <p>Nom : <input type="text" name="nom" /></p>
 <p>Latitude : <input type="text" name="lat" /></p>
 <p>Longitude : <input type="text" name="lon" /></p>
 <p>Altitude : <input type="text" name="alt" /></p>
 <p><input type="submit" value="OK"><input type="button" value="delete" onclick="onsubmitSite(true);"></p>
</form>


</body>
</html>