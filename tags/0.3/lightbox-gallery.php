<?php
/*
Plugin Name: Lightbox Gallery
Plugin URI: http://wordpressgogo.com/development/lightbox-gallery.html
Description: Changes to the lightbox view in galleries.
Author: Hiroaki Miyashita
Version: 0.3
Author URI: http://wordpressgogo.com/
*/

add_action('init', 'lightbox_gallery_textdomain');
function lightbox_gallery_textdomain() {
	load_plugin_textdomain('lightbox-gallery', 'wp-content/plugins/lightbox-gallery');
}

add_action('wp_head', 'add_lightbox_gallery_head',1);
add_action('wp_print_scripts', 'add_lightbox_gallery_jquery',1);

function add_lightbox_gallery_head() {
	global $wp_query;
	
	$flag = false;
	if($wp_query->posts) {
		for($i=0;$i<count($wp_query->posts);$i++) {
			if ( preg_match('/\[gallery([^\]]+)?\]/', $wp_query->posts[$i]->post_content) ) {
				$flag = true;
				break;
			}
		}
	}
	
	if ( !is_admin() && $flag ) {
		if (@file_exists(TEMPLATEPATH.'/lightbox-gallery.css')) {
			echo '<link rel="stylesheet" href="'.get_stylesheet_directory_uri().'/lightbox-gallery.css" type="text/css" />'."\n";	
		} else {
			echo '<link rel="stylesheet" type="text/css" href="' . get_settings('siteurl') . '/wp-content/plugins/lightbox-gallery/lightbox-gallery.css" />'."\n";
		}	
		echo '<link rel="stylesheet" type="text/css" href="' . get_settings('siteurl') . '/wp-content/plugins/lightbox-gallery/js/jquery.lightbox.css" />'."\n";
		echo '<link rel="stylesheet" type="text/css" href="' . get_settings('siteurl') . '/wp-content/plugins/lightbox-gallery/js/jquery.tooltip.css" />'."\n";
	}
}
	
function add_lightbox_gallery_jquery() {
	global $wp_query;
	
	$flag = false;
	if($wp_query->posts) {
		for($i=0;$i<count($wp_query->posts);$i++) {
			if ( preg_match('/\[gallery([^\]]+)?\]/', $wp_query->posts[$i]->post_content) ) {
				$flag = true;
				break;
			}
		}
	}
	
	if ( !is_admin() && $flag ) {
		wp_enqueue_script( 'jquery');
		wp_enqueue_script('dimensions', '/wp-content/plugins/lightbox-gallery/js/jquery.dimensions.js', array('jquery'));
		wp_enqueue_script('bgtiframe', '/wp-content/plugins/lightbox-gallery/js/jquery.bgiframe.js', array('jquery'));
		wp_enqueue_script('lightbox', '/wp-content/plugins/lightbox-gallery/js/jquery.lightbox.js', array('jquery'));
		wp_enqueue_script('tooltip', '/wp-content/plugins/lightbox-gallery/js/jquery.tooltip.js', array('jquery'));
		wp_enqueue_script('lightbox-gallery', '/wp-content/plugins/lightbox-gallery/lightbox-gallery.js', array('jquery'));
	}
}

add_shortcode('gallery', 'lightbox_gallery');

function lightbox_gallery($attr) {
	global $post;

	// Allow plugins/themes to override the default gallery template.
	$output = apply_filters('post_gallery', '', $attr);
	if ( $output != '' )
		return $output;

	// We're trusting author input, so let's at least make sure it looks like a valid orderby statement
	if ( isset( $attr['orderby'] ) ) {
		$attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
		if ( !$attr['orderby'] )
			unset( $attr['orderby'] );
	}
	
	if ( !isset( $attr['orderby'] ) && get_bloginfo('version')<2.6 ) {
		$attr['orderby'] = 'menu_order ASC, ID ASC';
	}
			
	extract(shortcode_atts(array(
		'order'      => 'ASC',
		'orderby'    => 'menu_order ID',
		'id'         => $post->ID,
		'itemtag'    => 'dl',
		'icontag'    => 'dt',
		'captiontag' => 'dd',
		'columns'    => 3,
		'size'       => 'thumbnail',
		'lightboxsize' => 'medium',
		'meta'       => 'false',
		'class'      => 'gallery1',
		'nofollow'   => false
	), $attr));
	
	$id = intval($id);
	$attachments = get_children( array('post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );

	if ( empty($attachments) )
		return '';

	if ( is_feed() ) {
		$output = "\n";
		foreach ( $attachments as $id => $attachment )
			$output .= wp_get_attachment_link($id, $size, true) . "\n";
		return $output;
	}

	$listtag = tag_escape($listtag);
	$itemtag = tag_escape($itemtag);
	$captiontag = tag_escape($captiontag);
	$columns = intval($columns);
	$itemwidth = $columns > 0 ? floor(100/$columns) : 100;
	
	$output = apply_filters('gallery_style', "
<style type='text/css'>
	.gallery-item {width: {$itemwidth}%;}
</style>
<div class='gallery {$class}'>");

	foreach ( $attachments as $id => $attachment ) {
		if ( $attachment->post_type == 'attachment' ) {
			$thumbnail_link = wp_get_attachment_image_src($attachment->ID, $size, false);
			$lightbox_link = wp_get_attachment_image_src($attachment->ID, $lightboxsize, false);
			trim($attachment->post_content);
			trim($attachment->post_excerpt);
		
			if($meta == "true") {
				$imagedata = wp_get_attachment_metadata($attachment->ID);
				unset($metadata);
				if($imagedata['image_meta']['camera'])
					$metadata .= __('camera', 'lightbox-gallery')            . ": ". $imagedata['image_meta']['camera'] . " ";
				if($imagedata['image_meta']['aperture'])
					$metadata .= __('aperture', 'lightbox-gallery')          . ": F". $imagedata['image_meta']['aperture'] . " ";
				if($imagedata['image_meta']['focal_length'])
					$metadata .= __('focal_length', 'lightbox-gallery')      . ": ". $imagedata['image_meta']['focal_length'] . "mm ";
				if($imagedata['image_meta']['iso'])
					$metadata .= __('ISO', 'lightbox-gallery')      . ": ". $imagedata['image_meta']['iso'] . " ";
				if($imagedata['image_meta']['shutter_speed']) {
					if($imagedata['image_meta']['shutter_speed']<1) $speed = "1/". round(1/$imagedata['image_meta']['shutter_speed']);
					else $speed = $imagedata['image_meta']['shutter_speed'];
					$metadata .= __('shutter_speed', 'lightbox-gallery')     . ": " . $speed . " ";
				}
				if($imagedata['image_meta']['created_timestamp'])
					$metadata .= __('created_timestamp', 'lightbox-gallery') . ": ". date('Y:m:d H:i:s', $imagedata['image_meta']['created_timestamp']);
			}

			$output .= "<{$itemtag} class='gallery-item'>";
			$output .= "
<{$icontag} class='gallery-icon'>
<a href='{$lightbox_link[0]}' title='{$attachment->post_excerpt}'";
			if ( $nofollow == "true" ) $output .= " rel='nofollow'";
			$output .= "><img src='{$thumbnail_link[0]}' width='{$thumbnail_link[1]}' height='{$thumbnail_link[2]}' alt='{$attachment->post_excerpt}' /></a>
</{$icontag}>";
			if ( $captiontag && (trim($attachment->post_excerpt) || trim($attachment->post_content) || $metadata) ) {
				$output .= "<{$captiontag} class='gallery-caption'>";
				if($attachment->post_excerpt) $output .= $attachment->post_excerpt . "<br />\n";
				if($attachment->post_content) $output .= $attachment->post_content . "<br />\n";
				if($metadata) $output .= $metadata;
				$output .= "</{$captiontag}>";
			}
			$output .= "</{$itemtag}>";
			if ( $columns > 0 && ++$i % $columns == 0 )
				$output .= '<div style="clear: both"></div>';
		}
	}

	$output .= '
<div style="clear: both"></div>
</div>';

	return $output;
}


?>