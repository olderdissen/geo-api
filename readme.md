# geo-api

simple open streeet map geo server with mysql database

## requirements

- apache
- php
- mysql

## configuration

apache need some extra configuration. **mod_alias** need to be enabled. some alias definition need to be made in apache configuration:

	<IfModule mod_alias.c>
		Alias /api /var/www/geo-api.php
		Alias /apimap /var/www/geo-api.php
	</IfModule>

geo-api.php needs access to mysql. the following tables need to be created:

- user (id)
- changeset (id, user, timestamp)
- node (id, version, changeset, lon, lat)
- node_tag(id, version, k, v)
- way (id, version, changeset)
- way_nd (id, version, ref)
- way_tag(id, version, k, v)
- relation (id, version, changeset)
- relation_member (id, version, type, ref, role)
- relation_tag (id, version, k, v)

