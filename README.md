# logfly-web
Visualiseur/editeur rudimentaire de carnet LogFly

![alt text](https://github.com/spasutto/logfly-web/blob/main/img/mainscreen.jpg?raw=true)

# installation
:warning: Le serveur web doit disposer du [module PHP permettant de lire les bases de données SQLite](https://www.php.net/manual/fr/book.sqlite3.php)

 - Placer tous les fichiers à la racine du répertoire web où on veut placer le visualisateur, y placer aussi le fichier Logfly.db

 - Renommer le fichier _".htaccess.example"_ en _".htaccess"_ et l'éditer pour mettre à jour l'instruction `RewriteBase` qui représente le répertoire de la racine du site web : par exemple si votre carnet est installé sur http://www.example.net/logfly/ il faudra écrire
```ApacheConf
...
RewriteBase /logfly/
...
```
voir aussi la section [sécurité](-sécurité) pour configurer un accès protégé à la base pour éviter que n'importe qui puisse éditer les vols

 - Renommer le fichier _config.php en config.php

Pour pouvoir utiliser la cartographie IGN il faut éditer le fichier config.php pour remplacer la chaîne "VOTRECLEGEOPORTAIL" par votre clé API Géoportail. Pour la génération des aperçus de traces côté serveur on peut aussi spécifier une clé sans protection (par exemple sans referer) dans la constante CLEGEOPORTAIL2.
De même, pour pouvoir utiliser la détermination du fuseau horaire IGC via [timezonedb.com](https://timezonedb.com/) il faut aussi remplacer la chaîne "CLETIMEZONEDB" par votre clé API timezonedb.

Pour plus de rapidité à l'affichage il est recommandé d'extraire les traces IGC du fichier `LogFly.db`. Pour ce faire aller sur la page `admin.php` et cliquer sur le lien _extraire les fichiers igc de la base_

# sécurité
Pour protéger en écriture le carnet de vol on peut éditer le fichier ".htaccess" à la racine du répertoire LogFly et y rajouter le contenu suivant :
```ApacheConf
<FilesMatch "^(admin|edit|upload|download|comment).*\.php|.*\.db|.*\.zip$">
AuthUserFile /le/chemin/absolu/vers/le/fichier/de/mots/de/passes/.htpasswd
AuthName "prive"
AuthType Basic
Require valid-user
Options +Indexes
</FilesMatch>
```
(Bien penser à changer le chemin vers le fichier htpasswd! Pour plus d'informations sur le fichier htpasswd se renseigner sur la marche à suivre sur google, exemple : https://stackoverflow.com/a/5229803)

Ainsi les fichiers permettant de modifier ou récupérer la base LogFly seront protégés par un mot de passe.

# Gestion de la topographie (DEM)
Le visualisateur de trace supporte la topographie au format TIF ou HGT. Pour cela il faut télécharger les fichiers DEM et les mettre dans le dossier correspondant (.tif dans elevation/SRTM et .hgt dans elevation/HGT). Ensuite bien penser à renommer le fichier _config.php en config.php.

Par défaut le service utilisera la topographie sous format HGT, pour le changer il faut éditer le fichier config.php et changer la valeur de la constante ELEVATIONSERVICE en "elevation/getElevationSRTM.php"

#### Sources
 - HGT : http://viewfinderpanoramas.org/dem3.html#alps
 - SRTM : http://dwtkns.com/srtm/

# Credits
 - visualisation GPX sur leaflet : https://github.com/mpetazzoni/leaflet-gpx
 - couche de gestion de la topographie SRTM : https://github.com/bobosola/PHP-SRTM
 - bouton fullscreen modifié de https://github.com/Leaflet/Leaflet.fullscreen
 - calcul du score https://github.com/mmomtchev/igc-xc-score/


