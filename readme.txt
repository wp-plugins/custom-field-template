=== Custom Field Template ===
Contributors: Hiroaki Miyashita
Donate link: http://wordpressgogo.com/development/custom-field-template.html
Tags: custom, fields, field, template, meta, custom field, custom fields, custom field template
Requires at least: 2.1
Tested up to: 2.7
Stable tag: 0.7.3

This plugin adds the default custom fields on the Write Post/Page.

== Description ==

The Custom Field Template plugin adds the default custom fields on the Write Post/Page. The template format is almost same as the one of the rc:custom_field_gui plugin. The difference is following.

* You can set any number of the custom field templates and switch the templates when you write/edit the post/page.
* This plugin does not use the ini file for the template but set it in the option page of the plugin.
* Support for TinyMCE in the textarea.
* Support for media buttons in the textarea. - requires at least 2.5.
* Support for multiple fields with the same key.
* Support for hideKey and label options.
* You can see the full option list in the setting page of the plugin.
* You can customize the design of custom field template with css.
* You can replace custom keys by labels.
* You can use wpautop function.
* You can use PHP codes in order to set values. (experimental, `code = 0`)
* You can set an access user level in each field. (`level = 1`)
* Supprt for inserting custom field values into tags automatically. (`insertTag = true`)
* Adds [cft] Shortcode to display the custom field template. (only shows the attributes which have `output = true`)
* Adds template instruction sections.

Localization

* Spanish (es_ES) - Dario Ferrer

If you have translated into your language, please let me know.

== Installation ==

1. Copy the `custom-field-template` directory into your `wp-content/plugins` directory
2. Activate the plugin through the `Plugins` menu in WordPress
3. Edit the options in `Settings` > `Custom Field Template`
4. That's it! :)

== Known Issues / Bugs ==

== Frequently Asked Questions ==

= How can I use this plugin? =

The template format is basically same as the one of the rc:custom_field_gui plugin.
See the default template and modify it.

== Screenshots ==

1. Custom Field Template - Settings
2. Custom Field Template

== Uninstall ==

1. Deactivate the plugin
2. That's it! :)
