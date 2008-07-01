=== Plugin Name ===
Contributors: awhalen
Tags: cycling, graphs, sports, exercise
Requires at least: 2.5
Tested up to: 2.5.1
Stable tag: 1.0

Track cycling stats from your bike's cyclocomputer and make pretty graphs.

== Description ==

CycloPress requires the GD library to be available from your PHP installation. After you activate CycloPress
you can see what version of GD, if any, you have installed by checking the CycloPress Options page under Plugins.

Take the data from your cyclocomputer after each ride and plug it into CycloPress under the Write menu.
CycloPress will update your graphs and text statistics accordingly.

Graphs are cached after each update made to CycloPress. Graphs are recreated when you update the CycloPress
Options, or when you add new data.

== Installation ==

1. Upload the CycloPress folder to the `/wp-content/plugins/` directory.
1. Make the `/wp-content/plugins/cyclopress/graphs/` directory writable by your web server. (e.g. chmod 775)
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Add your stats through the `Write -> Ride` interface.
1. Place some CycloPress PHP functions in your templates or in your PHP-enabled pages or posts.

== Frequently Asked Questions ==

No one has asked any yet!

== CycloPress Functions ==

After installing CycloPress, see the CycloPress Options page in Plugins for more information.

Here are the functions you can insert into your templates or into Pages if you have a plugin that allows PHP in pages and posts.

<pre>string cy_get_brief_stats ( )</pre>
Returns XHTML code with total miles traveled and average speed. Returns false if no data is available.

<pre>string cy_get_summary ( [ bool $compare, string $year ] )</pre>
Returns XHTML code with a summary of your stats. Returns false if no data is available.

<pre>string cy_get_first_ride_date ( )</pre>
Returns the date of the least recent ride in the database.

<pre>string cy_get_last_ride_date ( )</pre>
Returns the date of the most recent ride in the database.

<pre>string cy_get_graph_img_tag ( string $type )</pre>
The parameter 'type' cane take either 'distance' or 'average_speed'.

== Screenshots ==

Check back here later.
