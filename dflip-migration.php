<?php
/**
 * Plugin Name: DearFlip (dflip) Migration Tool
 * Description: Migrate posts from other viewers to DearFlip.
 * Version: 1.0.2
 *
 * Text Domain: DFLIP
 * Author: DearHive
 * Author URI: http://dearhive.com/
 *
 */
/**
 * Author : Deepak Ghimire
 * Date: 8/11/2016
 * Time: 4:15 PM
 *
 * @package dflip
 *
 * @since   dflip 1.2
 */

$dflip_migration_result = "";

class DFlip_Migration_Tools {
	
	/**
	 * Holds the singleton class object.
	 *
	 * @since 1.2.0
	 *
	 * @var object
	 */
	public static $instance;
	
	/**
	 * Holds the base DFlip class object.
	 *
	 * @since 1.2.0
	 *
	 * @var object
	 */
	public $base;
	public $hook;
	
	
	/**
	 * Primary class constructor.
	 *
	 * @since 1.2.0
	 */
	public function __construct() {
		
		// Load the plugin.
		add_action( 'init', array( $this, 'init' ), 0 );
		
	}
	
	public function init() {
		// Load the base class object.
		$this->base = DFlip::get_instance();
		if ( is_admin() && !(defined( 'DOING_AJAX' ) && DOING_AJAX) ) {
			$this->init_admin();
		}
		else { // Load frontend only components.
			$this->init_front();
		}
	}
	
	public function init_admin() {
		
		add_action( 'admin_menu', array( $this, 'settings_menu' ) );
	}
	
	public function init_front() {
		
		//dearpdf shortcodes are loaded in init, so we need a later hook than init, widgets_init runs after init
		add_action( 'widgets_init', array( $this, 'dearpdf_override' ), 0 );
		
	}
	
	public function dearpdf_override() {
		
		//having this plugin activated means there is dflip running
		add_action( 'wp_enqueue_scripts', function () {
			wp_dequeue_script( "dearpdf-script" );
			wp_dequeue_style( "dearpdf-style" );
			wp_deregister_script( "dearpdf-script" );
			wp_deregister_style( "dearpdf-style" );
		}, PHP_INT_MAX );
		
		if ( get_option( 'migrated_dearpdf_to_dflip' ) == "yes" ) {
			
			remove_shortcode( 'dearpdf' );
			add_shortcode( 'dearpdf', array( $this, 'shortcode_dearpdf_wrapper' ) );
			
			remove_shortcode( 'dpcss' );
			add_shortcode( 'dpcss', array( $this, 'shortcode_dearpdfcss' ) );
		}
		
	}
	
	public function shortcode_dearpdfcss( $raw_attr, $content = '' ) {
		
		if ( !class_exists( 'DFlip_ShortCode' ) ) {
			return '';
		}
		$dflip_shortcode = DFlip_ShortCode::get_instance();
		if ( !method_exists( $dflip_shortcode, 'get_post_data' ) ) {
			return '';
		}
		$post_id = trim( $content );
		
		$post = get_post( $post_id );
		if ( $post == null ) {
			return "";
		}
		
		$post_data = $dflip_shortcode->get_post_data( $post );
		$post_data['slug'] = $post->post_name;
		if ( isset( $post_data['pdfThumb'] ) ) {
			unset( $post_data['pdfThumb'] );
		}
		$post_data["id"] = $post->ID;
		
		return "dvcss dvcss_e_" . base64_encode( json_encode( $post_data ) );
		
	}
	
	public function shortcode_dearpdf_wrapper( $attr, $content = '' ) {
		if ( !class_exists( 'DFlip_ShortCode' ) ) {
			return '';
		}
		$dflip_shortcode = DFlip_ShortCode::get_instance();
		if ( isset( $attr['posts'] ) && trim( $attr['posts'] ) !== '' ) {
			$attr['books'] = $attr['posts'];
		}
		return $dflip_shortcode->shortcode( $attr, $content );
	}
	
	/**
	 * Creates menu for the settings page
	 *
	 * @since 1.2
	 */
	public function settings_menu() {
		
		$this->hook = add_submenu_page( 'edit.php?post_type=dflip', __( 'DearFlip Migration Tools', 'DFLIP' ), __( 'Migration', 'DFLIP' ), 'manage_options', $this->base->plugin_slug . '-tools',
			array( $this, 'settings_page' ) );
		
		//The resulting page's hook_suffix, or false if the user does not have the capability required.
		if ( $this->hook ) {
			add_action( 'load-' . $this->hook, array( $this, 'update_settings' ) );
			// Load metabox assets.
			add_action( 'load-' . $this->hook, array( $this, 'hook_page_assets' ) );
		}
	}
	
	/**
	 * Callback to create the settings page
	 *
	 * @since 1.2
	 */
	public function settings_page() {
		
		$tabs = array(
			'migration' => __( 'Migration', 'DFLIP' )
		);
		
		//create tabs and content
		?>

      <h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
      <form id="dflip-settings" method="post" class="dflip-settings postbox">
				
				<?php
				wp_nonce_field( 'dflip_migration_tools_nonce', 'dflip_migration_tools_nonce' );
				?>

          <div class="dflip-tabs">
              <ul class="dflip-tabs-list">
								<?php
								//create tabs
								$active_set = false;
								foreach ( (array)$tabs as $id => $title ) {
									?>
                    <li class="dflip-update-hash dflip-tab <?php echo($active_set == false ? 'dflip-active' : '') ?>">
                        <a href="#dflip-tab-content-<?php echo $id ?>"><?php echo $title ?></a></li>
									<?php $active_set = true;
								}
								?>
              </ul>
						<?php
						
						$active_set = false;
						foreach ( (array)$tabs as $id => $title ) {
							?>
                <div id="dflip-tab-content-<?php echo $id ?>"
                     class="dflip-tab-content <?php echo($active_set == false ? "dflip-active" : "") ?>">
									
									<?php
									$active_set = true;
									
									//create content for tab
									$function = $id . "_tab";
									if ( method_exists( $this, $function ) ) {
										call_user_func( array( $this, $function ) );
									};
									
									?>
                </div>
						<?php } ?>
          </div>
      </form>
		<?php
		
	}
	
	public function hook_page_assets() {
		add_action( 'admin_enqueue_scripts', array( $this, 'meta_box_styles_scripts' ) );
	}
	
	/**
	 * Loads styles and scripts for our metaboxes.
	 *
	 * @return null Bail out if not on the proper screen.
	 * @since 1.0.0
	 *
	 */
	public function meta_box_styles_scripts() {
		
		
		// Load necessary metabox styles.
		wp_register_style( $this->base->plugin_slug . '-setting-metabox-style', plugins_url( './assets/css/metaboxes.css', $this->base->file ), array(), $this->base->version );
		wp_enqueue_style( $this->base->plugin_slug . '-setting-metabox-style' );
		wp_enqueue_style( 'wp-color-picker' );
		
		// Load necessary metabox scripts.
		wp_register_script( $this->base->plugin_slug . '-setting-metabox-script', plugins_url( './assets/js/metaboxes.js', $this->base->file ), array( 'jquery', 'jquery-ui-tabs', 'wp-color-picker' ),
			$this->base->version );
		wp_enqueue_script( $this->base->plugin_slug . '-setting-metabox-script' );
		
		wp_enqueue_media();
		
	}
	
	
	public function migration_tab() {
		global $dflip_migration_result;
		
		if ( get_option( 'migrated_dearpdf_to_dflip' ) == "yes" ) {
			echo "DearPDF to DFLIP migration active. <code>dearpdf</code> shortcodes are handled by dflip migration plugin.<br><hr><br>";
		}
		submit_button( __( 'Migrate posts from DearPDF', 'DFLIP' ), 'primary', 'dflip_migration_tools_migrate_dearpdf_posts', false );
		echo "<br><br>";
//    submit_button(__('Re-import posts from DearPDF', 'DFLIP'), 'primary', 'dflip_migration_tools_re_import_dearpdf_posts', false);
//    echo "<br><br>";
//    submit_button(__('Migrate Global Setting from DearPDF', 'DFLIP'), 'primary', 'dflip_migration_tools_migrate_dearpdf_settings', false);
//    echo "<br><br>";
		submit_button( __( 'Undo Migrate posts from DearPDF', 'DFLIP' ), 'primary', 'dflip_migration_tools_unmigrate_dearpdf_posts', false );
		echo "<br><br><hr>";
		echo $dflip_migration_result;
		
		?>

      <!--Clear-fix-->
      <div class="dflip-box"></div>
		
		<?php
	}
	
	/**
	 * Update settings
	 *
	 * @return null Invalid nonce / no need to save
	 * @since 1.2.0.1
	 *
	 */
	public function update_settings() {
		
		// Check form was submitted
		if ( !isset( $_POST['dflip_migration_tools_nonce'] ) ) {
			return;
		}
		
		// Check nonce is valid
		if ( !wp_verify_nonce( $_POST['dflip_migration_tools_nonce'], 'dflip_migration_tools_nonce' ) ) {
			return;
		}
		global $dflip_migration_result;
		global $wpdb;
		
		if ( isset( $_POST['dflip_migration_tools_migrate_dearpdf_posts'] ) ) {
			$dflip_migration_result .= "DearPDF Migration:<br>";
			$dflip_migration_result .= "<br>Posts: <br>";
			$post_ids = get_posts( array(
				'post_type' => 'dearpdf',
				'post_status' => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ),
				'posts_per_page' => -1 ) );
			//then update each post
			foreach ( $post_ids as $p ) {
				$po = get_post( $p->ID );
				$po->post_type = "dflip";
				wp_update_post( $po );
				$this->import_dearpdf_post_options( $p->ID );
				$dflip_migration_result .= $p->ID . " - (" . $p->post_title . ")<br>";
			}
			update_option( 'migrated_dearpdf_to_dflip', 'yes' );
			
			$terms = get_terms( array(
				'taxonomy' => 'dearpdf_category',
				'hide_empty' => false
			) );
			if ( $terms ) {
				$dflip_migration_result .= "<br><hr><br>Categories: <br>";
				foreach ( $terms as $term ) {
					update_term_meta( $term->term_id, "is_dearpdf_term", 'yes' );
					$fallback_meta = get_term_meta( $term->term_id, "is_dearpdf_term" );
					if ( !empty( $fallback_meta ) ) {
						$update_term = $wpdb->update(
							$wpdb->prefix . 'term_taxonomy',
							[ 'taxonomy' => 'dflip_category' ],
							[ 'term_taxonomy_id' => $term->term_taxonomy_id ],
							[ '%s' ],
							[ '%d' ]
						);
						if ( $update_term ) {
							$dflip_migration_result .= $term->term_id . " - (" . $term->name . ")<br>";
							clean_term_cache( $term->term_id );
						}
						else {
							$dflip_migration_result .= $term->term_id . " - (" . $term->name . ") Failed<br>";
						}
					}
				}
			}
		}
		
		if ( isset( $_POST['dflip_migration_tools_unmigrate_dearpdf_posts'] ) ) {
			$dflip_migration_result .= "DearPDF Undo Migration:<br>";
			$dflip_migration_result .= "<br>Posts: <br>";
			$post_ids = get_posts( array(
				'post_type' => 'dflip',
				'post_status' => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ),
				'meta_query' => array(
					array(
						'key' => '_dearpdf_data'
					)
				),
				'posts_per_page' => -1 ) );
			//then update each post
			foreach ( $post_ids as $p ) {
				$po = get_post( $p->ID );
				$po->post_type = "dearpdf";
				wp_update_post( $po );
				$dflip_migration_result .= $p->ID . " - (" . $p->post_title . ") ,<br>";
			}
			update_option( 'migrated_dearpdf_to_dflip', 'no' );
			
			
			$terms = get_terms( array(
				'taxonomy' => 'dflip_category',
				'hide_empty' => false,
				'meta_key' => 'is_dearpdf_term'
			) );
			if ( $terms ) {
				$dflip_migration_result .= "<br><hr><br>Categories:<br>";
				foreach ( $terms as $term ) {
					
					if ( !empty( $term ) ) {
						$update_term = $wpdb->update(
							$wpdb->prefix . 'term_taxonomy',
							[ 'taxonomy' => 'dearpdf_category' ],
							[ 'term_taxonomy_id' => $term->term_taxonomy_id ],
							[ '%s' ],
							[ '%d' ]
						);
						if ( $update_term ) {
							$dflip_migration_result .= $term->term_id . " - (" . $term->name . ")<br>";
							clean_term_cache( $term->term_id );
						}
						else {
							$dflip_migration_result .= $term->term_id . " - (" . $term->name . ") Failed<br>";
						}
					}
				}
			}
		}
		
		if ( isset( $_POST['dflip_migration_tools_re_import_dearpdf_posts'] ) ) {
			$dflip_migration_result .= "Re-import Settings:<br>";
			$post_ids = get_posts( array(
				'post_type' => 'dflip',
				'post_status' => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ),
				'meta_query' => array(
					array(
						'key' => '_dearpdf_data'
					)
				),
				'posts_per_page' => -1 ) );
			//then update each post
			foreach ( $post_ids as $p ) {
				$this->import_dearpdf_post_options( $p->ID );
				$dflip_migration_result .= $p->ID . ",<br>";
			}
			
		}
	}
	
	function import_dearpdf_post_options( $post_id ) {
		//original _dearpdf_data should be untouched
		$settings = get_post_meta( $post_id, '_dearpdf_data', true );
		$settings = $this->validate_dearpdf_to_dearflip_options( $settings );
		
		update_post_meta( $post_id, '_dflip_data', $settings );
	}
	
	function getValue( $array, $key, $default = "" ) {
		if ( isset( $array[$key] ) ) {
			return $array[$key];
		}
		return $default;
	}
	
	function validate_dearpdf_to_dearflip_options( $settings, $is_global = false ) {
		if ( empty( $settings ) || !is_array( $settings ) ) {
			$settings = array();
		}
		
		$settings["source_type"] = 'pdf';
		$settings["pdf_source"] = $this->getValue( $settings, "source" );
		$settings["pdf_thumb"] = $this->getValue( $settings, "pdfThumb" );
		
		//viewerType
		//height
		$settings["bg_color"] = $this->getValue( $settings, "backgroundColor" );//backgroundColor
		$settings["bg_image"] = $this->getValue( $settings, "backgroundImage" );//backgroundImage
		$settings["enable_download"] = $this->getValue( $settings, "showDownloadControl" );//showDownloadControl
		$settings["controls_position"] = $this->getValue( $settings, "controlsPosition" );//controlsPosition
		$settings["enable_search"] = $this->getValue( $settings, "showSearchControl" );//showSearchControl
		$settings["enable_print"] = $this->getValue( $settings, "showPrintControl" );//showPrintControl
		$settings["auto_outline"] = $this->getValue( $settings, "autoOpenOutline" );//autoOpenOutline
		$settings["auto_thumbnail"] = $this->getValue( $settings, "autoOpenThumbnail" );//autoOpenThumbnail
		$settings["webgl"] = $this->getValue( $settings, "is3D" );//is3D
		$settings["cover3DType"] = $this->getValue( $settings, "has3DCover" );//has3DCover
		if ( $settings["cover3DType"] !== "global" ) {
			$settings["cover3DType"] = $settings["cover3DType"] === "true" ? "plain" : "none";
		}
		//color3DCover
		$settings["auto_sound"] = $this->getValue( $settings, "enableSound" );//enableSound
		//duration
		$settings["direction"] = $this->getValue( $settings, "readDirection" );//readDirection
		if ( $settings["direction"] !== "global" ) {
			$settings["direction"] = $settings["direction"] === "rtl" ? 2 : 1;
		}
		$settings["page_mode"] = $this->getValue( $settings, "pageMode" );//pageMode
		if ( $settings["page_mode"] !== "global" ) {
			$settings["page_mode"] = $settings["page_mode"] === "auto" ? '0'
				: ($settings["page_mode"] === "single" ? '1' : '2');
		}
		$settings["single_page_mode"] = $this->getValue( $settings, "singlePageMode" );//singlePageMode
		if ( $settings["single_page_mode"] !== "global" ) {
			$settings["single_page_mode"] = $settings["single_page_mode"] === "auto" ? '0'
				: ($settings["single_page_mode"] === "zoom" ? '1' : '2');
		}
		//disableRange //todo add in dearflip
		//rangeChunkSize //todo add in dearflip
		$settings["texture_size"] = $this->getValue( $settings, "maxTextureSize" );
		
		return $settings;
	}
	
	/**
	 * display a saved notice
	 *
	 * @since 1.2.0.1
	 */
	public function updated_settings() {
		?>
      <div class="updated">
          <p><?php _e( 'Settings updated.', 'DFLIP' ); ?></p>
      </div>
		<?php
		
	}
	
	/**
	 * Returns the singleton instance of the class.
	 *
	 * @return object DFlip_Migration_Tools object.
	 * @since 1.2.0
	 *
	 */
	public static function get_instance() {
		
		if ( !isset( self::$instance )
			&& !(self::$instance instanceof DFlip_Migration_Tools) ) {
			self::$instance = new DFlip_Migration_Tools();
		}
		
		return self::$instance;
		
	}
}

add_action( 'plugins_loaded', 'load_dflip_migration_tools' );
function load_dflip_migration_tools() {
	if ( class_exists( "DFLIP" ) ) {
		// Load the DFlip_Migration_Tools class.
		$dflip_migration_tools = DFlip_Migration_Tools::get_instance();
	}
}

