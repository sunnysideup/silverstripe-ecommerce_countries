```.htaccess


### SILVERSTRIPE START ###

# ...
# all other stuff
# ...

        # Match URL with country code
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^(nz|au|gb|eu|us|zz|jp|cn)/?(.*)$ framework/main.php?url=$2&ecomlocale=$1 [QSA,NC,L]

        # Fallback to original rewrite-rule
        RewriteCond %{REQUEST_URI} ^(.*)$
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule .* framework/main.php?url=%1 [QSA]

### SILVERSTRIPE END ###

```
