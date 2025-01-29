## Extension pour MediaWiki
Fork de l'extension :  
https://www.mediawiki.org/wiki/Extension:LastModified
### Installation
* Dans répertoire `extensions` :  
`git clone https://github.com/marmits/WMMarmitsCustom.git`
* Dans `LocalSettings.php` ajouter :   
`wfLoadExtension( 'WMMarmitsCustom' );`

### Paramètre  
Pour désactiver le hook de LastModified :   
Dans `LocalSettings.php` ajouter :  
`$wgMarmitsCustomRange = -1;`

Si le hook LastModified est activé :  
custom voir `$wgLastModifiedRange` dans doc  
`$wgMarmitsCustomRange` = `$wgLastModifiedRange`  
https://www.mediawiki.org/wiki/Extension:LastModified


+ Génère des horodatages de dernière modification pour les pages (Hook d'origine)
+ Protège l'accès à certaines pages 
  ```
  Spécial
  MediaWiki
  Catégorie:Private
  ```
+ Custom le footer 
  - Ajoute la date de la 1ère création
  - Ajoute la date de la dernère modification
+ Supprime le lien discussion de la page 
+ Supprime le lien voir source de la page 
+ Protège l'accès à l'information de la page
