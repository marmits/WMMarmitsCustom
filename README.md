## Extension pour MediaWiki
Fork de l'extension :  
[Extension:LastModified](https://www.mediawiki.org/wiki/Extension:LastModified)

### Informations
Cette extension rend privées certaines ressources.  
Plusieurs Hook sont exploités. (voir section Hooks dans `extension.json`)

### Description
+ Génère des horodatages de dernière modification pour les pages (Hook d'origine)
+ Protège l'accès à certaines pages
  ```
  Spécial
  MediaWiki
  Catégorie:Private
  ```
+ Bloque l'accès à l'api pour les anonymes à l'exception d'une liste d'urls définies (ressources authorisées)
+ Custom le footer via l'api et les ressources authorisées
  - Ajoute la date de la 1ère création (création du wiki)
  - Ajoute la date de la dernière modification dans le wiki
+ Supprime le lien discussion de la page
+ Supprime le lien voir source de la page
+ Protège l'accès à l'information de la page
+ Ajout d'un fichier log custom pour `authentification failed` (`$wgMarmitsCustomPathLogFileFailed` =>     fichier pour enregistrer les mauvaises authentifications et garde l'addresse IP reponsable / par défaut le  fichier `failed_auth.log` est créé).  
Peut être utlisé par fail2Ban pour bannir les ips contenu dans ce fichier.

Ressources api authorisées et utlisées
```
/w/api.php?action=query&list=logevents&lelimit=1&ledir=older&format=json
/w/api.php?action=query&list=logevents&lelimit=1&ledir=newer&format=json
 ```

### Requirement
Testé sur [MediaWiki 1.43 (LTS)](https://www.mediawiki.org/wiki/MediaWiki_1.43)

### Installation
* Dans répertoire `extensions` :  
`git clone https://github.com/marmits/WMMarmitsCustom.git`
* Dans `LocalSettings.php` ajouter :   
`wfLoadExtension( 'WMMarmitsCustom' );`

### Paramètres  
#### 1. Pour désactiver le hook d'origine de 'LastModified' :   
Dans `LocalSettings.php` ajouter :  
`$wgMarmitsCustomRange = -1;`

  Si le hook LastModified est activé :  
  custom voir `$wgLastModifiedRange` dans doc  
  `$wgMarmitsCustomRange` = `$wgLastModifiedRange`  
  [Extension:LastModified](https://www.mediawiki.org/wiki/Extension:LastModified)

#### 2. Pour désactiver l'affichage des dates de création et denière modification du wiki dans le footer.   
Dans `LocalSettings.php` ajouter :  
`$wgMarmitsCustomInfoDate = 0;`

Modifier le CSS -> ajouter dans `MediaWiki:Common.css`  
```
#marmitswikicreatedweb{
	color:#36c;
}
#marmitswikicreatedmobile{
	display:inline-block;
}
```
#### 3. Pour définir un fichier de log personnalisé pour authentification failed (Peut être exploité par Fail2Ban)
Dans `LocalSettings.php` ajouter :  
`$wgMarmitsCustomPathLogFileFailed = "/path/to/mediawiki/failed_auth.log";`

Exemple Fail2Ban:
```
/etc/fail2ban/jail.local
[mediawiki-auth]
backend = auto
enabled  = true
filter   = z_mediawiki-bruteforce
logpath  = /path/to/mediawiki/failed_auth.log
maxretry = 3
bantime  = 24h
findtime = 1h
port     = http,https
action   = %(action_mwl)s

/etc/fail2ban/filter.d/z_mediawiki-bruteforce.conf
[Definition]
failregex = ^Login failed for user '.*' from IP '<HOST>'$
ignoreregex =
```