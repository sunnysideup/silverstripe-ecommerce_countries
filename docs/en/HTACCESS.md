# Important

This module needs the following changes in your htaccess file

where xxxxx and yyyy (and other country codes) are replaced by the segments representing your country codes

```.htaccess
    # Match URL with country code
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(nz|au|gb|eu|us|yyyy|xxxx)/?(.*)$ framework/main.php?url=$2&ecomlocale=$1 [NC,L]

    # Fallback to original rewrite-rule
    RewriteCond %{REQUEST_URI} ^(.*)$
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule .* framework/main.php?url=%1 [QSA]
```
