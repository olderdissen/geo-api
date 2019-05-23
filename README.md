# geo-api
simple open streeet map geo server with mysql database

## requirements

+ apache
+ php
+ mysql

## configuration

apache need some extra configuration. mod_alias need to be enabled. some alias definition need to be made:

```txt
	<IfModule mod_alias.c>
		Alias /api /var/www/geo-api.php
		Alias /apimap /var/www/geo-api.php
	</IfModule>
```
