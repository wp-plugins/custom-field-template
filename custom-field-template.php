<?php
/*
Plugin Name: Custom Field Template
Plugin URI: http://wordpressgogo.com/development/custom-field-template.html
Description: This plugin adds the default custom fields on the Write Post/Page.
Author: Hiroaki Miyashita
Version: 0.4.2
Author URI: http://wordpressgogo.com/
*/

/*
This program is based on the rc:custom_field_gui plugin written by Joshua Sigar.
I appreciate your efforts, Joshua.
*/

class custom_field_template {

	function custom_field_template() {
		global $wp_version;
		
		add_action( 'init', array(&$this, 'custom_field_template_init') );
		add_action( 'admin_menu', array(&$this, 'custom_field_template_admin_menu') );
		add_action( 'admin_print_scripts', array(&$this, 'custom_field_template_admin_scripts') );

		add_action( 'simple_edit_form', array(&$this, 'insert_custome_field'), 1 );
		add_action( 'edit_form_advanced', array(&$this, 'insert_custom_field'), 1 );
		add_action( 'edit_page_form', array(&$this, 'insert_custom_field'), 1 );
		add_action( 'edit_post', array(&$this, 'edit_meta_value') );
		add_action( 'save_post', array(&$this, 'edit_meta_value') );
		add_action( 'publish_post', array(&$this, 'edit_meta_value') );

		add_filter( 'media_send_to_editor', array(&$this, 'media_send_to_custom_field'), 15 );
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
		if ( function_exists('load_plugin_textdomain') ) {
			load_plugin_textdomain('custom-field-template', 'wp-content/plugins/custom-field-template');
		}
		
		if ( is_user_logged_in() && isset($_REQUEST['id']) && $_REQUEST['page'] == 'custom-field-template/custom-field-template.php' ) {
			echo $this->load_custom_field( $_REQUEST['id'] );
			exit();
		}
		
		if( strstr($_SERVER['REQUEST_URI'], 'wp-admin/plugins.php') && isset($_GET['activate']) && $_GET['activate'] == 'true' ) {
			$options = $this->get_custom_field_template_data();
			if( !$options ) {
				$this->install_custom_field_template_data();
			}
		}
	}
	
	function custom_field_template_admin_scripts() {
		wp_enqueue_script( 'jquery');
	}

	function install_custom_field_template_data() {
		$options['custom_fields'][0]['title']   = __('Default Template', 'custom-field-template');
		$options['custom_fields'][0]['content'] = '[Plan]
type = textfield
size = 35

[Plan]
type = textfield
size = 35
hideKey = true

[Favorite Animal]
type = checkbox
value = dog
checked = true

[Favorite Animal]
type = checkbox
value = cat
hideKey = true

[Favorite Animal]
type = checkbox
value = monkey
hideKey = true

[Miles Walked]
type = radio
value = 0-9 # 10-19 # 20+
default = 10-19

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
			$options['custom_field_template_use_multiple_insert'] = $_POST['custom_field_template_use_multiple_insert'];
			for($i=0;$i<count($_POST["custom_field_template_content"]);$i++) {
				if( $_POST["custom_field_template_content"][$i] ) {
					$options['custom_fields'][$j]['title']   = $_POST["custom_field_template_title"][$i];
					$options['custom_fields'][$j]['content'] = $_POST["custom_field_template_content"][$i];
					$j++;
				}
			}			
			update_option('custom_field_template_data', $options);
			$message = __('Options updated.', 'custom-field-template');
		elseif ($_POST['custom_field_template_unset_options_submit']) :
			$this->install_custom_field_template_data();
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

<h3><?php _e('Custom Field Template Options', 'custom-field-template'); ?></h3>
<form method="post">
<table class="form-table">
<tbody>
<?php
	for ( $i = 0; $i < count($options['custom_fields'])+1; $i++ ) {
?>
<tr><td>
<p><label for="custom_field_template_title[<?= $i ?>]"><?php echo sprintf(__('Template Title %d', 'custom-field-template'), $i+1); ?></label>:<br />
<input type="text" name="custom_field_template_title[<?= $i ?>]" id="custom_field_template_title[<?= $i ?>]" class="input" value="<?= stripcslashes($options['custom_fields'][$i]['title']) ?>" size="60" /></p>
<p><label for="custom_field_template_content[<?= $i ?>]"><?php echo sprintf(__('Template Content %d', 'custom-field-template'), $i+1); ?></label>:<br />
<textarea name="custom_field_template_content[<?= $i ?>]" id="custom_field_template_content[<?= $i ?>]" class="textarea" rows="10" cols="60"><?= stripcslashes($options['custom_fields'][$i]['content']) ?></textarea></p>
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
<p><input type="submit" name="custom_field_template_set_options_submit" value="<?php _e('Update Options &raquo;', 'custom-field-template'); ?>" /></p>
</td></tr>
</tbody>
</table>
</form>

<h3><?php _e('Reset Options', 'custom-field-template'); ?></h3>
<form method="post" onsubmit="return confirm('<?php _e('Are you sure to reset options? Options you set will be reset to the default settings.', 'custom-field-template'); ?>');">
<table class="form-table">
<tbody>
<tr><td>
<p><input type="submit" name="custom_field_template_unset_options_submit" value="<?php _e('Unset Options &raquo;', 'custom-field-template'); ?>" /></p>
</td></tr>
</tbody>
</table>
</form>

<h3><?php _e('Delete Options', 'custom-field-template'); ?></h3>
<form method="post" onsubmit="return confirm('<?php _e('Are you sure to delete options? Options you set will be deleted.', 'custom-field-template'); ?>');">
<table class="form-table">
<tbody>
<tr><td>
<p><input type="submit" name="custom_field_template_delete_options_submit" value="<?php _e('Delete Options &raquo;', 'custom-field-template'); ?>" /></p>
</td></tr>
</tbody>
</table>
</form>

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
	
	function make_textfield( $name, $sid, $size = 25, $hideKey ) {
		$title = $name;
		$name = $this->sanitize_name( $name );
		
		if( isset( $_REQUEST[ 'post' ] ) ) {
			$value = get_post_meta( $_REQUEST[ 'post' ], $title );
			if ( $value ) {
				$value = $value[ $sid ];
			}
		}
		
		if( $hideKey == true ) $hide = ' style="visibility: hidden;"';
		
		$out .= 
			'<tr>' .
			'<th scope="row"' . $hide . '>' . $title . ' </th>' .
			'<td> <input id="' . $name . '" name="' . $name . '[]" value="' . attribute_escape($value) . '" type="textfield" size="' . $size . '" /></td>' .
			'</tr>';
		return $out;
	}
	
	function make_checkbox( $name, $sid, $value, $checked, $hideKey ) {
		$title = $name;
		$name = $this->sanitize_name( $name );
		
		if ( !$value ) $value = "true";

		if( isset( $_REQUEST[ 'post' ] ) && $_REQUEST[ 'post' ] > 0 ) {
			$selected = get_post_meta( $_REQUEST[ 'post' ], $title );
			if ( $selected ) {
 				if ( in_array($value, $selected) ) $checked = 'checked="checked"';
			}
		}
		else {
			if( $checked == true )  $checked = 'checked="checked"';
		}
		
		if( $hideKey == true ) $hide = ' style="visibility: hidden;"';
		
		$out .= 
			'<tr>' .
			'<th scope="row" valign="top"' . $hide . '>' . $title . ' </th>' .
			'<td>';
			
		$out .=	'<label for="' . $id . '" class="selectit"><input name="' . $name . '[' . $sid . ']" value="' . $value . '" ' . $checked . ' type="checkbox" /> ' . $value . '</label><br>';

		$out .= '</td>';
		
		return $out;
	}
	
	function make_radio( $name, $sid, $values, $default, $hideKey ) {
		$title = $name;
		$name = $this->sanitize_name( $name );
		
		if( isset( $_REQUEST[ 'post' ] ) && $_REQUEST[ 'post' ] > 0 ) {
			$selected = get_post_meta( $_REQUEST[ 'post' ], $title );
			$selected = $selected[ $sid ];
		}
		else {
			$selected = $default;
		}
			
		if( $hideKey == true ) $hide = ' style="visibility: hidden;"';
		
		$out .= 
			'<tr>' .
			'<th scope="row" valign="top"' . $hide . '>' . $title . ' </th>' .
			'<td>';
		
		foreach( $values as $val ) {
			$id = $name . '_' . $this->sanitize_name( $val );
			
			$checked = ( trim( $val ) == trim( $selected ) ) ? 'checked="checked"' : '';
			
			$out .=	
				'<label for="' . $id . '" class="selectit"><input id="' . $id . '" name="' . $name . '[' . $sid . ']" value="' . $val . '" ' . $checked . ' type="radio" /> ' . $val . '</label><br>';
		}	 
		$out .= '</td>';
		
		return $out;			
	}
	
	function make_select( $name, $sid, $values, $default, $hideKey ) {
		$title = $name;
		$name = $this->sanitize_name( $name );
		
		if( isset( $_REQUEST[ 'post' ] ) && $_REQUEST[ 'post' ] > 0 ) {
			$selected = get_post_meta( $_REQUEST[ 'post' ], $title );
			if ( $selected ) {
				$selected = $selected[ $sid ];
			}
		}
		else {
			$selected = $default;
		}
		
		if( $hideKey == true ) $hide = ' style="visibility: hidden;"';
		
		$out .= 
			'<tr>' .
			'<th scope="row" valign="top"' . $hide . '>' . $title . ' </th>' .
			'<td>' .
			'<select name="' . $name . '[]">' .
			'<option value="" >Select</option>';
			
		foreach( $values as $val ) {
			$checked = ( trim( $val ) == trim( $selected ) ) ? 'selected="selected"' : '';
		
			$out .=
				'<option value="' . $val . '" ' . $checked . ' > ' . $val. '</option>'; 
		}
		$out .= '</select></td>';
		
		return $out;
	}
	
	function make_textarea( $name, $sid, $rows, $cols, $tinyMCE, $mediaButton, $hideKey ) {
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
					'if ( typeof tinyMCE != "undefined" )' . "\n" .
				'jQuery(document).ready(function() {tinyMCE.execCommand("mceAddControl", false, "'. $name . $rand . '"); tinyMCEID.push("'. $name . $rand . '");});' . "\n" .
					'// ]]>' . "\n" .
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
			$swicth .= '</div>';
		
		}
		
		if( $hideKey == true ) $hide = ' style="visibility: hidden;"';
		
		$out .= 
			'<tr>' .
			'<th scope="row" valign="top"><span' . $hide . '>' . $title . '</span><br />' . $media . $switch . '</th>' .
			'<td><textarea id="' . $name . $rand . '" name="' . $name . '[' . $sid . ']" type="textfield" rows="' .$rows. '" cols="' . $cols . '" style="color:#000000">' . attribute_escape($value) . '</textarea></td>' .
			'</tr>';
		return $out;
	}

	function load_custom_field( $id = 0 ) {
		
		$fields = $this->get_custom_fields( $id );
		
		if( $fields == null)
			return;

		$out .= '<input type="hidden" name="custom-field-template-id" id="custom-field-template-id" value="' . $id . '" />';
		$out .= '<table class="editform" style="width:100%;">';
		foreach( $fields as $title => $data ) {
			for($i = 0; $i<count($data); $i++) {
				if( $data[$i][ 'type' ] == 'textfield' ) {
					$out .= $this->make_textfield( $title, $i, $data[$i][ 'size' ], $data[$i][ 'hideKey' ] );
				}
				else if( $data[$i][ 'type' ] == 'checkbox' ) {
					$out .= 
						$this->make_checkbox( $title, $i, $data[$i][ 'value' ], $data[$i][ 'checked' ], $data[$i][ 'hideKey' ] );
				}
				else if( $data[$i][ 'type' ] == 'radio' ) {
					$out .= 
						$this->make_radio( 
							$title, $i, explode( '#', $data[$i][ 'value' ] ), $data[$i][ 'default' ], $data[$i][ 'hideKey' ] );
				}
				else if( $data[$i][ 'type' ] == 'select' ) {
					$out .= 
						$this->make_select( 
							$title, $i, explode( '#', $data[$i][ 'value' ] ), $data[$i][ 'default' ], $data[$i][ 'hideKey' ] );
				}
				else if( $data[$i][ 'type' ] == 'textarea' ) {
					$out .= 
						$this->make_textarea( $title, $i, $data[$i][ 'rows' ], $data[$i][ 'cols' ], $data[$i][ 'tinyMCE' ], $data[$i][ 'mediaButton' ], $data[$i][ 'hideKey' ] );
				}
			}
		}
		
		$out .= '</table>';
	
		return $out;
	}

	function insert_custom_field() {
		global $wp_version;
		$options = $this->get_custom_field_template_data();
		
		if( $options == null)
			return;

		if ( substr($wp_version, 0, 3) >= '2.5' ) {
			$out .= '
<div id="postaiosp" class="postbox">
<h3>' . __('Custom Field Template', 'custom-field-template') . '</h3>
<div class="inside">
<div id="postaiosp">';
		} else {
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
		$out .=		'jQuery(document).ready(function() {' . "\n" .
					'	if(wpTinyMCEConfig) if(wpTinyMCEConfig.defaultEditor == "html") { jQuery("#edButtonPreview").trigger("click"); }' . "\n" .
					'});' . "\n";
									break;
								}
							}
						}
					}			
					
		$out .=		'var tinyMCEID = new Array();' . "\n" .
					'// ]]>' . "\n" .
					'</script>';
			
		$body = $this->load_custom_field();
		$out .= '<select id="custom_field_template_select" onchange="if(tinyMCEID.length) { for(i=0;i<tinyMCEID.length;i++) {tinyMCE.execCommand(\'mceRemoveControl\', false, tinyMCEID[i]);} tinyMCEID = new Array();};jQuery.ajax({type: \'GET\', url: \'?page=custom-field-template/custom-field-template.php&id=\'+jQuery(this).val()+\'&post=\'+jQuery(\'#post_ID\').val(), success: function(html) {jQuery(\'#custom-field-template-box\').html(html);}});">';
		for ( $i=0; $i < count($options['custom_fields']); $i++ ) {
			if ( $i == $options['posts'][$_REQUEST['post']] ) {
				$out .= '<option value="' . $i . '" selected="selected">' . stripcslashes($options['custom_fields'][$i]['title']) . '</option>';
				$body = $this->load_custom_field($i);
			} else
				$out .= '<option value="' . $i . '">' . stripcslashes($options['custom_fields'][$i]['title']) . '</option>';
		}
		$out .= '</select>';

		$out .= '<input type="hidden" name="custom-field-template-verify-key" id="custom-field-template-verify-key" value="' . wp_create_nonce('custom-field-template') . '" />';
		$out .= '<div id="custom-field-template-box">';
		
		$out .= $body;
		
		$out .= '</div>';
			
		if ( substr($wp_version, 0, 3) >= '2.5' ) {
			$out .= '</div></div></div>';
		} else {
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
			$title = $wpdb->escape(stripslashes(trim($title)));
			delete_post_meta($id, $title);
		}
				
		foreach( $fields as $title	=> $data) {
			for($i = 0; $i<count($data); $i++) {
				$name = $this->sanitize_name( $title );
				$title = $wpdb->escape(stripslashes(trim($title)));
			
				$meta_value = stripslashes(trim($_REQUEST[ "$name" ][$i]));
				if( isset( $meta_value ) && !empty( $meta_value ) ) {
				
					/*if( $data[$i][ 'type' ] == 'textfield' || 
							$data[$i][ 'type' ] == 'radio'	||
							$data[$i][ 'type' ] == 'select' || 
							$data[$i][ 'type' ] == 'textarea' ) {*/
						add_post_meta( $id, $title, $meta_value );
					/*}
					else if( $data[$i][ 'type' ] == 'checkbox' )
						add_post_meta( $id, $title, 'true' );*/
				}
			}
		}
		
		$options['posts'][$_REQUEST['post_ID']] = $_REQUEST['custom-field-template-id'];
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
					$Value["VALUE"] = trim(substr($Temp,$Pos+1),' "');
					
					if ($ProcessSections) {
						$Data[$Section][$id][$Value["NAME"]] = $Value["VALUE"];
					}
					else {
						$Data[$Value["NAME"]] = $Value["VALUE"];
					}
					break;
				}
			} while ($Temp = strtok("\r\n"));
		}
		return $Data;
	}

}

$custom_field_template = new custom_field_template();
?>