<?php
/*
Plugin Name: Shortcode Generator - Code Snippets
Description: This plugin allows you to create HTML code snippets, to easily use as shortcodes. Uses the ACE code editor for HTML code editing.
Author: Tyler Hozie
Version: 1.0
*/

if ( !class_exists ('wp_shortcode_generator_plugin')) {
	class wp_shortcode_generator_plugin {

		function shortcode_gen_options_box() {
			add_meta_box('shortcode_gen', 'Shortcode', array('wp_shortcode_generator_plugin','shortcode_gen_box'), 'shortcodegen', 'side', 'low');
			
			add_meta_box(
				'scode_content',
				'Shortcode HTML',
				array('wp_shortcode_generator_plugin','shortcode_content_box'),
				'shortcodegen',
				'normal',
				'default'
			);
		}

		function shortcode_gen_box() {
			global $post;
			$theID = $post->ID;
		?>
			<input type="text" readonly="readonly" value="[scode sid='<?php echo $theID; ?>']" />
		<?php
		}
		
		function shortcode_content_box() {
			global $post;
			$theID = $post->ID;
			
			// Nonce field to validate form request came from current site
			wp_nonce_field( basename( __FILE__ ), 'scode_fields' );
			// Get the data
			$shortcode_content = get_post_meta( $theID, 'shortcode_content', true );
		?>
			<style type="text/css" media="screen">
				#editor { 
					position: absolute;
					top: 0;
					right: 0;
					bottom: 0;
					left: 0;
					height: 400px;
					width: 100%;
				}
			</style>
			<label style="display:block;margin-bottom: 10px;">Enter your HTML / JavaScript Code here</label>
			<textarea style="display:block;" name="shortcode_content" value=""><?php echo $shortcode_content; ?></textarea>
			<div id="editor"><?php echo $shortcode_content; ?></div>
			<?php
				$pluginDir = plugin_dir_url( __FILE__ ); 
			?>
			<script src="<?php echo $pluginDir.'src-min-noconflict/ace.js'?>" type="text/javascript" charset="utf-8"></script>
			<script>
				var editor = ace.edit("editor");
				var textarea = jQuery('textarea[name="shortcode_content"]').hide();
				editor.setTheme("ace/theme/monokai");
				editor.session.setMode("ace/mode/html");
				editor.getSession().setValue(textarea.val());
				editor.getSession().on('change', function(){
				  textarea.val(editor.getSession().getValue());
				});
			</script>
		<?php
		}

	}
}

add_action('admin_menu', array('wp_shortcode_generator_plugin','shortcode_gen_options_box'));


// Register Custom Post Type
function _shortcodes_postType() {

	$labels = array(
		'name'                  => 'Shortcodes',
		'singular_name'         => 'Shortcode',
		'menu_name'             => 'Shortcodes',
		'name_admin_bar'        => 'Shortcodes',
		'archives'              => 'Item Archives',
		'parent_item_colon'     => 'Parent Item:',
		'all_items'             => 'All Shortcodes',
		'add_new_item'          => 'Add New Shortcode',
		'add_new'               => 'Add New Shortcode',
		'new_item'              => 'New Shortcode',
		'edit_item'             => 'Edit Shortcode',
		'update_item'           => 'Update Item',
		'view_item'             => 'View Item',
		'search_items'          => 'Search Item',
		'not_found'             => 'Not found',
		'not_found_in_trash'    => 'Not found in Trash',
		'featured_image'        => 'Featured Image',
		'set_featured_image'    => 'Set featured image',
		'remove_featured_image' => 'Remove featured image',
		'use_featured_image'    => 'Use as featured image',
		'insert_into_item'      => 'Insert into item',
		'uploaded_to_this_item' => 'Uploaded to this item',
		'items_list'            => 'Items list',
		'items_list_navigation' => 'Items list navigation',
		'filter_items_list'     => 'Filter items list',
	);
	$args = array(
		'label'                 => 'Shortcode',
		'description'           => 'Shortcode Generator Post Type',
		'labels'                => $labels,
		'supports'              => array( 'title', ),
		'hierarchical'          => false,
		'public'                => false,
		'show_ui'               => true,
		'show_in_menu'          => true,
		'menu_position'         => 20,
		'menu_icon'             => 'dashicons-welcome-add-page',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => false,
		'exclude_from_search'   => true,
		'publicly_queryable'    => true,
		'capability_type'       => 'page',
	);
	register_post_type( 'shortcodegen', $args );

}
add_action( 'init', '_shortcodes_postType', 0 );

/* Shortcode Generator Plugin Shortcode */
// Add Shortcode
function scode_shortcode($atts) {

// Attributes
extract( shortcode_atts(
	array(
		'sid' => '',
	), $atts )
);

// Code
$args = array( 'post_type' => 'shortcodegen', 'posts_per_page' => -1, 'page_id' => $sid );
$loop = new WP_Query( $args );
while ( $loop->have_posts() ) : $loop->the_post();
global $post;
// Plain Code Fields
$scode_content = get_post_meta( $post->ID, 'shortcode_content', true );

$output = $scode_content;

endwhile;
wp_reset_postdata();
return $output;

}
add_shortcode( 'scode', 'scode_shortcode' );

/**
 * Save the metabox data
 */
function scode_save_data( $post_id, $post ) {
	// Return if the user doesn't have edit permissions.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return $post_id;
	}
	// Verify this came from the our screen and with proper authorization,
	// because save_post can be triggered at other times.
	if ( ! isset( $_POST['shortcode_content'] ) || ! wp_verify_nonce( $_POST['scode_fields'], basename(__FILE__) ) ) {
		return $post_id;
	}
	// Now that we're authenticated, time to save the data.
	// This sanitizes the data from the field and saves it
	$scode_meta['shortcode_content'] = $_POST['shortcode_content'];
	foreach ( $scode_meta as $key => $value ) :
		// Don't store custom data twice
		if ( 'revision' === $post->post_type ) {
			return;
		}
		if ( get_post_meta( $post_id, $key, false ) ) {
			// If the custom field already has a value, update it.
			update_post_meta( $post_id, $key, $value );
		} else {
			// If the custom field doesn't have a value, add it.
			add_post_meta( $post_id, $key, $value);
		}
		if ( ! $value ) {
			// Delete the meta key if there's no value
			delete_post_meta( $post_id, $key );
		}
	endforeach;
}
add_action( 'save_post', 'scode_save_data', 1, 2 );
