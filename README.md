# logfly-web
Visualiseur/editeur rudimentaire de carnet LogFly

# installation
Placer tous les fichiers à la racine du répertoire web où on veut placer le visualisateur, y placer aussi le fichier Logfly.db

Le serveur web doit disposer du [module PHP permettant de lire les bases de données SQLite](https://www.php.net/manual/fr/book.sqlite3.php)

# sécurité
Pour protéger en écriture le carnet de vol on peut placer un fichier ".htaccess" à la racine du répertoire LogFly et y mettre le contenu suivant :
```ApacheConf
<FilesMatch "^(admin|edit).*\.php$">
AuthUserFile /le/chemin/absolu/vers/le/fichier/de/mots/de/passes/.htpasswd
AuthName "prive"
AuthType Basic
Require valid-user
Options +Indexes
</FilesMatch>
```
(Bien penser à changer le chemin vers le fichier htpasswd! Pour plus d'informations sur le fichier htpasswd se renseigner sur la marche à suivre sur google, exemple : https://stackoverflow.com/a/5229803)

Ainsi les fichiers edit.php, upload.php et editsite.php seront protégés par un mot de passe.

# Credits
visualisation GPX sur leaflet : https://github.com/mpetazzoni/leaflet-gpx

# Historique
13/10/2020		V0.1
