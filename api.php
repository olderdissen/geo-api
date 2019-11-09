<?
################################################################################
# copyright 2011 - 2019 by Markus Olderdissen
# free for private use or inspiration. 
# public use need written permission.
################################################################################

define("OSM_HOST", "192.168.147.166");
define("OSM_PORT", "3306");
define("OSM_USER", "root");
define("OSM_PASS", "34096");
define("OSM_NAME", "osm");

define("OSM_VERSION", 0.6);
define("OSM_GENERATOR", "geo.olderdissen.ro");

define("OSM_VERSION_MINIMUM", 0.6);
define("OSM_VERSION_MAXIMUM", 0.6);
define("OSM_AREA_MAXIMUM", 0.125);
define("OSM_TRACEPOINTS_PER_PAGE", 5000);
define("OSM_WAYNODES_MAXIMUM", 32767); # original: 2000
define("OSM_CHANGESETS_MAXIMUM_ELEMENTS", 50000);
define("OSM_TIMEOUT_SECONDS", 300);

# "select a.field from " . OSM_NAME . ".table as a where (a.field = 1);"

ini_set("max_execution_time", 180);

date_default_timezone_set("UTC");

#header_remove("X-Powered-By");

#check_login();

################################################################################
# this is not part of official api
################################################################################

if(preg_match("/api\/browse\/changeset\/(\d*)$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		header("Location: /?changeset=" . $id);
	}

################################################################################
# this is not part of official api
################################################################################

if(preg_match("/api\/browse\/note\/(\d*)$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		header("Location: /api/" . OSM_VERSION . "/notes/" . $id);
	}

################################################################################
# this is not part of official api
################################################################################

if(preg_match("/apimap$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	if($_SERVER["REQUEST_METHOD"] == "GET")
		header("Location: /api/" . OSM_VERSION . "/map?" . $_SERVER["QUERY_STRING"]);
	}

################################################################################
# this is not part of official api
################################################################################

if(preg_match("/api\/user\/(.*)$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $id) = $matches;


	list($id, $folder) = explode("/", $id, 2);

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			$uid = 0;

			if($query = $resource->query("select * from user where ((id = '" . $id . "') or (display_name = '" . $id . "') or (email = '" . $id . "'));"))
				{
				while($result = $query->fetch_object())
					$uid = $result->id;

				$query->free_result();
				}

			$resource->close();
			}

		if(strlen($folder) == 0)
			header("Location: /api/" . OSM_VERSION . "/user/" . $uid);
		else
			print($folder);
		}
	}

################################################################################
# this is not part of official api
################################################################################

if(preg_match("/api\/export\/(\w*)$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $k) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			$trans = array("node" => array(), "way" => array(), "relation" => array());

			foreach(array("node", "way", "relation") as $type)
				{
				if($query = $resource->query("select " . $type . ".* from " . $type . ", " . $type . "_tag where ((" . $type . ".id = " . $type . "_tag.id) and (" . $type . ".version = " . $type . "_tag.version) and (" . $type . "_tag.k = '" . $k . "') and (" . $type . ".visible = 'true'));"))
					{
					while($result = $query->fetch_object())
						$trans[$type][$result->id] = $result->version;

					$query->free_result();
					}
				}

			foreach($trans["way"] as $id => $version)
				{
				if($query = $resource->query("select node.* from node, way_nd where ((way_nd.id = " . $id . ") and (way_nd.version = " . $version . ") and (way_nd.ref = node.id) and (node.visible = 'true'));"))
					{
					while($result = $query->fetch_object())
						$trans["node"][$result->id] = $result->version;

					$query->free_result();
					}
				}

			foreach(array("node", "way", "relation") as $type)
				ksort($trans[$type]);

			foreach(array("node", "way", "relation") as $type)
				{
				foreach($trans[$type] as $id => $version)
					{
					if($query = $resource->query("select " . $type . ".*, user.id as uid, user.display_name as user from " . $type . ", changeset, user where ((" . $type . ".id = " . $id . ") and (" . $type . ".version = " . $version . ") and (changeset.id = " . $type . ".changeset) and (user.id = changeset.uid));"))
						{
						while($result = $query->fetch_object())
							{
							$node = $osm->addChild($type);

							if($type == "node")
								foreach(array("id", "visible", "version", "changeset", "timestamp", "user", "uid", "lat", "lon") as $key)
									$node->addAttribute($key, $result->$key);
							else
								foreach(array("id", "visible", "version", "changeset", "timestamp", "user", "uid") as $key)
									$node->addAttribute($key, $result->$key);

							if($type == "way")
								{
								if($query_way_nd = $resource->query("select * from way_nd where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by z asc;"))
									{
									while($result_way_nd = $query_way_nd->fetch_object())
										{
										$nd = $node->addChild("nd");

										foreach(array("ref") as $key)
											$nd->addAttribute($key, $result_way_nd->$key);
										}

									$query_way_nd->free_result();
									}
								}

							if($type == "relation")
								{
								if($query_relation_member = $resource->query("select * from relation_member where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by z asc;"))
									{
									while($result_relation_member = $query_relation_member->fetch_object())
										{
										$member = $node->addChild("member");

										foreach(array("type", "ref", "role") as $key)
											$member->addAttribute($key, $result_relation_member->$key);
										}

									$query_relation_member->free_result();
									}
								}

							if($query_tag = $resource->query("select * from " . $type . "_tag where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by k asc;"))
								{
								while($result_tag = $query_tag->fetch_object())
									{
									$tag = $node->addChild("tag");

									foreach(array("k", "v") as $key)
										$tag->addAttribute($key, $result_tag->$key);
									}

								$query_tag->free_result();
								}
							}

						$query->free_result();
						}
					}
				}
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# this is not part of official api
################################################################################

if(preg_match("/api\/export\/(\w*)\/(\w*)$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $k, $v) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			$trans = array("node" => array(), "way" => array(), "relation" => array());

			foreach(array("node", "way", "relation") as $type)
				{
				if($query = $resource->query("select " . $type . ".* from " . $type . ", " . $type . "_tag where ((" . $type . ".id = " . $type . "_tag.id) and (" . $type . ".version = " . $type . "_tag.version) and (" . $type . "_tag.k = '" . $k . "') and (" . $type . "_tag.v = '" . $v . "') and (" . $type . ".visible = 'true'));"))
					{
					while($result = $query->fetch_object())
						$trans[$type][$result->id] = $result->version;

					$query->free_result();
					}
				}

			foreach($trans["way"] as $id => $version)
				{
				if($query = $resource->query("select node.* from node, way_nd where ((way_nd.id = " . $id . ") and (way_nd.version = " . $version . ") and (way_nd.ref = node.id) and (node.visible = 'true'));"))
					{
					while($result = $query->fetch_object())
						$trans["node"][$result->id] = $result->version;

					$query->free_result();
					}
				}

			foreach(array("node", "way", "relation") as $type)
				ksort($trans[$type]);

			foreach(array("node", "way", "relation") as $type)
				{
				foreach($trans[$type] as $id => $version)
					{
					if($query = $resource->query("select " . $type . ".*, user.id as uid, user.display_name as user from " . $type . ", changeset, user where ((" . $type . ".id = " . $id . ") and (" . $type . ".version = " . $version . ") and (changeset.id = " . $type . ".changeset) and (user.id = changeset.uid));"))
						{
						while($result = $query->fetch_object())
							{
							$node = $osm->addChild($type);

							if($type == "node")
								{
								foreach(array("id", "visible", "version", "changeset", "timestamp", "user", "uid", "lat", "lon") as $key)
									$node->addAttribute($key, $result->$key);
								}
							else
								{
								foreach(array("id", "visible", "version", "changeset", "timestamp", "user", "uid") as $key)
									$node->addAttribute($key, $result->$key);
								}

							if($type == "way")
								{
								if($query_way_nd = $resource->query("select * from way_nd where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by z asc;"))
									{
									while($result_way_nd = $query_way_nd->fetch_object())
										{
										$nd = $node->addChild("nd");

										foreach(array("ref") as $key)
											$nd->addAttribute($key, $result_way_nd->$key);
										}

									$query_way_nd->free_result();
									}
								}

							if($type == "relation")
								{
								if($query_relation_member = $resource->query("select * from relation_member where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by z asc;"))
									{
									while($result_relation_member = $query_relation_member->fetch_object())
										{
										$member = $node->addChild("member");

										foreach(array("type", "ref", "role") as $key)
											$member->addAttribute($key, $result_relation_member->$key);
										}

									$query_relation_member->free_result();
									}
								}

							if($query_tag = $resource->query("select * from " . $type . "_tag where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by k asc;"))
								{
								while($result_tag = $query_tag->fetch_object())
									{
									$tag = $node->addChild("tag");

									foreach(array("k", "v") as $key)
										$tag->addAttribute($key, $result_tag->$key);
									}

								$query_tag->free_result();
								}
							}

						$query->free_result();
						}
					}
				}
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# this is not part of official api
################################################################################

if(preg_match("/api\/(\d*)\/(\d*)\/(\d*)$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $z, $x, $y) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		if(isset($_GET["dirty"]) === true)
			exec("sudo php tile.php " . $z . " " . $x . " " . $y . " > /dev/null");

		if(file_exists("tiles/" . $z . "/" . $x . "/" . $y . ".png") === false)
			$data = file_get_contents("index.png");
		else
			$data = file_get_contents("tiles/" . $z . "/" . $x . "/" . $y . ".png");

		header("Content-Type: image/png");
		header("Content-Length: " . strlen($data));

		print($data);
		}
	}

################################################################################
# 2.1.1 Capabilities: GET /api/capabilities
################################################################################
# This API call is meant to provide information about the capabilities and limitations of the current API.

if(preg_match("/api\/capabilities$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		$api = $osm->addChild("api");

		$version = $api->addChild("version");
		$version->addAttribute("minimum", OSM_VERSION_MINIMUM);
		$version->addAttribute("maximum", OSM_VERSION_MAXIMUM);

		$area = $api->addChild("area");
		$area->addAttribute("maximum", OSM_AREA_MAXIMUM);

		$tracepoints = $api->addChild("tracepoints");
		$tracepoints->addAttribute("per_page", OSM_TRACEPOINTS_PER_PAGE);

		$waynodes = $api->addChild("waynodes");
		$waynodes->addAttribute("maximum", OSM_WAYNODES_MAXIMUM);

		$changesets = $api->addChild("changesets");
		$changesets->addAttribute("maximum_elements", OSM_CHANGESETS_MAXIMUM_ELEMENTS);

		$timeout = $api->addChild("timeout");
		$timeout->addAttribute("seconds", OSM_TIMEOUT_SECONDS);

		$status = $api->addChild("status");
		$status->addAttribute("database", "online"); # online | readonly | offline
		$status->addAttribute("api", "online"); # online | readonly | offline
		$status->addAttribute("gpx", "online"); # online | readonly | offline

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# Note that the URL is versionless.
# For convenience, the server supports the request /api/0.6/capabilities too, such that clients can use the same URL prefix http:/.../api/0.6 for all requests.
################################################################################

if(preg_match("/api\/0\.6\/capabilities$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	header("Location: /api/capabilities");
	}

################################################################################
# 2.1.2 Retrieving map data by bounding box: GET /api/0.6/map
################################################################################

if(preg_match("/api\/0\.6\/map$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null) = $matches;

	$microtime = microtime(true);

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		check_login();

		if((isset($_GET["bbox"]) === false) || (strlen($_GET["bbox"]) == 0) || (count(explode(",", $_GET["bbox"])) != 4))
			exception_bbox_missed();

		list($min_lon, $min_lat, $max_lon, $max_lat) = explode(",", $_GET["bbox"]);

		if(check_bbox_range($min_lon, $min_lat, $max_lon, $max_lat) === false)
			exception_bbox_range();

		if(check_bbox_area($min_lon, $min_lat, $max_lon, $max_lat) === false)
			exception_bbox_area();

		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		$bounds = $osm->addChild("bounds");
		$bounds->addAttribute("minlat", $min_lat);
		$bounds->addAttribute("minlon", $min_lon);
		$bounds->addAttribute("maxlat", $max_lat);
		$bounds->addAttribute("maxlon", $max_lon);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			$trans = array("node" => array(), "way" => array(), "relation" => array());

			if($query = $resource->query("select * from node where ((" . bboxs($min_lon, $min_lat, $max_lon, $max_lat) . ") and (visible = 'true'));"))
				{
				while($result = $query->fetch_object())
					$trans["node"][$result->id] = $result->version;

				$query->free_result();
				}

			foreach($trans["node"] as $id => $version)
				{
				if($query = $resource->query("select way.* from way, way_nd where ((way_nd.id = way.id) and (way_nd.version = way.version) and (way_nd.ref = " . $id . ") and (way.visible = 'true'));"))
					{
					while($result = $query->fetch_object())
						$trans["way"][$result->id] = $result->version;

					$query->free_result();
					}
				}

			foreach($trans["way"] as $id => $version)
				{
				if($query = $resource->query("select node.* from node, way_nd where ((way_nd.id = " . $id . ") and (way_nd.version = " . $version . ") and (way_nd.ref = node.id) and (node.visible = 'true'));"))
					{
					while($result = $query->fetch_object())
						$trans["node"][$result->id] = $result->version;

					$query->free_result();
					}
				}

			# Error: You requested too many nodes (limit is 50000). Either request a smaller area, or use planet.osm

			foreach(array("node", "way") as $type)
				{
				foreach($trans[$type] as $id => $version)
					{
					if($query = $resource->query("select relation.* from relation, relation_member where ((relation.id = relation_member.id) and (relation.version = relation_member.version) and (relation_member.type = '" . $type . "') and (relation_member.ref = " . $id . ") and (relation.visible = 'true'));"))
						{
						while($result = $query->fetch_object())
							$trans["relation"][$result->id] = $result->version;

						$query->free_result();
						}
					}
				}

			foreach(array("node", "way", "relation") as $type)
				ksort($trans[$type]);

			foreach(array("node", "way", "relation") as $type)
				{
				foreach($trans[$type] as $id => $version)
					{
					if($query = $resource->query("select " . $type . ".*, user.id as uid, user.display_name as user from " . $type . ", changeset, user where ((" . $type . ".id = " . $id . ") and (" . $type . ".version = " . $version . ") and (changeset.id = " . $type . ".changeset) and (user.id = changeset.uid));"))
						{
						while($result = $query->fetch_object())
							{
							$node = $osm->addChild($type);

							if($type == "node")
								foreach(array("id", "visible", "version", "changeset", "timestamp", "user", "uid", "lat", "lon") as $key)
									$node->addAttribute($key, $result->$key);
							else
								foreach(array("id", "visible", "version", "changeset", "timestamp", "user", "uid") as $key)
									$node->addAttribute($key, $result->$key);

							if($type == "way")
								{
								if($query_way_nd = $resource->query("select * from way_nd where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by z asc;"))
									{
									while($result_way_nd = $query_way_nd->fetch_object())
										{
										$nd = $node->addChild("nd");

										foreach(array("ref") as $key)
											$nd->addAttribute($key, $result_way_nd->$key);
										}

									$query_way_nd->free_result();
									}
								}

							if($type == "relation")
								{
								if($query_relation_member = $resource->query("select * from relation_member where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by z asc;"))
									{
									while($result_relation_member = $query_relation_member->fetch_object())
										{
										$member = $node->addChild("member");

										foreach(array("type", "ref", "role") as $key)
											$member->addAttribute($key, $result_relation_member->$key);
										}

									$query_relation_member->free_result();
									}
								}

							if($query_tag = $resource->query("select * from " . $type . "_tag where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by k asc;"))
								{
								while($result_tag = $query_tag->fetch_object())
									{
									$tag = $node->addChild("tag");

									foreach(array("k", "v") as $key)
										$tag->addAttribute($key, $result_tag->$key);
									}

								$query_tag->free_result();
								}
							}

						$query->free_result();
						}
					}
				}

			$resource->close();
			}

#		$osm->addAttribute("time", microtime(true) - $microtime);

#		foreach(array("node", "way", "relation") as $key)
#			$osm->addAttribute($key . "s", count($osm->$key));

		$osm = $osm->asXML();

		header("Content-Disposition: inline; filename=\"map.osm\"");
		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.1.3 Retrieving permissions: GET /api/0.6/permissions
################################################################################
# Returns the permissions granted to the current API connection.

if(preg_match("/api\/0\.6\/permissions$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		$permissions = $osm->addChild("permissions");

#		$permission = $permissions->addChild("permission");
#		$permission->addAttribute("name", "allow_read_prefs");

#		$permission = $permissions->addChild("permission");
#		$permission->addAttribute("name", "allow_write_prefs");

#		$permission = $permissions->addChild("permission");
#		$permission->addAttribute("name", "allow_write_diary");

		$permission = $permissions->addChild("permission");
		$permission->addAttribute("name", "allow_write_api");

#		$permission = $permissions->addChild("permission");
#		$permission->addAttribute("name", "allow_read_gpx");

#		$permission = $permissions->addChild("permission");
#		$permission->addAttribute("name", "allow_write_gpx");

		$permission = $permissions->addChild("permission");
		$permission->addAttribute("name", "allow_write_notes");

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.2.2 Create: PUT /api/0.6/changeset/create
################################################################################
# The payload of a changeset creation request has to be one or more changeset elements optionally including an arbitrary number of tags.

if(preg_match("/api\/0\.6\/changeset\/create$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "PUT")
		{
		check_login();

		$data = file_get_contents("php://input");

		$osm = new SimpleXMLElement($data);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			$uid = 0;

			if($query = $resource->query("select * from user where ((id = '" . $_SERVER["PHP_AUTH_USER"] . "') or (display_name = '" . $_SERVER["PHP_AUTH_USER"] . "') or (email = '" . $_SERVER["PHP_AUTH_USER"] . "'));"))
				{
				while($result = $query->fetch_object())
					$uid = $result->id;

				$query->free_result();
				}

			$id = 0;

			if($query = $resource->query("select max(id) as id from changeset;"))
				{
				while($result = $query->fetch_object())
					$id = $result->id;

				$query->free_result();
				}

			$id = $id + 1;

			$resource->query("insert into changeset (id, uid, created_at) values (" . $id . ", " . $uid . ", '" . date("Y-m-d\TH:i:s\Z") . "');");

			foreach($osm->changeset as $changeset)
				foreach($changeset->tag as $tag)
					$resource->query("insert into changeset_tag (id, k, v) values (" . $id . ", '" . $tag["k"] . "', '" . str_replace("'", "\'", $tag["v"]) . "');");

			$resource->close();
			}

		header("Content-Type: text/plain");

		print($id);
		}

#	header($_SERVER["SERVER_PROTOCOL"] . "/1.1 403 Forbidden");
#	header("Content-Type: text/plain");
#	print("Your access to the API has been blocked. Please log-in to the web interface to find out more.")
	}

################################################################################
# 2.2.3 Read: GET /api/0.6/changeset/#id
################################################################################
# Returns the changeset with the given id in OSM-XML format.

if(preg_match("/api\/0\.6\/changeset\/(\d*)$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			if($query_changeset = $resource->query("select changeset.*, if(changeset.closed_at is null, 'true', 'false') as open, user.display_name as user from changeset, user where ((user.id = changeset.uid) and (changeset.id = " . $id . "));"))
				{
				while($result_changeset = $query_changeset->fetch_object())
					{
					$changeset = $osm->addChild("changeset");

					foreach(array("id", "user", "uid", "created_at", "open", "closed_at", "min_lat", "min_lon", "max_lat", "max_lon") as $key)
						{
						if($result_changeset->$key == null)
							continue;

						$changeset->addAttribute($key, $result_changeset->$key);
						}

					$changeset->addAttribute("comments_count", 0);

					if($query_changeset_tag = $resource->query("select * from changeset_tag where (id = " . $result_changeset->id . ") order by k asc;"))
						{
						while($result_changeset_tag = $query_changeset_tag->fetch_object())
							{
							$tag = $changeset->addChild("tag");

							foreach(array("k", "v") as $key)
								$tag->addAttribute($key, $result_changeset_tag->$key);
							}

						$query_changeset_tag->free_result();
						}

					if((isset($_GET["include_discussion"]) === true) && (strlen($_GET["include_discussion"]) != 0))
						{
						$discussion = $changeset->addChild("discussion");

						if($query_changeset_comment = $resource->query("select changeset_comment.*, user.display_name as user from changeset_comment, user where ((user.id = changeset_comment.uid) and (changeset_comment.z = " . $result_changeset->id . ")) order by changeset_comment.date asc;"))
							{
							while($result_changeset_comment = $query_changeset_comment->fetch_object())
								{
								$comment = $discussion->addChild("comment");

								foreach(array("date", "uid", "user") as $key)
									$comment->addAttribute($key, $result_changeset_comment->$key);

								$comment->addChild("text", $result_changeset_comment->text);
								}

							$query_changeset_comment->free_result();
							}
						}
					}

				$query_changeset->free_result();
				}

			$resource->close();
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.2.4 Update: PUT /api/0.6/changeset/#id
################################################################################
# For updating tags on the changeset, e.g. changeset comment=foo.

if(preg_match("/api\/0\.6\/changeset\/(\d*)$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "PUT")
		{
		check_login();

		$data = file_get_contents("php://input");

		$osm = new SimpleXMLElement($data);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			$resource->query("delete from changeset_tag where (id = " . $id . ");");

			foreach($osm->changeset as $changeset)
				foreach($changeset->tag as $tag)
					$resource->query("insert into changeset_tag (id, k, v) values (" . $id . ", '" . $tag["k"] . "', '" . str_replace("'", "\'", $tag["v"]) . "');");

			$resource->close();
			}
		}
	}

################################################################################
# 2.2.5 Close: PUT /api/0.6/changeset/#id/close
################################################################################
# Closes a changeset.

if(preg_match("/api\/0\.6\/changeset\/(\d*)\/close$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "PUT")
		{
		check_login();

		$got_pos = false;

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			$min_lon = 0 + 180;
			$min_lat = 0 +  90;
			$max_lon = 0 - 180;
			$max_lat = 0 -  90;

			if($query = $resource->query("select * from node where (changeset = " . $id . ");"))
				{
				while($result = $query->fetch_object())
					{
					if($query_node = $resource->query("select * from node where ((id = " . $result->id . ") and (version = " . $result->version . "));"))
						{
						while($result_node = $query_node->fetch_object())
							{
							$got_pos = true;

							$min_lon = min($result_node->lon, $min_lon);
							$min_lat = min($result_node->lat, $min_lat);
							$max_lon = max($result_node->lon, $max_lon);
							$max_lat = max($result_node->lat, $max_lat);
							}

						$query_node->free_result();
						}
					}

				$query->free_result();
				}

			if($query = $resource->query("select * from way where (changeset = " . $id . ");"))
				{
				while($result = $query->fetch_object())
					{
					if($query_way = $resource->query("select * from way where ((id = " . $result->id . ") and (version in (" . $result->version . ", " . ($result->version - 1) . ")));"))
						{
						while($result_way = $query_way->fetch_object())
							{
							if($query_way_nd = $resource->query("select * from way_nd where ((id = " . $result_way->id . ") and (version = " . $result_way->version . "));"))
								{
								while($result_way_nd = $query_way_nd->fetch_object())
									{
									if($query_node = $resource->query("select * from node where ((id = " . $result_way_nd->ref . ") and (changeset <= " . $result_way->changeset . ")) order by changeset desc limit 1;"))
										{
										while($result_node = $query_node->fetch_object())
											{
											$got_pos = true;

											$min_lon = min($result_node->lon, $min_lon);
											$min_lat = min($result_node->lat, $min_lat);
											$max_lon = max($result_node->lon, $max_lon);
											$max_lat = max($result_node->lat, $max_lat);
											}

										$query_node->free_result();
										}
									}

								$query_way_nd->free_result();
								}
							}

						$query_way->free_result();
						}
					}

				$query->free_result();
				}

			if($query = $resource->query("select * from relation where (changeset = " . $id . ");"))
				{
				while($result = $query->fetch_object())
					{
					if($query_relation = $resource->query("select * from relation where ((id = " . $result->id . ") and (version = " . $result->version . "));"))
						{
						while($result_relation = $query_relation->fetch_object())
							{
							if($query_relation_member = $resource->query("select * from relation_member where ((id = " . $result_relation->id . ") and (version = " . $result_relation->version . "));"))
								{
								while($result_relation_member = $query_relation_member->fetch_object())
									{
									if($result_relation_member->type == "node")
										{
										if($query_node = $resource->query("select * from node where ((id = " . $result_relation_member->ref . ") and (changeset <= " . $result_relation->changeset . ")) order by changeset desc limit 1;"))
											{
											while($result_node = $query_node->fetch_object())
												{
												$got_pos = true;

												$min_lon = min($result_node->lon, $min_lon);
												$min_lat = min($result_node->lat, $min_lat);
												$max_lon = max($result_node->lon, $max_lon);
												$max_lat = max($result_node->lat, $max_lat);
												}

											$query_node->free_result();
											}
										}

									if($result_relation_member->type == "way")
										{
										if($query_way = $resource->query("select * from way where ((id = " . $result_relation_member->ref . ") and (changeset <= " . $result_relation->changeset . ")) order by changeset desc limit 1;"))
											{
											while($result_way = $query_way->fetch_object())
												{
												if($query_way_nd = $resource->query("select * from way_nd where ((id = " . $result_way->id . ") and (version = " . $result_way->version . "));"))
													{
													while($result_way_nd = $query_way_nd->fetch_object())
														{
														if($query_node = $resource->query("select * from node where ((id = " . $result_way_nd->ref . ") and (changeset <= " . $result_way->changeset . ")) order by changeset desc limit 1;"))
															{
															while($result_node = $query_node->fetch_object())
																{
																$got_pos = true;

																$min_lon = min($result_node->lon, $min_lon);
																$min_lat = min($result_node->lat, $min_lat);
																$max_lon = max($result_node->lon, $max_lon);
																$max_lat = max($result_node->lat, $max_lat);
																}

															$query_node->free_result();
															}
														}

													$query_way_nd->free_result();
													}
												}

											$query_way->free_result();
											}
										}

									if($result_relation_member->type == "relation")
										{
										# noone cares this
										}
									}

								$query_relation_member->free_result();
								}
							}

						$query_relation->free_result();
						}
					}

				$query->free_result();
				}

			if($got_pos)
				foreach(array("min_lat" => $min_lat, "min_lon" => $min_lon, "max_lat" => $max_lat, "max_lon" => $max_lon) as $key => $value)
					$resource->query("update changeset set " . $key . " = " . $value . " where (id = " . $id . ");");

			$resource->query("update changeset set closed_at = '" . date("Y-m-d\TH:i:s\Z") . "' where (id = " . $id . ");");

			$resource->close();
			}

		################################################################################
		# trigger external tile creation
		################################################################################

		if($got_pos)
			exec("sudo php tile.php " . $id . " > /dev/null &");
		}
	}

################################################################################
# 2.2.6 Download: GET /api/0.6/changeset/#id/download
################################################################################
# Returns the OsmChange document describing all changes associated with the changeset.

if(preg_match("/api\/0\.6\/changeset\/(\d*)\/download$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		$osm = new SimpleXMLElement("<osmChange />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		$action_create = $osm->addChild("create");
		$action_modify = $osm->addChild("modify");
		$action_delete = $osm->addChild("delete");

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			foreach(array("node", "way", "relation") as $type)
				{
				if($query = $resource->query("select " . $type . ".*, user.id as uid, user.display_name as user from " . $type . ", changeset, user where ((changeset.id = " . $type . ".changeset) and (user.id = changeset.uid) and (" . $type . ".changeset = " . $id . "));"))
					{
					while($result = $query->fetch_object())
						{
						if($result->version == 1)
							$action_node = $action_create->addChild($type);
						elseif($result->visible == "false")
							$action_node = $action_delete->addChild($type);
						else
							$action_node = $action_modify->addChild($type);

						if($result->visible == "false") # nodes also contain lon/lat ???
							{
							foreach(array("id", "version", "changeset", "uid", "user", "visible", "timestamp") as $key)
								$action_node->addAttribute($key, $result->$key);

							continue;
							}

						if($type == "node")
							foreach(array("id", "version", "changeset", "uid", "user", "visible", "timestamp", "lat", "lon") as $key)
								$action_node->addAttribute($key, $result->$key);
						else
							foreach(array("id", "version", "changeset", "uid", "user", "visible", "timestamp") as $key)
								$action_node->addAttribute($key, $result->$key);

						if($type == "way")
							{
							if($query_way_nd = $resource->query("select * from way_nd where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by z asc;"))
								{
								while($result_way_nd = $query_way_nd->fetch_object())
									{
									$nd = $action_node->addChild("nd");

									foreach(array("ref") as $key)
										$nd->addAttribute($key, $result_way_nd->$key);
									}

								$query_way_nd->free_result();
								}
							}

						if($type == "relation")
							{
							if($query_relation_member = $resource->query("select * from relation_member where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by z asc;"))
								{
								while($result_relation_member = $query_relation_member->fetch_object())
									{
									$member = $action_node->addChild("member");

									foreach(array("type", "ref", "role") as $key)
										$member->addAttribute($key, $result_relation_member->$key);
									}

								$query_relation_member->free_result();
								}
							}

						if($query_tag = $resource->query("select * from " . $type . "_tag where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by k asc;"))
							{
							while($result_tag = $query_tag->fetch_object())
								{
								$tag = $action_node->addChild("tag");

								foreach(array("k", "v") as $key)
									$tag->addAttribute($key, $result_tag->$key);
								}

							$query_tag->free_result();
							}
						}

					$query->free_result();
					}

				}

			$resource->close();
			}

		foreach(array("create", "modify", "delete") as $action)
			{
			if(count($osm->$action->children()) != 0)
				continue;

			unset($osm->$action);
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.2.7 Expand Bounding Box: POST /api/0.6/changeset/#id/expand_bbox
################################################################################

if(preg_match("/api\/0\.6\/changeset\/(\d*)\/expand_bbox$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		check_login();

		$data = file_get_contents("php://input");

		$data = new SimpleXMLElement($data);

		$min_lon = 0 + 180;
		$min_lat = 0 +  90;
		$max_lon = 0 - 180;
		$max_lat = 0 -  90;

		foreach($data->node as $node)
			{
			$min_lon = min($node["lon"], $min_lon);
			$min_lat = min($node["lat"], $min_lat);
			$max_lon = max($node["lon"], $max_lon);
			$max_lat = max($node["lat"], $max_lat);
			}

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			foreach(array("min_lat" => $min_lat, "min_lon" => $min_lon, "max_lat" => $max_lat, "max_lon" => $max_lon) as $key => $value)
				$resource->query("update changeset set " . $key . " = " . $value . " where (id = " . $id . ");");

			$resource->close();
			}
		}
	}

################################################################################
# 2.2.8 Query: GET /api/0.6/changesets
################################################################################
# This is an API method for querying changesets.

if(preg_match("/api\/0\.6\/changesets$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		$search = array();

		if(isset($_GET["bbox"]) === true)
			{
			if((strlen($_GET["bbox"]) == 0) || (count(explode(",", $_GET["bbox"])) != 4))
				exception_bbox_missed();

			list($min_lon, $min_lat, $max_lon, $max_lat) = explode(",", $_GET["bbox"]);

			if(check_bbox_range($min_lon, $min_lat, $max_lon, $max_lat) === false)
				exception_bbox_range();

			$search[] = "((changeset.min_lon < " . $max_lon . ") and (changeset.min_lat < " . $max_lat . ") and (changeset.max_lon > " . $min_lon . ") and (changeset.max_lat > " . $min_lat . "))";
			}

		if(isset($_GET["user"]) === true)
			{
			if(strlen($_GET["user"]) == 0)
				exception_user_invalid();

			$search[] = "(changeset.uid = " . $_GET["user"] . ")"; # original api does not provide search by display_name
			}

		if(isset($_GET["time"]) === true)
			{
			if(strlen($_GET["time"]) == 0)
				exception_date_invalid();

			if(count(explode(",", $_GET["time"])) == 1)
				{
				list($time_a, $time_b) = explode(",", $_GET["time"] . "," . date("Y-m-d\TH:i:s\Z"));

				$search[] = "((changeset.closed_at > '" . date("Y-m-d\TH:i:s\Z", strtotime($time_a)) . "') and (changeset.created_at < '" . date("Y-m-d\TH:i:s\Z", strtotime($time_b)) . "'))";
				}

			if(count(explode(",", $_GET["time"])) == 2)
				{
				list($time_a, $time_b) = explode(",", $_GET["time"]);

				$search[] = "((changeset.closed_at > '" . date("Y-m-d\TH:i:s\Z", strtotime($time_a)) . "') and (changeset.created_at < '" . date("Y-m-d\TH:i:s\Z", strtotime($time_b)) . "'))";
				}
			}

		if(isset($_GET["open"]) === true)
			$search[] = "(changeset.closed_at is null)";

		if(isset($_GET["closed"]) === true)
			$search[] = "(changeset.closed_at is not null)";

		if(isset($_GET["changesets"]) === true)
			{
			if(strlen($_GET["changesets"]) == 0)
				exception_changeset_missed();

			$search[] = "(changeset.id in (" . $_GET["changesets"] . "))";
			}

		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			if($query_changeset = $resource->query("select changeset.*, if(changeset.closed_at is null, 'true', 'false') as open, user.display_name as user from changeset, user" . (count($search) == 0 ? " " : " where ((user.id = changeset.uid) and (" . implode(" and ", $search) . ")) ") . "order by changeset.id desc limit 100;"))
				{
				while($result_changeset = $query_changeset->fetch_object())
					{
					$changeset = $osm->addChild("changeset");

					foreach(array("id", "user", "uid", "created_at", "open", "closed_at", "min_lat", "min_lon", "max_lat", "max_lon") as $key)
						{
						if($result_changeset->$key == null)
							continue;

						$changeset->addAttribute($key, $result_changeset->$key);
						}

					if($query_changeset_tag = $resource->query("select * from changeset_tag where (id = " . $result_changeset->id . ") order by k asc;"))
						{
						while($result_changeset_tag = $query_changeset_tag->fetch_object())
							{
							$tag = $changeset->addChild("tag");

							foreach(array("k", "v") as $key)
								$tag->addAttribute($key, $result_changeset_tag->$key);
							}

						$query_changeset_tag->free_result();
						}
					}

				$query_changeset->free_result();
				}

			$resource->close();
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.2.9 Diff upload: POST /api/0.6/changeset/#id/upload
################################################################################
# With this API call files in the OsmChange format can be uploaded to the server.

# int.: multiple files can be uploaded. take care about new_id attributes !!!

if(preg_match("/api\/0\.6\/changeset\/(\d*)\/upload$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "POST")
		{
		check_login();

		$osm_change = file_get_contents("php://input");

		$diff = new SimpleXMLElement("<diffResult />");
		$diff->addAttribute("version", OSM_VERSION);
		$diff->addAttribute("generator", OSM_GENERATOR);

		$osm_change = new SimpleXMLElement($osm_change);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			foreach(array("node", "node_tag", "way", "way_nd", "way_tag", "relation", "relation_member", "relation_tag") as $type)
				{
#				$resource->query("lock tables " . $type . " write;");
				}

			$trans = array("node" => array(), "way" => array(), "relation" => array());

#			header($_SERVER["SERVER_PROTOCOL"] . " 412 Precondition failed");
#			header("Content-Type: text/plain; charset=utf-8");
#			header("Error: Node 0 is still used by way 0.");
#			die("Node 0 is still used by way 0.");

			foreach($osm_change as $action)
				{
				$action_name = $action->getName();

				foreach($action as $type)
					{
					$type_name = $type->getName();

					if($action_name == "create")
						{
						$id = 0;

						if($query = $resource->query("select max(id) as id from " . $type_name . ";"))
							{
							while($result = $query->fetch_object())
								$id = $result->id;

							$query->free_result();
							}

						$old_id = intval($type["id"]);
						$new_id = $id + 1;

						$old_version = 0;
						$new_version = 1;

						$diff_node = $diff->addChild($type_name);
						$diff_node->addAttribute("old_id", $old_id);
						$diff_node->addAttribute("new_id", $new_id);
						$diff_node->addAttribute("old_version", $old_version);
						$diff_node->addAttribute("new_version", $new_version);

						$type->addAttribute("visible", "true");

						$trans[$type_name][$old_id] = $new_id;
						}

					if($action_name == "delete")
						{
						$old_id = intval($type["id"]);
						$new_id = intval($type["id"]);

						$old_version = intval($type["version"]) + 0;
						$new_version = intval($type["version"]) + 1;

						$diff_node = $diff->addChild($type_name);
						$diff_node->addAttribute("old_id", $old_id);
						$diff_node->addAttribute("new_id", $new_id);
						$diff_node->addAttribute("old_version", $old_version);
						$diff_node->addAttribute("new_version", $new_version);

						$type->addAttribute("visible", "false"); # better to change after upload? will there be a new version after all?
						}

					if($action_name == "modify")
						{
						$old_id = intval($type["id"]);
						$new_id = intval($type["id"]);

						$old_version = intval($type["version"]) + 0;
						$new_version = intval($type["version"]) + 1;

						$diff_node = $diff->addChild($type_name);
						$diff_node->addAttribute("old_id", $old_id);
						$diff_node->addAttribute("new_id", $new_id);
						$diff_node->addAttribute("old_version", $old_version);
						$diff_node->addAttribute("new_version", $new_version);

						$type->addAttribute("visible", "true"); # better to change after upload?
						}

					if($action_name == "modify")
						$resource->query("update " . $type_name . " set visible = 'false' where ((id = " . $old_id . ") and (version = " . $old_version . "));");

					if($action_name == "delete")
						$resource->query("update " . $type_name . " set visible = 'false' where ((id = " . $old_id . ") and (version = " . $old_version . "));");

					if($type_name == "node")
						{
						if($action_name == "create")
							$resource->query("insert into node (id, version, changeset, timestamp, visible, lat, lon) values (" . $new_id . ", " . $new_version . ", " . $type["changeset"] . ", '" . date("Y-m-d\TH:i:s\Z") . "', '" . $type["visible"] . "', " . $type["lat"] . ", " . $type["lon"] . ");");

						if($action_name == "modify")
							$resource->query("insert into node (id, version, changeset, timestamp, visible, lat, lon) values (" . $new_id . ", " . $new_version . ", " . $type["changeset"] . ", '" . date("Y-m-d\TH:i:s\Z") . "', '" . $type["visible"] . "', " . $type["lat"] . ", " . $type["lon"] . ");");

						if($action_name == "delete")
							{
							if($query = $resource->query("select * from node where ((id = " . $old_id . ") and (version = " . $old_version . "));"))
								{
								while($result = $query->fetch_object())
									{
									$type["lat"] = $result->lat;
									$type["lon"] = $result->lon;
									}
								}

							$resource->query("insert into node (id, version, changeset, timestamp, visible, lat, lon) values (" . $new_id . ", " . $new_version . ", " . $type["changeset"] . ", '" . date("Y-m-d\TH:i:s\Z") . "', '" . $type["visible"] . "', " . $type["lat"] . ", " . $type["lon"] . ");");
							}
						}

					if($type_name == "way")
						$resource->query("insert into way (id, version, changeset, timestamp, visible) values (" . $new_id . ", " . $new_version . ", " . $type["changeset"] . ", '" . date("Y-m-d\TH:i:s\Z") . "', '" . $type["visible"] . "');");

					if($type_name == "relation")
						$resource->query("insert into relation (id, version, changeset, timestamp, visible) values (" . $new_id . ", " . $new_version . ", " . $type["changeset"] . ", '" . date("Y-m-d\TH:i:s\Z") . "', '" . $type["visible"] . "');");

					if(($action_name == "create") || ($action_name == "modify"))
						{
						$z = 0;

						foreach($type->nd as $nd)
							{
							$z = $z + 1;

							$r = intval($nd["ref"]);

							$nd["ref"] = ($r < 0 ? $trans["node"][$r] : $r);

							$resource->query("insert into way_nd (id, version, ref, z) values (" . $new_id . ", " . $new_version . ", " . $nd["ref"] . ", " . $z . ");");
							}

						$z = 0;

						foreach($type->member as $member)
							{
							$z = $z + 1;

							$r = intval($member["ref"]);
							$t = strval($member["type"]);

							$member["ref"] = ($r < 0 ? $trans[$t][$r] : $r);

							$resource->query("insert into relation_member (id, version, type, ref, role, z) values (" . $new_id . ", " . $new_version . ", '" . $member["type"] . "', " . $member["ref"] . ", '" . $member["role"] . "', " . $z . ");");
							}

						foreach($type->tag as $tag)
							$resource->query("insert into " . $type_name . "_tag (id, version, k, v) values (" . $new_id . ", " . $new_version . ", '" . $tag["k"] . "', '" . str_replace("'", "\'", $tag["v"]) . "');");
						}
					}
				}

#			$resource->query("unlock tables;");

			$resource->close();
			}

		$diff = $diff->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($diff));

		print($diff);
		}
	}

################################################################################
# 2.3.1 Comment: POST /api/0.6/changeset/#id/comment
################################################################################

if(preg_match("/api\/0\.6\/changeset\/(\d*)\/comment$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $id) = $matches;
	}

################################################################################
# 2.3.2 Subscribe: POST /api/0.6/changeset/#id/subscribe
################################################################################

if(preg_match("/api\/0\.6\/changeset\/(\d*)\/subscribe$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $id) = $matches;
	}

################################################################################
# 2.3.3 Unsubscribe: POST /api/0.6/changeset/#id/unsubscribe
################################################################################

if(preg_match("/api\/0\.6\/changeset\/(\d*)\/unsubscribe$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $id) = $matches;
	}

################################################################################
# 2.4.1 Create: PUT /api/0.6/[node|way|relation]/create
################################################################################
# Creates a new element of the specified type.

if(preg_match("/api\/0\.6\/(node|way|relation)\/create$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $type) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "PUT")
		{
		check_login();

		$data = file_get_contents("php://input");

		$osm = new SimpleXMLElement($data);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			foreach($osm->$type as $helper)
				{
				$id = 0;

				if($query = $resource->query("select max(id) as id from " . $type . ";"))
					{
					while($result = $query->fetch_object())
						$id = $result->id;

					$query->free_result();
					}

				$old_id = intval($helper["id"]) + 0;
				$new_id = $id + 1;

				$old_version = 0;
				$new_version = 1;

				if($type == "node")
					$resource->query("insert into node (id, version, changeset, timestamp, visible, lat, lon) values (" . $new_id . ", " . $new_version . ", " . $helper["changeset"] . ", '" . date("Y-m-d\TH:i:s\Z") . "', '" . $helper["visible"] . "', " . $helper["lat"] . ", " . $helper["lon"] . ");");

				if($type == "way")
					$resource->query("insert into way (id, version, changeset, timestamp, visible) values (" . $new_id . ", " . $new_version . ", " . $helper["changeset"] . ", '" . date("Y-m-d\TH:i:s\Z") . "', '" . $helper["visible"] . "');");

				if($type == "relation")
					$resource->query("insert into relation (id, version, changeset, timestamp, visible) values (" . $new_id . ", " . $new_version . ", " . $helper["changeset"] . ", '" . date("Y-m-d\TH:i:s\Z") . "', '" . $helper["visible"] . "');");

				$z = 0;

				foreach($helper->nd as $nd)
					{
					$z = $z + 1;

					$resource->query("insert into way_nd (id, version, ref, z) values (" . $new_id . ", " . $new_version . ", " . $nd["ref"] . ", " . $z . ");");
					}

				$z = 0;

				foreach($helper->member as $member)
					{
					$z = $z + 1;

					$resource->query("insert into relation_member (id, version, type, ref, role, z) values (" . $new_id . ", " . $new_version . ", '" . $member["type"] . "', " . $member["ref"] . ", '" . $member["role"] . "', " . $z . ");");
					}

				foreach($helper->tag as $tag)
					$resource->query("insert into " . $type . "_tag (id, version, k, v) values (" . $new_id . ", " . $new_version . ", '" . $tag["k"] . "', '" . str_replace("'", "\'", $tag["v"]) . "');");
				}

			$resource->close();
			}

		header("Content-Type: text/plain");

		print($new_id);
		}
	}

################################################################################
# 2.4.2 Read: GET /api/0.6/[node|way|relation]/#id
################################################################################
# Returns the XML representation of the element.

if(preg_match("/api\/0\.6\/(node|way|relation)\/(\d*)$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $type, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			if($query = $resource->query("select " . $type . ".*, user.id as uid, user.display_name as user from " . $type . ", changeset, user where ((changeset.id = " . $type . ".changeset) and (user.id = changeset.uid) and (" . $type . ".id = " . $id . ")) order by version desc limit 1;"))
				{
				while($result = $query->fetch_object())
					{
					$node = $osm->addChild($type);

					if($type == "node")
						foreach(array("id", "version", "changeset", "uid", "user", "visible", "timestamp", "lat", "lon") as $key)
							$node->addAttribute($key, $result->$key);
					else
						foreach(array("id", "version", "changeset", "uid", "user", "visible", "timestamp") as $key)
							$node->addAttribute($key, $result->$key);

					if($type == "way")
						{
						if($query_way_nd = $resource->query("select * from way_nd where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by z asc;"))
							{
							while($result_way_nd = $query_way_nd->fetch_object())
								{
								$nd = $node->addChild("nd");

								foreach(array("ref") as $key)
									$nd->addAttribute($key, $result_way_nd->$key);
								}

							$query_way_nd->free_result();
							}
						}

					if($type == "relation")
						{
						if($query_relation_member = $resource->query("select * from relation_member where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by z asc;"))
							{
							while($result_relation_member = $query_relation_member->fetch_object())
								{
								$member = $node->addChild("member");

								foreach(array("type", "ref", "role") as $key)
									$member->addAttribute($key, $result_relation_member->$key);
								}

							$query_relation_member->free_result();
							}
						}

					if($query_tag = $resource->query("select * from " . $type . "_tag where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by k asc;"))
						{
						while($result_tag = $query_tag->fetch_object())
							{
							$tag = $node->addChild("tag");

							foreach(array("k", "v") as $key)
								$tag->addAttribute($key, $result_tag->$key);
							}

						$query_tag->free_result();
						}
					}

				$query->free_result();
				}

			$resource->close();
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.4.3 Update: PUT /api/0.6/[node|way|relation]/#id
################################################################################
# Updates data from a preexisting element.
# A full representation of the element as it should be after the update has to be provided.
# So any tags that remain unchanged have to be in the update as well.
# A version number has to be provided as well.

if(preg_match("/api\/0\.6\/(node|way|relation)\/(\d*)$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $type, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "PUT")
		{
		check_login();

		$data = file_get_contents("php://input");

		$osm = new SimpleXMLElement($data);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			foreach($osm->$type as $helper)
				{
				$old_id = intval($helper["id"]) + 0;
				$new_id = intval($helper["id"]) + 0;

				$old_version = intval($helper["version"]) + 0;
				$new_version = intval($helper["version"]) + 1;

				$resource->query("update " . $type . " set visible = 'false' where ((id = " . $old_id . ") and (version = " . $old_version . "));");

				if($type == "node")
					$resource->query("insert into node (id, version, changeset, timestamp, visible, lat, lon) values (" . $new_id . ", " . $new_version . ", " . $helper["changeset"] . ", '" . date("Y-m-d\TH:i:s\Z") . "', '" . $helper["visible"] . "', " . $helper["lat"] . ", " . $helper["lon"] . ");");

				if($type == "way")
					$resource->query("insert into way (id, version, changeset, timestamp, visible) values (" . $new_id . ", " . $new_version . ", " . $helper["changeset"] . ", '" . date("Y-m-d\TH:i:s\Z") . "', '" . $helper["visible"] . "');");

				if($type == "relation")
					$resource->query("insert into relation (id, version, changeset, timestamp, visible) values (" . $new_id . ", " . $new_version . ", " . $helper["changeset"] . ", '" . date("Y-m-d\TH:i:s\Z") . "', '" . $helper["visible"] . "');");

				$z = 0;

				foreach($helper->nd as $nd)
					{
					$z = $z + 1;

					$resource->query("insert into way_nd (id, version, ref, z) values (" . $new_id . ", " . $new_version . ", " . $nd["ref"] . ", " . $z . ");");
					}

				$z = 0;

				foreach($helper->member as $member)
					{
					$z = $z + 1;

					$resource->query("insert into relation_member (id, version, type, ref, role, z) values (" . $new_id . ", " . $new_version . ", '" . $member["type"] . "', " . $member["ref"] . ", '" . $member["role"] . "', " . $z . ");");
					}

				foreach($helper->tag as $tag)
					$resource->query("insert into " . $type . "_tag values (id, version, k, v) (" . $new_id . ", " . $new_version . ", '" . $tag["k"] . "', '" . str_replace("'", "\'", $tag["v"]) . "');");
				}

			$resource->close();
			}

		header("Content-Type: text/xml; charset=utf-8");

		print($new_version);
		}
	}

################################################################################
# 2.4.4 Delete: DELETE /api/0.6/[node|way|relation]/#id
################################################################################
# Expects a valid XML representation of the element to be deleted.

if(preg_match("/api\/0\.6\/(node|way|relation)\/(\d*)$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $type, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "DELETE")
		{
		check_login();

		$data = file_get_contents("php://input");

		$osm = new SimpleXMLElement($data);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			foreach($osm->$type as $helper)
				{
				$old_id = intval($helper["id"]) + 0;
				$new_id = intval($helper["id"]) + 0;

				$old_version = intval($helper["version"]) + 0;
				$new_version = intval($helper["version"]) + 1;

				$resource->query("update " . $type . " set visible = 'false' where ((id = " . $id . ") and (version = " . $old_version . "));");

				if($type == "node")
					$resource->query("insert into node (id, version, changeset, timestamp, visible, lat, lon) values (" . $id . ", " . $new_version . ", " . $helper["changeset"] . ", '" . date("Y-m-d\TH:i:s\Z") . "', '" . $helper["visible"] . "', 0, 0);");

				if($type == "way")
					$resource->query("insert into way (id, version, changeset, timestamp, visible) values (" . $id . ", " . $new_version . ", " . $helper["changeset"] . ", '" . date("Y-m-d\TH:i:s\Z") . "', '" . $helper["visible"] . "');");

				if($type == "relation")
					$resource->query("insert into relation (id, version, changeset, timestamp, visible) values (" . $id . ", " . $new_version . ", " . $helper["changeset"] . ", '" . date("Y-m-d\TH:i:s\Z") . "', '" . $helper["visible"] . "');");
				}

			$resource->close();
			}

		header("Content-Type: text/xml; charset=utf-8");

		print($new_version);
		}
	}

################################################################################
# 2.4.5 History: GET /api/0.6/[node|way|relation]/#id/history
################################################################################
# Retrieves all old versions of an element.

if(preg_match("/api\/0\.6\/(node|way|relation)\/(\d*)\/history$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $type, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			if($query = $resource->query("select " . $type . ".*, user.id as uid, user.display_name as user from " . $type . ", changeset, user where ((changeset.id = " . $type . ".changeset) and (user.id = changeset.uid) and (" . $type . ".id = " . $id . ")) order by " . $type . ".version asc;"))
				{
				while($result = $query->fetch_object())
					{
					$node = $osm->addChild($type);

					$result->visible = "true"; # things are always visible

					if($type == "node")
						foreach(array("id", "version", "changeset", "uid", "user", "visible", "timestamp", "lat", "lon") as $key)
							$node->addAttribute($key, $result->$key);
					else
						foreach(array("id", "version", "changeset", "uid", "user", "visible", "timestamp") as $key)
							$node->addAttribute($key, $result->$key);

					if($type == "way")
						{
						if($query_way_nd = $resource->query("select * from way_nd where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by z asc;"))
							{
							while($result_way_nd = $query_way_nd->fetch_object())
								{
								$nd = $node->addChild("nd");

								foreach(array("ref") as $key)
									$nd->addAttribute($key, $result_way_nd->$key);
								}

							$query_way_nd->free_result();
							}
						}

					if($type == "relation")
						{
						if($query_relation_member = $resource->query("select * from relation_member where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by z asc;"))
							{
							while($result_relation_member = $query_relation_member->fetch_object())
								{
								$member = $node->addChild("member");

								foreach(array("type", "ref", "role") as $key)
									$member->addAttribute($key, $result_relation_member->$key);
								}

							$query_relation_member->free_result();
							}
						}

					if($query_tag = $resource->query("select * from " . $type . "_tag where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by k asc;"))
						{
						while($result_tag = $query_tag->fetch_object())
							{
							$tag = $node->addChild("tag");

							foreach(array("k", "v") as $key)
								$tag->addAttribute($key, $result_tag->$key);
							}

						$query_tag->free_result();
						}
					}

				$query->free_result();
				}

			$resource->close();
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.4.6 Version: GET /api/0.6/[node|way|relation]/#id/#version
################################################################################
# Retrieves a specific version of the element. 

if(preg_match("/api\/0\.6\/(node|way|relation)\/(\d*)\/(\d*)$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $type, $id, $version) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			if($query = $resource->query("select " . $type . ".*, user.id as uid, user.display_name as user from " . $type . ", changeset, user where ((changeset.id = " . $type . ".changeset) and (user.id = changeset.uid) and (" . $type . ".id = " . $id . ") and (" . $type . ".version = " . $version . "));"))
				{
				while($result = $query->fetch_object())
					{
					$node = $osm->addChild($type);

					$result->visible = "true"; # things are always visible

					if($type == "node")
						foreach(array("id", "version", "changeset", "uid", "user", "visible", "timestamp", "lat", "lon") as $key)
							$node->addAttribute($key, $result->$key);
					else
						foreach(array("id", "version", "changeset", "uid", "user", "visible", "timestamp") as $key)
							$node->addAttribute($key, $result->$key);

					if($type == "way")
						{
						if($query_way_nd = $resource->query("select * from way_nd where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by z asc;"))
							{
							while($result_way_nd = $query_way_nd->fetch_object())
								{
								$nd = $node->addChild("nd");

								foreach(array("ref") as $key)
									$nd->addAttribute($key, $result_way_nd->$key);
								}

							$query_way_nd->free_result();
							}
						}

					if($type == "relation")
						{
						if($query_relation_member = $resource->query("select * from relation_member where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by z asc;"))
							{
							while($result_relation_member = $query_relation_member->fetch_object())
								{
								$member = $node->addChild("member");

								foreach(array("type", "ref", "role") as $key)
									$member->addAttribute($key, $result_relation_member->$key);
								}

							$query_relation_member->free_result();
							}
						}

					if($query_tag = $resource->query("select * from " . $type . "_tag where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by k asc;"))
						{
						while($result_tag = $query_tag->fetch_object())
							{
							$tag = $node->addChild("tag");

							foreach(array("k", "v") as $key)
								$tag->addAttribute($key, $result_tag->$key);
							}

						$query_tag->free_result();
						}
					}

				$query->free_result();
				}

			$resource->close();
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.4.7 Multi fetch: GET /api/0.6/[nodes|ways|relations]?#parameters
################################################################################
# Allows a user to fetch multiple elements at once.

if(preg_match("/api\/0\.6\/(node|way|relation)s$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $type) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		if((isset($_GET[$type . "s"]) === false) || (strlen($_GET[$type . "s"]) == 0))
			exception_object_missed($type);

		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			if($query = $resource->query("select " . $type . ".*, user.id as uid, user.display_name as user from " . $type . ", changeset, user where ((changeset.id = " . $type . ".changeset) and (user.id = changeset.uid) and (" . $type . ".id in (" . $_GET[$type . "s"] . ")) and (" . $type . ".visible = 'true')) order by " . $type . ".id asc;"))
				{
				while($result = $query->fetch_object())
					{
					$node = $osm->addChild($type);

					if($result->visible == "false")
						{
						foreach(array("id", "version", "changeset", "uid", "user", "visible", "timestamp") as $key)
							$node->addAttribute($key, $result->$key);

						continue;
						}

					if($type == "node")
						foreach(array("id", "version", "changeset", "uid", "user", "visible", "timestamp", "lat", "lon") as $key)
							$node->addAttribute($key, $result->$key);

					else
						foreach(array("id", "version", "changeset", "uid", "user", "visible", "timestamp") as $key)
							$node->addAttribute($key, $result->$key);

					if($type == "way")
						{
						if($query_way_nd = $resource->query("select * from way_nd where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by z asc;"))
							{
							while($result_way_nd = $query_way_nd->fetch_object())
								{
								$nd = $node->addChild("nd");

								foreach(array("ref") as $key)
									$nd->addAttribute($key, $result_way_nd->$key);
								}

							$query_way_nd->free_result();
							}
						}

					if($type == "relation")
						{
						if($query_relation_member = $resource->query("select * from relation_member where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by z asc;"))
							{
							while($result_relation_member = $query_relation_member->fetch_object())
								{
								$member = $node->addChild("member");

								foreach(array("type", "ref", "role") as $key)
									$member->addAttribute($key, $result_relation_member->$key);
								}

							$query_relation_member->free_result();
							}
						}

					if($query_tag = $resource->query("select * from " . $type . "_tag where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by k asc;"))
						{
						while($result_tag = $query_tag->fetch_object())
							{
							$tag = $node->addChild("tag");

							foreach(array("k", "v") as $key)
								$tag->addAttribute($key, $result_tag->$key);
							}

						$query_tag->free_result();
						}
					}

				$query->free_result();
				}

			$resource->close();
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.4.8 Relations for element: GET /api/0.6/[node|way|relation]/#id/relations
################################################################################
# Returns a XML document containing all (not deleted) relations in which the given element is used.

if(preg_match("/api\/0\.6\/(node|way|relation)\/(\d*)\/relations$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $type, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			if($query_relation = $resource->query("select distinct relation.*, user.id as uid, user.display_name as user from relation, relation_member, changeset, user where ((relation.id = relation_member.id) and (relation.version = relation_member.version) and (changeset.id = relation.changeset) and (user.id = changeset.uid) and (relation_member.type = '" . $type . "') and (relation_member.ref = " . $id . ") and (relation.visible = 'true')) order by relation.id asc;"))
				{
				while($result_relation = $query_relation->fetch_object())
					{
					$node = $osm->addChild("relation");

					foreach(array("id", "version", "changeset", "uid", "user", "visible", "timestamp") as $key)
						$node->addAttribute($key, $result_relation->$key);

					if($query_relation_member = $resource->query("select * from relation_member where ((id = " . $result_relation->id . ") and (version = " . $result_relation->version . ")) order by z asc;"))
						{
						while($result_relation_member = $query_relation_member->fetch_object())
							{
							$member = $node->addChild("member");

							foreach(array("type", "ref", "role") as $key)
								$member->addAttribute($key, $result_relation_member->$key);
							}

						$query_relation_member->free_result();
						}

					if($query_tag = $resource->query("select * from relation_tag where ((id = " . $result_relation->id . ") and (version = " . $result_relation->version . ")) order by k asc;"))
						{
						while($result_tag = $query_tag->fetch_object())
							{
							$tag = $node->addChild("tag");

							foreach(array("k", "v") as $key)
								$tag->addAttribute($key, $result_tag->$key);
							}

						$query_tag->free_result();
						}
					}

				$query_relation->free_result();
				}

			$resource->close();
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.4.9 Ways for node: GET /api/0.6/node/#id/ways
################################################################################
# Returns a XML document containing all the (not deleted) ways in which the given node is used.

if(preg_match("/api\/0\.6\/node\/(\d*)\/ways$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			if($query_way = $resource->query("select distinct way.*, user.id as uid, user.display_name as user from way, way_nd, changeset, user where ((way_nd.id = way.id) and (way_nd.version = way.version) and (changeset.id = way.changeset) and (user.id = changeset.uid) and (way_nd.ref = " . $id . ") and (way.visible = 'true')) order by way.id asc;"))
				{
				while($result_way = $query_way->fetch_object())
					{
					$node = $osm->addChild("way");

					foreach(array("id", "version", "changeset", "uid", "user", "visible", "timestamp") as $key)
						$node->addAttribute($key, $result_way->$key);

					if($query_way_nd = $resource->query("select * from way_nd where ((id = " . $result_way->id . ") and (version = " . $result_way->version . ")) order by z asc;"))
						{
						while($result_way_nd = $query_way_nd->fetch_object())
							{
							$nd = $node->addChild("nd");

							foreach(array("ref") as $key)
								$nd->addAttribute($key, $result_way_nd->$key);
							}

						$query_way_nd->free_result();
						}

					if($query_tag = $resource->query("select * from way_tag where ((id = " . $result_way->id . ") and (version = " . $result_way->version . ")) order by k asc;"))
						{
						while($result_tag = $query_tag->fetch_object())
							{
							$tag = $node->addChild("tag");

							foreach(array("k", "v") as $key)
								$tag->addAttribute($key, $result_tag->$key);
							}

						$query_tag->free_result();
						}
					}

				$query_way->free_result();
				}

			$resource->close();
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.4.10 Full: GET /api/0.6/[way|relation]/#id/full
################################################################################
# This API call retrieves a way or relation and all other elements referenced by it

if(preg_match("/api\/0\.6\/(way|relation)\/(\d*)\/full$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $type, $id) = $matches;

	$microtime = microtime(true);

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			$trans = array("node" => array(), "way" => array(), "relation" => array());

			if($query = $resource->query("select " . $type . ".* from " . $type . " where ((" . $type . ".id = " . $id . ") and (" . $type . ".visible = 'true'));"))
				{
				while($result = $query->fetch_object())
					$trans[$type][$result->id] = $result->version;

				$query->free_result();
				}

			if($type == "way")
				{
				if($query = $resource->query("select node.* from node, way_nd where ((way_nd.ref = node.id) and (way_nd.id = " . $id . ") and (node.visible = 'true'));"))
					{
					while($result = $query->fetch_object())
						$trans["node"][$result->id] = $result->version;

					$query->free_result();
					}
				}

			if($type == "relation")
				{
				if($query = $resource->query("select node.* from node, relation_member where ((relation_member.ref = node.id) and (relation_member.type = 'node') and (relation_member.id = " . $id . ") and (node.visible = 'true'));"))
					{
					while($result = $query->fetch_object())
						$trans["node"][$result->id] = $result->version;

					$query->free_result();
					}

				if($query = $resource->query("select way.* from way, relation_member where ((relation_member.ref = way.id) and (relation_member.type = 'way') and (relation_member.id = " . $id . ") and (way.visible = 'true'));"))
					{
					while($result = $query->fetch_object())
						$trans["way"][$result->id] = $result->version;

					$query->free_result();
					}

				if($query = $resource->query("select relation.* from relation, relation_member where ((relation_member.ref = relation.id) and (relation_member.type = 'relation') and (relation_member.id = " . $id . ") and (relation.visible = 'true'));"))
					{
					while($result = $query->fetch_object())
						$trans["relation"][$result->id] = $result->version;

					$query->free_result();
					}
				}

			foreach(array("node", "way", "relation") as $type)
				ksort($trans[$type]);

			foreach(array("node", "way", "relation") as $type)
				{
				foreach($trans[$type] as $id => $version)
					{
					if($query = $resource->query("select " . $type . ".*, user.id as uid, user.display_name as user from " . $type . ", changeset, user where ((changeset.id = " . $type . ".changeset) and (user.id = changeset.uid) and (" . $type . ".id = " . $id . ") and (" . $type . ".visible = 'true'));"))
						{
						while($result = $query->fetch_object())
							{
							$node = $osm->addChild($type);

							if($type == "node")
								{
								foreach(array("id", "version", "changeset", "uid", "user", "visible", "timestamp", "lat", "lon") as $key)
									$node->addAttribute($key, $result->$key);
								}

							if($type == "way")
								{
								foreach(array("id", "version", "changeset", "uid", "user", "visible", "timestamp") as $key)
									$node->addAttribute($key, $result->$key);

								if($query_way_nd = $resource->query("select * from way_nd where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by z asc;"))
									{
									while($result_way_nd = $query_way_nd->fetch_object())
										{
										$nd = $node->addChild("nd");

										foreach(array("ref") as $key)
											$nd->addAttribute($key, $result_way_nd->$key);
										}

									$query_way_nd->free_result();
									}
								}

							if($type == "relation")
								{
								foreach(array("id", "version", "changeset", "uid", "user", "visible", "timestamp") as $key)
									$node->addAttribute($key, $result->$key);

								if($query_relation_member = $resource->query("select * from relation_member where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by z asc;"))
									{
									while($result_relation_member = $query_relation_member->fetch_object())
										{
										$member = $node->addChild("member");

										foreach(array("type", "ref", "role") as $key)
											$member->addAttribute($key, $result_relation_member->$key);
										}

									$query_relation_member->free_result();
									}
								}

							if($query_tag = $resource->query("select * from " . $type . "_tag where ((id = " . $result->id . ") and (version = " . $result->version . ")) order by k asc;"))
								{
								while($result_tag = $query_tag->fetch_object())
									{
									$tag = $node->addChild("tag");

									foreach(array("k", "v") as $key)
										$tag->addAttribute($key, $result_tag->$key);
									}

								$query_tag->free_result();
								}
							}

						$query->free_result();
						}
					}
				}

			$resource->close();
			}

		$osm->addAttribute("time", microtime(true) - $microtime);

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.4.11 Redaction: PUT /api/0.6/[node|way|relation]/#id/#version/redact?redaction=#redaction_id
################################################################################

if(preg_match("/api\/0\.6\/(node|way|relation)\/(\d*)\/(\d*)\/redact$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $type, $id, $version) = $matches;
	}

################################################################################
# 2.5.1 Retrieving GPS points
################################################################################

if(preg_match("/api\/0\.6\/trackpoints$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		if((isset($_GET["bbox"]) === false) || (strlen($_GET["bbox"]) == 0) || (count(explode(",", $_GET["bbox"])) != 4))
			exception_bbox_missed();

		list($min_lon, $min_lat, $max_lon, $max_lat) = explode(",", $_GET["bbox"]);

		if(check_bbox_range($min_lon, $min_lat, $max_lon, $max_lat) === false)
			exception_bbox_range();

		if(check_bbox_area($min_lon, $min_lat, $max_lon, $max_lat) === false)
			exception_bbox_area();

		$gpx = new SimpleXMLElement("<gpx />");
		$gpx->addAttribute("version", "1.0");
		$gpx->addAttribute("creator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			if($query = $resource->query("select * from gpx_trackpoint where (" . bboxs($min_lon, $min_lat, $max_lon, $max_lat) . ") order by id asc, track asc, z asc limit " . ((isset($_GET["page"]) === false ? 0 : $_GET["page"]) * 5000) . ",5000;"))
				{
				$t = 0;
				$z = 0;

				while($result = $query->fetch_object())
					{
					if($t != $result->id)
						$trk = $gpx->addChild("trk");

					$t = $result->id + 0;

					if($z != $result->z)
						$trkseg = $trk->addChild("trkseg");


					$z = $result->z + 1;

					$trkpt = $trkseg->addChild("trkpt");

					foreach(array("lat", "lon") as $key)
						$trkpt->addAttribute($key, $result->$key);

					foreach(array("ele", "time", "hdop") as $key)
						{
						if($result->$key == null)
							continue;

						$trkpt->addChild($key, $result->$key);
						}
					}

				$query->free_result();
				}

			$resource->close();
			}

		$gpx = $gpx->asXML();

		header("Content-Disposition: inline; filename=\"tracks.gpx\"");
		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($gpx));

		print($gpx);
		}
	}

################################################################################
# 2.5.2 Uploading traces
################################################################################

if(preg_match("/api\/0\.6\/gpx\/create$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "POST")
		{
		check_login();

		$gpx = ($_FILES["file"]["tmp_name"] == "" ? "<gpx />" : file_get_contents($_FILES["file"]["tmp_name"]));

		$gpx = new SimpleXMLElement($gpx);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			$uid = 0;

			if($query = $resource->query("select * from user where ((id = '" . $_SERVER["PHP_AUTH_USER"] . "') or (display_name = '" . $_SERVER["PHP_AUTH_USER"] . "') or (email = '" . $_SERVER["PHP_AUTH_USER"] . "'));"))
				{
				while($result = $query->fetch_object())
					$uid = $result->id;

				$query->free_result();
				}

			$id = 0;

			if($query = $resource->query("select * from gpx order by id desc limit 1;"))
				{
				while($result = $query->fetch_object())
					$id = $result->id;

				$query->free_result();
				}

			$id = $id + 1;

			$lat = 0; # $gpx->trk[0]->trkseg[0]->trkpt[0]["lat"];
			$lon = 0; # $gpx->trk[0]->trkseg[0]->trkpt[0]["lon"];

			foreach($gpx->trk as $trk)
				{
				foreach($trk->trkseg as $trkseg)
					{
					foreach($trkseg->trkpt as $trkpt)
						{
						$lat = $trkpt["lat"];
						$lon = $trkpt["lon"];

						break;
						}

					break;
					}

				break;
				}

			$resource->query("insert into gpx (id, uid, name, lat, lon, timestamp, description, pending, visibility) values (" . $id . ", " . $uid . ", '" . ($_FILES["file"]["name"] == "" ? "default.gpx" : $_FILES["file"]["name"]) . "', " . $lat . ", " . $lon . ", '" . date("Y-m-d\TH:i:s\Z") . "', '" . str_replace("'", "\'", $_POST["description"]) . "', 'false', '" . $_POST["visibility"] . "');");

			if((isset($_POST["tags"]) === true) && (strlen($_POST["tags"]) != 0))
				{
				foreach(explode(",", $_POST["tags"]) as $tag)
					{
					if(trim($tag) == "")
						continue;

					$resource->query("insert into gpx_tag (id, tag) values (" . $id . ", '" . trim($tag) . "');");
					}
				}

			$t = 0;
			$z = 0;

			foreach($gpx->trk as $trk)
				{
				foreach($trk->trkseg as $trkseg)
					{
					$t = $t + 1;

					foreach($trkseg->trkpt as $trkpt)
						{
						$z = $z + 1;

						foreach(array("ele", "time", "hdop") as $key)
							$trkpt->$key = (isset($trkpt->$key) === false ? null : $trkpt->$key);

						$resource->query("insert into gpx_trackpoint (id, track, ele, time, hdop, lat, lon, z) values (" . $id . ", " . $t . ", " . $trkpt->ele . ", " . $trkpt->time . ", " . $trkpt->hdop . ", " . $trkpt["lat"] . ", " . $trkpt["lon"] . ", " . $z . ");");
						}

					$z = 0;
					}
				}

			$resource->close();
			}
		}
	}

################################################################################
# 2.5.3 Downloading trace metadata
################################################################################

if(preg_match("/api\/0\.6\/gpx\/(\d*)\/details$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		check_login();

		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			if($query_gpx = $resource->query("select gpx.*, user.display_name as user from gpx, user where ((user.id = gpx.uid) and (gpx.id = " . $id . ") and ((user.display_name = '" . $_SERVER["PHP_AUTH_USER"] . "') or (user.email = '" . $_SERVER["PHP_AUTH_USER"] . "')));"))
				{
				while($result_gpx = $query_gpx->fetch_object())
					{
					$gpx_file = $osm->addChild("gpx_file");

					foreach(array("id", "name", "lat", "lon", "user", "visibility", "pending", "timestamp") as $key)
						$gpx_file->addAttribute($key, $result_gpx->$key);

					$gpx_file->addChild("description", $result_gpx->description);

					if($query_gpx_tag = $resource->query("select * from gpx_tag where (id = " . $result_gpx->id . ");"))
						{
						while($result_gpx_tag = $query_gpx_tag->fetch_object())
							$gpx_file->addChild("tag", $result_gpx_tag->tag);

						$query_gpx_tag->free_result();
						}
					}

				$query_gpx->free_result();
				}

			$resource->close();
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.5.3 Downloading trace metadata
################################################################################

if(preg_match("/api\/0\.6\/gpx\/(\d*)\/data$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		check_login();

		$gpx = new SimpleXMLElement("<gpx />");
		$gpx->addAttribute("version", "1.0");
		$gpx->addAttribute("creator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			$trk = $gpx->addChild("trk");

			if($query_gpx = $resource->query("select * from gpx where (id = " . $id . ");"))
				{
				while($result_gpx = $query_gpx->fetch_object())
					{
					$trk->addChild("name", $result_gpx->name);

					if($query_gpx_trackpoint = $resource->query("select * from gpx_trackpoint where (id = " . $result_gpx->id . ") order by track asc, z asc;"))
						{
						$z = 0;

						while($result_gpx_trackpoint = $query_gpx_trackpoint->fetch_object())
							{
							if($z != $result_gpx_trackpoint->z)
								$trkseg = $trk->addChild("trkseg");


							$z = $result_gpx_trackpoint->z + 1;

							$trkpt = $trkseg->addChild("trkpt");

							foreach(array("lat", "lon") as $key)
								$trkpt->addAttribute($key, $result_gpx_trackpoint->$key);
							}

						$query_gpx_trackpoint->free_result();
						}
					}

				$query_gpx->free_result();
				}

			$resource->close();
			}

		$gpx = $gpx->asXML();

		header("Content-Disposition: inline; filename=\"tracks.gpx\"");
		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($gpx));

		print($gpx);
		}
	}

################################################################################
# 2.5.3 Downloading trace metadata
################################################################################

if(preg_match("/api\/0\.6\/user\/gpx_files$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		check_login();

		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			if($query_gpx = $resource->query("select gpx.*, user.display_name as user from gpx, user where ((user.id = gpx.uid) and ((user.display_name = '" . $_SERVER["PHP_AUTH_USER"] . "') or (user.email = '" . $_SERVER["PHP_AUTH_USER"] . "'))) order by gpx.timestamp desc;"))
				{
				while($result_gpx = $query_gpx->fetch_object())
					{
					$gpx_file = $osm->addChild("gpx_file");

					foreach(array("id", "name", "lat", "lon", "user", "visibility", "pending", "timestamp") as $key)
						$gpx_file->addAttribute($key, $result_gpx->$key);

					$gpx_file->addChild("description", $result_gpx->description);

					if($query_gpx_tag = $resource->query("select * from gpx_tag where (id = " . $result_gpx->id . ");"))
						{
						while($result_gpx_tag = $query_gpx_tag->fetch_object())
							$gpx_file->addChild("tag", $result_gpx_tag->tag);

						$query_tag->free_result();
						}
					}

				$query_gpx->free_result();
				}

			$resource->close();
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.6.1 Details of a user
################################################################################

if(preg_match("/api\/0\.6\/user\/(\d*)$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			if($query = $resource->query("select * from user where (id = " . $id . ");"))
				{
				while($result = $query->fetch_object())
					{
					$count_a = 0;
					$count_b = 0;

					if($query_changeset = $resource->query("select count(*) as count from changeset where (uid = " . $result->id . ");"))
						{
						while($result_changeset = $query_changeset->fetch_object())
							$count_a = $result_changeset->count;

						$query->free_result();
						}

					if($query_gpx = $resource->query("select count(*) as count from gpx where (uid = " . $result->id . ");"))
						{
						while($result_gpx = $query_gpx->fetch_object())
							$count_b = $result_gpx->count;

						$query->free_result();
						}

					$user = $osm->addChild("user");

						foreach(array("id", "display_name", "account_created") as $key)
							$user->addAttribute($key, $result->$key);

						$description = $user->addChild("description", $result->description);

						$contributor_terms = $user->addChild("contributor-terms");
							$contributor_terms->addAttribute("agreed", $result->agreed);

						$roles = $user->addChild("roles", "    ");

						$changesets = $user->addChild("changesets");
							$changesets->addAttribute("count", $count_a);

						$traces = $user->addChild("traces");
							$traces->addAttribute("count", $count_b);

						$blocks = $user->addChild("blocks");
							$received = $blocks->addChild("received");
								$received->addAttribute("count", "0");
								$received->addAttribute("active", "0");
					}

				$query->free_result();
				}

			$resource->close();
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.6.2 Details of the logged-in user
################################################################################

if(preg_match("/api\/0\.6\/user\/details$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		check_login();

		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			if($query = $resource->query("select * from user where ((id = '" . $_SERVER["PHP_AUTH_USER"] . "') or (email = '" . $_SERVER["PHP_AUTH_USER"] . "') or (display_name = '" . $_SERVER["PHP_AUTH_USER"] . "'));"))
				{
				while($result = $query->fetch_object())
					{
					$count_a = 0;
					$count_b = 0;
					$count_c = 0;
					$count_d = 0;
					$count_e = 0;

					if($query_changeset = $resource->query("select count(*) as count from changeset where (uid = " . $result->id . ");"))
						{
						while($result_changeset = $query_changeset->fetch_object())
							$count_a = $result_changeset->count;

						$query_changeset->free_result();
						}

					if($query_gpx = $resource->query("select count(*) as count from gpx where (uid = " . $result->id . ");"))
						{
						while($result_gpx = $query_gpx->fetch_object())
							$count_b = $result_gpx->count;

						$query_gpx->free_result();
						}

					if($query_message = $resource->query("select count(*) as count from message where (uid_to = " . $result->id . ");"))
						{
						while($result_message = $query_message->fetch_object())
							$count_c = $result_message->count;

						$query_message->free_result();
						}

					if($query_message = $resource->query("select count(*) as count from message where ((uid_to = " . $result->id . ") and (unread = 'true'));"))
						{
						while($result_message = $query_message->fetch_object())
							$count_d = $result_message->count;

						$query_message->free_result();
						}

					if($query_message = $resource->query("select count(*) as count from message where (uid_from = " . $result->id . ");"))
						{
						while($result_message = $query_message->fetch_object())
							$count_e = $result_message->count;

						$query_message->free_result();
						}

					$user = $osm->addChild("user");

						foreach(array("id", "display_name", "account_created") as $key)
							$user->addAttribute($key, $result->$key);

						$description = $user->addChild("description", $result->description);

						$contributor_terms = $user->addChild("contributor-terms");
							$contributor_terms->addAttribute("agreed", $result->agreed);
							$contributor_terms->addAttribute("pd", $result->pd);

						$roles = $user->addChild("roles", "    ");

						$changesets = $user->addChild("changesets");
							$changesets->addAttribute("count", $count_a);

						$traces = $user->addChild("traces");
							$traces->addAttribute("count", $count_b);

						$blocks = $user->addChild("blocks");
							$received = $blocks->addChild("received");
								$received->addAttribute("count", "0");
								$received->addAttribute("active", "0");

						$languages = $user->addChild("languages");
							$languages->addChild("lang", "de");
							$languages->addChild("lang", "en");
							$languages->addChild("lang", "ro");

						$messages = $user->addChild("messages");

						$received = $messages->addChild("received");
							$received->addAttribute("count", $count_c);
							$received->addAttribute("unread", $count_d);

						$sent = $messages->addChild("sent");
							$sent->addAttribute("count", $count_e);
					}

				$query->free_result();
				}

			$resource->close();
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.6.3 Preferences of the logged-in user
################################################################################

if(preg_match("/api\/0\.6\/user\/preferences$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		check_login();

		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			$preferences = $osm->addChild("preferences");

			if($query = $resource->query("select * from preference, user where (preference.id = user.id) and ((user.id = '" . $_SERVER["PHP_AUTH_USER"] . "') or (user.email = '" . $_SERVER["PHP_AUTH_USER"] . "') or (user.display_name = '" . $_SERVER["PHP_AUTH_USER"] . "'));"))
				{
				while($result = $query->fetch_object())
					{
					$preference = $preferences->addChild("preference");

					foreach(array("k", "v") as $key)
						$preference->addAttribute($key, $result->$key);
					}

				$query->free_result();
				}

			$resource->close();
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

if(preg_match("/api\/0\.6\/user\/preferences\/(\w*)$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $key) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "PUT")
		{
		check_login();

		$data = file_get_contents("php://input");

		$osm = new SimpleXMLElement($data);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			$uid = 0;

			if($query = $resource->query("select * from user where ((id = '" . $_SERVER["PHP_AUTH_USER"] . "') or (display_name = '" . $_SERVER["PHP_AUTH_USER"] . "') or (email = '" . $_SERVER["PHP_AUTH_USER"] . "'));"))
				{
				while($result = $query->fetch_object())
					$uid = $result->id;

				$query->free_result();
				}

			$resource->query("delete from preference where ((id = " . $uid . ") and (k = '" . $k . "'));");
			$resource->query("insert into preference (id, k, v) values (" . $uid . ", '" . $k . "', '" . str_replace("'", "\'", $data) . "');");

			$resource->close();
			}
		}
	}

if(preg_match("/api\/0\.6\/user\/preferences$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "POST")
		{
		check_login();

		$data = file_get_contents("php://input");

		$data = new SimpleXMLElement($data);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			$uid = 0;

			if($query = $resource->query("select * from user where ((id = '" . $_SERVER["PHP_AUTH_USER"] . "') or (display_name = '" . $_SERVER["PHP_AUTH_USER"] . "') or (email = '" . $_SERVER["PHP_AUTH_USER"] . "'));"))
				{
				while($result = $query->fetch_object())
					$uid = $result->id;

				$query->free_result();
				}

			$resource->query("delete from preference where (id = " . $uid . ");");

			foreach($data->preferences as $preferences)
				foreach($preferences->preference as $preference)
					$resource->query("insert into preference (id, k, v) values (" . $uid . ", '" . $preference["k"] . "', '" . str_replace("'", "\'", $preference["v"]) . "');");

			$resource->close();
			}
		}
	}

################################################################################
# 2.7.1 Retrieving bug data by bounding box: GET /api/0.6/notes
################################################################################

if(preg_match("/api\/0\.6\/notes$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		if((isset($_GET["bbox"]) === false) || (strlen($_GET["bbox"]) == 0) || (count(explode(",", $_GET["bbox"])) != 4))
			exception_bbox_missed();

		list($min_lon, $min_lat, $max_lon, $max_lat) = explode(",", $_GET["bbox"]);

		if(check_bbox_range($min_lon, $min_lat, $max_lon, $max_lat) === false)
			exception_bbox_range();

		if(($max_lat - $min_lat) * ($max_lon - $min_lon) > OSM_AREA_MAXIMUM)
			exception_bbox_area();

		if((isset($_GET["limit"]) === true) && ((strlen($_GET["limit"]) == 0) || (is_numeric($_GET["limit"]) === false) || ($_GET["limit"] < 1) || ($_GET["limit"] > 10000)))
			exception_limit_range();
		elseif(isset($_GET["limit"]) === false)
			$limit = 100;
		else
			$limit = $_GET["limit"];

		if(isset($_GET["closed"]) === false)
			$closed = "(status = 'open') or ((status = 'closed') and (date_closed > '" . date("Y-m-d\TH:i:s\Z", time() - (7 * 86400)) . "'))";
		elseif($_GET["closed"] == 0)
			$closed = "status = 'open'";
		elseif($_GET["closed"] == 0 - 1)
			$closed = "(status = 'open') or (status = 'closed')";
		else
			$closed = "(status = 'open') or ((status = 'closed') and (date_closed > '" . date("Y-m-d\TH:i:s\Z", time() - ($_GET["closed"] * 86400)) . "'))";

		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			if($query_note = $resource->query("select * from note where ((" . bboxs($min_lon, $min_lat, $max_lon, $max_lat) . ") and (" . $closed . ")) limit " . $limit . ";"))
				{
				while($result_note = $query_note->fetch_object())
					{
					$note = $osm->addChild("note");

					foreach(array("lon", "lat") as $key)
						$note->addAttribute($key, $result_note->$key);

					foreach(array("id", "status", "date_created") as $key)
						$note->addChild($key, $result_note->$key);

					if($result_note->status == "open")
						$note->addChild("comment_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/comment");

					if($result_note->status == "open")
						$note->addChild("close_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/close");

					if($result_note->status == "closed")
						$note->addChild("date_closed", $result_note->date_closed);

					if($result_note->status == "closed")
						$note->addChild("reopen_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/reopen");

					$comments = $note->addChild("comments");

					if($query_note_comment = $resource->query("select note_comment.*, user.display_name as user from note_comment, user where ((user.id = note_comment.uid) and (note_comment.id = " . $result_note->id . "));"))
						{
						while($result_note_comment = $query_note_comment->fetch_object())
							{
							$comment = $comments->addChild("comment");

							foreach(array("date", "uid", "user", "action", "text") as $key)
								$comment->addChild($key, $result_note_comment->$key);
							}

						$query_note_comment->free_result();
						}
					}

				$query_note->free_result();
				}

			$resource->close();
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.7.2 Read: GET /api/0.6/notes/#id
################################################################################

if(preg_match("/api\/0\.6\/notes\/(\d*)$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			if($query_note = $resource->query("select * from note where (id = " . $id . ");"))
				{
				while($result_note = $query_note->fetch_object())
					{
					$note = $osm->addChild("note");

					foreach(array("lon", "lat") as $key)
						$note->addAttribute($key, $result_note->$key);

					foreach(array("id", "status", "date_created") as $key)
						$note->addChild($key, $result_note->$key);

					$note->addChild("url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id);

					if($result_note->status == "open")
						$note->addChild("comment_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/comment");

					if($result_note->status == "open")
						$note->addChild("close_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/close");

					if($result_note->status == "closed")
						$note->addChild("date_closed", $result_note->date_closed);

					if($result_note->status == "closed")
						$note->addChild("reopen_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/reopen");

					$comments = $note->addChild("comments");

					if($query_note_comment = $resource->query("select note_comment.*, user.display_name as user from note_comment, user where ((user.id = note_comment.uid) and (note_comment.id = " . $result_note->id . "));"))
						{
						while($result_note_comment = $query_note_comment->fetch_object())
							{
							$comment = $comments->addChild("comment");

							foreach(array("date", "uid", "user", "action", "text") as $key)
								$comment->addChild($key, $result_note_comment->$key);
							}

						$query_note_comment->free_result();
						}
					}

				$query_note->free_result();
				}

			$resource->close();
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.7.3 Create a new note: Create: POST /api/0.6/notes
################################################################################

if(preg_match("/api\/0\.6\/notes$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "POST")
		{
		check_login();

		if((isset($_GET["lat"]) === false) || (strlen($_GET["lat"]) == 0))
			exception_lat_missed();

		if((isset($_GET["lon"]) === false) || (strlen($_GET["lon"]) == 0))
			exception_lon_missed();

		if((isset($_GET["text"]) === false) || (strlen($_GET["text"]) == 0))
			exception_text_missed();

		if(is_numeric($_GET["lat"]) === false)
			exception_lat_invalid();

		if(is_numeric($_GET["lon"]) === false)
			exception_lon_invalid();

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			$uid = 0;

			if($query = $resource->query("select * from user where ((id = '" . $_SERVER["PHP_AUTH_USER"] . "') or (display_name = '" . $_SERVER["PHP_AUTH_USER"] . "') or (email = '" . $_SERVER["PHP_AUTH_USER"] . "'));"))
				{
				while($result = $query->fetch_object())
					$uid = $result->id;
				}

			$id = 0;

			if($query = $resource->query("select max(id) as id from note;"))
				{
				while($result = $query->fetch_object())
					$id = $result->id;
				}

			$id = $id + 1;

			$resource->query("insert into note (id, date_created, date_closed, status, lat, lon) values (" . $id . ", '" . date("Y-m-d\TH:i:s\Z") . "', '', 'open', " . $_GET["lat"] . ", " . $_GET["lon"] . ");");
			$resource->query("insert into note_comment (id, uid, date, action, text) values (" . $id . ", " . $uid . ", '" . date("Y-m-d\TH:i:s\Z") . "', 'opened', '" . str_replace("'", "\'", $_GET["text"]) . "');");

			$resource->close();
			}

		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			if($query_note = $resource->query("select * from note where (id = " . $id . ");"))
				{
				while($result_note = $query_note->fetch_object())
					{
					$note = $osm->addChild("note");

					foreach(array("lon", "lat") as $key)
						$note->addAttribute($key, $result_note->$key);

					foreach(array("id", "status", "date_created") as $key)
						$note->addChild($key, $result_note->$key);

					$note->addChild("url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id);

					if($result_note->status == "open")
						$note->addChild("comment_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/comment");

					if($result_note->status == "open")
						$note->addChild("close_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/close");

					if($result_note->status == "closed")
						$note->addChild("date_closed", $result_note->date_closed);

					if($result_note->status == "closed")
						$note->addChild("reopen_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/reopen");

					$comments = $note->addChild("comments");

					if($query_note_comment = $resource->query("select note_comment.*, user.display_name as user from note_comment, user where ((user.id = note_comment.uid) and (note_comment.id = " . $result_note->id . "));"))
						{
						while($result_note_comment = $query_note_comment->fetch_object())
							{
							$comment = $comments->addChild("comment");

							foreach(array("date", "uid", "user", "action", "text") as $key)
								$comment->addChild($key, $result_note_comment->$key);
							}

						$query_note_comment->free_result();
						}
					}

				$query_note->free_result();
				}

			$resource->close();
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.7.4 Create a new comment: Create: POST /api/0.6/notes/#id/comment
################################################################################

if(preg_match("/api\/0\.6\/notes\/(\d*)\/comment$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "POST")
		{
		check_login();

		if((isset($_GET["text"]) === false) || (strlen($_GET["text"]) == 0))
			exception_text_missed();

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			$uid = 0;

			if($query = $resource->query("select * from user where ((id = '" . $_SERVER["PHP_AUTH_USER"] . "') or (display_name = '" . $_SERVER["PHP_AUTH_USER"] . "') or (email = '" . $_SERVER["PHP_AUTH_USER"] . "'));"))
				{
				while($result = $query->fetch_object())
					$uid = $result->id;

				$query->free_result();
				}

			$resource->query("insert into note_comment (id, uid, date, action, text) values (" . $id . ", " . $uid . ", '" . date("Y-m-d\TH:i:s\Z") . "', 'commented', '" . str_replace("'", "\'", $_GET["text"]) . "');");

			$resource->close();
			}

		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			if($query_note = $resource->query("select * from note where (id = " . $id . ");"))
				{
				while($result_note = $query_note->fetch_object())
					{
					$note = $osm->addChild("note");

					foreach(array("lon", "lat") as $key)
						$note->addAttribute($key, $result_note->$key);

					foreach(array("id", "status", "date_created") as $key)
						$note->addChild($key, $result_note->$key);

					$note->addChild("url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id);

					if($result_note->status == "open")
						$note->addChild("comment_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/comment");

					if($result_note->status == "open")
						$note->addChild("close_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/close");

					if($result_note->status == "closed")
						$note->addChild("date_closed", $result_note->date_closed);

					if($result_note->status == "closed")
						$note->addChild("reopen_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/reopen");

					$comments = $note->addChild("comments");

					if($query_note_comment = $resource->query("select note_comment.*, user.display_name as user from note_comment, user where ((user.id = note_comment.uid) and (note_comment.id = " . $result_note->id . "));"))
						{
						while($result_note_comment = $query_note_comment->fetch_object())
							{
							$comment = $comments->addChild("comment");

							foreach(array("date", "uid", "user", "action", "text") as $key)
								$comment->addChild($key, $result_note_comment->$key);
							}

						$query_note_comment->free_result();
						}
					}

				$query_note->free_result();
				}

			$resource->close();
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.7.5 Close: POST /api/0.6/notes/#id/close
################################################################################

if(preg_match("/api\/0\.6\/notes\/(\d*)\/close$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "POST")
		{
		check_login();

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			$uid = 0;

			if($query = $resource->query("select * from user where ((id = '" . $_SERVER["PHP_AUTH_USER"] . "') or (display_name = '" . $_SERVER["PHP_AUTH_USER"] . "') or (email = '" . $_SERVER["PHP_AUTH_USER"] . "'));"))
				{
				while($result = $query->fetch_object())
					$uid = $result->id;

				$query->free_result();
				}

			$resource->query("insert into note_comment (id, uid, date, action, text) values (" . $id . ", " . $uid . ", '" . date("Y-m-d\TH:i:s\Z") . "', 'closed', '" . (isset($_GET["text"]) === false ? "" : str_replace("'", "\'", $_GET["text"])) . "');");
			$resource->query("update note set date_closed = '" . date("Y-m-d\TH:i:s\Z") . "' where (id = " . $id . ");");
			$resource->query("update note set status = 'closed' where (id = " . $id . ");");

			$resource->close();
			}

		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			if($query_note = $resource->query("select * from note where (id = " . $id . ");"))
				{
				while($result_note = $query_note->fetch_object())
					{
					$note = $osm->addChild("note");

					foreach(array("lon", "lat") as $key)
						$note->addAttribute($key, $result_note->$key);

					foreach(array("id", "status", "date_created") as $key)
						$note->addChild($key, $result_note->$key);

					$note->addChild("url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id);

					if($result_note->status == "open")
						$note->addChild("comment_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/comment");

					if($result_note->status == "open")
						$note->addChild("close_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/close");

					if($result_note->status == "closed")
						$note->addChild("date_closed", $result_note->date_closed);

					if($result_note->status == "closed")
						$note->addChild("reopen_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/reopen");

					$comments = $note->addChild("comments");

					if($query_note_comment = $resource->query("select note_comment.*, user.display_name as user from note_comment, user where ((user.id = note_comment.uid) and (note_comment.id = " . $result_note->id . "));"))
						{
						while($result_note_comment = $query_note_comment->fetch_object())
							{
							$comment = $comments->addChild("comment");

							foreach(array("date", "uid", "user", "action", "text") as $key)
								$comment->addChild($key, $result_note_comment->$key);
							}

						$query_note_comment->free_result();
						}
					}

				$query_note->free_result();
				}

			$resource->close();
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.7.6 Reopen: POST /api/0.6/notes/#id/reopen
################################################################################

if(preg_match("/api\/0\.6\/notes\/(\d*)\/reopen$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null, $id) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "POST")
		{
		check_login();

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			$uid = 0;

			if($query = $resource->query("select * from user where ((id = '" . $_SERVER["PHP_AUTH_USER"] . "') or (display_name = '" . $_SERVER["PHP_AUTH_USER"] . "') or (email = '" . $_SERVER["PHP_AUTH_USER"] . "'));"))
				{
				while($result = $query->fetch_object())
					$uid = $result->id;

				$query->free_result();
				}

			$resource->query("insert into note_comment (id, uid, date, action, text) values (" . $id . ", " . $uid . ", '" . date("Y-m-d\TH:i:s\Z") . "', 'reopened', '" . (isset($_GET["text"]) === false ? "" : str_replace("'", "\'", $_GET["text"])) . "');");
			$resource->query("update note set date_closed = '' where (id = " . $id . ");");
			$resource->query("update note set status = 'open' where (id = " . $id . ");");

			$resource->close();
			}

		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			if($query_note = $resource->query("select * from note where (id = " . $id . ");"))
				{
				while($result_note = $query_note->fetch_object())
					{
					$note = $osm->addChild("note");

					foreach(array("lon", "lat") as $key)
						$note->addAttribute($key, $result_note->$key);

					foreach(array("id", "status", "date_created") as $key)
						$note->addChild($key, $result_note->$key);

					$note->addChild("url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id);

					if($result_note->status == "open")
						$note->addChild("comment_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/comment");

					if($result_note->status == "open")
						$note->addChild("close_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/close");

					if($result_note->status == "closed")
						$note->addChild("date_closed", $result_note->date_closed);

					if($result_note->status == "closed")
						$note->addChild("reopen_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/reopen");

					$comments = $note->addChild("comments");

					if($query_note_comment = $resource->query("select note_comment.*, user.display_name as user from note_comment, user where ((user.id = note_comment.uid) and (note_comment.id = " . $result_note->id . "));"))
						{
						while($result_note_comment = $query_note_comment->fetch_object())
							{
							$comment = $comments->addChild("comment");

							foreach(array("date", "uid", "user", "action", "text") as $key)
								$comment->addChild($key, $result_note_comment->$key);
							}

						$query_note_comment->free_result();
						}
					}

				$query_note->free_result();
				}

			$resource->close();
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# 2.7.7 Search for notes on text and comments: GET /api/0.6/notes/search
################################################################################

if(preg_match("/api\/0\.6\/notes\/search$/", $_SERVER["PHP_SELF"], $matches) == 1)
	{
	list($null) = $matches;

	if($_SERVER["REQUEST_METHOD"] == "GET")
		{
		if(isset($_GET["q"]) === false)
			exception_query_missed();
		else
			$q = $_GET["q"];

		if(isset($_GET["limit"]) === false)
			$limit = 100;
		elseif((isset($_GET["limit"]) === true) && ((strlen($_GET["limit"]) == 0) || (is_numeric($_GET["limit"]) === false) || ($_GET["limit"] < 1) || ($_GET["limit"] > 10000)))
			exception_limit_range();
		else
			$limit = $_GET["limit"];

		if(isset($_GET["closed"]) === false)
			$closed = "(note.status = 'open') or ((note.status = 'closed') and (note.date_closed > '" . date("Y-m-d\TH:i:s\Z", time() - (7 * 86400)) . "'))";
		elseif($_GET["closed"] == 0)
			$closed = "note.status = 'open'";
		elseif($_GET["closed"] == 0 - 1)
			$closed = "(note.status = 'open') or (note.status = 'closed')";
		else
			$closed = "(note.status = 'open') or ((note.status = 'closed') and (note.date_closed > '" . date("Y-m-d\TH:i:s\Z", time() - ($_GET["closed"] * 86400)) . "'))";

		$osm = new SimpleXMLElement("<osm />");
		$osm->addAttribute("version", OSM_VERSION);
		$osm->addAttribute("generator", OSM_GENERATOR);

		if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
			{
			$resource->query("set names 'utf8';");

			if($query = $resource->query("select distinct note.id from note, note_comment where ((note.id = note_comment.id) and (note_comment.text like '%" . $q . "%') and (" . $closed . ")) limit " . $limit . ";"))
				{
				while($result = $query->fetch_object())
					{
					if($query_note = $resource->query("select * from note where (id = " . $result->id . ");"))
						{
						while($result_note = $query_note->fetch_object())
							{
							$note = $osm->addChild("note");

							foreach(array("lon", "lat") as $key)
								$note->addAttribute($key, $result_note->$key);

							foreach(array("id", "status", "date_created") as $key)
								$note->addChild($key, $result_note->$key);

							$note->addChild("url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id);

							if($result_note->status == "open")
								$note->addChild("comment_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/comment");

							if($result_note->status == "open")
								$note->addChild("close_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/close");

							if($result_note->status == "closed")
								$note->addChild("date_closed", $result_note->date_closed);

							if($result_note->status == "closed")
								$note->addChild("reopen_url", "http://" . OSM_GENERATOR . "/api/" . OSM_VERSION . "/notes/" . $result_note->id . "/reopen");

							$comments = $note->addChild("comments");

							if($query_note_comment = $resource->query("select note_comment.*, user.display_name as user from note_comment, user where ((user.id = note_comment.uid) and (note_comment.id = " . $result_note->id . "));"))
								{
								while($result_note_comment = $query_note_comment->fetch_object())
									{
									$comment = $comments->addChild("comment");

									foreach(array("date", "uid", "user", "action", "text") as $key)
										$comment->addChild($key, $result_note_comment->$key);
									}

								$query_note_comment->free_result();
								}
							}

						$query_note->free_result();
						}
					}

				$query->free_result();
				}

			$resource->close();
			}

		$osm = $osm->asXML();

		header("Content-Type: text/xml; charset=utf-8");
		header("Content-Length: " . strlen($osm));

		print($osm);
		}
	}

################################################################################
# ...
################################################################################

function check_login()
	{
	if((isset($_SERVER["PHP_AUTH_USER"]) === false) || (strlen($_SERVER["PHP_AUTH_USER"]) == 0))
		exception_auth_invalid();

	if((isset($_SERVER["PHP_AUTH_PW"]) === false) || (strlen($_SERVER["PHP_AUTH_PW"]) == 0))
		exception_auth_invalid();

	$authenticated = false;

	if($resource = new mysqli(OSM_HOST, OSM_USER, OSM_PASS, OSM_NAME, OSM_PORT))
		{
		$resource->query("set names 'utf8';");

		if($query = $resource->query("select * from user where ((id = '" . $_SERVER["PHP_AUTH_USER"] . "') or (email = '" . $_SERVER["PHP_AUTH_USER"] . "') or (display_name = '" . $_SERVER["PHP_AUTH_USER"] . "'));"))
			{
			while($result = $query->fetch_object())
				$authenticated = ($result->pass == $_SERVER["PHP_AUTH_PW"]);

			$query->free_result();
			}

		$resource->close();
		}

	if($authenticated === false)
		exception_auth_invalid();
	}

################################################################################
# ...
################################################################################

function bboxs($min_lon, $min_lat, $max_lon, $max_lat)
	{
	return("(lon between " . $min_lon . " and " . $max_lon . ") and (lat between " . $min_lat . " and " . $max_lat . ")");
	}

function check_bbox_area($min_lon, $min_lat, $max_lon, $max_lat)
	{
	if(($max_lat - $min_lat) * ($max_lon - $min_lon) > OSM_AREA_MAXIMUM)
		return(false);
	else
		return(true);
	}

function check_bbox_range($min_lon, $min_lat, $max_lon, $max_lat)
	{
	if($max_lat < $min_lat)
		return(false);

	if($max_lon < $min_lon)
		return(false);

	if($min_lat < 0 - 90)
		return(false);

	if($min_lat > 0 + 90)
		return(false);

	if($max_lat < 0 - 90)
		return(false);

	if($max_lat > 0 + 90)
		return(false);

	if($min_lon < 0 - 180)
		return(false);

	if($min_lon > 0 + 180)
		return(false);

	if($max_lon < 0 - 180)
		return(false);

	if($max_lon > 0 + 180)
		return(false);

	return(true);
	}

function exception($text)
	{
	header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
	header("Content-Type: text/plain; charset=utf-8");

	die($text);
	}

function exception_auth_invalid()
	{
	header($_SERVER["SERVER_PROTOCOL"] . " 401 Unauthorized");
	header("Content-Type: text/plain; charset=utf-8");

	header("WWW-Authenticate: basic realm=\"Web Password\"");

	die("Couldn't authenticate you");
	}

function exception_bbox_area()
	{
	exception("The maximum bbox size is " . OSM_AREA_MAXIMUM . ", and your request was too large. Either request a smaller area, or use planet.osm");
	}

function exception_bbox_range()
	{
	exception("The latitudes must be between -90 and 90, longitudes between -180 and 180 and the minima must be less than the maxima.");
	}

function exception_bbox_missed()
	{
	exception("The parameter bbox is required, and must be of the form min_lon,min_lat,max_lon,max_lat.");
	}

function exception_changeset_missed()
	{
	exception("No changesets were given to search for");
	}

function exception_date_invalid()
	{
	exception("invalid date");
	}

function exception_lat_invalid()
	{
	exception("lat was not a number");
	}

function exception_lat_missed()
	{
	exception("No lat was given");
	}

function exception_limit_range()
	{
	exception("Note limit must be between 1 and 10000");
	}

function exception_lon_invalid()
	{
	exception("lon was not a number");
	}

function exception_lon_missed()
	{
	exception("No lon was given");
	}

function exception_object_missed($type)
	{
	exception("The parameter " . $type . "s is required, and must be of the form " . $type . "s=id[,id[,id...]].");
	}

function exception_query_missed()
	{
	exception("No query string was given");
	}

function exception_text_missed()
	{
	exception("No text was given");
	}

function exception_user_invalid()
	{
	exception("invalid user ID");
	}

function exception_zoom_range()
	{
	exception("Requested zoom is invalid, or the supplied start is after the end time, or the start duration is more than 24 hours");
	}

function exception_id_differs($url_id, $xml_id)
	{
	exception("The id in the url (" . $url_id . ") is not the same as provided in the xml (" . $xml_id . ")");
	}
?>
