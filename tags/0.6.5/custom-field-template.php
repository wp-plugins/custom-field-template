<?php
/*
Plugin Name: Custom Field Template
Plugin URI: http://wordpressgogo.com/development/custom-field-template.html
Description: This plugin adds the default custom fields on the Write Post/Page.
Author: Hiroaki Miyashita
Version: 0.6.5
Author URI: http://wordpressgogo.com/
*/

/*
This program is based on the rc:custom_field_gui plugin written by Joshua Sigar.
I appreciate your efforts, Joshua.
*/

class custom_field_template {

	function custom_field_template() {
		add_action( 'init', array(&$this, 'custom_field_template_init') );
		add_action( 'admin_menu', array(&$this, 'custom_field_template_admin_menu') );
		add_action( 'admin_print_scripts', array(&$this, 'custom_field_template_admin_scripts') );
		
		add_action( 'edit_post', array(&$this, 'edit_meta_value') );
		add_action( 'save_post', array(&$this, 'edit_meta_value') );
		add_action( 'publish_post', array(&$this, 'edit_meta_value') );

		add_filter( 'media_send_to_editor', array(&$this, 'media_send_to_custom_field'), 15 );
		add_filter( 'plugin_action_links', array(&$this, 'wpaq_filter_plugin_actions',), 10, 2);
	}
	
	function media_send_to_custom_field($html) {
		$options = $this->get_custom_field_template_data();

		$out =  '<script type="text/javascript">' . "\n" .
				'	/* <![CDATA[ */' . "\n" .
				'	var win = window.dialogArguments || opener || parent || top;' . "\n" .
				'	win.send_to_custom_field("' . addslashes($html) . '");' . "\n" .
				'/* ]]> */' . "\n" .
				'</script>' . "\n";

		echo $out;

		if ($options['custom_field_template_use_multiple_insert']) {
			return;
		} else {
			exit();
		}
	}

	function custom_field_template_init() {
		global $wp_version;

		if ( function_exists('load_plugin_textdomain') ) {
			if ( !defined('WP_PLUGIN_DIR') ) {
				load_plugin_textdomain('custom-field-template', str_replace( ABSPATH, '', dirname(__FILE__) ) );
			} else {
				load_plugin_textdomain('custom-field-template', false, dirname( plugin_basename(__FILE__) ) );
			}
		}
		
		if ( is_user_logged_in() && isset($_REQUEST['id']) && $_REQUEST['page'] == 'custom-field-template/custom-field-template.php' ) {
			echo $this->load_custom_field( $_REQUEST['id'] );
			exit();
		}
		
		if( strstr($_SERVER['REQUEST_URI'], 'wp-admin/plugins.php') && ((isset($_GET['activate']) && $_GET['activate'] == 'true') || (isset($_GET['activate-multi']) && $_GET['activate-multi'] == 'true') ) ) {
			$options = $this->get_custom_field_template_data();
			if( !$options ) {
				$this->install_custom_field_template_data();
				$this->install_custom_field_template_css();
			}
		}
		
		if ( substr($wp_version, 0, 3) < '2.5' ) {
			add_action( 'simple_edit_form', array(&$this, 'insert_custom_field'), 1 );
			add_action( 'edit_form_advanced', array(&$this, 'insert_custom_field'), 1 );
			add_action( 'edit_page_form', array(&$this, 'insert_custom_field'), 1 );
		} else {
			require_once(ABSPATH . 'wp-admin/includes/template.php');
			add_meta_box('cftdiv', __('Custom Field Template', 'custom-field-template'), array(&$this, 'insert_custom_field'), 'post', 'normal', 'core');
			add_meta_box('cftdiv', __('Custom Field Template', 'custom-field-template'), array(&$this, 'insert_custom_field'), 'page', 'normal', 'core');
		}

	}
	
	function wpaq_filter_plugin_actions($links, $file){
		static $this_plugin;

		if( ! $this_plugin ) $this_plugin = plugin_basename(__FILE__);

		if( $file == $this_plugin ){
			$settings_link = '<a href="options-general.php?page=custom-field-template.php">' . __('Settings') . '</a>';
			$links = array_merge( array($settings_link), $links);
		}
		return $links;
	}
	
	function custom_field_template_admin_scripts() {
		wp_enqueue_script( 'jquery');
	}

	function install_custom_field_template_data() {
		$options['custom_fields'][0]['title']   = __('Default Template', 'custom-field-template');
		$options['custom_fields'][0]['content'] = '[Plan]
type = text
size = 35
label = Where are you going to go?

[Plan]
type = textfield
size = 35
hideKey = true

[Favorite Fruits]
type = checkbox
value = apple # orange # banana # grape
default = orange # grape

[Miles Walked]
type = radio
value = 0-9 # 10-19 # 20+
default = 10-19
clearButton = true

[Temper Level]
type = select
value = High # Medium # Low
default = Low

[Hidden Thought]
type = textarea
rows = 4
cols = 40
tinyMCE = true
mediaButton = true';
		update_option('custom_field_template_data', $options);
	}
	
	function install_custom_field_template_css() {
		$options = get_option('custom_field_template_data');
		$options['css'] = '#cft dl { clear:both; margin:0; padding:0; width:100%; }
#cft dt { float:left; font-weight:bold; margin:0; text-align:center; width:20%; }
#cft dt .hideKey { visibility:hidden; }
#cft dd { float:left; margin:0; text-align:left; width:80%; }
#cft dd p.label { font-weight:bold; margin:0; }
		';
		update_option('custom_field_template_data', $options);
	}

	
	function get_custom_field_template_data() {
		$options = get_option('custom_field_template_data');
		return $options;
	}

	function custom_field_template_admin_menu() {
		add_options_page(__('Custom Field Template', 'custom-field-template'), __('Custom Field Template', 'custom-field-template'), 8, basename(__FILE__), array(&$this, 'custom_field_template_admin'));
	}
	
	function custom_field_template_admin() {
		$options = $this->get_custom_field_template_data();
		if($_POST["custom_field_template_set_options_submit"]) :
			unset($options['custom_fields']);
			$j = 0;
			$options['custom_field_template_replace_keys_by_labels'] = $_POST['custom_field_template_replace_keys_by_labels'];
			$options['custom_field_template_use_multiple_insert'] = $_POST['custom_field_template_use_multiple_insert'];
			$options['custom_field_template_use_wpautop'] = $_POST['custom_field_template_use_wpautop'];
			for($i=0;$i<count($_POST["custom_field_template_content"]);$i++) {
				if( $_POST["custom_field_template_content"][$i] ) {
					$options['custom_fields'][$j]['title']   = $_POST["custom_field_template_title"][$i];
					$options['custom_fields'][$j]['content'] = $_POST["custom_field_template_content"][$i];
					$j++;
				}
			}			
			update_option('custom_field_template_data', $options);
			$message = __('Options updated.', 'custom-field-template');
		elseif ($_POST['custom_field_template_css_submit']) :
			$options['css'] = $_POST['custom_field_template_css'];
			update_option('custom_field_template_data', $options);
			$message = __('Options updated.', 'custom-field-template');
		elseif ($_POST['custom_field_template_php_submit']) :
			unset($options['php']);
			for($i=0;$i<count($_POST["custom_field_template_php"]);$i++) {
				if( $_POST["custom_field_template_php"][$i] )
					$options['php'][] = $_POST["custom_field_template_php"][$i];
			}			
			update_option('custom_field_template_data', $options);
			$message = __('Options updated.', 'custom-field-template');
		elseif ($_POST['custom_field_template_unset_options_submit']) :
			$this->install_custom_field_template_data();
			$this->install_custom_field_template_css();
			$options = $this->get_custom_field_template_data();
			$message = __('Options resetted.', 'custom-field-template');
		elseif ($_POST['custom_field_template_delete_options_submit']) :
			delete_option('custom_field_template_data');
			$options = $this->get_custom_field_template_data();
			$message = __('Options deleted.', 'custom-field-template');
		endif;
?>
<?php if ($message) : ?>
<div id="message" class="updated"><p><?php echo $message; ?></p></div>
<?php endif; ?>
<div class="wrap">
<h2><?php _e('Custom Field Template', 'custom-field-template'); ?></h2>
<br class="clear"/>

<div id="poststuff" class="ui-sortable">
<div class="postbox">
<h3><?php _e('Custom Field Template Options', 'custom-field-template'); ?></h3>
<div class="inside">
<form method="post">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<?php
	for ( $i = 0; $i < count($options['custom_fields'])+1; $i++ ) {
?>
<tr><td>
<p><label for="custom_field_template_title[<?= $i ?>]"><?php echo sprintf(__('Template Title %d', 'custom-field-template'), $i+1); ?></label>:<br />
<input type="text" name="custom_field_template_title[<?= $i ?>]" id="custom_field_template_title[<?= $i ?>]" value="<?= stripcslashes($options['custom_fields'][$i]['title']) ?>" size="60" /></p>
<p><label for="custom_field_template_content[<?= $i ?>]"><?php echo sprintf(__('Template Content %d', 'custom-field-template'), $i+1); ?></label>:<br />
<textarea name="custom_field_template_content[<?= $i ?>]" id="custom_field_template_content[<?= $i ?>]" rows="10" cols="60"><?= stripcslashes($options['custom_fields'][$i]['content']) ?></textarea></p>
</td></tr>
<?php
	}
?>
<tr><td>
<p><label for="custom_field_template_use_multiple_insert"><?php _e('In case that you would like to insert multiple images at once in use of the custom field media buttons', 'custom-field-template'); ?></label>:<br />
<input type="checkbox" name="custom_field_template_use_multiple_insert" id="custom_field_template_use_multiple_insert" value="1" <?php if ($options['custom_field_template_use_multiple_insert']) { echo 'checked="checked"'; } ?> /> <?php _e('Use multiple image inset', 'custom-field-template'); ?><br /><span style="color:#FF0000; font-weight:bold;"><?php _e('Caution:', 'custom-field-teplate'); ?> <?php _e('You need to edit `wp-admin/includes/media.php`. Delete or comment out the code in the function media_send_to_editor at around line 88-96.', 'custom-field-template'); ?></span></p>
</td>
</tr>
<tr><td>
<p><label for="custom_field_template_replace_keys_by_labels"><?php _e('In case that you would like to replace custom keys by labels if `label` is set', 'custom-field-template'); ?></label>:<br />
<input type="checkbox" name="custom_field_template_replace_keys_by_labels" id="custom_field_template_replace_keys_by_labels" value="1" <?php if ($options['custom_field_template_replace_keys_by_labels']) { echo 'checked="checked"'; } ?> /> <?php _e('Use labels in place of custom keys', 'custom-field-template'); ?></p>
</td>
<tr><td>
<p><label for="custom_field_template_use_wpautop"><?php _e('In case that you would like to add p and br tags in textareas automatically', 'custom-field-template'); ?></label>:<br />
<input type="checkbox" name="custom_field_template_use_wpautop" id="custom_field_template_use_wpautop" value="1" <?php if ($options['custom_field_template_use_wpautop']) { echo 'checked="checked"'; } ?> /> <?php _e('Use wpautop function', 'custom-field-template'); ?></p>
</td>
</tr>
<tr><td>
<p><input type="submit" name="custom_field_template_set_options_submit" value="<?php _e('Update Options &raquo;', 'custom-field-template'); ?>" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>
</div>

<div id="poststuff" class="ui-sortable">
<div class="postbox closed">
<h3><?php _e('CSS', 'custom-field-template'); ?></h3>
<div class="inside">
<form method="post">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr><td>
<p><textarea name="custom_field_template_css" id="custom_field_template_css" rows="10" cols="60"><?= stripcslashes($options['css']) ?></textarea></p>
</td></tr>
<tr><td>
<p><input type="submit" name="custom_field_template_css_submit" value="<?php _e('Update Options &raquo;', 'custom-field-template'); ?>" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>
</div>

<div id="poststuff" class="ui-sortable">
<div class="postbox closed">
<h3><?php _e('PHP CODE (Experimental Option)', 'custom-field-template'); ?></h3>
<div class="inside">
<form method="post" onsubmit="return confirm('<?php _e('Are you sure to save PHP codes? Please do it at your own risk.', 'custom-field-template'); ?>');">
<p><?php _e('This option is available only for `radio` and `select` types. You must set $values as an array.', 'custom-field-template'); ?><br />ex. $values = array('dog', 'cat', 'monkey'); $default = 'cat';</p>
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<?php
	for ($i=0;$i<count($options['php'])+1;$i++) :
?>
<tr><th>CODE# <?= $i ?></th></tr>
<tr><td>
<p><textarea name="custom_field_template_php[]" rows="10" cols="60"><?= stripcslashes($options['php'][$i]) ?></textarea></p>
</td></tr>
<?php
	endfor;
?>
<tr><td>
<p><input type="submit" name="custom_field_template_php_submit" value="<?php _e('Update Options &raquo;', 'custom-field-template'); ?>" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>
</div>

<div id="poststuff" class="ui-sortable">
<div class="postbox closed">
<h3><?php _e('Option List', 'custom-field-template'); ?></h3>
<div class="inside">
ex.<br />
[Plan]<br />
type = textfield<br />
size = 35<br />
hideKey = true<br />

<table class="form-table" style="margin-bottom:5px;">
<thead>
<tr>
<th>type</th><th>text or textfield</th><th>checkbox</th><th>radio</th><th>select</th><th>textarea</th>
</tr>
</thead>
<tbody>
<tr>
<th>hideKey</th><td>hideKey = true</td><td>hideKey = true</td><td>hideKey = true</td><td>hideKey = true</td><td>hideKey = true</td>
</tr>
<tr>
<tr>
<th>label</th><td>label = ABC</td><td>label = DEF</td><td>label = GHI</td><td>label = JKL</td><td>label = MNO</td>
</tr>
<th>size</th><td>size = 30</td><td></td><td></td><td></td><td></td>
</tr>
<tr>
<th>value</th><td></td><td>value = apple # orange # banana</td><td>value = apple # orange # banana</td><td>value = apple # orange # banana</td>
<td></td>
</tr>
<tr>
<th>default</th><td></td><td>default = orange # banana</td><td>default = orange</td><td>default = orange</td><td></td>
</tr>
<tr>
<th>clearButton</th><td></td><td></td><td>clearButton = true</td><td></td><td></td>
</tr>
<tr>
<th>rows</th><td></td><td></td><td></td><td></td><td>rows = 4</td>
</tr>
<tr>
<th>cols</th><td></td><td></td><td></td><td></td><td>cols = 40</td>
</tr>
<tr>
<th>tinyMCE</th><td></td><td></td><td></td><td></td><td>tinyMCE = true</td>
</tr>
<tr>
<th>mediaButton</th><td></td><td></td><td></td><td></td><td>mediaButton = true</td>
</tr>
<tr>
<th>code</th><td></td><td></td><td>code = 0</td><td>code = 0</td><td></td>
</tr>
<tr>
<th>level</th><td>level = 1</td><td>level = 3</td><td>level = 5</td><td>level = 7</td><td>level = 9</td>
</tr>
</tbody>
</table>
</div>
</div>
</div>

<div id="poststuff" class="ui-sortable">
<div class="postbox closed">
<h3><?php _e('Reset Options', 'custom-field-template'); ?></h3>
<div class="inside">
<form method="post" onsubmit="return confirm('<?php _e('Are you sure to reset options? Options you set will be reset to the default settings.', 'custom-field-template'); ?>');">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr><td>
<p><input type="submit" name="custom_field_template_unset_options_submit" value="<?php _e('Unset Options &raquo;', 'custom-field-template'); ?>" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>
</div>

<div id="poststuff" class="ui-sortable">
<div class="postbox closed">
<h3><?php _e('Delete Options', 'custom-field-template'); ?></h3>
<div class="inside">
<form method="post" onsubmit="return confirm('<?php _e('Are you sure to delete options? Options you set will be deleted.', 'custom-field-template'); ?>');">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr><td>
<p><input type="submit" name="custom_field_template_delete_options_submit" value="<?php _e('Delete Options &raquo;', 'custom-field-template'); ?>" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>
</div>

<script type="text/javascript">
// <![CDATA[
<?php if ( version_compare( substr($wp_version, 0, 3), '2.7', '<' ) ) { ?>
jQuery('.postbox h3').prepend('<a class="togbox">+</a> ');
<?php } ?>
jQuery('.postbox h3').click( function() { jQuery(jQuery(this).parent().get(0)).toggleClass('closed'); } );
jQuery('.postbox.close-me').each(function(){
jQuery(this).addClass("closed");
});
//-->
</script>


</div>
<?php
	}
		
	function sanitize_name( $name ) {
		$name = sanitize_title( $name );
		$name = str_replace( '-', '_', $name );
		
		return $name;
	}
	
	function get_custom_fields( $id ) {
		$options = $this->get_custom_field_template_data();
		if ( !$options['custom_fields'][$id] )
			return null;
			
		$custom_fields = $this->parse_ini_str( $options['custom_fields'][$id]['content'], true );
		return $custom_fields;
	}
	
	function make_textfield( $name, $sid, $size = 25, $hideKey, $label ) {
		$options = $this->get_custom_field_template_data();

		$title = $name;
		$name = $this->sanitize_name( $name );
		
		if( isset( $_REQUEST[ 'post' ] ) ) {
			$value = get_post_meta( $_REQUEST[ 'post' ], $title );
			if ( $value ) {
				$value = $value[ $sid ];
			}
		}
		
		if ( $hideKey == true ) $hide = ' class="hideKey""';
		
		if ( !empty($label) && $options['custom_field_template_replace_keys_by_labels'] )
			$title = stripcslashes($label);
		
		$out .= 
			'<dl>' .
			'<dt><span' . $hide . '>' . $title . '</span></dt>' .
			'<dd>';

		if ( !empty($label) && !$options['custom_field_template_replace_keys_by_labels'] )
			$out .= '<p class="label">' . stripcslashes($label) . '</p>';
		$out .= '<input id="' . $name . '" name="' . $name . '[]" value="' . attribute_escape($value) . '" type="text" size="' . $size . '" /></dd>' .
			'</dl>';
		return $out;
	}
	
	function make_checkbox( $name, $sid, $value, $checked, $hideKey, $label ) {
		$options = $this->get_custom_field_template_data();

		$title = $name;
		$name = $this->sanitize_name( $name );
		
		if ( !$value ) $value = "true";

		if( isset( $_REQUEST[ 'post' ] ) && $_REQUEST[ 'post' ] > 0 ) {
			$selected = get_post_meta( $_REQUEST[ 'post' ], $title );
			if ( $selected ) {
 				if ( in_array(stripcslashes($value), $selected) ) $checked = 'checked="checked"';
			}
		}
		else {
			if( $checked == true )  $checked = 'checked="checked"';
		}
		
		if ( $hideKey == true ) $hide = ' class="hideKey""';
		
		if ( !empty($label) && $options['custom_field_template_replace_keys_by_labels'] )
			$title = stripcslashes($label);
		
		$out .= 
			'<dl>' .
			'<dt><span' . $hide . '>' . $title . '</span></dt>' .
			'<dd>';
		
		if ( !empty($label) && !$options['custom_field_template_replace_keys_by_labels'] )
			$out .= '<p class="label">' . stripcslashes($label) . '</p>';
		$out .=	'<label for="' . $id . '" class="selectit"><input name="' . $name . '[' . $sid . ']" value="' . attribute_escape($value) . '" ' . $checked . ' type="checkbox" /> ' . stripcslashes($value) . '</label><br />';

		$out .= '</dd></dl>';
		
		return $out;
	}
	
	function make_radio( $name, $sid, $values, $clearButton, $default, $hideKey, $label, $code ) {
		$options = $this->get_custom_field_template_data();

		$title = $name;
		$name = $this->sanitize_name( $name );

		if ( is_numeric($code) ) :
			eval(stripcslashes($options['php'][$code]));
		endif;
		
		if( isset( $_REQUEST[ 'post' ] ) && $_REQUEST[ 'post' ] > 0 ) {
			$selected = get_post_meta( $_REQUEST[ 'post' ], $title );
			$selected = $selected[ $sid ];
		}
		else {
			$selected = $default;
		}
			
		if ( $hideKey == true ) $hide = ' class="hideKey""';

		if ( !empty($label) && $options['custom_field_template_replace_keys_by_labels'] )
			$title = stripcslashes($label);

		$out .= 
			'<dl>' .
			'<dt><span' . $hide . '>' . $title . '</span>';
			
		if( $clearButton == true ) {
			$out .= '<div>';
			$out .= '<a href="#clear" onclick="jQuery(this).parent().parent().parent().find(\'input\').attr(\'checked\', \'\'); return false;">' . __('Clear', 'custom-field-template') . '</a>';
			$out .= '</div>';
		}
			
		$out .=
			'</dt>' .
			'<dd>';

		if ( !empty($label) && !$options['custom_field_template_replace_keys_by_labels'] )
			$out .= '<p class="label">' . stripcslashes($label) . '</p>';
		foreach( $values as $val ) {
			$id = $name . '_' . $this->sanitize_name( $val );
			
			$checked = ( trim( $val ) == trim( $selected ) ) ? 'checked="checked"' : '';
			
			$out .=	
				'<label for="' . $id . '" class="selectit"><input id="' . $id . '" name="' . $name . '[' . $sid . ']" value="' . attribute_escape($val) . '" ' . $checked . ' type="radio" /> ' . stripcslashes($val) . '</label><br />';
		}	 
		$out .= '</dd></dl>';
		
		return $out;			
	}
	
	function make_select( $name, $sid, $values, $default, $hideKey, $label, $code ) {
		$options = $this->get_custom_field_template_data();

		$title = $name;
		$name = $this->sanitize_name( $name );

		if ( is_numeric($code) ) :
			eval(stripcslashes($options['php'][$code]));
		endif;
	
		if( isset( $_REQUEST[ 'post' ] ) && $_REQUEST[ 'post' ] > 0 ) {
			$selected = get_post_meta( $_REQUEST[ 'post' ], $title );
			if ( $selected ) {
				$selected = $selected[ $sid ];
			}
		}
		else {
			$selected = $default;
		}
		
		if ( $hideKey == true ) $hide = ' class="hideKey""';

		if ( !empty($label) && $options['custom_field_template_replace_keys_by_labels'] )
			$title = stripcslashes($label);
		
		$out .= 
			'<dl>' .
			'<dt><span' . $hide . '>' . $title . '</span></dt>' .
			'<dd>';
			
		if ( !empty($label) && !$options['custom_field_template_replace_keys_by_labels'] )
			$out .= '<p class="label">' . stripcslashes($label) . '</p>';
		$out .=	'<select name="' . $name . '[]">' .
			'<option value="" >Select</option>';
						
		foreach( $values as $val ) {
			$checked = ( trim( $val ) == trim( $selected ) ) ? 'selected="selected"' : '';
		
			$out .=
				'<option value="' . attribute_escape($val) . '" ' . $checked . ' > ' . $val. '</option>'; 
		}
		$out .= '</select></dd></dl>';
		
		return $out;
	}
	
	function make_textarea( $name, $sid, $rows, $cols, $tinyMCE, $mediaButton, $hideKey, $label ) {
		$options = $this->get_custom_field_template_data();

		global $wp_version;

		$title = $name;
		$name = $this->sanitize_name( $name );
		
		if( isset( $_REQUEST[ 'post' ] ) ) {
			$value = get_post_meta( $_REQUEST[ 'post' ], $title );
			$value = $value[ $sid ];
		}
		
		$rand = rand();
		
		if( $tinyMCE == true ) {
			$out = '<script type="text/javascript">' . "\n" .
					'// <![CDATA[' . "\n" .
					'if ( typeof tinyMCE != "undefined" )' . "\n";
			if ( $options['custom_field_template_use_wpautop'] ) :
				$out .=	'jQuery(document).ready(function() {document.getElementById("'. $name . $rand . '").value = document.getElementById("'. $name . $rand . '").value; tinyMCE.execCommand("mceAddControl", false, "'. $name . $rand . '"); tinyMCEID.push("'. $name . $rand . '");});' . "\n";
			else:
				$out .=	'jQuery(document).ready(function() {document.getElementById("'. $name . $rand . '").value = switchEditors.wpautop(document.getElementById("'. $name . $rand . '").value); tinyMCE.execCommand("mceAddControl", false, "'. $name . $rand . '"); tinyMCEID.push("'. $name . $rand . '");});' . "\n";
			endif;		
				$out .= '// ]]>' . "\n" .
						'</script>';
		}
		
		if ( substr($wp_version, 0, 3) >= '2.5' ) {

			if ( $mediaButton == true ) {
				$media_upload_iframe_src = "media-upload.php";
				$media_title = __('Add Media');
				$image_upload_iframe_src = apply_filters('image_upload_iframe_src', "$media_upload_iframe_src?type=image");
				$image_title = __('Add an Image');
				$video_upload_iframe_src = apply_filters('video_upload_iframe_src', "$media_upload_iframe_src?type=video");
				$video_title = __('Add Video');
				$audio_upload_iframe_src = apply_filters('audio_upload_iframe_src', "$media_upload_iframe_src?type=audio");
				$audio_title = __('Add Audio');
				$media = <<<EOF
<a href="{$image_upload_iframe_src}&TB_iframe=true" id="add_image{$rand}" title='$image_title' onclick="focusTextArea('{$name}{$rand}'); jQuery(this).attr('href',jQuery(this).attr('href').replace('\?','?post_id='+jQuery('#post_ID').val())); return thickbox(this);"><img src='images/media-button-image.gif' alt='$image_title' /></a>
<a href="{$video_upload_iframe_src}&amp;TB_iframe=true" id="add_video{$rand}" title='$video_title' onclick="focusTextArea('{$name}{$rand}'); jQuery(this).attr('href',jQuery(this).attr('href').replace('\?','?post_id='+jQuery('#post_ID').val())); return thickbox(this);"><img src='images/media-button-video.gif' alt='$video_title' /></a>
<a href="{$audio_upload_iframe_src}&amp;TB_iframe=true" id="add_audio{$rand}" title='$audio_title' onclick="focusTextArea('{$name}{$rand}'); jQuery(this).attr('href',jQuery(this).attr('href').replace('\?','?post_id='+jQuery('#post_ID').val())); return thickbox(this);"><img src='images/media-button-music.gif' alt='$audio_title' /></a>
<a href="{$media_upload_iframe_src}?TB_iframe=true" id="add_media{$rand}" title='$media_title' onclick="focusTextArea('{$name}{$rand}'); jQuery(this).attr('href',jQuery(this).attr('href').replace('\?','?post_id='+jQuery('#post_ID').val())); return thickbox(this);"><img src='images/media-button-other.gif' alt='$media_title' /></a>
EOF;
			}

			$switch = '<div>';
			if( $tinyMCE == true && user_can_richedit() ) {
				$switch .= '<a href="#toggle" onclick="switchMode(\''.$name.$rand.'\'); return false;">' . __('Toggle', 'custom-field-template') . '</a>';
			}
			$switch .= '</div>';
		
		}
				
		if ( $hideKey == true ) $hide = ' class="hideKey""';

		if ( !empty($label) && $options['custom_field_template_replace_keys_by_labels'] )
			$title = stripcslashes($label);
		
		$out .= 
			'<dl>' .
			'<dt><span' . $hide . '>' . $title . '</span><br />' . $media . $switch . '</dt>' .
			'<dd>';

		if ( !empty($label) && !$options['custom_field_template_replace_keys_by_labels'] )
			$out .= '<p class="label">' . stripcslashes($label) . '</p>';
		$out .= '<textarea id="' . $name . $rand . '" name="' . $name . '[' . $sid . ']" rows="' .$rows. '" cols="' . $cols . '" style="color:#000000">' . attribute_escape($value) . '</textarea><input type="hidden" name="'.$name.'_rand['.$sid.']" value="'.$rand.'" /></dd>' .
			'</dl>';
		return $out;
	}

	function load_custom_field( $id = 0 ) {
		global $userdata;
		get_currentuserinfo();
		$level = $userdata->user_level;
		
		$options = $this->get_custom_field_template_data();

		$fields = $this->get_custom_fields( $id );
		
		if( $fields == null)
			return;

		$out .= '<input type="hidden" name="custom-field-template-id" id="custom-field-template-id" value="' . $id . '" />';
		foreach( $fields as $title => $data ) {
			for($i = 0; $i<count($data); $i++) {
				if ( is_numeric($data[$i]['level']) ) :
					if ( $data[$i]['level'] > $level ) continue;
				endif; 
				if( $data[$i]['type'] == 'textfield' || $data[$i]['type'] == 'text' ) {
					$out .= $this->make_textfield( $title, $i, $data[$i]['size'], $data[$i]['hideKey'], $data[$i]['label'] );
				}
				else if( $data[$i]['type'] == 'checkbox' ) {
					$out .= 
						$this->make_checkbox( $title, $i, $data[$i]['value'], $data[$i]['checked'], $data[$i]['hideKey'], $data[$i]['label'] );
				}
				else if( $data[$i]['type'] == 'radio' ) {
					$out .= 
						$this->make_radio( 
							$title, $i, explode( '#', $data[$i]['value'] ), $data[$i]['clearButton'], $data[$i]['default'], $data[$i]['hideKey'], $data[$i]['label'], $data[$i]['code'] );
				}
				else if( $data[$i]['type'] == 'select' ) {
					$out .= 
						$this->make_select( 
							$title, $i, explode( '#', $data[$i]['value'] ), $data[$i]['default'], $data[$i]['hideKey'], $data[$i]['label'], $data[$i]['code'] );
				}
				else if( $data[$i]['type'] == 'textarea' ) {
					if ( $options['tinyMCE'][$_REQUEST['post']][$this->sanitize_name($title)][$i] )  $data[$i]['rows']  = $options['tinyMCE'][$_REQUEST['post']][$this->sanitize_name($title)][$i];
					$out .= 
						$this->make_textarea( $title, $i, $data[$i]['rows'], $data[$i]['cols'], $data[$i]['tinyMCE'], $data[$i]['mediaButton'], $data[$i]['hideKey'], $data[$i]['label'] );
				}
			}
		}
		$out .= '<br style="clear:both;" />';		
	
		return $out;
	}

	function insert_custom_field() {
		global $wp_version;
		$options = $this->get_custom_field_template_data();
		
		if( $options == null)
			return;

		if ( !$options['css'] ) {
			$this->install_custom_field_template_css();
			$options = $this->get_custom_field_template_data();
		}

		if ( substr($wp_version, 0, 3) < '2.5' ) {
			$out .= '
<div class="dbx-b-ox-wrapper">
<fieldset id="seodiv" class="dbx-box">
<div class="dbx-h-andle-wrapper">
<h3 class="dbx-handle">' . __('Custom Field Template', 'custom-field-template') . '</h3>
</div>
<div class="dbx-c-ontent-wrapper">
<div class="dbx-content">';
        }
		
		$out .= '<script type="text/javascript">' . "\n" .
					'// <![CDATA[' . "\n" .
					'function send_to_custom_field(h) {' . "\n" .
					'	if ( tmpFocus ) ed = tmpFocus;' . "\n" .
					'	else {ed = tinyMCE.get("content"); if(ed) {if(!ed.isHidden()) isTinyMCE = true;}}' . "\n" .
					'	if ( typeof tinyMCE != "undefined" && isTinyMCE && !ed.isHidden() ) {' . "\n" .
					'		ed.focus();' . "\n" .
					'		//if (tinymce.isIE)' . "\n" .
					'			//ed.selection.moveToBookmark(tinymce.EditorManager.activeEditor.windowManager.bookmark);' . "\n" .
					'		if ( h.indexOf("[caption") != -1 )' . "\n" .
					'			h = ed.plugins.wpeditimage._do_shcode(h);' . "\n" .
					'		ed.execCommand("mceInsertContent", false, h);' . "\n" .
					'	} else {' . "\n" .
					'		if ( tmpFocus ) edInsertContent(tmpFocus, h);' . "\n" .
					'		else edInsertContent(edCanvas, h);' . "\n" .
					'	}' . "\n";
					
					if (!$options['custom_field_template_use_multiple_insert']) {
						$out .= '	tb_remove();' . "\n" .
								'	tmpFocus = undefined;' . "\n" .
								'	isTinyMCE = false;' . "\n";
					}

		$out .=		'}' . "\n" .
					'jQuery(".thickbox").bind("click", function (e) {' . "\n" .
					'	tmpFocus = undefined;' . "\n" .
					'	isTinyMCE = false;' . "\n" . 
					'});' . "\n" .
					'var isTinyMCE;' . "\n" .
					'var tmpFocus;' . "\n" .
					'function focusTextArea(id) {' . "\n" . 
					'	jQuery(document).ready(function() {' . "\n" .
					'		var elm = tinyMCE.get(id);' . "\n" .
					'		if ( ! elm || elm.isHidden() ) {' . "\n" .
					'			elm = document.getElementById(id);' . "\n" .
					'			isTinyMCE = false;' . "\n" .
					'		}else isTinyMCE = true;' . "\n" .
					'		tmpFocus = elm' . "\n" .
					'		elm.focus();' . "\n" .
					'		if (elm.createTextRange) {' . "\n" .
					'			var range = elm.createTextRange();' . "\n" .
					'			range.move("character", elm.value.length);' . "\n" .
					'			range.select();' . "\n" .
					'		} else if (elm.setSelectionRange) {' . "\n" .
					'			elm.setSelectionRange(elm.value.length, elm.value.length);' . "\n" .
					'		}' . "\n" .
					'	});' . "\n" .
					'}' . "\n" .
					'function thickbox(link) {' . "\n" .
					'	var t = link.title || link.name || null;' . "\n" .
					'	var a = link.href || link.alt;' . "\n" .
					'	var g = link.rel || false;' . "\n" .
					'	tb_show(t,a,g);' . "\n" .
					'	link.blur();' . "\n" .
					'	return false;' . "\n" .
					'}' . "\n" .
					'function switchMode(id) {' . "\n" .
					'	var ed = tinyMCE.get(id);' . "\n" .
					'	if ( ! ed || ed.isHidden() ) {' . "\n" .
					'		document.getElementById(id).value = switchEditors.wpautop(document.getElementById(id).value);' . "\n" .
					'		if ( ed ) ed.show();' . "\n" .
					'		else tinyMCE.execCommand("mceAddControl", false, id);' . "\n" .
					'	} else {' . "\n" .
					'		ed.hide();document.getElementById(id).style.color="#000000";' . "\n" .
					'	}' . "\n" .
					'}' . "\n";
					
					if(count($options['custom_fields'])>$options['posts'][$_REQUEST['post']] && $options['posts'][$_REQUEST['post']]) $init_id = $options['posts'][$_REQUEST['post']];
					else $init_id = 0;

					$fields = $this->get_custom_fields( $init_id );
					if ( user_can_richedit() ) {
						foreach( $fields as $title => $data ) {
							for($i = 0; $i<count($data); $i++) {
								if( $data[$i][ 'type' ] == 'textarea' && $data[$i][ 'tinyMCE' ] ) {
		$out .=		'jQuery(document).ready(function() {' . "\n";
		if ( substr($wp_version, 0, 3) >= '2.7' ) {
		$out .=		'	if ( getUserSetting( "editor" ) == "html" ) {
jQuery("#edButtonPreview").trigger("click"); }' . "\n";
		} else {
		$out .=		'	if(wpTinyMCEConfig) if(wpTinyMCEConfig.defaultEditor == "html") { jQuery("#edButtonPreview").trigger("click"); }' . "\n";
		}
		$out .=		'});' . "\n";
									break;
								}
							}
						}
					}			
					
		$out .=		'var tinyMCEID = new Array();' . "\n" .
					'// ]]>' . "\n" .
					'</script>';
		$out .=		'<style type="text/css">' . "\n" .
					'<!--' . "\n";
		$out .=		$options['css'] . "\n";
		$out .=		'-->' . "\n" .
					'</style>';
		$body = $this->load_custom_field();
		
		if ( count($options['custom_fields'])>1 ) {
			$out .= '<select id="custom_field_template_select" onchange="if(tinyMCEID.length) { for(i=0;i<tinyMCEID.length;i++) {tinyMCE.execCommand(\'mceRemoveControl\', false, tinyMCEID[i]);} tinyMCEID = new Array();};jQuery.ajax({type: \'GET\', url: \'?page=custom-field-template/custom-field-template.php&id=\'+jQuery(this).val()+\'&post=\'+jQuery(\'#post_ID\').val(), success: function(html) {jQuery(\'#cft\').html(html);}});">';
			for ( $i=0; $i < count($options['custom_fields']); $i++ ) {
				if ( $i == $options['posts'][$_REQUEST['post']] ) {
					$out .= '<option value="' . $i . '" selected="selected">' . stripcslashes($options['custom_fields'][$i]['title']) . '</option>';
					$body = $this->load_custom_field($i);
				} else
					$out .= '<option value="' . $i . '">' . stripcslashes($options['custom_fields'][$i]['title']) . '</option>';
			}
			$out .= '</select>';
		}
		
		$out .= '<input type="hidden" name="custom-field-template-verify-key" id="custom-field-template-verify-key" value="' . wp_create_nonce('custom-field-template') . '" />';
		$out .= '<div id="cft">';
		$out .= $body;
		$out .= '</div>';
			
		if ( substr($wp_version, 0, 3) < '2.5' ) {
			$out .= '</div></fieldset></div>';
		}

		echo $out;
	}

	function edit_meta_value( $id ) {
		global $wpdb;
		$options = $this->get_custom_field_template_data();
		
		if( !isset( $id ) )
			$id = $_REQUEST[ 'post_ID' ];
		
		if( !current_user_can('edit_post', $id) )
				return $id;
								
		if( !wp_verify_nonce($_REQUEST['custom-field-template-verify-key'], 'custom-field-template') )
				return $id;
		
		$fields = $this->get_custom_fields($_REQUEST['custom-field-template-id']);
		
		if ( $fields == null )
			return;
	
		foreach( $fields as $title	=> $data) {
			$name = $this->sanitize_name( $title );
			$title = $wpdb->escape(stripcslashes(trim($title)));
			delete_post_meta($id, $title);
		}
				
		foreach( $fields as $title	=> $data) {
			for($i = 0; $i<count($data); $i++) {
				$name = $this->sanitize_name( $title );
				$title = $wpdb->escape(stripcslashes(trim($title)));
			
				$meta_value = stripcslashes(trim($_REQUEST[ "$name" ][$i]));
				
				if ( $options['custom_field_template_use_wpautop'] && $data[$i]['type'] == 'textarea' )
					$meta_value = wpautop($meta_value);
				if( isset( $meta_value ) && strlen( $meta_value ) ) {
					add_post_meta( $id, $title, $meta_value );						
						
					if ( $_REQUEST['TinyMCE_' . $name . trim($_REQUEST[ $name."_rand" ][$i]) . '_size'] ) {
						preg_match('/cw=[0-9]+&ch=([0-9]+)/', $_REQUEST['TinyMCE_' . $name . trim($_REQUEST[ $name."_rand" ][$i]) . '_size'], $matched);
						$options['tinyMCE'][$id][$name][$i] = (int)($matched[1]/20);			
					}
				}
			}
		}
			
		$options['posts'][$id] = $_REQUEST['custom-field-template-id'];
		update_option('custom_field_template_data', $options);
	}
	
	function parse_ini_str($Str,$ProcessSections = TRUE) {
		$Section = NULL;
		$Data = array();
		$Sections = array();
		$id = 0;
		if ($Temp = strtok($Str,"\r\n")) {
			do {
				switch ($Temp{0}) {
					case ';':
					case '#':
						break;
					case '[':
						if (!$ProcessSections) {
							break;
						}
						$Pos = strpos($Temp,'[');
						$Section = substr($Temp,$Pos+1,strpos($Temp,']',$Pos)-1);
						if ( in_array($Section, $Sections) ) {
							$id++;
						} else {
							$id = 0;
							$Data[$Section] = array();
						}
						$Sections[] = $Section;
						if($Data[$Section])
						break;
					default:
						$Pos = strpos($Temp,'=');
						if ($Pos === FALSE) {
							break;
						}
						$Value = array();
						$Value["NAME"] = trim(substr($Temp,0,$Pos));
						$Value["VALUE"] = trim(substr($Temp,$Pos+1));
											
						if ($ProcessSections) {							
							$Data[$Section][$id][$Value["NAME"]] = $Value["VALUE"];
						}
						else {
							$Data[$Value["NAME"]] = $Value["VALUE"];
						}
						break;
				}
			} while ($Temp = strtok("\r\n"));
				
				foreach($Data as $title => $data) {
					foreach($data as $key => $val) {
						if($val["type"] == "checkbox") {
							$values = explode( '#', $val["value"] );
							$defaults = explode( '#', $val["default"] );
							foreach($defaults as $dkey => $dval) {
								$defaults[$dkey] = trim($dval);
							}
							$tmp = $key;
							foreach($values as $value) {
								$Data[$title][$key]["type"] = "checkbox";
								$Data[$title][$key]["value"] = trim($value);
								if($tmp!=$key)
									$Data[$title][$key]["hideKey"] = true;
								if(in_array(trim($value), $defaults))
									$Data[$title][$key]["checked"] = true;
								$key++;
							}
						}
					}
				}			
		}
		return $Data;
	}

}

$custom_field_template = new custom_field_template();
?>