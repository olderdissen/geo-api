<?
class google_projection
	{
	var $bc = array();
	var $cc = array();
	var $zc = array();

	function google_projection($levels = 19)
		{
		foreach(range(0, $levels) as $d)
			{
			$c = pow(2, $d) * 256;

			$this->bc[] = $c / 360;
			$this->cc[] = $c / (2 * M_PI);
			$this->zc[] = array($c / 2, $c / 2);
			}
		}

	function minmax($a, $min, $max)
		{
		$a = min($a, $max);
		$a = max($a, $min);

		return($a);
		}

	function from_lon_lat_to_tile($lon_lat, $zoom)
		{
		$x = $lon_lat[0];
		$y = $lon_lat[1];

		$y = $this->minmax(sin(deg2rad($y)), 0 - 1, 0 + 1);
		$y = 0.5 * log((1 + $y) / (1 - $y));

		$x = $this->zc[$zoom][0] + $x * (0 + $this->bc[$zoom]);
		$y = $this->zc[$zoom][1] + $y * (0 - $this->cc[$zoom]);

		return(array($x, $y));
		}

	function from_tile_to_lon_lat($xy, $zoom)
		{
		$lon = ($xy[0] - $this->zc[$zoom][0]) / (0 + $this->bc[$zoom]);
		$lat = ($xy[1] - $this->zc[$zoom][1]) / (0 - $this->cc[$zoom]);

		$lat = rad2deg(2 * atan(exp($lat)) - 0.5 * pi());

		return(array($lon, $lat));
		}
	}
?>
