=== Plugin Name ===
Contributors: anukit
Tags: cycling, graphs, sports, exercise, jpgraph, gd
Requires at least: 2.5
Tested up to: 2.6
Stable tag: 1.3.0

Track cycling stats from your bike's cyclocomputer and make pretty graphs.

== Description ==

Take the data from your cyclocomputer after each ride and plug it into CycloPress under the Write menu.
CycloPress will update your graphs and text statistics accordingly.

CycloPress requires that the GD library is installed on your web server. After you activate CycloPress
you can see what version of GD (if any) you have installed by checking the CycloPress Options page under Plugins.

Graphs are cached after each update made to CycloPress. Graphs are recreated when you update the CycloPress
Options, or when you add new data.

== Installation ==

= Requirements =

* WordPress version 2.5 or higher. CycloPress may work with older versions, but it is not suggested.
* PHP version 4.3.1 or higher.
* GD Library version 1 or 2.

= Installation =

1. Upload the `cyclopress` folder to the `/wp-content/plugins/` directory.
1. Make the `/wp-content/plugins/cyclopress/graphs/` directory writable by your web server. (e.g. chmod 775)
1. Activate the plugin through the `Plugins` menu in WordPress.
1. Add your stats through the `Write -> Ride` interface.
1. Place some CycloPress PHP functions in your templates or in your PHP-enabled pages or posts to see your stats.

Find [more instructions and discussion](http://amwhalen.com/blog/projects/cyclopress/) on the CycloPress home page.

== To Do ==

* Automatic generation of a 'Cycling' page when stats are added. No template modifications required.

== CycloPress Functions ==

After installing CycloPress, see the CycloPress Options page in Plugins for more information.

Here are the functions you can insert into your templates or into Pages if you have a plugin that allows PHP in pages and posts.

`cy_get_brief_stats()`
Returns XHTML code with total miles traveled and average speed. Returns `false` if no data is available.

`cy_get_summary( [ bool $compare, string $year ] )`
Returns XHTML code with a summary of your stats. Returns `false` if no data is available.

`cy_get_first_ride_date()`
Returns the date of the least recent ride in the database.

`cy_get_last_ride_date()`
Returns the date of the most recent ride in the database.

`cy_get_graph_img_tag( $type )`
The parameter `type` cane take either `'distance'` or `'average_speed'`.

== Screenshots ==

1. The 'Add a Ride' interface found under `Write -> Ride`.
2. The 'CycloPress Options' page found under `Plugins -> CycloPress Options`.
3. Example output of `cy_get_summary()` and a distance graph.
