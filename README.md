## Extension pour MediaWiki
Fork de l'extension :  
[Extension:LastModified](https://www.mediawiki.org/wiki/Extension:LastModified)

### Requirement
Testé sur [MediaWiki 1.43 (LTS)](https://www.mediawiki.org/wiki/MediaWiki_1.43)

### Installation
* Dans répertoire `extensions` :  
`git clone https://github.com/marmits/WMMarmitsCustom.git`
* Dans `LocalSettings.php` ajouter :   
`wfLoadExtension( 'WMMarmitsCustom' );`

### Paramètre  
Pour désactiver le hook d'origine de 'LastModified' :   
Dans `LocalSettings.php` ajouter :  
`$wgMarmitsCustomRange = -1;`

Si le hook LastModified est activé :  
custom voir `$wgLastModifiedRange` dans doc  
`$wgMarmitsCustomRange` = `$wgLastModifiedRange`  
[Extension:LastModified](https://www.mediawiki.org/wiki/Extension:LastModified)

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
  - Ajoute la date de la dernière modification (dernière modification) 
+ Supprime le lien discussion de la page 
+ Supprime le lien voir source de la page 
+ Protège l'accès à l'information de la page

