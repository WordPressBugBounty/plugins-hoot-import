=== Hoot Import ===
Contributors: wphoot
Tags: wphoot, hoot, demo content, demos, import
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.8
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html

Hoot Import lets you import demo content for WordPress themes by wpHoot.

== Description ==

Hoot Import lets you import the demo data for <a href="https://wphoot.com/" target="_blank" rel="nofollow">wpHoot Themes</a> to help you get familiar with the theme. Import demo content, widgets and settings with just one click to make your site look like the demo site.

== Notes ==

* The plugin makes a call to our CDN server remotely to import static demo content files.

== Requirements ==

* This plugin requires <a href="https://wordpress.org/themes/author/wphoot/" target="_blank" rel="nofollow">Official wpHoot Themes</a>

== Installation ==

1. In your wp-admin (WordPress dashboard), go to Plugins Menu > Add New
2. Search for 'Hoot Import' in search field on top right.
3. In the search results, click on 'Install Now' button next to 'Hoot Import' result.
4. Once the installation is complete, click Activate button.

You can also install the plugin manually by following these steps:
1. Download the plugin zip file from https://wordpress.org/plugins/hoot-import/
2. In your wp-admin (WordPress dashboard), go to Plugins Menu > Add New
3. Click the 'Upload Plugin' button at the top.
4. Upload the zip file you downloaded in Step 1.
5. Once the upload is finish, click on Activate.

== Frequently Asked Questions ==

= What is the plugin license? =

This plugin is released under a GPL license.

= Which themes does Hoot Import work with? =

The plugin works only with wpHoot Themes.

== Changelog ==

= 1.8 =
* v1.7.1 : Fix PHP error: Undefined variable $hybridmenuicon in /include/importers/class-wxr-importer.php Ticket#12299
* Support 'blogposts' in json to set 'posts_per_page'
* Improved menu duplication if plugin runs more than once (no deletion of existing menu items / nav menus)
* Revert support for Lume/Booster => Add 'incompatible' support for newer themes instead
* Support for 'required' plugins
* Update to cdn v5 files + update cdn image folder
* Option to import HootKit Settings if available in import's json
* Minor css fixes

= 1.7 =
* Fix "Function _load_textdomain_just_in_time was called incorrectly" warning
* Add support for "posts_per_page" from JSON files
* Support for Magazine Lume and Magazine Booster

= 1.6 =
* Support New Themes

= 1.5 =
* New v4b Format of files (slug-ext.txt)
* Changed dat files to txt to avoid server security restrictions in certain cases Ticket#12159 (error: [hootimport_customizer_file_error] The customizer import file is not readable.)

= 1.4 =
* Update cdn url

= 1.3 =
* Allow themes to modify custom menu urls

= 1.2 =
* Split xml import for WooCommerce (optional)
* Setup WC pages during finalization
* Removed 'batch' ids
* Make final step actions conditional on what was imported
* Add support for slider cpt in themes which support it
* Map IDs (page, category etc) in widgets
* Fetch files as a separate 'prepare' step

= 1.1 =
* Initial Public Release