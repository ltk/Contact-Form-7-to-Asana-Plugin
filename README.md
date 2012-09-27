=== Contact Form 7 to Asana Extension ===
Contributors: ltkurtz
Tags: contact form,Asana,contact form Asana, Asana api
Requires at least: 3.2.1
License: GNU General Public License, Version 3
Tested up to: 3.4.1
Stable tag: trunk

Saves submitted form data as a new task within Asana. Captures data from Contact Form 7

== Attribution ==

This plugin is heavily modeled off of Michael Simpson's excellent "Contact Form 7 to Database Extension" plugin <http://wordpress.org/extend/plugins/contact-form-7-to-database-extension/>.

== Description ==

This "CF-Asana" plugin saves contact form submissions as a new task within Asana.

Once you've installed the plugin, and provided your Asana API key it will automatically begin to capture submissions from the Contact Form 7 (CF7) plugin.

Email is nice, but Asana is better. Take control of your most important action items by automatically adding your site's form submissions into Asana as new task. 

Finding Admin Page

* If you have CF7 installed and activated, look under the "Contact" menu in the Admin area.

Enter Your Asana API Key

* In order to function, this plugin requires your Asana API key in order to create tasks on your behalf. You may find you Asana API key within Asana by clicking your name in the bottom left of the main Asana screen, selecting "Account Settings", and clicking to the "API tab".

Disclaimer: I have no involvement with the development or maintenance of the Contact Form 7 plugin.

== Installation ==

1. Your WordPress site must be running PHP5 or better. This plugin will fail to activate if your site is running PHP4.
1. Be sure that Contact Form 7 is installed and activated. (This plugin is an extension of Contact Form 7 and will not function in its absence.)

Notes:

* This plugin stores all preferences in the WordPress Options table. No additional tables are created by this plugin.
* Tested using PHP 5.3.13


== Screenshots ==

1. Admin view of Asana setup
2. Form submission automatically creates a new task in Asana
