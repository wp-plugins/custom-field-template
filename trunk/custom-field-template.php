<?php
/*
Plugin Name: Custom Field Template
Plugin URI: http://wordpressgogo.com/development/custom-field-template.html
Description: This plugin adds the default custom fields on the Write Post/Page.
Author: Hiroaki Miyashita
Version: 1.2.4
Author URI: http://wordpressgogo.com/
*/

/*
This program is based on the rc:custom_field_gui plugin written by Joshua Sigar.
I appreciate your efforts, Joshua.
*/

class custom_field_template {
	var $is_excerpt;

	function custom_field_template() {
		add_action( 'init', array(&$this, 'custom_field_template_init') );
		add_action( 'admin_menu', array(&$this, 'custom_field_template_admin_menu') );
		add_action( 'admin_print_scripts', array(&$this, 'custom_field_template_admin_scripts') );
		add_action( 'admin_head', array(&$this, 'custom_field_template_admin_head'), 100 );
		
		//add_action( 'edit_post', array(&$this, 'edit_meta_value'), 100 );
		add_action( 'save_post', array(&$this, 'edit_meta_value'), 100 );
		//add_action( 'publish_post', array(&$this, 'edit_meta_value'), 100 );

		add_action( 'delete_post', array(&$this, 'custom_field_template_delete_post'), 100 );
		
		add_filter( 'media_send_to_editor', array(&$this, 'media_send_to_custom_field'), 15 );
		add_filter( 'plugin_action_links', array(&$this, 'wpaq_filter_plugin_actions'), 10, 2 );
		
		add_filter( 'get_the_excerpt', array(&$this, 'custom_field_template_get_the_excerpt'), 1 );
		add_filter( 'the_content', array(&$this, 'custom_field_template_the_content') );
		add_filter( 'the_content_rss', array(&$this, 'custom_field_template_the_content') );
		
		if ( $_REQUEST['cftsearch_submit'] ) :
			if ( $_REQUEST['limit'] )
				add_action( 'post_limits', array(&$this, 'custom_field_template_post_limits'));
			add_filter( 'posts_join', array(&$this, 'custom_field_template_posts_join') );
			add_filter( 'posts_where', array(&$this, 'custom_field_template_posts_where') );
			add_filter( 'posts_orderby',  array(&$this, 'custom_field_template_posts_orderby' ) );
		endif;
		
		if ( function_exists('add_shortcode') ) :
			add_shortcode( 'cft', array(&$this, 'output_custom_field_values') );
			add_shortcode( 'cftsearch', array(&$this, 'search_custom_field_values') );
		endif;
	}
	
	function custom_field_template_init() {
		global $wp_version;
		$options = $this->get_custom_field_template_data();

		if ( function_exists('load_plugin_textdomain') ) {
			if ( !defined('WP_PLUGIN_DIR') ) {
				load_plugin_textdomain('custom-field-template', str_replace( ABSPATH, '', dirname(__FILE__) ) );
			} else {
				load_plugin_textdomain('custom-field-template', false, dirname( plugin_basename(__FILE__) ) );
			}
		}
		
		if ( is_user_logged_in() && isset($_REQUEST['post']) && $_REQUEST['page'] == 'custom-field-template/custom-field-template.php' && $_REQUEST['cft_mode'] == 'selectbox' ) {
			echo $this->custom_field_template_selectbox();
			exit();
		}
		
		if ( is_user_logged_in() && isset($_REQUEST['post']) && $_REQUEST['page'] == 'custom-field-template/custom-field-template.php' && $_REQUEST['cft_mode'] == 'ajaxsave' ) {
			if ( $_REQUEST['post'] > 0 )
				$this->edit_meta_value( $_REQUEST['post'] );
			exit();
		}
		
		if ( is_user_logged_in() && $_REQUEST['page'] == 'custom-field-template/custom-field-template.php' && $_REQUEST['cft_mode'] == 'ajaxload') {
			if ( isset($_REQUEST['id']) )
				$id = $_REQUEST['id'];			
			elseif ( isset($options['posts'][$_REQUEST['post']]) )
				$id = $options['posts'][$_REQUEST['post']];
			else
				$id = 0;
			echo $this->load_custom_field( $id );
			exit();
		}
		
		if( strstr($_SERVER['REQUEST_URI'], 'wp-admin/plugins.php') && ((isset($_GET['activate']) && $_GET['activate'] == 'true') || (isset($_GET['activate-multi']) && $_GET['activate-multi'] == 'true') ) ) {
			$options = $this->get_custom_field_template_data();
			if( !$options ) {
				$this->install_custom_field_template_data();
				$this->install_custom_field_template_css();
			}
		}
		
		if ( function_exists('current_user_can') && current_user_can('edit_plugins') ) :
			if ( $_POST['custom_field_template_export_options_submit'] ) :
				$filename = "cft".date('Ymd');
				header("Accept-Ranges: none");
				header("Content-Disposition: attachment; filename=$filename");
				header('Content-Type: application/octet-stream');
				echo maybe_serialize($options);
				exit();
			endif;
		endif;
				
		if ( $options['custom_field_template_widget_shortcode'] )
			add_filter('widget_text', 'do_shortcode');
		
		if ( substr($wp_version, 0, 3) >= '2.7' ) {
			add_action( 'manage_posts_custom_column', array(&$this, 'add_manage_posts_custom_column'), 10, 2 );
			add_filter( 'manage_posts_columns', array(&$this, 'add_manage_posts_columns') );
			add_action( 'manage_pages_custom_column', array(&$this, 'add_manage_posts_custom_column'), 10, 2 );
			add_filter( 'manage_pages_columns', array(&$this, 'add_manage_pages_columns') );
			if ( empty($options['custom_field_template_disable_quick_edit']) )
				add_action( 'quick_edit_custom_box', array(&$this, 'add_quick_edit_custom_box'), 10, 2 );
		}
		
		if ( substr($wp_version, 0, 3) < '2.5' ) {
			add_action( 'simple_edit_form', array(&$this, 'insert_custom_field'), 1 );
			add_action( 'edit_form_advanced', array(&$this, 'insert_custom_field'), 1 );
			add_action( 'edit_page_form', array(&$this, 'insert_custom_field'), 1 );
		} else {
			require_once(ABSPATH . 'wp-admin/includes/template.php');
			add_meta_box('cftdiv', __('Custom Field Template', 'custom-field-template'), array(&$this, 'insert_custom_field'), 'post', 'normal', 'core');
			add_meta_box('cftdiv', __('Custom Field Template', 'custom-field-template'), array(&$this, 'insert_custom_field'), 'page', 'normal', 'core');
			if ( function_exists('remove_meta_box') && $options['custom_field_template_disable_default_custom_fields'] ) :
				remove_meta_box('postcustom', 'post', 'normal');
				remove_meta_box('pagecustomdiv', 'page', 'normal');
			endif;
		}

	}
	
	function add_quick_edit_custom_box($column_name, $type) {
		if( $column_name == 'custom-fields' ) :
			global $wp_version;
			$options = $this->get_custom_field_template_data();
		
			if( $options == null)
				return;

			if ( !$options['css'] ) {
				$this->install_custom_field_template_css();
				$options = $this->get_custom_field_template_data();
			}
					
			$out .= '<fieldset style="clear:both;">' . "\n";
			$out .= '<div class="inline-edit-group">';
			$out .=	'<style type="text/css">' . "\n" .
					'<!--' . "\n";
			$out .=	$options['css'] . "\n";
			$out .=	'-->' . "\n" .
					'</style>';
		
			if ( count($options['custom_fields'])>1 ) {
				$out .= '<select id="custom_field_template_select">';
				for ( $i=0; $i < count($options['custom_fields']); $i++ ) {
					if ( $i == $options['posts'][$_REQUEST['post']] ) {
						$out .= '<option value="' . $i . '" selected="selected">' . stripcslashes($options['custom_fields'][$i]['title']) . '</option>';
					} else
						$out .= '<option value="' . $i . '">' . stripcslashes($options['custom_fields'][$i]['title']) . '</option>';
				}
				$out .= '</select>';
				$out .= '<input type="button" class="button" value="' . __('Load', 'custom-field-template') . '" onclick="var post = jQuery(this).parent().parent().parent().parent().attr(\'id\').replace(\'edit-\',\'\'); var cftloading_select = function() {jQuery.ajax({type: \'GET\', url: \'?page=custom-field-template/custom-field-template.php&cft_mode=ajaxload&id=\'+jQuery(\'#custom_field_template_select\').val()+\'&post=\'+post, success: function(html) {jQuery(\'#cft\').html(html);}});};cftloading_select(post);" />';
			}
		
			$out .= '<input type="hidden" name="custom-field-template-verify-key" id="custom-field-template-verify-key" value="' . wp_create_nonce('custom-field-template') . '" />';
			$out .= '<div id="cft">';
			$out .= '</div>';

			$out .= '</div>' . "\n";
			$out .= '</fieldset>' . "\n";
		
			echo $out;
		endif;
	}
	
	function custom_field_template_admin_head() {
		global $wp_version;

		if ( substr($wp_version, 0, 3) >= '2.7' && is_user_logged_in() && ( strstr($_SERVER['REQUEST_URI'], 'wp-admin/edit.php') || strstr($_SERVER['REQUEST_URI'], 'wp-admin/edit-pages.php') ) && !strstr($_SERVER['REQUEST_URI'], 'page=') ) {
?>
<script type="text/javascript">
// <![CDATA[
	jQuery(document).ready(function() {
		jQuery('.hide-if-no-js-cft').show();
		jQuery('.hide-if-js-cft').hide();
		
		inlineEditPost.addEvents = function(r) {
			r.each(function() {
				var row = jQuery(this);
				jQuery('a.editinline', row).click(function() {
					inlineEditPost.edit(this);
					post_id = jQuery(this).parent().parent().parent().parent().attr('id').replace('post-','');
					inlineEditPost.cft_load(post_id);
					return false;
				});
			});
		}
		
		inlineEditPost.save = function(id) {
			if( typeof(id) == 'object' )
				id = this.getId(id);

			jQuery('table.widefat .inline-edit-save .waiting').show();

			var params = {
				action: 'inline-save',
				post_type: this.type,
				post_ID: id,
				edit_date: 'true'
			};

			var fields = jQuery('#edit-'+id+' :input').fieldSerialize();
			params = fields + '&' + jQuery.param(params);

			// make ajax request
			jQuery.post('admin-ajax.php', params,
				function(r) {
					jQuery('table.widefat .inline-edit-save .waiting').hide();

					if (r) {
						if ( -1 != r.indexOf('<tr') ) {
							jQuery(inlineEditPost.what+id).remove();
							jQuery('#edit-'+id).before(r).remove();

							var row = jQuery(inlineEditPost.what+id);
							row.hide();

							if ( 'draft' == jQuery('input[name="post_status"]').val() )
								row.find('td.column-comments').hide();

							row.find('.hide-if-no-js').removeClass('hide-if-no-js');
							jQuery('.hide-if-no-js-cft').show();
							jQuery('.hide-if-js-cft').hide();

							inlineEditPost.addEvents(row);
							row.fadeIn();
						} else {
							r = r.replace( /<.[^<>]*?>/g, '' );
							jQuery('#edit-'+id+' .inline-edit-save').append('<span class="error">'+r+'</span>');
						}
					} else {
						jQuery('#edit-'+id+' .inline-edit-save').append('<span class="error">'+inlineEditL10n.error+'</span>');
					}
				}
			, 'html');
			return false;
		}
		
		jQuery('.editinline').click(function () {post_id = jQuery(this).parent().parent().parent().parent().attr('id').replace('post-',''); inlineEditPost.cft_load(post_id);});
		inlineEditPost.cft_load = function (post_id) {
			jQuery.ajax({type: 'GET', url: '?page=custom-field-template/custom-field-template.php&cft_mode=ajaxload&post='+post_id, success: function(html) {jQuery('#cft').html(html);}});
		};
	});
//-->
</script>
<style type="text/css">
<!--
	div.cft_list p.key		{ font-weight:bold; margin: 0; }
	div.cft_list p.value	{ margin: 0 0 0 10px; }
	.cft-actions			{ visibility: hidden; padding: 2px 0 0; }
	tr:hover .cft-actions	{ visibility: visible; }
	.inline-edit-row fieldset label { display:inline; }
-->
</style>
<?php
		}
	}
	
	function add_manage_posts_custom_column($column_name, $post_id) {
		$data = get_post_custom($post_id);
		
		if( is_array($data) && $column_name == 'custom-fields' ) :
			$flag = 0;
			foreach($data as $key => $val) :
				if ( substr($key, 0, 1) == '_' || !$val[0] ) continue;
				$content .= '<p class="key">' . $key . '</p>' . "\n";
				foreach($val as $val2) :
					$val2 = htmlspecialchars($val2, ENT_QUOTES);
					if ( $flag ) :
						$content .= '<p class="value">' . $val2 . '</p>' . "\n";
					else :
						if ( function_exists( mb_strlen ) ) :
							if ( mb_strlen($val2) > 50 ) :
								$before_content = mb_substr($val2, 0, 50);
								$after_content  = mb_substr($val2, 50);
								$content .= '<p class="value">' . $before_content . '[[[break]]]' . '<p class="value">' . $after_content . '</p>' . "\n";
								$flag = 1;
							else :
								$content .= '<p class="value">' . $val2 . '</p>' . "\n";
							endif;
						else :
							if ( strlen($val2) > 50 ) :
								$before_content = substr($val2, 0, 50);
								$after_content  = substr($val2, 50);
								$content .= '<p class="value">' . $before_content . '[[[break]]]' . '<p class="value">' . $after_content . '</p>' . "\n";
								$flag = 1;
							else :
								$content .= '<p class="value">' . $val2 . '</p>' . "\n";
							endif;
						endif;
					endif;
				endforeach;
			endforeach;
			if ( $content ) :
				$content = preg_replace('/([^\n]+)\n([^\n]+)\n([^\n]+)\n([^\n]+)\n([^$]+)/', '\1\2\3\4[[[break]]]\5', $content);
				list($before, $after) = explode('[[[break]]]', $content, 2);
				$after = preg_replace('/\[\[\[break\]\]\]/', '', $after);
				$output .= '<div class="cft_list">';
				$output .= balanceTags($before, true);
				if ( $after ) :
					$output .= '<span class="hide-if-no-js-cft"><a href="javascript:void(0);" onclick="jQuery(this).parent().next().show(); jQuery(this).parent().next().next().show(); jQuery(this).parent().hide();">... ' . __('read more', 'custom-field-template') . '</a></span>';
					$output .= '<span class="hide-if-js-cft">' . balanceTags($after, true) . '</span>';
					$output .= '<span style="display:none;"><a href="javascript:void(0);" onclick="jQuery(this).parent().prev().hide(); jQuery(this).parent().prev().prev().show(); jQuery(this).parent().hide();">[^]</a></span>';
				endif;
				$output .= '</div>';
			else :
				$output .= '&nbsp;';
			endif;
		endif;
		
		echo $output;
	}
	
	function add_manage_posts_columns($columns) {
		$new_columns = array();
		foreach($columns as $key => $val) :
			$new_columns[$key] = $val;
			if ( $key == 'tags' )
				$new_columns['custom-fields'] = __('Custom Fields', 'custom-field-template');
		endforeach;
		return $new_columns;
	}
	
	function add_manage_pages_columns($columns) {
		$new_columns = array();
		foreach($columns as $key => $val) :
			$new_columns[$key] = $val;
			if ( $key == 'author' )
				$new_columns['custom-fields'] = __('Custom Fields', 'custom-field-template');
		endforeach;
		return $new_columns;
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
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-form' );
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
		$options['shortcode_format'][0] = '<table class="cft">
<tbody>
<tr>
<th>Plan</th><td colspan="3">[Plan]</td>
</tr>
<tr>
<th>Favorite Fruits</th><td>[Favorite Fruits]</td>
<th>Miles Walked</th><td>[Miles Walked]</td>
</tr>
<tr>
<th>Temper Level</th><td colspan="3">[Temper Level]</td>
</tr>
<tr>
<th>Hidden Thought</th><td colspan="3">[Hidden Thought]</td>
</tr>
</tbody>
</table>';
		update_option('custom_field_template_data', $options);
	}
	
	function install_custom_field_template_css() {
		$options = get_option('custom_field_template_data');
		$options['css'] = '#cft dl { clear:both; margin:0; padding:0; width:100%; }
#cft dt { float:left; font-weight:bold; margin:0; text-align:center; width:20%; }
#cft dt .hideKey { visibility:hidden; }
#cft dd { float:left; margin:0; text-align:left; width:80%; }
#cft dd p.label { font-weight:bold; margin:0; }
#cft_instruction { margin:10px; }
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
	
	
	function custom_field_template_get_the_excerpt($excerpt) {
		$this->is_excerpt = true;
		return $excerpt;
	}
	
	function custom_field_template_the_content($content) {
		global $wp_query, $post;
		$options = $this->get_custom_field_template_data();
		
		if ( $this->is_excerpt ) :
			$this->is_excerpt = false;
			if ( empty($post->post_excerpt) ) return $post->post_content;
		endif;
		
		if ( count($options['hook']) > 0 ) :
			$categories = get_the_category();
			$cats = array();
			foreach( $categories as $val ) :
				$cats[] = $val->cat_ID;
			endforeach;
			
			if ( $options['custom_fields'][$id]['post_type'] ) :
				if ( $options['custom_fields'][$id]['post_type'] == 'post' && (strstr($_SERVER['REQUEST_URI'], 'wp-admin/page-new.php') || strstr($_SERVER['REQUEST_URI'], 'wp-admin/page.php') || strstr($_SERVER['REQUEST_URI'], 'wp-admin/edit-pages.php')) )
					return;
				if ( $options['custom_fields'][$id]['post_type'] == 'page' && (strstr($_SERVER['REQUEST_URI'], 'wp-admin/post-new.php') || strstr($_SERVER['REQUEST_URI'], 'wp-admin/post.php') || strstr($_SERVER['REQUEST_URI'], 'wp-admin/edit.php')) )
					return;
			endif;
			
			for ( $i=0; $i<count($options['hook']); $i++ ) :
				if ( is_feed() && !$options['hook'][$i]['feed'] ) break;
				if ( $options['hook'][$i]['category'] ) :
					if ( is_category() || is_single() || is_feed() ) :
						if ( $options['hook'][$i]['use_php'] ) :
							$options['hook'][$i]['content'] = stripcslashes($options['hook'][$i]['content']);
							$options['hook'][$i]['content'] = preg_replace_callback("/(<\?php|<\?|< \?php)(.*?)\?>/si", array($this, 'EvalBuffer'), $options['hook'][$i]['content']);
						endif;
						$needle = explode(',', $options['hook'][$i]['category']);
						foreach ( $needle as $val ) :
							if ( in_array($val, $cats ) ) :
								if ( $options['hook'][$i]['position'] == 0 )
									$content .= $options['hook'][$i]['content'];
								elseif ( $options['hook'][$i]['position'] == 2 )
									$content = preg_replace('/\[cfthook hook='.$i.'\]/', $options['hook'][$i]['content'], $content);
								else
									$content = $options['hook'][$i]['content'] . $content;
								break;
							endif;
						endforeach;
					endif;
				elseif ( $option['hook'][$i]['post_type']=='post' ) :
					if ( is_category() || is_single() ) :
						if ( $options['hook'][$i]['use_php'] ) :
							$options['hook'][$i]['content'] = stripcslashes($options['hook'][$i]['content']);
							$options['hook'][$i]['content'] = preg_replace_callback("/(<\?php|<\?|< \?php)(.*?)\?>/si", array($this, 'EvalBuffer'), $options['hook'][$i]['content']);
						endif;
						if ( $options['hook'][$i]['position'] == 0 )
							$content .= $options['hook'][$i]['content'];
						elseif ( $options['hook'][$i]['position'] == 2 )
							$content = preg_replace('/\[cfthook hook='.$i.'\]/', $options['hook'][$i]['content'], $content);
						else
							$content = $options['hook'][$i]['content'] . $content;
					endif;		
				elseif ( $option['hook'][$i]['post_type']=='page' ) :
					if ( is_page() ) :
						if ( $options['hook'][$i]['use_php'] ) :
							$options['hook'][$i]['content'] = stripcslashes($options['hook'][$i]['content']);
							$options['hook'][$i]['content'] = preg_replace_callback("/(<\?php|<\?|< \?php)(.*?)\?>/si", array($this, 'EvalBuffer'), $options['hook'][$i]['content']);
						endif;
						if ( $options['hook'][$i]['position'] == 0 )
							$content .= $options['hook'][$i]['content'];
						elseif ( $options['hook'][$i]['position'] == 2 )
							$content = preg_replace('/\[cfthook hook='.$i.'\]/', $options['hook'][$i]['content'], $content);
						else
							$content = $options['hook'][$i]['content'] . $content;
					endif;
				else :
					if ( $options['hook'][$i]['use_php'] ) :
						$options['hook'][$i]['content'] = stripcslashes($options['hook'][$i]['content']);
						$options['hook'][$i]['content'] = preg_replace_callback("/(<\?php|<\?|< \?php)(.*?)\?>/si", array($this, 'EvalBuffer'), $options['hook'][$i]['content']);
					endif;
					if ( $options['hook'][$i]['position'] == 0 )
						$content .= $options['hook'][$i]['content'];
					elseif ( $options['hook'][$i]['position'] == 2 )
						$content = preg_replace('/\[cfthook hook='.$i.'\]/', $options['hook'][$i]['content'], $content);
					else
						$content = $options['hook'][$i]['content'] . $content;
				endif;
			endfor;
		endif;
		
		return stripcslashes($content);
	}
	
	function custom_field_template_admin() {
		$options = $this->get_custom_field_template_data();
		if($_POST["custom_field_template_set_options_submit"]) :
			unset($options['custom_fields']);
			$j = 0;
			$options['custom_field_template_replace_keys_by_labels'] = $_POST['custom_field_template_replace_keys_by_labels'];
			$options['custom_field_template_use_multiple_insert'] = $_POST['custom_field_template_use_multiple_insert'];
			$options['custom_field_template_use_wpautop'] = $_POST['custom_field_template_use_wpautop'];
			$options['custom_field_template_use_autosave'] = $_POST['custom_field_template_use_autosave'];
			$options['custom_field_template_disable_default_custom_fields'] = $_POST['custom_field_template_disable_default_custom_fields'];
			$options['custom_field_template_disable_quick_edit'] = $_POST['custom_field_template_disable_quick_edit'];
			$options['custom_field_template_replace_the_title'] = $_POST['custom_field_template_replace_the_title'];
			$options['custom_field_template_widget_shortcode'] = $_POST['custom_field_template_widget_shortcode'];
			for($i=0;$i<count($_POST["custom_field_template_content"]);$i++) {
				if( $_POST["custom_field_template_content"][$i] ) {
					$options['custom_fields'][$j]['title']   = $_POST["custom_field_template_title"][$i];
					$options['custom_fields'][$j]['content'] = $_POST["custom_field_template_content"][$i];
					$options['custom_fields'][$j]['instruction'] = $_POST["custom_field_template_instruction"][$i];
					$options['custom_fields'][$j]['category'] = $_POST["custom_field_template_category"][$i];
					$options['custom_fields'][$j]['post_type'] = $_POST["custom_field_template_post_type"][$i];
					$j++;
				}
			}			
			update_option('custom_field_template_data', $options);
			$message = __('Options updated.', 'custom-field-template');
		elseif ($_POST['custom_field_template_css_submit']) :
			$options['css'] = $_POST['custom_field_template_css'];
			update_option('custom_field_template_data', $options);
			$message = __('Options updated.', 'custom-field-template');
		elseif ($_POST['custom_field_template_shortcode_format_submit']) :
			unset($options['shortcode_format'], $options['shortcode_format_use_php']);
			$j = 0;
			for($i=0;$i<count($_POST["custom_field_template_shortcode_format"]);$i++) {
				if( $_POST["custom_field_template_shortcode_format"][$i] ) :
					$options['shortcode_format'][$j] = $_POST["custom_field_template_shortcode_format"][$i];
					$options['shortcode_format_use_php'][$j] = $_POST["custom_field_template_shortcode_format_use_php"][$i];
					$j++;
				endif;
			}			
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
		elseif($_POST["custom_field_template_hook_submit"]) :
			unset($options['hook']);
			$j = 0;
			for($i=0;$i<count($_POST["custom_field_template_hook_content"]);$i++) {
				if( $_POST["custom_field_template_hook_content"][$i] ) {
					$options['hook'][$j]['position'] = $_POST["custom_field_template_hook_position"][$i];
					$options['hook'][$j]['content']  = $_POST["custom_field_template_hook_content"][$i];
					$options['hook'][$j]['category'] = preg_replace('/\s/', '', $_POST["custom_field_template_hook_category"][$i]);
					$options['hook'][$j]['use_php']  = $_POST["custom_field_template_hook_use_php"][$i];
					$options['hook'][$j]['feed']  = $_POST["custom_field_template_hook_feed"][$i];
					$options['hook'][$j]['post_type']  = $_POST["custom_field_template_hook_post_type"][$i];
					$j++;
				}
			}			
			update_option('custom_field_template_data', $options);
			$message = __('Options updated.', 'custom-field-template');
		elseif ($_POST['custom_field_template_rebuild_value_counts_submit']) :
			$this->custom_field_template_rebuild_value_counts();
			$options = $this->get_custom_field_template_data();
			$message = __('Value Counts rebuilt.', 'custom-field-template');
		elseif ($_POST['custom_field_template_rebuild_tags_submit']) :
			$options = $this->get_custom_field_template_data();
			$message = __('Tags rebuilt.', 'custom-field-template');
		elseif ($_POST['custom_field_template_import_options_submit']) :
			if ( is_uploaded_file($_FILES['cftfile']['tmp_name']) ) :
				ob_start();
				readfile ($_FILES['cftfile']['tmp_name']);
				$import = ob_get_contents();
				ob_end_clean();
				$import = maybe_unserialize($import);
				update_option('custom_field_template_data', $import);
				$message = __('Options imported.', 'custom-field-template');
				$options = $this->get_custom_field_template_data();
			endif;
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
<div id="icon-plugins" class="icon32"><br/></div>
<h2><?php _e('Custom Field Template', 'custom-field-template'); ?></h2>

<br class="clear"/>

<div id="poststuff" class="meta-box-sortables" style="position: relative; margin-top:10px;">
<div class="postbox">
<div class="handlediv" title="<?php _e('Click to toggle', 'custom-field-template'); ?>"><br /></div>
<h3><?php _e('Custom Field Template Options', 'custom-field-template'); ?></h3>
<div class="inside">
<form method="post">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<?php
	for ( $i = 0; $i < count($options['custom_fields'])+1; $i++ ) {
?>
<tr><td>
<p><strong>TEMPLATE #<?php echo $i; ?></strong></p>
<p><label for="custom_field_template_title[<?php echo $i; ?>]"><?php echo sprintf(__('Template Title', 'custom-field-template'), $i); ?></label>:<br />
<input type="text" name="custom_field_template_title[<?php echo $i; ?>]" id="custom_field_template_title[<?php echo $i; ?>]" value="<?php echo stripcslashes($options['custom_fields'][$i]['title']); ?>" size="80" /></p>
<p><label for="custom_field_template_instruction[<?php echo $i; ?>]"><a href="javascript:void(0);" onclick="jQuery(this).parent().next().next().toggle();"><?php echo sprintf(__('Template Instruction', 'custom-field-template'), $i); ?></a></label>:<br />
<textarea name="custom_field_template_instruction[<?php echo $i; ?>]" id="custom_field_template_instruction[<?php echo $i; ?>]" rows="5" cols="80"<?php if ( empty($options['custom_fields'][$i]['instruction']) ) : echo ' style="display:none;"'; endif; ?>><?php echo stripcslashes($options['custom_fields'][$i]['instruction']); ?></textarea></p>
<p><label for="custom_field_template_post_type[<?php echo $i; ?>]"><a href="javascript:void(0);" onclick="jQuery(this).parent().next().next().toggle();"><?php echo sprintf(__('Post Type', 'custom-field-template'), $i); ?></a></label>:<br />
<span<?php if ( empty($options['custom_fields'][$i]['post_type']) ) : echo ' style="display:none;"'; endif; ?>>
<input type="radio" name="custom_field_template_post_type[<?php echo $i; ?>]" id="custom_field_template_post_type[<?php echo $i; ?>]" value=""<?php if ( !$options['custom_fields'][$i]['post_type'] ) :  echo ' checked="checked"'; endif; ?> /> <?php _e('Both', 'custom-field-template'); ?>
<input type="radio" name="custom_field_template_post_type[<?php echo $i; ?>]" id="custom_field_template_post_type[<?php echo $i; ?>]" value="post"<?php if ( $options['custom_fields'][$i]['post_type']=='post') : echo ' checked="checked"'; endif; ?> /> <?php _e('Post', 'custom-field-template'); ?>
<input type="radio" name="custom_field_template_post_type[<?php echo $i; ?>]" id="custom_field_template_post_type[<?php echo $i; ?>]" value="page"<?php if ( $options['custom_fields'][$i]['post_type']=='page') : echo ' checked="checked"'; endif; ?> /> <?php _e('Page', 'custom-field-template'); ?></span></p>
<p><label for="custom_field_template_category[<?php echo $i; ?>]"><a href="javascript:void(0);" onclick="jQuery(this).parent().next().next().toggle();"><?php echo sprintf(__('Category ID (comma-deliminated)', 'custom-field-template'), $i); ?></a></label>:<br />
<input type="text" name="custom_field_template_category[<?php echo $i; ?>]" id="custom_field_template_category[<?php echo $i; ?>]" value="<?php echo stripcslashes($options['custom_fields'][$i]['category']); ?>" size="80"<?php if ( empty($options['custom_fields'][$i]['category']) ) : echo ' style="display:none;"'; endif; ?> /></p>
<p><label for="custom_field_template_content[<?php echo $i; ?>]"><?php echo sprintf(__('Template Content', 'custom-field-template'), $i); ?></label>:<br />
<textarea name="custom_field_template_content[<?php echo $i; ?>]" id="custom_field_template_content[<?php echo $i; ?>]" rows="10" cols="80"><?php echo stripcslashes($options['custom_fields'][$i]['content']); ?></textarea></p>
</td></tr>
<?php
	}
?>
<tr><td>
<p><label for="custom_field_template_use_multiple_insert"><?php _e('In case that you would like to insert multiple images at once in use of the custom field media buttons', 'custom-field-template'); ?></label>:<br />
<input type="checkbox" name="custom_field_template_use_multiple_insert" id="custom_field_template_use_multiple_insert" value="1" <?php if ($options['custom_field_template_use_multiple_insert']) { echo 'checked="checked"'; } ?> /> <?php _e('Use multiple image inset', 'custom-field-template'); ?><br /><span style="color:#FF0000; font-weight:bold;"><?php _e('Caution:', 'custom-field-teplate'); ?> <?php _e('You need to edit `wp-admin/includes/media.php`. Delete or comment out the code in the function media_send_to_editor.', 'custom-field-template'); ?></span></p>
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
<p><label for="custom_field_template_use_autosave"><?php _e('In case that you would like to save values automatically in switching templates', 'custom-field-template'); ?></label>:<br />
<input type="checkbox" name="custom_field_template_use_autosave" id="custom_field_template_use_autosave" value="1" <?php if ($options['custom_field_template_use_autosave']) { echo 'checked="checked"'; } ?> /> <?php _e('Use the auto save in switching templates', 'custom-field-template'); ?></p>
</td>
</tr>
<tr><td>
<p><label for="custom_field_template_disable_default_custom_fields"><?php _e('In case that you would like to forbid to use the default custom fields.', 'custom-field-template'); ?></label>:<br />
<input type="checkbox" name="custom_field_template_disable_default_custom_fields" id="custom_field_template_disable_default_custom_fields" value="1" <?php if ($options['custom_field_template_disable_default_custom_fields']) { echo 'checked="checked"'; } ?> /> <?php _e('Disable the default custom fields', 'custom-field-template'); ?></p>
</td>
</tr>
<tr><td>
<p><label for="custom_field_template_disable_quick_edit"><?php _e('In case that you would like to forbid to use the quick edit.', 'custom-field-template'); ?></label>:<br />
<input type="checkbox" name="custom_field_template_disable_quick_edit" id="custom_field_template_disable_quick_edit" value="1" <?php if ($options['custom_field_template_disable_quick_edit']) { echo 'checked="checked"'; } ?> /> <?php _e('Disable the quick edit', 'custom-field-template'); ?></p>
</td>
</tr>
<tr><td>
<p><label for="custom_field_template_replace_the_title"><?php _e('In case that you would like to replace the box title with the template title.', 'custom-field-template'); ?></label>:<br />
<input type="checkbox" name="custom_field_template_replace_the_title" id="custom_field_template_replace_the_title" value="1" <?php if ($options['custom_field_template_replace_the_title']) { echo 'checked="checked"'; } ?> /> <?php _e('Replace the box title', 'custom-field-template'); ?></p>
</td>
</tr>
<tr><td>
<p><label for="custom_field_template_widget_shortcode"><?php _e('In case that you would like to use the shortcode in the widget.', 'custom-field-template'); ?></label>:<br />
<input type="checkbox" name="custom_field_template_widget_shortcode" id="custom_field_template_widget_shortcode" value="1" <?php if ($options['custom_field_template_widget_shortcode']) { echo 'checked="checked"'; } ?> /> <?php _e('Use the shortcode in the widget', 'custom-field-template'); ?></p>
</td>
</tr>
<tr><td>
<p><input type="submit" name="custom_field_template_set_options_submit" value="<?php _e('Update Options &raquo;', 'custom-field-template'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>

<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'custom-field-template'); ?>"><br /></div>
<h3><?php _e('ADMIN CSS', 'custom-field-template'); ?></h3>
<div class="inside">
<form method="post">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr><td>
<p><textarea name="custom_field_template_css" id="custom_field_template_css" rows="10" cols="80"><?php echo stripcslashes($options['css']); ?></textarea></p>
</td></tr>
<tr><td>
<p><input type="submit" name="custom_field_template_css_submit" value="<?php _e('Update Options &raquo;', 'custom-field-template'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>

<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'custom-field-template'); ?>"><br /></div>
<h3><?php _e('[cft] and [cftsearch] Shortcode Format', 'custom-field-template'); ?></h3>
<div class="inside">
<form method="post">
<p><?php _e('For [cft], [key] will be converted into the value of [key].', 'custom-field-template'); ?><br />
<?php _e('For [cftsearch], [key] will be converted into the input field.', 'custom-field-template'); ?></p>
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<?php
	for ($i=0;$i<count($options['shortcode_format'])+1;$i++) :
?>
<tr><th><strong>FORMAT #<?php echo $i; ?></strong></th></tr>
<tr><td>
<p><textarea name="custom_field_template_shortcode_format[<?php echo $i; ?>]" rows="10" cols="80"><?php echo stripcslashes($options['shortcode_format'][$i]); ?></textarea></p>
<p><label><input type="checkbox" name="custom_field_template_shortcode_format_use_php[<?php echo $i; ?>]" value="1" <?php if ($options['shortcode_format_use_php'][$i]) { echo ' checked="checked"'; } ?> /> <?php _e('Use PHP', 'custom-field-template'); ?></label></p>
</td></tr>
<?php
	endfor;
?>
<tr><td>
<p><input type="submit" name="custom_field_template_shortcode_format_submit" value="<?php _e('Update Options &raquo;', 'custom-field-template'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>

<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'custom-field-template'); ?>"><br /></div>
<h3><?php _e('PHP CODE (Experimental Option)', 'custom-field-template'); ?></h3>
<div class="inside">
<form method="post" onsubmit="return confirm('<?php _e('Are you sure to save PHP codes? Please do it at your own risk.', 'custom-field-template'); ?>');">
<dl><dt><?php _e('For `text` and `textarea`, you must set $value as an string.', 'custom-field-template'); ?><br />
ex. `text` and `textarea`:</dt><dd>$value = 'Yes we can.';</dd></dl>
<dl><dt><?php _e('For `checkbox`, `radio`, and `select`, you must set $values as an array.', 'custom-field-template'); ?><br />
ex. `radio` and `select`:</dt><dd>$values = array('dog', 'cat', 'monkey'); $default = 'cat';</dd>
<dt>ex. `checkbox`:</dt><dd>$values = array('dog', 'cat', 'monkey'); $defaults = array('dog', 'cat');</dd></dl>
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<?php
	for ($i=0;$i<count($options['php'])+1;$i++) :
?>
<tr><th><strong>CODE #<?php echo $i; ?></strong></th></tr>
<tr><td>
<p><textarea name="custom_field_template_php[]" rows="10" cols="80"><?php echo stripcslashes($options['php'][$i]); ?></textarea></p>
</td></tr>
<?php
	endfor;
?>
<tr><td>
<p><input type="submit" name="custom_field_template_php_submit" value="<?php _e('Update Options &raquo;', 'custom-field-template'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>

<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'custom-field-template'); ?>"><br /></div>
<h3><?php _e('Auto Hook of `the_content()` (Experimental Option)', 'custom-field-template'); ?></h3>
<div class="inside">
<form method="post">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<?php
	for ($i=0;$i<count($options['hook'])+1;$i++) :
?>
<tr><th><strong>HOOK #<?php echo $i; ?></strong></th></tr>
<tr><td>
<p><label for="custom_field_template_hook_position[<?php echo $i; ?>]"><?php echo sprintf(__('Position', 'custom-field-template'), $i); ?></label>:<br />
<input type="radio" name="custom_field_template_hook_position[<?php echo $i; ?>]" value="1" <?php if($options['hook'][$i]['position']==1) echo ' checked="checked"'; ?> /> <?php _e('Before the conetnt', 'custom-field-template'); ?>
<input type="radio" name="custom_field_template_hook_position[<?php echo $i; ?>]" value="0" <?php if($options['hook'][$i]['position']==0) echo ' checked="checked"'; ?> /> <?php _e('After the conetnt', 'custom-field-template'); ?>
<input type="radio" name="custom_field_template_hook_position[<?php echo $i; ?>]" value="2" <?php if($options['hook'][$i]['position']==2) echo ' checked="checked"'; ?> /> <?php echo sprintf(__('Inside the conetnt ([cfthook hook=%d])', 'custom-field-template'), $i); ?>
</p>
<p><label for="custom_field_template_hook_post_type[<?php echo $i; ?>]"><?php echo sprintf(__('Post Type', 'custom-field-template'), $i); ?></label>:<br />
<input type="radio" name="custom_field_template_hook_post_type[<?php echo $i; ?>]" id="custom_field_template_hook_post_type[<?php echo $i; ?>]" value=""<?php if ( !$options['hook'][$i]['post_type'] ) :  echo ' checked="checked"'; endif; ?> /> <?php _e('Both', 'custom-field-template'); ?>
<input type="radio" name="custom_field_template_hook_post_type[<?php echo $i; ?>]" id="custom_field_template_hook_post_type[<?php echo $i; ?>]" value="post"<?php if ( $options['hook'][$i]['post_type']=='post') : echo ' checked="checked"'; endif; ?> /> <?php _e('Post', 'custom-field-template'); ?>
<input type="radio" name="custom_field_template_hook_post_type[<?php echo $i; ?>]" id="custom_field_template_hook_post_type[<?php echo $i; ?>]" value="page"<?php if ( $options['hook'][$i]['post_type']=='page') : echo ' checked="checked"'; endif; ?> /> <?php _e('Page', 'custom-field-template'); ?></p>
<p><label for="custom_field_template_hook_category[<?php echo $i; ?>]"><?php echo sprintf(__('Category ID (comma-deliminated)', 'custom-field-template'), $i); ?></label>:<br />
<input type="text" name="custom_field_template_hook_category[<?php echo $i; ?>]" id="custom_field_template_hook_category[<?php echo $i; ?>]" value="<?php echo stripcslashes($options['hook'][$i]['category']); ?>" size="80" /></p>
<p><label for="custom_field_template_hook_content[<?php echo $i; ?>]"><?php echo sprintf(__('Content', 'custom-field-template'), $i); ?></label>:<br /><textarea name="custom_field_template_hook_content[<?php echo $i; ?>]" rows="5" cols="80"><?php echo stripcslashes($options['hook'][$i]['content']); ?></textarea></p>
<p><input type="checkbox" name="custom_field_template_hook_use_php[<?php echo $i; ?>]" id="custom_field_template_hook_use_php[<?php echo $i; ?>]" value="1" <?php if ($options['hook'][$i]['use_php']) { echo ' checked="checked"'; } ?> /> <?php _e('Use PHP', 'custom-field-template'); ?></p>
<p><input type="checkbox" name="custom_field_template_hook_feed[<?php echo $i; ?>]" id="custom_field_template_hook_feed[<?php echo $i; ?>]" value="1" <?php if ($options['hook'][$i]['feed']) { echo ' checked="checked"'; } ?> /> <?php _e('Apply to feeds', 'custom-field-template'); ?></p>
</td></tr>
<?php
	endfor;
?>
<tr><td>
<p><input type="submit" name="custom_field_template_hook_submit" value="<?php _e('Update Options &raquo;', 'custom-field-template'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>

<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'custom-field-template'); ?>"><br /></div>
<h3><?php _e('Rebuild Value Counts', 'custom-field-template'); ?></h3>
<div class="inside">
<form method="post" onsubmit="return confirm('<?php _e('Are you sure to rebuild all value counts?', 'custom-field-template'); ?>');">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr><td>
<p><?php _e('Value Counts are used for temporarily saving how many values in each key. Set `valueCount = true` into fields.', 'custom-field-template'); ?></p>
<p>global $custom_field_template;<br />
$value_count = $custom_field_template->get_value_count();<br />
echo $value_count[$meta_key][$meta_value];</p>
<p><input type="submit" name="custom_field_template_rebuild_value_counts_submit" value="<?php _e('Rebuild Value Counts &raquo;', 'custom-field-template'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>

<!--
<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'custom-field-template'); ?>"><br /></div>
<h3><?php _e('Rebuild Tags', 'custom-field-template'); ?></h3>
<div class="inside">
<form method="post" onsubmit="return confirm('<?php _e('Are you sure to rebuild tags?', 'custom-field-template'); ?>');">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr><td>
<p><input type="submit" name="custom_field_template_rebuild_tags_submit" value="<?php _e('Rebuild Tags &raquo;', 'custom-field-template'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>
//-->

<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'custom-field-template'); ?>"><br /></div>
<h3><?php _e('Option List', 'custom-field-template'); ?></h3>
<div class="inside">
ex.<br />
[Plan]<br />
type = textfield<br />
size = 35<br />
hideKey = true<br />

<table class="widefat" style="margin:10px 0 5px 0;">
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
<th>label</th><td>label = ABC</td><td>label = DEF</td><td>label = GHI</td><td>label = JKL</td><td>label = MNO</td>
</tr>
<tr>
<th>size</th><td>size = 30</td><td></td><td></td><td></td><td></td>
</tr>
<tr>
<th>value</th><td></td><td>value = apple # orange # banana</td><td>value = apple # orange # banana</td><td>value = apple # orange # banana</td>
<td></td>
</tr>
<tr>
<th>valueLabel</th><td></td><td>valueLabel = apples # oranges # bananas</td><td>valueLabel = apples # oranges # bananas</td><td>valueLabel = apples # oranges # bananas</td>
<td></td>
</tr>
<tr>
<th>default</th><td>default = orange</td><td>default = orange # banana</td><td>default = orange</td><td>default = orange</td><td>default = orange</td>
</tr>
<tr>
<th>clearButton</th><td></td><td></td><td>clearButton = true</td><td></td><td></td>
</tr>
<tr>
<th>selectLabel</th><td></td><td></td><td></td><td>selectLabel = Select a fruit</td><td></td>
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
<th>htmlEditor</th><td></td><td></td><td></td><td></td><td>htmlEditor = true</td>
</tr>
<tr>
<th>mediaButton</th><td></td><td></td><td></td><td></td><td>mediaButton = true</td>
</tr>
<tr>
<th>code</th><td>code = 0</td><td>code = 0</td><td>code = 0</td><td>code = 0</td><td>code = 0</td>
</tr>
<tr>
<th>editCode</th><td>editCode = 0</td><td>editCode = 0</td><td>editCode = 0</td><td>editCode = 0</td><td>editCode = 0</td>
</tr>
<tr>
<th>level</th><td>level = 1</td><td>level = 3</td><td>level = 5</td><td>level = 7</td><td>level = 9</td>
</tr>
<tr>
<th>insertTag</th><td>insertTag = true</td><td>insertTag = true</td><td>insertTag = true</td><td>insertTag = true</td><td>insertTag = true</td>
</tr>
<tr>
<th>output</th><td>output = true</td><td>output = true</td><td>output = true</td><td>output = true</td><td>output = true</td>
</tr>
<tr>
<th>outputCode</th><td>outputCode = 0</td><td>outputCode = 0</td><td>outputCode = 0</td><td>outputCode = 0</td><td>outputCode = 0</td>
</tr>
<tr>
<th>multiple</th><td>multiple = true</td><td></td><td>multiple = true</td><td>multiple = true</td><td>multiple = true</td>
</tr>
<tr>
<th>startNum</th><td>startNum = 5</td><td></td><td>startNum = 5</td><td>startNum = 5</td><td>startNum = 5</td>
</tr>
<tr>
<th>endNum</th><td>endNum = 10</td><td></td><td>endNum = 10</td><td>endNum = 10</td><td>endNum = 10</td>
</tr>
<tr>
<th>multipleButton</th><td>multipleButton = true</td><td></td><td>multipleButton = true</td><td>multipleButton = true</td><td></td>
</tr>
<tr>
<th>blank</th><td>blank = true</td><td>blank = true</td><td>blank = true</td><td>blank = true</td><td>blank = true</td>
</tr>
<tr>
<th>sort</th><td>sort = asc</td><td>sort = desc</td><td>sort = asc</td><td>sort = desc</td><td>sort = asc</td>
</tr>
<tr>
<th>search</th><td>search = true</td><td>search = true</td><td>search = true</td><td>search = true</td><td>search = true</td>
</tr>
<tr>
<th>class</th><td>class = text</td><td>class = checkbox</td><td>class = radio</td><td>class = select</td><td>class = textarea</td>
</tr>
<tr>
<th>style</th><td>style = color:#FF0000;</td><td>style = color:#FF0000;</td><td>style = color:#FF0000;</td><td>style = color:#FF0000;</td><td>style = color:#FF0000;</td>
</tr>
<tr>
<th>before</th><td>before = abcde</td><td></td><td></td><td>before = abcde</td><td></td>
</tr>
<tr>
<th>after</th><td>after = abcde</td><td></td><td></td><td>after = abcde</td><td></td>
</tr>
<tr>
<th>valueCount</th><td>valueCount = true</td><td>valueCount = true</td><td>valueCount = true</td><td>valueCount = true</td><td>valueCount = true</td>
</tr>
<tr>
<th>JavaScript Event Handlers</th><td>onclick = alert('ok');</td><td>onchange = alert('ok');</td><td>onchange = alert('ok');</td><td>onchange = alert('ok');</td><td>onfocus = alert('ok');</td>
</tr>
</tbody>
</table>
</div>
</div>

<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'custom-field-template'); ?>"><br /></div>
<h3><?php _e('Export Options', 'custom-field-template'); ?></h3>
<div class="inside">
<form method="post">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr><td>
<p><input type="submit" name="custom_field_template_export_options_submit" value="<?php _e('Export Options &raquo;', 'custom-field-template'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>

<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'custom-field-template'); ?>"><br /></div>
<h3><?php _e('Import Options', 'custom-field-template'); ?></h3>
<div class="inside">
<form method="post" enctype="multipart/form-data" onsubmit="return confirm('<?php _e('Are you sure to import options? Options you set will be overwritten.', 'custom-field-template'); ?>');">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr><td>
<p><input type="file" name="cftfile" /> <input type="submit" name="custom_field_template_import_options_submit" value="<?php _e('Import Options &raquo;', 'custom-field-template'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>

<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'custom-field-template'); ?>"><br /></div>
<h3><?php _e('Reset Options', 'custom-field-template'); ?></h3>
<div class="inside">
<form method="post" onsubmit="return confirm('<?php _e('Are you sure to reset options? Options you set will be reset to the default settings.', 'custom-field-template'); ?>');">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr><td>
<p><input type="submit" name="custom_field_template_unset_options_submit" value="<?php _e('Unset Options &raquo;', 'custom-field-template'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>

<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'custom-field-template'); ?>"><br /></div>
<h3><?php _e('Delete Options', 'custom-field-template'); ?></h3>
<div class="inside">
<form method="post" onsubmit="return confirm('<?php _e('Are you sure to delete options? Options you set will be deleted.', 'custom-field-template'); ?>');">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr><td>
<p><input type="submit" name="custom_field_template_delete_options_submit" value="<?php _e('Delete Options &raquo;', 'custom-field-template'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>

<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'custom-field-template'); ?>"><br /></div>
<h3><?php _e('Donation', 'custom-field-template'); ?></h3>
<div class="inside">
<p><?php _e('If you liked this plugin, please make a donation via paypal! Any amount is welcome. Your support is much appreciated.', 'custom-field-template'); ?></p>
<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr><td>
<input type="hidden" name="cmd" value="_s-xclick" />
<input type="hidden" name="hosted_button_id" value="100156" />
<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG_global.gif" border="0" name="submit" alt="" style="border:0;" />
<img alt="" border="0" src="https://www.paypal.com/ja_JP/i/scr/pixel.gif" width="1" height="1" />
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
jQuery('.postbox div.handlediv').click( function() { jQuery(jQuery(this).parent().get(0)).toggleClass('closed'); } );
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
	
	function make_textfield( $name, $sid, $size = 25, $default, $hideKey, $label, $code, $class, $style, $before, $after, $maxlength, $multipleButton,
	$onclick, $ondblclick, $onkeydown, $onkeypress, $onkeyup, $onmousedown, $onmouseup, $onmouseover, $onmouseout, $onmousemove, $onfocus, $onblur, $onchange, $onselect ) {
		$options = $this->get_custom_field_template_data();

		$title = $name;
		$name = $this->sanitize_name( $name );

		if ( is_numeric($code) ) :
			eval(stripcslashes($options['php'][$code]));
		endif;
		
		if( isset( $_REQUEST[ 'post' ] ) && $_REQUEST[ 'post' ] > 0 && $_REQUEST['default'] != true ) {
			$value = get_post_meta( $_REQUEST[ 'post' ], $title );
			if ( $value ) {
				$value = $value[ $sid ];
			}
		} else {
			$value = $default;
		}
		
		if ( $enforced_value ) :
			$value = $enforced_value;
		endif;
		
		if ( $hideKey == true ) $hide = ' class="hideKey"';
		if ( !empty($class) ) $class = ' class="' . $class . '"';
		if ( !empty($style) ) $style = ' style="' . $style . '"';
		if ( !empty($maxlength) ) $maxlength = ' maxlength="' . $maxlength . '"';
		
		if ( !empty($label) && $options['custom_field_template_replace_keys_by_labels'] )
			$title = stripcslashes($label);
		
		$event = array('onclick' => $onclick, 'ondblclick' => $ondblclick, 'onkeydown' => $onkeydown, 'onkeypress' => $onkeypress, 'onkeyup' => $onkeyup, 'onmousedown' => $onmousedown, 'onmouseup' => $onmouseup, 'onmouseover' => $onmouseover, 'onmouseout' => $onmouseout, 'onmousemove' => $onmousemove, 'onfocus' => $onfocus, 'onblur' => $onblur, 'onchange' => $onchange, 'onselect' => $onselect);
		$event_output = "";
		foreach($event as $key => $val) :
			if ( $val )
				$event_output .= " " . $key . '="' . stripcslashes(trim($val)) . '"';
		endforeach;
		
		if ( $multipleButton == true && $sid == 0 ) :
			$addfield .= '<div>';
			$addfield .= '<a href="#clear" onclick="jQuery(this).parent().parent().parent().clone().insertBefore(jQuery(this).parent().parent().parent()).find('."'input'".').val('."''".');jQuery(this).parent().css('."'visibility','hidden'".');jQuery(this).parent().prev().css('."'visibility','hidden'".'); return false;">' . __('Add New', 'custom-field-template') . '</a>';
			$addfield .= '</div>';
		endif;
				
		$out .= 
			'<dl id="dl_' . $name . $sid . '">' .
			'<dt><span' . $hide . '><label for="' . $name . $sid . '">' . $title . '</label></span>'.$addfield.'</dt>' .
			'<dd>';

		if ( !empty($label) && !$options['custom_field_template_replace_keys_by_labels'] )
			$out .= '<p class="label">' . stripcslashes($label) . '</p>';
		$out .= trim($before).'<input id="' . $name . $sid . '" name="' . $name . '[]" value="' . attribute_escape(trim($value)) . '" type="text" size="' . $size . '"' . $class . $style . $maxlength . $event_output . ' />'.trim($after).'</dd>' .
			'</dl>'."\n";
		return $out;
	}
	
	function make_checkbox( $name, $sid, $value, $valueLabel, $checked, $hideKey, $label, $code, $class, $style, 
	$onclick, $ondblclick, $onkeydown, $onkeypress, $onkeyup, $onmousedown, $onmouseup, $onmouseover, $onmouseout, $onmousemove, $onfocus, $onblur, $onchange, $onselect ) {
		$options = $this->get_custom_field_template_data();

		$title = $name;
		$name = $this->sanitize_name( $name );

		if ( !$value ) $value = "true";

		if( isset( $_REQUEST[ 'post' ] ) && $_REQUEST[ 'post' ] > 0 && $_REQUEST['default'] != true ) {
			$selected = get_post_meta( $_REQUEST[ 'post' ], $title );
			if ( $selected ) {
 				if ( in_array(stripcslashes($value), $selected) ) $checked = 'checked="checked"';
			}
		} else {
			if( $checked == true )  $checked = ' checked="checked"';
		}
		
		if ( $hideKey == true ) $hide = ' class="hideKey"';
		if ( !empty($class) ) $class = ' class="' . $class . '"';
		if ( !empty($style) ) $style = ' style="' . $style . '"';

		if ( !empty($label) && $options['custom_field_template_replace_keys_by_labels'] )
			$title = stripcslashes($label);

		$event = array('onclick' => $onclick, 'ondblclick' => $ondblclick, 'onkeydown' => $onkeydown, 'onkeypress' => $onkeypress, 'onkeyup' => $onkeyup, 'onmousedown' => $onmousedown, 'onmouseup' => $onmouseup, 'onmouseover' => $onmouseover, 'onmouseout' => $onmouseout, 'onmousemove' => $onmousemove, 'onfocus' => $onfocus, 'onblur' => $onblur, 'onchange' => $onchange, 'onselect' => $onselect);
		$event_output = "";
		foreach($event as $key => $val) :
			if ( $val )
				$event_output .= " " . $key . '="' . stripcslashes(trim($val)) . '"';
		endforeach;
		
		$out .= 
			'<dl id="dl_' . $name . $sid . '">' .
			'<dt><span' . $hide . '>' . $title . '</span></dt>' .
			'<dd>';
			
		$id = $name . $sid . '_' . $this->sanitize_name( $value );
		
		if ( !empty($label) && !$options['custom_field_template_replace_keys_by_labels'] )
			$out .= '<p class="label">' . stripcslashes($label) . '</p>';
		$out .=	'<label for="' . $id . '" class="selectit"><input id="' . $id . '" name="' . $name . '[' . $sid . ']" value="' . attribute_escape(trim($value)) . '"' . $checked . ' type="checkbox"' . $class . $style . $event_output . ' /> ';
		if ( $valueLabel )
			$out .= stripcslashes(trim($valueLabel));
		else
			$out .= stripcslashes(trim($value));
		$out .= '</label>';

		$out .= '</dd></dl>'."\n";
		
		return $out;
	}
	
	function make_radio( $name, $sid, $values, $valueLabel, $clearButton, $default, $hideKey, $label, $code, $class, $style, $multipleButton, 
	$onclick, $ondblclick, $onkeydown, $onkeypress, $onkeyup, $onmousedown, $onmouseup, $onmouseover, $onmouseout, $onmousemove, $onfocus, $onblur, $onchange, $onselect ) {
		$options = $this->get_custom_field_template_data();

		$title = $name;
		$name = $this->sanitize_name( $name );

		if ( is_numeric($code) ) :
			eval(stripcslashes($options['php'][$code]));
		endif;
		
		if( isset( $_REQUEST[ 'post' ] ) && $_REQUEST[ 'post' ] > 0 && $_REQUEST['default'] != true ) {
			$selected = get_post_meta( $_REQUEST[ 'post' ], $title );
			$selected = $selected[ $sid ];
		} else {
			$selected = $default;
		}
			
		if ( $hideKey == true ) $hide = ' class="hideKey"';
		if ( !empty($class) ) $class = ' class="' . $class . '"';
		if ( !empty($style) ) $style = ' style="' . $style . '"';

		if ( !empty($label) && $options['custom_field_template_replace_keys_by_labels'] )
			$title = stripcslashes($label);

		$event = array('onclick' => $onclick, 'ondblclick' => $ondblclick, 'onkeydown' => $onkeydown, 'onkeypress' => $onkeypress, 'onkeyup' => $onkeyup, 'onmousedown' => $onmousedown, 'onmouseup' => $onmouseup, 'onmouseover' => $onmouseover, 'onmouseout' => $onmouseout, 'onmousemove' => $onmousemove, 'onfocus' => $onfocus, 'onblur' => $onblur, 'onchange' => $onchange, 'onselect' => $onselect);
		$event_output = "";
		foreach($event as $key => $val) :
			if ( $val )
				$event_output .= " " . $key . '="' . stripcslashes(trim($val)) . '"';
		endforeach;

		if ( $multipleButton == true && $sid == 0 ) :
			$addfield .= '<div>';
			$addfield .= '<a href="#clear" onclick="jQuery(this).parent().parent().parent().clone().insertBefore(jQuery(this).parent().parent().parent()).find('."'input'".').attr('."'checked',false".');jQuery(this).parent().css('."'visibility','hidden'".');jQuery(this).parent().prev().css('."'visibility','hidden'".'); return false;">' . __('Add New', 'custom-field-template') . '</a>';
			$addfield .= '</div>';
		endif;		

		$out .= 
			'<dl id="dl_' . $name . $sid . '">' .
			'<dt><span' . $hide . '>' . $title . '</span>'.$addfield;
			
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
		$i = 0;
		foreach( $values as $val ) {
			$id = $name . $sid . '_' . $this->sanitize_name( $val );
			
			$checked = ( trim( $val ) == trim( $selected ) ) ? 'checked="checked"' : '';
			
			$out .=	
				'<label for="' . $id . '" class="selectit"><input id="' . $id . '" name="' . $name . '[' . $sid . ']" value="' . attribute_escape(trim($val)) . '" ' . $checked . ' type="radio"' . $class . $style . $event_output . ' /> ';
			if ( $valueLabel[$i] )
				$out .= stripcslashes(trim($valueLabel[$i]));
			else
				$out .= stripcslashes(trim($val));
			$out .= '</label><br />';
			$i++;
		}	 
		$out .= '</dd></dl>'."\n";
		
		return $out;			
	}
	
	function make_select( $name, $sid, $values, $valueLabel, $default, $hideKey, $label, $code, $class, $style, $before, $after, $selectLabel, $multipleButton,
	$onclick, $ondblclick, $onkeydown, $onkeypress, $onkeyup, $onmousedown, $onmouseup, $onmouseover, $onmouseout, $onmousemove, $onfocus, $onblur, $onchange, $onselect ) {
		$options = $this->get_custom_field_template_data();

		$title = $name;
		$name = $this->sanitize_name( $name );

		if ( is_numeric($code) ) :
			eval(stripcslashes($options['php'][$code]));
		endif;
	
		if( isset( $_REQUEST[ 'post' ] ) && $_REQUEST[ 'post' ] > 0 && $_REQUEST['default'] != true ) {
			$selected = get_post_meta( $_REQUEST[ 'post' ], $title );
			if ( $selected ) {
				$selected = $selected[ $sid ];
			}
		} else {
			$selected = $default;
		}
		
		if ( $hideKey == true ) $hide = ' class="hideKey"';
		if ( !empty($class) ) $class = ' class="' . $class . '"';
		if ( !empty($style) ) $style = ' style="' . $style . '"';

		if ( !empty($label) && $options['custom_field_template_replace_keys_by_labels'] )
			$title = stripcslashes($label);

		$event = array('onclick' => $onclick, 'ondblclick' => $ondblclick, 'onkeydown' => $onkeydown, 'onkeypress' => $onkeypress, 'onkeyup' => $onkeyup, 'onmousedown' => $onmousedown, 'onmouseup' => $onmouseup, 'onmouseover' => $onmouseover, 'onmouseout' => $onmouseout, 'onmousemove' => $onmousemove, 'onfocus' => $onfocus, 'onblur' => $onblur, 'onchange' => $onchange, 'onselect' => $onselect);
		$event_output = "";
		foreach($event as $key => $val) :
			if ( $val )
				$event_output .= " " . $key . '="' . stripcslashes(trim($val)) . '"';
		endforeach;
		
		if ( $multipleButton == true && $sid == 0 ) :
			$addfield .= '<div>';
			$addfield .= '<a href="#clear" onclick="jQuery(this).parent().parent().parent().clone().insertBefore(jQuery(this).parent().parent().parent()).find('."'option'".').attr('."'selected',false".');jQuery(this).parent().css('."'visibility','hidden'".');jQuery(this).parent().prev().css('."'visibility','hidden'".'); return false;">' . __('Add New', 'custom-field-template') . '</a>';
			$addfield .= '</div>';
		endif;		

		$out .= 
			'<dl id="dl_' . $name . $sid . '">' .
			'<dt><span' . $hide . '><label for="' . $name . $sid . '">' . $title . '</label></span>'.$addfield.'</dt>' .
			'<dd>';
			
		if ( !empty($label) && !$options['custom_field_template_replace_keys_by_labels'] )
			$out .= '<p class="label">' . stripcslashes($label) . '</p>';
		$out .=	trim($before).'<select id="' . $name . $sid . '" name="' . $name . '[]"' . $class . $style . $event_output . '>';
		
		if ( $selectLabel )
			$out .= '<option value="">' . stripcslashes(trim($selectLabel)) . '</option>';
		else
			$out .= '<option value="">' . __('Select', 'custom-field-template') . '</option>';
		
		$i = 0;
		foreach( $values as $val ) {
			$checked = ( trim( $val ) == trim( $selected ) ) ? 'selected="selected"' : '';
		
			$out .=	'<option value="' . attribute_escape(trim($val)) . '" ' . $checked . '>';
			if ( $valueLabel[$i] )
				$out .= stripcslashes(trim($valueLabel[$i]));
			else
				$out .= stripcslashes(trim($val));
			$out .= '</option>';
			$i++;
		}
		$out .= '</select>'.trim($after).'</dd></dl>'."\n";
		
		return $out;
	}
	
	function make_textarea( $name, $sid, $rows, $cols, $tinyMCE, $htmlEditor, $mediaButton, $default, $hideKey, $label, $code, $class, $style, 
	$onclick, $ondblclick, $onkeydown, $onkeypress, $onkeyup, $onmousedown, $onmouseup, $onmouseover, $onmouseout, $onmousemove, $onfocus, $onblur, $onchange, $onselect ) {
		$options = $this->get_custom_field_template_data();

		global $wp_version;

		$title = $name;
		$name = $this->sanitize_name( $name );
		
		if ( is_numeric($code) ) :
			eval(stripcslashes($options['php'][$code]));
		endif;
		
		if( isset( $_REQUEST[ 'post' ] ) && $_REQUEST[ 'post' ] > 0 && $_REQUEST['default'] != true ) {
			$value = get_post_meta( $_REQUEST[ 'post' ], $title );
			$value = $value[ $sid ];
		} else {
			$value = $default;
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
			$out .= '// ]]>' . "\n" . '</script>';
		}
		
		if ( substr($wp_version, 0, 3) >= '2.5' ) {

		if ( !strstr($_SERVER['REQUEST_URI'], 'wp-admin/edit.php') && !strstr($_SERVER['REQUEST_URI'], 'wp-admin/edit-pages.php')  ) {

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

		}
				
		if ( $hideKey == true ) $hide = ' class="hideKey"';
		$content_class = ' class="';
		if ( $htmlEditor == true ) $content_class .= 'content';
		if ( !empty($class) ) $content_class .= ' ' . $class;
		$content_class .= '"';
		if ( !empty($style) ) $style = ' style="' . $style . '"';

		if ( !empty($label) && $options['custom_field_template_replace_keys_by_labels'] )
			$title = stripcslashes($label);

		$event = array('onclick' => $onclick, 'ondblclick' => $ondblclick, 'onkeydown' => $onkeydown, 'onkeypress' => $onkeypress, 'onkeyup' => $onkeyup, 'onmousedown' => $onmousedown, 'onmouseup' => $onmouseup, 'onmouseover' => $onmouseover, 'onmouseout' => $onmouseout, 'onmousemove' => $onmousemove, 'onfocus' => $onfocus, 'onblur' => $onblur, 'onchange' => $onchange, 'onselect' => $onselect);
		$event_output = "";
		foreach($event as $key => $val) :
			if ( $val )
				$event_output .= " " . $key . '="' . stripcslashes(trim($val)) . '"';
		endforeach;
		
		$out .= 
			'<dl id="dl_' . $name . $sid . '">' .
			'<dt><span' . $hide . '><label for="' . $name . $sid . '">' . $title . '</label></span><br />' . $media . $switch . '</dt>' .
			'<dd>';

		if ( !empty($label) && !$options['custom_field_template_replace_keys_by_labels'] )
			$out .= '<p class="label">' . stripcslashes($label) . '</p>';
		
		if ( $htmlEditor == true ) :
			if( $tinyMCE == true ) $quicktags_hide = ' jQuery(\'#qt_' . $name . $rand . '_qtags\').hide();';
			$out .= '<div class="quicktags"><script type="text/javascript">' . "\n" . '// <![CDATA[' . "\n" . '
		jQuery(document).ready(function() { qt_' . $name . $rand . ' = new QTags(\'qt_' . $name . $rand . '\', \'' . $name . $rand . '\', \'editorcontainer_' . $name . $rand . '\', \'more\'); ' . $quicktags_hide . ' });' . "\n" . '// ]]>' . "\n" . '</script>';
			$editorcontainer_class .= ' class="editorcontainer"';
		endif;
		
		$out .= '<div' . $editorcontainer_class . ' id="editorcontainer_' . $name . $rand . '"><textarea id="' . $name . $rand . '" name="' . $name . '[' . $sid . ']" rows="' .$rows. '" cols="' . $cols . '" style="color:#000000"' . $content_class . $style . $event_output . '>' . attribute_escape(trim($value)) . '</textarea><input type="hidden" name="'.$name.'_rand['.$sid.']" value="'.$rand.'" /></div>';
		if ( $htmlEditor == true ) $out .= '</div>';
		$out .= '</dd></dl>'."\n";
		
		return $out;
	}

	function load_custom_field( $id = 0 ) {
		global $userdata;
		get_currentuserinfo();
		$level = $userdata->user_level;
		
		$options = $this->get_custom_field_template_data();

		$fields = $this->get_custom_fields( $id );
				
		if ( $fields == null )
			return;
			
		if ( $options['custom_fields'][$id]['post_type'] ) :
			if ( $options['custom_fields'][$id]['post_type'] == 'post' && (strstr($_SERVER['REQUEST_URI'], 'wp-admin/page-new.php') || strstr($_SERVER['REQUEST_URI'], 'wp-admin/page.php') || strstr($_SERVER['REQUEST_URI'], 'wp-admin/edit-pages.php')) )
				return;
			if ( $options['custom_fields'][$id]['post_type'] == 'page' && (strstr($_SERVER['REQUEST_URI'], 'wp-admin/post-new.php') || strstr($_SERVER['REQUEST_URI'], 'wp-admin/post.php') || strstr($_SERVER['REQUEST_URI'], 'wp-admin/edit.php')) )
				return;
		endif;
					
		if ( $options['custom_fields'][$id]['category'] && (strstr($_SERVER['REQUEST_URI'], 'wp-admin/page-new.php') || strstr($_SERVER['REQUEST_URI'], 'wp-admin/page.php')) )
			return;

		if ( (!$_REQUEST['post'] || $_REQUEST['post']<0) && $options['custom_fields'][$id]['category'] && $_REQUEST['cft_mode'] != 'ajaxload' )
			return;
		
		if ( $_REQUEST['post'] && $options['custom_fields'][$id]['category'] && !isset($options['posts'][$_REQUEST['post']]) && $options['posts'][$_REQUEST['post']] !== $id && $_REQUEST['cft_mode'] != 'ajaxload' )
			return;
			
		if ( $options['custom_fields'][$id]['instruction'] ) :
			$instruction = stripcslashes($options['custom_fields'][$id]['instruction']);
			$instruction = preg_replace_callback("/(<\?php|<\?|< \?php)(.*?)\?>/si", array($this, 'EvalBuffer'), $instruction);
			$out .= '<div id="cft_instruction">' . $instruction . '</div>';
		endif;

		$out .= '<div>';
		$out .= '<input type="hidden" name="custom-field-template-id" id="custom-field-template-id" value="' . $id . '" />';
		foreach( $fields as $title => $data ) {
			for($i = 0; $i<count($data); $i++) {
				if ( is_numeric($data[$i]['level']) ) :
					if ( $data[$i]['level'] > $level ) continue;
				endif;
				if( $data[$i]['type'] == 'break' ) {
					if ( !empty($data[$i]['class']) ) $class = ' class="' . $data[$i]['class'] . '"';
					$out .= '</div><div' . $class . '>';
				}
				else if( $data[$i]['type'] == 'textfield' || $data[$i]['type'] == 'text' ) {
					$out .= $this->make_textfield( $title, $i, $data[$i]['size'], $data[$i]['default'], $data[$i]['hideKey'], $data[$i]['label'], $data[$i]['code'], $data[$i]['class'], $data[$i]['style'], $data[$i]['before'], $data[$i]['after'], $data[$i]['maxlength'], $data[$i]['multipleButton'], 
						$data[$i]['onclick'], $data[$i]['ondblclick'], $data[$i]['onkeydown'], $data[$i]['onkeypress'], $data[$i]['onkeyup'], $data[$i]['onmousedown'], $data[$i]['onmouseup'], $data[$i]['onmouseover'], $data[$i]['onmouseout'], $data[$i]['onmousemove'], $data[$i]['onfocus'], $data[$i]['onblur'], $data[$i]['onchange'], $data[$i]['onselect'] );
				}
				else if( $data[$i]['type'] == 'checkbox' ) {
					$out .= 
						$this->make_checkbox( $title, $i, $data[$i]['value'], $data[$i]['valueLabel'], $data[$i]['checked'], $data[$i]['hideKey'], $data[$i]['label'], $data[$i]['code'], $data[$i]['class'], $data[$i]['style'],
						$data[$i]['onclick'], $data[$i]['ondblclick'], $data[$i]['onkeydown'], $data[$i]['onkeypress'], $data[$i]['onkeyup'], $data[$i]['onmousedown'], $data[$i]['onmouseup'], $data[$i]['onmouseover'], $data[$i]['onmouseout'], $data[$i]['onmousemove'], $data[$i]['onfocus'], $data[$i]['onblur'], $data[$i]['onchange'], $data[$i]['onselect'] );
				}
				else if( $data[$i]['type'] == 'radio' ) {
					$out .= 
						$this->make_radio( 
							$title, $i, explode( '#', $data[$i]['value'] ), explode( '#', $data[$i]['valueLabel'] ), $data[$i]['clearButton'], $data[$i]['default'], $data[$i]['hideKey'], $data[$i]['label'], $data[$i]['code'], $data[$i]['class'], $data[$i]['style'], $data[$i]['multipleButton'], 
						$data[$i]['onclick'], $data[$i]['ondblclick'], $data[$i]['onkeydown'], $data[$i]['onkeypress'], $data[$i]['onkeyup'], $data[$i]['onmousedown'], $data[$i]['onmouseup'], $data[$i]['onmouseover'], $data[$i]['onmouseout'], $data[$i]['onmousemove'], $data[$i]['onfocus'], $data[$i]['onblur'], $data[$i]['onchange'], $data[$i]['onselect'] );
				}
				else if( $data[$i]['type'] == 'select' ) {
					$out .= 
						$this->make_select( 
							$title, $i, explode( '#', $data[$i]['value'] ), explode( '#', $data[$i]['valueLabel'] ), $data[$i]['default'], $data[$i]['hideKey'], $data[$i]['label'], $data[$i]['code'], $data[$i]['class'], $data[$i]['style'], $data[$i]['before'], $data[$i]['after'], $data[$i]['selectLabel'], $data[$i]['multipleButton'], 
						$data[$i]['onclick'], $data[$i]['ondblclick'], $data[$i]['onkeydown'], $data[$i]['onkeypress'], $data[$i]['onkeyup'], $data[$i]['onmousedown'], $data[$i]['onmouseup'], $data[$i]['onmouseover'], $data[$i]['onmouseout'], $data[$i]['onmousemove'], $data[$i]['onfocus'], $data[$i]['onblur'], $data[$i]['onchange'], $data[$i]['onselect'] );
				}
				else if( $data[$i]['type'] == 'textarea' ) {
					if ( $options['tinyMCE'][$_REQUEST['post']][$this->sanitize_name($title)][$i] )  $data[$i]['rows']  = $options['tinyMCE'][$_REQUEST['post']][$this->sanitize_name($title)][$i];
					$out .= 
						$this->make_textarea( $title, $i, $data[$i]['rows'], $data[$i]['cols'], $data[$i]['tinyMCE'], $data[$i]['htmlEditor'], $data[$i]['mediaButton'], $data[$i]['default'], $data[$i]['hideKey'], $data[$i]['label'], $data[$i]['code'], $data[$i]['class'], $data[$i]['style'],
						$data[$i]['onclick'], $data[$i]['ondblclick'], $data[$i]['onkeydown'], $data[$i]['onkeypress'], $data[$i]['onkeyup'], $data[$i]['onmousedown'], $data[$i]['onmouseup'], $data[$i]['onmouseover'], $data[$i]['onmouseout'], $data[$i]['onmousemove'], $data[$i]['onfocus'], $data[$i]['onblur'], $data[$i]['onchange'], $data[$i]['onselect'] );
				}
			}
		}
		$out .= '</div>';
		$out .= '<br style="clear:both; font-size:1px;" />';		
	
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
					'		if ( ed ) { jQuery(\'#editorcontainer_\'+id).prev().hide(); ed.show(); }' . "\n" .
					'		else {tinyMCE.execCommand("mceAddControl", false, id);}' . "\n" .
					'	} else {' . "\n" .
					'		ed.hide(); jQuery(\'#editorcontainer_\'+id).prev().show(); document.getElementById(id).style.color="#000000";' . "\n" .
					'	}' . "\n" .
					'}' . "\n";
					
					if(count($options['custom_fields'])>$options['posts'][$_REQUEST['post']] && $options['posts'][$_REQUEST['post']]) $init_id = $options['posts'][$_REQUEST['post']];
					else $init_id = 0;

		$out .=		'jQuery(document).ready(function() {' . "\n";

					$fields = $this->get_custom_fields( $init_id );
					if ( user_can_richedit() ) :
						foreach( $fields as $title => $data ) :
							for($i = 0; $i<count($data); $i++) :
								if( $data[$i][ 'type' ] == 'textarea' && $data[$i][ 'tinyMCE' ] ) :
									if ( substr($wp_version, 0, 3) >= '2.7' ) :
		/*$out .=		'	if ( getUserSetting( "editor" ) == "html" ) {
jQuery("#edButtonPreview").trigger("click"); }' . "\n";*/
									else :
		$out .=		'	if(wpTinyMCEConfig) if(wpTinyMCEConfig.defaultEditor == "html") { jQuery("#edButtonPreview").trigger("click"); }' . "\n";
									endif;
									break;
								endif;
							endfor;
						endforeach;
					endif;

		if ( $options['custom_fields'] ) :
			foreach ( $options['custom_fields'] as $key => $val ) :
				if ( $val['category'] ) :
					$val['category'] = preg_replace('/\s/', '', $val['category']);
					$categories = explode(',', $val['category']);
					foreach($categories as $cat_id) :
						if ( is_numeric($cat_id) ) :
		$out .=		'	jQuery(\'#in-category-' . $cat_id . '\').click(function(){if(jQuery(\'#in-category-' . $cat_id . '\').attr(\'checked\') == true) { if(tinyMCEID.length) { for(i=0;i<tinyMCEID.length;i++) {tinyMCE.execCommand(\'mceRemoveControl\', false, tinyMCEID[i]);} tinyMCEID.length=0;}; jQuery.get(\'?page=custom-field-template/custom-field-template.php&cft_mode=selectbox&post=\'+jQuery(\'#post_ID\').val()+\'&\'+jQuery(\'#categories-all :input\').fieldSerialize(), function(html) { jQuery(\'#cft_selectbox\').html(html); });';
			if ( $options['custom_field_template_use_autosave'] ) :
				$out .= ' var fields = jQuery(\'#cft :input\').fieldSerialize();';
				$out .= 'jQuery.ajax({type: \'POST\', url: \'?page=custom-field-template/custom-field-template.php&cft_mode=ajaxsave&post=\'+jQuery(\'#post_ID\').val()+\'&custom-field-template-verify-key=\'+jQuery(\'#custom-field-template-verify-key\').val()+\'&\'+fields, success: function(){jQuery(\'#custom_field_template_select\').val(\'' . $key . '\');jQuery.ajax({type: \'GET\', url: \'?page=custom-field-template/custom-field-template.php&cft_mode=ajaxload&id=' . $key . '&post=\'+jQuery(\'#post_ID\').val(), success: function(html) {';
				if ( $options['custom_field_template_replace_the_title'] ) :
					$out .= 'jQuery(\'#cftdiv h3 span\').text(\'' . $options['custom_fields'][$key]['title'] . '\');';
				endif;
				$out .= 'jQuery(\'#cft\').html(html);}});}});';
			else :
				$out .=		'	jQuery(\'#custom_field_template_select\').val(\'' . $key . '\');jQuery.ajax({type: \'GET\', url: \'?page=custom-field-template/custom-field-template.php&cft_mode=ajaxload&id=' . $key . '&post=\'+jQuery(\'#post_ID\').val(), success: function(html) {';
				if ( $options['custom_field_template_replace_the_title'] ) :
					$out .= 'jQuery(\'#cftdiv h3 span\').text(\'' . $options['custom_fields'][$key]['title'] . '\');';
				endif;
				$out .= 'jQuery(\'#cft\').html(html);}});';
			endif;

		$out .=		'	}else{ jQuery(\'#cft\').html(\'\');jQuery.get(\'?page=custom-field-template/custom-field-template.php&cft_mode=selectbox&post=\'+jQuery(\'#post_ID\').val(), function(html) { jQuery(\'#cft_selectbox\').html(html); });';
		if ( $options['custom_field_template_replace_the_title'] ) :
			$out .= 'jQuery(\'#cftdiv h3 span\').text(\'' . __('Custom Field Template', 'custom-field-template') . '\');';
		endif;
		$out .= '}});' . "\n";
						endif;
					endforeach;
				endif;
			endforeach;
		endif;
		$out .= 	'	jQuery(\'#cftloading_img\').ajaxStart(function() { jQuery(this).show();});';
		$out .= 	'	jQuery(\'#cftloading_img\').ajaxStop(function() { jQuery(this).hide();});';
		$out .=		'});' . "\n";

					
		$out .=		'var tinyMCEID = new Array();' . "\n" .
					'// ]]>' . "\n" .
					'</script>';
		$out .=		'<style type="text/css">' . "\n" .
					'<!--' . "\n";
		$out .=		$options['css'] . "\n";
		$out .=		'.editorcontainer { overflow:hidden; background:#FFFFFF; }
.content { width:98%; }
.editorcontainer .content { padding: 6px; line-height: 150%; border: 0 none; outline: none;	-moz-box-sizing: border-box;	-webkit-box-sizing: border-box;	-khtml-box-sizing: border-box; box-sizing: border-box; }
.quicktags { border:1px solid #DFDFDF; border-collapse: separate; -moz-border-radius: 6px 6px 0 0; -webkit-border-top-right-radius: 6px; -webkit-border-top-left-radius: 6px; -khtml-border-top-right-radius: 6px; -khtml-border-top-left-radius: 6px; border-top-right-radius: 6px; border-top-left-radius: 6px; }
.quicktags { padding: 0; margin-bottom: -1px; border-bottom-width:1px;	background-image: url("images/ed-bg.gif"); background-position: left top; background-repeat: repeat; }
.quicktags div div { padding: 2px 4px 0; }
.quicktags div div input { margin: 3px 1px 4px; line-height: 18px; display: inline-block; border-width: 1px; border-style: solid; min-width: 26px; padding: 2px 4px; font-size: 12px; -moz-border-radius: 3px; -khtml-border-radius: 3px; -webkit-border-radius: 3px; border-radius: 3px; background:#FFFFFF url(images/fade-butt.png) repeat-x scroll 0 -2px; overflow: visible; }' . "\n";
		$out .=		'-->' . "\n" .
					'</style>';
		$body = $this->load_custom_field($init_id);
		
		$out .= '<div id="cft_selectbox" style="height:25px;">';
		$out .= $this->custom_field_template_selectbox();
		$out .= '</div>';
		
		$out .= '<input type="hidden" name="custom-field-template-verify-key" id="custom-field-template-verify-key" value="' . wp_create_nonce('custom-field-template') . '" />';
		$out .= '<div id="cft">';
		$out .= $body;
		$out .= '</div>';
		
		$out .= '<div style="position:absolute; top:30px; right:5px;">';
		$out .= '<img class="waiting" style="display:none; vertical-align:middle;" src="images/loading.gif" alt="" id="cftloading_img" /> ';
		$out .= '<input type="button" value="' . __('Initialize', 'custom-field-template') . '" onclick="';
		$out .= 'if(confirm(\''.__('Are you sure to reset current values? Default values will be loaded.', 'custom-field-template').'\')){if(tinyMCEID.length) { for(i=0;i<tinyMCEID.length;i++) {tinyMCE.execCommand(\'mceRemoveControl\', false, tinyMCEID[i]);} tinyMCEID.length=0;};jQuery.ajax({type: \'GET\', url: \'?page=custom-field-template/custom-field-template.php&cft_mode=ajaxload&default=true&id=\'+jQuery(\'#custom-field-template-id\').val()+\'&post=\'+jQuery(\'#post_ID\').val(), success: function(html) {';
		$out .= 'jQuery(\'#cft\').html(html);}});}';
		$out .= '" class="button" style="vertical-align:middle;" />';
		$out .= '<input type="button" value="' . __('Save', 'custom-field-template') . '" onclick="';
		$out .= 'var fields = jQuery(\'#cft :input\').fieldSerialize();';
		$out .= 'jQuery.ajax({type: \'POST\', url: \'?page=custom-field-template/custom-field-template.php&cft_mode=ajaxsave&post=\'+jQuery(\'#post_ID\').val()+\'&custom-field-template-verify-key=\'+jQuery(\'#custom-field-template-verify-key\').val()+\'&\'+fields});';
		$out .= '" class="button" style="vertical-align:middle;" />';
		$out .= '</div>';
			
		if ( substr($wp_version, 0, 3) < '2.5' ) {
			$out .= '</div></fieldset></div>';
		} else {
			if ( $body && $options['custom_field_template_replace_the_title'] ) :
				$out .= '<script type="text/javascript">' . "\n" . '// <![CDATA[' . "\n";
				$out .=	'jQuery(document).ready(function() {jQuery(\'#cftdiv h3 span\').text(\'' . $options['custom_fields'][$init_id]['title'] . '\');});' . "\n";
				$out .= '// ]]>' . "\n" . '</script>';
			endif;
		}

		echo $out;
	}
	
	function custom_field_template_selectbox() {
		$options = $this->get_custom_field_template_data();
		if ( count($options['custom_fields'])>1 ) :
			$flag = 0;
			$categories = get_the_category($_REQUEST['post']);
			$cats = array();
			if ( is_array($categories) ) foreach($categories as $category) $cats[] = $category->cat_ID; 
			if ( $_REQUEST['post_category'] ) $cats = array_merge($cats, $_REQUEST['post_category']);
			for ( $i=0; $i < count($options['custom_fields']); $i++ ) :
				if ( !$options['custom_fields'][$i]['category'] || in_array($options['custom_fields'][$i]['category'], $cats) ) :
					$flag = 1;
					break;
				endif;
			endfor;
			if ( $flag ) :
				$out .= '<select id="custom_field_template_select">';
				for ( $i=0; $i < count($options['custom_fields']); $i++ ) {
					if ( in_array($options['custom_fields'][$i]['category'], $cats) ) :
						$out .= '<option value="' . $i . '">' . stripcslashes($options['custom_fields'][$i]['title']) . '</option>';
					elseif ( $options['custom_fields'][$i]['category'] ) :
						$out .= '';
					elseif ( $i == $options['posts'][$_REQUEST['post']] && isset($_REQUEST['post']) ) :
						$out .= '<option value="' . $i . '" selected="selected">' . stripcslashes($options['custom_fields'][$i]['title']) . '</option>';
					else :
						$out .= '<option value="' . $i . '">' . stripcslashes($options['custom_fields'][$i]['title']) . '</option>';
					endif;
				}
				$out .= '</select> ';
				$out .= '<input type="button" class="button" value="' . __('Load', 'custom-field-template') . '" onclick="if(tinyMCEID.length) { for(i=0;i<tinyMCEID.length;i++) {tinyMCE.execCommand(\'mceRemoveControl\', false, tinyMCEID[i]);} tinyMCEID.length=0;};';
				$out .= ' var cftloading_select = function() {jQuery.ajax({type: \'GET\', url: \'?page=custom-field-template/custom-field-template.php&cft_mode=ajaxload&id=\'+jQuery(\'#custom_field_template_select\').val()+\'&post=\'+jQuery(\'#post_ID\').val(), success: function(html) {';
				if ( $options['custom_field_template_replace_the_title'] ) :
					$out .= 'jQuery(\'#cftdiv h3 span\').text(jQuery(\'#custom_field_template_select :selected\').text());';
				endif;
				$out .= 'jQuery(\'#cft\').html(html);}});};';
				if ( $options['custom_field_template_use_autosave'] ) :
					$out .= 'var fields = jQuery(\'#cft :input\').fieldSerialize();';
					$out .= 'jQuery.ajax({type: \'POST\', url: \'?page=custom-field-template/custom-field-template.php&cft_mode=ajaxsave&post=\'+jQuery(\'#post_ID\').val()+\'&custom-field-template-verify-key=\'+jQuery(\'#custom-field-template-verify-key\').val()+\'&\'+fields, success: cftloading_select});';
				else :
					$out .= 'cftloading_select();';
				endif;
				$out .= '" />';
			else :
				$out .= '&nbsp;';
			endif;
		else :
			$out .= '&nbsp;';			
		endif;
		
		return $out;
	}

	function edit_meta_value( $id ) {
		global $wpdb, $wp_version;
		$options = $this->get_custom_field_template_data();
		
		if( !isset( $id ) || $_REQUEST[ 'post_ID' ] )
			$id = $_REQUEST[ 'post_ID' ];
		
		if( !current_user_can('edit_post', $id) )
			return $id;
								
		if( !wp_verify_nonce($_REQUEST['custom-field-template-verify-key'], 'custom-field-template') )
			return $id;
		
		if ( !isset($_REQUEST['custom-field-template-id']) ) :
			unset($options['posts'][$id]);
			update_option('custom_field_template_data', $options);
			return $id;
		endif;
		
		$fields = $this->get_custom_fields($_REQUEST['custom-field-template-id']);
		
		if ( $fields == null )
			return;
			
		if ( !class_exists('SimpleTags') && strstr($_POST['tags_input'], ',') ) {
			$tags_input = explode(",", $_POST['tags_input']);
		}
							
		foreach( $fields as $title	=> $data) {
			$name = $this->sanitize_name( $title );
			$title = $wpdb->escape(stripcslashes(trim($title)));

			unset($values);
			for($i = 0; $i<count($data); $i++) {
				unset($value);
				$value = stripcslashes(trim($_REQUEST[ "$name" ][$i]));
				
				if ( $options['custom_field_template_use_wpautop'] && $data[$i]['type'] == 'textarea' && !empty($value) )
					$value = wpautop($value);
				if ( is_numeric($data[$i]['editCode']) ) :
					eval(stripcslashes($options['php'][$data[$i]['editCode']]));
				endif;
				if( isset( $value ) && strlen( $value ) ) {
					if ( strlen( $value ) ) :
						$values[] = addslashes($value);
					endif;
					if ( $data[$i]['insertTag'] == true ) $tags_input[] = $value;
					if ( $data[$i]['valueCount'] == true ) :
						$options['value_count'][$title][$value] = $this->set_value_count($title, $value);
					endif;
						
					if ( $_REQUEST['TinyMCE_' . $name . trim($_REQUEST[ $name."_rand" ][$i]) . '_size'] ) {
						preg_match('/cw=[0-9]+&ch=([0-9]+)/', $_REQUEST['TinyMCE_' . $name . trim($_REQUEST[ $name."_rand" ][$i]) . '_size'], $matched);
						$options['tinyMCE'][$id][$name][$i] = (int)($matched[1]/20);			
					}
				} else {
					if ( $data[$i]['blank'] == true ) $values[] = "";
				}
			}
			if ( count($values) == 1 ) :
				if ( !add_post_meta( $id, $title, apply_filters('cft_'.urlencode($title), $values[0]), true ) ) :
					update_post_meta( $id, $title, apply_filters('cft_'.urlencode($title), $values[0]) );
				endif;
			elseif ( count($values) > 1 ) :
				$tmp = get_post_meta( $id, $title, false );
				if ( count($tmp)>0 ) :
					if ( $values != $tmp ) :
						delete_post_meta($id, $title);
						foreach($values as $val)
							add_post_meta( $id, $title, apply_filters('cft_'.urlencode($title), $val) );
					endif;
				else :
					foreach($values as $val)
						add_post_meta( $id, $title, apply_filters('cft_'.urlencode($title), $val) );
				endif;
			else :
				delete_post_meta($id, $title);
			endif;
		}

		if ( is_array($tags_input) ) :
			if ( class_exists('SimpleTags') ) :
				wp_cache_flush();
				$taxonomy = wp_get_object_terms($id, 'post_tag', array());
				if ( $taxonomy ) foreach($taxonomy as $val) $tags[] = $val->name;
				if ( is_array($tags) ) $tags_input = array_merge($tags, $tags_input);
			endif;
			$tags_input = array_unique($tags_input);
			if ( substr($wp_version, 0, 3) >= '2.3' )
				wp_set_post_tags( $id, $tags_input );
		endif;
		
		$options['posts'][$id] = $_REQUEST['custom-field-template-id'];
		update_option('custom_field_template_data', $options);
	}
	
	function parse_ini_str($Str,$ProcessSections = TRUE) {
		$options = $this->get_custom_field_template_data();

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

				foreach($Data as $title => $data) :
					foreach($data as $key => $val) :
						if ( $val['multiple'] == true && $val['type'] != 'checkbox' ) :
							$counter = count($_REQUEST[$this->sanitize_name($title)]);
							if ( !$counter ) :
								$counter = count(get_post_meta( $_REQUEST['post'], $title, false ));
								$counter++;
							endif;							
							if ( is_numeric($val['startNum']) && $val['startNum']>$counter ) $counter = $val['startNum'];
							if ( is_numeric($val['endNum']) && $val['endNum']<$counter  ) $counter = $val['endNum'];
						endif;
						if ( $counter ) :
							for($i=1;$i<$counter; $i++) :
								$Data[$title][$i] =  $Data[$title][0];
								$Data[$title][$i]["hideKey"] = true;
							endfor;							
						endif;
						unset($counter);
					endforeach;
				endforeach;
				
				foreach($Data as $title => $data) {
					foreach($data as $key => $val) {
						if($val["type"] == "checkbox") {
							if ( is_numeric($val["code"]) ) :
								eval(stripcslashes($options['php'][$val["code"]]));
							else :
								$values = explode( '#', $val["value"] );
								$valueLabel = explode( '#', $val["valueLabel"] );
								$defaults = explode( '#', $val["default"] );
							endif;

							if ( is_array($defaults) )
								foreach($defaults as $dkey => $dval)
									$defaults[$dkey] = trim($dval);
							
							$tmp = $key;
							if ( is_array($values) ) :
								$i = 0;
								foreach($values as $value) {
									$Data[$title][$key]["type"] = "checkbox";
									$Data[$title][$key]["value"] = trim($value);
									if ( $valueLabel[$i] )
										$Data[$title][$key]["valueLabel"] = trim($valueLabel[$i]);
									if ( $tmp!=$key )
										$Data[$title][$key]["hideKey"] = true;
									if ( is_array($defaults) )
										if ( in_array(trim($value), $defaults) )
											$Data[$title][$key]["checked"] = true;
									if ( $val["level"] )
										$Data[$title][$key]["level"] = $val["level"];
									if ( $val["insertTag"] == true )
										$Data[$title][$key]["insertTag"] = true;
									if ( $val["valueCount"] == true )
										$Data[$title][$key]["valueCount"] = true;
									if ( $val["output"] == true )
										$Data[$title][$key]["output"] = true;
									$key++;
									$i++;
								}
							endif;
						}
					}
				}			
		}
		return $Data;
	}

	function output_custom_field_values($attr) {
		global $post;
		$options = $this->get_custom_field_template_data();

		extract(shortcode_atts(array(
			'post_id'   => $post->ID,
			'template'  => 0,
			'format'    => '',
			'key'   => '',
			'single'    => false
		), $attr));
		
		$metakey = $key;
		if ( $metakey ) :
			$metavalue = get_post_meta($post_id, $key, $single);
			if ( is_array($metavalue) ) :
				$output = '<ul>' . "\n";
				foreach ( $metavalue as $val ) :
					$output .= '<li>' . $val . '</li>' . "\n";
				endforeach;
				$output .= '</ul>' . "\n";
			else :
				$output = $metavalue;
			endif;
			return $output;
		endif;
		
		if ( is_numeric($format) && $output = $options['shortcode_format'][$format] ) :
			$data = get_post_custom($post_id);
			$output = stripcslashes($output);
			
			if( $data == null)
				return;

			$count = count($options['custom_fields']);
			if ( $count ) :
				for ($i=0;$i<$count;$i++) :
					$fields = $this->get_custom_fields( $i );
					foreach ( $fields as $key => $val ) :
						if ( count($data[$key]) > 1 ) :
							if ( $val[0]['sort'] == 'asc' ) :
								sort($data[$key]);
							elseif ( $val[0]['sort'] == 'desc' ) :
								rsort($data[$key]);
							elseif ( $val[0]['sort'] == 'order' ) :
								$tmpdata = array();
								for( $j=0; $j<count($val); $j++) :
									if ( in_array($val[$j]['value'], $data[$key]) )
										$tmpdata[] = $val[$j]['value'];
								endfor;
								$data[$key] = $tmpdata;
							endif;
							$replace_val = '<ul>';
							foreach ( $data[$key] as $val2 ) :
								$value = $val2;
								if ( is_numeric($val[0]['outputCode']) ) :
									eval(stripcslashes($options['php'][$val[0]['outputCode']]));
								endif;
								$replace_val .= '<li>'.$value.'</li>';
							endforeach;
							$replace_val .= '</ul>';
						elseif ( count($data[$key]) == 1 ) :
							$value = $data[$key][0];
							if ( is_numeric($val[0]['outputCode']) ) :
								eval(stripcslashes($options['php'][$val[0]['outputCode']]));
							endif;
							$replace_val = $value;
						else :
							$replace_val = '';
						endif;
						if ( $options['shortcode_format_use_php'][$format] )
							$output = preg_replace_callback("/(<\?php|<\?|< \?php)(.*?)\?>/si", array($this, 'EvalBuffer'), $output);
						$key = preg_quote($key, '/');
						$replace_val = str_replace('\\', '\\\\', $replace_val); 
						$replace_val = str_replace('$', '\$', $replace_val); 
						$output = preg_replace('/\['.$key.'\]/', $replace_val, $output);
					endforeach;
				endfor;
			endif;
		else :
			$fields = $this->get_custom_fields( $template );
					
			if( $fields == null)
				return;

			$output = '<dl class="cft">' . "\n";
			foreach ( $fields as $key => $val ) :
				$values = get_post_meta( $post_id, $key );
				if ($values):
					if ( $val[0]['sort'] == 'asc' ) :
						sort($values);
					elseif ( $val[0]['sort'] == 'desc' ) :
						rsort($values);
					elseif ( $val[0]['sort'] == 'order' ) :
						$tmpdata = array();
						for( $j=0; $j<count($val); $j++) :
							if ( in_array($val[$j]['value'], $data[$key]) )
								$tmpdata[] = $val[$j]['value'];
						endfor;
						$values = $tmpdata;
					endif;
					foreach ( $val as $key2 => $val2 ) :
						$hide = '';
						if ( $val2['output'] == true ) :
							$value = $values[$key2];
							$value = str_replace('\\', '\\\\', $value); 
							if ( is_numeric($val2['outputCode']) ) :
								eval(stripcslashes($options['php'][$val2['outputCode']]));
							endif;
							if ( $val2['hideKey'] == true ) $hide = ' class="hideKey"';
							if ( !empty($val2['label']) && $options['custom_field_template_replace_keys_by_labels'] )
								$key = stripcslashes($val2['label']);
							if ( $val2['type'] == 'checkbox' ) :
								if( in_array($val2['value'], $values) ) :
									$output .= '<dt><span' . $hide . '>' . $key . '</span></dt>' . "\n";
									$output .= '<dd>' . $value . '</dd>' . "\n";
								endif;
							else :
								$output .= '<dt><span' . $hide . '>' . $key . '</span></dt>' . "\n";
								$output .= '<dd>' . $value . '</dd>' . "\n";
							endif;
						endif;
					endforeach;
				endif;
			endforeach;
			$output .= '</dl>' . "\n";
		endif;
		
		return stripcslashes($output);
	}
	
	function search_custom_field_values($attr) {
		global $post;
		$options = $this->get_custom_field_template_data();

		extract(shortcode_atts(array(
			'template'    => 0,
			'format'      => '',
			'search_label' => __('Search &raquo;', 'custom-field-template'),
			'button'      => true
		), $attr));
		
		if ( is_numeric($format) && $output = $options['shortcode_format'][$format] ) :
			$output = stripcslashes($output);
			$output = '<form method="get" action="/" id="cftsearch'.(int)$format.'">' . "\n" . $output;

			$count = count($options['custom_fields']);
			if ( $count ) :
				for ($i=0;$i<$count;$i++) :
					$fields = $this->get_custom_fields( $i );
					foreach ( $fields as $key => $val ) :
						unset($replace);
						$replace[0] = $val;

						$search = array();
						if($val[0]['searchType']) eval('$search["type"] =' . stripslashes($val[0]['searchType']));
						if($val[0]['searchValue']) eval('$search["value"] =' . stripslashes($val[0]['searchValue']));
						if($val[0]['searchOperator']) eval('$search["operator"] =' . stripslashes($val[0]['searchOperator']));
						if($val[0]['searchValueLabel']) eval('$search["valueLabel"] =' . stripslashes($val[0]['searchValueLabel']));
						if($val[0]['searchDefault']) eval('$search["default"] =' . stripslashes($val[0]['searchDefault']));
						if($val[0]['searchClass']) eval('$search["class"] =' . stripslashes($val[0]['searchClass']));
						
						foreach ( $search as $skey => $sval ) :
							$j = 1;
							foreach ( $sval as $sval2 ) :
								$replace[$j][0][$skey] = $sval2;
								$j++;
							endforeach;
						endforeach;						
												
						foreach( $replace as $rkey => $rval ) :				
							$replace_val[$rkey] = "";
							$class = "";
							switch ( $rval[0]['type'] ) :
								case 'text':
								case 'textfield':
								case 'textarea':
									if ( $rval[0]['class'] ) $class = ' class="' . $rval[0]['class'] . '"'; 
									$replace_val[$rkey] .= '<input type="text" name="cftsearch[' . urlencode($key) . '][' . $rkey . '][]" value="' . attribute_escape($_REQUEST['cftsearch'][urlencode($key)][$rkey][0]) . '"' . $class . ' />';
									break;		
								case 'checkbox':
									if ( $rval[0]['class'] ) $class = ' class="' . $rval[0]['class'] . '"'; 
									$values = $valueLabels = array();
									if ( $rkey == 0 ) :
										foreach( $rval as $rval2 ) :
											$values[] = $rval2['value'];
											$valueLabels[] = $rval2['valueLabel'];
										endforeach;
									else :
										$values = explode( '#', $rval[0]['value'] );
										$valueLabels = explode( '#', $rval[0]['valueLabel'] );
										$default = explode( '#', $rval[0]['default'] );
									endif;
									if ( count($values) > 1 ) :
										$replace_val[$rkey] .= '<ul>';
										$j=0;
										foreach( $values as $metavalue ) :
											$checked = '';
											$metavalue = trim($metavalue);
											if ( is_array($_REQUEST['cftsearch'][urlencode($key)][$rkey]) ) :
												if ( in_array($metavalue, $_REQUEST['cftsearch'][urlencode($key)][$rkey]) )
													$checked = ' checked="checked"';
												else
													$checked = '';
											endif;
											if ( in_array($metavalue, $default) && !$_REQUEST['cftsearch'][urlencode($key)][$rkey] )
												$checked = ' checked="checked"';

											$replace_val[$rkey] .= '<li><label><input type="checkbox" name="cftsearch[' . urlencode($key) . '][' . $rkey . '][]" value="' . attribute_escape($metavalue) . '"' . $class . $checked . '  /> ';			
											if ( $valueLabels[$j] ) $replace_val[$rkey] .= stripcslashes($valueLabels[$j]);
											else $replace_val[$rkey] .= stripcslashes($metavalue);
											$replace_val[$rkey] .= '</label></li>';
											$j++;
										endforeach;
										$replace_val[$rkey] .= '</ul>';
									else :
										$replace_val[$rkey] .= '<label><input type="checkbox" name="cftsearch[' . urlencode($key) . '][' . $rkey . '][]" value="' . attribute_escape(trim($values[0])) . '"' . $class . ' /> ';			
										if ( $valueLabel[0] ) $replace_val[$rkey] .= stripcslashes(trim($valueLabels[0]));
										else $replace_val[$rkey] .= stripcslashes(trim($values[0]));
										$replace_val[$rkey] .= '</label>';
									endif;
									break;
								case 'radio':
									if ( $rval[0]['class'] ) $class = ' class="' . $rval[0]['class'] . '"'; 
									$values = explode( '#', $rval[0]['value'] );
									$valueLabels = explode( '#', $rval[0]['valueLabel'] );
									$default = explode( '#', $rval[0]['default'] );
									if ( count($values) > 1 ) :
										$replace_val[$rkey] .= '<ul>';
										$j=0;
										foreach ( $values as $metavalue ) :
											$checked = '';
											$metavalue = trim($metavalue);
											if ( is_array($_REQUEST['cftsearch'][urlencode($key)][$rkey]) ) :
												if ( in_array($metavalue, $_REQUEST['cftsearch'][urlencode($key)][$rkey]) )
													$checked = ' checked="checked"';
												else
													$checked = '';
											endif;
											if ( in_array($metavalue, $default) && !$_REQUEST['cftsearch'][urlencode($key)][$rkey] )
												$checked = ' checked="checked"';
											$replace_val[$rkey] .= '<li><label><input type="radio" name="cftsearch[' . urlencode($key) . '][' . $rkey . '][]" value="' . attribute_escape($metavalue) . '"' . $class . $checked . ' /> ';			
											if ( $valueLabels[$j] ) $replace_val[$rkey] .= stripcslashes(trim($valueLabels[$j]));
											else $replace_val[$rkey] .= stripcslashes($metavalue);
											$replace_val[$rkey] .= '</label></li>';
											$j++;
										endforeach;
										$replace_val[$rkey] .= '</ul>';
									else :
										$replace_val[$rkey] .= '<label><input type="radio" name="cftsearch[' . urlencode($key) . '][]" value="' . attribute_escape(trim($values[0])) . '"' . $class . ' /> ';			
										if ( $valueLabels[0] ) $replace_val[$rkey] .= stripcslashes(trim($valueLabels[0]));
										else $replace_val[$rkey] .= stripcslashes(trim($values[0]));
										$replace_val[$rkey] .= '</label>';
									endif;
									break;
								case 'select':
									if ( $rval[0]['class'] ) $class = ' class="' . $rval[0]['class'] . '"'; 
									$values = explode( '#', $rval[0]['value'] );
									$valueLabels = explode( '#', $rval[0]['valueLabel'] );
									$default = explode( '#', $rval[0]['default'] );
									$replace_val[$rkey] .= '<select name="cftsearch[' . urlencode($key) . '][' . $rkey . '][]"' . $class . '>';
									$replace_val[$rkey] .= '<option value=""></option>';
									$j=0;
									foreach ( $values as $metaval ) :
										$metaval = trim($metaval);
										if ( in_array($metavalue, $default) && !$_REQUEST['cftsearch'][urlencode($key)][$rkey] )
												$checked = ' checked="checked"';

										if ( $_REQUEST['cftsearch'][urlencode($key)][$rkey][0] == $metaval ) $selected = ' selected="selected"';
										else $selected = "";
										$replace_val[$rkey] .= '<option value="' . attribute_escape($metaval) . '"' . $selected . '>';			
										if ( $valueLabels[$j] )
											$replace_val[$rkey] .= stripcslashes(trim($valueLabels[$j]));
										else
											$replace_val[$rkey] .= stripcslashes($metaval);
										$replace_val[$rkey] .= '</option>' . "\n";
										$j++;
									endforeach;
									$replace_val[$rkey] .= '</select>' . "\n";
									break;
							endswitch;			
						endforeach;
																		
						if ( $options['shortcode_format_use_php'][$format] )
							$output = preg_replace_callback("/(<\?php|<\?|< \?php)(.*?)\?>/si", array($this, 'EvalBuffer'), $output);
						$key = preg_quote($key, '/');
						$output = preg_replace('/\['.$key.'\](?!\[[0-9]+\])/', $replace_val[0], $output); 
						$output = preg_replace('/\['.$key.'\]\[([0-9]+)\](?!\[\])/e', '$replace_val[${1}]', $output);
						
					endforeach;
				endfor;
			endif;

			if ( $button === true )
				$output .= '<p><input type="submit" value="' . $search_label . '" class="cftsearch_submit" /></p>' . "\n";
			$output .= '<input type="hidden" name="cftsearch_submit" value="1" /></p>' . "\n";
			$output .= '</form>' . "\n";
		else :
			$fields = $this->get_custom_fields( $template );
	
			if ( $fields == null )
				return;
	
			$output = '<form method="get" action="/" id="cftsearch'.(int)$format.'">' . "\n";
			foreach( $fields as $key => $val) :
				if ( $val[0]['search'] == true ) :
					if ( !empty($val[0]['label']) && $options['custom_field_template_replace_keys_by_labels'] )
						$key = stripcslashes($val[0]['label']);
					$output .= '<dl>' ."\n";
					$output .= '<dt><label>' . $key . '</label></dt>' ."\n";
					foreach ( $val as $key2 => $val2 ) :
						$class = "";
						switch ( $val2['type'] ) :
							case 'text':
							case 'textfield':
							case 'textarea':
								if ( $val2['class'] ) $class = ' class="' . $val2['class'] . '"'; 
								$output .= '<dd><input type="text" name="cftsearch[' . urlencode($key) . '][' . $rkey . '][]" value="' . attribute_escape($_REQUEST['cftsearch'][urlencode($key)]) . '"' . $class . ' /></dd>';
								break;		
							case 'checkbox':
								if ( $val2['class'] ) $class = ' class="' . $val2['class'] . '"'; 
								$output .= '<dd><label><input type="checkbox" name="cftsearch[' . urlencode($key) . '][' . $rkey . '][]" value="' . attribute_escape($val2['value']) . '"' . $class . ' /> ';			
								if ( $val2['valueLabel'] )
									$output .= stripcslashes($val2['valueLabel']);
								else
									$output .= stripcslashes($val2['value']);
								$output .= '</label></dd>' . "\n";
								break;
							case 'radio':
								if ( $val2['class'] ) $class = ' class="' . $val2['class'] . '"'; 
								$values = explode( '#', $val2['value'] );
								$valueLabels = explode( '#', $val2['valueLabel'] );
								$i=0;
								foreach ( $values as $metaval ) :
									$metaval = trim($metaval);
									$output .= '<dd><label>' . '<input type="radio" name="cftsearch[' . urlencode($key) . '][' . $rkey . '][]" value="' . attribute_escape($metaval) . '"' . $class . ' /> ';			
									if ( $val2['valueLabel'] )
										$output .= stripcslashes(trim($valueLabels[$i]));
									else
										$output .= stripcslashes($metaval);
									$i++;
									$output .= '</label></dd>' . "\n";
								endforeach;
								break;
							case 'select':
								if ( $val2['class'] ) $class = ' class="' . $val2['class'] . '"'; 
								$values = explode( '#', $val2['value'] );
								$valueLabels = explode( '#', $val2['valueLabel'] );
								$output .= '<dd><select name="cftsearch[' . urlencode($key) . '][' . $rkey . '][]"' . $class . '>';
								$output .= '<option value=""></option>';
								$i=0;
								foreach ( $values as $metaval ) :
									$metaval = trim($metaval);
									if ( $_REQUEST['cftsearch'][urlencode($key)] == $metaval ) $selected = ' selected="selected"';
									else $selected = "";
									$output .= '<option value="' . attribute_escape($metaval) . '"' . $selected . '>';			
									if ( $val2['valueLabel'] )
										$output .= stripcslashes(trim($valueLabels[$i]));
									else
										$output .= stripcslashes($metaval);
									$output .= '</option>' . "\n";
									$i++;
								endforeach;
								$output .= '</select></dd>' . "\n";
								break;
						endswitch;
					endforeach;
					$output .= '</dl>' ."\n";
				endif;
			endforeach;
			if ( $button == true )
				$output .= '<p><input type="submit" value="' . $search_label . '" class="cftsearch_submit" /></p>' . "\n";
			$output .= '<input type="hidden" name="cftsearch_submit" value="1" /></p>' . "\n";
			$output .= '</form>' . "\n";
		endif;
		
		return stripcslashes($output);
	}
	
	function custom_field_template_posts_where($where) {
		global $wp_query, $wp_version, $wpdb;
		$options = $this->get_custom_field_template_data();
		
		$wp_query->is_search = 1;
		$wp_query->is_page = '';
		$wp_query->is_singular = '';
		
		$original_where = $where;
						
		$where = '';

		$count = count($options['custom_fields']);
		if ( $count ) :
			for ($i=0;$i<$count;$i++) :
				$fields = $this->get_custom_fields( $i );
				foreach ( $fields as $key => $val ) :
					$replace[$key][0] = $val;
					$search = array();
					if($val[0]['searchType']) eval('$search["type"] =' . stripslashes($val[0]['searchType']));
					if($val[0]['searchValue']) eval('$search["value"] =' . stripslashes($val[0]['searchValue']));
					if($val[0]['searchOperator']) eval('$search["operator"] =' . stripslashes($val[0]['searchOperator']));
						
					foreach ( $search as $skey => $sval ) :
						$j = 1;
						foreach ( $sval as $sval2 ) :
							$replace[$key][$j][0][$skey] = $sval2;
							$j++;
						endforeach;
					endforeach;
				endforeach;
			endfor;
		endif;
				
		if ( is_array($_REQUEST['cftsearch']) ) :
			foreach ( $_REQUEST['cftsearch'] as $key => $val ) :
				$key = rawurldecode($key);
				foreach( $val as $key2 => $val2 ) :
					foreach( $val2 as $val3 ) :
						if ( $val3 ) :
							switch( $replace[$key][$key2][0]['operator'] ) :
								case '<=' :
								case '>=' :
								case '<' :
								case '>' :
								case '=' :
								case '<>' :
								case '<=>':
									$where .= " AND ROW(ID,1) IN (SELECT post_id,count(post_id) FROM wp_postmeta WHERE (" . $wpdb->postmeta . ".meta_key = '" . $key . "' AND " . $wpdb->postmeta . ".meta_value " . $replace[$key][$key2][0]['operator'] . " " . trim($val3) . ") GROUP BY post_id) ";
									break;
								default :
									$where .= " AND ROW(ID,1) IN (SELECT post_id,count(post_id) FROM wp_postmeta WHERE (" . $wpdb->postmeta . ".meta_key = '" . $key . "' AND " . $wpdb->postmeta . ".meta_value LIKE '%" . trim($val3) . "%') GROUP BY post_id) ";
									break;
								endswitch;
						endif;
					endforeach;
				endforeach;
			endforeach;
		endif;
		
		if ( $_REQUEST['s'] ) :
			if ( function_exists('mb_split') ) :
				$s = mb_split('\s', $_REQUEST['s']);
			else:
				$s = split('\s', $_REQUEST['s']);
			endif;
			foreach ( $s as $v ) :
				if ( !empty($v) ) :
					$where .= " AND ROW(ID,1) IN (SELECT post_id,count(post_id) FROM wp_postmeta WHERE (" . $wpdb->postmeta . ".meta_value LIKE '%" . trim($v) . "%') GROUP BY post_id) ";
				endif;
			endforeach;
		endif;

		if ( is_array($_REQUEST['cftcategory_in']) ) :
			$ids = get_objects_in_term($_REQUEST['cftcategory_in'], 'category');
			if ( is_array($ids) && count($ids) > 0 ) :
				$in_posts = "'" . implode("', '", $ids) . "'";
				$where .= " AND ID IN (" . $in_posts . ")";
			endif;
			$where .= " AND " . $wpdb->posts . ".post_type = 'post'"; 
		endif;
		if ( is_array($_REQUEST['cftcategory_not_in']) ) :
			$ids = get_objects_in_term($_REQUEST['cftcategory_not_in'], 'category');
			if ( is_array($ids) && count($ids) > 0 ) :
				$in_posts = "'" . implode("', '", $ids) . "'";
				$where .= " AND ID NOT IN (" . $in_posts . ")";
			endif;
		endif;
		
		$where .= " AND `".$wpdb->posts."`.post_status = 'publish' GROUP BY wp_posts.ID";
		//if ( $_REQUEST['s'] ) $where .= $original_where;
						
		return $where;
	}

	function custom_field_template_posts_join($sql) {
		if ( ($_REQUEST['order'] == 'ASC' || $_REQUEST['order'] == 'DESC') && $_REQUEST['orderby'] ) :
			global $wpdb;

			$sql = " LEFT JOIN `" . $wpdb->postmeta . "` AS meta ON (`" . $wpdb->posts . "`.ID = meta.post_id AND meta.meta_key = '". $_REQUEST['orderby'] . "')"; 
			return $sql;
		endif;
	}

	function custom_field_template_posts_orderby($sql) {
		global $wpdb;

		if ( empty($_REQUEST['order']) || ((strtoupper($_REQUEST['order']) != 'ASC') && (strtoupper($_REQUEST['order']) != 'DESC')) )
			$_REQUEST['order'] = 'DESC';

		if ( $_REQUEST['orderby'] ) :
			if ( in_array($_REQUEST['orderby'], array('post_author', 'post_date', 'post_title', 'post_modified', 'menu_order', 'post_parent', 'ID')) ):
				$sql = "`".$wpdb->posts."`.".$_REQUEST['orderby']." ".$_REQUEST['order'];
			elseif ( $_REQUEST['orderby']=='rand' ):
				$sql = "RAND()";
			else:
				if ( in_array($_REQUEST['cast'], array('binary', 'char', 'date', 'datetime', 'signed', 'time', 'unsigned')) ) :
					$sql = " CAST(meta.meta_value AS " . $_REQUEST['cast'] . ") " . $_REQUEST['order'];
				else :
					$sql = " meta.meta_value " . $_REQUEST['order'];
				endif;
			endif;
			return $sql;
		endif;

		$sql = "`".$wpdb->posts."`.post_date ".$_REQUEST['order'];
		return $sql;
	}
	
	function custom_field_template_post_limits($sql_limit) {
		global $wp_query;

		if ( !$sql_limit ) return;
		list($offset, $old_limit) = explode(',', $sql_limit);
		$limit = (int)$_REQUEST['limit'];
		if ( !$limit )
			$limit = trim($old_limit);
		$wp_query->query_vars['posts_per_page'] = $limit;
		$offset = ($wp_query->query_vars['paged'] - 1) * $limit;
		if ( $offset < 0 ) $offset = 0;

		return ( $limit ? "LIMIT $offset, $limit" : '' );
	}
	
	function EvalBuffer($string) {
		ob_start();
		eval("$string[2];");
		$ret = ob_get_contents();
		ob_end_clean();
		return $ret;
	}
	
	function set_value_count($key, $value) {
		global $wpdb;
		
		$query = $wpdb->prepare("SELECT COUNT(meta_id) FROM `". $wpdb->postmeta."` WHERE `". $wpdb->postmeta."`.meta_key = %s AND `". $wpdb->postmeta."`.meta_value = %s;", $key, $value);
		$count = $wpdb->get_var($query);
				
		return (int)$count;
	}
	
	function get_value_count($key = '', $value = '') {
		$options = $this->get_custom_field_template_data();
		
		if ( $key && $value ) :
			return $options['value_count'][$key][$value];
		else:
			return $options['value_count'];
		endif; 
	}
	
	function custom_field_template_delete_post($post_id) {
		global $wpdb;
		$options = $this->get_custom_field_template_data();
		$id = $options['posts'][$post_id];
		
		if ( is_numeric($id) ) :
			$fields = $this->get_custom_fields($id);
		
			if ( $fields == null )
				return;
					
			foreach( $fields as $title	=> $data) :
				$name = $this->sanitize_name( $title );
				$title = $wpdb->escape(stripcslashes(trim($title)));
				for($i = 0; $i<count($data); $i++) :
					$value = get_post_meta($post_id, $title);
					if ( is_array($value) ) :
						foreach ( $value as $val ) :
							if ( $data[$i]['valueCount'] == true ) :
								$count = $this->set_value_count($title, $val)-1;
								if ( $count<=0 )
									unset($options['value_count'][$title][$val]);
								else
									$options['value_count'][$title][$val] = $count;
							endif;
						endforeach;
					else :
						if ( $data[$i]['valueCount'] == true ) :
							$count = $this->set_value_count($title, $value)-1;
							if ( $count<=0 )
								unset($options['value_count'][$title][$value]);
							else
								$options['value_count'][$title][$value] = $count;
						endif;
					endif;
				endfor;
			endforeach;
		endif;
		update_option('custom_field_template_data', $options);
	}
	
	function custom_field_template_rebuild_value_counts() {
		global $wpdb;
		$options = $this->get_custom_field_template_data();
		unset($options['value_count']);

		if ( is_array($options['custom_fields']) ) :
			for($j=0;$j<count($options['custom_fields']);$j++) :
		
				$fields = $this->get_custom_fields($j);
		
				if ( $fields == null )
					return;
					
				foreach( $fields as $title	=> $data) :
					$name = $this->sanitize_name( $title );
					$title = $wpdb->escape(stripcslashes(trim($title)));
					for($i = 0; $i<count($data); $i++) :
						if ( $data[$i]['valueCount'] == true ) :
							$query = $wpdb->prepare("SELECT COUNT(meta_id) as meta_count, `". $wpdb->postmeta."`.meta_value FROM `". $wpdb->postmeta."` WHERE `". $wpdb->postmeta."`.meta_key = %s GROUP BY `". $wpdb->postmeta."`.meta_value;", $title);
							$result = $wpdb->get_results($query, ARRAY_A);
							if ( $result ) :
								foreach($result as $val) :
									$options['value_count'][$title][$val['meta_value']] = $val['meta_count'];
								endforeach;
							endif;
						endif;
					endfor;
				endforeach;
			endfor;
		endif;
		update_option('custom_field_template_data', $options);
	}
}

$custom_field_template = new custom_field_template();
?>