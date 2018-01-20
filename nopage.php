<?php
/*
Plugin Name: Nopage
Description: Adds the option to disable a page and only use it as a data container
Author: Ben Steers
Author URI: http://bensteers.me
Version: 0.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

include_once(plugin_dir_path(__FILE__).'mods.php');

class Nopage_Plugin{

	public $name = 'nopage';
	public $default_is_smart = true;
	public $post_types = array();

	function __construct(){
		add_action('init', array($this, 'add_actions'));
	}

	function add_actions(){
		// get post types
		$this->post_types = get_post_types(array(
			'public'   => true,
			'_builtin' => false
		));

		// redirect if nopage
		add_action('wp', array($this, 'check_to_redirect_page'));

		// permalink
		add_filter('post_type_link', array($this, 'alter_permalink'), 10, 2);

		// options page
		add_filter( 'admin_init' , array( $this , 'register_options' ) );

		// add post type columns
		foreach((array)$this->post_types as $pt){
			add_filter('manage_'.$pt.'_posts_columns', array($this, 'add_admin_column'), 20, 1);
			add_action('manage_'.$pt.'_posts_custom_column', array($this, 'manage_admin_column'), 10, 2);
			add_action( 'save_post_'.$pt, array($this, 'save_metabox_data') );
		}

		// metabox
		add_action( 'add_meta_boxes', array($this, 'add_metabox'));

		// quick/bulk edit
		add_action('bulk_edit_custom_box',  array($this, 'add_quick_edit'), 10, 2);
		add_action('quick_edit_custom_box',  array($this, 'add_quick_edit'), 10, 2);

		// save quick/bulk edit
		add_action('save_post', array($this, 'save_quick_post'), 10, 1);
		add_action('wp_ajax_nopage_bulk_edit', array($this, 'save_bulk_post'));

		// style/script
		add_action('admin_head', array($this, 'admin_column_style'));
		add_action('admin_enqueue_scripts', array($this, 'admin_edit_script'));
	}




	// redirect
	function check_to_redirect_page(){
		if($this->is_nopage(null, false)){
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			get_template_part( 404 ); 
			exit();
		}
	}

	// stop get permalink
	function alter_permalink($link, $post){
		if($post && $this->is_nopage($post->ID, false))
			$link = '';
		return $link;
	}







	// Options Page
	function register_options() {
        register_setting( 'reading', $this->name, 'esc_attr' );
        add_settings_field(
        	$this->name, 
        	'<label for="nopage">'.__('Nopage' , 'wordpress' ).'</label>' , 
        	array($this, 'options_field_html') , 
        	'reading' 
        );
    }
    function options_field_html() {
        $value = $this->is_smart(); ?>		
		<p>
			<select name="<?= $this->name ?>" id="nopage">
				<option value="1" <?=  $value?'selected':'' ?>>Check Content</option>
				<option value="0" <?= !$value?'selected':'' ?>>Manual</option>
			</select>
			<small>When set to "Check Content" and a post's nopage is set to default, a page will only display if there is content.</small>
		</p> <?php
    }








	// Register column
	function add_admin_column($columns){
		return $columns + array($this->name => __(''));
	}

	// Get column value
	function manage_admin_column($column_name, $post_id){
		if ($column_name != $this->name) return;
		$this->print_icons($post_id);
	}

	// Print icons that indicate nopage status
	function print_icons($post_id=null){
		$is_nopage = $this->is_nopage($post_id, false); // display smart value (page setting + check page content)
		$is_set_nopage = $this->is_set_nopage($post_id); // display page setting
		?> 

		<span class='fa-stack'>
			<?php if($is_set_nopage === null && $this->is_smart()){ ?>
			<i class="fa fa-bolt" title='Set to auto'></i>
			<?php } ?>
			<i class="fa fa-<?= $is_nopage ? 'eye-slash' : 'eye' ?>" title='Page is <?= $is_nopage ? 'visible' : 'invisible' ?>'></i>
		</span>
		<input id='wpedit-nopage<?= $post_id ?>' type='hidden' value='<?= $is_set_nopage === null ? '' : (int)$is_set_nopage ?>'> <?php
	}








    // Metabox
	function add_metabox(){
		if(!$this->is_supported_post_type()) return;
		add_meta_box(
			'nopage_settings',
			__( 'Nopage', 'wordpress' ),
			array($this, 'metabox_callback'),
			null,
			'side',
			'low'
		);
	}

	// print metabox
	function metabox_callback(){
		echo wp_nonce_field( basename( __FILE__ ), 'nopage_nonce' );
		$value = $this->is_set_nopage(); ?>		
		<p>
			<select name="<?= $this->name ?>" id="nopage">
				<option value='' <?= $value === null  ?'selected':'' ?>>Auto</option>
				<option value=0  <?= $value === false ?'selected':'' ?>>Has Page</option>
				<option value=1  <?= $value === true  ?'selected':'' ?>>No Page</option>
			</select>
			<?= $this->print_icons(); ?>
		</p> <?php
	}

	function save_metabox_data($post_id){
		if(!isset($_POST['nopage_nonce']) || !wp_verify_nonce($_POST['nopage_nonce'], basename(__FILE__)))
			return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;
		if ( !current_user_can('edit_post', $post_id) )
			return;

		return $this->update_post($post_id, $_POST[$this->name]);
	}







	// print bulk/quick edit
	function add_quick_edit($column_name, $post_type=null){
		if ($column_name != $this->name) return; ?>
		
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<label for="<?= $this->name ?>" class='inline-edit-nopage alignleft'>
					<span class="title">Nopage</span>
					<select name="wpedit-nopage" id="<?= $this->name ?>">
						<option value='-1'> -- </option>
						<option value=''>Auto</option>
						<option value=0>Has Page</option>
						<option value=1>No Page</option>
					</select> 
				</label>
			</div>
	    </fieldset>
		<?php
	}
	
	// save quick edit
	function save_quick_post($post_id){
		if(!isset($_POST['wpedit-nopage'])) // if val is set
			return $post_id;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) // if not autosaving
			return $post_id; 
        if(!$this->is_supported_post_type($post_id)) // if supported post type
			return $post_id;
        if(!current_user_can('edit_post', $post_id)) // if has permission to edit
			return $post_id;
		
		return $this->update_post($post_id, $_POST['wpedit-nopage']);
	}

	// save bulk edit
	function save_bulk_post(){
		$post_ids = isset($_POST['post_ids']) ? (array)$_POST['post_ids'] : [];
		$val = isset($_POST['wpedit_nopage']) ? (string)$_POST['wpedit_nopage'] : null;
		
		foreach( $post_ids as $post_id ){
			$this->update_post($post_id, $val);
		}
	}

	// generic quick/bulk edit update function
	function update_post($post_id, $val){
		if($val == -1); // do nothing
		else if($val == '') // set to blank
			delete_post_meta($post_id, $this->name);
		else
			update_post_meta($post_id, $this->name, $val);
		return $post_id;
	}










	// Helper functions

	// If true, page does not exist
	function is_nopage($id=null, $check_user=true){
		$is_nopage = false;

		// Only work on single pages and not admin pages
		if( !$id && (!is_single() || is_admin()) )
			$is_nopage = false;

		// Check if nopage is enabled for this post type
		elseif(!$this->is_supported_post_type($id))
			$is_nopage = false;
		
		// if is authenticated and we're checking user
		elseif($check_user && $this->is_auth_user()) 
			$is_nopage = false;
		
		else{
			$post_setting = $this->is_set_nopage($id);
			// if post setting not explicitly set and is smart with no content
			if($post_setting === null)
				$is_nopage = $this->is_smart() && !$this->has_content($id);
			else
				$is_nopage = (bool)$post_setting;
		}

		return apply_filters('nopage/is_nopage', $is_nopage, $id);
	}

	// Get page setting
	function is_set_nopage($id=null){
		$val = get_post_meta($id?:get_the_ID(), $this->name, true);
		return apply_filters('nopage/is_set_nopage', $val === '' ? null : (bool)$val, $id);
	}
	// Check global option to see if content checking is enabled
	function is_smart(){
		$is_smart = get_option( $this->name, $this->default_is_smart );
		return apply_filters('nopage/is_smart', $is_smart);
	}
	// Check if page has content
	function has_content($id=null){
		$has_content = strlen(trim(get_the_content($id)));
		return apply_filters('nopage/has_content', $has_content, $id);
	}
	// check if post type is among supported types
	function is_supported_post_type($id=null){
		$is_supported_post_type = in_array($this->get_post_type($id), $this->post_types);
		return apply_filters('nopage/is_supported_post_type', $is_supported_post_type, $id);
	}
	// If user has permissions, (to allow page to be displayed)
	function is_auth_user(){
		$is_auth_user = current_user_can('editor') || current_user_can('administrator') || current_user_can('author');
		return apply_filters('nopage/is_auth_user', $is_auth_user, $id);
	}


	private function get_post_type($id=null){
		global $typenow, $current_screen;
		$post = get_post($id);

		$post_type = null;
		if ( $post && $pt = get_post_type($post->ID) )
			$post_type = $pt;
		elseif( $typenow )
			$post_type = $typenow;
		elseif( $current_screen && $current_screen->post_type )
			$post_type = $current_screen->post_type;
		elseif( isset( $_REQUEST['post_type'] ) )
			$post_type = sanitize_key( $_REQUEST['post_type'] );
		return apply_filters('nopage/get_post_type', $post_type);
	}




	

	// set column styles
	function admin_column_style() { ?>
		<style type="text/css">
		.column-<?= $this->name ?> { 
			text-align: center; 
			width: 20px !important; 
			overflow: hidden;
		}
		</style> <?php
	}

	// add update scripts
	function admin_edit_script() { 
		wp_enqueue_script('nopage-js', plugin_dir_url( __FILE__ ).'nopage.js', array('jquery', 'inline-edit-post'));
	}
}

new Nopage_Plugin();




if ( ! function_exists('write_log')) {
   function write_log ( $log )  {
      if ( is_array( $log ) || is_object( $log ) ) {
         error_log( print_r( $log, true ) );
      } else {
         error_log( $log );
      }
   }
}