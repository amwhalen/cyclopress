=== Plugin Name ===
Contributors: anukit
Tags: cycling, graphs, sports, exercise, jpgraph, gd
Requires at least: 2.5
Tested up to: 2.7
Stable tag: 1.3.6

Track cycling stats from your bike's cyclocomputer and make pretty graphs.

== Description ==

CycloPress allows you to track your cycling statistics from a cyclocomputer with WordPress.
You can even create a page in your blog to show your cycling stats and graphs.

The GD library must be installed on your web server. After you activate CycloPress
you can see what version of GD (if any) you have installed by checking the CycloPress Debug page.

== Installation ==

= Requirements =

* WordPress version 2.5 or higher. CycloPress may work with older versions, but it is not suggested.
* PHP version 4.3.1 or higher.
* GD Library.

= Installation =

1. Upload the `cyclopress` folder to the `/wp-content/plugins/` directory.
1. Make the `/wp-content/plugins/cyclopress/graphs/` directory writable by your web server. (e.g. chmod 775)
1. Activate the plugin through the `Plugins` menu in WordPress.
1. Add your stats through the `Write -> Ride` interface.
1. Create your cycling page in the CycloPress admin to see your stats on your blog.

Find [more instructions and discussion](http://amwhalen.com/blog/projects/cyclopress/) on the CycloPress home page at amwhalen.com.

== Screenshots ==

1. Manage your stats with CycloPress.
2. The 'Add a Ride' interface found under `Write -> Ride`.
3. A graph of distance over time.
4. A graph of average speed over time.
5. Example output of `cy_get_summary()`.
6. CycloPress is widget-compatible. It shows your total miles and average speed.
7. The 'CycloPress Options' page found under `Plugins -> CycloPress Options`.
