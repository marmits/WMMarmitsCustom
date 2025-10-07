MediaWiki configuré pour utiliser des URLs "jolies"
- http://example.com/wiki/Accueil
- http://example.com/wiki/article_name
- http://example.com/wiki/Catégorie:Private  

1. `LocalSettings.php` ajouter :
```php
$wgScriptPath = "/w";
$wgScriptExtension = ".php";
$wgArticlePath = "/wiki/$1";
$wgUsePathInfo = true;
```
2. vhost apache
```
<VirtualHost *:80>
    ServerName example.com
    DocumentRoot /var/www/html/root
     
    <Directory "/var/www/html/root">
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
    </Directory>

    # Alias pour MediaWiki
    Alias /w "/var/www/html/mediawiki-1.43.5"
    <Directory /var/www/html/mediawiki-1.43.5>
        Options Includes FollowSymlinks
        AllowOverride All
        Require all granted

        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^(.*)$ index.php [QSA,L]
    </Directory>

    <Directory "/var/www/html/mediawiki-1.43.5/images">
        AllowOverride All
        Require all granted
        Options -ExecCGI
        <FilesMatch "\.(php|phtml|php3|php4|php5|phps)$">
            Require all denied
        </FilesMatch>
        Header set X-Content-Type-Options "nosniff"
    </Directory>
</VirtualHost>
```

3. `.htaccess`
```
/var/www/html/root/.htaccess
RewriteEngine On
RewriteRule ^/?wiki(/.*)?$ %{DOCUMENT_ROOT}/w/index.php [L]
```