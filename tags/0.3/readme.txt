=== Lightbox Gallery ===
Contributors: Hiroaki Miyashita
Donate link: http://wordpressgogo.com/development/lightbox-gallery.html
Tags: lightbox, gallery, image, images, album, photo
Requires at least: 2.5
Tested up to: 2.6.2
Stable tag: 0.3

This plugin changes the view of galleries to the lightbox.

== Description ==

The Lightbox Gallery plugin changes the view of galleries to the lightbox.

* Lightbox display of Gallery
* Tooltip view of caption of images 
* Displays the associated metadata with images

You can also make regular images appear in a lightbox. See Faq.

== Installation ==

1. Edit the `lightbox-gallery.js` and check the path of line 2 according to your settings.
2. Copy the `lightbox-gallery` directory into your `wp-content/plugins` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. That's it! :)

Lightbox Gallery will load 'lightbox-gallery.css' from your theme's directory if it exists. 
If it doesn't exists, it will just load the default 'lightbox-gallery.css' that comes with Lightbox Gallery. 
This will allow you to upgrade Lightbox Gallery without worrying about overwriting your lightbox gallery styles that you have created. 

== Known Issues / Bugs ==

== Frequently Asked Questions ==
* How can I make regular images appear in a lightbox without [gallery] shortcode?

Just add rel="lightbox" into "a" tag. Here is a sample.

&lt;a href="image.jpg" rel="lightbox" title="this is a caption"&gt;<br />
&lt;img src="thumbnail.jpg" alt="" /&gt;<br />
&lt;/a&gt;

* How can I handle multiple galleries in one page as separate ones?

You need to do two steps. If you would like to handle galleries separately, 
add different class names into [gallery] and add codes as many as class names 
into `lightbox-gallery.js`.
 

1. Edit `lightbox-gallery.js` and add some codes. Look at the comment of the file.
2. Add a class into [gallery]. ex) [gallery class="gallery2"]

== Screenshots ==

1. Lightbox Gallery

== How to use ==
How to use this plugin is basically the same as the way to add [gallery] which has been adopted 
by over WordPress 2.5. Lightbox Gallery plugin automatically converted the default view of gallery 
into the lightbox view. Photo captions are displayed as tooltips. Photo descriptions are displayed 
when the lightbox pops up.

== Advanced settings ==
There are three additional options to extend the shorttag [gallery].

* lightboxsize

The image size when the lightbox pops up. The default is medium, but you can change to full.

[gallery lightboxsize="full"] 

* meta

Defines whether the exif information is displayed. The default is false.
If you want to show the photo info, set true. The exif shown on the lightbox includes camera body, 
aperture, focal length, shutter speed, and created timestamp.

[gallery meta="true"]

* class

Adds a class attribute of the gallery. The default is 'gallery1'.

[gallery class="gallery2"]

* nofollow

Adds the attribute, rel="nofollow". The default is false.

[gallery nofollow="true"]

== Uninstall ==

1. Deactivate the plugin
2. That's it! :)
