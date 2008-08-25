<?PHP
/*
Plugin Name: CycloPress
Plugin URI: http://amwhalen.com/blog/projects/cyclopress/
Description: Keep track of your cycling statistics with WordPress and make pretty graphs.
Version: 1.2.7
Author: Andrew M. Whalen
Author URI: http://amwhalen.com
*/

/*  Copyright 2008  Andrew M. Whalen  (email : cyclopress@amwhalen.com)

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


$cy_version = '1.2.7';
$cy_db_version = '1.0';
$cy_graph_dir = 'graphs';
$cy_graph_dir_full = dirname(__FILE__).'/'.$cy_graph_dir;
$cy_dir = get_bloginfo('url').'/wp-content/plugins/'.basename(dirname(__FILE__));
$cy_src_dir = $cy_dir.'/'.$cy_graph_dir;


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
 * Add links to the cycling admin pages where appropriate.
 */
function cy_admin_menu() {

	// options for cycling
	//add_options_page('Cycling Options', 'Cycling', 'edit_files', __FILE__, 'cy_options_page');
	add_submenu_page('plugins.php', 'CycloPress', 'CycloPress', 'edit_files', __FILE__, 'cy_options_page');
	
	// add a ride form
	add_submenu_page('post.php', 'Ride', 'Ride', 'edit_files', __FILE__, 'cy_write_page');
	
	// add a ride editing page
	add_submenu_page('edit.php', 'Rides', 'Rides', 'edit_files', __FILE__, 'cy_manage_page');

}

/**
 * The cycling options page.
 */
function cy_options_page() {

	global $cy_graph_dir_full, $cy_dir, $cy_version;

	// update the graphs if settings were changed
	if ($_GET['updated']) {
	
		cy_create_all_graphs();
	
	}

	?>
	<div class="wrap">
	
	<h2>CycloPress Options</h2>
	
	<table class="cy_status_table">
		<caption>Requirements Status</caption>
		<tr>
			<?PHP if (!cy_check_php()) { ?>
			<th class="cy_error"><img src="<?php echo $cy_dir; ?>/img/error.gif" alt="Error!" /></th>
			<td class="cy_error">PHP version <?php echo phpversion(); ?> is not supported! PHP version 4.3.1 or higher is required.</td>
			<?PHP } else { ?>
			<th class="cy_ok"><img src="<?php echo $cy_dir; ?>/img/ok.gif" alt="OK" /></th>
			<td class="cy_ok">PHP version <?php echo phpversion(); ?> installed.</td>
			<?PHP } ?>
		</tr>
		<tr>
			<?PHP if (!cy_check_gd()) { if (function_exists('gd_info')) { $GDArray = gd_info(); } else { $GDArray = false; } ?>
			<th class="cy_error"><img src="<?php echo $cy_dir; ?>/img/error.gif" alt="Error!" /></th>
			<td class="cy_error"><?php if (is_array($GDArray)) { echo 'GD version '.ereg_replace('[[:alpha:][:space:]()]+', '', $GDArray['GD Version']).' is not supported. GD version 2 or higher is required.'; } else { echo 'GD library is not installed!'; } ?> You cannot create graphs.</td>
			<?PHP } else { $GDArray = gd_info(); ?>
			<th class="cy_ok"><img src="<?php echo $cy_dir; ?>/img/ok.gif" alt="OK" /></th>
			<td class="cy_ok">GD version <?php echo ereg_replace('[[:alpha:][:space:]()]+', '', $GDArray['GD Version']); ?> installed.</td>
			<?PHP } ?>
		</tr>
		<tr>
			<?PHP if (!cy_is_cache_writable()) { ?>
			<th class="cy_warning"><img src="<?php echo $cy_dir; ?>/img/warning.gif" alt="Warning!" /></th>
			<td class="cy_warning">
				Graphs directory is not writable for PHP!<br />
				Please make <?php echo $cy_graph_dir_full; ?> writable.<br />
				You cannot create graphs until this directory can be written to.
			</td>
			<?PHP } else { ?>
			<th class="cy_ok"><img src="<?php echo $cy_dir; ?>/img/ok.gif" alt="OK" /></th>
			<td class="cy_ok">Graphs directory is writable.</td>
			<?PHP } ?>
		</tr>
	</table>
	
	<form method="post" action="options.php">
		
		<?php wp_nonce_field('update-options'); ?>
		
		<input type="hidden" name="action" value="update" />
		
		<input type="hidden" name="page_options" value="cy_unit,cy_graph_type,cy_graph_width,cy_graph_height,cy_graph_color_top,cy_graph_color_bottom,cy_graph_transparency" />
		
		<table class="form-table">
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
	
	<p>CycloPress version <?php echo $cy_version; ?>. See the <a href="http://amwhalen.com/blog/projects/cyclopress/">CycloPress page</a> for more information.</p>
	
	</div>
	<?PHP

}

/**
 * The "Add a Ride" page.
 */
function cy_write_page() {

	global $wpdb;

	$table_name = $wpdb->prefix . "cy_rides";
	
	// set defaults
	$mon = date('n');
	$day = date('j');
	$year = date('Y');
	$hour = '';
	$minute = '';
	$ampm = 'am';
	$miles = '';
	$minutes = '';
	$as = '';
	$ms = '';
	$cad = '';
	$notes = '';

	// processing for when the form is submitted
	if (isset($_POST['submitted'])) {
		
		// check that we got required data
		if (!$_POST['miles'] || !$_POST['minutes']) {
			
			$mon = $_POST['month'];
			$day = $_POST['day'];
			$year = $_POST['year'];
			$hour = $_POST['hour'];
			$minute = $_POST['minute'];
			$ampm = $_POST['ampm'];
			$miles = $_POST['miles'];
			$minutes = $_POST['minutes'];
			$as = $_POST['avg_speed'];
			$ms = $_POST['max_speed'];
			$cad = $_POST['cadence'];
			$notes = $_POST['notes'];
			
			$saved = false;
			$e = 'Please fill in all required fields.';
			
		} else {
		
			// set the date and time
			$date = $_POST['year'].'-'.$_POST['month'].'-'.$_POST['day'];
			if ($_POST['hour'] != '' && $_POST['minute'] != '') {
				$time = $_POST['hour'].':'.$_POST['minute'].' '.$_POST['ampm'];
			} else {
				$time = '00:00pm';
			}
			
			// no average speed given? calculate it for the lazy person
			if (!$_POST['avg_speed']) {
				$_POST['avg_speed'] = $_POST['miles'] / ($_POST['minutes']/60);
			}
			
			$sql  = 'insert into '.$table_name.'(startdate,miles,avg_speed,max_speed,minutes,cadence,notes) ';
			$sql .= "values('".$wpdb->escape($date)."','".$wpdb->escape($_POST['miles'])."','".$wpdb->escape($_POST['avg_speed'])."'";
			$sql .= ",'".$wpdb->escape($_POST['max_speed'])."','".$wpdb->escape($_POST['minutes'])."'";
			$sql .= ",'".$wpdb->escape($_POST['cadence'])."','".$wpdb->escape($_POST['notes'])."');";
	
			// send the query to the DBMS
			$result = $wpdb->query($sql);
			
			// error?
			$saved = ($result === false) ? false : true;
			
			// create the graphs
			if ($saved) {
			
				cy_create_all_graphs();
			
			}
	
		}
		
		if (!$saved) {
			?>
			<div id="message" class="updated fade"><p><?php echo $e; ?></p></div>
			<?PHP
		} else {
			?>
			<div id="message" class="updated fade"><p>Ride saved.</p></div>
			<?PHP
		}
			
	}

	?>
	<div class="wrap">
	<h2>Add a Ride</h2>
	<form name="cycling" action="" method="post" enctype="multipart/form-data">
		<input type="hidden" name="submitted" value="1" />
		<table width="100%" border="0" cellspacing="2" cellpadding="5" class="editform">
		  <tr>
			<th width="33%" scope="row" style="text-align: right;">*Date:</th>
			<td>
				<select name="month">
					<?PHP
						for ($i = 1; $i <= 12; $i++) {
							if ($i == $mon) {
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
							if ($i == $day) {
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
							if ($i == $year) {
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
							if ($i == $hour) {
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
							if ($t == $minute) {
								$sel = ' selected="selected"';
							} else {
								$sel = '';
							}
							?><option value="<?php echo $t; ?>"<?php echo $sel; ?>><?php echo $t; ?></option><?PHP
						}
					?>
				</select>
				<select name="ampm">
					<option value="am"<?php if ($ampm=='am') { echo ' selected="selected"'; } ?>>am</option>
					<option value="pm"<?php if ($ampm=='pm') { echo ' selected="selected"'; } ?>>pm</option>
				</select>
			</td>
		  </tr>
		  <tr valign="top">
			<th scope="row" style="text-align: right;">*Distance:</th>
			<td><input type="text" name="miles" id="miles" size="5" value="<?php echo htmlentities(stripslashes($miles)); ?>" /> <?php echo cy_distance_text(); ?></td>
		  </tr>
		  <tr valign="top">
			<th scope="row" style="text-align: right;">*Time:</th>
			<td><input type="text" name="minutes" id="minutes" size="5" value="<?php echo htmlentities(stripslashes($minutes)); ?>" /> minutes</td>
		  </tr>
		  <tr valign="top">
			<th scope="row" style="text-align: right;">Average Speed:</th>
			<td><input type="text" name="avg_speed" id="avg_speed" size="5" value="<?php echo htmlentities(stripslashes($as)); ?>" /> <?php echo cy_speed_text(); ?></td>
		  </tr>
		  <tr valign="top">
			<th scope="row" style="text-align: right;">Maximum Speed:</th>
			<td><input type="text" name="max_speed" id="max_speed" size="5" value="<?php echo htmlentities(stripslashes($ms)); ?>" /> <?php echo cy_speed_text(); ?></td>
		  </tr>
		  <tr valign="top">
		  	<th scope="row" style="text-align: right;">Cadence:</th>
			<td><input type="text" name="cadence" id="cadence" size="5" value="<?php echo htmlentities(stripslashes($cad)); ?>" /> rpm</td>
		  </tr>
		  <tr valign="top">
			<th scope="row" style="text-align: right;">Notes:</th>
			<td><textarea name="notes" rows="10" cols="50"><?php echo htmlentities(stripslashes($notes)); ?></textarea></td>
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
	
	$sql  = 'select * from '.$table_name.$where.' order by startdate desc';
	$rides = $wpdb->get_results($sql, ARRAY_A);
	
	?>
	
	<div class="wrap">
	
		<h2>Manage Rides</h2>
	
		<p>These statistics have been tracked since <?php echo cy_get_first_ride_date(); ?> and were last updated on <?php echo cy_get_last_ride_date(); ?>.</p>

		<?php echo cy_get_summary(true); ?>

		<table border="1">
			
			<tr>
				<th>Date</th>
				<th>Distance</th>
				<th>Average Speed</th>
				<th>Max Speed</th>
				<th>Cadence</th>
				<th>Time</th>
				<th>Notes</th>
			</tr>
			
			<?php
			
			if (sizeof($rides)) { foreach ($rides as $ride) {
			
				$hours = floor($ride['minutes']/60);
				$minutes = floor($ride['minutes']%60);
				$h_text = ($hours == 1) ? 'hour' : 'hours';
				$m_text = ($minutes == 1) ? 'minute' : 'minutes';
				
			?>
			
			<tr>
				<td><?php echo date('F jS, Y g:ia', strtotime($ride['startdate'])); ?></td>
				<td><?php echo $ride['miles'] . ' ' . cy_distance_text(); ?></td>
				<td><?php echo $ride['avg_speed'] . ' '. cy_speed_text(); ?></td>
				<td><?php echo $ride['max_speed'] . ' '. cy_speed_text(); ?></td>
				<td><?php echo $ride['cadence']; ?> rpm</td>
				<td><?php echo ($hours == 0) ? $ride['minutes'] . ' minutes' : $hours . ' '.$h_text.', ' . $minutes . ' '.$m_text; ?></td>
				<td><?php echo (strlen(trim(strip_tags($ride['notes']))) > 100) ? substr(trim(strip_tags($ride['notes'])), 0, 100) : trim(strip_tags($ride['notes'])); ?></td>
			</tr>
				
			<?php } } ?>
			
		</table>
	
	</div>
	
	<?php
	
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
		table.cy_status_table {
			border-collapse: collapse;
			width: 100%;
			border-top: 1px solid #ccc;
			margin-top: 1em;
		}
		table.cy_status_table caption {
			background: #fff;
			font-weight: bold;
			text-align: left;
			margin-top: 1em;
		}
		table.cy_status_table tr {
			border-bottom: 1px solid #ccc;
		}
		table.cy_status_table th {
			width: 50px;
		}
		table.cy_status_table th, table.cy_status_table td {
			padding: 3px;
		}
		table.cy_status_table .cy_ok {
			background: #cfc;
			color: #060;
			font-weight: bold;
		}
		table.cy_status_table .cy_warning {
			background: #fec;
			color: #000;
			font-weight: bold;
		}
		table.cy_status_table .cy_error {
			background: #fcc;
			color: #c00;
			font-weight: bold;
		}
		.cy_function {
			border: 1px solid #ccc;
			padding: 0 1em;
			margin: 0 0 1em 0;
			background: #eee;
		}
		.cy_function h4 {
			font-size: 14px;
			margin: 0 -1em 1em -1em;
			padding: 1em;
			font-family: "Courier New", Courier, monospace;
			background: #ddf;
			font-weight: normal;
		}
		.cy_function pre {
			border: 1px solid #aaa;
			background: #ccc;
			padding: .5em;
			overflow: auto;
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
	$rows = sizeof($result);
	
	// create all graphs
	if ($rows >= 1) {
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
	
	if (phpversion() >= 5) {
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
 * Returns true if PHP is version 5 or higher, false otherwise.
 */
function cy_check_php() {
	
	return (phpversion() < 4.3.1) ? false : true;

}

/**
 * Checks the version of CycloPress in the DB versus the CycloPress plugin version.
 * The cy_install function isn't called when the plugin is 'upgraded automatically'.
 * This function should be called from any API functions.
 */
function cy_check_version() {

	// for now, this just needs to call cy_install
	cy_install();

}

/**
 * Install this plugin
 */
function cy_install() {

	global $wpdb;
	global $cy_db_version, $cy_version;

	$installed_ver = get_option("cy_db_version");
	$table_name = $wpdb->prefix . "cy_rides";

	// the table
	$sql  = 'CREATE TABLE `'.$table_name.'` (';
	$sql .= '`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,';
	$sql .= '`startdate` DATETIME NOT NULL ,';
	$sql .= '`miles` DOUBLE(6,2) NOT NULL ,';
	$sql .= '`avg_speed` DOUBLE(4,2) NULL ,';
	$sql .= '`max_speed` DOUBLE(4,2) NULL ,';
	$sql .= '`minutes` INT(4) UNSIGNED NOT NULL ,';
	$sql .= '`cadence` DOUBLE(5,2) NULL ,';
	$sql .= '`notes` TEXT NULL';
	$sql .= ');';

	// get defaul options
	$opts = cy_get_default_options();

	// Upgrade or install
	if ( $installed_ver != $cy_db_version ) {
	
		// upgrade
	
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		dbDelta($sql);
	
		// update only CY options, leave user options alone
		update_option("cy_version", $cy_version);
		update_option("cy_db_version", $cy_db_version);
	
	} else if ( $wpdb->get_var("show tables like '$table_name'") != $table_name ) {
	
		// install
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		dbDelta($sql);
		
		// add all options
		$opts = cy_get_default_options();
		foreach($opts as $k=>$v) {
			add_option($k, $v);
		}
	
	}
	
	// create all graphs
	if (cy_is_cache_writable()) {
		cy_empty_cache();
		cy_create_all_graphs();
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
		'cy_version' => $cy_version,
		'cy_db_version' =>  $cy_db_version,
		'cy_graph_type' => 'line',
		'cy_graph_width' => '400',
		'cy_graph_height' => '300',
		'cy_graph_color_top' => 'cccccc',
		'cy_graph_color_bottom' => '777777',
		'cy_graph_transparency' => '0.7',
		'cy_unit' => 'mile',
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
