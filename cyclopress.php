<?PHP
/*
Plugin Name: CycloPress
Plugin URI: http://amwhalen.com/blog/projects/cyclopress/
Description: Keep track of your cycling statistics with WordPress and make pretty graphs.
Version: 1.3.9
Author: Andrew M. Whalen
Author URI: http://amwhalen.com
*/

/*  Copyright 2009  Andrew M. Whalen  (email : cyclopress@amwhalen.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


$cy_version = '1.3.9';
$cy_db_version = '1.7';
$cy_db_version = rand(0,1);
$cy_graph_dir = 'graphs';
$cy_graph_dir_full = dirname(__FILE__).'/'.$cy_graph_dir;
$cy_dir = get_bloginfo('url').'/wp-content/plugins/'.basename(dirname(__FILE__));
$cy_src_dir = $cy_dir.'/'.$cy_graph_dir;


/**
 * The CycloPress Ride object
 */
class CYRide {

	var $id = NULL;
	var $startdate = '';
	var $miles = '';
	var $minutes = '';
	var $avg_speed = '';
	var $max_speed = '';
	var $cadence = '';
	var $notes = '';
	
	// 1.3.8
	var $bike_id = NULL;
	var $type_id = NULL;
	
	// 1.3.9
	var $avg_hr;
	var $max_hr;
	var $avg_watts;
	var $max_watts;
	var $est_calories;
	var $elevation_gain;
	var $elevation_loss;
	var $max_elevation;
	var $min_elevation;
	var $weight;
	
	// form-only
	var $month = '';
	var $day = '';
	var $year = '';
	var $hour = '';
	var $minute = '';
	var $ampm = 'am';
	
	/**
	 * Constructor
	 */
	function CYRide() {
	
		// set the default date
		$this->month = date('n');
		$this->day = date('j');
		$this->year = date('Y');
		$this->startdate = $this->get_startdate();
	
	}
	
	/**
	 * Returns this ride's start date.
	 */
	function get_startdate($format='Y-m-d H:i:s') {
	
		$date = $this->year.'-'.$this->month.'-'.$this->day;
		if ($this->hour != '' && $this->minute != '') {
			$time = $this->hour.':'.$this->minute.' '.$this->ampm;
		} else {
			$time = '00:00:00';
		}
		$this->startdate = date($format, strtotime($date . ' ' . $time));
		
		return $this->startdate;
	
	}
	
	/**
	 * Loads all data from POST
	 */
	function load_post() {
	
		foreach ($this as $k=>$v) {
			$this->$k = $_POST[$k];
		}
		
		$this->startdate = $this->get_startdate();
	
	}
	
	/**
	 * Loads all data from a DB row (associative)
	 */
	function load($row) {
	
		foreach ($this as $k=>$v) {
			$this->$k = $row[$k];
		}
		
		$ts = strtotime($this->startdate);
		$this->month = date('n', $ts);
		$this->day = date('j', $ts);
		$this->year = date('Y', $ts);
		$this->hour = date('g', $ts);
		$this->minute = date('i', $ts);
		$this->ampm = date('a', $ts);
	
	}

};

/**
 * The CycloPress Bike object
 */
class CYBike extends CYDBObject {

	var $id;
	var $label;
	var $make;
	var $model;
	var $year;
	var $notes;

};

/**
 * The CycloPress Type object
 */
class CYType extends CYDBObject {

	var $id;
	var $type;
	var $label;
	var $description;

};

/**
 * The CycloPress DBObject object
 */
class CYDBObject {

	/**
	 * Loads all data from POST
	 */
	function load_post() {
	
		$this->load($_POST);
			
	}
	
	/**
	 * Loads all data from a DB row (associative)
	 */
	function load($row) {
	
		foreach ($this as $k=>$v) {
			$this->$k = $row[$k];
		}
	
	}

};

/**
 * Returns an XHTML string with a brief stats overview.
 */
function cy_get_brief_stats($before='<p>', $separator=', ', $after='</p>') {

	cy_check_version();

	$stats = cy_db_stats();
	
	if (!$stats) return false;

	$str = $before . $stats['total_miles'].' '.cy_distance_text($stats['total_miles']) . $separator . round($stats['avg_avg_speed'],1).' '.cy_speed_text().' average speed' . $after;

	return $str;

}

/**
 * Returns an XHTML string with the stats summary.
 */
function cy_get_summary($compare=false,$year=false) {

	cy_check_version();

	if ($compare) return cy_get_summary_compare($year);

	$stats = cy_db_stats($year);
	
	if (!$stats) return false;

	$str  = '<dl class="cy">';
	$str .= '<dt>Total Rides</dt> <dd>'.$stats['total_rides'].'</dd>';
	$str .= '<dt>Total Distance</dt> <dd>'.round($stats['total_miles'],2).' miles</dd>';
	$str .= '<dt>Average Ride Distance</dt> <dd>'.round($stats['total_miles']/$stats['total_rides'],2).' miles</dd>';
	$str .= '<dt>Total Riding Time</dt> <dd>'.round($stats['total_time']/60,2).' hours</dd>';
	$str .= '<dt>Average Ride Time</dt> <dd>'.round($stats['total_time']/$stats['total_rides'],2).' minutes</dd>';
	$str .= '<dt>Average Overall Speed</dt> <dd>'.round($stats['avg_avg_speed'],2).' mph</dd>';
	$str .= '<dt>Maximum Speed</dt> <dd>'.round($stats['max_max_speed'],2).' mph</dd>';
	$str .= '</dl>';

	return $str;

}

/**
 * Returns an XHTML string with the stats summary.
 */
function cy_get_summary_compare($year=false) {

	cy_check_version();

	$stats = cy_db_stats($year);
	$stats_now = cy_db_stats(date('Y'));
	
	if (!$stats) return false;

	$str  = '<dl class="cy">';
	$str .= '<dt>Total Rides</dt> <dd>'.$stats['total_rides'].' <small>('.$stats_now['total_rides'].' this year)</small></dd>';
	$str .= '<dt>Total Distance</dt> <dd>'.round($stats['total_miles'],2).' miles <small>('.round($stats_now['total_miles'],2).' this year)</small></dd>';
	$str .= '<dt>Average Ride Distance</dt> <dd>'.round($stats['total_miles']/$stats['total_rides'],2).' miles <small>('.round($stats_now['total_miles']/$stats_now['total_rides'],2).' this year)</small></dd>';
	$str .= '<dt>Total Riding Time</dt> <dd>'.round($stats['total_time']/60,2).' hours <small>('.round($stats_now['total_time']/60,2).' this year)</small></dd>';
	$str .= '<dt>Average Ride Time</dt> <dd>'.round($stats['total_time']/$stats['total_rides'],2).' minutes <small>('.round($stats_now['total_time']/$stats_now['total_rides'],2).' this year)</small></dd>';
	$str .= '<dt>Average Overall Speed</dt> <dd>'.round($stats['avg_avg_speed'],2).' mph <small>('.round($stats_now['avg_avg_speed'],2).' this year)</small></dd>';
	$str .= '<dt>Maximum Speed</dt> <dd>'.round($stats['max_max_speed'],2).' mph <small>('.round($stats_now['max_max_speed'],2).' this year)</small></dd>';
	$str .= '</dl>';

	return $str;

}

/**
 * Returns the date of the first ride.
 */
function cy_get_first_ride_date($format='F jS, Y') {

	cy_check_version();

	global $wpdb;

	$table_name = $wpdb->prefix . "cy_rides";
	
	$sql  = 'select * from '.$table_name.' order by startdate asc limit 1';
	$result = $wpdb->get_results($sql, OBJECT);
	if (!$result) return false;
	$first = $result[0];
		
	return date($format, strtotime($first->startdate));

}

/**
 * Returns the date of the last ride.
 */
function cy_get_last_ride_date($format='F jS, Y') {

	cy_check_version();

	global $wpdb;

	$table_name = $wpdb->prefix . "cy_rides";
	
	$sql  = 'select * from '.$table_name.' order by startdate desc limit 1';
	$result = $wpdb->get_results($sql, OBJECT);
	if (!$result) return false;
	$last = $result[0];
	
	return date($format, strtotime($last->startdate));

}

/**
 * Returns the src to a graph
 */
function cy_get_graph_img_tag($type='distance') {

	cy_check_version();

	global $cy_src_dir;
	
	return '<img src="'.$cy_src_dir.'/cy_'.$type.'.png" alt="graph of cycling '.$type.' per ride over time" />';

}

/**
 * Returns an associative array with most recent ride and summary stats included.
 */
function cy_db_stats($year=false) {

	global $wpdb;

	$table_name = $wpdb->prefix . "cy_rides";

	if ($year) {
		$where = " where startdate like '".$year."%'";
	} else {
		$where = '';
	}

	$sql  = 'select * from '.$table_name.$where.' order by startdate desc limit 1';
	$result = $wpdb->get_results($sql, ARRAY_A);
	if (!$result) {
		return false;
	}
	$stats = $result[0];
	
	// get the 'since' portion (the first date of the stats)
	$sql = 'select startdate from '.$table_name.$where.' order by startdate asc limit 1';
	$result = $wpdb->get_results($sql, ARRAY_A);
	$row = $result[0];
	$stats['since'] = $row['date'];
	
	// average avg_speed
	$sql = 'select avg(avg_speed) as avg_avg_speed from '.$table_name.$where;
	$result = $wpdb->get_results($sql, ARRAY_A);
	$row = $result[0];
	$stats['avg_avg_speed'] = $row['avg_avg_speed'];
	
	// total_miles
	$sql = 'select sum(miles) as total_miles from '.$table_name.$where;
	$result = $wpdb->get_results($sql, ARRAY_A);
	$row = $result[0];
	$stats['total_miles'] = $row['total_miles'];
	
	// total_time
	$sql = 'select sum(minutes) as total_time from '.$table_name.$where;
	$result = $wpdb->get_results($sql, ARRAY_A);
	$row = $result[0];
	$stats['total_time'] = $row['total_time'];
	
	// total_rides
	$sql = 'select count(*) as total_rides from '.$table_name.$where;
	$result = $wpdb->get_results($sql, ARRAY_A);
	$row = $result[0];
	$stats['total_rides'] = $row['total_rides'];
	
	// max_max_speed
	$sql = 'select max(max_speed) as max_max_speed from '.$table_name.$where;
	$result = $wpdb->get_results($sql, ARRAY_A);
	$row = $result[0];
	$stats['max_max_speed'] = $row['max_max_speed'];
	
	return $stats;

}

/**
 * Saves a ride in the database
 */
function cy_save_ride($ride) {

	if ($ride->id == NULL) {
		return cy_insert_ride($ride);
	} else  {
		return cy_update_ride($ride);
	}

}

/**
 * Inserts a ride into the database
 */
function cy_insert_ride($ride) {

	global $wpdb;

	$table_name = $wpdb->prefix . "cy_rides";

	//$sql  = 'insert into '.$table_name.'(startdate,miles,avg_speed,max_speed,minutes,cadence,notes) ';
	$sql  = 'insert into '.$table_name.'(startdate,miles,avg_speed,max_speed,minutes,cadence,notes,avg_hr,max_hr,avg_watts,max_watts,elevation_gain,elevation_loss,max_elevation,min_elevation,weight,est_calories,bike_id,type_id) ';
	$sql .= "values('".$wpdb->escape($ride->get_startdate())."','".$wpdb->escape($ride->miles)."','".$wpdb->escape($ride->avg_speed)."'";
	$sql .= ",'".$wpdb->escape($ride->max_speed)."','".$wpdb->escape($ride->minutes)."'";
	$sql .= ",'".$wpdb->escape($ride->cadence)."','".$wpdb->escape($ride->notes)."'";
	$sql .= ",'".$wpdb->escape($ride->avg_hr)."','".$wpdb->escape($ride->max_hr)."'";
	$sql .= ",'".$wpdb->escape($ride->avg_watts)."','".$wpdb->escape($ride->max_watts)."'";
	$sql .= ",'".$wpdb->escape($ride->elevation_gain)."','".$wpdb->escape($ride->elevation_loss)."'";
	$sql .= ",'".$wpdb->escape($ride->max_elevation)."','".$wpdb->escape($ride->min_elevation)."'";
	$sql .= ",'".$wpdb->escape($ride->weight)."','".$wpdb->escape($ride->est_calories)."'";
	$sql .= ",'".$wpdb->escape($ride->bike_id)."','".$wpdb->escape($ride->ride_id)."')";

	// send the query to the DBMS
	$result = $wpdb->query($sql);
	
	// error?
	$saved = ($result === false) ? false : true;
	if (!$saved) echo $sql;
	return $saved;

}

/**
 * Updates a ride in the database
 */
function cy_update_ride($ride) {

	global $wpdb;

	$table_name = $wpdb->prefix . "cy_rides";
	
	$sql  = 'update '.$table_name.' ';
	$sql .= "set startdate='".$wpdb->escape($ride->get_startdate())."', miles='".$wpdb->escape($ride->miles)."', avg_speed='".$wpdb->escape($ride->avg_speed)."'";
	$sql .= ", max_speed='".$wpdb->escape($ride->max_speed)."', minutes='".$wpdb->escape($ride->minutes)."'";
	$sql .= ", cadence='".$wpdb->escape($ride->cadence)."', notes='".$wpdb->escape($ride->notes)."'";
	$sql .= ", bike_id='".$wpdb->escape($ride->bike_id)."', type_id='".$wpdb->escape($ride->type_id)."'";
	$sql .= ", avg_hr='".$wpdb->escape($ride->avg_hr)."', max_hr='".$wpdb->escape($ride->max_hr)."'";
	$sql .= ", avg_watts='".$wpdb->escape($ride->avg_watts)."', max_watts='".$wpdb->escape($ride->max_watts)."'";
	$sql .= ", elevation_gain='".$wpdb->escape($ride->elevation_gain)."', elevation_loss='".$wpdb->escape($ride->elevation_loss)."'";
	$sql .= ", max_elevation='".$wpdb->escape($ride->max_elevation)."', min_elevation='".$wpdb->escape($ride->min_elevation)."'";
	$sql .= ", weight='".$wpdb->escape($ride->weight)."', est_calories='".$wpdb->escape($ride->est_calories)."'";
	$sql .= " where id='".$wpdb->escape($ride->id)."'";

	// send the query to the DBMS
	$result = $wpdb->query($sql);
	
	// error?
	$saved = ($result === false) ? false : true;
	return $saved;

}

/**
 * Saves a bike in the database
 */
function cy_save_bike($bike) {

	if ($bike->id == NULL) {
		return cy_insert_bike($bike);
	} else  {
		return cy_update_bike($bike);
	}

}

/**
 * Inserts a bike into the database
 */
function cy_insert_bike($bike) {

	global $wpdb;

	$table_name = $wpdb->prefix . "cy_bikes";

	$sql  = 'insert into '.$table_name.'(label,make,model,year,notes) ';
	$sql .= "values('".$wpdb->escape($bike->label)."','".$wpdb->escape($bike->make)."','".$wpdb->escape($bike->model)."'";
	$sql .= ",'".$wpdb->escape($bike->year)."','".$wpdb->escape($bike->notes)."')";

	// send the query to the DBMS
	$result = $wpdb->query($sql);
	
	// error?
	$saved = ($result === false) ? false : true;
	if (!$saved) echo $sql;
	return $saved;

}

/**
 * Updates a bike in the database
 */
function cy_update_bike($bike) {

	global $wpdb;

	$table_name = $wpdb->prefix . "cy_bikes";
	
	$sql  = 'update '.$table_name.' ';
	$sql .= "set label='".$wpdb->escape($bike->label)."', make='".$wpdb->escape($bike->make)."'";
	$sql .= ", model='".$wpdb->escape($bike->model)."', year='".$wpdb->escape($bike->year)."'";
	$sql .= ", notes='".$wpdb->escape($bike->notes)."'";
	$sql .= " where id='".$wpdb->escape($bike->id)."'";

	// send the query to the DBMS
	$result = $wpdb->query($sql);
	
	// error?
	$saved = ($result === false) ? false : true;
	return $saved;

}

/**
 * Gets a bike.
 */
function cy_get_bike($bike_id) {

	global $wpdb;
	$bike_table_name = $wpdb->prefix . "cy_bikes";
	
	$sql  = 'select * from '.$bike_table_name.' where id='.mysql_escape_string($bike_id);
	$bikes = $wpdb->get_results($sql, ARRAY_A);
	
	$bike = new CYBike();
	$bike->load($bikes[0]);
	
	return $bike;

}

/**
 * Gets all bikes.
 */
function cy_get_bikes($sort_col='label', $sort_order='asc') {

	global $wpdb;
	$bike_table_name = $wpdb->prefix . "cy_bikes";
	
	$sql  = 'select * from '.$bike_table_name.' order by '.mysql_escape_string($sort_col).' '.mysql_escape_string($sort_order);
	$bikes = $wpdb->get_results($sql, ARRAY_A);

	return $bikes;

}

/**
 * Saves a type in the database
 */
function cy_save_type($type) {

	if ($type->id == NULL) {
		return cy_insert_type($type);
	} else  {
		return cy_update_type($type);
	}

}

/**
 * Inserts a type into the database
 */
function cy_insert_type($type) {

	global $wpdb;

	$table_name = $wpdb->prefix . "cy_types";

	$sql  = 'insert into '.$table_name.'(type,label,description) ';
	$sql .= "values('".$wpdb->escape($type->type)."','".$wpdb->escape($type->label)."','".$wpdb->escape($type->description)."')";

	// send the query to the DBMS
	$result = $wpdb->query($sql);
	
	// error?
	$saved = ($result === false) ? false : true;
	if (!$saved) echo $sql;
	return $saved;

}

/**
 * Updates a type in the database
 */
function cy_update_type($type) {

	global $wpdb;

	$table_name = $wpdb->prefix . "cy_types";
	
	$sql  = 'update '.$table_name.' ';
	$sql .= "set type='".$wpdb->escape($type->type)."', label='".$wpdb->escape($type->label)."'";
	$sql .= ", description='".$wpdb->escape($type->description)."'";
	$sql .= " where id='".$wpdb->escape($type->id)."'";

	// send the query to the DBMS
	$result = $wpdb->query($sql);
	
	// error?
	$saved = ($result === false) ? false : true;
	return $saved;

}

/**
 * Returns the admin navigation bar
 */
function cy_admin_navigation($current_page='', $current_subpage='') {

	cy_check_version();

	$wp_url = get_bloginfo('url');

	$links = array(
		'manage' => array(
			'url' => $wp_url.'/wp-admin/plugins.php?page=cyclopress/cyclopress.php&manage=1',
			'title' => 'Rides',
			'subnav' => array(
				'manage' => array(
					'url' => $wp_url.'/wp-admin/plugins.php?page=cyclopress/cyclopress.php&manage=1',
					'title' => 'List',
				),
				'manage_calendar' => array(
					'url' => $wp_url.'/wp-admin/plugins.php?page=cyclopress/cyclopress.php&manage=1',
					'title' => 'Calendar',
				),
				'add' => array(
					'url' => $wp_url.'/wp-admin/post-new.php?page=cyclopress/cyclopress.php',
					'title' => 'Add a Ride',
				),
			),
		),
		'manage_bikes' => array(
			'url' => $wp_url.'/wp-admin/plugins.php?page=cyclopress/cyclopress.php&manage_bikes=1',
			'title' => 'Bikes',
			'subnav' => array(
				'manage_bikes' => array(
					'url' => $wp_url.'/wp-admin/plugins.php?page=cyclopress/cyclopress.php&manage_bikes=1',
					'title' => 'List',
				),
				'add_bikes' => array(
					'url' => $wp_url.'/wp-admin/plugins.php?page=cyclopress/cyclopress.php&add_bikes=1',
					'title' => 'Add',
				),
			),
		),
		'options' => array(
			'url' => $wp_url.'/wp-admin/plugins.php?page=cyclopress/cyclopress.php',
			'title' => 'Options',
			'subnav' => array(
				'options' => array(
					'url' => $wp_url.'/wp-admin/plugins.php?page=cyclopress/cyclopress.php',
					'title' => 'Options',
				),
				'cycling' => array(
					'url' => $wp_url.'/wp-admin/plugins.php?page=cyclopress/cyclopress.php&cycling=1',
					'title' => 'Cycling Page',
				),
			),
		),
		'export' => array(
			'url' => $wp_url.'/wp-admin/plugins.php?page=cyclopress/cyclopress.php&export=1',
			'title' => 'Tools',
			'subnav' => array(
				'export' => array(
					'url' => $wp_url.'/wp-admin/plugins.php?page=cyclopress/cyclopress.php&export=1',
					'title' => 'Export',
				),
				'stats' => array(
					'url' => $wp_url.'/wp-admin/plugins.php?page=cyclopress/cyclopress.php&stats=1',
					'title' => 'Stats',
				),
				'debug' => array(
					'url' => $wp_url.'/wp-admin/plugins.php?page=cyclopress/cyclopress.php&debug=1',
					'title' => 'Debug',
				),
				'about' => array(
					'url' => $wp_url.'/wp-admin/plugins.php?page=cyclopress/cyclopress.php&about=1',
					'title' => 'About',
				),
			),
		),
	);

	$str = '<div class="cy_admin_navigation">CycloPress: <ul>';
	$tabs = array();
	foreach ($links as $k=>$link) {
	
		$class = '';
		$warning = '';
	
		// should an error be reported in the nav?
		if ($k == 'debug') {
			if (!cy_check_php() || !cy_check_gd() || !cy_is_cache_writable()) {
				$class = ' cy_debug';
				$warning = ' !';
			}
		}
	
		// make the current page look different
		if ($current_page == $k) {
			$tabs[] = '<li class="here'.$class.'"><a href="'.$link['url'].'">'.$link['title'].$warning.'</a></li>';
		} else {
			$tabs[] = '<li class="'.$class.'"><a href="'.$link['url'].'">'.$link['title'].$warning.'</a></li>';
		}
	}
	$str .= implode('', $tabs);
	$str .= '</ul></div>';
	
	$str .= '<div class="cy_admin_subnav"><ul>';
	$tabs = array();
	foreach ($links[$current_page]['subnav'] as $k=>$link) {
	
		$class = '';
		$warning = '';
	
		// should an error be reported in the nav?
		if ($k == 'debug') {
			if (!cy_check_php() || !cy_check_gd() || !cy_is_cache_writable()) {
				$class = ' cy_debug';
				$warning = ' !';
			}
		}
	
		// make the current page look different
		if ($current_page == $k) {
			$tabs[] = '<li class="here'.$class.'"><a href="'.$link['url'].'">'.$link['title'].$warning.'</a></li>';
		} else {
			$tabs[] = '<li class="'.$class.'"><a href="'.$link['url'].'">'.$link['title'].$warning.'</a></li>';
		}
	}
	$str .= implode('', $tabs);
	$str .= '</ul></div>';
	
	return $str;

}

/**
 * Add links to the cycling admin pages where appropriate.
 */
function cy_admin_menu() {

	// options for cycling
	add_submenu_page('plugins.php', 'CycloPress', 'CycloPress', 'edit_files', __FILE__, 'cy_options_page');
	
	// add a ride form
	add_submenu_page('post.php', 'Rides', 'Rides', 'edit_pages', __FILE__, 'cy_write_page');
	
	// add a ride editing page
	// removed for version 2.7. Older versions will be OK, since there's a CycloPress nav bar anyway.
	//add_submenu_page('edit.php', 'Rides', 'Rides', 'edit_pages', __FILE__, 'cy_manage_page');

}

/**
 * The cycling options page.
 */
function cy_options_page() {

	global $cy_graph_dir_full, $cy_dir, $cy_version;

	// export
	if (isset($_GET['export']) && $_GET['export']) {
	
		cy_export_page();
		return;
	
	}
	
	// cycling page
	if (isset($_GET['cycling']) && $_GET['cycling']) {
	
		cy_cycling_page();
		return;
	
	}

	// manage rides
	if (isset($_GET['manage']) && $_GET['manage']) {
	
		cy_manage_page();
		return;
	
	}
	
	// manage rides as a calendar
	if (isset($_GET['manage_calendar']) && $_GET['manage_calendar']) {
	
		cy_manage_calendar_page();
		return;
	
	}
	
	// add bikes
	if (isset($_GET['add_bikes']) && $_GET['add_bikes']) {
	
		cy_write_bikes_page();
		return;
	
	}
	
	// manage bikes
	if (isset($_GET['manage_bikes']) && $_GET['manage_bikes']) {
	
		cy_manage_bikes_page();
		return;
	
	}

	// redirect to the debug page if clicked on
	if (isset($_GET['debug']) && $_GET['debug']) {
		
		cy_debug_page();
		return;
		
	}
	
	// redirect to the stats page
	if (isset($_GET['stats']) && $_GET['stats']) {
	
		cy_stats_page();
		return;
	
	}
	
	// redirect to the about page
	if (isset($_GET['about']) && $_GET['about']) {
	
		cy_about_page();
		return;
	
	}

	// update the graphs if settings were changed
	if (isset($_GET['updated']) && $_GET['updated']) {
	
		cy_create_all_graphs();
	
	}

	?>
	<div class="wrap">
	
	<?php echo cy_admin_navigation('options','options'); ?>
	
	<h3>CycloPress Options</h3>
	
	<form method="post" action="options.php">
		
		<?php wp_nonce_field('update-options'); ?>
		
		<input type="hidden" name="action" value="update" />
		
		<input type="hidden" name="page_options" value="cy_unit,cy_graph_type,cy_graph_width,cy_graph_height,cy_graph_color_top,cy_graph_color_bottom,cy_graph_transparency" />
		
		<table class="widefat">
			<tr valign="top">
				<th scope="row">Units</th>
				<td>
					<select name="cy_unit">
						<option value="miles"<?php if (get_option('cy_unit')=='mile') { echo ' selected="selected"'; } ?>>Miles</option>
						<option value="kilometer"<?php if (get_option('cy_unit')=='kilometer') { echo ' selected="selected"'; } ?>>Kilometers</option>
					</select>
					<br /><small>This doesn't convert your numbers, it just changes the text 'miles' to 'kilometers' and vice versa.</small>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Graph Type</th>
				<td>
					<select name="cy_graph_type">
						<option value="line"<?php if (get_option('cy_graph_type')=='line') { echo ' selected="selected"'; } ?>>Line Graph</option>
						<option value="bar"<?php if (get_option('cy_graph_type')=='bar') { echo ' selected="selected"'; } ?>>Bar Graph</option>
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Graph Width</th>
				<td><input type="text" name="cy_graph_width" size="5" maxlength="4" value="<?php echo get_option('cy_graph_width'); ?>" /> pixels</td>
			</tr>
			<tr valign="top">
				<th scope="row">Graph Height</th>
				<td><input type="text" name="cy_graph_height" size="5" maxlength="4" value="<?php echo get_option('cy_graph_height'); ?>" /> pixels</td>
			</tr>
			<tr valign="top">
				<th scope="row">Graph Fill Gradient Top Color</th>
				<td>
					#<input type="text" name="cy_graph_color_top" size="8" maxlength="6" value="<?php echo get_option('cy_graph_color_top'); ?>" />
					<small>(e.g. #cccccc)</small>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Graph Fill Gradient Bottom Color</th>
				<td>
					#<input type="text" name="cy_graph_color_bottom" size="8" maxlength="6" value="<?php echo get_option('cy_graph_color_bottom'); ?>" />
					<small>(e.g. #777777)</small>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Graph Fill</th>
				<td>
					<select name="cy_graph_transparency">
						<option value="0"<?php if (get_option('cy_graph_transparency')=='0') { echo ' selected="selected"'; }?>>100% (Solid Fill)</option>
						<option value="0.1"<?php if (get_option('cy_graph_transparency')=='0.1') { echo ' selected="selected"'; }?>>90%</option>
						<option value="0.2"<?php if (get_option('cy_graph_transparency')=='0.2') { echo ' selected="selected"'; }?>>80%</option>
						<option value="0.3"<?php if (get_option('cy_graph_transparency')=='0.3') { echo ' selected="selected"'; }?>>70%</option>
						<option value="0.4"<?php if (get_option('cy_graph_transparency')=='0.4') { echo ' selected="selected"'; }?>>60%</option>
						<option value="0.5"<?php if (get_option('cy_graph_transparency')=='0.5') { echo ' selected="selected"'; }?>>50%</option>
						<option value="0.6"<?php if (get_option('cy_graph_transparency')=='0.6') { echo ' selected="selected"'; }?>>40%</option>
						<option value="0.7"<?php if (get_option('cy_graph_transparency')=='0.7') { echo ' selected="selected"'; }?>>30%</option>
						<option value="0.8"<?php if (get_option('cy_graph_transparency')=='0.8') { echo ' selected="selected"'; }?>>20%</option>
						<option value="0.9"<?php if (get_option('cy_graph_transparency')=='0.9') { echo ' selected="selected"'; }?>>10%</option>
						<option value="1.0"<?php if (get_option('cy_graph_transparency')=='1.0') { echo ' selected="selected"'; }?>>0% (No Fill)</option>
					</select>
				</td>
			</tr>
		</table>
				
		<p class="submit">
			<input type="submit" name="Submit" value="<?php _e('Update Options &raquo;') ?>" />
		</p>

	</form>
		
	</div>
	<?PHP

}

/**
 * The "Add a Ride" and "Edit a Ride" page.
 */
function cy_write_page($ride=false) {
	
	global $wpdb;
	$table_name = $wpdb->prefix . "cy_rides";
	
	// bikes
	$bikes = cy_get_bikes();
	
	// processing for when the form is submitted
	if (isset($_POST['submitted'])) {
		
		// load the form data from POST
		$ride = new CYRide();
		$ride->load_post();
		
		// check that we got required data
		if (!$_POST['miles'] || !$_POST['minutes']) {
			
			$saved = false;
			$e = 'Please fill in all required fields.';
			
		} else {
			
			// no average speed given? calculate it for the lazy person
			if (!$_POST['avg_speed']) {
				$ride->avg_speed = round($_POST['miles'] / ($_POST['minutes']/60), 2);
			}
			
			$saved = cy_save_ride($ride);
			
			// create the graphs
			if ($saved) {
				cy_create_all_graphs();
			}
	
		}
		
		if (!$saved) {
			?>
			<div id="message" class="updated fade" style="margin-top: 1.5em;"><p>Error: <?php echo $e; $wpdb->print_error(); ?></p></div>
			<?PHP
		} else {
			?>
			<div id="message" class="updated fade" style="margin-top: 1.5em;"><p>Your ride on <?php echo $ride->get_startdate('F j, Y \a\t g:ia'); ?> has been saved.</p></div>
			<?PHP
			$ride = new CYRide();
		}
			
	} else if ($ride === false || !is_object($ride)) {
	
		$ride = new CYRide();
	
	}

	?>
	<div class="wrap">
	
		<?php echo cy_admin_navigation('manage','add'); ?>
	
		<h3>CycloPress Ride</h3>
	
		<form name="cycling" action="" method="post" enctype="multipart/form-data">
			
			<input type="hidden" name="submitted" value="1" />
			
			<?php if ($ride->id != NULL) { ?>
			<input type="hidden" name="id" value="<?php echo $ride->id; ?>" />
			<?php } ?>
			
			<table class="widefat">
			  <tr>
				<th width="33%" scope="row" style="text-align: right;">*Date:</th>
				<td>
					<select name="month">
						<?PHP
							for ($i = 1; $i <= 12; $i++) {
								if ($i == $ride->month || $i == '0'.$ride->month) {
									$sel = ' selected="selected"';
								} else {
									$sel = '';
								}
								if ($i < 10) {
									$m = '0'.$i;
								} else {
									$m = $i;
								}
								?><option value="<?php echo $m; ?>"<?php echo $sel; ?>><?php echo $i.' - '.cy_get_month($i); ?></option><?PHP
							}
						?>
					</select>
					<select name="day">
						<?PHP
							for ($i = 1; $i <= 31; $i++) {
								if ($i == $ride->day || $i == '0'.$ride->day) {
									$sel = ' selected="selected"';
								} else {
									$sel = '';
								}
								if ($i < 10) {
									$d = '0'.$i;
								} else {
									$d = $i;
								}
								?><option value="<?php echo $d; ?>"<?php echo $sel; ?>><?php echo $i;?></option><?PHP
							}
						?>
					</select>
					<select name="year">
						<?PHP
							for ($i = 2002; $i <= date('Y')+2; $i++) {
								if ($i == $ride->year) {
									$sel = ' selected="selected"';
								} else {
									$sel = '';
								}
								?><option value="<?php echo $i; ?>"<?php echo $sel;?>><?php echo $i;?></option><?PHP
							}
						?>
					</select>
				</td>
			  </tr>
			  <th width="33%" scope="row" style="text-align: right;">Time:</th>
				<td>
					<select name="hour">
						<option value=""></option>
						<?PHP
							for ($i = 1; $i <= 12; $i++) {
								if ($i == $ride->hour) {
									$sel = ' selected="selected"';
								} else {
									$sel = '';
								}
								?><option value="<?php echo $i; ?>"<?php echo $sel; ?>><?php echo $i; ?></option><?PHP
							}
						?>
					</select>
					<select name="minute">
						<option value=""></option>
						<?PHP
							for ($i = 0; $i < 60; $i+=5) {
								if ($i < 10) {
									$t = '0'.strval($i);
								} else {
									$t = ''.$i;
								}
								if ($t == $ride->minute) {
									$sel = ' selected="selected"';
								} else {
									$sel = '';
								}
								?><option value="<?php echo $t; ?>"<?php echo $sel; ?>><?php echo $t; ?></option><?PHP
							}
						?>
					</select>
					<select name="ampm">
						<option value="am"<?php if ($ride->ampm=='am') { echo ' selected="selected"'; } ?>>am</option>
						<option value="pm"<?php if ($ride->ampm=='pm') { echo ' selected="selected"'; } ?>>pm</option>
					</select>
				</td>
			  </tr>
			  <tr>
			  	<th width="33%" scope="row" style="text-align: right;">Bike:</th>
			  	<td>
			  		<select name="bike_id">
						<option value="">Unknown</option>
						<?PHP
							$b = new CYBike();
							foreach ($bikes as $bike) {
								$b->load($bike);
								if ($b->id == $ride->bike_id) {
									$sel = ' selected="selected"';
								} else {
									$sel = '';
								}
								?><option value="<?php echo $b->id; ?>"<?php echo $sel; ?>><?php echo $b->label; ?></option><?PHP
							}
						?>
					</select>
			  	</td>
			  </tr>
			  <tr valign="top">
				<th scope="row" style="text-align: right;">*Distance:</th>
				<td><input type="text" name="miles" id="miles" size="5" value="<?php echo htmlentities(stripslashes($ride->miles)); ?>" /> <?php echo cy_distance_text($ride->miles); ?></td>
			  </tr>
			  <tr valign="top">
				<th scope="row" style="text-align: right;">*Time:</th>
				<td><input type="text" name="minutes" id="minutes" size="5" value="<?php echo htmlentities(stripslashes($ride->minutes)); ?>" /> minutes</td>
			  </tr>
			  <tr valign="top">
				<th scope="row" style="text-align: right;">Average Speed:</th>
				<td><input type="text" name="avg_speed" id="avg_speed" size="5" value="<?php echo htmlentities(stripslashes($ride->avg_speed)); ?>" /> <?php echo cy_speed_text($ride->avg_speed); ?></td>
			  </tr>
			  <tr valign="top">
				<th scope="row" style="text-align: right;">Maximum Speed:</th>
				<td><input type="text" name="max_speed" id="max_speed" size="5" value="<?php echo htmlentities(stripslashes($ride->max_speed)); ?>" /> <?php echo cy_speed_text($ride->avg_speed); ?></td>
			  </tr>
			  <tr valign="top">
				<th scope="row" style="text-align: right;">Cadence:</th>
				<td><input type="text" name="cadence" id="cadence" size="5" value="<?php echo htmlentities(stripslashes($ride->cadence)); ?>" /> rpm</td>
			  </tr>
			  <tr valign="top">
				<th scope="row" style="text-align: right;">Average Heart Rate:</th>
				<td><input type="text" name="avg_hr" id="avg_hr" size="5" value="<?php echo htmlentities(stripslashes($ride->avg_hr)); ?>" /> bpm</td>
			  </tr>
			  <tr valign="top">
				<th scope="row" style="text-align: right;">Maximum Heart Rate:</th>
				<td><input type="text" name="max_hr" id="max_hr" size="5" value="<?php echo htmlentities(stripslashes($ride->max_hr)); ?>" /> bpm</td>
			  </tr>
			  <tr valign="top">
				<th scope="row" style="text-align: right;">Average Watts:</th>
				<td><input type="text" name="avg_watts" id="avg_watts" size="5" value="<?php echo htmlentities(stripslashes($ride->avg_watts)); ?>" /> watts</td>
			  </tr>
			  <tr valign="top">
				<th scope="row" style="text-align: right;">Maximum Watts:</th>
				<td><input type="text" name="max_watts" id="max_watts" size="5" value="<?php echo htmlentities(stripslashes($ride->max_watts)); ?>" /> watts</td>
			  </tr>
			  <tr valign="top">
				<th scope="row" style="text-align: right;">Elevation Gain:</th>
				<td><input type="text" name="elevation_gain" id="elevation_gain" size="7" value="<?php echo htmlentities(stripslashes($ride->elevation_gain)); ?>" /> feet</td>
			  </tr>
			  <tr valign="top">
				<th scope="row" style="text-align: right;">Elevation Loss:</th>
				<td><input type="text" name="elevation_loss" id="elevation_loss" size="7" value="<?php echo htmlentities(stripslashes($ride->elevation_loss)); ?>" /> feet</td>
			  </tr>
			  <tr valign="top">
				<th scope="row" style="text-align: right;">Max Elevation:</th>
				<td><input type="text" name="max_elevation" id="max_elevation" size="7" value="<?php echo htmlentities(stripslashes($ride->max_elevation)); ?>" /> feet</td>
			  </tr>
			  <tr valign="top">
				<th scope="row" style="text-align: right;">Min Elevation:</th>
				<td><input type="text" name="min_elevation" id="min_elevation" size="7" value="<?php echo htmlentities(stripslashes($ride->min_elevation)); ?>" /> feet</td>
			  </tr>
			  <tr valign="top">
				<th scope="row" style="text-align: right;">Weight:</th>
				<td><input type="text" name="weight" id="weight" size="5" value="<?php echo htmlentities(stripslashes($ride->weight)); ?>" /> pounds</td>
			  </tr>
			  <tr valign="top">
				<th scope="row" style="text-align: right;">Estimated Calories Burned:</th>
				<td><input type="text" name="est_calories" id="est_calories" size="5" value="<?php echo htmlentities(stripslashes($ride->est_calories)); ?>" /> kcal</td>
			  </tr>
			  <tr valign="top">
				<th scope="row" style="text-align: right;">Notes:</th>
				<td><textarea name="notes" rows="10" cols="50"><?php echo htmlentities(stripslashes($ride->notes)); ?></textarea></td>
			  </tr>
			</table>
			<p class="submit"><input type="submit" name="Submit" value="Save" style="font-weight: bold;" /></p>
		</form>
		
	</div>
	<?PHP

}

/**
 * The "Manage Rides" page.
 */
function cy_manage_page() {

	global $wpdb;
	$table_name = $wpdb->prefix . "cy_rides";
	
	if ($_POST['submitted']) {
		
		cy_write_page();
	
	} else if (isset($_GET['cy_ride_id']) && $_GET['cy_ride_id']) {
	
		$sql  = 'select * from '.$table_name.' where id=' . mysql_escape_string($_GET['cy_ride_id']);
		$ride_result = $wpdb->get_results($sql, ARRAY_A);
		$db_ride = $ride_result[0];
	
		$ride = new CYRide();
		$ride->load($db_ride);
	
		cy_write_page($ride);
	
	} else {
	
		if (isset($_GET['cy_sort_col'])) {
		
			switch ($_GET['cy_sort_col']) {
				
				case "date":
					$sort_col = 'startdate';
					break;
				
				case "distance":
					$sort_col = 'miles';
					break;
					
				case "max_speed":
					$sort_col = 'max_speed';
					break;
					
				case "avg_speed":
					$sort_col = 'avg_speed';
					break;
					
				case "cadence":
					$sort_col = 'cadence';
					break;
					
				case "time":
					$sort_col = 'minutes';
					break;
					
				case "bike_id":
					$sort_col = 'bike_id';
					break;
				
				default:
					$sort_col = 'startdate';
				
			}
		
			$sort_order = (isset($_GET['cy_sort'])) ? $_GET['cy_sort'] : 'desc';
		
		} else {
			$sort_col = 'startdate';
			$sort_order = 'desc';
		}
	
		$sql  = 'select * from '.$table_name.' order by '.mysql_escape_string($sort_col).' '.mysql_escape_string($sort_order);
		$rides = $wpdb->get_results($sql, ARRAY_A);
	
		?>
		
		<div class="wrap">
		
			<?php echo cy_admin_navigation('manage','manage'); ?>
		
			<h3>Manage Rides</h3>
			
			<div id="cy_manage_list">
				<a href="?page=cyclopress/cyclopress.php&manage=1" class="here">List</a>
				<a href="?page=cyclopress/cyclopress.php&manage_calendar=1">Calendar</a>
			</div>
	
			<table class="widefat cy_manage_table">
				
				<?php
				
				if (sizeof($rides)) {
					
					$i = 0;
					
					?>
					
					<thead>
						<tr>
							<th><a href="?page=cyclopress/cyclopress.php&manage=1&cy_sort_col=date&cy_sort=<?php if ($sort_col=='startdate') { if ($sort_order=='desc') { echo 'asc'; } else { echo 'desc'; } } else  { echo 'desc'; } ?>" class="cy_sort">Date<?php if ($sort_col=='startdate') { if ($sort_order=='desc') { echo '&nbsp;&darr;'; } else { echo '&nbsp;&uarr;'; } } ?></a></th>
							<th><a href="?page=cyclopress/cyclopress.php&manage=1&cy_sort_col=distance&cy_sort=<?php if ($sort_col=='miles') { if ($sort_order=='desc') { echo 'asc'; } else { echo 'desc'; } } else  { echo 'desc'; } ?>" class="cy_sort">Distance<?php if ($sort_col=='miles') { if ($sort_order=='desc') { echo '&nbsp;&darr;'; } else { echo '&nbsp;&uarr;'; } } ?></a></th>
							<th><a href="?page=cyclopress/cyclopress.php&manage=1&cy_sort_col=avg_speed&cy_sort=<?php if ($sort_col=='avg_speed') { if ($sort_order=='desc') { echo 'asc'; } else { echo 'desc'; } } else  { echo 'desc'; } ?>" class="cy_sort">Average Speed<?php if ($sort_col=='avg_speed') { if ($sort_order=='desc') { echo '&nbsp;&darr;'; } else { echo '&nbsp;&uarr;'; } } ?></a></th>
							<th><a href="?page=cyclopress/cyclopress.php&manage=1&cy_sort_col=max_speed&cy_sort=<?php if ($sort_col=='max_speed') { if ($sort_order=='desc') { echo 'asc'; } else { echo 'desc'; } } else  { echo 'desc'; } ?>" class="cy_sort">Max Speed<?php if ($sort_col=='max_speed') { if ($sort_order=='desc') { echo '&nbsp;&darr;'; } else { echo '&nbsp;&uarr;'; } } ?></a></th>
							<th><a href="?page=cyclopress/cyclopress.php&manage=1&cy_sort_col=cadence&cy_sort=<?php if ($sort_col=='cadence') { if ($sort_order=='desc') { echo 'asc'; } else { echo 'desc'; } } else  { echo 'desc'; } ?>" class="cy_sort">Cadence<?php if ($sort_col=='cadence') { if ($sort_order=='desc') { echo '&nbsp;&darr;'; } else { echo '&nbsp;&uarr;'; } } ?></a></th>
							<th><a href="?page=cyclopress/cyclopress.php&manage=1&cy_sort_col=time&cy_sort=<?php if ($sort_col=='minutes') { if ($sort_order=='desc') { echo 'asc'; } else { echo 'desc'; } } else  { echo 'desc'; } ?>" class="cy_sort">Time<?php if ($sort_col=='minutes') { if ($sort_order=='desc') { echo '&nbsp;&darr;'; } else { echo '&nbsp;&uarr;'; } } ?></a></th>
							<th><a href="?page=cyclopress/cyclopress.php&manage=1&cy_sort_col=bike_id&cy_sort=<?php if ($sort_col=='bike_id') { if ($sort_order=='desc') { echo 'asc'; } else { echo 'desc'; } } else  { echo 'desc'; } ?>" class="cy_sort">Bike<?php if ($sort_col=='bike_id') { if ($sort_order=='desc') { echo '&nbsp;&darr;'; } else { echo '&nbsp;&uarr;'; } } ?></a></th>
							<th>Notes</th>
						</tr>
					</thead>
					
					<tbody>
					<?php
					
					foreach ($rides as $ride) {
				
						$hours = floor($ride['minutes']/60);
						$minutes = floor($ride['minutes']%60);
						$h_text = ($hours == 1) ? 'hour' : 'hours';
						$m_text = ($minutes == 1) ? 'minute' : 'minutes';
						$bike = cy_get_bike($ride['bike_id']);
						
						if ($i%2 == 0) {
							$c = '';
						} else {
							$c = 'alternate';
						}
					
						?>
						
						<tr class="<?php echo $c; ?>">
							<td><strong><a href="?page=cyclopress/cyclopress.php&manage=1&cy_ride_id=<?php echo $ride['id']; ?>"><?php echo date('F j, Y g:ia', strtotime($ride['startdate'])); ?></a></strong></td>
							<td><?php echo $ride['miles'] . ' ' . cy_distance_text(); ?></td>
							<td><?php echo $ride['avg_speed'] . ' '. cy_speed_text(); ?></td>
							<td><?php echo $ride['max_speed'] . ' '. cy_speed_text(); ?></td>
							<td><?php echo $ride['cadence']; ?> rpm</td>
							<td><?php echo ($hours == 0) ? $ride['minutes'] . ' minutes' : $hours . ' '.$h_text.', ' . $minutes . ' '.$m_text; ?></td>
							<td><?php echo $bike->label; ?></td>
							<td><?php echo (strlen(trim(strip_tags($ride['notes']))) > 50) ? substr(trim(strip_tags($ride['notes'])), 0, 50).'...' : trim(strip_tags($ride['notes'])); ?></td>
						</tr>
							
						<?php
				
						$i++;
				
					} // end foreach
					
					?>
					</tbody>
					<?php
				
				} else {
				
					?>
					<tbody>
					<tr>
						<th>No Rides! Get out there on your bike!</th>
					</tr>
					</tbody>
					<?php
				
				}
				
				?>
				
			</table>
		
		</div>
		
		<?php
		
	}
	
}

/**
 * The "Manage Rides" page viewed as a calendar.
 */
function cy_manage_calendar_page() {

	global $wpdb;
	$table_name = $wpdb->prefix . "cy_rides";
	
	if ($_POST['submitted']) {
		
		cy_write_page();
	
	} else if (isset($_GET['cy_ride_id']) && $_GET['cy_ride_id']) {
	
		$sql  = 'select * from '.$table_name.' where id=' . mysql_escape_string($_GET['cy_ride_id']);
		$ride_result = $wpdb->get_results($sql, ARRAY_A);
		$db_ride = $ride_result[0];
	
		$ride = new CYRide();
		$ride->load($db_ride);
	
		cy_write_page($ride);
	
	} else {
	
		if (isset($_GET['cy_sort_col'])) {
		
			switch ($_GET['cy_sort_col']) {
				
				case "date":
					$sort_col = 'startdate';
					break;
				
				case "distance":
					$sort_col = 'miles';
					break;
					
				case "max_speed":
					$sort_col = 'max_speed';
					break;
					
				case "avg_speed":
					$sort_col = 'avg_speed';
					break;
					
				case "cadence":
					$sort_col = 'cadence';
					break;
					
				case "time":
					$sort_col = 'minutes';
					break;
					
				case "bike_id":
					$sort_col = 'bike_id';
					break;
				
				default:
					$sort_col = 'startdate';
				
			}
		
			$sort_order = (isset($_GET['cy_sort'])) ? $_GET['cy_sort'] : 'desc';
		
		} else {
			$sort_col = 'startdate';
			$sort_order = 'desc';
		}
	
		$sql  = 'select * from '.$table_name.' order by '.mysql_escape_string($sort_col).' '.mysql_escape_string($sort_order);
		$rides = $wpdb->get_results($sql, ARRAY_A);
	
		?>
		
		<div class="wrap">
		
			<?php echo cy_admin_navigation('manage','manage_calendar'); ?>
		
			<h3>Manage Rides</h3>
	
			<div id="cy_manage_list">
				<a href="?page=cyclopress/cyclopress.php&manage=1">List</a>
				<a href="?page=cyclopress/cyclopress.php&manage_calendar=1" class="here">Calendar</a>
			</div>
	
			<table class="widefat cy_manage_table">
				
				<?php
				
				if (sizeof($rides)) {
					
					$i = 0;
					
					?>
					
					<thead>
						<tr>
							<th><a href="?page=cyclopress/cyclopress.php&manage=1&cy_sort_col=date&cy_sort=<?php if ($sort_col=='startdate') { if ($sort_order=='desc') { echo 'asc'; } else { echo 'desc'; } } else  { echo 'desc'; } ?>" class="cy_sort">Date<?php if ($sort_col=='startdate') { if ($sort_order=='desc') { echo '&nbsp;&darr;'; } else { echo '&nbsp;&uarr;'; } } ?></a></th>
							<th><a href="?page=cyclopress/cyclopress.php&manage=1&cy_sort_col=distance&cy_sort=<?php if ($sort_col=='miles') { if ($sort_order=='desc') { echo 'asc'; } else { echo 'desc'; } } else  { echo 'desc'; } ?>" class="cy_sort">Distance<?php if ($sort_col=='miles') { if ($sort_order=='desc') { echo '&nbsp;&darr;'; } else { echo '&nbsp;&uarr;'; } } ?></a></th>
							<th><a href="?page=cyclopress/cyclopress.php&manage=1&cy_sort_col=avg_speed&cy_sort=<?php if ($sort_col=='avg_speed') { if ($sort_order=='desc') { echo 'asc'; } else { echo 'desc'; } } else  { echo 'desc'; } ?>" class="cy_sort">Average Speed<?php if ($sort_col=='avg_speed') { if ($sort_order=='desc') { echo '&nbsp;&darr;'; } else { echo '&nbsp;&uarr;'; } } ?></a></th>
							<th><a href="?page=cyclopress/cyclopress.php&manage=1&cy_sort_col=max_speed&cy_sort=<?php if ($sort_col=='max_speed') { if ($sort_order=='desc') { echo 'asc'; } else { echo 'desc'; } } else  { echo 'desc'; } ?>" class="cy_sort">Max Speed<?php if ($sort_col=='max_speed') { if ($sort_order=='desc') { echo '&nbsp;&darr;'; } else { echo '&nbsp;&uarr;'; } } ?></a></th>
							<th><a href="?page=cyclopress/cyclopress.php&manage=1&cy_sort_col=cadence&cy_sort=<?php if ($sort_col=='cadence') { if ($sort_order=='desc') { echo 'asc'; } else { echo 'desc'; } } else  { echo 'desc'; } ?>" class="cy_sort">Cadence<?php if ($sort_col=='cadence') { if ($sort_order=='desc') { echo '&nbsp;&darr;'; } else { echo '&nbsp;&uarr;'; } } ?></a></th>
							<th><a href="?page=cyclopress/cyclopress.php&manage=1&cy_sort_col=time&cy_sort=<?php if ($sort_col=='minutes') { if ($sort_order=='desc') { echo 'asc'; } else { echo 'desc'; } } else  { echo 'desc'; } ?>" class="cy_sort">Time<?php if ($sort_col=='minutes') { if ($sort_order=='desc') { echo '&nbsp;&darr;'; } else { echo '&nbsp;&uarr;'; } } ?></a></th>
							<th><a href="?page=cyclopress/cyclopress.php&manage=1&cy_sort_col=bike_id&cy_sort=<?php if ($sort_col=='bike_id') { if ($sort_order=='desc') { echo 'asc'; } else { echo 'desc'; } } else  { echo 'desc'; } ?>" class="cy_sort">Bike<?php if ($sort_col=='bike_id') { if ($sort_order=='desc') { echo '&nbsp;&darr;'; } else { echo '&nbsp;&uarr;'; } } ?></a></th>
							<th>Notes</th>
						</tr>
					</thead>
					
					<tbody>
					<?php
					
					foreach ($rides as $ride) {
				
						$hours = floor($ride['minutes']/60);
						$minutes = floor($ride['minutes']%60);
						$h_text = ($hours == 1) ? 'hour' : 'hours';
						$m_text = ($minutes == 1) ? 'minute' : 'minutes';
						$bike = cy_get_bike($ride['bike_id']);
						
						if ($i%2 == 0) {
							$c = '';
						} else {
							$c = 'alternate';
						}
					
						?>
						
						<tr class="<?php echo $c; ?>">
							<td><strong><a href="?page=cyclopress/cyclopress.php&manage=1&cy_ride_id=<?php echo $ride['id']; ?>"><?php echo date('F j, Y g:ia', strtotime($ride['startdate'])); ?></a></strong></td>
							<td><?php echo $ride['miles'] . ' ' . cy_distance_text(); ?></td>
							<td><?php echo $ride['avg_speed'] . ' '. cy_speed_text(); ?></td>
							<td><?php echo $ride['max_speed'] . ' '. cy_speed_text(); ?></td>
							<td><?php echo $ride['cadence']; ?> rpm</td>
							<td><?php echo ($hours == 0) ? $ride['minutes'] . ' minutes' : $hours . ' '.$h_text.', ' . $minutes . ' '.$m_text; ?></td>
							<td><?php echo $bike->label; ?></td>
							<td><?php echo (strlen(trim(strip_tags($ride['notes']))) > 50) ? substr(trim(strip_tags($ride['notes'])), 0, 50).'...' : trim(strip_tags($ride['notes'])); ?></td>
						</tr>
							
						<?php
				
						$i++;
				
					} // end foreach
					
					?>
					</tbody>
					<?php
				
				} else {
				
					?>
					<tbody>
					<tr>
						<th>No Rides! Get out there on your bike!</th>
					</tr>
					</tbody>
					<?php
				
				}
				
				?>
				
			</table>
		
		</div>
		
		<?php
		
	}
	
}

/**
 * The "Add a Bike" and "Edit a Bike" page.
 */
function cy_write_bikes_page($bike=false) {
	
	global $wpdb;
	
	// processing for when the form is submitted
	if (isset($_POST['submitted'])) {
		
		// load the form data from POST
		$bike = new CYBike();
		$bike->load_post();
		
		// check that we got required data
		if (!$_POST['label']) {
			
			$saved = false;
			$e = 'Please fill in all required fields.';
			
		} else {
			
			$saved = cy_save_bike($bike);
	
		}
		
		if (!$saved) {
			?>
			<div id="message" class="updated fade" style="margin-top: 1.5em;"><p>Error: <?php echo $e; $wpdb->print_error(); ?></p></div>
			<?PHP
		} else {
			?>
			<div id="message" class="updated fade" style="margin-top: 1.5em;"><p>Your new bike has been saved.</p></div>
			<?PHP
			$bike = new CYBike();
		}
			
	} else if ($bike === false || !is_object($bike)) {
	
		$bike = new CYBike();
	
	}

	?>
	<div class="wrap">
	
		<?php echo cy_admin_navigation('manage_bikes','add_bikes'); ?>
	
		<h3>CycloPress Bikes</h3>
	
		<form name="cycling_bikes" action="" method="post" enctype="multipart/form-data">
			
			<input type="hidden" name="submitted" value="1" />
			
			<?php if ($bike->id != NULL) { ?>
			<input type="hidden" name="id" value="<?php echo $bike->id; ?>" />
			<?php } ?>
			
			<table class="widefat">
			  <tr valign="top">
				<th scope="row" style="text-align: right;">*Label:</th>
				<td><input type="text" name="label" id="label" size="20" value="<?php echo htmlentities(stripslashes($bike->label)); ?>" /></td>
			  </tr>
			  <tr valign="top">
				<th scope="row" style="text-align: right;">Make:</th>
				<td><input type="text" name="make" id="make" size="20" value="<?php echo htmlentities(stripslashes($bike->make)); ?>" /></td>
			  </tr>
			  <tr valign="top">
				<th scope="row" style="text-align: right;">Model:</th>
				<td><input type="text" name="model" id="model" size="20" value="<?php echo htmlentities(stripslashes($bike->model)); ?>" /></td>
			  </tr>
			  <tr valign="top">
				<th scope="row" style="text-align: right;">Year:</th>
				<td><input type="text" name="year" id="year" size="5" value="<?php echo htmlentities(stripslashes($bike->year)); ?>" /></td>
			  </tr>
			  <tr valign="top">
				<th scope="row" style="text-align: right;">Notes:</th>
				<td><textarea name="notes" rows="10" cols="50"><?php echo htmlentities(stripslashes($ride->notes)); ?></textarea></td>
			  </tr>
			</table>
			<p class="submit"><input type="submit" name="Submit" value="Save" style="font-weight: bold;" /></p>
		</form>
		
	</div>
	<?PHP

}

/**
 * The "Manage Bikes" page.
 */
function cy_manage_bikes_page() {

	global $wpdb;
	$table_name = $wpdb->prefix . "cy_bikes";
	
	if ($_POST['submitted']) {
		
		cy_write_bikes_page();
	
	} else if (isset($_GET['cy_bike_id']) && $_GET['cy_bike_id']) {
		
		$bike = cy_get_bike($_GET['cy_bike_id']);
	
		cy_write_bikes_page($bike);
	
	} else {
	
		if (isset($_GET['cy_sort_col'])) {
		
			switch ($_GET['cy_sort_col']) {
					
				case "make":
					$sort_col = 'make';
					break;
					
				case "model":
					$sort_col = 'model';
					break;
					
				case "year":
					$sort_col = 'year';
					break;
				
				case "label":
					$sort_col = 'label';
					break;
				
				default:
					$sort_col = 'label';
				
			}
		
			$sort_order = (isset($_GET['cy_sort'])) ? $_GET['cy_sort'] : 'asc';
		
		} else {
			$sort_col = 'label';
			$sort_order = 'asc';
		}
	
		$bikes = cy_get_bikes($sort_col, $sort_order);
	
		?>
		
		<div class="wrap">
		
			<?php echo cy_admin_navigation('manage_bikes','manage_bikes'); ?>
		
			<h3>Manage Bikes</h3>
	
			<table class="widefat cy_manage_table">
				
				<?php
				
				if (sizeof($bikes)) {
					
					$i = 0;
					
					?>
					
					<thead>
						<tr>
							<!--<th>Default</th>-->
							<th><a href="?page=cyclopress/cyclopress.php&manage_bikes=1&cy_sort_col=label&cy_sort=<?php if ($sort_col=='label') { if ($sort_order=='desc') { echo 'asc'; } else { echo 'desc'; } } else  { echo 'asc'; } ?>" class="cy_sort">Label<?php if ($sort_col=='label') { if ($sort_order=='desc') { echo '&nbsp;&darr;'; } else { echo '&nbsp;&uarr;'; } } ?></a></th>
							<th><a href="?page=cyclopress/cyclopress.php&manage_bikes=1&cy_sort_col=make&cy_sort=<?php if ($sort_col=='make') { if ($sort_order=='desc') { echo 'asc'; } else { echo 'desc'; } } else  { echo 'asc'; } ?>" class="cy_sort">Make<?php if ($sort_col=='make') { if ($sort_order=='desc') { echo '&nbsp;&darr;'; } else { echo '&nbsp;&uarr;'; } } ?></a></th>
							<th><a href="?page=cyclopress/cyclopress.php&manage_bikes=1&cy_sort_col=model&cy_sort=<?php if ($sort_col=='model') { if ($sort_order=='desc') { echo 'asc'; } else { echo 'desc'; } } else  { echo 'asc'; } ?>" class="cy_sort">Model<?php if ($sort_col=='model') { if ($sort_order=='desc') { echo '&nbsp;&darr;'; } else { echo '&nbsp;&uarr;'; } } ?></a></th>
							<th><a href="?page=cyclopress/cyclopress.php&manage_bikes=1&cy_sort_col=year&cy_sort=<?php if ($sort_col=='year') { if ($sort_order=='desc') { echo 'asc'; } else { echo 'desc'; } } else  { echo 'desc'; } ?>" class="cy_sort">Year<?php if ($sort_col=='year') { if ($sort_order=='desc') { echo '&nbsp;&darr;'; } else { echo '&nbsp;&uarr;'; } } ?></a></th>
							<th>Notes</th>
						</tr>
					</thead>
					
					<tbody>
					<?php
					
					foreach ($bikes as $bike) {
						
						if ($i%2 == 0) {
							$c = '';
						} else {
							$c = 'alternate';
						}
					
						?>
						
						<tr class="<?php echo $c; ?>">
							<!--<td><input type="checkbox" name="default_bike" value="<?php echo $bike['id']; ?>"<?php if ($bike['default'] == '1') { echo ' checked="checked"'; } ?> /></td>-->
							<td><strong><a href="?page=cyclopress/cyclopress.php&manage_bikes=1&cy_bike_id=<?php echo $bike['id']; ?>"><?php echo $bike['label']; ?></a></strong></td>
							<td><?php echo $bike['make']; ?></td>
							<td><?php echo $bike['model']; ?></td>
							<td><?php echo $bike['year']; ?></td>
							<td><?php echo (strlen(trim(strip_tags($bike['notes']))) > 50) ? substr(trim(strip_tags($bike['notes'])), 0, 50).'...' : trim(strip_tags($bike['notes'])); ?></td>
						</tr>
							
						<?php
				
						$i++;
				
					} // end foreach
					
					?>
					</tbody>
					<?php
				
				} else {
				
					?>
					<tbody>
					<tr>
						<td>No bikes! What are you riding these days?</td>
					</tr>
					<tr>
						<td><strong><a href="?page=cyclopress/cyclopress.php&add_bikes=1">Add a new bike.</a></strong></td>
					</tr>
					</tbody>
					<?php
				
				}
				
				?>
				
			</table>
		
		</div>
		
		<?php
		
	}
	
}

/**
 * CycloPress debugging information page.
 */
function cy_debug_page() {
	
	global $wpdb, $wp_version, $cy_version, $cy_db_version, $cy_dir, $cy_graph_dir_full;
	
	$rides_table = $wpdb->prefix . "cy_rides";
	$bikes_table = $wpdb->prefix . "cy_bikes";
	$types_table = $wpdb->prefix . "cy_types";
	
	// all options
	$opts = cy_get_default_options();
		
	// stats
	$stats = cy_db_stats();
	
	?>
	
	<div class="wrap">
	
		<?php echo cy_admin_navigation('tools','debug'); ?>
	
		<h3>CycloPress Debugging Information</h3>
		
		<table class="widefat">
			<tr>
				<th>PHP</th>
				<?PHP if (!cy_check_php()) { ?>
				<td class="cy_error">PHP version <?php echo phpversion(); ?> is not supported! PHP version 4.3.1 or higher is required.</td>
				<?PHP } else { ?>
				<td class="cy_ok">PHP version <?php echo phpversion(); ?> installed.</td>
				<?PHP } ?>
			</tr>
			<tr>
				<th>GD</th>
				<?PHP if (!cy_check_gd()) { if (function_exists('gd_info')) { $GDArray = gd_info(); } else { $GDArray = false; } ?>
				<td class="cy_error"><?php if (is_array($GDArray)) { echo 'GD version '.ereg_replace('[[:alpha:][:space:]()]+', '', $GDArray['GD Version']).' is not supported. GD version 2 or higher is required.'; } else { echo 'GD library is not installed!'; } ?> You cannot create graphs.</td>
				<?PHP } else { $GDArray = gd_info(); ?>
				<td class="cy_ok">GD version <?php echo ereg_replace('[[:alpha:][:space:]()]+', '', $GDArray['GD Version']); ?> installed.</td>
				<?PHP } ?>
			</tr>
			<tr>
				<th>Cache is writable</th>
				<?PHP if (!cy_is_cache_writable()) { ?>
				<td class="cy_warning">
					Graphs directory is not writable for PHP!<br />
					Please make <?php echo $cy_graph_dir_full; ?> writable.<br />
					You cannot create graphs until this directory can be written to.
				</td>
				<?PHP } else { ?>
				<td class="cy_ok">Graphs directory is writable.</td>
				<?PHP } ?>
			</tr>
			
			<tr>
				<th>WordPress</th>
				<td><?PHP echo $wp_version; ?></td>
			</tr>
			
			<tr>
				<th>Server</th>
				<td><?PHP echo $_SERVER['SERVER_SOFTWARE']; ?></td>
			</tr>
			
			<tr>
				<th>User Agent</th>
				<td><?PHP echo $_SERVER['HTTP_USER_AGENT']; ?></td>
			</tr>
			
			<tr>
				<th>cy_rides table exists</th>
				<td><?PHP echo ($wpdb->get_var("show tables like '$rides_table'") == $rides_table) ? 'yes ('.$rides_table.')' : 'no'; ?></td>
			</tr>
			
			<tr>
				<th>cy_bikes table exists</th>
				<td><?PHP echo ($wpdb->get_var("show tables like '$bikes_table'") == $bikes_table) ? 'yes ('.$bikes_table.')' : 'no'; ?></td>
			</tr>
			
			<tr>
				<th>cy_types table exists</th>
				<td><?PHP echo ($wpdb->get_var("show tables like '$types_table'") == $types_table) ? 'yes ('.$types_table.')' : 'no'; ?></td>
			</tr>
			
			<tr>
				<th>cy_version</th>
				<td><?PHP echo $cy_version; ?></td>
			</tr>
			
			<tr>
				<th>cy_db_version</th>
				<td><?PHP echo $cy_db_version; ?></td>
			</tr>
			
			<?PHP
			foreach($opts as $k=>$v) {
				
				if ($k == 'cy_version' || $k == 'cy_db_version') continue;
				
				$val = get_option($k);
				?>
				<tr>
					<th><?php echo $k; ?></th>
					<td><?php echo $val; ?></td>
				</tr>
				<?PHP
			}
			?>
			
			<tr>
				<th>total rides</th>
				<td><?php echo $stats['total_rides']; ?></td>
			</tr>
			
		</table>

	</div>
	
	<?PHP
	
}

/**
 * CycloPress cycling page.
 */
function cy_cycling_page() {

	global $cy_dir;

	?>
	<div class="wrap">

		<?php echo cy_admin_navigation('options','cycling'); ?>
	
		<h3>Cycling Page</h3>
	
		<?php
		
		if (isset($_GET['cy_create_page']) && $_GET['cy_create_page']) {
		
			$post = array(
				'comment_status' => 'closed',
				'post_content' => '<?php $stats = cy_db_stats(); if ($stats) { ?><p>These statistics have been tracked since <?php echo cy_get_first_ride_date(); ?> and were last updated on <?php echo cy_get_last_ride_date(); ?>.</p><?php echo cy_get_summary(true); ?><h3>Distance</h3><?php echo cy_get_graph_img_tag("distance"); ?><h3>Average Speed</h3><?php echo cy_get_graph_img_tag("average_speed"); } else { ?><p>No stats! Get out there and ride!</p><?php } ?>',
				'post_status' => 'draft', // draft, publish, pending
				'post_title' => 'Cycling Stats',
				'post_type' => 'page',
			);
		
			$page_id = wp_insert_post($post);
			
			if ($page_id) {
				?><p class="cy_ok">Your page was created, but it is not yet published.<?php
				update_option('cy_page_id', $page_id);
				update_option('cy_page_name', 'Cycling Stats');
				update_option('cy_page_status', 'draft');
				update_option('cy_show_summary', '1');
				update_option('cy_show_detailed_stats', '1');
				update_option('cy_show_distance_graph', '1');
				update_option('cy_show_avg_speed_graph', '1');
			} else {
				?><p class="cy_error">There was an error creating your page.</p><?php
			}
			
		} else if (isset($_GET['cy_update_page']) && $_GET['cy_update_page']) {
		
			// create the PHP string for the page
			$php = '<?php $stats = cy_db_stats(); if ($stats) { ?>';
			if (isset($_GET['cy_show_summary']) && $_GET['cy_show_summary']) {
				$php .= ' <p>These statistics have been tracked since <?php echo cy_get_first_ride_date(); ?> and were last updated on <?php echo cy_get_last_ride_date(); ?>.</p> ';
			}
			if (isset($_GET['cy_show_detailed_stats']) && $_GET['cy_show_detailed_stats']) {
				$php .= ' <?php echo cy_get_summary(true); ?> ';
			}
			if (isset($_GET['cy_show_distance_graph']) && $_GET['cy_show_distance_graph']) {
				$php .= ' <h3>Distance</h3><?php echo cy_get_graph_img_tag("distance"); ?> ';
			}
			if (isset($_GET['cy_show_avg_speed_graph']) && $_GET['cy_show_avg_speed_graph']) {
				$php .= ' <h3>Average Speed</h3><?php echo cy_get_graph_img_tag("average_speed"); ?> ';
			}
			$php .= '<?php } else { echo "<p>No stats! Get out there and ride!</p>"; } ?>';
		
			// update the page
			$post = array(
				'post_status' => $_GET['cy_page_status'],
				'post_title' => $_GET['cy_page_name'],
				'post_content' => $php,
				'ID' => get_option('cy_page_id'),
			);
			$page_id = wp_update_post($post);
			
			// update the cyclopress options
			$cy_show_summary = (isset($_GET['cy_show_summary']) && $_GET['cy_show_summary']) ? '1' : '0';
			$cy_show_detailed_stats = (isset($_GET['cy_show_detailed_stats']) && $_GET['cy_show_detailed_stats']) ? '1' : '0';
			$cy_show_distance_graph = (isset($_GET['cy_show_distance_graph']) && $_GET['cy_show_distance_graph']) ? '1' : '0';
			$cy_show_avg_speed_graph = (isset($_GET['cy_show_avg_speed_graph']) && $_GET['cy_show_avg_speed_graph']) ? '1' : '0';
			update_option('cy_page_name', $_GET['cy_page_name']);
			update_option('cy_page_status', $_GET['cy_page_status']);
			update_option('cy_show_summary', $cy_show_summary);
			update_option('cy_show_detailed_stats', $cy_show_detailed_stats);
			update_option('cy_show_distance_graph', $cy_show_distance_graph);
			update_option('cy_show_avg_speed_graph', $cy_show_avg_speed_graph);
			
			?><p class="cy_ok">Your cycling page has been updated.</p><?php
		
		} else if (isset($_GET['cy_clear_page']) && $_GET['cy_clear_page']) {
		
			update_option('cy_page_id', '');
			
			?><p class="cy_ok">OK, CycloPress no longer thinks you still have a cycling page.</p><?php
		
		}
		
		// has there been a page created yet?
		if (!get_option('cy_page_id')) {
		
		?>
		
			<p>From here you can create a page where your viewers can see your graphs and stats.</p>
		
			<p>You haven't created a cycling page yet. Would you like to <a href="?page=cyclopress/cyclopress.php&cycling=1&cy_create_page=1">create a cycling page now</a>?</p>
				
		<?php
		
		} else {
		
			$page = get_post(get_option('cy_page_id'));
		
			if(!$page) {
			
				?>
				
				<p>It looks like you've deleted your cycling page from WordPress. That's OK. Please <a href="?page=cyclopress/cyclopress.php&cycling=1&cy_clear_page=1">tell CycloPress that it's been deleted</a>.</p>
			
				<?php
				
			} else {
			
				?>
				
				<p><a href="<?php echo get_bloginfo('url').'/?p='.get_option('cy_page_id'); ?>">View cycling page &raquo;</a></p>
				
				<p><strong>Never modify your cycling page from elsewhere unless you know what you're doing.</strong> It contains some PHP to show your stats, and unless you have a PHP plugin for WordPress, you may garble the code by editing the page.</p>
				
				<form action="plugins.php" method="get" enctype="multipart/form-data">
				
					<input type="hidden" name="page" value="cyclopress/cyclopress.php" />
					<input type="hidden" name="cycling" value="1" />
					<input type="hidden" name="cy_update_page" value="1" />
				
					<table class="widefat">
						<tr>
							<th align="right">Cycling Page Name</th>
							<td><input type="text" name="cy_page_name" value="<?php echo get_option('cy_page_name'); ?>" /></td>
						</tr>
						<tr>
							<th align="right">Status</th>
							<td>
								<select name="cy_page_status">
									<option value="draft"<?php if ($page->post_status == 'draft') { echo ' selected="selected"'; } ?>>Draft</option>
									<option value="publish"<?php if ($page->post_status == 'publish') { echo ' selected="selected"'; } ?>>Published</option>
								</select>
							</td>
						</tr>
						<tr>
							<th align="right"><input type="checkbox" name="cy_show_summary" id="cy_show_summary" value="1"<?php if (get_option('cy_show_summary')) { echo ' checked="checked"'; } ?> /></th>
							<td>
								<label for="cy_show_summary"><strong>Show Tracking Summary:</strong></label>
								<p>These statistics have been tracked since <?php echo cy_get_first_ride_date(); ?> and were last updated on <?php echo cy_get_last_ride_date(); ?>.</p>
							</td>
						</tr>
						<tr>
							<th align="right"><input type="checkbox" name="cy_show_detailed_stats" id="cy_show_detailed_stats" value="1"<?php if (get_option('cy_show_detailed_stats')) { echo ' checked="checked"'; } ?> /></th>
							<td>
								<label for="cy_show_detailed_stats"><strong>Show Detailed Stats:</strong></label>
								<p><?php echo cy_get_summary(); ?></p>
							</td>
						</tr>
						<tr>
							<th align="right"><input type="checkbox" name="cy_show_distance_graph" id="cy_show_distance_graph" value="1"<?php if (get_option('cy_show_distance_graph')) { echo ' checked="checked"'; } ?> /></th>
							<td>
								<label for="cy_show_distance_graph"><strong>Show Distance Graph:</strong></label>
								<p><?php echo cy_get_graph_img_tag('distance'); ?></p>
							</td>
						</tr>
						<tr>
							<th align="right"><input type="checkbox" name="cy_show_avg_speed_graph" id="cy_show_avg_speed_graph" value="1"<?php if (get_option('cy_show_avg_speed_graph')) { echo ' checked="checked"'; } ?> /></th>
							<td>
								<label for="cy_show_avg_speed_graph"><strong>Show Average Speed Graph:</strong></label>
								<p><?php echo cy_get_graph_img_tag('average_speed'); ?></p>
							</td>
						</tr>
					</table>
					
					<p class="submit"><input type="submit" name="Submit" value="Save" style="font-weight: bold;" /></p>
										
				</form>
														
				<?php
		
			}
		}
		
		?>

	</div>
	<?PHP

}

/**
 * CycloPress stats page.
 */
function cy_stats_page() {

	global $cy_dir;

	?>
	<div class="wrap">
	
		<?php
		
		echo cy_admin_navigation('export','stats');
		
		$stats = cy_db_stats();
		
		if ($stats) {
		
			cy_css();
		
			$first_ride_date = cy_get_last_ride_date();
		
			?><p>These statistics have been tracked since <?php echo cy_get_first_ride_date(); ?> and were last updated on <?php echo cy_get_last_ride_date(); ?>.</p><?php
		
			echo cy_get_summary(true);
			
			?><h3>Distance</h3><?php
			
			echo cy_get_graph_img_tag('distance');
			
			?><h3>Average Speed</h3><?php
			
			echo cy_get_graph_img_tag('average_speed');
		
		} else {
		
			?><p>No stats! Get out there and ride!</p><?php
		
		}
				
		?>

	</div>
	<?PHP

}

/**
 * CycloPress information page.
 */
function cy_about_page() {

	global $cy_dir;

	?>
	<div class="wrap">
	
		<?php echo cy_admin_navigation('export','about'); ?>
	
		<p>CycloPress was created by <a href="http://amwhalen.com">Andrew M. Whalen</a>.</p>
		
		<ul>
			<li><a href="http://amwhalen.com/blog/projects/cyclopress/" target="_blank">Docs and Support on AMWhalen.com &raquo;</a></li>
			<li><a href="http://wordpress.org/extend/plugins/cyclopress/" target="_blank">CycloPress on WordPress.org &raquo;</a></li>
		</ul>
		
		<p><a href="http://amwhalen.com/blog/projects/cyclopress/"><img src="<?php echo $cy_dir; ?>/img/poweredby.gif" alt="Powered by CycloPress" /></a></p>
		
	</div>
	<?PHP

}

/**
 * CycloPress export page
 */
function cy_export_page() {

	?>
	<div class="wrap">
		
		<?php echo cy_admin_navigation('export','export'); ?>
		
		<div id="cy_export">
			
			<div id="cy_export_list">
				<a href="?page=cyclopress/cyclopress.php&export=1&cy_export_format=csv"<?php if (!isset($_GET['cy_export_format']) || $_GET['cy_export_format'] == 'csv') echo ' class="here"'; ?>>CSV</a>
				<a href="?page=cyclopress/cyclopress.php&export=1&cy_export_format=xml"<?php if (isset($_GET['cy_export_format']) && $_GET['cy_export_format'] == 'xml') echo ' class="here"'; ?>>XML</a>
			</div>
			<div id="cy_export_desc">
			<?php if (!isset($_GET['cy_export_format']) || $_GET['cy_export_format'] == 'csv') { ?>
				<p>Exporting as <acronym title="Comma Separated Values">CSV</acronym> allows you to open your stats in Excel or other spreadsheet software.</p>
			<?php } else { ?>
				<p>Exporting as <acronym title="Extensible Markup Language">XML</acronym> allows you to <em>eventually</em> (not yet implemented) import your stats and options back into CycloPress. For now you can back up your stats to be safe.</p>
			<?php } ?>
			<p>Copy this text into a plain-text (not Word!) file and save it with a <strong><?php if (isset($_GET['cy_export_format']) && $_GET['cy_export_format'] == 'xml') { echo '.xml'; } else { echo '.csv'; } ?></strong> extension.</p>
			</div>
			<textarea id="cy_export_content" rows="20" cols="80"><?php echo htmlentities(cy_export($_GET['cy_export_format'])); ?></textarea>
		
		</div>
	
	</div>
	<?PHP

}

/**
 * Returns month text given the month's number.
 */
function cy_get_month($number) {

	$months = array(
		1 => 'January',
		2 => 'February',
		3 => 'March',
		4 => 'April',
		5 => 'May',
		6 => 'June',
		7 => 'July',
		8 => 'August',
		9 => 'September',
		10 => 'October',
		11 => 'November',
		12 => 'December',
	);
	
	return $months[$number];

}

/**
 * Cycling CSS to be put in the header of pages.
 */
function cy_css() {

	?>
	
	<style type="text/css">
		dl.cy {
			margin: 20px 0;
		}
		dl.cy dt {
			clear: left;
			float: left;
			width: 180px;
			font-weight: bold;
			text-align: right;
		}
		dl.cy dd {
			margin-left: 200px;
			margin-bottom: 10px;
		}
		dl.cy dd small{
			color: #666;
		}
	</style>
	
	<?PHP

}

/**
 * Cycling CSS for the admin page.
 */
function cy_admin_css() {	

	?>
	
	<style type="text/css">
		.cy_ok {
			background: #cfc;
			color: #060;
			font-weight: bold;
		}
		.cy_warning {
			background: #fec;
			color: #000;
			font-weight: bold;
		}
		.cy_error {
			background: #fcc;
			color: #c00;
			font-weight: bold;
		}
		.cy_sort {
			color: #ddd;
		}
		.cy_admin_navigation {
			margin: 1.5em 0 0 0;
			padding: 4px;
			border-bottom: 3px solid #ddd;
		}
		.cy_admin_subnav {
			background: #ddd;
			padding: 4px;
			margin: 0 0 1.5em 0;
		}
		.cy_admin_navigation ul,
		.cy_admin_subnav ul {
			margin: 0;
			padding: 0;
			display: inline;
			background: none;
		}
		.cy_admin_navigation ul li,
		.cy_admin_subnav ul li {
			display: inline;
			padding: 5px;
		}
		.cy_admin_navigation ul li a,
		.cy_admin_subnav ul li a {
			padding: 5px;
			border: 1px solid #eee;
			border-width: 1px 1px 0 1px;
			text-decoration: none;
		}
		.cy_admin_navigation ul li a:hover,
		.cy_admin_subnav ul li a:hover {
			border: 1px solid #ddd;
			border-width: 1px 1px 0 1px;
		}
		.cy_admin_navigation ul li.here a,
		.cy_admin_subnav ul li.here a {
			background: #ddd;
			font-weight: bold;
			color: #000;
			border: 1px solid #ddd;
			border-width: 1px 1px 0 1px;
		}
		.cy_admin_navigation ul li.cy_debug a {
			background: #fcc;
			color: #000;
			font-weight: bold;
		}
		.cy_manage_table th a {
			color: #000;
		}
		.cy_manage_table th a:hover {
			color: #666;
		}
		#cy_export {
			width: 600px;
		}
		#cy_manage_list {
			margin-left: 5px;
		}
		#cy_export_list,
		#cy_manage_list {
			padding: 5px 0;
		}
		#cy_export_list a,
		#cy_manage_list a {
			padding: 5px;
			background: #fff;
			border: 1px solid #ddd;
			border-width: 1px 1px 0 1px;
			text-decoration: none;
		}
		#cy_export_list a.here,
		#cy_manage_list a.here {
			background: #ddd;
			font-weight: bold;
			color: #000;
		}
		#cy_export_desc {
			background: #ddd;
		}
		#cy_export_desc p {
			margin: 0;
			padding: 5px;
		}
		#cy_export_content {
			width: 600px;
			margin: 0;
		}
	</style>
	
	<?PHP

}

/**
 * Returns the current unit for distance.
 * If a number is supplies, this will make it plural if necessary.
 */
function cy_distance_text($num=2) {

	if (get_option('cy_unit') == 'mile') {
		return ($num != 1) ? 'miles': 'mile';
	} else if (get_option('cy_unit') == 'kilometer') {
		return ($num != 1) ? 'kilometers': 'kilometer';
	} else {
		return ($num != 1) ? 'miles': 'mile';
	}
	
}

/**
 * Returns the current unit for speed.
 */
function cy_speed_text($num=2) {

	if (get_option('cy_unit') == 'mile') {
		return 'mph';
	} else if (get_option('cy_unit') == 'kilometer') {
		return 'kmh';
	} else {
		return 'mph';
	}
	
}

/**
 * Converts miles to kilometers OR mph to kph
 */
function cy_m2k($miles) {
	return 0.621371192*$miles;
}

/**
 * Converts kilometers to miles OR kph to mph
 */
function cy_k2m($kilometers) {
	return 1.609344*$kilometers;
}

/**
 * Creates all graphs
 */
function cy_create_all_graphs($year=false) {

	// first check if we have data
	global $wpdb;
	$table_name = $wpdb->prefix . "cy_rides";
	
	$sql = 'select * from '.$table_name;
	$result = $wpdb->query($sql);
	
	// create all graphs
	if ($result != 0) {
		cy_create_average_speed_graph($year);
		cy_create_distance_graph($year);
	} else {
		cy_empty_cache();
	}


}

/**
 * Creates and saves the average speed graph.
 */
function cy_create_average_speed_graph($year=false) {
	
	global $wpdb;
	$table_name = $wpdb->prefix . "cy_rides";
	
	$where = ($year) ? " where startdate like '$year%'" : '';

	$sql = 'select startdate, avg_speed from '.$table_name.$where.' order by startdate asc';
	$result = $wpdb->get_results($sql, ARRAY_A);
	
	$data = array();
	if ($result) {
		foreach ($result as $row) {
		
			$date = date("m/d/y H:i", strtotime($row['startdate']));
			
			$data[$date] = $row['avg_speed'];
		
		}
	}
	
	$graph_type = (get_option('cy_graph_type') == 'line') ? 0 : 1;
	
	cy_graph($data, 'Average Speed Over Time', get_option('cy_graph_width'), get_option('cy_graph_height'), 'cy_average_speed'.$year.'.png', $graph_type);

}

/**
 * Creates and saves the distance graph.
 */
function cy_create_distance_graph($year=false) {
	
	global $wpdb;
	$table_name = $wpdb->prefix . "cy_rides";
	
	$where = ($year) ? " where startdate like '$year%'" : '';

	$sql = 'select startdate, miles from '.$table_name.$where.' order by startdate asc';
	$result = $wpdb->get_results($sql, ARRAY_A);
	
	$data = array();
	if ($result) {
		foreach ($result as $row) {
		
			$date = date("m/d/y H:i", strtotime($row['startdate']));
			
			$data[$date] = $row['miles'];
		
		}
	}
	
	$graph_type = (get_option('cy_graph_type') == 'line') ? 0 : 1;
	
	cy_graph($data, 'Distance Over Time', get_option('cy_graph_width'), get_option('cy_graph_height'), 'cy_distance'.$year.'.png', $graph_type);

}

/**
 * Creates a graph.
 */
function cy_graph($associative_array, $title, $w, $h, $name='auto', $plot_type=0) {

	global $cy_graph_dir_full;
	
	switch($plot_type) {
	
		case 0:
			$use_line_plot = true;
			break;
		
		case 1:
			$use_bar_plot = true;
			break;
			
		default:
			return;
	
	}
	
	if (version_compare(phpversion(), '5') === 1) {
		$jpgraph = 'jpgraph-2.2/';
	} else {
		$jpgraph = 'jpgraph-1.26/';
	}
	
	require_once ($jpgraph . "jpgraph.php");
	
	if ($use_bar_plot) {
		require_once ($jpgraph . "jpgraph_bar.php"); // bar graph
	}
	else if ($use_line_plot) {
		require_once ($jpgraph . "jpgraph_line.php"); // line graph
	}
	
	// for the x-axis date scale
	require_once ($jpgraph . "jpgraph_date.php");
	
	$datay=array_values($associative_array);
	
	// Set the basic parameters of the graph
	// width, height, cache filename, minutes the cache is valid 
	$graph = new Graph($w, $h, $name, 1440);
	//$graph->img->SetAntiAliasing();
	
	// date/time scale for the x-axis
	if (sizeof($associative_array) >= 2) {
		$graph->SetScale("datlin");
	} else {
		// only used when there's only 1 data point
		$graph ->SetScale("textlin");
	}
	
	// left, right, top, bottom
	$graph->SetMargin(50,20,20,100);
	// set margins and turn the graph sideways
	//$graph->Set90AndMargin($left,$right,$top,$bottom);
	
	// Setup labels
	$lbl = array_keys($associative_array);
	$graph->xaxis->SetTickLabels($lbl);
	
	// Label align for X-axis
	// (horizontal alignment, vertical alignment, paragraph alignment)
	$graph->xaxis->SetLabelAlign('center','top','center');
	
	// Interval to show X-axis labels
	//$graph->xaxis->SetTextLabelInterval(5);
	
	// angle of X-axis labels
	$graph->xaxis->SetLabelAngle(90);
	
	// Label align for Y-axis
	// (horizontal alignment, vertical alignment, paragraph alignment)
	$graph->yaxis->SetLabelAlign('right','center','left');
	
	// fill in the grid
	$graph->ygrid->SetFill(true, '#FFFFFF', '#eeeeee');
	
	// Titles
	$graph->title->Set($title);
	
	if ($use_bar_plot) {
	
		// Create a bar pot
		$bplot = new BarPlot($datay);
		//$bplot->SetFillColor('#553311');
		$bplot->SetFillColor('#'.get_option('cy_graph_color_bottom').'@'.get_option('cy_graph_transparency'));
		$bplot->SetWidth(0.3);
		$bplot->value->Show(); // show value for each bar
		//$bplot->SetShadow('#666666', 1, 1);
		//$bplot->value->SetFormat('%d'); // Show integer values - no decimal point
		
		// inset the x-scale so the first and last points don't fall on the edges
		$bplot->SetCenter();
		
	}
	else if ($use_line_plot) {
	
		// Create a linear plot
		$lineplot = new LinePlot($datay);
		
		// color of the line
		$lineplot->SetColor("#666666");
		
		// show values at data points
		//$lineplot->value->show();
		
		// inset the x-scale so the first and last points don't fall on the edges
		$lineplot->SetCenter();
		
		// show plot marks
		// MARK_[SQUARE, UTRIANGLE, DTRIANGLE, DIAMOND, CIRCLE, FILLEDCIRCLE, CROSS, STAR, X] also IMAGE and FLAG are available
		//$lineplot->mark->SetType(MARK_FILLEDCIRCLE);
		
		// fill in underneath the graph
		//$lineplot->SetFillColor('#553311');
		//$lineplot->SetFillGradient('#cdb49b', '#553311');
		$lineplot->SetFillGradient('#'.get_option('cy_graph_color_top').'@'.get_option('cy_graph_transparency'), '#'.get_option('cy_graph_color_bottom').'@'.get_option('cy_graph_transparency'));
	
	}
	
	// add a 5% grace (space) to the y axis
	$graph->yaxis->scale->SetGrace(5);
	
	// set background
	$graph->SetColor('#FFFFFF');
	$graph->SetMarginColor('#FFFFFF');
	
	// no frame
	$graph->SetFrame(false);
	
	// Add the graph
	if ($use_bar_plot) {
		
		$graph->Add($bplot);
		
	} else if ($use_line_plot) {
		
		$graph->Add($lineplot);
	
	}
	
	$graph->Stroke($cy_graph_dir_full.'/'.$name);

}

/**
 * The cyclopress widget registration
 */
function cyclopress_widget_register() {

	if (function_exists('register_sidebar_widget')) :

		/**
		 * The CycloPress Widget
		 */
		function cyclopress_widget($args) {
		
			extract($args);
			$options = get_option('widget_cyclopress');
			?>
				<?php echo $before_widget; ?>
					<?php echo $before_title . $options['title'] . $after_title; ?>
					<?php echo cy_get_brief_stats(); ?>
				<?php echo $after_widget; ?>
			<?php
		
		}
		
		/**
		 * The CycloPress Widget Controls
		 */
		function cyclopress_widget_control() {
		
			$options = $newoptions = get_option('widget_cyclopress');
			if ( $_POST["cyclopress-submit"] ) {
				$newoptions['title'] = strip_tags(stripslashes($_POST["cyclopress-title"]));
				if ( empty($newoptions['title']) ) $newoptions['title'] = 'CycloPress';
			}
			if ( $options != $newoptions ) {
				$options = $newoptions;
				update_option('widget_cyclopress', $options);
			}
			$title = htmlspecialchars($options['title'], ENT_QUOTES);
			?>
				<p><label for="cyclopress-title"><?php _e('Title:'); ?> <input style="width: 250px;" id="cyclopress-title" name="cyclopress-title" type="text" value="<?php echo $title; ?>" /></label></p>
				<input type="hidden" id="cyclopress-submit" name="cyclopress-submit" value="1" />
			<?php
		
		}
		
		register_sidebar_widget('CycloPress', 'cyclopress_widget');
		register_widget_control('CycloPress', 'cyclopress_widget_control');

	endif;
	
}

/**
 * Is the image cache folder writable?
 */
function cy_is_cache_writable() {

	global $cy_graph_dir_full;

	$dir = $cy_graph_dir_full;
	
	return is_writable($dir);

}

/**
 * Deletes all files in the graph cache directory
 */
function cy_empty_cache() {

	global $cy_graph_dir_full;

	// writable?
	if (!cy_is_cache_writable()) return;

	// did it open?
	if (!$dh = @opendir($cy_graph_dir_full)) return;
	
	// delete all
	while (false !== ($obj = readdir($dh))) {
		if ($obj=='.' || $obj=='..') continue;
		@unlink($cy_graph_dir_full.'/'.$obj);
	}
	
	closedir($dh);

}

/**
 * Returns true if GD works, false otherwise.
 */
function cy_check_gd() {

	$gd_works = @imagecreate(1, 1);
	
	$GDArray = gd_info ();
	$version = ereg_replace('[[:alpha:][:space:]()]+', '', $GDArray['GD Version']);
	
	if ($gd_works === false || $version < 2) {
		return false;
	} else {
		return true;
	}

}

/**
 * Returns true if PHP is version 4.3.1 or higher, false otherwise.
 */
function cy_check_php() {
	
	return (version_compare(phpversion(), '4.3.1') === 1) ? true : false;

}

/**
 * Checks the version of CycloPress in the DB versus the CycloPress plugin version.
 * The cy_install function isn't called when the plugin is 'upgraded automatically'.
 * This function should be called from any API functions.
 */
function cy_check_version($recreate_graphs=false) {

	// for now, this just needs to call cy_install
	cy_install($recreate_graphs);

}

/**
 * Returns the SQL for the main Rides table
 */
function cy_rides_sql() {

	//echo 'checking cy_rides_sql';

	global $wpdb;
	
	$table_name = $wpdb->prefix . "cy_rides";

	$sql  = 'CREATE TABLE '.$table_name.' (
				id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT  PRIMARY KEY,
				startdate DATETIME NOT NULL ,
				miles DOUBLE(6,2) NOT NULL ,
				avg_speed DOUBLE(4,2) NULL ,
				max_speed DOUBLE(4,2) NULL ,
				minutes INT(4) UNSIGNED NOT NULL ,
				cadence DOUBLE(5,2) NULL ,
				avg_hr DOUBLE(5,2) NULL ,
				max_hr DOUBLE(5,2) NULL ,
				avg_watts DOUBLE(5,2) NULL ,
				max_watts DOUBLE(5,2) NULL ,
				elevation_gain DOUBLE(7,2) NULL ,
				elevation_loss DOUBLE(7,2) NULL ,
				max_elevation DOUBLE(7,2) NULL ,
				min_elevation DOUBLE(7,2) NULL ,
				weight DOUBLE(5,2) NULL ,
				est_calories INT(5) NULL ,
				bike_id INT(10) UNSIGNED NULL ,
				type_id INT(10) UNSIGNED NULL ,
				notes TEXT NULL
			);';
	
	return $sql;

}

/**
 * Returns the SQL for the main Bikes table
 */
function cy_bikes_sql() {

	global $wpdb;
	
	$table_name = $wpdb->prefix . "cy_bikes";

	$sql  = 'CREATE TABLE '.$table_name.' (
				id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT  PRIMARY KEY,
				label VARCHAR(255) NOT NULL ,
				make VARCHAR(255) NULL ,
				model VARCHAR(255) NULL ,
				year INT(4) UNSIGNED NULL ,
				default INT(1) UNSIGNED NOT NULL ,
				notes TEXT NULL
			);';
	
	return $sql;

}
	
/**
 * Returns the SQL for the main Bikes table
 */
function cy_types_sql() {

	global $wpdb;
	
	$table_name = $wpdb->prefix . "cy_types";

	$sql  = "CREATE TABLE ".$table_name." (
				id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT  PRIMARY KEY ,
				type ENUM('bike', 'ride') NOT NULL,
				label VARCHAR(255) NOT NULL,
				description TEXT NULL
			);";
	
	return $sql;

}

/**
 * Install this plugin
 */
function cy_install($recreate_graphs=false) {

	global $wpdb;
	global $cy_db_version, $cy_version;

	$installed_db_ver = get_option("cy_db_version");
	$installed_ver = get_option("cy_version");
	
	$rides_table = $wpdb->prefix . 'cy_rides';

	// get defaul options
	$opts = cy_get_default_options();

	// Upgrade or install
	$changed = false;
	if ( $installed_db_ver != $cy_db_version || $installed_ver != $cy_version ) {
	
		// upgrade
		//echo 'upgrading cyclopress<br />';
	
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		// cy_rides
		dbDelta(cy_rides_sql());
		// cy_bikes
		dbDelta(cy_bikes_sql());
		// cy_types
		dbDelta(cy_types_sql());
	
		// update only CY options, leave user options alone
		update_option("cy_version", $cy_version);
		update_option("cy_db_version", $cy_db_version);
		
		// add any new options, leaving old values untouched
		$opts = cy_get_default_options();
		foreach($opts as $k=>$v) {
			if (get_option($k) === FALSE) {
				add_option($k, $v);
			}
		}
	
		// set this flag to true so the graphs will be recreated
		$changed = true;
	
	} else if ( $wpdb->get_var("show tables like '$rides_table'") != $rides_table ) {
	
		// install
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		// cy_rides
		dbDelta(cy_rides_sql());
		// cy_bikes
		dbDelta(cy_bikes_sql());
		// cy_types
		dbDelta(cy_types_sql());
		
		// add all options
		$opts = cy_get_default_options();
		foreach($opts as $k=>$v) {
			add_option($k, $v);
		}
		
		// set this flag to true so the graphs will be recreated
		$changed = true;
	
	}
	
	// create all graphs
	if ( (cy_is_cache_writable() && $changed) || $recreate_graphs) {
		cy_empty_cache();
		cy_create_all_graphs();
	}

}

/**
 * Exports all data to an XML or CSV format.
 */
function cy_export($format) {

	global $wpdb, $cy_db_version, $cy_version;

	// default to CSV
	if ($format == '') $format = 'csv';

	$tables = array(
		'cy_rides',
		'cy_bikes',
		'cy_types'
	);
	
	if ($format == 'csv') {
	
		$csv = '';
	
		foreach ($tables as $table) {
			
			$csv .= "\n";
			
			$table_name = $wpdb->prefix . $table;
			
			// get the rows
			$sql  = 'select * from '.$table_name;
			$result = $wpdb->get_results($sql, ARRAY_A);
			if (!$result) {
				continue;
			}
			
			// add all rows here
			$i = 0;
			foreach ($result as $row) {
				
				// add the column labels
				if ($i == 0) {
					$j = 0;
					foreach ($row as $key=>$val) {
						$csv .= '"'.$key.'"';
						if ($j < sizeof($row)-1) { $csv .= ','; } else { $csv .= "\n"; }
						$j++;
					}
				}
				
				// add the column values
				$j = 0;
				foreach ($row as $key=>$val) {
					$csv .= '"'.$val.'"';
					if ($j < sizeof($row)-1) { $csv .= ','; } else { $csv .= "\n"; }
					$j++;
				}
				
				$i++;
				
			}
						
		}
		
		return $csv;
	
	} else {
	
		$xml = '<cyclopress exportdate="'.date('r').'">';
		$xml .= "\n\t<options>";
			$opts = cy_get_default_options();
			foreach ($opts as $key=>$val) {
				$xml .= "\n\t\t<".$key.">".get_option($key)."</".$key.">";
			}
		$xml .= "\n\t</options>";
		
		foreach ($tables as $table) {
			
			$table_name = $wpdb->prefix . $table;
			
			// open this table's tag
			$xml .= "\n\t<".$table.'>';
			
			// get the rows
			$sql  = 'select * from '.$table_name;
			$result = $wpdb->get_results($sql, ARRAY_A);
			if (!$result) {
				$xml .= "\n\t</".$table.'>';
				continue;
			}
			
			// add all rows here
			foreach ($result as $row) {
				$xml .= "\n\t\t<row>";
				foreach ($row as $key=>$val) {
					$xml .= "\n\t\t\t<".$key.'>'.$val.'</'.$key.'>';
				}
				$xml .= "\n\t\t</row>";
			}
			
			$xml .= "\n\t</".$table.'>';
			
		}
		
		$xml .= "\n</cyclopress>";
	
		return $xml;
		
	}

}

/**
 * Uninstall this plugin
 */
function cy_uninstall() {

	// does this need to do anything?
	// I could clean up DB and options, but what if the user's just upgrading?

}

/**
 * Returns the default cycling options array
 */
function cy_get_default_options() {

	global $cy_version, $cy_db_version;

	$options = array(
		// cyclopress
		'cy_version' => $cy_version,
		'cy_db_version' =>  $cy_db_version,
		// graphs
		'cy_graph_type' => 'line',
		'cy_graph_width' => '400',
		'cy_graph_height' => '300',
		'cy_graph_color_top' => 'cccccc',
		'cy_graph_color_bottom' => '777777',
		'cy_graph_transparency' => '0.7',
		// page options
		'cy_page_id' => '',
		'cy_page_name' => 'Cycling Stats',
		'cy_page_status' => 'draft',
		// stat options
		'cy_unit' => 'mile',
		'cy_show_summary' => '1',
		'cy_show_detailed_stats' => '1',
		'cy_show_distance_graph' => '1',
		'cy_show_avg_speed_graph' => '1',
		// personal stats
		'cy_birthday' => '',
		'cy_gender' => '',
	);
	
	return $options;

}

// install on activation
register_activation_hook(__FILE__,'cy_install');

// uninstall on deactivation
register_deactivation_hook(__FILE__, 'cy_uninstall');

// add actions and filters
add_action('wp_head', 'cy_css');
add_action('admin_head', 'cy_admin_css');
add_action('admin_menu', 'cy_admin_menu');

// initialize widget
add_action('init', 'cyclopress_widget_register');

?>
