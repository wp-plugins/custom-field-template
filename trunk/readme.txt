=== Custom Field Template ===
Contributors: Hiroaki Miyashita
Donate link: http://wordpressgogo.com/development/custom-field-template.html
Tags: custom, fields, field, template, meta, custom field, custom fields, custom field template
Requires at least: 2.1
Tested up to: 2.8.2
Stable tag: 1.3.7

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
* Adds the value label option for the case that values are diffrent from viewed values. (`valueLabel = apples # oranges # bananas`)
* Adds the blank option. (`blank = true`)
* Adds the break type. Set CSS of '#cft div'. (`type = break`)
* Adds [cft] Shortcode Format.
* Adds the sort option. (`sort = asc`, `sort = desc`, `sort = order`)
* Support for Quick Edit of custom fields. (tinyMCE and mediaButton are not supported yet)
* Support for the custom field search. (only shows the attributes which have `search = true`.)
* Adds [cftsearch] Shortcode Format. (under development)
* Adds PHP codes for the output value. (`outputCode = 0`)
* Adds PHP codes before saving the values. (`editCode = 0`)
* Adds the save functionality.
* Adds the class option. (`class = text`)
* Adds the auto hook of `the_content()`. (experimental)
* You can use the HTML Editor in the textarea. (`htmlEditor = true`)
* Adds the box title replacement option.
* Adds the select option of the post type.
* Adds the value count option.
* Adds the option to use the shortcode in the widhet.
* Adds the attributes of JavaScript Event Handlers. (`onclick = alert('ok');`)
* Adds the Initialize button.
* Adds the attributes of before and after text. (`before = blah`, `after = blah`)
* Adds the export and import functionality.
* Adds the style attribute. (`style = color:#FF0000;`)
* Adds the maxlength attribute. (`maxlength = 10`)
* Adds the attributes of multiple fields. (`multiple = true`, `startNum = 5`, `endNum = 10`, `multipleButton = true`)
* Adds the attributes of the date picker in `text` type. (`date = true`, `dateFirstDayOfWeek = 0`, `dateFormat = yyyy/mm/dd`)
* Adds the filter of page template file names (Thanks, Joel Pittet).
* Adds the attribute of `shortCode` in order to output the shortcode filtered values. (`shortCode = true`)
* Adds the attribute of `outputNone` in case there is no data to output. (`outputNone = No Data`)
* Adds the attribute of `singleList` attribute in order to output with `<ul><li>` if the value is single. ex) `singleList = true`

Localization

* Belorussian (by_BY) - [Marcis Gasuns](http://www.fatcow.com/)
* German (de_DE) - F J Kaiser
* Spanish (es_ES) - [Dario Ferrer](http://www.darioferrer.com/)
* French (fr_FR) - Nicolas Lemoine
* Italian (it_IT) - [Gianni Diurno](http://gidibao.net/)
* Japanese (ja) - [Hiroaki Miyashita](http://wordpressgogo.com/)
* Russian (ru_RU) - [Sonika](http://www.sonika.ru/blog/)
* Turkish (tr_TR) - [Omer Faruk](http://ramerta.com/)

If you have translated into your language, please let me know.

== Installation ==

1. Copy the `custom-field-template` directory into your `wp-content/plugins` directory
2. Activate the plugin through the `Plugins` menu in WordPress
3. Edit the options in `Settings` > `Custom Field Template`
4. That's it! :)

== Frequently Asked Questions ==

= How can I use this plugin? =

The template format is basically same as the one of the rc:custom_field_gui plugin.
See the default template and modify it.

= How can I display the custom fields? =

1. Use the cft shortcode. In the edit post, write down just `[cft]`. If you would like to specify the post ID, `[cft post_id=15]`. You can also set the template ID like `[cft template=1]`.
2. Do you want to insert a particular key value? Use `[cft key=Key_Name]`.
3. If you set the format of the custom fields, use `[cft format=0]`.
4. Auto Hook of `the_content()` in the option page of this plugin may help you do this. You can use [cft] shortcodes here. You can switch the cft formats in each category.

== Changelog ==

= 1.3.7 =
* Bugfix: class attribute of `text` type.
* `shortCode` attribute in order to output the shortcode filtered values. ex) `shortCode = true`
* `outputNone` attribute in case there is no data to output. ex) `outputNone = No Data`
* `singleList` attribute in order to output with `<ul><li>` if the value is single. ex) `singleList = true`
* Option not to display the custom field column on the edit post list page.

= 1.3.6 =
* Changelog.

= 1.3.3 =
* Exerpt Shortcode option.

= 1.3 =
* Attributes of the date picker in `text` type. ex) `date = true`, `dateFirstDayOfWeek = 0`, `dateFormat = yyyy/mm/dd`
* Filter of page template file names (Thanks, Joel Pittet).

= 1.2.7 =
* Post ID options.

= 1.2.5 =
* French and Belorussian.

= 1.2 =
* Attributes of multiple fields. ex) `multiple = true`, `startNum = 5`, `endNum = 10`, `multipleButton = true`

= 1.1.7 =
* Maxlength attribute. ex) `maxlength = 10`

= 1.1.5 =
* Style attribute.

= 1.1.3 =   
* Attributes of before and after text. ex) `before = blah`, `after = blah`
* Export and import functionality.

= 1.1.1 =   
* Initialize button.
* Auto hook inside the content. ex) `[cfthook hook=0]`

= 1.1 =   
* Attributes of JavaScript Event Handlers. (`onclick = alert('ok');`) Event Handlers: onclick, ondblclick, onkeydown, onkeypress, onkeyup, onmousedown, onmouseup, onmouseover, onmouseout, onmousemove, onfocus, onblur, onchange, onselect 

= 1.0.8 =   
* Option to use the shortcode in the widhet.

= 1.0.7 =   
* Select option of the post type.
* Value count option.

= 1.0.5 =   
* Box title replacement option.

= 1.0.4 =   
* Option to disable the quick edit.
* Attribute of HTML Editor in the textarea. ex) `htmlEditor = true`
* Italian (it_IT) - Gianni Diurno

= 1.0.3 =   
* Option to disable the default custom fields.

= 1.0=
* Custom field search. (only shows the attributes which have `search = true`.)
* [cftsearch] Shortcode Format.
* PHP codes for the output value. ex) `outputCode = 0`
* PHP codes before saving the values. ex) `editCode = 0`
* Save functionality.
* Class option. ex) `class = text`
* Auto hook of `the_content()`.
* German (de_DE) - F J Kaiser
* Turkish (tr_TR) - Omer Faruk

= 0.9 =
* Sort option. ex) `sort = asc` or `sort = desc`
* Quick Edit of custom fields.

= 0.8 =
* The value label option for the case that values are diffrent from viewed values. (`valueLabel = apples # oranges # bananas`).
* Blank option. ex) `blank = true`
* Break type. Set CSS of '#cft div'. ex) `type = break` | #cft div { width:50%; float:left; }
* [cft] Shortcode Format.
* Russian (ru_RU) - Sonika

= 0.7.3 =
* Spanish (es_ES) - Dario Ferrer.

= 0.7.2 =
* PHP codes for `checkbox`.

= 0.7.1 =
* Template Instruction.

= 0.7 =
* Inserting custom field values into tags automatically. ex) `insertTag = true`
* [cft] Shortcode to display the custom field template. (only shows the attributes which have `output = true`).

= 0.6.5 =
* User level in each field. ex) `level = 2`

= 0.6.4 =
* PHP codes in order to set values of `radio` and `select` types. ex) `code = 0`

= 0.6 =
* `type = text`, which is same as `type = textfield`.
* Option to replace custom keys by labels

= 0.5 =
* Full option list.
* `clearButton = true` in radios.
* Keeps tinyMCE height after resizing the textarea and saving the post.

= 0.4.4 =
* Multiple checkboxes.

= 0.4 =
* Multiple fields with the same key.
* hideKey options. ex) `hideKey = true`
* The default of media buttons is false. ex) `mediaButton = true`

= 0.3.1 =
* Media buttons in the textarea.

= 0.2 =
* TinyMCE in the textarea.

= 0.1 =
* Initial release.

== Screenshots ==

1. Custom Field Template - Settings
2. Custom Field Template

== Known Issues / Bugs ==

== Uninstall ==

1. Deactivate the plugin
2. That's it! :)
