# logfly-web
Visualiseur/editeur rudimentaire de carnet LogFly

![alt text](https://github.com/spasutto/logfly-web/blob/main/img/mainscreen.jpg?raw=true)

# installation
Placer tous les fichiers à la racine du répertoire web où on veut placer le visualisateur, y placer aussi le fichier Logfly.db

Renommer le fichier _config.php en config.php

Le serveur web doit disposer du [module PHP permettant de lire les bases de données SQLite](https://www.php.net/manual/fr/book.sqlite3.php)

Pour pouvoir utiliser la cartographie IGN il faut éditer le fichier config.php pour remplacer la chaîne "VOTRECLEGEOPORTAIL" par votre clé API Géoportail.
De même, pour pouvoir utiliser la détermination du fuseau horaire IGC via [timezonedb.com](https://timezonedb.com/) il faut aussi remplacer la chaîne "CLETIMEZONEDB" par votre clé API timezonedb.

# sécurité
Pour protéger en écriture le carnet de vol on peut placer un fichier ".htaccess" à la racine du répertoire LogFly et y mettre le contenu suivant :
```ApacheConf
<FilesMatch "^(admin|edit|upload).*\.php$">
AuthUserFile /le/chemin/absolu/vers/le/fichier/de/mots/de/passes/.htpasswd
AuthName "prive"
AuthType Basic
Require valid-user
Options +Indexes
</FilesMatch>
```
(Bien penser à changer le chemin vers le fichier htpasswd! Pour plus d'informations sur le fichier htpasswd se renseigner sur la marche à suivre sur google, exemple : https://stackoverflow.com/a/5229803)

Ainsi les fichiers edit.php, upload.php et editsite.php seront protégés par un mot de passe.

# Gestion de la topographie (DEM)
Le visualisateur de trace supporte la topographie au format TIF ou HGT. Pour cela il faut télécharger les fichiers DEM et les mettre dans le dossier correspondant (.tif dans elevation/SRTM et .hgt dans elevation/HGT). Ensuite bien penser à renommer le fichier _config.php en config.php.

Par défaut le service utilisera la topographie sous format HGT, pour le changer il faut éditer le fichier config.php et changer la valeur de la constante ELEVATIONSERVICE en "elevation/getElevationSRTM.php"

#### Sources
 - HGT : http://viewfinderpanoramas.org/dem3.html#alps
 - SRTM : http://dwtkns.com/srtm/

# Credits
 - visualisation GPX sur leaflet : https://github.com/mpetazzoni/leaflet-gpx
 - couche de gestion de la topographie SRTM : https://github.com/bobosola/PHP-SRTM

# Historique
13/10/2020		V0.1
