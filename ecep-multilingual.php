<?php
/**
 * Plugin Name: ECEP Multilingual
 * Plugin URI: https://github.com/thienphuoc1990/ecep-multilingual
 * Description: This is a plugin for multi languages on 1 site.
 * Version: 1.0 
 * Author: Phuoc Dinh
 * Author URI: https://github.com/thienphuoc1990
 * License: GPLv2 or later 
 */
?>
<?php

global $ecep_multilingual_db_version;
$ecep_multilingual_db_version = '1.0';

global $expected_locale;
$expected_locale = array(
	array( "lang_code" => "vi", "lang_name" => "Vietnamese" ),
	array( "lang_code" => "en_US", "lang_name" => "English" ),
);

global $default_locale;
$default_locale = "en_US";

add_action( 'plugins_loaded', 'set_home_url_multilingual' );

function set_home_url_multilingual(){
	global $default_locale;
	global $the_home;
	$the_home = (get_locale() == $default_locale) ? get_home_url() : get_home_url(null, get_locale());
}

function ecep_multilingual_install() {
	global $wpdb;
	global $ecep_multilingual_db_version;

	$table_name = $wpdb->prefix . 'languages_relations';
	
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		original_id text NOT NULL,
		translated_id  text NOT NULL,
		original_lang tinytext NOT NULL,
		translated_lang tinytext NOT NULL,
		type tinytext NOT NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( 'ecep_multilingual_db_version', $ecep_multilingual_db_version );
}

function ecep_multilingual_install_data() {
	global $wpdb;
	global $default_locale;
	$type = ['course', 'page'];
	$original_lang = $default_locale;

	$translated_id = '';
	$translated_lang = '';
	foreach ($type as $item) {
		// Get all post_id
		$table_lang_rel = $wpdb->prefix . 'languages_relations'; 
		$sql_string = "select $wpdb->posts.ID from $wpdb->posts";
		// Where condition
		$sql_string .= " where post_type = '".$item."'";
		$courses = $wpdb->get_results($sql_string);
		// Foreach post, get post metadata, insert to table_lang_rel original_id, original_lang
		foreach ($courses as $obj) {
			$original_id = $obj->ID;
		
			$wpdb->insert( 
				$table_lang_rel, 
				array( 
					'original_id' => $original_id, 
					'translated_id' => $translated_id, 
					'original_lang' => $original_lang, 
					'translated_lang' => $translated_lang, 
					'type' => $item, 
				) 
			);
		}
	}
}

function ecep_multilingual_remove_data(){
	global $wpdb;
	$table_lang_rel = $wpdb->prefix . 'languages_relations';
	$wpdb->query('TRUNCATE TABLE '.$table_lang_rel);
}

register_activation_hook( __FILE__, 'ecep_multilingual_install' );
register_activation_hook( __FILE__, 'ecep_multilingual_install_data' );
register_deactivation_hook( __FILE__, 'ecep_multilingual_remove_data' );

function wpsx_redefine_locale($locale) {
	global $expected_locale;
    $lang = explode('/', $_SERVER['REQUEST_URI']);

    // here change to english if requested
    if ( !is_admin() ) {
		foreach ($expected_locale as $item) {
			if($item['lang_code'] == $lang[1]){
				$locale = $lang[1];
				break;
			}
		}
    }

    return $locale;
}
add_filter('locale','wpsx_redefine_locale',10);  

add_action( 'after_setup_theme', 'register_multilingual_menu' );

function register_multilingual_menu() {
	global $_wp_registered_nav_menus;
	global $expected_locale;
	$old_menu = $_wp_registered_nav_menus;
	$new_menu = [];
	foreach ($expected_locale as $item) {
		foreach ($old_menu as $key => $value) {
			$new_menu[$key.'_'.$item['lang_code']] = $value.' '.$item['lang_name'];
		}
	}
	$_wp_registered_nav_menus = $new_menu;
}

function modify_multilingual_menu_args( $args ) {
	$locale = get_locale();
	$args['theme_location'] = $args['theme_location'].'_'.$locale;

	return $args;
}

add_filter( 'wp_nav_menu_args', 'modify_multilingual_menu_args' );

add_filter('manage_course_posts_columns', 'modify_course_table_head');
function modify_course_table_head( $defaults ) {
	global $expected_locale;
	foreach ($expected_locale as $item) {
		$defaults[$item['lang_code']]  = $item['lang_name'];
	}
    return $defaults;
}

add_action( 'manage_course_posts_custom_column', 'modify_course_table_content', 10, 2 );

function modify_course_table_content( $column_name, $post_id ) {
	global $wpdb;
	global $expected_locale;
	$table_lang_rel = $wpdb->prefix . 'languages_relations';
	$original_id = $post_id;

	// Find in table_lang_rel have record with id suitable
	$sql_string = "select * from ".$table_lang_rel;

	// Where condition
	$sql_string .= " where translated_id = ".$post_id;

	$courses_translated = $wpdb->get_results($sql_string);

	if(count($courses_translated) > 0){
		foreach ($courses_translated as $obj) {
			$original_id = $obj->original_id;
		}
	}

	// Find in table_lang_rel have record with id suitable
	$sql_string = "select * from ".$table_lang_rel;

	// Where condition
	$sql_string .= " where original_id = ".$original_id;

	$courses_original = $wpdb->get_results($sql_string);
	$arr = [];
	if(count($courses_original) > 0){
		foreach ($courses_original as $obj) {
			if(empty($arr[$obj->original_lang])){
				$arr[$obj->original_lang] = $obj->original_id;
			}

			if(isset($obj->translated_lang) && $obj->translated_lang != ''){
				$arr[$obj->translated_lang] = $obj->translated_id;
			}
		}
	}

	foreach ($expected_locale as $item) {
		$defaults[$item['lang_code']]  = $item['lang_name'];
		if($column_name == $item['lang_code']){
			if(isset($arr[$item['lang_code']]) && $arr[$item['lang_code']] != ''){
				echo '<a href="'.get_edit_post_link( $arr[$item['lang_code']] ).'">edit</a>';
			}else{
				if (current_user_can('edit_posts')) {
					echo '<a href="' . wp_nonce_url('admin.php?action=duplicate_course_as_draft&post=' . $original_id.'&lang_code='.$item['lang_code'], basename(__FILE__), 'duplicate_nonce' ) . '" title="Create this item" rel="permalink">Create</a>';
				}
			}
		}
	}
}

/*
 * Function creates post duplicate as a draft and redirects then to the edit post screen
 */
function duplicate_course_as_draft(){
	global $wpdb;
	if (! ( isset( $_GET['post']) || isset( $_POST['post'])  || ( isset($_REQUEST['action']) && 'duplicate_course_as_draft' == $_REQUEST['action'] ) ) ) {
		wp_die('No post to duplicate has been supplied!');
	}
 
	/*
	 * Nonce verification
	 */
	if ( !isset( $_GET['duplicate_nonce'] ) || !wp_verify_nonce( $_GET['duplicate_nonce'], basename( __FILE__ ) ) )
		return;
 
	/*
	 * get the original post id
	 */
	$post_id = (isset($_GET['post']) ? absint( $_GET['post'] ) : absint( $_POST['post'] ) );
	/*
	 * and all the original post data then
	 */
	$post = get_post( $post_id );
 
	/*
	 * get the new lang code
	 */
	$lang_code = (isset($_GET['lang_code']) ? $_GET['lang_code'] : $_POST['lang_code'] );
 
	/*
	 * if you don't want current user to be the new post author,
	 * then change next couple of lines to this: $new_post_author = $post->post_author;
	 */
	$current_user = wp_get_current_user();
	$new_post_author = $current_user->ID;
 
	/*
	 * if post data exists, create the post duplicate
	 */
	if (isset( $post ) && $post != null) {
 
		/*
		 * new post data array
		 */
		$args = array(
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_author'    => $new_post_author,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_name'      => $post->post_name,
			'post_parent'    => $post->post_parent,
			'post_password'  => $post->post_password,
			'post_status'    => 'draft',
			'post_title'     => $post->post_title,
			'post_type'      => $post->post_type,
			'to_ping'        => $post->to_ping,
			'menu_order'     => $post->menu_order,
		);
 
		/*
		 * insert the post by wp_insert_post() function
		 */
		$new_post_id = wp_insert_post( $args );
 
		/*
		 * get all current post terms ad set them to the new post draft
		 */
		$taxonomies = get_object_taxonomies($post->post_type); // returns array of taxonomy names for post type, ex array("category", "post_tag");
		foreach ($taxonomies as $taxonomy) {
			$post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
			wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
		}
 
		/*
		 * duplicate all post meta just in two SQL queries
		 */
		$post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id");
		if (count($post_meta_infos)!=0) {
			$sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
			foreach ($post_meta_infos as $meta_info) {
				$meta_key = $meta_info->meta_key;
				if( $meta_key == '_wp_old_slug' ) continue;
				$meta_value = addslashes($meta_info->meta_value);
				$sql_query_sel[]= "SELECT $new_post_id, '$meta_key', '$meta_value'";
			}
			$sql_query.= implode(" UNION ALL ", $sql_query_sel);
			$wpdb->query($sql_query);
		}
 
 
		/*
		 * insert to language relations table
		 */

		$table_lang_rel = $wpdb->prefix . 'languages_relations';
		$sql_string = "select * from ".$table_lang_rel." where original_id = ".$post_id;
		$courses_original = $wpdb->get_results($sql_string);
		$inserted = false;
		foreach ($courses_original as $obj) {
			if( !is_null($obj->original_id) ){
				if(!$inserted){
					$result = $wpdb->update( 
						$table_lang_rel, 
						array( 
							'original_id' => $obj->original_id, 
							'translated_id' => $new_post_id, 
							'original_lang' => $obj->original_lang, 
							'translated_lang' => $lang_code, 
							'type' => $obj->type, 
						),
						array( 'id'	=>	$obj->id )
					);

					if (is_wp_error($result)) {
						$errors = $result->get_error_messages();
						foreach ($errors as $error) {
							echo $error;
						}
					}else{
						$inserted = true;
					}
				}
			}
		}
	
		if(!$inserted){
			$wpdb->insert( 
				$table_lang_rel, 
				array( 
					'original_id' => $post_id, 
					'translated_id' => $new_post_id, 
					'original_lang' => $courses_original[0]->original_lang, 
					'translated_lang' => $lang_code, 
					'type' => $type, 
				) 
			);
		}
 
		/*
		 * finally, redirect to the edit post screen for the new draft
		 */
		wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
		exit;
	} else {
		wp_die('Post creation failed, could not find original post: ' . $post_id);
	}
}
add_action( 'admin_action_duplicate_course_as_draft', 'duplicate_course_as_draft' );

add_action( 'admin_init', 'multilingual_init' );

function multilingual_init() {
    add_action( 'delete_post', 'multilingual_delete', 10 );
}

function multilingual_delete( $pid ) {
    global $wpdb;
	$table_lang_rel = $wpdb->prefix . 'languages_relations';
	$sql_string = "select * from ".$table_lang_rel." where original_id = ".$pid." or translated_id = ".$pid;
	$rows = $wpdb->get_results($sql_string);
	foreach ($rows as $obj) {
		if($obj->original_id == $pid){
			$obj->original_id = '';
			$obj->original_lang = '';
		}

		if($obj->translated_id == $pid){
			$obj->translated_id = '';
			$obj->translated_lang = '';
		}

		$result = $wpdb->update( 
			$table_lang_rel, 
			array( 
				'original_id' => $obj->original_id, 
				'translated_id' => $obj->translated_id, 
				'original_lang' => $obj->original_lang, 
				'translated_lang' => $obj->translated_lang, 
				'type' => $obj->type, 
			),
			array( 'id'	=>	$obj->id )
		);

		if (is_wp_error($result)) {
			$errors = $result->get_error_messages();
			foreach ($errors as $error) {
				echo $error;
			}
		}
	}
}

function modify_page_table_head( $defaults ) {
	global $expected_locale;
	foreach ($expected_locale as $item) {
		$defaults[$item['lang_code']]  = $item['lang_name'];
	}
    return $defaults;
}

add_filter('manage_pages_columns', 'modify_page_table_head');

function modify_page_table_content( $column_name, $post_id ) {
	global $wpdb;
	global $expected_locale;
	$table_lang_rel = $wpdb->prefix . 'languages_relations';
	$original_id = $post_id;

	// Find in table_lang_rel have record with id suitable
	$sql_string = "select * from ".$table_lang_rel;

	// Where condition
	$sql_string .= " where translated_id = ".$post_id;

	$courses_translated = $wpdb->get_results($sql_string);

	if(count($courses_translated) > 0){
		foreach ($courses_translated as $obj) {
			$original_id = $obj->original_id;
		}
	}

	// Find in table_lang_rel have record with id suitable
	$sql_string = "select * from ".$table_lang_rel;

	// Where condition
	$sql_string .= " where original_id = ".$original_id;

	$courses_original = $wpdb->get_results($sql_string);
	$arr = [];
	if(count($courses_original) > 0){
		foreach ($courses_original as $obj) {
			if(empty($arr[$obj->original_lang])){
				$arr[$obj->original_lang] = $obj->original_id;
			}

			if(isset($obj->translated_lang) && $obj->translated_lang != ''){
				$arr[$obj->translated_lang] = $obj->translated_id;
			}
		}
	}

	foreach ($expected_locale as $item) {
		$defaults[$item['lang_code']]  = $item['lang_name'];
		if($column_name == $item['lang_code']){
			if(isset($arr[$item['lang_code']]) && $arr[$item['lang_code']] != ''){
				echo '<a href="'.get_edit_post_link( $arr[$item['lang_code']] ).'">edit</a>';
			}else{
				if (current_user_can('edit_posts')) {
					echo '<a href="' . wp_nonce_url('admin.php?action=duplicate_course_as_draft&post=' . $original_id.'&lang_code='.$item['lang_code'], basename(__FILE__), 'duplicate_nonce' ) . '" title="Create this item" rel="permalink">Create</a>';
				}
			}
		}
	}
}

add_action( 'manage_pages_custom_column', 'modify_page_table_content', 10, 2 );

if ( !class_exists('ECEP_Custom_Nav')) {
    class ECEP_Custom_Nav {
        public function add_nav_menu_meta_boxes() {
        	add_meta_box(
        		'ls_login_nav_link',
        		__('Language Switch'),
        		array( $this, 'nav_menu_link'),
        		'nav-menus',
        		'side',
        		'low'
        	);
        }
        
        public function nav_menu_link() {
        	global $expected_locale;
        	global $default_locale; ?>
        	<div id="posttype-language-switch" class="posttypediv">
        		<div id="tabs-panel-language-switch" class="tabs-panel tabs-panel-active">
    				<ul id ="language-switch-checklist" class="categorychecklist form-no-clear">
        			<?php  
        			foreach ($expected_locale as $item) {
        				$item_url = ($item['lang_code'] == $default_locale) ? get_home_url() : get_home_url(null, $item['lang_code']);
        				?>
        				<li>
        					<label class="menu-item-title">
        						<input type="checkbox" class="menu-item-checkbox" name="menu-item[-1][menu-item-object-id]" value="-1"> <?php _e($item['lang_name']); ?>
        					</label>
        					<input type="hidden" class="menu-item-type" name="menu-item[-1][menu-item-type]" value="custom">
        					<input type="hidden" class="menu-item-title" name="menu-item[-1][menu-item-title]" value="<?php echo $item['lang_name']; ?>">
        					<input type="hidden" class="menu-item-url" name="menu-item[-1][menu-item-url]" value="<?php echo $item_url; ?>">
        					<input type="hidden" class="menu-item-classes" name="menu-item[-1][menu-item-classes]" value="language-switch-pop">
        				</li>
        				<?php
        			}
        			?>
        			</ul>
        		</div>
        		<p class="button-controls">
        			<span class="list-controls">
        				<a href="/wordpress/wp-admin/nav-menus.php?page-tab=all&amp;selectall=1#posttype-page" class="select-all">Select All</a>
        			</span>
        			<span class="add-to-menu">
        				<input type="submit" class="button-secondary submit-add-to-menu right" value="Add to Menu" name="add-post-type-menu-item" id="submit-posttype-language-switch">
        				<span class="spinner"></span>
        			</span>
        		</p>
        	</div>
        <?php }
    }
}
$custom_nav = new ECEP_Custom_Nav;
add_action('admin_init', array($custom_nav, 'add_nav_menu_meta_boxes'));
?>