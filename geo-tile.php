<?
################################################################################
# tile.php - Copyright 2015 by Markus Olderdissen
#
# this file is payware.
#
# you are not allowed to ...
# ... use this file without written permission
# ... use this file for inpiration on how to make things work
# ... modify this file
################################################################################

#exit;
ini_set("display_errors", "On");
ini_set("error_reporting", E_ALL);

include("google_projection.php");

define("FORCE_REDRAW", true);

if($resource = new mysqli("127.0.0.1", "root", "34096", "osm", "3306"))
	$resource->query("set names 'utf8';");

$timestamp = 0;

# printx(create_tile_data(17,68671,43278));exit;

################################################################################
# ...
################################################################################

function printx($data)
	{
	header("Content-Disposition: inline; filename=\"tile.svg\"");
	header("Content-Type: image/svg+xml; charset=\"utf-8\"");
	header("Content-Length: " . strlen($data));

	print($data);
	}

################################################################################
# ...
################################################################################

if(isset($argc) === false)
	{
	header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
	header("Error: This is render script.");

	die("This is render script.");
	}

################################################################################
# php tile.php
################################################################################

if($argc == 1)
	{
	list($null) = $argv;

	$areas = array();

	$areas[] = array(8.3778, 51.9148, 8.6634, 52.1148);
#	$areas[] = array(7.8608, 50.3417, 7.9135, 50.3690);

	foreach($areas as $area)
		foreach(range(16, 10) as $zoom)
			create_tiles_by_bbox($zoom, $area);
	}

################################################################################
# php tile.php <changeset>
################################################################################

if($argc == 2)
	{
	list($null, $changeset) = $argv;

#	foreach(range(19, 12) as $zoom)
#		{
#		create_tiles_by_changeset($zoom, $changeset);
#		}

	x_create_tiles_by_changeset($changeset);
	}

################################################################################
# php tile.php <z> <x> <y>
################################################################################

if($argc == 4)
	{
	list($null, $zoom, $x, $y) = $argv;

	create_tile($zoom, $x, $y);
	}

################################################################################
# ...
################################################################################

function create_tile($zoom, $x, $y)
	{
	global $timestamp;

	$timestamp = 0;

	$microtime = microtime(true);

	################################################################################
	# create folder for zoom
	################################################################################

	$folder = dirname(__FILE__) . "/tiles/" . $zoom;

	if(is_dir($folder) === false)
		mkdir($folder);

	chmod($folder, 0777);
	chown($folder, "nomatrix");
	chgrp($folder, "nomatrix");

	################################################################################
	# create folder for zoom / x
	################################################################################

	$folder = dirname(__FILE__) . "/tiles/" . $zoom . "/" . $x;

	if(is_dir($folder) === false)
		mkdir($folder);

	chmod($folder, 0777);
	chown($folder, "nomatrix");
	chgrp($folder, "nomatrix");

	################################################################################
	# create file for zoom / x / y
	################################################################################

	$file = dirname(__FILE__) . "/tiles/" . $zoom . "/" . $x . "/" . $y;

	print("create tile " . $file . ".png");

	$data = create_tile_data($zoom, $x, $y);

	$continue_render = (FORCE_REDRAW ? true : (file_exists($file . ".png") === false ? (strlen($data) == 1646 ? false : true) : ($timestamp > filemtime($file . ".png"))));

#print(strlen($data));
#exit;
	if($continue_render === false)
		{
		# in any case return value will be an empty svg.

		print(" skiped");
		}

	if($continue_render === true)
		{
		file_put_contents($file . ".svg", $data);

		exec("inkscape --export-area-page --without-gui --export-dpi=90 --export-png=" . $file . ".png " . $file . ".svg");

		unlink($file . ".svg");

		chmod($file . ".png", 0777);
		chown($file . ".png", "nomatrix");
		chgrp($file . ".png", "nomatrix");
		}

	print(" (" . number_format(microtime(true) - $microtime, 3, ".", "") . ")\n");
	}

################################################################################
# ...
################################################################################

function create_tiles_by_bbox($zoom, $bbox)
	{
	list($min_lon, $min_lat, $max_lon, $max_lat) = $bbox;

	$p = new google_projection($zoom);

	################################################################################
	# lon and lat into x and y
	################################################################################

	list($min_x, $min_y) = $p->from_lon_lat_to_tile(array($min_lon, $min_lat), $zoom);

	$min_x = intval($min_x / 256);
	$min_y = intval($min_y / 256);

	list($max_x, $max_y) = $p->from_lon_lat_to_tile(array($max_lon, $max_lat), $zoom);

	$max_x = intval($max_x / 256);
	$max_y = intval($max_y / 256);

	foreach(range($min_x, $max_x) as $x)
		foreach(range($min_y, $max_y) as $y)
			create_tile($zoom, $x, $y);
	}

################################################################################
# ...
################################################################################

function create_tiles_by_changeset($zoom, $id)
	{
	list($got_pos, $min_lon, $min_lat, $max_lon, $max_lat) = array(false, null, null, null, null);

	global $resource;

#	if($resource = new mysqli("127.0.0.1", "root", "34096", "osm", "3306"))
	if($resource)
		{
		################################################################################
		# init database connection
		################################################################################

#		$resource->query("set names 'utf8';");

		if($query = $resource->query("select * from changeset where (id = " . $id . ");"))
			{
			while($result = $query->fetch_object())
				list($got_pos, $min_lon, $min_lat, $max_lon, $max_lat) = array(true, $result->min_lon, $result->min_lat, $result->max_lon, $result->max_lat);

			$query->free_result();
			}

#		$resource->close();
		}

	################################################################################
	# finally create the tile
	################################################################################

	if($got_pos === true)
		create_tiles_by_bbox($zoom, array($min_lon, $min_lat, $max_lon, $max_lat), 1);
	}

function x_create_tiles_by_changeset($id)
	{
	list($got_run, $got_pos, $min_lon, $min_lat, $max_lon, $max_lat) = array(false, false, null, null, null, null);

#	if($resource = new mysqli("127.0.0.1", "root", "34096", "osm", "3306"))
	global $resource;

	if($resource)
		{
		################################################################################
		# init database connection
		################################################################################

#		$resource->query("set names 'utf8';");

		if($id != 0)
			{
			if($query = $resource->query("select * from tile limit 1;"))
				{
				while($result = $query->fetch_object())
					$got_run = true;

				$query->free_result();
				}
			}

		if($query = $resource->query("select * from changeset where (id = " . $id . ");"))
			{
			while($result = $query->fetch_object())
				list($got_pos, $min_lon, $min_lat, $max_lon, $max_lat) = array(true, $result->min_lon, $result->min_lat, $result->max_lon, $result->max_lat);

			$query->free_result();
			}

		if($got_pos === true)
			{
			$p = new google_projection(20);

			foreach(range(15, 5) as $zoom)
				{
				list($min_x, $min_y) = $p->from_lon_lat_to_tile(array($min_lon, $min_lat), $zoom);

				$min_x = intval($min_x / 256);
				$min_y = intval($min_y / 256);

				list($max_x, $max_y) = $p->from_lon_lat_to_tile(array($max_lon, $max_lat), $zoom);

				$max_x = intval($max_x / 256);
				$max_y = intval($max_y / 256);

				foreach(range($min_x, $max_x) as $x)
					foreach(range($min_y, $max_y) as $y)
						$resource->query("insert into tile (x, y, z) values (" . $x . ", " . $y . ", " . $zoom . ");");
				}
			}

		while($got_run === false)
			{
			$got_run = true;

			if($query = $resource->query("select * from tile order by z desc, x asc, y asc limit 1;"))
				{
				while($result = $query->fetch_object())
					{
					list($got_run, $x, $y, $zoom) = array(false, $result->x, $result->y, $result->z);

					create_tile($zoom, $x, $y);

					$resource->query("delete from tile where ((x = " . $x . ") and (y = " . $y . ") and (z = " . $zoom . "));");
					}

				$query->free_result();
				}
			}

#		$resource->close();
		}
	}

################################################################################
# ...
################################################################################

function create_tile_data($zoom, $x, $y)
	{
	################################################################################
	# draw everything
	################################################################################

	$svg = new SimpleXMLElement("<svg />");

	$svg->addAttribute("width", 256);
	$svg->addAttribute("height", 256);
	$svg->addAttribute("xmlns", "http://www.w3.org/2000/svg");
	$svg->addAttribute("xmlns:xmlns:xlink", "http://www.w3.org/1999/xlink");

	$defs = $svg->addChild("defs");

	$filter = $defs->addChild("filter");
	$filter->addAttribute("id", "halo");

	$femorphology = $filter->addChild("feMorphology");
	$femorphology->addAttribute("in", "SourceAlpha");
	$femorphology->addAttribute("result", "morphed");
	$femorphology->addAttribute("operator", "dilate");
	$femorphology->addAttribute("radius", 1);

	$femorphology = $filter->addChild("feColorMatrix");
	$femorphology->addAttribute("in", "morphed");
	$femorphology->addAttribute("result", "recolored");
	$femorphology->addAttribute("type", "matrix");
	$femorphology->addAttribute("values", "-1 0 0 0 1, 0 -1 0 0 1, 0 0 -1 0 1, 0 0 0 1 0");

	$femerge = $filter->addChild("feMerge");

	$femergenode = $femerge->addChild("feMergeNode");
	$femergenode->addAttribute("in", "recolored");

	$femergenode = $femerge->addChild("feMergeNode");
	$femergenode->addAttribute("in", "SourceGraphic");

	$pattern = $defs->addChild("pattern");

	foreach(array("id" => "forest", "x" => 0, "y" => 0, "width" => 32, "height" => 32, "patternUnits" => "userSpaceOnUse") as $k => $v)
		$pattern->addAttribute($k, $v);

	$rect = $pattern->addChild("rect");
	$rect->addAttribute("width", 32);
	$rect->addAttribute("height", 32);
	$rect->addAttribute("fill", "#8dc56c");

	$image = $pattern->addChild("image");
	$image->addAttribute("width", 21);
	$image->addAttribute("height", 24);
	$image->addAttribute("xmlns:xlink:href", dirname(__FILE__) . "/temp/own/mapnik/symbols/forest.png");

	$pattern = $defs->addChild("pattern");

	foreach(array("id" => "nature_reserve", "x" => 0, "y" => 0, "width" => 64, "height" => 64, "patternUnits" => "userSpaceOnUse") as $k => $v)
		$pattern->addAttribute($k, $v);

	$image = $pattern->addChild("image");
	$image->addAttribute("width", 42);
	$image->addAttribute("height", 48);
	$image->addAttribute("xmlns:xlink:href", dirname(__FILE__) . "/temp/own/mapnik/symbols/nature_reserve6.png");

	$pattern = $defs->addChild("pattern");

	foreach(array("id" => "grave_yard", "x" => 0, "y" => 0, "width" => 16, "height" => 16, "patternUnits" => "userSpaceOnUse") as $k => $v)
		$pattern->addAttribute($k, $v);

	$rect = $pattern->addChild("rect");
	$rect->addAttribute("width", 32);
	$rect->addAttribute("height", 32);
	$rect->addAttribute("fill", "#a9c9ae");

	$image = $pattern->addChild("image");
	$image->addAttribute("width", 16);
	$image->addAttribute("height", 16);
	$image->addAttribute("xmlns:xlink:href", dirname(__FILE__) . "/temp/own/mapnik/symbols/grave_yard.png");

	$pattern = $defs->addChild("pattern");

	foreach(array("id" => "scrub", "x" => 0, "y" => 0, "width" => 16, "height" => 16, "patternUnits" => "userSpaceOnUse") as $k => $v)
		$pattern->addAttribute($k, $v);

	$rect = $pattern->addChild("rect");
	$rect->addAttribute("width", 32);
	$rect->addAttribute("height", 32);
	$rect->addAttribute("fill", "#b5e3b5");

	$image = $pattern->addChild("image");
	$image->addAttribute("width", 30);
	$image->addAttribute("height", 30);
	$image->addAttribute("xmlns:xlink:href", dirname(__FILE__) . "/temp/own/mapnik/symbols/scrub.png");

	$pattern = $defs->addChild("pattern");

	foreach(array("id" => "wetland", "x" => 0, "y" => 0, "width" => 32, "height" => 32, "patternUnits" => "userSpaceOnUse") as $k => $v)
		$pattern->addAttribute($k, $v);

	$image = $pattern->addChild("image");
	$image->addAttribute("width", 30);
	$image->addAttribute("height", 30);
	$image->addAttribute("xmlns:xlink:href", dirname(__FILE__) . "/temp/own/mapnik/symbols/marsh.png");

	$p = new google_projection(20);

	list($min_lon, $min_lat) = $p->from_tile_to_lon_lat(array(($x + 0) * 256, ($y + 0) * 256), $zoom);
	list($max_lon, $max_lat) = $p->from_tile_to_lon_lat(array(($x + 1) * 256, ($y + 1) * 256), $zoom);

	list($min_x, $min_y) = $p->from_lon_lat_to_tile(array($min_lon, $min_lat), $zoom);
	list($max_x, $max_y) = $p->from_lon_lat_to_tile(array($max_lon, $max_lat), $zoom);

	################################################################################
	# calculate range, add additional size of tile to each side of tile
	################################################################################

	$lon_range = ($max_lon - $min_lon);
	$lat_range = ($max_lat - $min_lat);

	$lon_range = ($lon_range * 0.25) + ($lon_range * 0.75 / 19 * $zoom); # 25% (z = 0) - 100% (z = 19)
	$lat_range = ($lat_range * 0.25) + ($lat_range * 0.75 / 19 * $zoom); # 25% (z = 0) - 100% (z = 19)

	$left		= $min_lon - $lon_range;
	$right		= $max_lon + $lon_range;

	$bottom		= $max_lat + $lat_range;
	$top		= $min_lat - $lat_range;

	################################################################################
	# jede zoomstufe verdoppelt oder halbiert die auflösung
	# 1, 2, 4, 8, 16, ... 2 hoch x ... pow(2, x)
	################################################################################

	$scale = 256 / (6335439 * M_PI / pow(2, $zoom));
#	print($scale);
#	$scale = pow(2, $zoom) * 0.00001;
#	$scale = pow(2, $zoom) / 100000;
#	$scale = (1 << $zoom) / 100000;

	$trans = array("node" => array(), "way" => array(), "relation" => array());

#	if($resource = new mysqli("127.0.0.1", "root", "34096", "osm", "3306"))
	global $resource;
	global $timestamp;

	$timestamp = 0;

	if($resource)
		{
		################################################################################
		# init database connection
		################################################################################

#		$resource->query("set names 'utf8';");

		################################################################################
		# get all nodes of bbox
		################################################################################

		if($query = $resource->query("select * from node where ((lon between " . $left . " and " . $right . ") and (lat between " . $bottom . " and " . $top . ") and (visible = 'true'));"))
			{
			while($result = $query->fetch_object())
				{
				$trans["node"][$result->id] = $result->version;

				$timestamp = (strtotime($result->timestamp) > $timestamp ? strtotime($result->timestamp) : $timestamp);
				}

			$query->free_result();
			}

		################################################################################
		# get all ways of nodes
		################################################################################

		foreach($trans["node"] as $id => $version)
			{
			if($query = $resource->query("select way.* from way, way_nd where ((way_nd.id = way.id) and (way_nd.version = way.version) and (way_nd.ref = " . $id . ") and (way.visible = 'true'));"))
				{
				while($result = $query->fetch_object())
					{
					$trans["way"][$result->id] = $result->version;

					$timestamp = (strtotime($result->timestamp) > $timestamp ? strtotime($result->timestamp) : $timestamp);
					}

				$query->free_result();
				}
			}


		$file = dirname(__FILE__) . "/tiles/" . $zoom . "/" . $x . "/" . $y;

		$continue_render = (FORCE_REDRAW ? true : (file_exists($file . ".png") === false ? (count($trans["node"]) == 0 ? false : true) : ($timestamp > filemtime($file . ".png"))));

		if($continue_render === true)
			{
			################################################################################
			# layers
			################################################################################

			$layers = array();

			$layers[] = "landuse";
			$layers[] = "amenity";
			$layers[] = "leisure";
			$layers[] = "natural";
			$layers[] = "waterway";
			$layers[] = "building";
			$layers[] = "barrier";
			$layers[] = "tourism";
			$layers[] = "highway";
			$layers[] = "railway";
			$layers[] = "public_transport";
			$layers[] = "shop";
			$layers[] = "power";

			$g = array();

			foreach(array("default", "tunnel", "normal", "bridge", "heaven") as $level)
				{
				$g[$level] = $svg->addChild("g");
				$g[$level]->addAttribute("id", $level);
				$g[$level]->addAttribute("fill", "none");
				$g[$level]->addAttribute("stroke", "silver");

				foreach($layers as $layer)
					{
					if((($layer == "highway") || ($layer == "railway")) && ($level == "default"))
						continue;

					$g[$level . "-" . $layer] = $g[$level]->addChild("g");
					$g[$level . "-" . $layer]->addAttribute("id", $layer);

					if(($layer == "highway") || ($layer == "railway"))
						{
						foreach(array("default", "outer", "inner") as $c)
							{
							$g[$level . "-" . $layer . "-" . $c] = $g[$level . "-" . $layer]->addChild("g");
							$g[$level . "-" . $layer . "-" . $c]->addAttribute("id", $c);
							}
						}
					}
				}

			$layers[] = "boundary";
			$layers[] = "place";

			$g["tunnel-highway"]->addAttribute("stroke-linecap", "round");
			$g["tunnel-highway"]->addAttribute("stroke-linejoin", "round");
			$g["normal-highway"]->addAttribute("stroke-linecap", "round");
			$g["normal-highway"]->addAttribute("stroke-linejoin", "round");
			$g["bridge-highway"]->addAttribute("stroke-linecap", "round");
			$g["bridge-highway"]->addAttribute("stroke-linejoin", "round");

			$g["tunnel-railway"]->addAttribute("stroke-linecap", "butt");;
			$g["tunnel-railway"]->addAttribute("stroke-linejoin", "round");;
			$g["normal-railway"]->addAttribute("stroke-linecap", "butt");;
			$g["normal-railway"]->addAttribute("stroke-linejoin", "round");;
			$g["bridge-railway"]->addAttribute("stroke-linecap", "butt");;
			$g["bridge-railway"]->addAttribute("stroke-linejoin", "round");;

			$g["boundary"] = $svg->addChild("g");
			$g["boundary"]->addAttribute("id", "boundary");
			$g["boundary"]->addAttribute("fill", "none");

			$g["text"] = $svg->addChild("g");
			$g["text"]->addAttribute("id", "text");
			$g["text"]->addAttribute("font-family", "Tahoma");

			$g["tunnel"]->addAttribute("stroke-opacity", 0.4);

			################################################################################
			# layers for highways
			################################################################################

			$highways = array();

			$highways[] = "track";
			$highways[] = "cycleway";
			$highways[] = "footway";
			$highways[] = "steps";
			$highways[] = "path";
			$highways[] = "platform";
			$highways[] = "pedestrian";
			$highways[] = "construction";
			$highways[] = "proposed";
			$highways[] = "service";
			$highways[] = "services";
			$highways[] = "living_street";
			$highways[] = "unclassified";
			$highways[] = "residential";
			$highways[] = "road";
			$highways[] = "primary_link";
			$highways[] = "secondary_link";
			$highways[] = "tertiary_link";
			$highways[] = "trunk_link";
			$highways[] = "motorway_link";
			$highways[] = "primary";
			$highways[] = "secondary";
			$highways[] = "tertiary";
			$highways[] = "trunk";
			$highways[] = "motorway";

			foreach(array("tunnel", "normal", "bridge", "heaven") as $level) # highway doesn't use default level !!!
				{
				foreach(array("default", "outer", "inner") as $c)
					{
					foreach($highways as $d)
						{
						$g[$level . "-highway-" . $c . "-" . $d] = $g[$level . "-highway-" . $c]->addChild("g");
						$g[$level . "-highway-" . $c . "-" . $d]->addAttribute("id", $d);
						}
					}
				}

			foreach($trans["way"] as $id => $version)
				{
				if($query_way = $resource->query("select * from way where ((id = " . $id . ") and (version = " . $version . "));"))
					{
					while($result_way = $query_way->fetch_object())
						{
						################################################################################
						# get properties of way
						################################################################################

						$properties = get_properties("way", $id, $version);

						################################################################################
						# init waypoints
						################################################################################

						$waypoints = array();

						foreach($layers as $type) # $layer will be used also for default, tunnel, normal, bridge, heaven
							{
							if(isset($properties[$type]) === false)
								continue;


							$sorter = $properties[$type];

							if($type == "amenity")
								{
								$font_size = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1);

								if($font_size[$zoom] == 0)
									continue;

								$color = array(
									"*" => "none",
									"arts_centre" => "#beadad",
									"grave_yard" => "url(#grave_yard)",
									"kindergarten" => "#f0f0d8",
									"parking" => "#f6eeb6",
									"parking_space" => "#f6eeb6",
									"public_building" => "#beadad",
									"school" => "#f0f0d8"
									);

								$color = (isset($color[$sorter]) === false ? $color["*"] : $color[$sorter]);

								################################################################################
								# get nodes of way
								################################################################################

								if(count($waypoints) == 0)
									{
									$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

									$svg_path = get_svg_path($waypoints);
									}

								$path = $g["default-" . $type]->addChild("path");
								$path->addAttribute("id", $id);
								$path->addAttribute("fill", $color);
								$path->addAttribute("d", $svg_path);
								}

							if($type == "boundary")
								{
								$color = array(
									"*" => "none",
									"administrative" => "purple",
									"cadastre" => "blue",
									"maritime" => "black",
									"national_park" => "green",
									"political" => "orange",
									"postal_code" => "yellow",
									"protected_area" => "green"
									);

								$color = (isset($color[$sorter]) === false ? $color["*"] : $color[$sorter]);

								$admin_level = (isset($properties["admin_level"]) === false ? 11 : $properties["admin_level"]);
								$widths = array(0, 0, 7, 5, 4, 2, 2, 2, 2, 2, 2, 2, 0);
								$dasharrays = array(array(0, 0), array(0, 0), array(1, 0), array(5, 2), array(4, 3), array(6, 3, 2, 3, 2, 3), array(6, 3, 2, 3), array(5, 2), array(5, 2), array(2, 3), array(2, 3), array(2, 3), array(0, 0));

								################################################################################
								# glue waypoints
								################################################################################

								if(count($waypoints) == 0)
									{
									$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

									$svg_path = get_svg_path($waypoints);
									}

								$path = $g[$type]->addChild("path");
								$path->addAttribute("id", $id);
								$path->addAttribute("stroke", $color);
								$path->addAttribute("stroke-dasharray", implode(", ", $dasharrays[$admin_level]));
								$path->addAttribute("stroke-width", $widths[$admin_level]);
								$path->addAttribute("d", $svg_path);
								}

							if($type == "building")
								{
								$color = array(
									"*" => "#beadad",
									"church" => "#aeaeae",
									"commercial" => "#beadad",
									"grave_yard" => "#a9c9ae"
									);

								$color = (isset($color[$sorter]) === false ? $color["*"] : $color[$sorter]);

								$font_size = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1);

								if($font_size[$zoom] == 0)
									continue;

								################################################################################
								# get nodes of way
								################################################################################

								if(count($waypoints) == 0)
									{
									$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

									$svg_path = get_svg_path($waypoints);
									}

								$path = $g["default-" . $type]->addChild("path");
								$path->addAttribute("id", $id);
								$path->addAttribute("fill", $color);
								$path->addAttribute("d", $svg_path);

								if($properties["addr:housenumber"] == "")
									continue;

								$font_size = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 9, 9, 9);

								if($font_size[$zoom] == 0)
									continue;

								list($waypoint_start_x, $waypoint_start_y) = reset($waypoints);
								list($waypoint_stop_x, $waypoint_stop_y) = end($waypoints);

								if(($waypoint_start_x == $waypoint_stop_x) && ($waypoint_start_y == $waypoint_stop_y))
									$area_center = get_area_center($waypoints);

								list($text_width, $text_height) = get_text_size("Tahoma", $font_size[$zoom], $properties["addr:housenumber"]);

								list($x, $y) = $area_center;

								$x = $x - sqrt($text_width); # sqrt works best. but is it right?
								$y = $y + sqrt($text_height);

								$text = $g["text"]->addChild("text", htmlspecialchars($properties["addr:housenumber"]));
								$text->addAttribute("font-size", ($scale * 6));
								$text->addAttribute("fill", "black");
								$text->addAttribute("x", $x);
								$text->addAttribute("y", $y);
#								$text->addAttribute("dy", + (sqrt($text_width) * $scale));
#								$text->addAttribute("dx", - ((($text_width / 2) * $scale)));
								}

							if($type == "highway")
								{
								$color = array(
									"*" => "none",
									"cycleway" => "blue",
									"construction" => "white",
									"footway" => "#f98173",
									"living_street" => "#cccccc",
									"motorway" => "#7f9afe",
									"motorway_link" => "#7f9afe",
									"parking_aisle" => "white",
									"path" => "#69675D",
									"pedestrian" => "white",
									"proposed" => "grey",
									"primary" => "#fea980",
									"primary_link" => "#fea980",
									"residential" => "white",
									"road" => "#dcdcdc",
									"secondary" => "#ffd080",
									"secondary_link" => "#ffd080",
									"service" => "white",
									"tertiary" => "#fdfdb3",
									"tertiary_link" => "#fdfdb3",
									"track" => "#AC8432",
									"trunk" => "#ff8095",
									"trunk_link" => "#ff8095",
									"unclassified" => "white"
									);

								$width = array(
									"*" => 3.75,
									"primary_link" => 3.75,
									"primary" => 3.75,
									"secondary_link" => 3.75,
									"secondary" => 3.75,
									"trunk_link" => 3.75,
									"trunk" => 3.75,
									"motorway_link" => 3.75,
									"motorway" => 3.75,
									"cycleway" => 0.50,
									"footway" => 0.50,
									"path" => 0.50,
									"service" => 2.50,
									"services" => 2.50,
									"steps" => 0.50,
									"track" => 0.50,
									);

								# on highway=construction, we have construction=* in here now.
								$helper = (isset($properties[$sorter]) === false ? "*" : $properties[$sorter]);
								$helper = (isset($color[$helper]) === false ? $color["*"] : $color[$helper]); # helper color

								$lanes = (isset($properties["lanes"]) === false ? 1 : $properties["lanes"]); # per direction
								$oneway = (isset($properties["oneway"]) === false ? "no" : $properties["oneway"]);

#								$width = (isset($properties["width"]) === false ? $width["*"] : $properties["width"]);

								$width = (isset($width[$sorter]) === false ? $width["*"] : $width[$sorter]);
								$color = (isset($color[$sorter]) === false ? $color["*"] : $color[$sorter]);

								$width = ($width * $lanes * ($oneway == "no" ? 2 : 1));

								$layer = "normal"; # don't use default level !!!
								$layer = ($properties["tunnel"] == "yes" ? "tunnel" : $layer);
								$layer = ($properties["bridge"] == "yes" ? "bridge" : $layer);

								# bridleway
								# bus_guideway

								if($sorter == "construction" || $sorter == "proposed")
									{
									$font_size = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1);

									if($font_size[$zoom] == 0)
										continue;

									################################################################################
									# get nodes of way
									################################################################################

									if(count($waypoints) == 0)
										{
										$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

										$svg_path = get_svg_path($waypoints);
										}

									if($layer == "bridge")
										{
										$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
										$path->addAttribute("stroke", "black");
										$path->addAttribute("stroke-width", ($scale * ($width + 1.5)));
										$path->addAttribute("d", $svg_path);

										$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
										$path->addAttribute("stroke", "white");
										$path->addAttribute("stroke-width", ($scale * ($width + 1.0)));
										$path->addAttribute("d", $svg_path);
										}

									$path = $g[$layer . "-" . $type . "-outer-" . $sorter]->addChild("path");
									$path->addAttribute("stroke-width", ($scale * ($width + 0.5)));
									$path->addAttribute("d", $svg_path);

									$path = $g[$layer . "-" . $type . "-inner-" . $sorter]->addChild("path");
									$path->addAttribute("id", $id);
									$path->addAttribute("stroke", $color); # highway=*
									$path->addAttribute("stroke-width", ($scale * ($width + 0.0)));
									$path->addAttribute("d", $svg_path);

									################################################################################
									# construction drawing
									################################################################################

									$path = $g[$layer . "-" . $type . "-inner-" . $sorter]->addChild("path");
									$path->addAttribute("id", $id);
									$path->addAttribute("stroke", $helper); # construction=*
									$path->addAttribute("stroke-linecap", "butt"); # !!!
									$path->addAttribute("stroke-width", ($scale * ($width + 0.0)));
									$path->addAttribute("stroke-dasharray", ($scale * 9) . ", " . ($scale * 9));
									$path->addAttribute("d", $svg_path);
									}

								if($sorter == "cycleway" || $sorter == "footway" || $sorter == "steps")
									{
									$font_size = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1);

									if($font_size[$zoom] == 0)
										continue;

									################################################################################
									# get nodes of way
									################################################################################

									if(count($waypoints) == 0)
										{
										$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

										$svg_path = get_svg_path($waypoints);
										}

									if($layer == "bridge")
										{
										$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
										$path->addAttribute("stroke", "black");
										$path->addAttribute("stroke-width", ($scale * ($width + 1.5)));
										$path->addAttribute("d", $svg_path);

										$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
										$path->addAttribute("stroke", "white");
										$path->addAttribute("stroke-width", ($scale * ($width + 1.0)));
										$path->addAttribute("d", $svg_path);
										}

									$path = $g[$layer . "-" . $type . "-outer-" . $sorter]->addChild("path");
									$path->addAttribute("stroke-width", ($scale * ($width + 0.5)));
									$path->addAttribute("stroke-opacity", 0.4);
									$path->addAttribute("d", $svg_path);

									$path = $g[$layer . "-" . $type . "-inner-" . $sorter]->addChild("path");
									$path->addAttribute("id", $id);
									$path->addAttribute("stroke", $color);
									$path->addAttribute("stroke-width", ($scale * ($width + 0.0)));
									$path->addAttribute("stroke-dasharray", ($scale * 2) . ", " . ($scale * 2));
									$path->addAttribute("d", $svg_path);
									}

								if($sorter == "living_street" || $sorter == "motorway" || $sorter == "motorway_link" || $sorter == "primary" || $sorter == "primary_link" || $sorter == "secondary" || $sorter == "secondary_link" || $sorter == "residential" || $sorter == "road" || $sorter == "tertiary" || $sorter == "tertiary_link" || $sorter == "trunk" || $sorter == "trunk_link" || $sorter == "unclassified")
									{
									$font_size = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1);

									if($font_size[$zoom] == 0)
										continue;

									################################################################################
									# get nodes of way
									################################################################################

									if(count($waypoints) == 0)
										{
										$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

										$svg_path = get_svg_path($waypoints);
										}

									if($layer == "bridge")
										{
										$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
										$path->addAttribute("stroke", "black");
										$path->addAttribute("stroke-width", ($scale * ($width + 1.5)));
										$path->addAttribute("d", $svg_path);

										$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
										$path->addAttribute("stroke", "white");
										$path->addAttribute("stroke-width", ($scale * ($width + 1.0)));
										$path->addAttribute("d", $svg_path);
										}

									$path = $g[$layer . "-" . $type . "-outer-" . $sorter]->addChild("path");
									$path->addAttribute("stroke-width", ($scale * ($width + 0.5)));
									$path->addAttribute("d", $svg_path);

									$path = $g[$layer . "-" . $type . "-inner-" . $sorter]->addChild("path");
									$path->addAttribute("id", $id);
									$path->addAttribute("stroke", $color);
									$path->addAttribute("stroke-width", ($scale * ($width + 0.00)));
									$path->addAttribute("d", $svg_path);
									}

								if($sorter == "path")
									{
									$font_size = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1);

									if($font_size[$zoom] == 0)
										continue;

									################################################################################
									# get nodes of way
									################################################################################

									if(count($waypoints) == 0)
										{
										$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

										$svg_path = get_svg_path($waypoints);
										}

									if($layer == "bridge")
										{
										$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
										$path->addAttribute("stroke", "black");
										$path->addAttribute("stroke-width", ($scale * ($width + 1.5)));
										$path->addAttribute("d", $svg_path);

										$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
										$path->addAttribute("stroke", "white");
										$path->addAttribute("stroke-width", ($scale * ($width + 1.0)));
										$path->addAttribute("d", $svg_path);
										}

									$path = $g[$layer . "-" . $type . "-default-" . $sorter]->addChild("path");
									$path->addAttribute("id", $id);
									$path->addAttribute("stroke", $color);
									$path->addAttribute("stroke-width", ($scale * ($width + 0.0)));
									$path->addAttribute("stroke-dasharray", ($scale * 2) . ", " . ($scale * 1));
									$path->addAttribute("d", $svg_path);
									}

								if($sorter == "pedestrian")
									{
									$font_size = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1);

									if($font_size[$zoom] == 0)
										continue;

									################################################################################
									# get nodes of way
									################################################################################

									if(count($waypoints) == 0)
										{
										$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

										$svg_path = get_svg_path($waypoints);
										}

									if($properties["area"] == "yes")
										{
										$path = $g["bridge-" . $type . "-default-" . $sorter]->addChild("path");
										$path->addAttribute("id", $id);
										$path->addAttribute("fill", "white");
										$path->addAttribute("d", $svg_path);
										}

									if($properties["area"] == "no")
										{
										$path = $g[$layer . "-" . $type . "-outer-" . $sorter]->addChild("path");
										$path->addAttribute("stroke-width", ($scale * 3));
										$path->addAttribute("d", $svg_path);

										$path = $g[$layer . "-" . $type . "-inner-" . $sorter]->addChild("path");
										$path->addAttribute("id", $id);
										$path->addAttribute("stroke", $color);
										$path->addAttribute("stroke-width", ($scale * 2));
										$path->addAttribute("d", $svg_path);
										}
									}

								if($sorter == "platform")
									{
									$font_size = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1);

									if($font_size[$zoom] == 0)
										continue;

									################################################################################
									# get nodes of way
									################################################################################

									if(count($waypoints) == 0)
										{
										$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

										$svg_path = get_svg_path($waypoints);
										}

									$path = $g[$layer . "-" . $type . "-default-" . $sorter]->addChild("path");
									$path->addAttribute("id", $id);
									$path->addAttribute("fill", "#bababa");
									$path->addAttribute("d", $svg_path);
									}

								# raceway

								if($sorter == "service" || $sorter == "services")
									{
									$font_size = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1);

									if($font_size[$zoom] == 0)
										continue;

									################################################################################
									# get nodes of way
									################################################################################

									if(count($waypoints) == 0)
										{
										$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

										$svg_path = get_svg_path($waypoints);
										}

									if($layer == "bridge")
										{
										$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
										$path->addAttribute("stroke", "black");
										$path->addAttribute("stroke-width", ($scale * ($width + 1.5)));
										$path->addAttribute("d", $svg_path);

										$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
										$path->addAttribute("stroke", "white");
										$path->addAttribute("stroke-width", ($scale * ($width + 1.0)));
										$path->addAttribute("d", $svg_path);
										}

									$path = $g[$layer . "-" . $type . "-outer-" . $sorter]->addChild("path");
									$path->addAttribute("stroke-width", ($scale * ($width + 0.5)));
									$path->addAttribute("d", $svg_path);

									$path = $g[$layer . "-" . $type . "-inner-" . $sorter]->addChild("path");
									$path->addAttribute("id", $id);
									$path->addAttribute("stroke", $color);
									$path->addAttribute("stroke-width", ($scale * ($width + 0.0)));
									$path->addAttribute("d", $svg_path);
									}

								# steps

								if($sorter == "track")
									{
									$font_size = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1);

									if($font_size[$zoom] == 0)
										continue;

									################################################################################
									# get nodes of way
									################################################################################

									if(count($waypoints) == 0)
										{
										$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

										$svg_path = get_svg_path($waypoints);
										}

									if($layer == "bridge")
										{
										$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
										$path->addAttribute("stroke", "black");
										$path->addAttribute("stroke-width", ($scale * ($width + 1.5)));
										$path->addAttribute("d", $svg_path);

										$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
										$path->addAttribute("stroke", "white");
										$path->addAttribute("stroke-width", ($scale * ($width + 1.0)));
										$path->addAttribute("d", $svg_path);
										}

									$path = $g[$layer . "-" . $type . "-outer-" . $sorter]->addChild("path");
									$path->addAttribute("stroke", "white");
									$path->addAttribute("stroke-width", ($scale * ($width + 0.5)));
									$path->addAttribute("d", $svg_path);

									$path = $g[$layer . "-" . $type . "-inner-" . $sorter]->addChild("path");
									$path->addAttribute("id", $id);
									$path->addAttribute("stroke", $color);
									$path->addAttribute("stroke-width", ($scale * ($width + 0.0)));
									$path->addAttribute("stroke-dasharray", ($scale * 4) . ", " . ($scale * 2));
									$path->addAttribute("d", $svg_path);
									}

								$font_size = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1);

								if($font_size[$zoom] == 0)
									continue;

								if($properties["area"] == "yes")
									continue;

								if($properties["name"] == "")
									continue;

								if(count($waypoints) == 0)
									{
									$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

#									$svg_path = get_svg_path($waypoints);
									}

								$way_length = get_way_length($waypoints);

								list($text_width, $text_height) = get_text_size("Tahoma", $scale * 5, $properties["name"]);

								if($text_width > $way_length)
									continue;

								if(get_way_direction($waypoints) == 1)
									$waypoints = array_reverse($waypoints);

								$svg_path = get_svg_path($waypoints);

								$path = $g["text"]->addChild("path");
								$path->addAttribute("id", "x-" . $id);
								$path->addAttribute("stroke", "none");
								$path->addAttribute("fill", "none");
								$path->addAttribute("d", $svg_path);

								$text = $g["text"]->addChild("text");
								$text->addAttribute("font-size", ($scale * 5));
								$text->addAttribute("fill", "black");
								$text->addAttribute("dy", ($scale * (5 / 3)));

								$text_path = $text->addChild("textPath", htmlspecialchars($properties["name"]));
								$text_path->addAttribute("xmlns:xlink:href", "#x-" . $id);
								$text_path->addAttribute("startOffset", "50%");
								$text_path->addAttribute("text-anchor", "middle");
								}

							if($type == "landuse")
								{
								$color = array(
									"*" => "none",
									"allotments" => "#ead8bd",
									"basin" => "#acd7f2",
									"brownfield" => "#afaf8c",
									"cemetery" => "url(#grave_yard)",
									"commercial" => "#eec8c8",
									"construction" => "#b0b08e",
									"farmland" => "#ead8bd",
									"farmyard" => "#dcbe91",
									"forest" => "url(#forest)",
									"grass" => "#cfeca8",
									"industrial" => "#ded0d5",
									"landfill" => "#ded0d5",
									"meadow" => "#cfeca8",
									"military" => "#fe8585",
									"railway" => "#ded0d5",
									"recreation_ground" => "#cfeca8",
									"reservoir" => "#acd7f2",
									"residential" => "#dcdcdc",
									"retail" => "#f0d9d9",
									"village_green" => "#cfeca8"
									);

								$color = (isset($color[$sorter]) === false ? $color["*"] : $color[$sorter]);

								$font_size = array(0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1);

								if($font_size[$zoom] == 0)
									continue;

								################################################################################
								# get nodes of way
								################################################################################

								if(count($waypoints) == 0)
									{
									$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

									$svg_path = get_svg_path($waypoints);
									}

								$path = $g["default-" . $type]->addChild("path");
								$path->addAttribute("id", $id);
								$path->addAttribute("fill", $color);
								$path->addAttribute("d", $svg_path);
								}

							if($type == "leisure")
								{
								$color = array(
									"*" => "none",
									"golf_course" => "#b4e2b4",
									"nature_reserve" => "url(#nature_reserve)",
									"park" => "#cef6ca",
									"pitch" => "#89d2ae",
									"playground" => "#ccfff1",
									"sports_centre" => "#beadad",
									"stadium" => "#33cc99"
									);

								$color = (isset($color[$sorter]) === false ? $color["*"] : $color[$sorter]);

								$font_size = array(0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1);

								if($font_size[$zoom] == 0)
									continue;

								################################################################################
								# get nodes of way
								################################################################################

								if(count($waypoints) == 0)
									{
									$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

									$svg_path = get_svg_path($waypoints);
									}

								$path = $g["default-" . $type]->addChild("path");
								$path->addAttribute("id", $id);
								$path->addAttribute("fill", $color);
								$path->addAttribute("d", $svg_path);
								}

							if($type == "natural")
								{
								$color = array(
									"*" => "none",
									"grassland" => "#c6e4b4",
									"water" => "#acd7f2",
									"sand" => "#fedf88",
									"scrub" => "url(#scrub)",
									"wetland" => "url(#wetland)",
									"wood" => "#8dc56c"
									);

								$color = $color[isset($color[$sorter]) === false ? "*" : $sorter];

								################################################################################
								# get nodes of way
								################################################################################

								if(count($waypoints) == 0)
									{
									$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

									$svg_path = get_svg_path($waypoints);
									}

								$path = $g["default-" . $type]->addChild("path");
								$path->addAttribute("id", $id);
								$path->addAttribute("fill", $color);
								$path->addAttribute("d", $svg_path);
								}

							if($type == "power")
								{
								$color = array(
									"*" => "none",
									"generator" => "#bababa",
									"line" => "grey",
									"minor_line" => "grey"
									);

								$color = (isset($color[$sorter]) === false ? $color["*"] : $color[$sorter]);

								if($sorter == "generator")
									{
									################################################################################
									# get nodes of way
									################################################################################

									if(count($waypoints) == 0)
										{
										$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

										$svg_path = get_svg_path($waypoints);
										}

	#									$path->addAttribute("fill", $color);
									}

								if($sorter == "line")
									{
									$font_size = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1);

									if($font_size[$zoom] == 0)
										continue;

									################################################################################
									# get nodes of way
									################################################################################

									if(count($waypoints) == 0)
										{
										$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

										$svg_path = get_svg_path($waypoints);
										}

									$path = $g["heaven-" . $type]->addChild("path");
									$path->addAttribute("id", $id);
									$path->addAttribute("stroke", $color);
									$path->addAttribute("stroke-width", 1);
									$path->addAttribute("d", $svg_path);
									}

								if($sorter == "minor_line")
									{
									$font_size = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1);

									if($font_size[$zoom] == 0)
										continue;

									################################################################################
									# get nodes of way
									################################################################################

									if(count($waypoints) == 0)
										{
										$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

										$svg_path = get_svg_path($waypoints);
										}

									$path = $g["heaven-" . $type]->addChild("path");
									$path->addAttribute("id", $id);
									$path->addAttribute("stroke", $color);
									$path->addAttribute("stroke-width", 1);
									$path->addAttribute("d", $svg_path);
									}
								}

							if($type == "public_transport")
								{
								################################################################################
								# get nodes of way
								################################################################################

								if(count($waypoints) == 0)
									{
									$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

									$svg_path = get_svg_path($waypoints);
									}

								if($sorter == "platform")
									{
									$path = $g["default-" . $type]->addChild("path");
									$path->addAttribute("id", $id);
									$path->addAttribute("fill", "#bababa");
									$path->addAttribute("d", $svg_path);
									}
								}

							if($type == "railway")
								{
								################################################################################
								# get nodes of way
								################################################################################

								if(count($waypoints) == 0)
									{
									$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

									$svg_path = get_svg_path($waypoints);
									}

								$layer = "normal"; # don't use default level !!!
								$layer = ($properties["tunnel"] == "yes" ? "tunnel" : $layer);
								$layer = ($properties["bridge"] == "yes" ? "bridge" : $layer);

								$gauge = (isset($properties["gauge"]) === false ? 1.435 : $properties["gauge"]);

								# abadoned
								# construction

								if($sorter == "disused")
									{
									$path = $g[$layer . "-" . $type . "-outer"]->addChild("path");
									$path->addAttribute("stroke", "#999999");
									$path->addAttribute("stroke-width", ($scale * 3));
									$path->addAttribute("stroke-opacity", 0.4);
									$path->addAttribute("d", $svg_path);

									$path = $g[$layer . "-" . $type . "-inner"]->addChild("path");
									$path->addAttribute("id", $id);
									$path->addAttribute("stroke", "white");
									$path->addAttribute("stroke-width", ($scale * 2));
									$path->addAttribute("stroke-dasharray", ($scale * 10) . ", " . ($scale * 10));
									$path->addAttribute("d", $svg_path);
									}

								# funicular

								if($sorter == "light_rail")
									{
									$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
									$path->addAttribute("id", $id);
									$path->addAttribute("stroke", "#666666");
									$path->addAttribute("stroke-width", ($scale * 1));

									if($layer == "tunnel")
										$path->addAttribute("stroke-dasharray", ($scale * 3) . ", " . ($scale * 3));

									$path->addAttribute("d", $svg_path);
									}

								# miniature
								# monorail
								# narrow_gauge
								# preserved

								if($sorter == "rail")
									{
									if($layer == "bridge")
										{
										$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
										$path->addAttribute("stroke", "black");
										$path->addAttribute("stroke-width", ($scale * 5));
										$path->addAttribute("d", $svg_path);

										$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
										$path->addAttribute("stroke", "white");
										$path->addAttribute("stroke-width", ($scale * 4));
										$path->addAttribute("d", $svg_path);

										$path = $g[$layer . "-" . $type . "-outer"]->addChild("path");
										$path->addAttribute("stroke", "#999999");
										$path->addAttribute("stroke-width", ($scale * 3));
										$path->addAttribute("d", $svg_path);

										$path = $g[$layer . "-" . $type . "-inner"]->addChild("path");
										$path->addAttribute("id", $id);
										$path->addAttribute("stroke", "white");
										$path->addAttribute("stroke-width", ($scale * 2));
										$path->addAttribute("stroke-dasharray", ($scale * 10) . ", " . ($scale * 10));
										$path->addAttribute("d", $svg_path);
										}

									if($layer == "tunnel")
										{
										$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
										$path->addAttribute("stroke", "white");
										$path->addAttribute("stroke-width", ($scale * 3));
										$path->addAttribute("stroke-dasharray", ($scale * 1) . ", " . ($scale * 9));
										$path->addAttribute("d", $svg_path);

										$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
										$path->addAttribute("stroke", "#fdfdfd");
										$path->addAttribute("stroke-width", ($scale * 3));
										$path->addAttribute("stroke-dasharray", ($scale * 0) . ", " . ($scale * 1) . ", " . ($scale * 1) . ", " . ($scale * 8));
										$path->addAttribute("d", $svg_path);

										$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
										$path->addAttribute("stroke", "#ececec");
										$path->addAttribute("stroke-width", ($scale * 3));
										$path->addAttribute("stroke-dasharray", ($scale * 0) . ", " . ($scale * 2) . ", " . ($scale * 1) . ", " . ($scale * 7));
										$path->addAttribute("d", $svg_path);

										$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
										$path->addAttribute("stroke", "#cacaca");
										$path->addAttribute("stroke-width", ($scale * 3));
										$path->addAttribute("stroke-dasharray", ($scale * 0) . ", " . ($scale * 3) . ", " . ($scale * 1) . ", " . ($scale * 6));
										$path->addAttribute("d", $svg_path);

										$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
										$path->addAttribute("stroke", "#afafaf");
										$path->addAttribute("stroke-width", ($scale * 3));
										$path->addAttribute("stroke-dasharray", ($scale * 0) . ", " . ($scale * 4) . ", " . ($scale * 1) . ", " . ($scale * 5));
										$path->addAttribute("d", $svg_path);

										$path = $g[$layer . "-" . $type . "-default"]->addChild("path");
										$path->addAttribute("stroke", "#a1a1a1");
										$path->addAttribute("stroke-width", ($scale * 3));
										$path->addAttribute("stroke-dasharray", ($scale * 0) . ", " . ($scale * 6) . ", " . ($scale * 1) . ", " . ($scale * 4));
										$path->addAttribute("d", $svg_path);

										}

									if($layer == "normal")
										{
										$path = $g[$layer . "-" . $type . "-outer"]->addChild("path");
										$path->addAttribute("stroke", "#999999");
										$path->addAttribute("stroke-width", ($scale * 3));
										$path->addAttribute("d", $svg_path);

										$path = $g[$layer . "-" . $type . "-inner"]->addChild("path");
										$path->addAttribute("id", $id);
										$path->addAttribute("stroke", "white");
										$path->addAttribute("stroke-width", ($scale * 2));
										$path->addAttribute("stroke-dasharray", ($scale * 10) . ", " . ($scale * 10));
										$path->addAttribute("d", $svg_path);
										}
									}

								# subway
								# tram
								}

							if($type == "tourism")
								{
								$color = array(
									"*" => "none",
									"attraction" => "#beadad",
									"camp_site" => "#def5c0",
									"museum" => "#beadad"
									);

								$color = (isset($color[$sorter]) === false ? $color["*"] : $color[$sorter]);

								################################################################################
								# get nodes of way
								################################################################################

								if(count($waypoints) == 0)
									{
									$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

									$svg_path = get_svg_path($waypoints);
									}

								$path = $g["default-" . $type]->addChild("path");
								$path->addAttribute("id", $id);
								$path->addAttribute("fill", $color);
								$path->addAttribute("d", $svg_path);
								}

							if($type == "waterway")
								{
								$color = array(
									"*" => "#acd7f2"
									);

								$color = (isset($color[$sorter]) === false ? $color["*"] : $color[$sorter]);

								################################################################################
								# get nodes of way
								################################################################################

								if(count($waypoints) == 0)
									{
									$waypoints = get_waypoints($id, $version, $zoom, $min_x, $min_y);

									$svg_path = get_svg_path($waypoints);
									}

								$path = $g["default-" . $type]->addChild("path");
								$path->addAttribute("id", $id);
								$path->addAttribute("stroke", $color);
								$path->addAttribute("d", $svg_path);
								}
							}
						}

					$query_way->free_result();
					}
				}

			foreach($trans["node"] as $id => $version)
				{
				if($query_node = $resource->query("select * from node where ((id = " . $id . ") and (version = " . $version . "));"))
					{
					while($result_node = $query_node->fetch_object())
						{
						list($def_x, $def_y) = $p->from_lon_lat_to_tile(array($result_node->lon, $result_node->lat), $zoom);

						$x = round($def_x - $min_x, 0);
						$y = round($def_y - $min_y, 0);

						################################################################################
						# get properties
						################################################################################

						$properties = get_properties("node", $id, $version);

						foreach($layers as $type)
							{
							if(isset($properties[$type]) === false)
								continue;

							$sorter = $properties[$type];

							$font_size = array(
								"*" => array(
									"*" => array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)
									),
								"natural" => array(
									"*" => array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
									"peak" => array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 10, 10, 10, 10, 10, 10)
									),
								"place" => array(
									"*" => array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
									"city" => array(0, 0, 0, 0, 0, 0, 8, 8, 8, 10, 10, 10, 10, 10, 10, 0, 0, 0, 0, 0),
									"farm" => array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 10, 10, 11, 11, 11, 11),
									"hamlet" => array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 10, 10, 11, 11, 11, 11),
									"isolated_dwelling" => array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 10, 10, 11, 11, 11, 11),
									"suburb" => array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 12, 12, 13, 13, 13, 13, 13, 13),
									"village" => array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 8, 8, 8, 8, 10, 10, 10, 10, 10)
									)
								);

							$font_size = (isset($font_size[$type]) === false ? $font_size["*"] : $font_size[$type]);

							$font_size = (isset($font_size[$sorter]) === false ? $font_size["*"] : $font_size[$sorter]);

							$font_size = (isset($font_size[$zoom]) === false ? 0 : $font_size[$zoom]);

							if($font_size == 0)
								continue;

							if(strlen($properties["name"]) == 0)
								continue;

							$text = $g["text"]->addChild("text", $properties["name"] . (strlen($properties["ele"]) == 0 ? "" : " (" . $properties["ele"] . ")"));
							$text->addAttribute("font-size", $font_size);
							$text->addAttribute("fill", "black");
							$text->addAttribute("text-anchor", "middle");
							$text->addAttribute("font-weight", "bold");
							$text->addAttribute("stroke-width", 0.2);
							$text->addAttribute("stroke", "white");
							$text->addAttribute("x", $x);
							$text->addAttribute("y", $y);
							}
						}

					}

				$query_node->free_result();
				}
			}

#		$resource->close();
		}

	$svg = $svg->asXML();

#		$dom = new DOMDocument(null);
#		$dom->preserveWhiteSpace = false;
#		$dom->formatOutput = true;
#		$dom->loadXML($svg);
#		$svg = $dom->saveXML();

	return($svg);
	}

################################################################################
# ...
################################################################################

function get_area_center($waypoints)
	{
	list($k, $x, $y) = array(0, 0, 0);

	list($x_last, $y_last) = $waypoints[0];

	foreach($waypoints as $waypoint_id => $waypoint_data)
		{
		list($x_current, $y_current) = $waypoint_data;

		$k = $k + ($c = ($x_last * $y_current) - ($x_current * $y_last));

		$x = $x + (($x_last + $x_current) * $c);
		$y = $y + (($y_last + $y_current) * $c);

		list($x_last, $y_last) = $waypoint_data;
		}

	return($k ? array($x / ($k * 3), $y / ($k * 3)) : array(0, 0));
	}

################################################################################
# ...
################################################################################

function get_properties($type, $id, $version)
	{
	################################################################################
	# init properties
	################################################################################

	$properties = array();

	################################################################################
	# set default properties of way
	################################################################################

	foreach(array("addr:housenumber" => "", "access" => "yes", "area" => "no", "bridge" => "no", "construction" => "", "ele" => "", "name" => "", "tunnel" => "no", "admin_level" => 11) as $k => $v)
		$properties[$k] = $v;

	################################################################################
	# get properties of object
	################################################################################

	global $resource;

	if($resource)
		{
		if($query = $resource->query("select * from " . $type . "_tag where ((id = " . $id . ") and (version = " . $version . "));"))
			{
			while($result = $query->fetch_object())
				$properties[$result->k] = $result->v;

			$query->free_result();
			}
		}

	return($properties);
	}

################################################################################
# ...
################################################################################

function get_text_size($font_family, $font_size, $string)
	{
	# font must be 0755

	$data = imagettfbbox($font_size, 0, "/usr/share/fonts/truetype/arkpandora/" . $font_family . ".ttf", $string);

	$min_x = min(array($data[0], $data[2], $data[4], $data[6]));
	$max_x = max(array($data[0], $data[2], $data[4], $data[6]));

	$min_y = min(array($data[1], $data[3], $data[5], $data[7]));
	$max_y = max(array($data[1], $data[3], $data[5], $data[7]));

	return(array($max_x - $min_x, $max_y - $min_y));
	}

################################################################################
# ...
################################################################################

function get_way_direction($waypoints)
	{
	$way_length = get_way_length($waypoints) / 2;

	$retval = 0;

	list($x_last, $y_last) = $waypoints[0];

	$direction = 0 - 1;

	foreach($waypoints as $waypoint_id => $waypoint_data)
		{
		list($x_current, $y_current) = $waypoint_data;

		$retval = $retval + sqrt(pow($x_last - $x_current, 2) + pow($y_last - $y_current, 2));

		$direction = ($direction < 0 ? ($retval > $way_length ? ($x_current > $x_last ? 0 : 1) : $direction) : $direction);

		list($x_last, $y_last) = $waypoint_data;
		}

	return($direction);
	}

function get_way_length($waypoints)
	{
	$retval = 0;

	list($x_last, $y_last) = $waypoints[0];

	foreach($waypoints as $waypoint_id => $waypoint_data)
		{
		list($x_current, $y_current) = $waypoint_data;

		$retval = $retval + sqrt(pow($x_last - $x_current, 2) + pow($y_last - $y_current, 2));

		list($x_last, $y_last) = $waypoint_data;
		}

	return($retval);
	}

################################################################################
# ...
################################################################################

function get_svg_path($waypoints)
	{
	foreach($waypoints as $waypoint_id => $waypoint_data)
		$waypoints[$waypoint_id] = implode(" ", $waypoint_data);

	return("M " . implode(" ", $waypoints));
	}

################################################################################
# ...
################################################################################

function get_waypoints($id, $version, $zoom, $min_x, $min_y)
	{
	################################################################################
	# init nodes of way
	################################################################################

	$waypoints = array();

	################################################################################
	# get nodes of way
	################################################################################

	global $resource;

	$p = new google_projection($zoom);

	if($resource)
		{
		if($query = $resource->query("select node.* from node, way_nd where ((way_nd.id = " . $id . ") and (way_nd.version = " . $version . ") and (node.id = way_nd.ref) and (node.visible = 'true')) order by z asc;"))
			{
			while($result = $query->fetch_object())
				{
				list($def_x, $def_y) = $p->from_lon_lat_to_tile(array($result->lon, $result->lat), $zoom);

				$x = round($def_x - $min_x, 0); # use one decimal digit to avoid zig-zag-lines
				$y = round($def_y - $min_y, 0); # use one decimal digit to avoid zig-zag-lines

				# do not repeat last node
				if(count($waypoints) > 0)
					{
					list($a, $b) = end($waypoints);

					if(($a == $x) && ($b == $y))
						continue;
					}

				$waypoints[] = array($x, $y);
				}

			$query->free_result();
			}
		}

	return($waypoints);
	}
?>
