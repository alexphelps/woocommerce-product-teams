<?php
/* 
Plugin Name: WooCommerce Product Teams
Plugin URI: http://alexphelps.me
Description: Add Teams taxonomy for products from WooCommerce plugin for team stores.
Version: 0.1
Author: Alex Phelps
Author URI: http://alexphelps.me
Text Domain: woocommerce-product-teams
*/


require_once dirname( __FILE__ ) . '/inc/wp-thumb/wpthumb.php';

/**
 * Make sure we're not doing anything wrong
 **/
if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}
/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

class WC_Product_Teams{

	function __construct() {
		add_action( 'init', array( &$this, 'init') );
		add_action( 'init', array( $this, 'register_teams_taxonomy' ) );
		
		
	}

	function init() {

		// Add Team Thumbnail Form Fields
		add_action( 'product_team_add_form_fields', array( $this, 'add_team_taxonomy_fields' ) );
		add_action( 'product_team_edit_form_fields', array( $this, 'edit_team_taxonomy_fields' ), 10 );
		
		// Save Thumbnail ID as Term Meta
		add_action( 'created_product_team', array( $this, 'save_product_team_fields' ), 10, 2 );
		add_action( 'edited_product_team', array( $this, 'update_product_team_fields' ), 10, 2 );

		// Add Thumbnail to Columns
		add_filter( 'manage_edit-product_team_columns', array( $this, 'product_team_columns' ) );
		add_filter( 'manage_product_team_custom_column', array( $this, 'product_team_column' ), 10, 3 );

		// Add Product Teams Shortchode [product_teams]
		add_shortcode( 'product_teams', array( $this, 'product_teams_shortcode' ) );

		// Add Admin Column Filters
		add_action('restrict_manage_posts', array( $this, 'products_filter_post_type_by_taxonomy' ) );
		add_filter('parse_query', array( $this, 'products_filter_convert_id_to_term_in_query' ) );

		add_action('restrict_manage_posts', array( $this, 'shop_order_filter_post_type_by_taxonomy' ) );
		add_filter('parse_query', array( $this, 'shop_order_filter_convert_id_to_term_in_query' ) );

		// Filter Team Products from Store
		add_action('pre_get_posts', array( $this, 'exclude_team_products' ) );

		// Override the related products
		add_filter( 'woocommerce_related_products_args', array( $this, 'team_related_products' ), 10 ) ;

		// show team description on team index
		add_action( 'woocommerce_before_shop_loop', array( $this,'add_product_team_notice' ), 9 );

		// save the team on new order
		add_action( 'woocommerce_thankyou', array( $this, 'save_team_on_new_order' ) ); 

		// override woocommerce stock and backorder settings if a product is in a team
		add_action('save_post_product', array( $this, 'override_team_product_stock_and_backorder' ), 10, 1 );

	}

	/**
	 * Register custom Brand taxonomy
	 */
	function register_teams_taxonomy() {
		$labels = array(
			'name' => __( 'Teams', 'taxonomy general name' ),
			'singular_name' => __( 'Team', 'taxonomy singular name' ),
			'search_items' =>  __( 'Search Teams' ),
			'all_items' => __( 'All Teams' ),
			'parent_item' => __( 'Parent Team' ),
			'parent_item_colon' => __( 'Parent Team:' ),
			'edit_item' => __( 'Edit Team' ),
			'update_item' => __( 'Update Team' ),
			'add_new_item' => __( 'Add New Team' ),
			'new_item_name' => __( 'New Team Name' ),
			'menu_name' => __( 'Teams' ),
		);

		$args = array(
			'public' => true,
			 'hierarchical' => true,
			 'labels' => $labels,
			 'show_ui' => true,
			 'query_var' => true,
			 'rewrite' => array( 
				'slug' => 		'teams',
				'with_front' => true, 
			 ),
			 'show_admin_column' => true
		 );

		register_taxonomy('product_team', array('product','shop_order'), $args );
		 
	}

	/**
	 * Add Term Meta form to Add Term Form
	 */
	function add_team_taxonomy_fields() {
		wp_enqueue_media();
		?>
		<div class="form-field">
			<label><?php _e( 'Team Thumbnail', 'woocommerce-product-teams' ); ?></label>
			<div id="product_team_thumbnail" style="float: left; margin-right: 10px;"><img src="<?php echo esc_url( wc_placeholder_img_src() ); ?>" width="60px" height="60px" /></div>
			<div style="line-height: 60px;">
				<input type="hidden" id="product_team_thumbnail_id" name="product_team_thumbnail_id" />
				<button type="button" class="upload_image_button button"><?php _e( 'Upload/Add image', 'woocommerce-product-teams' ); ?></button>
				<button type="button" class="remove_image_button button"><?php _e( 'Remove image', 'woocommerce-product-teams' ); ?></button>
			</div>
			<script type="text/javascript">

				// Only show the "remove image" button when needed
				if ( ! jQuery( '#product_team_thumbnail_id' ).val() ) {
					jQuery( '.remove_image_button' ).hide();
				}

				// Uploading files
				var file_frame;

				jQuery( document ).on( 'click', '.upload_image_button', function( event ) {

					event.preventDefault();

					// If the media frame already exists, reopen it.
					if ( file_frame ) {
						file_frame.open();
						return;
					}

					// Create the media frame.
					file_frame = wp.media.frames.downloadable_file = wp.media({
						title: '<?php _e( "Choose an image", "woocommerce-product-teams" ); ?>',
						button: {
							text: '<?php _e( "Use image", "woocommerce-product-teams" ); ?>'
						},
						multiple: false
					});

					// When an image is selected, run a callback.
					file_frame.on( 'select', function() {
						var attachment = file_frame.state().get( 'selection' ).first().toJSON();

						jQuery( '#product_team_thumbnail_id' ).val( attachment.id );
						jQuery( '#product_team_thumbnail' ).find( 'img' ).attr( 'src', attachment.sizes.thumbnail.url );
						jQuery( '.remove_image_button' ).show();
					});

					// Finally, open the modal.
					file_frame.open();
				});

				jQuery( document ).on( 'click', '.remove_image_button', function() {
					jQuery( '#product_team_thumbnail' ).find( 'img' ).attr( 'src', '<?php echo esc_js( wc_placeholder_img_src() ); ?>' );
					jQuery( '#product_team_thumbnail_id' ).val( '' );
					jQuery( '.remove_image_button' ).hide();
					return false;
				});

			</script>
			<div class="clear"></div>
		</div>
		<?php
	}

	/**
	 * Add Term Meta form to Edit Term Form
	 */
	function edit_team_taxonomy_fields( $term ) {
		wp_enqueue_media();
		$enabled = get_term_meta( $term->term_id, 'team_enabled', true );
		if ( $enabled == 'yes' ) { 
		 $enabled_setting = 'checked'; 
		} else {
			$enabled_setting = ''; 
		}
		$thumbnail_id = get_term_meta( $term->term_id, 'product_team_thumbnail_id', true );
		if ( $thumbnail_id ) {
			$image = wp_get_attachment_thumb_url( $thumbnail_id );
		} else {
			$image = wc_placeholder_img_src();
		}
		?>
		<tr class="form-field">
		<th scope="row">Team Store Enabled </th>
		<td><fieldset><legend class="screen-reader-text"><span>Enabled </span></legend>
			<label for="team_enabled">
			<input name="team_enabled" type="checkbox" id="team_enabled" value="yes" <?php echo $enabled_setting; ?>>Enabled</label>
			<p class="description">Unchecking this box will hide this team store.</p>
		</fieldset></td>
		</tr>
		<tr class="form-field">
			<th scope="row" valign="top"><label><?php _e( 'Team Thumbnail', 'woocommerce-product-teams' ); ?></label></th>
			<td>
				<div id="product_team_thumbnail" style="float: left; margin-right: 10px;"><img src="<?php echo esc_url( $image ); ?>" width="60px" height="60px" /></div>
				<div style="line-height: 60px;">
					<input type="hidden" id="product_team_thumbnail_id" name="product_team_thumbnail_id" value="<?php echo $thumbnail_id; ?>" />
					<button type="button" class="upload_image_button button"><?php _e( 'Upload/Add image', 'woocommerce-product-teams' ); ?></button>
					<button type="button" class="remove_image_button button"><?php _e( 'Remove image', 'woocommerce-product-teams' ); ?></button>
				</div>
				<script type="text/javascript">

					// Only show the "remove image" button when needed
					if ( '0' === jQuery( '#product_team_thumbnail_id' ).val() ) {
						jQuery( '.remove_image_button' ).hide();
					}

					// Uploading files
					var file_frame;

					jQuery( document ).on( 'click', '.upload_image_button', function( event ) {

						event.preventDefault();

						// If the media frame already exists, reopen it.
						if ( file_frame ) {
							file_frame.open();
							return;
						}

						// Create the media frame.
						file_frame = wp.media.frames.downloadable_file = wp.media({
							title: '<?php _e( "Choose an image", "woocommerce-product-teams" ); ?>',
							button: {
								text: '<?php _e( "Use image", "woocommerce-product-teams" ); ?>'
							},
							multiple: false
						});

						// When an image is selected, run a callback.
						file_frame.on( 'select', function() {
							var attachment = file_frame.state().get( 'selection' ).first().toJSON();

							jQuery( '#product_team_thumbnail_id' ).val( attachment.id );
							jQuery( '#product_team_thumbnail' ).find( 'img' ).attr( 'src', attachment.sizes.thumbnail.url );
							jQuery( '.remove_image_button' ).show();
						});

						// Finally, open the modal.
						file_frame.open();
					});

					jQuery( document ).on( 'click', '.remove_image_button', function() {
						jQuery( '#product_team_thumbnail' ).find( 'img' ).attr( 'src', '<?php echo esc_js( wc_placeholder_img_src() ); ?>' );
						jQuery( '#product_team_thumbnail_id' ).val( '' );
						jQuery( '.remove_image_button' ).hide();
						return false;
					});

				</script>
				<div class="clear"></div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save Term data when new team is created
	 */
	function save_product_team_fields( $term_id, $tt_id ) {
		if ( isset( $_POST['product_team_thumbnail_id'] ) && '' !== $_POST['product_team_thumbnail_id'] ) {
			$image = $_POST['product_team_thumbnail_id'];
			add_term_meta( $term_id, 'product_team_thumbnail_id', $image, true );
			add_term_meta( $term_id, 'team_enabled', 'yes', true);
		}

	}

	/**
	 * Save Term data when new team is edited
	 */
	function update_product_team_fields( $term_id, $tt_id ) {
		if ( isset( $_POST['product_team_thumbnail_id'] ) && '' !== $_POST['product_team_thumbnail_id'] ) {
			$image = $_POST['product_team_thumbnail_id'];
			update_term_meta( $term_id, 'product_team_thumbnail_id', $image );
		} else {
		 update_term_meta( $term_id, 'product_team_thumbnail_id', '' );
	   }
	   $enabled = $_POST['team_enabled'];
		 var_dump($enabled);
	   //wp_die();
	   update_term_meta( $term_id, 'team_enabled', $enabled );
	}

	/**
	 * Add Team thumbnail to admin columns
	 */
	function product_team_columns( $columns ) {
		$new_columns          = array();
		$new_columns['thumb'] = __( 'Thumbnail', 'woocommerce-product-teams' );
		return array_merge( $columns, $new_columns );
	}

	function product_team_column( $columns, $column, $id ) {

		if ( 'thumb' == $column ) {

			$thumbnail_id = get_term_meta( $id, 'product_team_thumbnail_id', true );

			if ( $thumbnail_id ) {
				$image = wp_get_attachment_thumb_url( $thumbnail_id );
			} else {
				$image = wc_placeholder_img_src();
			}

			// Prevent esc_url from breaking spaces in urls for image embeds
			// Ref: http://core.trac.wordpress.org/ticket/23605
			$image = str_replace( ' ', '%20', $image );

			$columns .= '<img src="' . esc_url( $image ) . '" alt="' . esc_attr__( 'Thumbnail', 'woocommerce-product-teams' ) . '" class="wp-post-image" height="48" width="48" />';

		}

		return $columns;
	}

	/**
	 * Function to add product team filter in admin
	 */
	function products_filter_post_type_by_taxonomy() {
		global $typenow;
		$post_type = 'product'; 
		$taxonomy  = 'product_team'; 
		if ($typenow == $post_type) {
			$selected      = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';
			$info_taxonomy = get_taxonomy($taxonomy);
			wp_dropdown_categories(array(
				'show_option_all' => __("Show All {$info_taxonomy->label}"),
				'taxonomy'        => $taxonomy,
				'name'            => $taxonomy,
				'orderby'         => 'name',
				'selected'        => $selected,
				'show_count'      => true,
				'hide_empty'      => true,
			));
		};
	}

	/**
	 * Function to help product team filter in admin
	 */
	function products_filter_convert_id_to_term_in_query($query) {
		global $pagenow;
		$post_type = 'product';
		$taxonomy  = 'product_team';
		$q_vars    = &$query->query_vars;
		if ( $pagenow == 'edit.php' && isset($q_vars['post_type']) && $q_vars['post_type'] == $post_type && isset($q_vars[$taxonomy]) && is_numeric($q_vars[$taxonomy]) && $q_vars[$taxonomy] != 0 ) {
			$term = get_term_by('id', $q_vars[$taxonomy], $taxonomy);
			$q_vars[$taxonomy] = $term->slug;
		}
	}

	/**
	 * Function to add product team filter in orders admin
	 */
	function shop_order_filter_post_type_by_taxonomy() {
		global $typenow;
		$post_type = 'shop_order'; 
		$taxonomy  = 'product_team'; 
		if ($typenow == $post_type) {
			$selected      = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';
			$info_taxonomy = get_taxonomy($taxonomy);
			wp_dropdown_categories(array(
				'show_option_all' => __("Show All {$info_taxonomy->label}"),
				'taxonomy'        => $taxonomy,
				'name'            => $taxonomy,
				'orderby'         => 'name',
				'selected'        => $selected,
				'show_count'      => false,
				'hide_empty'      => true,
			));
		};
	}

	/**
	 * Function to help product team filter in orders admin
	 */
	function shop_order_filter_convert_id_to_term_in_query($query) {
		global $pagenow;
		$post_type = 'shop_order';
		$taxonomy  = 'product_team';
		$q_vars    = &$query->query_vars;
		if ( $pagenow == 'edit.php' && isset($q_vars['post_type']) && $q_vars['post_type'] == $post_type && isset($q_vars[$taxonomy]) && is_numeric($q_vars[$taxonomy]) && $q_vars[$taxonomy] != 0 ) {
			$term = get_term_by('id', $q_vars[$taxonomy], $taxonomy);
			$q_vars[$taxonomy] = $term->slug;
		}
	}



	/**
	 * Shortcode to display all teams on any page
	 */
	function product_teams_shortcode( $atts ) {
		ob_start();
		
		$terms = get_terms( array(
			'taxonomy' => 'product_team',
			'hide_empty' => true,
		) );
		echo '<ul class="wc-product-teams">';
		foreach ( $terms as $term ) {
			$enabled = get_term_meta( $term->term_id, 'team_enabled', true );
			$thumbnail_id = get_term_meta( $term->term_id, 'product_team_thumbnail_id', true );
			$image = wp_get_attachment_image_src( $thumbnail_id, 'full' );
			$arg_defaults = array(
                'width'              => 350,
                'height'             => 350,
                'resize'             => true,
                'crop'               => false,
                'crop_from_position' => 'center,center',
                'cache'              => true,
                'default'            => null,
                'jpeg_quality'       => 70,
                'resize_animations'  => true,
                'return'             => 'url',
                'background_fill'    => 'auto'
            );
			if ( $enabled == 'yes') {
				echo '<li class="product-team">';
				echo '<a href="' . get_term_link( $term ) .'">';
				echo '<img src="' . wpthumb( $image[0], $arg_defaults ) . '" />';
				echo '<p class="product-team-name">' . $term->name . '</p>';
				echo '</a></li>';
			}
		}
		echo '</ul>';

		return ob_get_clean();
	}

	/**
	 * Filter team products from main shop only on frontend on shop, category, and tag archives
	 */
	function exclude_team_products( $query ) {
		$archive_query = $query->is_post_type_archive('product') && $query->is_main_query();
		$cat_tag_query = $query->is_tax( array('product_cat', 'product_tag') ) && $query->is_main_query();

		if ( !is_admin() && $archive_query || !is_admin() && $cat_tag_query ) {
		  $taxquery = array(
				array(
					'taxonomy' => 'product_team',
					'field' => 'id',
					'terms' => '', //leave blank to filter all teams
					'operator'=> 'NOT EXISTS'
					)
			);

			$query->set( 'tax_query', $taxquery );
		
		}
	}


	/**
	 * Override the regular related products to now only be related from the team's products
	 */
	function team_related_products( $args ) {
		global $woocommerce, $product;

		if ( is_object_in_term($product->id, 'product_team') ) {
			$terms = wp_get_post_terms( $product->id, 'product_team' );
			unset( $args['post__in'] );
		    $args['tax_query'] = array(
				'post_type' => 'product',
				'tax_query' => array(
					array(
						'taxonomy' => 'product_team',
						'field'    => 'id',
						'terms'    => $terms[0]->term_id,
					),
				),
			);
		}
		return $args;

	}

	/**
	 * Show team description on the team archive
	 */
	function add_product_team_notice() {
		if ( is_tax( 'product_team' ) ) {
			$term_id = get_queried_object_id();
			$term = get_term( $term_id);
			$name = $term->name;
			$description = $term->description;
			if ( ! empty($description) ) {
				echo '<div class="woocommerce-info"><h4>Please Read Import Details Below</h4>';
				echo '<p>' . $description . '</p>';
				echo '</div>';
			}
		}
	}

	/**
	 * Save team on new order
	 */
	function save_team_on_new_order( $order_id ) {

		$order = new WC_Order( $order_id );
		$items = $order->get_items();
		
		foreach ( $items as $item ) {
			
		    $product_id = $item['product_id'];
			$teams = wp_get_post_terms( $product_id, 'product_team' );

			foreach ( $teams as $team ) {
				wp_set_post_terms( $order_id, $team->term_id, 'product_team', true );
			}
		    
		}

	}


	/**
	 * Automatically set the stock, stock status, and back order setting for team products
	 */
	function override_team_product_stock_and_backorder( $post_id ) {
		
		if ( is_object_in_term($post_id, 'product_team') ) {
			
			if (isset( $_POST['variable_sku'] ) ) {
				$variable_sku     = $_POST['variable_sku'];
				$variable_post_id = $_POST['variable_post_id'];
				$manage_stock_setting = 'yes';
				$stock_status_setting = 'instock';
				$backorder_setting = 'yes';

				for ( $i = 0; $i < sizeof( $variable_sku ); $i++ ) {
					$variation_id = (int) $variable_post_id[$i];
					update_post_meta( $variation_id, '_manage_stock', $manage_stock_setting );
					update_post_meta( $variation_id, '_stock_status', $stock_status_setting );
					update_post_meta( $variation_id, '_backorders', $backorder_setting );
				}
			}
		}

	}
	
}

new wc_product_teams();

} // endif checking if WooCommerce is active
