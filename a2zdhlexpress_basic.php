<?php
/**
 * Plugin Name: DHL Express Rates & Labels 
 * Plugin URI: https://myshipi.com/
 * Description: Realtime Shipping Rates, Shipping label, Pickup, commercial invoice automation included.
 * Version: 5.5.2
 * Author: Shipi
 * Author URI: https://myshipi.com/
 * Developer: aarsiv
 * Developer URI: https://myshipi.com/
 * Text Domain: a2z_dhlexpress
 * Domain Path: /i18n/languages/
 *
 * WC requires at least: 2.6
 * WC tested up to: 6.4
 *
 *
 * @package WooCommerce
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define WC_PLUGIN_FILE.
if ( ! defined( 'A2Z_DHLEXPRESS_PLUGIN_FILE' ) ) {
	define( 'A2Z_DHLEXPRESS_PLUGIN_FILE', __FILE__ );
}

// set HPOS feature compatible by plugin
add_action(
    'before_woocommerce_init',
    function () {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }
);

// Include the main WooCommerce class.
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	
	if( !class_exists('a2z_dhlexpress_parent') ){
		Class a2z_dhlexpress_parent
		{
			private $errror = '';
			private $hpos_enabled = false;
			private $new_prod_editor_enabled = false;
			public function __construct() {
				if (get_option("woocommerce_custom_orders_table_enabled") === "yes") {
 		            $this->hpos_enabled = true;
 		        }
 		        if (get_option("woocommerce_feature_product_block_editor_enabled") === "yes") {
 		            $this->new_prod_editor_enabled = true;
 		        }
				add_action( 'woocommerce_shipping_init', array($this,'a2z_dhlexpress_init') );
				add_action( 'init', array($this,'hit_order_status_update') );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'a2z_dhlexpress_plugin_action_links' ) );
				add_action( 'add_meta_boxes', array($this, 'create_dhl_shipping_meta_box' ));
				if ($this->hpos_enabled) {
					add_action( 'woocommerce_process_shop_order_meta', array($this, 'hit_create_dhl_shipping'), 10, 1 );
					add_action( 'woocommerce_process_shop_order_meta', array($this, 'hit_create_dhl_return_shipping'), 10, 1 );
				} else {
					add_action( 'save_post', array($this, 'hit_create_dhl_shipping'), 10, 1 );
					add_action( 'save_post', array($this, 'hit_create_dhl_return_shipping'), 10, 1 );
				}
				if ($this->hpos_enabled) {
					add_filter( 'bulk_actions-woocommerce_page_wc-orders', array($this, 'hit_bulk_order_menu'), 10, 1 );
					add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array($this, 'hit_bulk_create_order'), 10, 3 );
				} else {
					add_filter( 'bulk_actions-edit-shop_order', array($this, 'hit_bulk_order_menu'), 10, 1 );
					add_filter( 'handle_bulk_actions-edit-shop_order', array($this, 'hit_bulk_create_order'), 10, 3 );
				}
				add_action( 'admin_notices', array($this, 'shipo_bulk_label_action_admin_notice' ) );
				add_filter( 'woocommerce_product_data_tabs', array($this,'hit_product_data_tab') );
				add_action( 'woocommerce_process_product_meta', array($this,'hit_save_product_options' ));
				add_filter( 'woocommerce_product_data_panels', array($this,'hit_product_option_view') );
				add_action( 'woocommerce_variation_options_pricing', array($this, 'hit_prod_add_variation_text_field'), 10, 3 );
				add_action( 'woocommerce_save_product_variation', array($this, 'hit_prod_save_variation_text_field'), 10, 2 );
				add_action( 'admin_menu', array($this, 'hit_dhl_menu_page' ));
				if ($this->hpos_enabled) {
					add_filter( 'manage_woocommerce_page_wc-orders_columns', array($this, 'a2z_wc_new_order_column') );
					add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'show_buttons_to_downlaod_shipping_label'), 10, 2 );
				} else {
					add_filter( 'manage_edit-shop_order_columns', array($this, 'a2z_wc_new_order_column') );
					add_action( 'manage_shop_order_posts_custom_column', array( $this, 'show_buttons_to_downlaod_shipping_label'), 10, 2 );
				}
				add_action( 'woocommerce_thankyou', array( $this, 'hit_wc_checkout_order_processed' ) );
				add_action( 'woocommerce_order_status_processing', array( $this, 'hit_wc_checkout_order_processed' ) );
				add_action('woocommerce_order_details_after_order_table', array( $this, 'dhl_track' ) );
				
				add_action('admin_print_styles', array($this, 'hits_admin_scripts'));
				add_action('rest_api_init', array($this, 'hits_rest_api_routes'));
				
				$general_settings = get_option('a2z_dhl_main_settings');
				$general_settings = empty($general_settings) ? array() : $general_settings;

				if(isset($general_settings['a2z_dhlexpress_v_enable']) && $general_settings['a2z_dhlexpress_v_enable'] == 'yes' ){
					add_action( 'woocommerce_product_options_shipping', array($this,'hit_choose_vendor_address' ));
					add_action( 'woocommerce_process_product_meta', array($this,'hit_save_product_meta' ));

					// Edit User Hooks
					add_action( 'edit_user_profile', array($this,'hit_define_dhl_credentails') );
					add_action( 'edit_user_profile_update', array($this, 'save_user_fields' ));

				}
			
			}
			public function hits_rest_api_routes()
			{
				register_rest_route('hits_dhl_app_action', '/track', array(
			        'methods' => 'POST',
			        'callback' => array($this,'hits_app_action_trk'), // function definition
			        'permission_callback' => array($this,'hits_rest_api_auth') // check the permission part
			    ));
			    // end point will be "/wp-json/hits_dhl_app_action/track"
			}
			public function hits_rest_api_auth($request)
			{
				$general_settings = get_option('a2z_dhl_main_settings');
				$input_data = $request->get_body();
				$input_data = !empty($input_data) ? json_decode($input_data, true) : [];
				if (isset($general_settings['a2z_dhlexpress_integration_key']) && !empty($general_settings['a2z_dhlexpress_integration_key']) && isset($input_data['token'])) {
					if ($general_settings['a2z_dhlexpress_integration_key'] == $input_data['token']) {
						return true;
					}
				}
				return false;
			}
			public function hits_app_action_trk(WP_REST_Request $request)
			{
				$input_data = $request->get_body();
				if(empty($input_data)){
			        return new WP_REST_Response(array('error' => 'Error message.'), 400);
			    }
			    apply_filters('a2z_dhlexpress_app_action', $input_data);
			}
			public function hits_admin_scripts() {
		        global $wp_scripts;
		        wp_enqueue_script('wc-enhanced-select');
		        wp_enqueue_script('chosen');
		        wp_enqueue_style('woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css');
				wp_enqueue_style('select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/css/select2.min.css');
		    	wp_enqueue_script('custom-script-admin', plugin_dir_url(__FILE__) . 'js/accountstates.js', array('jquery'), '1.0', true);
				wp_localize_script('custom-script-admin', 'states_list', json_decode(file_get_contents(plugin_dir_url(__FILE__).'data/states.json'),true ));
				wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/js/select2.min.js', array('jquery'), '', true);
				wp_enqueue_script('jquery','https://code.jquery.com/jquery-3.6.4.min.js');
		    }
			
			function a2z_wc_new_order_column( $columns ) {
				$columns['hit_dhlexpress'] = 'DHL Express';
				return $columns;
			}
			
			function show_buttons_to_downlaod_shipping_label( $column, $post ) {
				
				if ( 'hit_dhlexpress' === $column ) {
			
					$order    = ($this->hpos_enabled) ? $post : wc_get_order( $post );
					$order_id = $order->get_id();
					$json_data = get_option('hit_dhl_values_'.$order_id);
					$order_data = $order->get_data();
					$to_con = (isset($order_data['shipping']['country']) && !empty($order_data['shipping']['country'])) ? $order_data['shipping']['country'] : "";
					if (empty($to_con)) {
						$to_con = (isset($order_data['billing']['country']) && !empty($order_data['billing']['country'])) ? $order_data['billing']['country'] : "";
					}
					if(!empty($json_data)){
						$array_data = json_decode( $json_data, true );
						// echo '<pre>';print_r($array_data);die();
						if(isset($array_data[0])){
							foreach ($array_data as $key => $value) {
								echo '<a href="'.$value['label'].'" target="_blank" class="button button-secondary"><span class="dashicons dashicons-printer" style="vertical-align:sub;"></span></a> ';
								if (isset($value['invoice']) && apply_filters('hits_show_invoice', true, $to_con)) {
									echo ' <a href="'.$value['invoice'].'" target="_blank" class="button button-secondary"><span class="dashicons dashicons-pdf" style="vertical-align:sub;"></span></a><br/>';
								}
							}	
						}else{
							echo '<a href="'.$array_data['label'].'" target="_blank" class="button button-secondary"><span class="dashicons dashicons-printer" style="vertical-align:sub;"></span></a> ';
							if (isset($array_data['invoice']) && apply_filters('hits_show_invoice', true, $to_con)) {
								echo ' <a href="'.$array_data['invoice'].'" target="_blank" class="button button-secondary"><span class="dashicons dashicons-pdf" style="vertical-align:sub;"></span></a>';
							}
						}
					}else{
						echo '-';
					}
				}
			}
			
			function hit_dhl_menu_page() {

				$general_settings = get_option('a2z_dhl_main_settings');
				if (isset($general_settings['a2z_dhlexpress_integration_key']) && !empty($general_settings['a2z_dhlexpress_integration_key'])) {
					add_menu_page(__( 'DHL Labels', 'a2z_dhlexpress' ), 'DHL Labels', 'manage_options', 'hit-dhl-labels', array($this,'my_label_page_contents'), '', 6);
				}
				
				add_submenu_page( 'options-general.php', 'DHL Express Config', 'DHL Express Config', 'manage_options', 'hit-dhl-express-configuration', array($this, 'my_admin_page_contents') ); 

			}
			function my_label_page_contents(){
				$general_settings = get_option('a2z_dhl_main_settings');
				$url = site_url();
				if (isset($general_settings['a2z_dhlexpress_integration_key']) && !empty($general_settings['a2z_dhlexpress_integration_key'])) {
					echo "<iframe style='width: 100%;height: 100vh;' src='https://app.myshipi.com/embed/label.php?shop=".$url."&key=".$general_settings['a2z_dhlexpress_integration_key']."&show=ship'></iframe>";
				}
			}
			function my_admin_page_contents(){
				include_once('controllors/views/a2z_dhlexpress_settings_view.php');
			}

			public function hit_product_data_tab( $tabs) {

				$tabs['hits_product_options'] = array(
					'label'		=> __( 'Shipi - DHL Options', 'a2z_dhlexpress' ),
					'target'	=> 'hit_dhl_product_options',
					// 'class'		=> array( 'show_if_simple', 'show_if_variable' ),
				);
			
				return $tabs;
			
			}

			public function hit_save_product_options( $post_id ){
				if( isset($_POST['hits_dhl_cc']) ){
					$cc = sanitize_text_field($_POST['hits_dhl_cc']);
					if ($this->hpos_enabled && $this->new_prod_editor_enabled) {
 	                    $hpos_prod_data = wc_get_product($post_id);
 	                    $hpos_prod_data->update_meta_data("hits_dhl_cc", (string) esc_html( $cc ));
 	                } else {
						update_post_meta( $post_id, 'hits_dhl_cc', (string) esc_html( $cc ) );
					}
					// print_r($post_id);die();
				}
				if( isset($_POST['hits_dhl_cc_inb']) ){
					$cc_inb = sanitize_text_field($_POST['hits_dhl_cc_inb']);
					if ($this->hpos_enabled && $this->new_prod_editor_enabled) {
 	                    $hpos_prod_data = wc_get_product($post_id);
 	                    $hpos_prod_data->update_meta_data("hits_dhl_cc_inb", (string) esc_html( $cc_inb ));
 	                } else {
						update_post_meta( $post_id, 'hits_dhl_cc_inb', (string) esc_html( $cc_inb ) );
					}
				}
				if( isset($_POST['hits_dhl_export_reason']) ){
					$cc = sanitize_text_field($_POST['hits_dhl_export_reason']);
					if ($this->hpos_enabled && $this->new_prod_editor_enabled) {
 	                    $hpos_prod_data = wc_get_product($post_id);
 	                    $hpos_prod_data->update_meta_data("hits_dhl_export_reason", (string) esc_html( $cc ));
 	                } else {
						update_post_meta( $post_id, 'hits_dhl_export_reason', (string) esc_html( $cc ) );
					}
					// print_r($post_id);die();
				}
				if( isset($_POST['hits_dhl_desc']) ){
					$cc = sanitize_text_field($_POST['hits_dhl_desc']);
					if ($this->hpos_enabled && $this->new_prod_editor_enabled) {
 	                    $hpos_prod_data = wc_get_product($post_id);
 	                    $hpos_prod_data->update_meta_data("hits_dhl_desc", (string) esc_html( $cc ));
 	                } else {
						update_post_meta( $post_id, 'hits_dhl_desc', (string) esc_html( $cc ) );
					}
					// print_r($post_id);die();
				}
				if( isset($_POST['hits_dhl_danger_good_content_id']) ){
					$cc = sanitize_text_field($_POST['hits_dhl_danger_good_content_id']);
					if ($this->hpos_enabled && $this->new_prod_editor_enabled) {
 	                    $hpos_prod_data = wc_get_product($post_id);
 	                    $hpos_prod_data->update_meta_data("hits_dhl_danger_good_content_id", (string) esc_html( $cc ));
 	                } else {
						update_post_meta( $post_id, 'hits_dhl_danger_good_content_id', (string) esc_html( $cc ) );
					}
					// print_r($post_id);die();
				}
				if( isset($_POST['hits_dhl_danger_good_label_description']) ){
					$cc = sanitize_text_field($_POST['hits_dhl_danger_good_label_description']);
					if ($this->hpos_enabled && $this->new_prod_editor_enabled) {
 	                    $hpos_prod_data = wc_get_product($post_id);
 	                    $hpos_prod_data->update_meta_data("hits_dhl_danger_good_label_description", (string) esc_html( $cc ));
 	                } else {
						update_post_meta( $post_id, 'hits_dhl_danger_good_label_description', (string) esc_html( $cc ) );
					}
					// print_r($post_id);die();
				}
				if( isset($_POST['hits_dhl_danger_good_un_code']) ){
					$cc = sanitize_text_field($_POST['hits_dhl_danger_good_un_code']);
					if ($this->hpos_enabled && $this->new_prod_editor_enabled) {
 	                    $hpos_prod_data = wc_get_product($post_id);
 	                    $hpos_prod_data->update_meta_data("hits_dhl_danger_good_un_code", (string) esc_html( $cc ));
 	                } else {
						update_post_meta( $post_id, 'hits_dhl_danger_good_un_code', (string) esc_html( $cc ) );
					}
					// print_r($post_id);die();
				}
				
			}

			public function hit_product_option_view(){
				global $woocommerce, $post;
				if ($this->hpos_enabled) {
                    $hpos_prod_data = wc_get_product($post->ID);
                    $hits_dhl_saved_cc = $hpos_prod_data->get_meta("hits_dhl_cc");
                    $hits_dhl_saved_cc_inb = $hpos_prod_data->get_meta("hits_dhl_cc_inb");
                    $hits_dhl_saved_export_reason = $hpos_prod_data->get_meta("hits_dhl_export_reason");
					$hits_dhl_saved_desc = $hpos_prod_data->get_meta("hits_dhl_desc");
					$hits_dhl_saved_Dgs_contentid = $hpos_prod_data->get_meta("hits_dhl_danger_good_content_id");
					$hits_dhl_saved_Dgs_label_desicribtion = $hpos_prod_data->get_meta("hits_dhl_danger_good_label_description");
					$hits_dhl_saved_Dgs_un_code = $hpos_prod_data->get_meta("hits_dhl_danger_good_un_code");
                } else {
					$hits_dhl_saved_cc = get_post_meta( $post->ID, 'hits_dhl_cc', true);
					$hits_dhl_saved_cc_inb = get_post_meta( $post->ID, 'hits_dhl_cc_inb', true);
					$hits_dhl_saved_export_reason = get_post_meta( $post->ID, 'hits_dhl_export_reason', true);
					$hits_dhl_saved_desc = get_post_meta( $post->ID, 'hits_dhl_desc', true);
					$hits_dhl_saved_Dgs_contentid = get_post_meta( $post->ID, 'hits_dhl_danger_good_content_id', true);
					$hits_dhl_saved_Dgs_label_desicribtion = get_post_meta( $post->ID, 'hits_dhl_danger_good_label_description', true);
					$hits_dhl_saved_Dgs_un_code = get_post_meta( $post->ID, 'hits_dhl_danger_good_un_code', true);
				}
				?>
				<div id='hit_dhl_product_options' class='panel woocommerce_options_panel'>
					<div class='options_group'>
						<p class="form-field">
							<label for="hits_dhl_cc"><?php _e( 'Enter Commodity code - outbound', 'a2z_dhlexpress' ); ?></label>
							<span class='woocommerce-help-tip' data-tip="<?php _e('Enter outbound commodity code for product (20 charcters max).','a2z_dhlexpress') ?>"></span>
							<input type='text' id='hits_dhl_cc' name='hits_dhl_cc' maxlength="20" <?php echo (!empty($hits_dhl_saved_cc) ? 'value="'.$hits_dhl_saved_cc.'"' : '');?> style="width: 90%;">
						</p>
					</div>
					<div class='options_group'>
						<p class="form-field">
							<label for="hits_dhl_cc_inb"><?php _e( 'Enter Commodity code - inbound', 'a2z_dhlexpress' ); ?></label>
							<span class='woocommerce-help-tip' data-tip="<?php _e('Enter inbound commodity code for product (20 charcters max).','a2z_dhlexpress') ?>"></span>
							<input type='text' id='hits_dhl_cc_inb' name='hits_dhl_cc_inb' maxlength="20" <?php echo (!empty($hits_dhl_saved_cc_inb) ? 'value="'.$hits_dhl_saved_cc_inb.'"' : '');?> style="width: 90%;">
						</p>
					</div>
					<div class='options_group'>
						<p class="form-field">
							<label for="hits_dhl_desc"><?php _e( 'Description of contents - (Invoice Product Name)', 'a2z_dhlexpress' ); ?></label>
							<input type='text' id='hits_dhl_desc' name='hits_dhl_desc' maxlength="100" <?php echo (!empty($hits_dhl_saved_desc) ? 'value="'.$hits_dhl_saved_desc.'"' : '');?> style="width: 90%;">
						</p>
					</div>
					
					<div class='options_group'>
						<p class="form-field">
							<label for="hits_dhl_danger_good_content_id"><?php _e( 'Dangerous Goods Content ID', 'a2z_dhlexpress' ); ?></label>
							<span class='woocommerce-help-tip' data-tip="<?php _e('Dangerous Goods Content ID for product (20 charcters max).','a2z_dhlexpress') ?>"></span>
							<input type='text' id='hits_dhl_danger_good_content_id' name='hits_dhl_danger_good_content_id' maxlength="20" <?php echo (!empty($hits_dhl_saved_Dgs_contentid) ? 'value="'.$hits_dhl_saved_Dgs_contentid.'"' : '');?> style="width: 90%;">
						</p>
					</div>
					<div class='options_group'>
						<p class="form-field">
							<label for="hits_dhl_danger_good_label_description"><?php _e( 'Dangerous Goods Label Description', 'a2z_dhlexpress' ); ?></label>
							<span class='woocommerce-help-tip' data-tip="<?php _e('Dangerous Goods Label Description for product (20 charcters max).','a2z_dhlexpress') ?>"></span>
							<input type='text' id='hits_dhl_danger_good_label_description' name='hits_dhl_danger_good_label_description' maxlength="20" <?php echo (!empty($hits_dhl_saved_Dgs_label_desicribtion) ? 'value="'.$hits_dhl_saved_Dgs_label_desicribtion.'"' : '');?> style="width: 90%;">
						</p>
					</div>
					<div class='options_group'>
						<p class="form-field">
							<label for="hits_dhl_danger_good_un_code"><?php _e( 'Dangerous Goods with Excepted Quantities attributes using UN code', 'a2z_dhlexpress' ); ?></label>
							<span class='woocommerce-help-tip' data-tip="<?php _e('Dangerous Goods with Excepted Quantities attributes using UN code for product (20 charcters max).','a2z_dhlexpress') ?>"></span>
							<input type='text' id='hits_dhl_danger_good_un_code' name='hits_dhl_danger_good_un_code' maxlength="20" <?php echo (!empty($hits_dhl_saved_Dgs_un_code) ? 'value="'.$hits_dhl_saved_Dgs_un_code.'"' : '');?> style="width: 90%;">
						</p>
					</div>
					
				</div>
				<?php
			}
			// Add text box input field to variation form
			public function hit_prod_add_variation_text_field( $loop, $variation_data, $variation ) {
				if ($this->hpos_enabled) {
                    $hpos_prod_data = wc_get_product($variation->ID);
                    $prod_var_desc = $hpos_prod_data->get_meta("hit_dhl_prod_variation_desc");
                } else {
					$prod_var_desc = get_post_meta( $variation->ID, 'hit_dhl_prod_variation_desc', true );
				}
			    woocommerce_wp_text_input( array(
			        'id'          => 'hit_dhl_prod_variation_desc' . $loop,
			        'label'       => __( 'Description of contents - (Invoice Product Name)', 'woocommerce' ),
			        'placeholder' => '',
			        // 'description' => __( 'Enter a custom text value for this variation', 'woocommerce' ),
			        'value'       => $prod_var_desc,
			    ) );
			}
			// Save variation text field value
			function hit_prod_save_variation_text_field( $variation_id, $i ) {
			    $custom_text_field = $_POST['hit_dhl_prod_variation_desc' . $i];
			    if ( ! empty( $custom_text_field ) ) {
			    	if ($this->hpos_enabled && $this->new_prod_editor_enabled) {
 	                    $hpos_prod_data = wc_get_product($variation_id);
 	                    $hpos_prod_data->update_meta_data("hit_dhl_prod_variation_desc", (string) esc_html( $custom_text_field ));
 	                } else {
				        update_post_meta( $variation_id, 'hit_dhl_prod_variation_desc', sanitize_text_field( $custom_text_field ) );
				    }
			    }
			}
			public function hit_bulk_order_menu( $actions ) {
				// echo "<pre>";print_r($actions);die();
				$actions['create_label_shipo'] = __( 'Create Labels - Shipi', 'a2z_dhlexpress' );
				return $actions;
			}

			public function hit_bulk_create_order($redirect_to, $action, $order_ids){
				$success = 0;
				$failed = 0;
				$failed_ids = [];
				if($action == "create_label_shipo"){
					
					if(!empty($order_ids)){
						$general_settings = get_option('a2z_dhl_main_settings',array());
						$create_shipment_for = "default";
						$service_code = "N";
						$ship_content = isset($general_settings['a2z_dhlexpress_ship_content']) ? sanitize_text_field($general_settings['a2z_dhlexpress_ship_content']) : 'Shipment Content';
						$pickup_mode = 'manual';
						
						foreach($order_ids as $key => $order_id){
							$order = wc_get_order( $order_id );
							if($order){

									$order_data = $order->get_data();
									$order_id = $order_data['id'];
									$order_currency = $order_data['currency'];

									$shipping_arr = (isset($order_data['shipping']['first_name']) && $order_data['shipping']['first_name'] != "") ? $order_data['shipping'] : $order_data['billing'];
									$order_shipping_first_name = $shipping_arr['first_name'];
									$order_shipping_last_name = $shipping_arr['last_name'];
									$order_shipping_company = empty($shipping_arr['company']) ? $shipping_arr['first_name'] :  $shipping_arr['company'];
									$order_shipping_address_1 = $shipping_arr['address_1'];
									$order_shipping_address_2 = $shipping_arr['address_2'];
									$order_shipping_city = $shipping_arr['city'];
									$order_shipping_state = $shipping_arr['state'];
									$order_shipping_postcode = $shipping_arr['postcode'];
									$order_shipping_country = $shipping_arr['country'];
									$order_shipping_phone = $order_data['billing']['phone'];
									$order_shipping_email = $order_data['billing']['email'];

									// $order_shipping_first_name = $order_data['shipping']['first_name'];
									// $order_shipping_last_name = $order_data['shipping']['last_name'];
									// $order_shipping_company = empty($order_data['shipping']['company']) ? $order_data['shipping']['first_name'] :  $order_data['shipping']['company'];
									// $order_shipping_address_1 = $order_data['shipping']['address_1'];
									// $order_shipping_address_2 = $order_data['shipping']['address_2'];
									// $order_shipping_city = $order_data['shipping']['city'];
									// $order_shipping_state = $order_data['shipping']['state'];
									// $order_shipping_postcode = $order_data['shipping']['postcode'];
									// $order_shipping_country = $order_data['shipping']['country'];
									// $order_shipping_phone = $order_data['billing']['phone'];
									// $order_shipping_email = $order_data['billing']['email'];
									
									
									$items = $order->get_items();
									$pack_products = array();

									if($general_settings['a2z_dhlexpress_country'] != $order_shipping_country){
										$service_code = "P";
									}

									foreach ( $items as $item ) {
										$product_data = $item->get_data();

										$product = array();
										$product['product_name'] = str_replace('"', '', $product_data['name']);
										$product['product_quantity'] = $product_data['quantity'];
										$product['product_id'] = $product_data['product_id'];

										if ($this->hpos_enabled) {
						                    $hpos_prod_data = wc_get_product($product_data['product_id']);
						                    $saved_cc = $hpos_prod_data->get_meta("hits_dhl_cc");
						                    $saved_cc_inb = $hpos_prod_data->get_meta("hits_dhl_cc_inb");
						                    $saved_desc = $hpos_prod_data->get_meta("hits_dhl_desc");
											$dgs_contentid = $hpos_prod_data->get_meta("hits_dhl_danger_good_content_id");
											$dgs_label_des = $hpos_prod_data->get_meta("hits_dhl_danger_good_label_description");
											$dgs_uncode = $hpos_prod_data->get_meta("hits_dhl_danger_good_un_code");
						                } else {
											$saved_cc = get_post_meta( $product_data['product_id'], 'hits_dhl_cc', true);
											$saved_cc_inb = get_post_meta( $product_data['product_id'], 'hits_dhl_cc_inb', true);
											$saved_desc = get_post_meta( $product_data['product_id'], 'hits_dhl_desc', true);
											$dgs_contentid = get_post_meta( $product_data['product_id'], 'hits_dhl_danger_good_content_id', true);
											$dgs_label_des = get_post_meta( $product_data['product_id'], 'hits_dhl_danger_good_label_description', true);
											$dgs_uncode = get_post_meta( $product_data['product_id'], 'hits_dhl_danger_good_un_code', true);
										}
										if(!empty($saved_cc)){
											$product['commodity_code'] = $saved_cc;
										}
										if(!empty($saved_cc_inb)){
											$product['commodity_code_inb'] = apply_filters("a2z_dhlexpress_cc_inb", $saved_cc_inb, $product_data['product_id'], $order_shipping_country);
										}
										if(!empty($saved_desc)){
											$product['invoice_desc'] = $saved_desc;
										}
										if(!empty($dgs_contentid)){
											$product['danger_good_contentid'] = $dgs_contentid;
										}
										if(!empty($dgs_label_des)){
											$product['danger_good_label_desci'] = $dgs_label_des;
										}
										if(!empty($dgs_uncode)){
											$product['danger_good_uncode'] = $dgs_uncode;
										}

										$product_variation_id = $item->get_variation_id();
										if(empty($product_variation_id)){
											$getproduct = wc_get_product( $product_data['product_id'] );
										}else{
											$getproduct = wc_get_product( $product_variation_id );
											if ($this->hpos_enabled) {
							                    $hpos_prod_data = wc_get_product($product_variation_id);
							                    $prod_variation_desc = $hpos_prod_data->get_meta("hit_dhl_prod_variation_desc");
							                } else {
							                	$prod_variation_desc = get_post_meta( $product_variation_id, 'hit_dhl_prod_variation_desc', true );
							                }
											if (!empty($prod_variation_desc)) {
												$product['invoice_desc'] = $prod_variation_desc;
											}
										}

										$skip = apply_filters("a2z_dhlexpress_skip_sku_from_label", false, $getproduct->get_sku());
										if($skip){
											continue;
										}
										
										$woo_weight_unit = get_option('woocommerce_weight_unit');
										$woo_dimension_unit = get_option('woocommerce_dimension_unit');

										$dhl_mod_weight_unit = $dhl_mod_dim_unit = '';

										if(!empty($general_settings['a2z_dhlexpress_weight_unit']) && $general_settings['a2z_dhlexpress_weight_unit'] == 'KG_CM')
										{
											$dhl_mod_weight_unit = 'kg';
											$dhl_mod_dim_unit = 'cm';
										}elseif(!empty($general_settings['a2z_dhlexpress_weight_unit']) && $general_settings['a2z_dhlexpress_weight_unit'] == 'LB_IN')
										{
											$dhl_mod_weight_unit = 'lbs';
											$dhl_mod_dim_unit = 'in';
										}
										else
										{
											$dhl_mod_weight_unit = 'kg';
											$dhl_mod_dim_unit = 'cm';
										}
										$product['sku'] =  $getproduct->get_sku();
										$product['price'] = (isset($product_data['total']) && isset($product_data['quantity'])) ? number_format(($product_data['total'] / $product_data['quantity']), 2) : 0;
										

										if ($woo_dimension_unit != $dhl_mod_dim_unit) {
										$prod_width = $getproduct->get_width();
										$prod_height = $getproduct->get_height();
										$prod_depth = $getproduct->get_length();

										//wc_get_dimension( $dimension, $to_unit, $from_unit );
										$product['width'] = (!empty($prod_width) && $prod_width > 0) ? round(wc_get_dimension( $prod_width, $dhl_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;
										$product['height'] =  (!empty($prod_height) && $prod_height > 0) ? round(wc_get_dimension( $prod_height, $dhl_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;
										$product['depth'] =  (!empty($prod_depth) && $prod_depth > 0) ? round(wc_get_dimension( $prod_depth, $dhl_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;

										}else {
											$product['width'] = $getproduct->get_width();
											$product['height'] = $getproduct->get_height();
											$product['depth'] = $getproduct->get_length();
										}
										
										if ($woo_weight_unit != $dhl_mod_weight_unit) {
											$prod_weight = $getproduct->get_weight();
											$product['weight'] =  (!empty($prod_weight) && $prod_weight > 0 ) ? round(wc_get_dimension( $prod_weight, $dhl_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;
										}else{
											$product['weight'] = $getproduct->get_weight();
										}

										$pack_products[] = $product;
										
									}
									if (empty($pack_products)) {
										$failed += 1;
										$failed_ids[] = $order_id;
										continue;
									}
									// $desination_country = (isset($order_data['shipping']['country']) && $order_data['shipping']['country'] != '') ? $order_data['shipping']['country'] : $order_data['billing']['country'];
									// if(isset($general_settings['a2z_dhlexpress_country']) && $general_settings["a2z_dhlexpress_country"] == $desination_country && $general_settings["a2z_dhlexpress_country"] !='null'){
									// 	$service_code = $general_settings['a2z_dhlexpress_Domestic_service'];
									// }elseif(isset($general_settings['a2z_dhlexpress_country']) && $general_settings["a2z_dhlexpress_country"] != $desination_country && $general_settings["a2z_dhlexpress_country"] !='null'){
									// 	$service_code = $general_settings['a2z_dhlexpress_international_service'];
									// }
									
									$custom_settings = array();
									$custom_settings['default'] = array(
														'a2z_dhlexpress_api_type' => isset($general_settings['a2z_dhlexpress_api_type']) ? $general_settings['a2z_dhlexpress_api_type'] : "",
														'a2z_dhlexpress_site_id' => $general_settings['a2z_dhlexpress_site_id'],
														'a2z_dhlexpress_site_pwd' => $general_settings['a2z_dhlexpress_site_pwd'],
														'a2z_dhlexpress_acc_no' => $general_settings['a2z_dhlexpress_acc_no'],
														'a2z_dhlexpress_import_no' => $general_settings['a2z_dhlexpress_import_no'],
														'a2z_dhlexpress_shipper_name' => $general_settings['a2z_dhlexpress_shipper_name'],
														'a2z_dhlexpress_company' => $general_settings['a2z_dhlexpress_company'],
														'a2z_dhlexpress_mob_num' => $general_settings['a2z_dhlexpress_mob_num'],
														'a2z_dhlexpress_email' => $general_settings['a2z_dhlexpress_email'],
														'a2z_dhlexpress_address1' => $general_settings['a2z_dhlexpress_address1'],
														'a2z_dhlexpress_address2' => $general_settings['a2z_dhlexpress_address2'],
														'a2z_dhlexpress_city' => $general_settings['a2z_dhlexpress_city'],
														'a2z_dhlexpress_state' => $general_settings['a2z_dhlexpress_state'],
														'a2z_dhlexpress_zip' => $general_settings['a2z_dhlexpress_zip'],
														'a2z_dhlexpress_country' => $general_settings['a2z_dhlexpress_country'],
														'a2z_dhlexpress_gstin' => $general_settings['a2z_dhlexpress_gstin'],
														'a2z_dhlexpress_con_rate' => $general_settings['a2z_dhlexpress_con_rate'],
														'service_code' => $service_code,
														'a2z_dhlexpress_label_email' => $general_settings['a2z_dhlexpress_label_email'],
														'a2z_dhlexpress_sig_img_url' => isset($general_settings['a2z_dhlexpress_sig_img_url']) ? $general_settings['a2z_dhlexpress_sig_img_url'] : ""
													);
									$vendor_settings = array();
									if(isset($general_settings['a2z_dhlexpress_v_enable']) && $general_settings['a2z_dhlexpress_v_enable'] == 'yes' && isset($general_settings['a2z_dhlexpress_v_labels']) && $general_settings['a2z_dhlexpress_v_labels'] == 'yes'){
									// Multi Vendor Enabled
									foreach ($pack_products as $key => $value) {
										$product_id = $value['product_id'];
										if ($this->hpos_enabled) {
							                $hpos_prod_data = wc_get_product($product_id);
							                $dhl_account = $hpos_prod_data->get_meta("dhl_express_address");
							            } else {
											$dhl_account = get_post_meta($product_id,'dhl_express_address', true);
										}
										if(empty($dhl_account) || $dhl_account == 'default'){
											$dhl_account = 'default';
											if (!isset($vendor_settings[$dhl_account])) {
												$vendor_settings[$dhl_account] = $custom_settings['default'];
											}
											
											$vendor_settings[$dhl_account]['products'][] = $value;
										}

										if($dhl_account != 'default'){
											$user_account = get_post_meta($dhl_account,'a2z_dhl_vendor_settings', true);
											$user_account = empty($user_account) ? array() : $user_account;
											if(!empty($user_account)){
												if(!isset($vendor_settings[$dhl_account])){

													$vendor_settings[$dhl_account] = $custom_settings['default'];
													
												if($user_account['a2z_dhlexpress_site_id'] != '' && $user_account['a2z_dhlexpress_site_pwd'] != '' && $user_account['a2z_dhlexpress_acc_no'] != ''){
													$vendor_settings[$dhl_account]['a2z_dhlexpress_api_type'] = isset($user_account['a2z_dhlexpress_api_type']) ? $user_account['a2z_dhlexpress_api_type'] : "";
													$vendor_settings[$dhl_account]['a2z_dhlexpress_site_id'] = $user_account['a2z_dhlexpress_site_id'];

													if($user_account['a2z_dhlexpress_site_pwd'] != ''){
														$vendor_settings[$dhl_account]['a2z_dhlexpress_site_pwd'] = $user_account['a2z_dhlexpress_site_pwd'];
													}

													if($user_account['a2z_dhlexpress_acc_no'] != ''){
														$vendor_settings[$dhl_account]['a2z_dhlexpress_acc_no'] = $user_account['a2z_dhlexpress_acc_no'];
													}

													$vendor_settings[$dhl_account]['a2z_dhlexpress_import_no'] = !empty($user_account['a2z_dhlexpress_import_no']) ? $user_account['a2z_dhlexpress_import_no'] : '';
													
												}

												if ($user_account['a2z_dhlexpress_address1'] != '' && $user_account['a2z_dhlexpress_city'] != '' && $user_account['a2z_dhlexpress_state'] != '' && $user_account['a2z_dhlexpress_zip'] != '' && $user_account['a2z_dhlexpress_country'] != '' && $user_account['a2z_dhlexpress_shipper_name'] != '') {
													
													if($user_account['a2z_dhlexpress_shipper_name'] != ''){
														$vendor_settings[$dhl_account]['a2z_dhlexpress_shipper_name'] = $user_account['a2z_dhlexpress_shipper_name'];
													}

													if($user_account['a2z_dhlexpress_company'] != ''){
														$vendor_settings[$dhl_account]['a2z_dhlexpress_company'] = $user_account['a2z_dhlexpress_company'];
													}

													if($user_account['a2z_dhlexpress_mob_num'] != ''){
														$vendor_settings[$dhl_account]['a2z_dhlexpress_mob_num'] = $user_account['a2z_dhlexpress_mob_num'];
													}

													if($user_account['a2z_dhlexpress_email'] != ''){
														$vendor_settings[$dhl_account]['a2z_dhlexpress_email'] = $user_account['a2z_dhlexpress_email'];
													}

													if ($user_account['a2z_dhlexpress_address1'] != '') {
														$vendor_settings[$dhl_account]['a2z_dhlexpress_address1'] = $user_account['a2z_dhlexpress_address1'];
													}

													$vendor_settings[$dhl_account]['a2z_dhlexpress_address2'] = $user_account['a2z_dhlexpress_address2'];
													
													if($user_account['a2z_dhlexpress_city'] != ''){
														$vendor_settings[$dhl_account]['a2z_dhlexpress_city'] = $user_account['a2z_dhlexpress_city'];
													}

													if($user_account['a2z_dhlexpress_state'] != ''){
														$vendor_settings[$dhl_account]['a2z_dhlexpress_state'] = $user_account['a2z_dhlexpress_state'];
													}

													if($user_account['a2z_dhlexpress_zip'] != ''){
														$vendor_settings[$dhl_account]['a2z_dhlexpress_zip'] = $user_account['a2z_dhlexpress_zip'];
													}

													if($user_account['a2z_dhlexpress_country'] != ''){
														$vendor_settings[$dhl_account]['a2z_dhlexpress_country'] = $user_account['a2z_dhlexpress_country'];
													}

													$vendor_settings[$dhl_account]['a2z_dhlexpress_gstin'] = $user_account['a2z_dhlexpress_gstin'];
													$vendor_settings[$dhl_account]['a2z_dhlexpress_con_rate'] = $user_account['a2z_dhlexpress_con_rate'];

												}
													
													if(isset($general_settings['a2z_dhlexpress_v_email']) && $general_settings['a2z_dhlexpress_v_email'] == 'yes'){
														$user_dat = get_userdata($dhl_account);
														$vendor_settings[$dhl_account]['a2z_dhlexpress_label_email'] = $user_dat->data->user_email;
													}
													

													if($order_data['shipping']['country'] != $vendor_settings[$dhl_account]['a2z_dhlexpress_country']){
														$vendor_settings[$dhl_account]['service_code'] = empty($service_code) ? $user_account['a2z_dhlexpress_def_inter'] : $service_code;
													}else{
														$vendor_settings[$dhl_account]['service_code'] = empty($service_code) ? $user_account['a2z_dhlexpress_def_dom'] : $service_code;
													}

													if (isset($user_account['a2z_dhlexpress_sig_img_url']) && !empty($user_account['a2z_dhlexpress_sig_img_url'])) {
														$vendor_settings[$dhl_account]['a2z_dhlexpress_sig_img_url'] = $user_account['a2z_dhlexpress_sig_img_url'];
													}
												}
												$vendor_settings[$dhl_account]['products'][] = $value;
											}
										}

									}

								}

								if(empty($vendor_settings)){
									$custom_settings['default']['products'] = $pack_products;
								}else{
									$custom_settings = $vendor_settings;
								}

								if(!empty($general_settings) && isset($general_settings['a2z_dhlexpress_integration_key']) && isset($custom_settings[$create_shipment_for])){
									$mode = 'live';
									if(isset($general_settings['a2z_dhlexpress_test']) && $general_settings['a2z_dhlexpress_test']== 'yes'){
										$mode = 'test';
									}

									$execution = 'manual';
									
									$boxes_to_shipo = array();
									if (isset($general_settings['a2z_dhlexpress_packing_type']) && $general_settings['a2z_dhlexpress_packing_type'] == "box") {
										if (isset($general_settings['a2z_dhlexpress_boxes']) && !empty($general_settings['a2z_dhlexpress_boxes'])) {
											foreach ($general_settings['a2z_dhlexpress_boxes'] as $box) {
												if ($box['enabled'] != 1) {
													continue;
												}else {
													$boxes_to_shipo[] = $box;
												}
											}
										}
									}

									global $dhl_core;
									$frm_curr = get_option('woocommerce_currency');
									$to_curr = isset($dhl_core[$custom_settings[$create_shipment_for]['a2z_dhlexpress_country']]) ? $dhl_core[$custom_settings[$create_shipment_for]['a2z_dhlexpress_country']]['currency'] : '';
									$curr_con_rate = ( isset($custom_settings[$create_shipment_for]['a2z_dhlexpress_con_rate']) && !empty($custom_settings[$create_shipment_for]['a2z_dhlexpress_con_rate']) ) ? $custom_settings[$create_shipment_for]['a2z_dhlexpress_con_rate'] : 0;

									if (!empty($frm_curr) && !empty($to_curr) && ($frm_curr != $to_curr) ) {
										if (isset($general_settings['a2z_dhlexpress_auto_con_rate']) && $general_settings['a2z_dhlexpress_auto_con_rate'] == "yes") {
											$current_date = date('m-d-Y', time());
											$ex_rate_data = get_option('a2z_dhl_ex_rate'.$create_shipment_for);
											$ex_rate_data = !empty($ex_rate_data) ? $ex_rate_data : array();
											if (empty($ex_rate_data) || (isset($ex_rate_data['date']) && $ex_rate_data['date'] != $current_date) ) {
												if (isset($custom_settings[$create_shipment_for]['a2z_dhlexpress_country']) && !empty($custom_settings[$create_shipment_for]['a2z_dhlexpress_country']) && isset($general_settings['a2z_dhlexpress_integration_key']) && !empty($general_settings['a2z_dhlexpress_integration_key'])) {
													
													$ex_rate_Request = json_encode(array('integrated_key' => $general_settings['a2z_dhlexpress_integration_key'],
																		'from_curr' => $frm_curr,
																		'to_curr' => $to_curr));

													$ex_rate_url = "https://app.myshipi.com/get_exchange_rate.php";
													$ex_rate_response = wp_remote_post( $ex_rate_url , array(
																	'method'      => 'POST',
																	'timeout'     => 45,
																	'redirection' => 5,
																	'httpversion' => '1.0',
																	'blocking'    => true,
																	'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
																	'body'        => $ex_rate_Request,
																	'sslverify'   => true
																	)
																);

													$ex_rate_result = ( is_array($ex_rate_response) && isset($ex_rate_response['body'])) ? json_decode($ex_rate_response['body'], true) : array();

													if ( !empty($ex_rate_result) && isset($ex_rate_result['ex_rate']) && $ex_rate_result['ex_rate'] != "Not Found" ) {
														$ex_rate_result['date'] = $current_date;
														update_option('a2z_dhl_ex_rate'.$create_shipment_for, $ex_rate_result);
													}else {
														if (!empty($ex_rate_data)) {
															$ex_rate_data['date'] = $current_date;
															update_option('a2z_dhl_ex_rate'.$create_shipment_for, $ex_rate_data);
														}
													}
												}
											}
											$get_ex_rate = get_option('a2z_dhl_ex_rate'.$create_shipment_for, '');
											$get_ex_rate = !empty($get_ex_rate) ? $get_ex_rate : array();
											$curr_con_rate = ( !empty($get_ex_rate) && isset($get_ex_rate['ex_rate']) ) ? $get_ex_rate['ex_rate'] : 0;
										}
									}

									$c_codes = [];

									foreach($custom_settings[$create_shipment_for]['products'] as $prod_to_shipo_key => $prod_to_shipo){
										if ($this->hpos_enabled) {
						                    $hpos_prod_data = wc_get_product($prod_to_shipo['product_id']);
						                    $saved_cc = $hpos_prod_data->get_meta("hits_dhl_cc");
						                } else {
											$saved_cc = get_post_meta( $prod_to_shipo['product_id'], 'hits_dhl_cc', true);
										}
										if(!empty($saved_cc)){
											$c_codes[] = $saved_cc;
										}

										if (!empty($frm_curr) && !empty($to_curr) && ($frm_curr != $to_curr) ) {
											if ($curr_con_rate > 0 && apply_filters("hit_do_conversion_while_label_generation", true, $order_shipping_country)) {
												$custom_settings[$create_shipment_for]['products'][$prod_to_shipo_key]['price'] = $prod_to_shipo['price'] * $curr_con_rate;
											}
										}
									}
									$insurance = apply_filters("hitshipo_ins_ship", $general_settings['a2z_dhlexpress_insure'], $create_shipment_for, $order);
									$insurance_value = apply_filters("hitshipo_ins_val_ship", 0, $create_shipment_for, $order);
									
									$data = array();
									$data['integrated_key'] = $general_settings['a2z_dhlexpress_integration_key'];
									$data['order_id'] = $order_id;
									$data['exec_type'] = $execution;
									$data['mode'] = $mode;
									$data['carrier_type'] = 'dhl';
									$data['meta'] = array(
										"api_type" => (isset($custom_settings[$create_shipment_for]['a2z_dhlexpress_api_type']) && !empty($custom_settings[$create_shipment_for]['a2z_dhlexpress_api_type'])) ? $custom_settings[$create_shipment_for]['a2z_dhlexpress_api_type'] : "SOAP",
										"site_id" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_site_id'],
										"password"  => $custom_settings[$create_shipment_for]['a2z_dhlexpress_site_pwd'],
										"accountnum" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_acc_no'],
										"t_company" => $order_shipping_company,
										"t_address1" => str_replace('"', '', $order_shipping_address_1),
										"t_address2" => str_replace('"', '', $order_shipping_address_2),
										"t_city" => $order_shipping_city,
										"t_state" => $order_shipping_state,
										"t_postal" => $order_shipping_postcode,
										"t_country" => $order_shipping_country,
										"t_name" => $order_shipping_first_name . ' '. $order_shipping_last_name,
										"t_phone" => $order_shipping_phone,
										"t_email" => $order_shipping_email,
										"t_gstin" => apply_filters("hitshipo_dhlexpress_receiver_vat", "", $order),
										"dutiable" => $general_settings['a2z_dhlexpress_duty_payment'],
										"insurance" => $insurance,
										"cus_ins_val" => $insurance_value,
										"pack_this" => "Y",
										"products" => apply_filters("hitshipo_prods_to_ship", $custom_settings[$create_shipment_for]['products'], $order, $create_shipment_for),
										"pack_algorithm" => $general_settings['a2z_dhlexpress_packing_type'],
										"boxes" => $boxes_to_shipo,
										"max_weight" => $general_settings['a2z_dhlexpress_max_weight'],
										"plt" => ($general_settings['a2z_dhlexpress_ppt'] == 'yes') ? "Y" : "N",
										"airway_bill" => ($general_settings['a2z_dhlexpress_aabill'] == 'yes') ? "Y" : "N",
										"sd" => ($general_settings['a2z_dhlexpress_sat'] == 'yes') ? "Y" : "N",
										"cod" => ($general_settings['a2z_dhlexpress_cod'] == 'yes') ? "Y" : "N",
										"service_code" => $custom_settings[$create_shipment_for]['service_code'],
										"email_alert" => ( isset($general_settings['a2z_dhlexpress_email_alert']) && ($general_settings['a2z_dhlexpress_email_alert'] == 'yes') ) ? "Y" : "N",
										"shipment_content" => apply_filters("hitshipo_dhl_cus_ship_desc", $ship_content, $order_id, $create_shipment_for),
										"danger_goods_item" => isset($general_settings['a2z_dhlexpress_dgs']) ? $general_settings['a2z_dhlexpress_dgs'] : 'no',
										"s_company" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_company'],
										"s_address1" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_address1'],
										"s_address2" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_address2'],
										"s_city" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_city'],
										"s_state" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_state'],
										"s_postal" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_zip'],
										"s_country" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_country'],
										"gstin" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_gstin'],
										"s_name" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_shipper_name'],
										"s_phone" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_mob_num'],
										"s_email" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_email'],
										"label_size" => $general_settings['a2z_dhlexpress_print_size'],
										"sent_email_to" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_label_email'],
										"sig_img_url" => isset($custom_settings[$create_shipment_for]['a2z_dhlexpress_sig_img_url']) ? $custom_settings[$create_shipment_for]['a2z_dhlexpress_sig_img_url'] : "",
										"pic_exec_type" => $pickup_mode,
										"pic_loc_type" => (isset($general_settings['a2z_dhlexpress_pickup_loc_type']) ? $general_settings['a2z_dhlexpress_pickup_loc_type'] : ''),
										"pic_pac_loc" => (isset($general_settings['a2z_dhlexpress_pickup_pac_loc']) ? $general_settings['a2z_dhlexpress_pickup_pac_loc'] : ''),
										"pic_contact_per" => (isset($general_settings['a2z_dhlexpress_pickup_per_name']) ? $general_settings['a2z_dhlexpress_pickup_per_name'] : ''),
										"pic_contact_no" => (isset($general_settings['a2z_dhlexpress_pickup_per_contact_no']) ? $general_settings['a2z_dhlexpress_pickup_per_contact_no'] : ''),
										"pic_door_to" => (isset($general_settings['a2z_dhlexpress_pickup_door_to']) ? $general_settings['a2z_dhlexpress_pickup_door_to'] : ''),
										"pic_type" => (isset($general_settings['a2z_dhlexpress_pickup_type']) ? $general_settings['a2z_dhlexpress_pickup_type'] : ''),
										"pic_days_after" => (isset($general_settings['a2z_dhlexpress_pickup_date']) ? $general_settings['a2z_dhlexpress_pickup_date'] : ''),
										"pic_open_time" => (isset($general_settings['a2z_dhlexpress_pickup_open_time']) ? $general_settings['a2z_dhlexpress_pickup_open_time'] : ''),
										"pic_close_time" => (isset($general_settings['a2z_dhlexpress_pickup_close_time']) ? $general_settings['a2z_dhlexpress_pickup_close_time'] : ''),
										"pic_mail_date" => date('c'),
										"pic_date" => date("Y-m-d"),
										"payment_con" => (isset($general_settings['a2z_dhlexpress_pay_con']) ? $general_settings['a2z_dhlexpress_pay_con'] : 'S'),
										"cus_payment_con" => (isset($general_settings['a2z_dhlexpress_cus_pay_con']) ? $general_settings['a2z_dhlexpress_cus_pay_con'] : ''),
										"translation" => ( (isset($general_settings['a2z_dhlexpress_translation']) && $general_settings['a2z_dhlexpress_translation'] == "yes" ) ? 'Y' : 'N'),
										"translation_key" => (isset($general_settings['a2z_dhlexpress_translation_key']) ? $general_settings['a2z_dhlexpress_translation_key'] : ''),
										"commodity_code" => $c_codes,
										"ship_price" => isset($order_data['shipping_total']) ? $order_data['shipping_total'] : 0,
										"order_total" => isset($order_data['total']) ? $order_data['total'] : 0,
										"order_total_tax" => isset($order_data['total_tax']) ? $order_data['total_tax'] : 0,
										"inv_type" => isset($general_settings['a2z_dhlexpress_inv_type']) ? $general_settings['a2z_dhlexpress_inv_type'] : "",
										"inv_temp_type" => isset($general_settings['a2z_dhlexpress_inv_temp_type']) ? $general_settings['a2z_dhlexpress_inv_temp_type'] : "",
										"label" => $create_shipment_for,
										"export_reason" => (isset($general_settings['a2z_dhlexpress_export_reason']) ? $general_settings['a2z_dhlexpress_export_reason'] : 'P')
									);
									
									//Bulk shipment
									$bulk_shipment_url = "https://app.myshipi.com/label_api/create_shipment.php";
									$response = wp_remote_post( $bulk_shipment_url , array(
										'method'      => 'POST',
										'timeout'     => 45,
										'redirection' => 5,
										'httpversion' => '1.0',
										'blocking'    => true,
										'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
										'body'        => json_encode($data),
										'sslverify'   => true
										)
									);
									
									$output = (is_array($response) && isset($response['body'])) ? json_decode($response['body'],true) : [];
									
										if($output){
											if(isset($output['status']) || isset($output['pic_status'])){

												if(isset($output['status']) && $output['status'] != 'success'){
													// update_option('hit_dhl_status_'.$order_id, $output['status'][0]);
													$failed += 1;
													$failed_ids[] = $order_id;

												}else if(isset($output['status']) &&  $output['status'] == 'success'){
													$output['user_id'] = $create_shipment_for;
													$result_arr = array();
													$data = get_option('hit_dhl_values_'.$order_id, array());
													if($data){
														$result_arr = json_decode($data, true);
													}
													
													$result_arr[] = $output;	

													update_option('hit_dhl_values_'.$order_id, json_encode(apply_filters("shipi_dhl_express_save_output", $result_arr, $order_id)));

													$success += 1;
													
												}
												
												if (isset($output['pic_status']) && $output['pic_status'] != 'success') {
													$pic_res['status'] = "failed";
													update_option('hit_dhl_pickup_values_'.$order_id, json_encode($pic_res));
												}elseif (isset($output['pic_status']) && $output['pic_status'] == 'success') {
													$pic_res['confirm_no'] = $output['confirm_no'];
													$pic_res['ready_time'] = $output['ready_time'];
													$pic_res['pickup_date'] = $output['pickup_date'];
													$pic_res['status'] = "success";

													update_option('hit_dhl_pickup_values_'.$order_id, json_encode($pic_res));
												}
											}else{
												$failed += 1;
												$failed_ids[] = $order_id;
											}
										}else{
											$failed += 1;
											$failed_ids[] = $order_id;
										}
									}
							}else{
								$failed += 1;
							}
							
						}
						return $redirect_to = add_query_arg( array(
							'success_lbl' => $success,
							'failed_lbl' => $failed,
							// 'failed_lbl_ids' => implode( ',', rtrim($failed_ids, ",") ),
						), $redirect_to );
					}
				}
				
			}

			function shipo_bulk_label_action_admin_notice() {
				if(isset($_GET['success_lbl']) && isset($_GET['failed_lbl'])){
					printf( '<div id="message" class="updated fade"><p>
						Generated labels: '. esc_html($_GET['success_lbl']) .' Failed Label: '. esc_html($_GET['failed_lbl']).' </p></div>');
				}

			}

			public function dhl_track($order){
				$general_settings = get_option('a2z_dhl_main_settings',array());
				$order_id = $order->get_id();
				$json_data = get_option('hit_dhl_values_'.$order_id);

				if (!empty($json_data) && isset($general_settings['a2z_dhlexpress_trk_status_cus']) && $general_settings['a2z_dhlexpress_trk_status_cus'] == "yes") {

					$array_data_to_track = json_decode($json_data, true);
					$track_datas = array();

					if (isset($array_data_to_track[0])) {
						$track_datas = $array_data_to_track;
					}else {
						$track_datas[] = $array_data_to_track;
					}
					$trk_count = 1;
					$tot_trk_count = count($track_datas);
					
// echo '<pre>';print_r($array_data_to_track);echo '<br/>'; print_r($track_datas);die();

					if ($track_datas) {

						echo '<div style = "box-shadow: 1px 1px 10px 1px #d2d2d2;">
							<div style= "font-size: 1.5rem; padding: 20px;">
							DHL Express Tracking</div>';
						$to_disp = "";
						foreach ($track_datas as $value) {
							if (isset($general_settings['a2z_dhlexpress_site_id']) && isset($general_settings['a2z_dhlexpress_site_pwd'])) {
								$trk_no = $value['tracking_num']; // "4910036843";
								$user_id = $value['user_id'];		//Test track No : 2192079993	'.$trk_no.'

								if (isset($general_settings['a2z_dhlexpress_api_type']) && $general_settings['a2z_dhlexpress_api_type'] == "REST") {
									$result = wp_remote_post( "https://express.api.dhl.com/mydhlapi/shipments/".$trk_no."/tracking" , array(
										'method'      => 'GET',
										'timeout'     => 45,
										'redirection' => 5,
										'httpversion' => '1.0',
										'blocking'    => true,
										'sslverify'   => true,
										'headers' => ['Authorization' => 'Basic ' . base64_encode($general_settings['a2z_dhlexpress_site_id'] . ':' . $general_settings['a2z_dhlexpress_site_pwd'])]
										)
									);
								} else {
									$xml = '<?xml version="1.0" encoding="UTF-8"?>
											<req:KnownTrackingRequest xmlns:req="http://www.dhl.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.dhl.com TrackingRequestKnown.xsd" schemaVersion="1.0">
												<Request>
													<ServiceHeader>
														<MessageTime>2002-06-25T11:28:56-08:00</MessageTime>
														<MessageReference>1234567890123456789012345678</MessageReference>
														<SiteID>'.$general_settings['a2z_dhlexpress_site_id'].'</SiteID>
														<Password>'.$general_settings['a2z_dhlexpress_site_pwd'].'</Password>
													</ServiceHeader>
												</Request>
												<LanguageCode>en</LanguageCode>
												<AWBNumber>'.$trk_no.'</AWBNumber>
												<LevelOfDetails>ALL_CHECK_POINTS</LevelOfDetails>
												<PiecesEnabled>B</PiecesEnabled>
											</req:KnownTrackingRequest>';
									
									$result = wp_remote_post( "https://xmlpi-ea.dhl.com/XMLShippingServlet" , array(
										'method'      => 'POST',
										'timeout'     => 45,
										'redirection' => 5,
										'httpversion' => '1.0',
										'blocking'    => true,
										'body'        => $xml,
										'sslverify'   => true
										)
									);
								}

									if (isset($result['body']) && !empty($result['body'])) {
										if (isset($general_settings['a2z_dhlexpress_api_type']) && $general_settings['a2z_dhlexpress_api_type'] == "REST") {
											$xml = json_decode($result['body'], true);
										} else {
											$xml = simplexml_load_string($result['body']);
											$xml = json_decode(json_encode($xml), true);
										}

										if( (isset($xml['AWBInfo']['ShipmentInfo']['ShipmentEvent']) && !empty($xml['AWBInfo']['ShipmentInfo']['ShipmentEvent'])) || (isset($xml['shipments'][0]['events']) && !empty($xml['shipments'][0]['events'])) ){

											$events = isset($xml['AWBInfo']['ShipmentInfo']['ShipmentEvent']) ? $xml['AWBInfo']['ShipmentInfo']['ShipmentEvent'] : $xml['shipments'][0]['events'];
											$last_event_status = '';
											// echo '<pre>';print_r($events);die();
											$event_count = count($events);
        									if (isset($events[$event_count -1])) {
        										if (isset($events[$event_count -1]['ServiceEvent']['Description'])) {
        											$last_event_status = $events[$event_count -1]['ServiceEvent']['Description'];
        										} elseif (isset($events[$event_count -1]['description'])) {
        											$last_event_status = $events[$event_count -1]['description'];
        										}
        									}

											$to_disp = '<div style= "background-color:#4CBB87; width: 100%; height: 80px; display: flex; flex-direction: row;">
															<div style= "color: #ecf0f1; display: flex; flex-direction: column; align-items: center; padding: 23px; width: 50%;">Package Status: '.$last_event_status.'</div>
															<span style= "border-left: 4px solid #fdfdfd; margin-top: 20px; height: 40px;"></span>
															<div style= "color: #ecf0f1; display: flex; flex-direction: column; align-items: center; padding: 12px; width: 50%;">Package '.$trk_count.' of '.$tot_trk_count.'
																<span>Tracking No: '.$trk_no.'</span>
															</div>
														</div>
														<div style= "padding-bottom: 5px;">
															<ul style= "list-style: none; padding-bottom: 5px;">';
											
        									foreach ($events as $key => $value) {
        										if (isset($value['ServiceEvent']['Description'])) {
        											$event_status = $value['ServiceEvent']['Description'];
	        										$event_loc = $value['ServiceArea']['Description'];
	        										$event_time = date('h:i - A', strtotime($value['Time']));
	        										$event_date = date('M d Y', strtotime($value['Date']));
        										} elseif (isset($value['description'])) {
        											$event_status = $value['description'];
	        										$event_loc = isset($value['serviceArea'][0]['description']) ? $value['serviceArea'][0]['description'] : "";
	        										$event_time = isset($value['time']) ? date('h:i - A', strtotime($value['time'])) : "";
	        										$event_date = isset($value['date']) ? date('M d Y', strtotime($value['date'])) : "";
        										}
        										
// echo '<pre>';echo '<h4>XML</h4>';print_r($value);print_r($events);die();
        										$to_disp .= '<li style= "display: flex; flex-direction: row;">
																<div style= "display: flex;margin-top: 0px; margin-bottom: 0px; ">
																	<div style="border-left:1px #ecf0f1 solid; position: relative; left:161px; height:150%; margin-top: -28px; z-index: -1;"></div>
																	<div style= "display: flex; flex-direction: column; width: 120px; align-items: end;">
																		<p style= "font-weight: bold; margin: 0;">'.$event_date.'</p>
																		<p style= "margin: 0; color: #4a5568;">'.$event_time.'</p>
																	</div>
																	<div style= "display: flex; flex-direction: column; width: 80px; align-items: center;">';

														if ( (isset($value['ServiceEvent']['EventCode']) && $value['ServiceEvent']['EventCode'] == "OK") || (isset($value['typeCode']) && $value['typeCode'] == "OK") ) {
															$to_disp .= '<img style="width: 34px; height: 34px;" src="data:image/svg+xml;charset=utf-8;base64,PHN2ZyB4bWxuczpza2V0Y2g9Imh0dHA6Ly93d3cuYm9oZW1pYW5jb2RpbmcuY29tL3NrZXRjaC9ucyIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB2aWV3Qm94PSIwIDAgMTI4IDEyOCIgZW5hYmxlLWJhY2tncm91bmQ9Im5ldyAwIDAgMTI4IDEyOCI+PHN0eWxlIHR5cGU9InRleHQvY3NzIj4uc3Qwe2ZpbGw6IzRDQkI4Nzt9IC5zdDF7ZmlsbDojRkZGRkZGO308L3N0eWxlPjxnIGlkPSJEZWxpdmVkIiBza2V0Y2g6dHlwZT0iTVNMYXllckdyb3VwIj48cGF0aCBpZD0iT3ZhbC03LUNvcHktMiIgc2tldGNoOnR5cGU9Ik1TU2hhcGVHcm91cCIgY2xhc3M9InN0MCIgZD0iTTY0IDEyOGMzNS4zIDAgNjQtMjguNyA2NC02NHMtMjguNy02NC02NC02NC02NCAyOC43LTY0IDY0IDI4LjcgNjQgNjQgNjR6Ii8+PHBhdGggaWQ9IlNoYXBlIiBza2V0Y2g6dHlwZT0iTVNTaGFwZUdyb3VwIiBjbGFzcz0ic3QxIiBkPSJNODIuNSA1My4ybC0zLjQtMy40Yy0uNS0uNS0xLS43LTEuNy0uN3MtMS4yLjItMS43LjdsLTE2LjIgMTYuNS03LjMtNy40Yy0uNS0uNS0xLS43LTEuNy0uN3MtMS4yLjItMS43LjdsLTMuNCAzLjRjLS41LjUtLjcgMS0uNyAxLjdzLjIgMS4yLjcgMS43bDkgOS4xIDMuNCAzLjRjLjUuNSAxIC43IDEuNy43czEuMi0uMiAxLjctLjdsMy40LTMuNCAxNy45LTE4LjJjLjUtLjUuNy0xIC43LTEuN3MtLjItMS4yLS43LTEuN3oiLz48L2c+PC9zdmc+">';
														}else {
															$to_disp .= '<div style="width: 36px; height: 36px; border-radius: 50%; border-width: 1px; border-style: solid; border-color: #ecf0f1; margin-top: 10px; background-color: #ffffff;">
																		<div style="width: 12px; height: 12px; transform: translate(-50%,-50%); background-color: #ddd; border-radius: 100%; margin-top: 17px; margin-left: 17px;"></div>
																	</div>';
														}
														
														$to_disp .= '</div>
																	<div style= "display: flex; flex-direction: column; width: 250px;">
																		<p style= "font-weight: bold; margin: 0;">'.$event_status.'</p>
																		<p style= "margin: 0; color: #4a5568;">'.$event_loc.'</p>
																	</div>
																</div>
															</li>';
        									}
        									$to_disp .= '</ul></div>';
        								}else {
        									$to_disp = '<h4 style= "text-align: center;">Sorry! No data found for this package...<h4/></div>';
        									echo $to_disp;
        									return;
        								}
									}else {
										$to_disp = '<h4 style= "text-align: center;>Sorry! No data found for this package...<h4/></div>';
										echo $to_disp;
										return;
									}
							}
							$trk_count ++;
						}

						$to_disp .= '</div>';
						echo $to_disp;
					}
				}
			}
			public function save_user_fields($user_id){
				if(isset($_POST['a2z_dhlexpress_country'])){
					$general_settings['a2z_dhlexpress_api_type'] = sanitize_text_field(isset($_POST['a2z_dhlexpress_api_type']) ? $_POST['a2z_dhlexpress_api_type'] : '');
					$general_settings['a2z_dhlexpress_site_id'] = sanitize_text_field(isset($_POST['a2z_dhlexpress_site_id']) ? $_POST['a2z_dhlexpress_site_id'] : '');
					$general_settings['a2z_dhlexpress_site_pwd'] = sanitize_text_field(isset($_POST['a2z_dhlexpress_site_pwd']) ? $_POST['a2z_dhlexpress_site_pwd'] : '');
					$general_settings['a2z_dhlexpress_acc_no'] = sanitize_text_field(isset($_POST['a2z_dhlexpress_acc_no']) ? $_POST['a2z_dhlexpress_acc_no'] : '');
					$general_settings['a2z_dhlexpress_import_no'] = sanitize_text_field(isset($_POST['a2z_dhlexpress_import_no']) ? $_POST['a2z_dhlexpress_import_no'] : '');
					$general_settings['a2z_dhlexpress_shipper_name'] = sanitize_text_field(isset($_POST['a2z_dhlexpress_shipper_name']) ? $_POST['a2z_dhlexpress_shipper_name'] : '');
					$general_settings['a2z_dhlexpress_company'] = sanitize_text_field(isset($_POST['a2z_dhlexpress_company']) ? $_POST['a2z_dhlexpress_company'] : '');
					$general_settings['a2z_dhlexpress_mob_num'] = sanitize_text_field(isset($_POST['a2z_dhlexpress_mob_num']) ? $_POST['a2z_dhlexpress_mob_num'] : '');
					$general_settings['a2z_dhlexpress_email'] = sanitize_text_field(isset($_POST['a2z_dhlexpress_email']) ? $_POST['a2z_dhlexpress_email'] : '');
					$general_settings['a2z_dhlexpress_address1'] = sanitize_text_field(isset($_POST['a2z_dhlexpress_address1']) ? $_POST['a2z_dhlexpress_address1'] : '');
					$general_settings['a2z_dhlexpress_address2'] = sanitize_text_field(isset($_POST['a2z_dhlexpress_address2']) ? $_POST['a2z_dhlexpress_address2'] : '');
					$general_settings['a2z_dhlexpress_city'] = sanitize_text_field(isset($_POST['a2z_dhlexpress_city']) ? $_POST['a2z_dhlexpress_city'] : '');
					$general_settings['a2z_dhlexpress_state'] = sanitize_text_field(isset($_POST['a2z_dhlexpress_state']) ? $_POST['a2z_dhlexpress_state'] : '');
					$general_settings['a2z_dhlexpress_zip'] = sanitize_text_field(isset($_POST['a2z_dhlexpress_zip']) ? $_POST['a2z_dhlexpress_zip'] : '');
					$general_settings['a2z_dhlexpress_country'] = sanitize_text_field(isset($_POST['a2z_dhlexpress_country']) ? $_POST['a2z_dhlexpress_country'] : '');
					$general_settings['a2z_dhlexpress_gstin'] = sanitize_text_field(isset($_POST['a2z_dhlexpress_gstin']) ? $_POST['a2z_dhlexpress_gstin'] : '');
					$general_settings['a2z_dhlexpress_con_rate'] = sanitize_text_field(isset($_POST['a2z_dhlexpress_con_rate']) ? $_POST['a2z_dhlexpress_con_rate'] : '');
					$general_settings['a2z_dhlexpress_def_dom'] = sanitize_text_field(isset($_POST['a2z_dhlexpress_def_dom']) ? $_POST['a2z_dhlexpress_def_dom'] : '');
					$general_settings['a2z_dhlexpress_def_inter'] = sanitize_text_field(isset($_POST['a2z_dhlexpress_def_inter']) ? $_POST['a2z_dhlexpress_def_inter'] : '');
					$general_settings['a2z_dhlexpress_sig_img_url'] = sanitize_text_field(isset($_POST['a2z_dhlexpress_sig_img_url']) ? $_POST['a2z_dhlexpress_sig_img_url'] : '');
					update_post_meta($user_id,'a2z_dhl_vendor_settings',$general_settings);
				}

			}

			public function hit_define_dhl_credentails( $user ){
				global $dhl_core;
				$main_settings = get_option('a2z_dhl_main_settings');
				$main_settings = empty($main_settings) ? array() : $main_settings;
				$allow = false;
				
				if(!isset($main_settings['a2z_dhlexpress_v_roles'])){
					return;
				}else{
					foreach ($user->roles as $value) {
						if(in_array($value, $main_settings['a2z_dhlexpress_v_roles'])){
							$allow = true;
						}
					}
				}
				
				if(!$allow){
					return;
				}

				$general_settings = get_post_meta($user->ID,'a2z_dhl_vendor_settings',true);
				$general_settings = empty($general_settings) ? array() : $general_settings;
				$countires =  array(
									'AF' => 'Afghanistan',
									'AL' => 'Albania',
									'DZ' => 'Algeria',
									'AS' => 'American Samoa',
									'AD' => 'Andorra',
									'AO' => 'Angola',
									'AI' => 'Anguilla',
									'AG' => 'Antigua and Barbuda',
									'AR' => 'Argentina',
									'AM' => 'Armenia',
									'AW' => 'Aruba',
									'AU' => 'Australia',
									'AT' => 'Austria',
									'AZ' => 'Azerbaijan',
									'BS' => 'Bahamas',
									'BH' => 'Bahrain',
									'BD' => 'Bangladesh',
									'BB' => 'Barbados',
									'BY' => 'Belarus',
									'BE' => 'Belgium',
									'BZ' => 'Belize',
									'BJ' => 'Benin',
									'BM' => 'Bermuda',
									'BT' => 'Bhutan',
									'BO' => 'Bolivia',
									'BA' => 'Bosnia and Herzegovina',
									'BW' => 'Botswana',
									'BR' => 'Brazil',
									'VG' => 'British Virgin Islands',
									'BN' => 'Brunei',
									'BG' => 'Bulgaria',
									'BF' => 'Burkina Faso',
									'BI' => 'Burundi',
									'KH' => 'Cambodia',
									'CM' => 'Cameroon',
									'CA' => 'Canada',
									'CV' => 'Cape Verde',
									'KY' => 'Cayman Islands',
									'CF' => 'Central African Republic',
									'TD' => 'Chad',
									'CL' => 'Chile',
									'CN' => 'China',
									'CO' => 'Colombia',
									'KM' => 'Comoros',
									'CK' => 'Cook Islands',
									'CR' => 'Costa Rica',
									'HR' => 'Croatia',
									'CU' => 'Cuba',
									'CY' => 'Cyprus',
									'CZ' => 'Czech Republic',
									'DK' => 'Denmark',
									'DJ' => 'Djibouti',
									'DM' => 'Dominica',
									'DO' => 'Dominican Republic',
									'TL' => 'East Timor',
									'EC' => 'Ecuador',
									'EG' => 'Egypt',
									'SV' => 'El Salvador',
									'GQ' => 'Equatorial Guinea',
									'ER' => 'Eritrea',
									'EE' => 'Estonia',
									'ET' => 'Ethiopia',
									'FK' => 'Falkland Islands',
									'FO' => 'Faroe Islands',
									'FJ' => 'Fiji',
									'FI' => 'Finland',
									'FR' => 'France',
									'GF' => 'French Guiana',
									'PF' => 'French Polynesia',
									'GA' => 'Gabon',
									'GM' => 'Gambia',
									'GE' => 'Georgia',
									'DE' => 'Germany',
									'GH' => 'Ghana',
									'GI' => 'Gibraltar',
									'GR' => 'Greece',
									'GL' => 'Greenland',
									'GD' => 'Grenada',
									'GP' => 'Guadeloupe',
									'GU' => 'Guam',
									'GT' => 'Guatemala',
									'GG' => 'Guernsey',
									'GN' => 'Guinea',
									'GW' => 'Guinea-Bissau',
									'GY' => 'Guyana',
									'HT' => 'Haiti',
									'HN' => 'Honduras',
									'HK' => 'Hong Kong',
									'HU' => 'Hungary',
									'IS' => 'Iceland',
									'IN' => 'India',
									'ID' => 'Indonesia',
									'IR' => 'Iran',
									'IQ' => 'Iraq',
									'IE' => 'Ireland',
									'IL' => 'Israel',
									'IT' => 'Italy',
									'CI' => 'Ivory Coast',
									'JM' => 'Jamaica',
									'JP' => 'Japan',
									'JE' => 'Jersey',
									'JO' => 'Jordan',
									'KZ' => 'Kazakhstan',
									'KE' => 'Kenya',
									'KI' => 'Kiribati',
									'KW' => 'Kuwait',
									'KG' => 'Kyrgyzstan',
									'LA' => 'Laos',
									'LV' => 'Latvia',
									'LB' => 'Lebanon',
									'LS' => 'Lesotho',
									'LR' => 'Liberia',
									'LY' => 'Libya',
									'LI' => 'Liechtenstein',
									'LT' => 'Lithuania',
									'LU' => 'Luxembourg',
									'MO' => 'Macao',
									'MK' => 'Macedonia',
									'MG' => 'Madagascar',
									'MW' => 'Malawi',
									'MY' => 'Malaysia',
									'MV' => 'Maldives',
									'ML' => 'Mali',
									'MT' => 'Malta',
									'MH' => 'Marshall Islands',
									'MQ' => 'Martinique',
									'MR' => 'Mauritania',
									'MU' => 'Mauritius',
									'YT' => 'Mayotte',
									'MX' => 'Mexico',
									'FM' => 'Micronesia',
									'MD' => 'Moldova',
									'MC' => 'Monaco',
									'MN' => 'Mongolia',
									'ME' => 'Montenegro',
									'MS' => 'Montserrat',
									'MA' => 'Morocco',
									'MZ' => 'Mozambique',
									'MM' => 'Myanmar',
									'NA' => 'Namibia',
									'NR' => 'Nauru',
									'NP' => 'Nepal',
									'NL' => 'Netherlands',
									'NC' => 'New Caledonia',
									'NZ' => 'New Zealand',
									'NI' => 'Nicaragua',
									'NE' => 'Niger',
									'NG' => 'Nigeria',
									'NU' => 'Niue',
									'KP' => 'North Korea',
									'MP' => 'Northern Mariana Islands',
									'NO' => 'Norway',
									'OM' => 'Oman',
									'PK' => 'Pakistan',
									'PW' => 'Palau',
									'PA' => 'Panama',
									'PG' => 'Papua New Guinea',
									'PY' => 'Paraguay',
									'PE' => 'Peru',
									'PH' => 'Philippines',
									'PL' => 'Poland',
									'PT' => 'Portugal',
									'PR' => 'Puerto Rico',
									'QA' => 'Qatar',
									'CG' => 'Republic of the Congo',
									'RE' => 'Reunion',
									'RO' => 'Romania',
									'RU' => 'Russia',
									'RW' => 'Rwanda',
									'SH' => 'Saint Helena',
									'KN' => 'Saint Kitts and Nevis',
									'LC' => 'Saint Lucia',
									'VC' => 'Saint Vincent and the Grenadines',
									'WS' => 'Samoa',
									'SM' => 'San Marino',
									'ST' => 'Sao Tome and Principe',
									'SA' => 'Saudi Arabia',
									'SN' => 'Senegal',
									'RS' => 'Serbia',
									'SC' => 'Seychelles',
									'SL' => 'Sierra Leone',
									'SG' => 'Singapore',
									'SK' => 'Slovakia',
									'SI' => 'Slovenia',
									'SB' => 'Solomon Islands',
									'SO' => 'Somalia',
									'ZA' => 'South Africa',
									'KR' => 'South Korea',
									'SS' => 'South Sudan',
									'ES' => 'Spain',
									'LK' => 'Sri Lanka',
									'SD' => 'Sudan',
									'SR' => 'Suriname',
									'SZ' => 'Swaziland',
									'SE' => 'Sweden',
									'CH' => 'Switzerland',
									'SY' => 'Syria',
									'TW' => 'Taiwan',
									'TJ' => 'Tajikistan',
									'TZ' => 'Tanzania',
									'TH' => 'Thailand',
									'TG' => 'Togo',
									'TO' => 'Tonga',
									'TT' => 'Trinidad and Tobago',
									'TN' => 'Tunisia',
									'TR' => 'Turkey',
									'TC' => 'Turks and Caicos Islands',
									'TV' => 'Tuvalu',
									'VI' => 'U.S. Virgin Islands',
									'UG' => 'Uganda',
									'UA' => 'Ukraine',
									'AE' => 'United Arab Emirates',
									'GB' => 'United Kingdom',
									'US' => 'United States',
									'UY' => 'Uruguay',
									'UZ' => 'Uzbekistan',
									'VU' => 'Vanuatu',
									'VE' => 'Venezuela',
									'VN' => 'Vietnam',
									'YE' => 'Yemen',
									'ZM' => 'Zambia',
									'ZW' => 'Zimbabwe',
								);
				 $_dhl_carriers = array(
					//"Public carrier name" => "technical name",
					'1'                    => 'DOMESTIC EXPRESS 12:00',
					'2'                    => 'B2C',
					'3'                    => 'B2C',
					'4'                    => 'JETLINE',
					'5'                    => 'SPRINTLINE',
					'7'                    => 'EXPRESS EASY',
					'8'                    => 'EXPRESS EASY',
					'9'                    => 'EUROPACK',
					'B'                    => 'BREAKBULK EXPRESS',
					'C'                    => 'MEDICAL EXPRESS',
					'D'                    => 'EXPRESS WORLDWIDE',
					'E'                    => 'EXPRESS 9:00',
					'F'                    => 'FREIGHT WORLDWIDE',
					'G'                    => 'DOMESTIC ECONOMY SELECT',
					'H'                    => 'ECONOMY SELECT',
					'I'                    => 'DOMESTIC EXPRESS 9:00',
					'J'                    => 'JUMBO BOX',
					'K'                    => 'EXPRESS 9:00',
					'L'                    => 'EXPRESS 10:30',
					'M'                    => 'EXPRESS 10:30',
					'N'                    => 'DOMESTIC EXPRESS',
					'O'                    => 'DOMESTIC EXPRESS 10:30',
					'P'                    => 'EXPRESS WORLDWIDE',
					'Q'                    => 'MEDICAL EXPRESS',
					'R'                    => 'GLOBALMAIL BUSINESS',
					'S'                    => 'SAME DAY',
					'T'                    => 'EXPRESS 12:00',
					'U'                    => 'EXPRESS WORLDWIDE',
					'V'                    => 'EUROPACK',
					'W'                    => 'ECONOMY SELECT',
					'X'                    => 'EXPRESS ENVELOPE',
					'Y'                    => 'EXPRESS 12:00'	
				);			

				 echo '<hr><h3 class="heading">DHL Express - <a href="https://myshipi.com/" target="_blank">Shipi</a></h3>';
				    ?>
				    
				    <table class="form-table">
				    	
				    	<tr>
							<td style=" width: 50%; padding: 5px; ">
								<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Choose whether your credentials are from XML API or MYDHL API','a2z_dhlexpress') ?>"></span>	<?php _e('API Type','a2z_dhlexpress') ?></h4>
							</td>
							<td>
								<?php
									if ((!isset($general_settings['a2z_dhlexpress_site_id']) && !isset($general_settings['a2z_dhlexpress_api_type'])) || (isset($general_settings['a2z_dhlexpress_api_type']) && $general_settings['a2z_dhlexpress_api_type'] == "REST")) {
									?>
										<input type="radio" name="a2z_dhlexpress_api_type" value="REST" checked> I have API key (MY DHL API) &nbsp; &nbsp;
										<input type="radio" name="a2z_dhlexpress_api_type" value="SOAP"> I don't have API key (XML API)
									<?php
									} else {
									?>
										<input type="radio" name="a2z_dhlexpress_api_type" value="REST"> I have API key (MY DHL API) &nbsp; &nbsp;
										<input type="radio" name="a2z_dhlexpress_api_type" value="SOAP" checked> I don't have API key (XML API)
									<?php
									}
								?>
							</td>
						</tr>
						<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('DHL Integration Team will give this details to you.','a2z_dhlexpress') ?>"></span>	<?php _e('DHL XML API Site ID','a2z_dhlexpress') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','a2z_dhlexpress') ?> </p>
						</td>
						<td>
							<input type="text" name="a2z_dhlexpress_site_id" value="<?php echo (isset($general_settings['a2z_dhlexpress_site_id'])) ? $general_settings['a2z_dhlexpress_site_id'] : ''; ?>">
						</td>

					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('DHL Integration Team will give this details to you.','a2z_dhlexpress') ?>"></span>	<?php _e('DHL XML API Password','a2z_dhlexpress') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','a2z_dhlexpress') ?> </p>
						</td>
						<td>
							<input type="text" name="a2z_dhlexpress_site_pwd" value="<?php echo (isset($general_settings['a2z_dhlexpress_site_pwd'])) ? $general_settings['a2z_dhlexpress_site_pwd'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('DHL Integration Team will give this details to you.','a2z_dhlexpress') ?>"></span>	<?php _e('DHL Account Number','a2z_dhlexpress') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','a2z_dhlexpress') ?> </p>
						</td>
						<td>
							
							<input type="text" name="a2z_dhlexpress_acc_no" value="<?php echo (isset($general_settings['a2z_dhlexpress_acc_no'])) ? $general_settings['a2z_dhlexpress_acc_no'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('This is for proceed with return labels.','a2z_dhlexpress') ?>"></span>	<?php _e('DHL Import Account Number','a2z_dhlexpress') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','a2z_dhlexpress') ?> </p>
						</td>
						<td>
							
							<input type="text" name="a2z_dhlexpress_import_no" value="<?php echo (isset($general_settings['a2z_dhlexpress_import_no'])) ? $general_settings['a2z_dhlexpress_import_no'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Shipping Person Name','a2z_dhlexpress') ?>"></span>	<?php _e('Shipper Name','a2z_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="a2z_dhlexpress_shipper_name" value="<?php echo (isset($general_settings['a2z_dhlexpress_shipper_name'])) ? $general_settings['a2z_dhlexpress_shipper_name'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Shipper Company Name.','a2z_dhlexpress') ?>"></span>	<?php _e('Company Name','a2z_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="a2z_dhlexpress_company" value="<?php echo (isset($general_settings['a2z_dhlexpress_company'])) ? $general_settings['a2z_dhlexpress_company'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Shipper Mobile / Contact Number.','a2z_dhlexpress') ?>"></span>	<?php _e('Contact Number','a2z_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="a2z_dhlexpress_mob_num" value="<?php echo (isset($general_settings['a2z_dhlexpress_mob_num'])) ? $general_settings['a2z_dhlexpress_mob_num'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Email Address of the Shipper.','a2z_dhlexpress') ?>"></span>	<?php _e('Email Address','a2z_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="a2z_dhlexpress_email" value="<?php echo (isset($general_settings['a2z_dhlexpress_email'])) ? $general_settings['a2z_dhlexpress_email'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Address Line 1 of the Shipper from Address.','a2z_dhlexpress') ?>"></span>	<?php _e('Address Line 1','a2z_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="a2z_dhlexpress_address1" value="<?php echo (isset($general_settings['a2z_dhlexpress_address1'])) ? $general_settings['a2z_dhlexpress_address1'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Address Line 2 of the Shipper from Address.','a2z_dhlexpress') ?>"></span>	<?php _e('Address Line 2','a2z_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="a2z_dhlexpress_address2" value="<?php echo (isset($general_settings['a2z_dhlexpress_address2'])) ? $general_settings['a2z_dhlexpress_address2'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%;padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('City of the Shipper from address.','a2z_dhlexpress') ?>"></span>	<?php _e('City','a2z_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="a2z_dhlexpress_city" value="<?php echo (isset($general_settings['a2z_dhlexpress_city'])) ? $general_settings['a2z_dhlexpress_city'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('State of the Shipper from address.','a2z_dhlexpress') ?>"></span>	<?php _e('State (Two Digit String)','a2z_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="a2z_dhlexpress_state" value="<?php echo (isset($general_settings['a2z_dhlexpress_state'])) ? $general_settings['a2z_dhlexpress_state'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Postal/Zip Code.','a2z_dhlexpress') ?>"></span>	<?php _e('Postal/Zip Code','a2z_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="a2z_dhlexpress_zip" value="<?php echo (isset($general_settings['a2z_dhlexpress_zip'])) ? $general_settings['a2z_dhlexpress_zip'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Country of the Shipper from Address.','a2z_dhlexpress') ?>"></span>	<?php _e('Country','a2z_dhlexpress') ?></h4>
						</td>
						<td>
							<select name="a2z_dhlexpress_country" class="wc-enhanced-select" style="width:210px;">
								<?php foreach($countires as $key => $value)
								{

									if(isset($general_settings['a2z_dhlexpress_country']) && ($general_settings['a2z_dhlexpress_country'] == $key))
									{
										echo "<option value=".$key." selected='true'>".$value." [". $dhl_core[$key]['currency'] ."]</option>";
									}
									else
									{
										echo "<option value=".$key.">".$value." [". $dhl_core[$key]['currency'] ."]</option>";
									}
								} ?>
							</select>
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('GSTIN/VAT No.','a2z_dhlexpress') ?>"></span>	<?php _e('GSTIN/VAT No','a2z_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="a2z_dhlexpress_gstin" value="<?php echo (isset($general_settings['a2z_dhlexpress_gstin'])) ? $general_settings['a2z_dhlexpress_gstin'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Conversion Rate from Site Currency to DHL Currency.','a2z_dhlexpress') ?>"></span>	<?php _e('Conversion Rate from Site Currency to DHL Currency ( Ignore if auto conversion is Enabled )','a2z_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="a2z_dhlexpress_con_rate" value="<?php echo (isset($general_settings['a2z_dhlexpress_con_rate'])) ? $general_settings['a2z_dhlexpress_con_rate'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Default Domestic Express Shipping.','a2z_dhlexpress') ?>"></span>	<?php _e('Default Domestic Service','a2z_dhlexpress') ?></h4>
							<p><?php _e('This will be used while shipping label generation.','a2z_dhlexpress') ?></p>
						</td>
						<td>
							<select name="a2z_dhlexpress_def_dom" class="wc-enhanced-select" style="width:210px;">
								<?php foreach($_dhl_carriers as $key => $value)
								{
									if(isset($general_settings['a2z_dhlexpress_def_dom']) && ($general_settings['a2z_dhlexpress_def_dom'] == $key))
									{
										echo "<option value=".$key." selected='true'>[".$key."] ".$value."</option>";
									}
									else
									{
										echo "<option value=".$key.">[".$key."] ".$value."</option>";
									}
								} ?>
							</select>
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Default International Shipping.','a2z_dhlexpress') ?>"></span>	<?php _e('Default International Service','a2z_dhlexpress') ?></h4>
							<p><?php _e('This will be used while shipping label generation.','a2z_dhlexpress') ?></p>
						</td>
						<td>
							<select name="a2z_dhlexpress_def_inter" class="wc-enhanced-select" style="width:210px;">
								<?php foreach($_dhl_carriers as $key => $value)
								{
									if(isset($general_settings['a2z_dhlexpress_def_inter']) && ($general_settings['a2z_dhlexpress_def_inter'] == $key))
									{
										echo "<option value=".$key." selected='true'>[".$key."] ".$value."</option>";
									}
									else
									{
										echo "<option value=".$key.">[".$key."] ".$value."</option>";
									}
								} ?>
							</select>
						</td>
					</tr>
					<tr>
						<td style="width: 50%; padding: 5px;">
							<h4><span class="woocommerce-help-tip" data-tip="<?php _e('Input the url of signature image and it should below 1 MB and in any one of these format (PNG, GIF, JPEG, JPG).','a2z_dhlexpress') ?>"></span><?php _e('Signature Image url','a2z_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="a2z_dhlexpress_sig_img_url" value="<?php echo (isset($general_settings['a2z_dhlexpress_sig_img_url'])) ? $general_settings['a2z_dhlexpress_sig_img_url'] : ''; ?>">
						</td>
					</tr>
				    </table>
				    <hr>
				    <?php
			}
			public function hit_save_product_meta( $post_id ){
				if(isset( $_POST['dhl_express_shipment'])){
					$dhl_express_shipment = sanitize_text_field($_POST['dhl_express_shipment']);
					if( !empty( $dhl_express_shipment ) ){
						if ($this->hpos_enabled && $this->new_prod_editor_enabled) {
	 	                    $hpos_prod_data = wc_get_product($post_id);
	 	                    $hpos_prod_data->update_meta_data("dhl_express_address", (string) esc_html( $dhl_express_shipment ));
	 	                } else {
	 	                	update_post_meta( $post_id, 'dhl_express_address', (string) esc_html( $dhl_express_shipment ) );
	 	                }
					}
				}
							
			}
			public function hit_choose_vendor_address(){
				global $woocommerce, $post;
				$hit_multi_vendor = get_option('hit_multi_vendor');
				$hit_multi_vendor = empty($hit_multi_vendor) ? array() : $hit_multi_vendor;
				if ($this->hpos_enabled) {
				    $hpos_prod_data = wc_get_product($post->ID);
				    $selected_addr = $hpos_prod_data->get_meta("dhl_express_address");
				} else {
					$selected_addr = get_post_meta( $post->ID, 'dhl_express_address', true);
				}

				$main_settings = get_option('a2z_dhl_main_settings');
				$main_settings = empty($main_settings) ? array() : $main_settings;
				if(!isset($main_settings['a2z_dhlexpress_v_roles']) || empty($main_settings['a2z_dhlexpress_v_roles'])){
					return;
				}
				$v_users = get_users( [ 'role__in' => $main_settings['a2z_dhlexpress_v_roles'] ] );
				
				?>
				<div class="options_group">
				<p class="form-field dhl_express_shipment">
					<label for="dhl_express_shipment"><?php _e( 'DHL Express Account', 'woocommerce' ); ?></label>
					<select id="dhl_express_shipment" style="width:240px;" name="dhl_express_shipment" class="wc-enhanced-select" data-placeholder="<?php _e( 'Search for a product&hellip;', 'woocommerce' ); ?>">
						<option value="default" >Default Account</option>
						<?php
							if ( $v_users ) {
								foreach ( $v_users as $value ) {
									echo '<option value="' .  $value->data->ID  . '" '.($selected_addr == $value->data->ID ? 'selected="true"' : '').'>' . $value->data->display_name . '</option>';
								}
							}
						?>
					</select>
					</p>
				</div>
				<?php
			}

			public function a2z_dhlexpress_init()
			{
				include_once(dirname(__FILE__) ."/controllors/a2z_dhlexpress_init.php");
			}
			public function hit_order_status_update(){
				global $woocommerce;
				if(isset($_GET['shipi_key'])){
					$hitshipo_key = sanitize_text_field($_GET['shipi_key']);
					if($hitshipo_key == 'fetch'){
						echo json_encode(array(get_transient('hitshipo_dhl_express_nonce_temp')));
						die();
					}
				}

				if(isset($_GET['hitshipo_integration_key']) && isset($_GET['hitshipo_action'])){
					$integration_key = sanitize_text_field($_GET['hitshipo_integration_key']);
					$hitshipo_action = sanitize_text_field($_GET['hitshipo_action']);
					$general_settings = get_option('a2z_dhl_main_settings');
					$general_settings = empty($general_settings) ? array() : $general_settings;
					if(isset($general_settings['a2z_dhlexpress_integration_key']) && $integration_key == $general_settings['a2z_dhlexpress_integration_key']){
						if($hitshipo_action == 'stop_working'){
							update_option('a2z_dhl_express_working_status', 'stop_working');
						}else if ($hitshipo_action = 'start_working'){
							update_option('a2z_dhl_express_working_status', 'start_working');
						}
					}
					
				}

				if(isset($_GET['h1t_updat3_0rd3r']) && isset($_GET['key']) && isset($_GET['action'])){
					$order_id = sanitize_text_field($_GET['h1t_updat3_0rd3r']);
					$key = sanitize_text_field($_GET['key']);
					$action = sanitize_text_field($_GET['action']);
					$order_ids = explode(",",$order_id);
					$general_settings = get_option('a2z_dhl_main_settings',array());
					
					if(isset($general_settings['a2z_dhlexpress_integration_key']) && $general_settings['a2z_dhlexpress_integration_key'] == $key){
						if($action == 'processing'){
							foreach ($order_ids as $order_id) {
								$order = wc_get_order( $order_id );
								$order->update_status( 'processing' );
							}
						}else if($action == 'completed'){
							foreach ($order_ids as $order_id) {
								  $order = wc_get_order( $order_id );
								  $order->update_status( 'completed' );
								  	
							}
						}
					}
					die();
				}

				if(isset($_GET['h1t_updat3_sh1pp1ng']) && isset($_GET['key']) && isset($_GET['user_id']) && isset($_GET['carrier']) && isset($_GET['track']) && isset($_GET['pic_status'])){
					$order_id = sanitize_text_field($_GET['h1t_updat3_sh1pp1ng']);
					$key = sanitize_text_field($_GET['key']);
					$general_settings = get_option('a2z_dhl_main_settings',array());
					$user_id = sanitize_text_field($_GET['user_id']);
					$carrier = sanitize_text_field($_GET['carrier']);
					$track = sanitize_text_field($_GET['track']);
					$pic_status = sanitize_text_field($_GET['pic_status']);
					$return_status = isset($_GET['return']) ? sanitize_text_field($_GET['return']) : '';
					$output['status'] = 'success';
					$output['tracking_num'] = $track;
					$output['label'] = "https://app.myshipi.com/api/shipping_labels/".$user_id."/".$carrier."/order_".$order_id."_track_".$track."_label.pdf";
					$output['invoice'] = "https://app.myshipi.com/api/shipping_labels/".$user_id."/".$carrier."/order_".$order_id."_track_".$track."_invoice.pdf";
					$result_arr = array();
					if(isset($general_settings['a2z_dhlexpress_integration_key']) && $general_settings['a2z_dhlexpress_integration_key'] == $key){
						
						$output = apply_filters('shipi_dhlexpress_shipment_data', $output, $order_id);
						
						if(isset($_GET['label'])){
							$output['user_id'] = sanitize_text_field($_GET['label']);
							if(isset($general_settings['a2z_dhlexpress_v_enable']) && $general_settings['a2z_dhlexpress_v_enable'] == 'yes'){
								$result_arr = !empty(get_option('hit_dhl_values_'.$order_id, array())) ? json_decode(get_option('hit_dhl_values_'.$order_id, array())) : [];
							}
							
							$result_arr[] = $output;

						}else{
							$result_arr[] = $output;							
						}
						
						if(!empty($return_status)){
							update_option('hit_dhl_return_values_'.$order_id, json_encode($result_arr));
							$order = wc_get_order($order_id);
							$order->update_meta_data( apply_filters('a2z_rtracking_id_meta_name', 'a2z_rtracking_num'), $track );
    						$order->save();
							// update_post_meta($order_id, apply_filters('a2z_rtracking_id_meta_name', 'a2z_rtracking_num'), $track);
							die();
						}


						update_option('hit_dhl_values_'.$order_id, json_encode(apply_filters("shipi_dhl_express_save_output", $result_arr, $order_id)));

						if (isset($pic_status) && $pic_status == "success") {

							$pic_res['confirm_no'] = sanitize_text_field($_GET['confirm_no']);
							$pic_res['ready_time'] = sanitize_text_field($_GET['ready_time']);
							$pic_res['pickup_date'] = sanitize_text_field($_GET['pickup_date']);
							$pic_res['status'] = "success";

							update_option('hit_dhl_pickup_values_'.$order_id, json_encode($pic_res));

						}elseif (isset($pic_status) && $pic_status == "failed"){
							$pic_res['status'] = "failed";
							update_option('hit_dhl_pickup_values_'.$order_id, json_encode($pic_res));

						}
					}

					die();
				}
			}
			
			
			public function a2z_dhlexpress_plugin_action_links($links)
			{
				$setting_value = version_compare(WC()->version, '2.1', '>=') ? "wc-settings" : "woocommerce_settings";
				$plugin_links = array(
					'<a href="' . admin_url( 'options-general.php?page=hit-dhl-express-configuration' ) . '" style="color:green;">' . __( 'Configure', 'a2z_dhlexpress' ) . '</a>',
					'<a href="https://app.myshipi.com/support" target="_blank" >' . __('Support', 'a2z_dhlexpress') . '</a>'
					);
				return array_merge( $plugin_links, $links );
			}
			public function create_dhl_shipping_meta_box() {
				$meta_scrn = $this->hpos_enabled ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
	       		add_meta_box( 'hit_create_dhl_shipping', __('DHL Shipping Label','a2z_dhlexpress'), array($this, 'create_dhl_shipping_label_genetation'), $meta_scrn, 'side', 'core' );
	       		add_meta_box( 'hit_create_dhl_return_shipping', __('DHL Return Label','a2z_dhlexpress'), array($this, 'create_dhl_return_label_genetation'), $meta_scrn, 'side', 'core' );
				wp_enqueue_script('hits_dhl_edit_order_js', plugin_dir_url(__FILE__).'/js/edit_order.js');
		    }
		    public function create_dhl_shipping_label_genetation($post){
		    	// print_r('expression');
		    	// die();		    	
		        if(!$this->hpos_enabled && $post->post_type !='shop_order' ){
		    		return;
		    	}
		    	$order = (!$this->hpos_enabled) ? wc_get_order( $post->ID ) : $post;
		    	$order_id = $order->get_id();
		    	$order_data = $order->get_data();
				$service_code = '';
				$multi_ven = '';
				foreach( $order->get_shipping_methods() as $item_id => $item ){
					$service_code = $item->get_meta('a2z_dhl_service');
					$multi_ven = $item->get_meta('a2z_multi_ven');
				}
				
				$to_con = (isset($order_data['shipping']['country']) && !empty($order_data['shipping']['country'])) ? $order_data['shipping']['country'] : "";
				if (empty($to_con)) {
					$to_con = (isset($order_data['billing']['country']) && !empty($order_data['billing']['country'])) ? $order_data['billing']['country'] : "";
				}
		        $_dhl_carriers = array(
								//"Public carrier name" => "technical name",
								'1'                    => 'DOMESTIC EXPRESS 12:00',
								'2'                    => 'B2C',
								'3'                    => 'B2C',
								'4'                    => 'JETLINE',
								'5'                    => 'SPRINTLINE',
								'7'                    => 'EXPRESS EASY',
								'8'                    => 'EXPRESS EASY',
								'9'                    => 'EUROPACK',
								'B'                    => 'BREAKBULK EXPRESS',
								'C'                    => 'MEDICAL EXPRESS',
								'D'                    => 'EXPRESS WORLDWIDE',
								'E'                    => 'EXPRESS 9:00',
								'F'                    => 'FREIGHT WORLDWIDE',
								'G'                    => 'DOMESTIC ECONOMY SELECT',
								'H'                    => 'ECONOMY SELECT',
								'I'                    => 'DOMESTIC EXPRESS 9:00',
								'J'                    => 'JUMBO BOX',
								'K'                    => 'EXPRESS 9:00',
								'L'                    => 'EXPRESS 10:30',
								'M'                    => 'EXPRESS 10:30',
								'N'                    => 'DOMESTIC EXPRESS',
								'O'                    => 'DOMESTIC EXPRESS 10:30',
								'P'                    => 'EXPRESS WORLDWIDE',
								'Q'                    => 'MEDICAL EXPRESS',
								'R'                    => 'GLOBALMAIL BUSINESS',
								'S'                    => 'SAME DAY',
								'T'                    => 'EXPRESS 12:00',
								'U'                    => 'EXPRESS WORLDWIDE',
								'V'                    => 'EUROPACK',
								'W'                    => 'ECONOMY SELECT',
								'X'                    => 'EXPRESS ENVELOPE',
								'Y'                    => 'EXPRESS 12:00'	
							);

		        $general_settings = get_option('a2z_dhl_main_settings',array());
				if(isset($general_settings["a2z_dhlexpress_country"]) && $general_settings["a2z_dhlexpress_country"] == "DE"){
					_e( '<script type="text/javascript">
					jQuery(document).ready(function() {
						var service = jQuery("#hit_dhl_express_service_code_default").val(); 
						var servic_value = ["E","Y","P","H"];
						var cond = "";
							jQuery.each(servic_value, function(key, value){
							if(service == value){
								cond = value;
								return;
							}
							});
							if (service != cond) {
								jQuery("#hit_dhl_export_reason").attr("hidden", "hidden");
								jQuery("#hit_dhl_duty_type").attr("hidden", "hidden");
								jQuery("#duty_payment").attr("hidden", "hidden");
								jQuery("#reason_for_export").attr("hidden", "hidden");
							}else{
								jQuery("#hit_dhl_export_reason").removeAttr("hidden");
								jQuery("#hit_dhl_duty_type").removeAttr("hidden");
								jQuery("#duty_payment").removeAttr("hidden");
								jQuery("#reason_for_export").removeAttr("hidden");
							}
						
						jQuery("#hit_dhl_express_service_code_default").change(function() {
							var service = document.getElementById("hit_dhl_express_service_code_default").value;
							var servic_value = ["E","Y","P","H"];
							var cond = "";
							jQuery.each(servic_value, function(key, value){
							if(service == value){
								cond = value;
								return ;
							}
							});
							if (service != cond) {
								jQuery("#hit_dhl_export_reason").attr("hidden", "hidden");
								jQuery("#hit_dhl_duty_type").attr("hidden", "hidden");
								jQuery("#duty_payment").attr("hidden", "hidden");
								jQuery("#reason_for_export").attr("hidden", "hidden");
							} else{
								jQuery("#hit_dhl_export_reason").removeAttr("hidden");
								jQuery("#hit_dhl_duty_type").removeAttr("hidden");
								jQuery("#duty_payment").removeAttr("hidden");
								jQuery("#reason_for_export").removeAttr("hidden");
							}
						});
					});
					</script>');
				}
		        
				$order_products = $this->get_products_on_order($general_settings, $order);
				$custom_settings = $this->get_vendors_on_order($general_settings, $order_products);
				// echo "<pre>";print_r($custom_settings);die();
		       	$json_data = get_option('hit_dhl_values_'.$order_id);
		       	$pickup_json_data = get_option('hit_dhl_pickup_values_'.$order_id);
				// echo "<pre>";print_r($pickup_json_data);die();
		       	$rate_json_data = get_option('hit_dhl_order_rates_'.$order_id);
		       	// echo $pickup_json_data;
		       	$notice = get_option('hit_dhl_status_'.$order_id, null);
		        if($notice && $notice != 'success'){
		        	echo "<p style='color:red'>".$notice."</p>";
		        	delete_option('hit_dhl_status_'.$order_id);
		        }
		        if(!empty($json_data)){
   					$array_data = json_decode( $json_data, true );
   					// echo '<pre>';print_r($array_data);die();
		       		if(isset($array_data[0])){
		       			foreach ($array_data as $key => $value) {
		       				if(isset($value['user_id'])){
		       					unset($custom_settings[$value['user_id']]);
		       				}
		       				if(isset($value['user_id']) && $value['user_id'] == 'default'){
		       					echo '<br/><b>Account: </b><small>Default</small><br/>';
		       				}else{
		       					$user = get_user_by( 'id', $value['user_id'] );
		       					echo '<br/><b>Account:</b> <small>'.$user->display_name.'</small><br/>';
		       				}
		       				if (isset($value['tracking_num'])) {
		       					echo '<b>Tracking No: </b><a href="https://track.myshipi.com?id='.$order_id.'&no='.$value["tracking_num"].'&track=1" target="_blank">#'.$value["tracking_num"].'</a><br/>';
		       				}
			       			echo '<a href="'.$value['label'].'" target="_blank" style="background:#FFCC00; color: #D40511;border-color: #FFCC00;box-shadow: 0px 1px 0px #FFCC00;text-shadow: 0px 1px 0px #D40511;margin-top:3px;" class="button button-primary"> Shipping Label</a> ';
			       			if (isset($value['invoice']) && apply_filters('hits_show_invoice', true, $to_con)) {
			       				echo ' <a href="'.$value['invoice'].'" target="_blank" class="button button-primary" style="margin-top:3px;"> Invoice </a><br/>';
			       			}
			       			if(isset($value['slip'])){
								echo ' <a href="'.$value['slip'].'" target="_blank" class="button button-primary" style="margin-top:3px;"> Packing Slip </a><br/>';
							}
		       			}	
		       		}else{
		       			$custom_settings = array();
		       			if (isset($array_data['tracking_num'])) {
		       				echo '<b>Tracking No: </b><a href="https://track.myshipi.com?id='.$order_id.'&no='.$array_data["tracking_num"].'&track=1" target="_blank">#'.$array_data["tracking_num"].'</a><br/>';
		       			}
		       			echo '<a href="'.$array_data['label'].'" target="_blank" style="background:#FFCC00; color: #D40511;border-color: #FFCC00;box-shadow: 0px 1px 0px #FFCC00;text-shadow: 0px 1px 0px #D40511;" class="button button-primary"> Shipping Label</a> ';
		       			if (isset($array_data['invoice']) && apply_filters('hits_show_invoice', true, $to_con)) {
			       			echo ' <a href="'.$array_data['invoice'].'" target="_blank" class="button button-primary"> Invoice </a>';
			       		}
							if(isset($array_data['slip'])){
								echo ' <a href="'.$array_data['slip'].'" target="_blank" class="button button-primary"> Packing Slip </a>';
							}
			       			
		       		}
   				}
   				$rate_data = [];
   				if (!empty($rate_json_data)) {
   					$rate_data = json_decode( $rate_json_data, true );
   					delete_option('hit_dhl_order_rates_'.$order_id);
   				}
   				$woo_curr = get_option('woocommerce_currency');
   				global $dhl_core;
	       		foreach ($custom_settings as $ukey => $value) {
	       			$general_settings['a2z_dhlexpress_currency'] = isset($dhl_core[(isset($value['a2z_dhlexpress_country']) ? $value['a2z_dhlexpress_country'] : 'A2Z')]) ? $dhl_core[$value['a2z_dhlexpress_country']]['currency'] : '';
	       			$dhl_packs = $this->hit_get_dhl_packages($value['products'], $general_settings, $general_settings['a2z_dhlexpress_currency']);
	       			// echo "<pre>";print_r($dhl_packs);die();
	       			if($ukey == 'default'){
	       				echo '<br/><b>Account: </b><small>Default</small>';
				        echo '<br/><br/><b>Select Service:</b>';
				        if (isset($rate_data[$ukey]) && !empty($rate_data[$ukey])) {
				        	foreach ($rate_data[$ukey] as $rate_code => $rate_cost) {
				        		if (isset($general_settings['a2z_dhlexpress_carrier'][$rate_code]) && $general_settings['a2z_dhlexpress_carrier'][$rate_code] == "yes") {
				        			echo '<br/><input type="radio" name="hit_dhl_express_service_code_default" value="'.$rate_code.'"><label>['.$rate_code.'] '.$_dhl_carriers[$rate_code] .' - '. wc_price($rate_cost).'</label>';
				        		}
				        	}
				        } else {
				        	echo '<br/><select id="hit_dhl_express_service_code_default" name="hit_dhl_express_service_code_default" class="wc-enhanced-select">';
				        	if(!empty($general_settings['a2z_dhlexpress_carrier'])){
					        	foreach ($general_settings['a2z_dhlexpress_carrier'] as $key => $crr_value) {
					        		echo "<option value='".$key."' ". ($service_code == $key ? 'selected' :'') ." >".$key .' - ' .$_dhl_carriers[$key]."</option>";
					        	}
					        }
				        }
						$duty = "S";
						if(isset($general_settings['a2z_dhlexpress_duty_payment'])){
				        	$duty = "R";
				        }
				        echo '</select>';
				        echo '<br/><b>Shipment Content:</b>';
		        
						echo '<br/><input type="text" style="width:250px;"  name="hit_dhl_shipment_content_default" placeholder="Shipment content" value="' . ((isset($general_settings['a2z_dhlexpress_ship_content'])) ? apply_filters("hitshipo_dhl_cus_ship_desc", $general_settings['a2z_dhlexpress_ship_content'], $order_id, 'default') : "") . '" >';
						
				        echo '<br/><b id="reason_for_export">Reason for Export: </b><select id="hit_dhl_export_reason" name="hit_dhl_export_reason_default" class="form-control" style="width:100%">
								<option value="P">SALE</option>
								<option value="G">GIFT</option>
								<option value="T">Temporary</option>
								<option value="R">Return For Repair</option>
								<option value="M">Used Exhibition Goods To Origin</option>
								<option value="I">Intercompany Use</option>
								<option value="C">Commercial Purpose</option>
								<option value="E">Personal Belongings or Personal Use</option>
								<option value="S">Sample</option>
								<option value="U">Return To Origin</option>
								<option value="W">Warranty Replacement</option>
								<option value="D">Diplomatic Goods</option>
								<option value="F">Defenece Material</option>
							</select><br/>';
						echo '<b id="duty_payment">Duty Payment Type: </b><select id="hit_dhl_duty_type" name="hit_dhl_duty_type_default" class="form-control" style="width:100%;">
							<option value="S" '. ($duty == "S" ? "selected='true'" : '') .'>SENDER</option>
							<option value="R" '. ($duty == "R" ? "selected='true'" : '') .'>RECIPIENT</option>
						</select>
						<br/>
						<b id="pickup_type">Planing Shipping/Pickup date is: </b><select id="hit_dhl_pickup_type" name="hit_dhl_pickup_type_default" class="form-control" style="width:100%;">
							<option value="S">Today</option>
							<option value="1">+1 Day</option>
							<option value="2">+2 Day</option>
							<option value="3">+3 Day</option>
							<option value="4">+4 Day</option>
							<option value="5">+5 Day</option>
							<option value="6">+6 Day</option>
							<option value="7">+7 Day</option>
							<option value="8">+8 Day</option>
							<option value="9">+9 Day</option>
						</select><br/>';
						
					
						echo apply_filters("hitdhl_custom_fields", '', 'default');
						echo "<br/>";
						_e('<input type="text" id="hits_dhl_prods_default" value="' . ((isset($value["products"]) && !empty($value["products"])) ? base64_encode(json_encode($value["products"])) : "") . '" hidden>');
						_e('<input type="text" id="hits_dhl_packs_default" value="' . ((isset($dhl_packs) && !empty($dhl_packs)) ? base64_encode(json_encode($dhl_packs)) : "") . '" hidden>');
						_e('<input type="checkbox" class="hits_dhl_add_cus_pack" name="hits_dhl_add_cus_pack_default" value="default"><b>Customize packages</b><br/>');
						_e('<div id="hits_dhl_cus_packs_default" style="overflow-y:scroll" hidden></div><br/>');
						_e('<p id="hits_dhl_cus_packs_default_status" hidden></p>');
				        echo '<input type="checkbox" name="hit_dhl_add_pickup_default" '. (($general_settings['a2z_dhlexpress_pickup_automation'] == "yes") ? "checked" : "") .'> <b>Create Pickup along with shipment.</b><br/><br/>';
				        echo '<button name="hit_dhl_get_rates" value="default" style="background:#FFCC00; color: #D40511;border-color: #FFCC00;box-shadow: 0px 1px 0px #FFCC00;text-shadow: 0px 1px 0px #D40511; margin-right: 2px;" class="button button-primary"  type="submit">Get Rates</button><button name="hit_dhl_create_label" value="default" style="background:#FFCC00; color: #D40511;border-color: #FFCC00;box-shadow: 0px 1px 0px #FFCC00;text-shadow: 0px 1px 0px #D40511;" class="button button-primary" type="submit">Create Shipment</button>';
				        
	       			}else{

	       				$user = get_user_by( 'id', $ukey );
		       			echo '<br/><b>Account:</b> <small>'.$user->display_name.'</small>';
		       			echo '<br/><br/><b>Select Service:</b>';
				        if (isset($rate_data[$ukey]) && !empty($rate_data[$ukey])) {
				        	foreach ($rate_data[$ukey] as $rate_code => $rate_cost) {
				        		if (isset($general_settings['a2z_dhlexpress_carrier'][$rate_code]) && $general_settings['a2z_dhlexpress_carrier'][$rate_code] == "yes") {
				        			echo '<br/><input type="radio" name="hit_dhl_express_service_code_'.$ukey.'" value="'.$rate_code.'"><label>['.$rate_code.'] '.$_dhl_carriers[$rate_code] .' - '. $rate_cost.'</label>';
				        		}
				        	}
				        } else {
				        	echo '<br/><select name="hit_dhl_express_service_code_'.$ukey.'">';
					        if(!empty($general_settings['a2z_dhlexpress_carrier'])){
					        	foreach ($general_settings['a2z_dhlexpress_carrier'] as $key => $value) {
					        		echo "<option value='".$key."'>".$key .' - ' .$_dhl_carriers[$key]."</option>";
					        	}
					        }
				        }
						$duty = "S";
						if(isset($general_settings['a2z_dhlexpress_duty_payment'])){
				        	$duty = "R";
				        }

				        echo '</select>';
				        echo '<br/><b>Shipment Content:</b>';
		        
				        echo '<br/><input type="text" style="width:250px;"  name="hit_dhl_shipment_content_'.$ukey.'" placeholder="Shipment content" value="' . (($general_settings['a2z_dhlexpress_ship_content']) ? apply_filters("hitshipo_dhl_cus_ship_desc", $general_settings['a2z_dhlexpress_ship_content'], $order_id, $ukey) : "") . '" >';
						echo '<br/><b id="reason_for_export">Reason for Export: </b><select id="hit_dhl_export_reason" name="hit_dhl_export_reason_'.$ukey.'" class="form-control" style="width:100%;">
									<option value="P">SALE</option>
									<option value="G">GIFT</option>
									<option value="T">Temporary</option>
									<option value="R">Return For Repair</option>
									<option value="M">Used Exhibition Goods To Origin</option>
									<option value="I">Intercompany Use</option>
									<option value="C">Commercial Purpose</option>
									<option value="E">Personal Belongings or Personal Use</option>
									<option value="S">Sample</option>
									<option value="U">Return To Origin</option>
									<option value="W">Warranty Replacement</option>
									<option value="D">Diplomatic Goods</option>
									<option value="F">Defenece Material</option>
								</select><br/>';
						echo '<b id="duty_payment">Duty Payment Type: </b><select id="hit_dhl_duty_type" name="hit_dhl_duty_type_'.$ukey.'" class="form-control" style="width:100%;">
								<option value="S" '. ($duty == "S" ? "selected='true'" : '') .'>SENDER</option>
								<option value="R" '. ($duty == "R" ? "selected='true'" : '') .'>RECIPIENT</option>
							</select><br/>
								<b id="pickup_type">Planing Shipping/Pickup date is: </b><select id="hit_dhl_pickup_type" name="hit_dhl_pickup_type_'.$ukey.'" class="form-control" style="width:100%;">
								<option value="S">Today</option>
								<option value="1">+1 Day</option>
								<option value="2">+2 Day</option>
								<option value="3">+3 Day</option>
								<option value="4">+4 Day</option>
								<option value="5">+5 Day</option>
								<option value="6">+6 Day</option>
								<option value="7">+7 Day</option>
								<option value="8">+8 Day</option>
								<option value="9">+9 Day</option>
							</select><br/>';
						echo apply_filters("hitdhl_custom_fields", '', $ukey);
						echo "<br/>";
						_e('<input type="text" id="hits_dhl_prods_'.$ukey.'" value="' . ((isset($value["products"]) && !empty($value["products"])) ? base64_encode(json_encode($value["products"])) : "") . '" hidden>');
						_e('<input type="text" id="hits_dhl_packs_'.$ukey.'" value="' . ((isset($dhl_packs) && !empty($dhl_packs)) ? base64_encode(json_encode($dhl_packs)) : ""). '" hidden>');
						_e('<input type="checkbox" class="hits_dhl_add_cus_pack" name="hits_dhl_add_cus_pack_'.$ukey.'" value="'.$ukey.'"><b>Customize packages</b><br/>');
						_e('<div id="hits_dhl_cus_packs_'.$ukey.'" hidden></div><br/>');
						_e('<p id="hits_dhl_cus_packs_'.$ukey.'_status" hidden></p>');
				        echo '<input type="checkbox" name="hit_dhl_add_pickup_'.$ukey.'" '. (($general_settings['a2z_dhlexpress_pickup_automation'] == "yes") ? "checked" : "") .'> <b>Create Pickup along with shipment.</b><br/><br/>';
				        echo '<button name="hit_dhl_get_rates" value="'.$ukey.'" style="background:#FFCC00; color: #D40511;border-color: #FFCC00;box-shadow: 0px 1px 0px #FFCC00;text-shadow: 0px 1px 0px #D40511; margin-right: 2px;" class="button button-primary" type="submit">Get Rates</button><button name="hit_dhl_create_label" value="'.$ukey.'" style="background:#FFCC00; color: #D40511;border-color: #FFCC00;box-shadow: 0px 1px 0px #FFCC00;text-shadow: 0px 1px 0px #D40511;" class="button button-primary" type="submit">Create Shipment</button><br/>';
				        
	       			}
	       			
	       		}
		        
		        if (!empty($pickup_json_data) && empty($json_data)) {
		        	$pickup_array_data = json_decode( $pickup_json_data, true );
		        	if (isset($pickup_array_data['status']) && $pickup_array_data['status'] == "failed") {
		        		echo "<br/>Pickup creation failed<br/>";
		        	}else{
			        	echo '<h4>DHL pickup details:</h4>';
			        	echo '<b>Confirmation No : </b>'.$pickup_array_data['confirm_no'].'<br/>';
			        	echo '<b>Ready By Time : </b>'.$pickup_array_data['ready_time'].'<br/>';
			        	echo '<b>Pickup Date : </b>'.$pickup_array_data['pickup_date'].'<br/>';
		        	}
		        }else {
		        	echo '<h4>Pickup request can only be created with shipment request</h4>';
		        }

		       	if(!empty($json_data)){
		       		
		       		echo '<br/><button name="hit_dhl_reset" class="button button-secondary" style="margin-top:3px;" type="submit"> Reset Shipments</button>';
		       		if (!empty($pickup_json_data)) {
			        	$pickup_array_data = json_decode( $pickup_json_data, true );
			        	if (isset($pickup_array_data['status']) && $pickup_array_data['status'] == "failed") {
			        		echo "<br/><p style='color:red'>(Note: Manual pickup scheduling is currently not available. Recreating the shipment will reduce HITShippo balance.)</p>";
			        	}else{
				        	echo '<h4>DHL pickup details:</h4>';
				        	echo '<b>Confirmation No :</b>'.$pickup_array_data['confirm_no'].'<br/>';
				        	echo '<b>Ready By Time :</b>'.$pickup_array_data['ready_time'].'<br/>';
				        	echo '<b>Pickup Date :</b>'.$pickup_array_data['pickup_date'].'<br/>';
			        	}
			        }else {
			        	echo '<br/><br/>Pickup Not Created';
			        }
		       	}

		    }

		    public function create_dhl_return_label_genetation($post){
		    	// print_r('expression');
		    	// die();		    	
		        if(!$this->hpos_enabled && $post->post_type !='shop_order' ){
		    		return;
		    	}
		    	$order = (!$this->hpos_enabled) ? wc_get_order( $post->ID ) : $post;
		    	$order_id = $order->get_id();
		    	$order_data = $order->get_data();
				$to_con = (isset($order_data['shipping']['country']) && !empty($order_data['shipping']['country'])) ? $order_data['shipping']['country'] : "";
				if (empty($to_con)) {
					$to_con = (isset($order_data['billing']['country']) && !empty($order_data['billing']['country'])) ? $order_data['billing']['country'] : "";
				}
		        $_dhl_carriers = array(
								//"Public carrier name" => "technical name",
								'1'                    => 'DOMESTIC EXPRESS 12:00',
								'2'                    => 'B2C',
								'3'                    => 'B2C',
								'4'                    => 'JETLINE',
								'5'                    => 'SPRINTLINE',
								'7'                    => 'EXPRESS EASY',
								'8'                    => 'EXPRESS EASY',
								'9'                    => 'EUROPACK',
								'B'                    => 'BREAKBULK EXPRESS',
								'C'                    => 'MEDICAL EXPRESS',
								'D'                    => 'EXPRESS WORLDWIDE',
								'E'                    => 'EXPRESS 9:00',
								'F'                    => 'FREIGHT WORLDWIDE',
								'G'                    => 'DOMESTIC ECONOMY SELECT',
								'H'                    => 'ECONOMY SELECT',
								'I'                    => 'DOMESTIC EXPRESS 9:00',
								'J'                    => 'JUMBO BOX',
								'K'                    => 'EXPRESS 9:00',
								'L'                    => 'EXPRESS 10:30',
								'M'                    => 'EXPRESS 10:30',
								'N'                    => 'DOMESTIC EXPRESS',
								'O'                    => 'DOMESTIC EXPRESS 10:30',
								'P'                    => 'EXPRESS WORLDWIDE',
								'Q'                    => 'MEDICAL EXPRESS',
								'R'                    => 'GLOBALMAIL BUSINESS',
								'S'                    => 'SAME DAY',
								'T'                    => 'EXPRESS 12:00',
								'U'                    => 'EXPRESS WORLDWIDE',
								'V'                    => 'EUROPACK',
								'W'                    => 'ECONOMY SELECT',
								'X'                    => 'EXPRESS ENVELOPE',
								'Y'                    => 'EXPRESS 12:00'	
							);

		        $general_settings = get_option('a2z_dhl_main_settings',array());
		       	
		       	$json_data = get_option('hit_dhl_return_values_'.$order_id);
		       	if(empty($json_data)){

			        echo '<b>Choose Service to Return</b>';
			        echo '<br/><select name="hit_dhl_express_return_service_code">';
			        if(!empty($general_settings['a2z_dhlexpress_carrier'])){
			        	foreach ($general_settings['a2z_dhlexpress_carrier'] as $key => $value) {
			        		echo "<option value='".$key."'>".$key .' - ' .$_dhl_carriers[$key]."</option>";
			        	}
			        }
			        echo '</select>';
			        

			        echo '<br/><b>Products to return</b>';
			        echo '<br/>';
			        echo '<table>';
			        $items = $order->get_items();
					foreach ( $items as $item ) {
						$product_data = $item->get_data();
					    
					    $product_variation_id = $item->get_variation_id();
					    $product_id = $product_data['product_id'];
					    if(!empty($product_variation_id) && $product_variation_id != 0){
					    	$product_id = $product_variation_id;
					    }

					    echo "<tr><td><input type='checkbox' name='return_products[]' checked value='".$product_id."'>
					    	</td>";
					    echo "<td style='width:150px;'><small title='".$product_data['name']."'>". substr($product_data['name'],0,7)."</small></td>";
					    echo "<td><input type='number' name='qty_products[".$product_id."]' style='width:50px;' value='".$product_data['quantity']."'></td>";
					    echo "</tr>";
					    
					    
					}
			        echo '</table><br/>';

			        $notice = get_option('hit_dhl_return_status_'.$order_id, null);
			        if($notice && $notice != 'success'){
			        	echo "<p style='color:red'>".$notice."</p>";
			        	delete_option('hit_dhl_return_status_'.$order_id);
			        }

			        echo '<button name="hit_dhl_create_return_label" style="background:#FFCC00; color: #D40511;border-color: #FFCC00;box-shadow: 0px 1px 0px #FFCC00;text-shadow: 0px 1px 0px #D40511;" class="button button-primary" type="submit">Create Return Shipment</button>';
			        
		       	} else{
		       		$array_data = json_decode( $json_data, true );
		       		echo '<a href="'.$array_data['label'].'" target="_blank" style="background:#FFCC00; color: #D40511;border-color: #FFCC00;box-shadow: 0px 1px 0px #FFCC00;text-shadow: 0px 1px 0px #D40511;" class="button button-primary"> Return Label </a> ';
		       		if (isset($array_data['invoice']) && apply_filters('hits_show_invoice', true, $to_con, "Return")) {
					   echo '<a href="'.$array_data['invoice'].'" target="_blank" class="button button-primary"> Invoice </a></br>';
					}
					   echo '<button name="hit_dhl_return_reset" class="button button-secondary" style="margin-top:3px;" type="submit"> Reset</button>';
		       		
		       	}

		    }

		    public function hit_wc_checkout_order_processed($order_id){
		    	if ($this->hpos_enabled) {
	 		        if ('shop_order' !== Automattic\WooCommerce\Utilities\OrderUtil::get_order_type($order_id)) {
	 		            return;
	 		        }
	 		    } else {
					$post = get_post($order_id);
					
			    	if($post->post_type !='shop_order' ){
			    		return;
			    	}
			    }

		        $order = wc_get_order( $order_id );

				$already_processed = get_post_meta($order_id, "_shipi_label_requested", false);
				if($already_processed){
					return;
				}else{
					update_post_meta($order_id, "_shipi_label_requested", true);
				}
				
		        $service_code = $multi_ven = '';
		        foreach( $order->get_shipping_methods() as $item_id => $item ){
					$service_code = $item->get_meta('a2z_dhl_service');
					$multi_ven = $item->get_meta('a2z_multi_ven');

				}
              
			
				$general_settings = get_option('a2z_dhl_main_settings',array());
		    	$order_data = $order->get_data();
		    	$items = $order->get_items();
		    	
		    	if(!isset($general_settings['a2z_dhlexpress_label_automation']) || $general_settings['a2z_dhlexpress_label_automation'] != 'yes'){
		    		return;
		    	}

		    	$ship_content = isset($general_settings['a2z_dhlexpress_ship_content']) ? sanitize_text_field($general_settings['a2z_dhlexpress_ship_content']) : 'Shipment Content';
				$desination_country = (isset($order_data['shipping']['country']) && $order_data['shipping']['country'] != '') ? $order_data['shipping']['country'] : $order_data['billing']['country'];
				if(empty($service_code)){
					if( !isset($general_settings['a2z_dhlexpress_international_service']) && !isset($general_settings['a2z_dhlexpress_Domestic_service'])){
						return;
					}
					if (isset($general_settings['a2z_dhlexpress_country']) && $general_settings["a2z_dhlexpress_country"] == $desination_country && $general_settings['a2z_dhlexpress_Domestic_service'] != 'null'){
						$service_code = $general_settings['a2z_dhlexpress_Domestic_service'];
					} elseif (isset($general_settings['a2z_dhlexpress_country']) && $general_settings["a2z_dhlexpress_country"] != $desination_country && $general_settings['a2z_dhlexpress_international_service'] != 'null'){
						$service_code = $general_settings['a2z_dhlexpress_international_service'];
					} else {
						return;
					}
					
				}
			

		    	$custom_settings = array();
				$custom_settings['default'] = array(
									'a2z_dhlexpress_api_type' => isset($general_settings['a2z_dhlexpress_api_type']) ? $general_settings['a2z_dhlexpress_api_type'] : "",
									'a2z_dhlexpress_site_id' => $general_settings['a2z_dhlexpress_site_id'],
									'a2z_dhlexpress_site_pwd' => $general_settings['a2z_dhlexpress_site_pwd'],
									'a2z_dhlexpress_acc_no' => $general_settings['a2z_dhlexpress_acc_no'],
									'a2z_dhlexpress_import_no' => $general_settings['a2z_dhlexpress_import_no'],
									'a2z_dhlexpress_shipper_name' => $general_settings['a2z_dhlexpress_shipper_name'],
									'a2z_dhlexpress_company' => $general_settings['a2z_dhlexpress_company'],
									'a2z_dhlexpress_mob_num' => $general_settings['a2z_dhlexpress_mob_num'],
									'a2z_dhlexpress_email' => $general_settings['a2z_dhlexpress_email'],
									'a2z_dhlexpress_address1' => $general_settings['a2z_dhlexpress_address1'],
									'a2z_dhlexpress_address2' => $general_settings['a2z_dhlexpress_address2'],
									'a2z_dhlexpress_city' => $general_settings['a2z_dhlexpress_city'],
									'a2z_dhlexpress_state' => $general_settings['a2z_dhlexpress_state'],
									'a2z_dhlexpress_zip' => $general_settings['a2z_dhlexpress_zip'],
									'a2z_dhlexpress_country' => $general_settings['a2z_dhlexpress_country'],
									'a2z_dhlexpress_gstin' => $general_settings['a2z_dhlexpress_gstin'],
									'a2z_dhlexpress_con_rate' => $general_settings['a2z_dhlexpress_con_rate'],
									'service_code' => $service_code,
									'a2z_dhlexpress_label_email' => $general_settings['a2z_dhlexpress_label_email'],
									'a2z_dhlexpress_sig_img_url' => isset($general_settings['a2z_dhlexpress_sig_img_url']) ? $general_settings['a2z_dhlexpress_sig_img_url'] : ""
								);
				$vendor_settings = array();



				if(!empty($general_settings['a2z_dhlexpress_weight_unit']) && $general_settings['a2z_dhlexpress_weight_unit'] == 'KG_CM')
				{
					$dhl_mod_weight_unit = 'kg';
					$dhl_mod_dim_unit = 'cm';
				}elseif(!empty($general_settings['a2z_dhlexpress_weight_unit']) && $general_settings['a2z_dhlexpress_weight_unit'] == 'LB_IN')
				{
					$dhl_mod_weight_unit = 'lbs';
					$dhl_mod_dim_unit = 'in';
				}
				else
				{
					$dhl_mod_weight_unit = 'kg';
					$dhl_mod_dim_unit = 'cm';
				}
			    

				$pack_products = array();
				
				foreach ( $items as $item ) {
					$product_data = $item->get_data();

				    $product = array();
				    $product['product_name'] = str_replace('"', '', $product_data['name']);
				    $product['product_quantity'] = $product_data['quantity'];
				    $product['product_id'] = $product_data['product_id'];

				    if ($this->hpos_enabled) {
					    $hpos_prod_data = wc_get_product($product_data['product_id']);
					    $saved_cc = $hpos_prod_data->get_meta("hits_dhl_cc");
					    $saved_cc_inb = $hpos_prod_data->get_meta("hits_dhl_cc_inb");
					    $saved_desc = $hpos_prod_data->get_meta("hits_dhl_desc");
						$dgs_contentid = $hpos_prod_data->get_meta("hits_dhl_danger_good_content_id");
						$dgs_label_des = $hpos_prod_data->get_meta("hits_dhl_danger_good_label_description");
						$dgs_uncode = $hpos_prod_data->get_meta("hits_dhl_danger_good_un_code");
					} else {
				    	$saved_cc = get_post_meta( $product_data['product_id'], 'hits_dhl_cc', true);
				    	$saved_cc_inb = get_post_meta( $product_data['product_id'], 'hits_dhl_cc_inb', true);
				    	$saved_desc = get_post_meta( $product_data['product_id'], 'hits_dhl_desc', true);
						$dgs_contentid = get_post_meta( $product_data['product_id'], 'hits_dhl_danger_good_content_id', true);
						$dgs_label_des = get_post_meta( $product_data['product_id'], 'hits_dhl_danger_good_label_description', true);
						$dgs_uncode = get_post_meta( $product_data['product_id'], 'hits_dhl_danger_good_un_code', true);
				    }
					if(!empty($saved_cc)){
						$product['commodity_code'] = $saved_cc;
					}
					if(!empty($saved_cc_inb)){
						$product['commodity_code_inb'] = apply_filters("a2z_dhlexpress_cc_inb", $saved_cc_inb, $product_data['product_id'], $desination_country);
					}
					if(!empty($saved_desc)){
						$product['invoice_desc'] = $saved_desc;
					}
					if(!empty($dgs_contentid)){
						$product['danger_good_contentid'] = $dgs_contentid;
					}
					if(!empty($dgs_label_des)){
						$product['danger_good_label_desci'] = $dgs_label_des;
					}
					if(!empty($dgs_uncode)){
						$product['danger_good_uncode'] = $dgs_uncode;
					}
					
				    $product_variation_id = $item->get_variation_id();
				    if(empty($product_variation_id) || $product_variation_id == 0){
				    	$getproduct = wc_get_product( $product_data['product_id'] );
				    }else{
				    	$getproduct = wc_get_product( $product_variation_id );
				    	if ($this->hpos_enabled) {
						    $hpos_prod_data = wc_get_product($product_variation_id);
						    $prod_variation_desc = $hpos_prod_data->get_meta("hit_dhl_prod_variation_desc");
						} else {
							$prod_variation_desc = get_post_meta( $product_variation_id, 'hit_dhl_prod_variation_desc', true );
						}
						if (!empty($prod_variation_desc)) {
							$product['invoice_desc'] = $prod_variation_desc;
						}
				    }

				    $skip = apply_filters("a2z_dhlexpress_skip_sku_from_label", false, $getproduct->get_sku());
					if($skip){
						continue;
					}
				    $woo_weight_unit = get_option('woocommerce_weight_unit');
					$woo_dimension_unit = get_option('woocommerce_dimension_unit');

					$dhl_mod_weight_unit = $dhl_mod_dim_unit = '';

					if(!empty($general_settings['a2z_dhlexpress_weight_unit']) && $general_settings['a2z_dhlexpress_weight_unit'] == 'KG_CM')
						{
							$dhl_mod_weight_unit = 'kg';
							$dhl_mod_dim_unit = 'cm';
						}elseif(!empty($general_settings['a2z_dhlexpress_weight_unit']) && $general_settings['a2z_dhlexpress_weight_unit'] == 'LB_IN')
						{
							$dhl_mod_weight_unit = 'lbs';
							$dhl_mod_dim_unit = 'in';
						}
						else
						{
							$dhl_mod_weight_unit = 'kg';
							$dhl_mod_dim_unit = 'cm';
						}
						

					$product['sku'] =  $getproduct->get_sku();
					$product['price'] = (isset($product_data['total']) && isset($product_data['quantity'])) ? number_format(($product_data['total'] / $product_data['quantity']), 2) : 0;
					
					$width  = $getproduct->get_width();
					$height  = $getproduct->get_height();
					$depth  = $getproduct->get_length();

					if($width && $height && $depth){
						if ($woo_dimension_unit != $dhl_mod_dim_unit) {
				    	$prod_width = round($width, 3);
				    	$prod_height = round($height, 3);
				    	$prod_depth = round($depth, 3);

				    	//wc_get_dimension( $dimension, $to_unit, $from_unit );
				    	$product['width'] = (!empty($prod_width) && $prod_width > 0) ? round(wc_get_dimension( $prod_width, $dhl_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;
				    	$product['height'] = (!empty($prod_height) && $prod_height > 0) ? round(wc_get_dimension( $prod_height, $dhl_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;
						$product['depth'] = (!empty($prod_depth) && $prod_depth > 0 )? round(wc_get_dimension( $prod_depth, $dhl_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;

				    }else {
				    	$product['width'] = round($width,3);
				    	$product['height'] = round($height,3);
				    	$product['depth'] = round($depth,3);
				    }
					}else{
						$product['width'] = '';
				    	$product['height'] = '';
				    	$product['depth'] = '';
					}
				    
				    
				    if ($woo_weight_unit != $dhl_mod_weight_unit) {
				    	$prod_weight = $getproduct->get_weight();
						
				    	$product['weight'] =  (!empty($prod_weight) && $prod_weight > 0 ) ? round(wc_get_dimension( $prod_weight, $dhl_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;
				    }else{
				    	$product['weight'] = round($getproduct->get_weight(),3);
					}
				    $pack_products[] = $product;
				    
				}
				if (empty($pack_products)) {
					return;
				}
				if(isset($general_settings['a2z_dhlexpress_v_enable']) && $general_settings['a2z_dhlexpress_v_enable'] == 'yes' && isset($general_settings['a2z_dhlexpress_v_labels']) && $general_settings['a2z_dhlexpress_v_labels'] == 'yes'){
					// Multi Vendor Enabled
					foreach ($pack_products as $key => $value) {

						$product_id = $value['product_id'];
						if ($this->hpos_enabled) {
						    $hpos_prod_data = wc_get_product($product_id);
						    $dhl_account = $hpos_prod_data->get_meta("dhl_express_address");
						} else {
							$dhl_account = get_post_meta($product_id,'dhl_express_address', true);
						}
						if(empty($dhl_account) || $dhl_account == 'default'){
							$dhl_account = 'default';
							if (!isset($vendor_settings[$dhl_account])) {
								$vendor_settings[$dhl_account] = $custom_settings['default'];
							}
							
							$vendor_settings[$dhl_account]['products'][] = $value;
						}

						if($dhl_account != 'default'){
							$user_account = get_post_meta($dhl_account,'a2z_dhl_vendor_settings', true);
							$user_account = empty($user_account) ? array() : $user_account;
							if(!empty($user_account)){
								if(!isset($vendor_settings[$dhl_account])){

									$vendor_settings[$dhl_account] = $custom_settings['default'];
									
									if($user_account['a2z_dhlexpress_site_id'] != '' && $user_account['a2z_dhlexpress_site_pwd'] != '' && $user_account['a2z_dhlexpress_acc_no'] != ''){
										$vendor_settings[$dhl_account]['a2z_dhlexpress_api_type'] = isset($user_account['a2z_dhlexpress_api_type']) ? $user_account['a2z_dhlexpress_api_type'] : "";
										$vendor_settings[$dhl_account]['a2z_dhlexpress_site_id'] = $user_account['a2z_dhlexpress_site_id'];

										if($user_account['a2z_dhlexpress_site_pwd'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_site_pwd'] = $user_account['a2z_dhlexpress_site_pwd'];
										}

										if($user_account['a2z_dhlexpress_acc_no'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_acc_no'] = $user_account['a2z_dhlexpress_acc_no'];
										}

										$vendor_settings[$dhl_account]['a2z_dhlexpress_import_no'] = !empty($user_account['a2z_dhlexpress_import_no']) ? $user_account['a2z_dhlexpress_import_no'] : '';
										
									}

									if ($user_account['a2z_dhlexpress_address1'] != '' && $user_account['a2z_dhlexpress_city'] != '' && $user_account['a2z_dhlexpress_state'] != '' && $user_account['a2z_dhlexpress_zip'] != '' && $user_account['a2z_dhlexpress_country'] != '' && $user_account['a2z_dhlexpress_shipper_name'] != '') {
										
										if($user_account['a2z_dhlexpress_shipper_name'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_shipper_name'] = $user_account['a2z_dhlexpress_shipper_name'];
										}

										if($user_account['a2z_dhlexpress_company'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_company'] = $user_account['a2z_dhlexpress_company'];
										}

										if($user_account['a2z_dhlexpress_mob_num'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_mob_num'] = $user_account['a2z_dhlexpress_mob_num'];
										}

										if($user_account['a2z_dhlexpress_email'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_email'] = $user_account['a2z_dhlexpress_email'];
										}

										if ($user_account['a2z_dhlexpress_address1'] != '') {
											$vendor_settings[$dhl_account]['a2z_dhlexpress_address1'] = $user_account['a2z_dhlexpress_address1'];
										}

										$vendor_settings[$dhl_account]['a2z_dhlexpress_address2'] = $user_account['a2z_dhlexpress_address2'];
										
										if($user_account['a2z_dhlexpress_city'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_city'] = $user_account['a2z_dhlexpress_city'];
										}

										if($user_account['a2z_dhlexpress_state'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_state'] = $user_account['a2z_dhlexpress_state'];
										}

										if($user_account['a2z_dhlexpress_zip'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_zip'] = $user_account['a2z_dhlexpress_zip'];
										}

										if($user_account['a2z_dhlexpress_country'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_country'] = $user_account['a2z_dhlexpress_country'];
										}

										$vendor_settings[$dhl_account]['a2z_dhlexpress_gstin'] = $user_account['a2z_dhlexpress_gstin'];
										$vendor_settings[$dhl_account]['a2z_dhlexpress_con_rate'] = $user_account['a2z_dhlexpress_con_rate'];
									}
									if (isset($user_account['a2z_dhlexpress_sig_img_url']) && !empty($user_account['a2z_dhlexpress_sig_img_url'])) {
										$vendor_settings[$dhl_account]['a2z_dhlexpress_sig_img_url'] = $user_account['a2z_dhlexpress_sig_img_url'];
									}

									if(isset($general_settings['a2z_dhlexpress_v_email']) && $general_settings['a2z_dhlexpress_v_email'] == 'yes'){
										$user_dat = get_userdata($dhl_account);
										$vendor_settings[$dhl_account]['a2z_dhlexpress_label_email'] = $user_dat->data->user_email;
									}
									
									if($multi_ven !=''){
										$array_ven = explode('|',$multi_ven);
										$scode = '';
										foreach ($array_ven as $key => $svalue) {
											$ex_service = explode("_", $svalue);
											if($ex_service[0] == $dhl_account){
												$vendor_settings[$dhl_account]['service_code'] = $ex_service[1];
											}
										}
										
										if($scode == ''){
											if($order_data['shipping']['country'] != $vendor_settings[$dhl_account]['a2z_dhlexpress_country']){
												$vendor_settings[$dhl_account]['service_code'] = $user_account['a2z_dhlexpress_def_inter'];
											}else{
												$vendor_settings[$dhl_account]['service_code'] = $user_account['a2z_dhlexpress_def_dom'];
											}
										}

									}else{
										if($order_data['shipping']['country'] != $vendor_settings[$dhl_account]['a2z_dhlexpress_country']){
											$vendor_settings[$dhl_account]['service_code'] = $user_account['a2z_dhlexpress_def_inter'];
										}else{
											$vendor_settings[$dhl_account]['service_code'] = $user_account['a2z_dhlexpress_def_dom'];
										}

									}
								}
								$vendor_settings[$dhl_account]['products'][] = $value;
							}
						}

					}

				}

				if(empty($vendor_settings)){
					$custom_settings['default']['products'] = $pack_products;
				}else{
					$custom_settings = $vendor_settings;
				}

				$order_id = $order_data['id'];
	       		$order_currency = $order_data['currency'];

				   $shipping_arr = (isset($order_data['shipping']['first_name']) && $order_data['shipping']['first_name'] != "") ? $order_data['shipping'] : $order_data['billing'];
				   $order_shipping_first_name = $shipping_arr['first_name'];
				   $order_shipping_last_name = $shipping_arr['last_name'];
				   $order_shipping_company = empty($shipping_arr['company']) ? $shipping_arr['first_name'] :  $shipping_arr['company'];
				   $order_shipping_address_1 = $shipping_arr['address_1'];
				   $order_shipping_address_2 = $shipping_arr['address_2'];
				   $order_shipping_city = $shipping_arr['city'];
				   $order_shipping_state = $shipping_arr['state'];
				   $order_shipping_postcode = $shipping_arr['postcode'];
				   $order_shipping_country = $shipping_arr['country'];
				   $order_shipping_phone = $order_data['billing']['phone'];
				   $order_shipping_email = $order_data['billing']['email'];

	       		// $order_shipping_first_name = $order_data['shipping']['first_name'];
				// $order_shipping_last_name = $order_data['shipping']['last_name'];
				// $order_shipping_company = empty($order_data['shipping']['company']) ? $order_data['shipping']['first_name'] :  $order_data['shipping']['company'];
				// $order_shipping_address_1 = $order_data['shipping']['address_1'];
				// $order_shipping_address_2 = $order_data['shipping']['address_2'];
				// $order_shipping_city = $order_data['shipping']['city'];
				// $order_shipping_state = $order_data['shipping']['state'];
				// $order_shipping_postcode = $order_data['shipping']['postcode'];
				// $order_shipping_country = $order_data['shipping']['country'];
				// $order_shipping_phone = $order_data['billing']['phone'];
				// $order_shipping_email = $order_data['billing']['email'];

				if(!empty($general_settings) && isset($general_settings['a2z_dhlexpress_integration_key'])){
					$mode = 'live';
					if(isset($general_settings['a2z_dhlexpress_test']) && $general_settings['a2z_dhlexpress_test']== 'yes'){
						$mode = 'test';
					}
					$execution = 'manual';
					if(isset($general_settings['a2z_dhlexpress_label_automation']) && $general_settings['a2z_dhlexpress_label_automation']== 'yes'){
						$execution = 'auto';
					}

					$boxes_to_shipo = array();
					if (isset($general_settings['a2z_dhlexpress_packing_type']) && $general_settings['a2z_dhlexpress_packing_type'] == "box") {
						if (isset($general_settings['a2z_dhlexpress_boxes']) && !empty($general_settings['a2z_dhlexpress_boxes'])) {
							foreach ($general_settings['a2z_dhlexpress_boxes'] as $box) {
								if ($box['enabled'] != 1) {
									continue;
								}else {
									$boxes_to_shipo[] = $box;
								}
							}
						}
					}

					foreach ($custom_settings as $key => $cvalue) {
						global $dhl_core;
						$frm_curr = get_option('woocommerce_currency');
						$to_curr = isset($dhl_core[$cvalue['a2z_dhlexpress_country']]) ? $dhl_core[$cvalue['a2z_dhlexpress_country']]['currency'] : '';
						$curr_con_rate = ( isset($cvalue['a2z_dhlexpress_con_rate']) && !empty($cvalue['a2z_dhlexpress_con_rate']) ) ? $cvalue['a2z_dhlexpress_con_rate'] : 0;

						if (!empty($frm_curr) && !empty($to_curr) && ($frm_curr != $to_curr) ) {
							if (isset($general_settings['a2z_dhlexpress_auto_con_rate']) && $general_settings['a2z_dhlexpress_auto_con_rate'] == "yes") {
								$current_date = date('m-d-Y', time());
								$ex_rate_data = get_option('a2z_dhl_ex_rate'.$key);
								$ex_rate_data = !empty($ex_rate_data) ? $ex_rate_data : array();
								if (empty($ex_rate_data) || (isset($ex_rate_data['date']) && $ex_rate_data['date'] != $current_date) ) {
									if (isset($cvalue['a2z_dhlexpress_country']) && !empty($cvalue['a2z_dhlexpress_country']) && isset($general_settings['a2z_dhlexpress_integration_key']) && !empty($general_settings['a2z_dhlexpress_integration_key'])) {
										
										$ex_rate_Request = json_encode(array('integrated_key' => $general_settings['a2z_dhlexpress_integration_key'],
															'from_curr' => $frm_curr,
															'to_curr' => $to_curr));

										$ex_rate_url = "https://app.myshipi.com/get_exchange_rate.php";
										$ex_rate_response = wp_remote_post( $ex_rate_url , array(
														'method'      => 'POST',
														'timeout'     => 45,
														'redirection' => 5,
														'httpversion' => '1.0',
														'blocking'    => true,
														'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
														'body'        => $ex_rate_Request,
														'sslverify'   => true
														)
													);

										$ex_rate_result = ( is_array($ex_rate_response) && isset($ex_rate_response['body'])) ? json_decode($ex_rate_response['body'], true) : array();

										if ( !empty($ex_rate_result) && isset($ex_rate_result['ex_rate']) && $ex_rate_result['ex_rate'] != "Not Found" ) {
											$ex_rate_result['date'] = $current_date;
											update_option('a2z_dhl_ex_rate'.$key, $ex_rate_result);
										}else {
											if (!empty($ex_rate_data)) {
												$ex_rate_data['date'] = $current_date;
												update_option('a2z_dhl_ex_rate'.$key, $ex_rate_data);
											}
										}
									}
								}
								$get_ex_rate = get_option('a2z_dhl_ex_rate'.$key, '');
								$get_ex_rate = !empty($get_ex_rate) ? $get_ex_rate : array();
								$curr_con_rate = ( !empty($get_ex_rate) && isset($get_ex_rate['ex_rate']) ) ? $get_ex_rate['ex_rate'] : 0;
							}
						}

						$c_codes = [];

						foreach($cvalue['products'] as $prod_to_shipo_key => $prod_to_shipo){
							if ($this->hpos_enabled) {
							    $hpos_prod_data = wc_get_product($prod_to_shipo['product_id']);
							    $saved_cc = $hpos_prod_data->get_meta("hits_dhl_cc");
							} else {
								$saved_cc = get_post_meta( $prod_to_shipo['product_id'], 'hits_dhl_cc', true);
							}
							if(!empty($saved_cc)){
								$c_codes[] = $saved_cc;
							}

							if (!empty($frm_curr) && !empty($to_curr) && ($frm_curr != $to_curr) ) {
								if ($curr_con_rate > 0 && apply_filters("hit_do_conversion_while_label_generation", true, $order_shipping_country)) {
									$cvalue['products'][$prod_to_shipo_key]['price'] = $prod_to_shipo['price'] * $curr_con_rate;
								}
							}
						}
						$insurance = apply_filters("hitshipo_ins_ship", $general_settings['a2z_dhlexpress_insure'], $key, $order);
						$insurance_value = apply_filters("hitshipo_ins_val_ship", 0, $key, $order);

						//For Automatic Label Generation						
						$mode = apply_filters("shipi_dhlexpress_auto_mode", $mode);

						$data = array();
						$data['integrated_key'] = $general_settings['a2z_dhlexpress_integration_key'];
						$data['order_id'] = $order_id;
						$data['exec_type'] = $execution;
						$data['mode'] = $mode;
						$data['carrier_type'] = 'dhl';
						$data['meta'] = array(
							"api_type" => (isset($cvalue['a2z_dhlexpress_api_type']) && !empty($cvalue['a2z_dhlexpress_api_type'])) ? $cvalue['a2z_dhlexpress_api_type'] : "SOAP",
							"site_id" => $cvalue['a2z_dhlexpress_site_id'],
							"password"  => $cvalue['a2z_dhlexpress_site_pwd'],
							"accountnum" => $cvalue['a2z_dhlexpress_acc_no'],
							"t_company" => $order_shipping_company,
							"t_address1" => str_replace('"', '', $order_shipping_address_1),
							"t_address2" => str_replace('"', '', $order_shipping_address_2),
							"t_city" => $order_shipping_city,
							"t_state" => $order_shipping_state,
							"t_postal" => $order_shipping_postcode,
							"t_country" => $order_shipping_country,
							"t_name" => $order_shipping_first_name . ' '. $order_shipping_last_name,
							"t_phone" => $order_shipping_phone,
							"t_email" => $order_shipping_email,
							"t_gstin" => apply_filters("hitshipo_dhlexpress_receiver_vat", "", $order),
							"dutiable" => $general_settings['a2z_dhlexpress_duty_payment'],
							"insurance" => $insurance,
							"cus_ins_val" => $insurance_value,
							"pack_this" => "Y",
							"products" => apply_filters("hitshipo_prods_to_ship", $cvalue['products'], $order, $key),
							"pack_algorithm" => $general_settings['a2z_dhlexpress_packing_type'],
							"boxes" => $boxes_to_shipo,
							"max_weight" => $general_settings['a2z_dhlexpress_max_weight'],
							"plt" => ($general_settings['a2z_dhlexpress_ppt'] == 'yes') ? "Y" : "N",
							"airway_bill" => ($general_settings['a2z_dhlexpress_aabill'] == 'yes') ? "Y" : "N",
							"sd" => ($general_settings['a2z_dhlexpress_sat'] == 'yes') ? "Y" : "N",
							"cod" => ($general_settings['a2z_dhlexpress_cod'] == 'yes') ? "Y" : "N",
							"service_code" => $service_code,
							"danger_goods_item" => isset($general_settings['a2z_dhlexpress_dgs']) ? $general_settings['a2z_dhlexpress_dgs'] : 'no',
							"shipment_content" => apply_filters("hitshipo_dhl_cus_ship_desc", $ship_content, $order_id, $key),
							"email_alert" => ( isset($general_settings['a2z_dhlexpress_email_alert']) && ($general_settings['a2z_dhlexpress_email_alert'] == 'yes') ) ? "Y" : "N",
							"s_company" => $cvalue['a2z_dhlexpress_company'],
							"s_address1" => $cvalue['a2z_dhlexpress_address1'],
							"s_address2" => $cvalue['a2z_dhlexpress_address2'],
							"s_city" => $cvalue['a2z_dhlexpress_city'],
							"s_state" => $cvalue['a2z_dhlexpress_state'],
							"s_postal" => $cvalue['a2z_dhlexpress_zip'],
							"s_country" => $cvalue['a2z_dhlexpress_country'],
							"gstin" => $cvalue['a2z_dhlexpress_gstin'],
							"s_name" => $cvalue['a2z_dhlexpress_shipper_name'],
							"s_phone" => $cvalue['a2z_dhlexpress_mob_num'],
							"s_email" => $cvalue['a2z_dhlexpress_email'],
							"label_size" => $general_settings['a2z_dhlexpress_print_size'],
							"sent_email_to" => $cvalue['a2z_dhlexpress_label_email'],
							"sig_img_url" => isset($cvalue['a2z_dhlexpress_sig_img_url']) ? $cvalue['a2z_dhlexpress_sig_img_url'] : "",
							"pic_exec_type" => (isset($general_settings['a2z_dhlexpress_pickup_automation']) && $general_settings['a2z_dhlexpress_pickup_automation'] == 'yes') ? "auto" : "manual",
				            "pic_loc_type" => (isset($general_settings['a2z_dhlexpress_pickup_loc_type']) ? $general_settings['a2z_dhlexpress_pickup_loc_type'] : ''),
				            "pic_pac_loc" => (isset($general_settings['a2z_dhlexpress_pickup_pac_loc']) ? $general_settings['a2z_dhlexpress_pickup_pac_loc'] : ''),
				            "pic_contact_per" => (isset($general_settings['a2z_dhlexpress_pickup_per_name']) ? $general_settings['a2z_dhlexpress_pickup_per_name'] : ''),
				            "pic_contact_no" => (isset($general_settings['a2z_dhlexpress_pickup_per_contact_no']) ? $general_settings['a2z_dhlexpress_pickup_per_contact_no'] : ''),
				            "pic_door_to" => (isset($general_settings['a2z_dhlexpress_pickup_door_to']) ? $general_settings['a2z_dhlexpress_pickup_door_to'] : ''),
				            "pic_type" => (isset($general_settings['a2z_dhlexpress_pickup_type']) ? $general_settings['a2z_dhlexpress_pickup_type'] : ''),
				            "pic_days_after" => (isset($general_settings['a2z_dhlexpress_pickup_date']) ? $general_settings['a2z_dhlexpress_pickup_date'] : ''),
				            "pic_open_time" => (isset($general_settings['a2z_dhlexpress_pickup_open_time']) ? $general_settings['a2z_dhlexpress_pickup_open_time'] : ''),
				            "pic_close_time" => (isset($general_settings['a2z_dhlexpress_pickup_close_time']) ? $general_settings['a2z_dhlexpress_pickup_close_time'] : ''),
				            "pic_mail_date" => date('c'),
				    		"pic_date" => date("Y-m-d"),
							"label" => $key,
							"payment_con" => (isset($general_settings['a2z_dhlexpress_pay_con']) ? $general_settings['a2z_dhlexpress_pay_con'] : 'S'),
							"cus_payment_con" => (isset($general_settings['a2z_dhlexpress_cus_pay_con']) ? $general_settings['a2z_dhlexpress_cus_pay_con'] : ''),
							"translation" => ( (isset($general_settings['a2z_dhlexpress_translation']) && $general_settings['a2z_dhlexpress_translation'] == "yes" ) ? 'Y' : 'N'),
							"translation_key" => (isset($general_settings['a2z_dhlexpress_translation_key']) ? $general_settings['a2z_dhlexpress_translation_key'] : ''),
							"commodity_code" => $c_codes,
							"ship_price" => isset($order_data['shipping_total']) ? $order_data['shipping_total'] : 0,
							"order_total" => isset($order_data['total']) ? $order_data['total'] : 0,
							"order_total_tax" => isset($order_data['total_tax']) ? $order_data['total_tax'] : 0,
							"inv_type" => isset($general_settings['a2z_dhlexpress_inv_type']) ? $general_settings['a2z_dhlexpress_inv_type'] : "",
							"inv_temp_type" => isset($general_settings['a2z_dhlexpress_inv_temp_type']) ? $general_settings['a2z_dhlexpress_inv_temp_type'] : "",
							"export_reason" => (isset($general_settings['a2z_dhlexpress_export_reason']) ? $general_settings['a2z_dhlexpress_export_reason'] : 'P')
						);

						//Auto Shipment
						 $auto_ship_url = "https://app.myshipi.com/label_api/create_shipment.php";
						

						if($mode == "manual"){
							$response = wp_remote_post( $auto_ship_url , array(
								'method'      => 'POST',
								'timeout'     => 45,
								'redirection' => 5,
								'httpversion' => '1.0',
								'blocking'    => true,
								'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
								'body'        => json_encode($data),
								'sslverify'   => true
								)
							);
							$output = (is_array($response) && isset($response['body'])) ? json_decode($response['body'],true) : [];
						
							if($output){
								if(isset($output['status']) || isset($output['pic_status'])){

									if(isset($output['status']) && $output['status'] != 'success'){
										   update_option('hit_dhl_status_'.$order_id, $output['status']);
										   
									}else if(isset($output['status']) && $output['status'] == 'success'){
										$output['user_id'] = $create_shipment_for;
										$val = get_option('hit_dhl_values_'.$order_id, []);
										$result_arr = array();
										if(!empty($val)){
											$result_arr = json_decode($val, true);
										}
										
										$result_arr[] = $output;

										update_option('hit_dhl_values_'.$order_id, json_encode(apply_filters("shipi_dhl_express_save_output", $result_arr, $order_id)));
										
									}
									if (isset($output['pic_status']) && $output['pic_status'] != 'success') {
										$pic_res['status'] = "failed";
										update_option('hit_dhl_pickup_values_'.$order_id, json_encode($pic_res));
									}elseif (isset($output['pic_status']) && $output['pic_status'] == 'success') {
										$pic_res['confirm_no'] = $output['confirm_no'];
										$pic_res['ready_time'] = $output['ready_time'];
										$pic_res['pickup_date'] = $output['pickup_date'];
										$pic_res['status'] = "success";

										update_option('hit_dhl_pickup_values_'.$order_id, json_encode(apply_filters("shipi_dhl_express_save_pickup_output", $pic_res)));
									}
								}else{
									update_option('hit_dhl_status_'.$order_id, 'Something Went Wrong.');
								}
							}else{
								update_option('hit_dhl_status_'.$order_id, 'Something Went Wrong.');
							}
						}else{
							wp_remote_post( $auto_ship_url , array(
								'method'      => 'POST',
								'timeout'     => 45,
								'redirection' => 5,
								'httpversion' => '1.0',
								'blocking'    => false,
								'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
								'body'        => json_encode($data),
								'sslverify'   => true
								)
							);
						}

					}
	       		
				}	
		    }

		    // Save the data of the Meta field
			public function hit_create_dhl_shipping( $order_id ) {
				if ($this->hpos_enabled) {
	 		        if ('shop_order' !== Automattic\WooCommerce\Utilities\OrderUtil::get_order_type($order_id)) {
	 		            return;
	 		        }
	 		    } else {
			    	$post = get_post($order_id);
			    	if($post->post_type !='shop_order' ){
			    		return;
			    	}
			    }
		    	
		    	if (  isset( $_POST[ 'hit_dhl_reset' ] ) ) {
		    		delete_option('hit_dhl_values_'.$order_id);
		    	}

		    	if (isset($_POST['hit_dhl_get_rates'])) {
		    		global $dhl_core;
		    		$get_rates_for = sanitize_text_field($_POST['hit_dhl_get_rates']);
		    		$general_settings = get_option('a2z_dhl_main_settings',array());
		    		$order = wc_get_order( $order_id );
					if($order){
			    		$order_data = $order->get_data();
				       	$order_id = $order_data['id'];
				       	$order_products = $this->get_products_on_order($general_settings, $order);
				       	if (empty($order_products)) {
				       		update_option('hit_dhl_status_'.$order_id, "No product data found.");
							return;
				       	}
				       	$custom_settings = $this->get_vendors_on_order($general_settings, $order_products);
				       	$custom_packs = [];
			        	if (isset($_POST['hits_dhl_add_cus_pack_'.$get_rates_for])) {
							if (!isset($_POST['hits_dhl_pack_weight_'.$get_rates_for])) {
								update_option('hit_dhl_status_'.$order_id, "Need packs for custom packing.");
			    				return;
							}
							foreach ($_POST['hits_dhl_pack_weight_'.$get_rates_for] as $key => $pack_heights) {
								$curr_pack = [];
								$curr_pack['GroupNumber'] = $key+1;
								$curr_pack['GroupPackageCount'] = $key+1;
								$curr_pack['Weight'] = [
									"Value" => isset($_POST['hits_dhl_pack_weight_'.$get_rates_for][$key]) ? $_POST['hits_dhl_pack_weight_'.$get_rates_for][$key] : 0,
									"Units" => isset($_POST['hits_dhl_pack_weg_unit_'.$get_rates_for][$key]) ? $_POST['hits_dhl_pack_weg_unit_'.$get_rates_for][$key] : "KG"
								];
								if (isset($_POST['hits_dhl_pack_height_'.$get_rates_for][$key]) && isset($_POST['hits_dhl_pack_length_'.$get_rates_for][$key]) && isset($_POST['hits_dhl_pack_width_'.$get_rates_for][$key])) {
									if (!empty($_POST['hits_dhl_pack_height_'.$get_rates_for][$key]) && !empty($_POST['hits_dhl_pack_length_'.$get_rates_for][$key]) && !empty($_POST['hits_dhl_pack_width_'.$get_rates_for][$key])) {
										$curr_pack['Dimensions'] = [
											"Length" => $_POST['hits_dhl_pack_length_'.$get_rates_for][$key],
											"Width" => $_POST['hits_dhl_pack_width_'.$get_rates_for][$key],
											"Height" => $_POST['hits_dhl_pack_height_'.$get_rates_for][$key],
											"Units" =>$_POST['hits_dhl_pack_dim_unit_'.$get_rates_for][$key]
										];
									}
								}
								$curr_pack['InsuredValue'] = [
									"Amount" => (isset($_POST['hits_dhl_pack_cost_'.$get_rates_for][$key]) && !empty($_POST['hits_dhl_pack_cost_'.$get_rates_for][$key])) ? $_POST['hits_dhl_pack_cost_'.$get_rates_for][$key] : 1,
									"Currency" => isset($order_data['currency']) ? $order_data['currency'] : ""
								];
								$curr_pack['packed_products'] = [];
								$curr_pack['package_id'] = "Custom_Pack_".$key;
								$curr_pack['packtype'] = "BOX";
								$custom_packs[] = $curr_pack;
							}
						}
				       	$mesage_time = date('c');
						$message_date = date('Y-m-d');
						$weight_unit = $dim_unit = '';
						if (!empty($general_settings['a2z_dhlexpress_weight_unit']) && $general_settings['a2z_dhlexpress_weight_unit'] == 'KG_CM') {
							$weight_unit = 'KG';
							$dim_unit = 'CM';
						} else {
							$weight_unit = 'LB';
							$dim_unit = 'IN';
						}

						if (!isset($general_settings['a2z_dhlexpress_packing_type'])) {
							return;
						}

						$woo_weight_unit = get_option('woocommerce_weight_unit');
						$woo_dimension_unit = get_option('woocommerce_dimension_unit');

						$dhl_mod_weight_unit = $dhl_mod_dim_unit = '';

						if (!empty($general_settings['a2z_dhlexpress_weight_unit']) && $general_settings['a2z_dhlexpress_weight_unit'] == 'KG_CM') {
							$dhl_mod_weight_unit = 'kg';
							$dhl_mod_dim_unit = 'cm';
						} elseif (!empty($general_settings['a2z_dhlexpress_weight_unit']) && $general_settings['a2z_dhlexpress_weight_unit'] == 'LB_IN') {
							$dhl_mod_weight_unit = 'lbs';
							$dhl_mod_dim_unit = 'in';
						} else {
							$dhl_mod_weight_unit = 'kg';
							$dhl_mod_dim_unit = 'cm';
						}

						$shipping_rates = array();
						$value = isset($custom_settings[$get_rates_for]) ? $custom_settings[$get_rates_for] : [];
						if (isset($general_settings['a2z_dhlexpress_auto_con_rate']) && $general_settings['a2z_dhlexpress_auto_con_rate'] == "yes") {
							$current_date = date('m-d-Y', time());
							$ex_rate_data = get_option('a2z_dhl_ex_rate');
							$ex_rate_data = !empty($ex_rate_data) ? $ex_rate_data : array();
							if (empty($ex_rate_data) || (isset($ex_rate_data['date']) && $ex_rate_data['date'] != $current_date) ) {
								if (isset($general_settings['a2z_dhlexpress_country']) && !empty($general_settings['a2z_dhlexpress_country']) && isset($general_settings['a2z_dhlexpress_integration_key']) && !empty($general_settings['a2z_dhlexpress_integration_key'])) {
									$frm_curr = get_option('woocommerce_currency');
									$to_curr = isset($dhl_core[$general_settings['a2z_dhlexpress_country']]) ? $dhl_core[$general_settings['a2z_dhlexpress_country']]['currency'] : '';
									$ex_rate_Request = json_encode(array('integrated_key' => $general_settings['a2z_dhlexpress_integration_key'],
														'from_curr' => $frm_curr,
														'to_curr' => $to_curr));

									$ex_rate_url = "https://app.myshipi.com/get_exchange_rate.php";
									$ex_rate_response = wp_remote_post( $ex_rate_url , array(
													'method'      => 'POST',
													'timeout'     => 45,
													'redirection' => 5,
													'httpversion' => '1.0',
													'blocking'    => true,
													'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
													'body'        => $ex_rate_Request,
													'sslverify'   => true
													)
												);
									$ex_rate_result = ( is_array($ex_rate_response) && isset($ex_rate_response['body'])) ? json_decode($ex_rate_response['body'], true) : array();

									if ( !empty($ex_rate_result) && isset($ex_rate_result['ex_rate']) && $ex_rate_result['ex_rate'] != "Not Found" ) {
										$ex_rate_result['date'] = $current_date;
										update_option('a2z_dhl_ex_rate'.$key, $ex_rate_result);
									}else {
										if (!empty($ex_rate_data)) {
											$ex_rate_data['date'] = $current_date;
											update_option('a2z_dhl_ex_rate'.$key, $ex_rate_data);
										}
									}
								}
							}
						}
						if (isset($general_settings['a2z_dhlexpress_translation']) && $general_settings['a2z_dhlexpress_translation'] == "yes" ) {
							if (isset($general_settings['a2z_dhlexpress_translation_key']) && !empty($general_settings['a2z_dhlexpress_translation_key'])) {
								include_once('classes/gtrans/vendor/autoload.php');
								foreach($order_data['shipping'] as $dkey => $dvalue){
									if (!empty($dvalue)) {
										if (!preg_match('%^[ -~]+$%', $dvalue))      //Cheks english or not  /[^A-Za-z0-9]+/ 
										{
										  $response =array();
										  try{
											$translate = new TranslateClient(['key' => $general_settings['a2z_dhlexpress_translation_key']]);
											// Tranlate text
											$response = $translate->translate($dvalue, [
												'target' => 'en',
											]);
										  }catch(exception $e){
											// echo "\n Exception Caught" . $e->getMessage(); //Error handling
										  }
										  if (!empty($response) && isset($response['text']) && !empty($response['text'])) {
											$order_data['shipping'][$dkey] = $response['text'];
										  }
										}
									}
								}
							}
						}
						$to_city = $order_data['shipping']['city'];

						$shipping_rates[$get_rates_for] = array();
						$orgin_postalcode_or_city = $this->a2z_get_zipcode_or_city($value['a2z_dhlexpress_country'], $value['a2z_dhlexpress_city'], $value['a2z_dhlexpress_zip']);
						$destination_postcode_city = $this->a2z_get_zipcode_or_city($order_data['shipping']['country'], $to_city, $order_data['shipping']['postcode']);
						$general_settings['a2z_dhlexpress_currency'] = isset($dhl_core[(isset($value['a2z_dhlexpress_country']) ? $value['a2z_dhlexpress_country'] : 'A2Z')]) ? $dhl_core[$value['a2z_dhlexpress_country']]['currency'] : '';
						$value['products'] = apply_filters('a2z_dhlexpress_rate_based_product', $value['products'],'true');
						$dhl_packs = !empty($custom_packs) ? $custom_packs : $this->hit_get_dhl_packages($value['products'], $general_settings, $general_settings['a2z_dhlexpress_currency']);
						
						if (isset($value['a2z_dhlexpress_api_type']) && $value['a2z_dhlexpress_api_type'] == "REST") {
							$rec_address = (isset($order_data['shipping']['first_name']) && !empty($order_data['shipping']['first_name'])) ? $order_data['shipping'] : $order_data['billing'];
							$ship_address = $this->getFormatedShipAddr($value);
							if (!class_exists("dhl_rest")) {
								include_once('controllors/classes/dhl_rest_main.php');
							}
							$dhl_rest_obj = new dhl_rest();
							$dhl_rest_obj->dhlCurrency = $general_settings['a2z_dhlexpress_currency'];
							$mode = (isset($general_settings['a2z_dhlexpress_test']) && $general_settings['a2z_dhlexpress_test'] != 'yes') ? "live" : "test";
							$create_shipment_for = sanitize_text_field($_POST['hit_dhl_get_rates']);
						    $hit_dhl_pickup_type = !empty($_POST['hit_dhl_pickup_type_'.$create_shipment_for]) ? sanitize_text_field($_POST['hit_dhl_pickup_type_'.$create_shipment_for]) : 'S';
							$add_date = $hit_dhl_pickup_type != "S" ? $hit_dhl_pickup_type : 0;
							do {
								$xmlRequest = $dhl_rest_obj->createRateReq($dhl_packs, $general_settings, $ship_address, $rec_address, $add_date);
								$xml = $dhl_rest_obj->getRes($xmlRequest, $mode, $value['a2z_dhlexpress_site_id'], $value['a2z_dhlexpress_site_pwd'], "rate");
								if(isset($xml->detail) && (strpos((string)$xml->detail, "996") !== false)){
								    $re_run = true;
								    $add_date++;
							    } else {
							    	$re_run = false;
							    }
							} while ($re_run);
						} else {
							$pieces = "";
							$cart_total = 0;
							$index = 0;
							if ($dhl_packs) {
								foreach ($dhl_packs as $parcel) {
									$index = $index + 1;
									$pieces .= '<Piece><PieceID>' . $index . '</PieceID>';
									$pieces .= '<PackageTypeCode>' . $parcel['packtype'] . '</PackageTypeCode>';

									if (isset($parcel['Dimensions']['Height']) && !empty($parcel['Dimensions']['Height']) && !empty($parcel['Dimensions']['Length']) && !empty($parcel['Dimensions']['Width'])) {

										if ($woo_dimension_unit != $dhl_mod_dim_unit) {
											//wc_get_dimension( $dimension, $to_unit, $from_unit );
											$pieces .= '<Height>' . round(wc_get_dimension($parcel['Dimensions']['Height'], $dhl_mod_dim_unit, $woo_dimension_unit), 2) . '</Height>';
											$pieces .= '<Depth>' . round(wc_get_dimension($parcel['Dimensions']['Length'], $dhl_mod_dim_unit, $woo_dimension_unit), 2) . '</Depth>';
											$pieces .= '<Width>' . round(wc_get_dimension($parcel['Dimensions']['Width'], $dhl_mod_dim_unit, $woo_dimension_unit), 2) . '</Width>';
										} else {
											$pieces .= '<Height>' . $parcel['Dimensions']['Height'] . '</Height>';
											$pieces .= '<Depth>' . $parcel['Dimensions']['Length'] . '</Depth>';
											$pieces .= '<Width>' . $parcel['Dimensions']['Width'] . '</Width>';
										}
									}
									$total_weight   = (string) $parcel['Weight']['Value'];
									$total_weight   = str_replace(',', '.', $total_weight);
									if ($total_weight < 0.001) {
										$total_weight = 0.001;
									} else {
										$total_weight = round((float)$total_weight, 3);
									}
									if ($woo_weight_unit != $dhl_mod_weight_unit) {
										$pieces .= '<Weight>' . round(wc_get_weight($total_weight, $dhl_mod_weight_unit, $woo_weight_unit), 2) . '</Weight></Piece>';
									} else {
										$pieces .= '<Weight>' . $total_weight . '</Weight></Piece>';
									}
									$cart_total += $parcel['InsuredValue']['Amount'];
								}
							}
							$order_total = $cart_total;
							$fetch_accountrates = (isset($general_settings['a2z_dhlexpress_account_rates']) && $general_settings['a2z_dhlexpress_account_rates'] == "yes") ? "<PaymentAccountNumber>" . $value['a2z_dhlexpress_acc_no'] . "</PaymentAccountNumber>" : "";
							$dutiable = ( ($order_data['shipping']['country'] == $value['a2z_dhlexpress_country']) ) ? "N" : "Y";
							if($this->a2z_dhl_is_eu_country($value['a2z_dhlexpress_country'], $order_data['shipping']['country'])){
								$dutiable = "N";
							}
							if($order_data['shipping']['country'] == 'AT' && $value['a2z_dhlexpress_country'] == 'CZ'){
								$dutiable = "N";
							}
							if($order_data['shipping']['country'] == 'NL' && $value['a2z_dhlexpress_country'] == 'SE'){
								$dutiable = "N";
							}
							
							if ($general_settings['a2z_dhlexpress_currency'] != get_option('woocommerce_currency')) {
								if (isset($general_settings['a2z_dhlexpress_auto_con_rate']) && $general_settings['a2z_dhlexpress_auto_con_rate'] == "yes") {
									$get_ex_rate = get_option('a2z_dhl_ex_rate'.$key, '');
									$get_ex_rate = !empty($get_ex_rate) ? $get_ex_rate : array();
									$exchange_rate = ( !empty($get_ex_rate) && isset($get_ex_rate['ex_rate']) ) ? $get_ex_rate['ex_rate'] : 0;
								}else{
									$exchange_rate = $value['a2z_dhlexpress_con_rate'];
								}

								if ($exchange_rate && $exchange_rate > 0) {
									$cart_total *= $exchange_rate;
								}
							}

							$dutiable_content = ($dutiable == "Y") ? "<Dutiable><DeclaredCurrency>" . $general_settings['a2z_dhlexpress_currency'] . "</DeclaredCurrency><DeclaredValue>" . $cart_total . "</DeclaredValue></Dutiable>" : "";

							$insurance_details = (isset($general_settings['a2z_dhlexpress_insure']) && $general_settings['a2z_dhlexpress_insure'] == 'yes')  ? "<QtdShp><QtdShpExChrg><SpecialServiceType>II</SpecialServiceType><LocalSpecialServiceType>XCH</LocalSpecialServiceType></QtdShpExChrg></QtdShp><InsuredValue>" . round($cart_total, 2) . "</InsuredValue><InsuredCurrency>" . $general_settings['a2z_dhlexpress_currency'] . "</InsuredCurrency>" : ""; //insurance type
							$danger_goods = (isset($general_settings['a2z_dhlexpress_dgs']) && $general_settings['a2z_dhlexpress_dgs'] == 'yes')  ? "<QtdShp><QtdShpExChrg><SpecialServiceType>HE</SpecialServiceType><LocalSpecialServiceType>XCH</LocalSpecialServiceType></QtdShpExChrg></QtdShp>" : ''; //danger goods type
							$duty_tax = (isset($general_settings['a2z_dhlexpress_duty_payment']) && ($general_settings['a2z_dhlexpress_duty_payment'] == "S") && ($dutiable == "Y")) ? "<QtdShp><QtdShpExChrg><SpecialServiceType>DD</SpecialServiceType></QtdShpExChrg></QtdShp>" : "";
							$xmlRequest =  file_get_contents(dirname(__FILE__) . '/controllors/xml/rate.xml');
							
							$pay_con = $value['a2z_dhlexpress_country'];

							if (isset($general_settings['a2z_dhlexpress_pay_con']) && $general_settings['a2z_dhlexpress_pay_con'] == "R") {
								$pay_con = $order_data['shipping']['country'];
							}elseif (isset($general_settings['a2z_dhlexpress_pay_con']) && $general_settings['a2z_dhlexpress_pay_con'] == "C") {
								if (isset($general_settings['a2z_dhlexpress_cus_pay_con']) && !empty($general_settings['a2z_dhlexpress_cus_pay_con'])) {
									$pay_con = $general_settings['a2z_dhlexpress_cus_pay_con'];
								}
							}
							$xmlRequest = str_replace('{mesage_time}', $mesage_time, $xmlRequest);
							$xmlRequest = str_replace('{siteid}', $value['a2z_dhlexpress_site_id'], $xmlRequest);
							$xmlRequest = str_replace('{pwd}', $value['a2z_dhlexpress_site_pwd'], $xmlRequest);
							$xmlRequest = str_replace('{base_co}', $value['a2z_dhlexpress_country'], $xmlRequest);
							$xmlRequest = str_replace('{pay_con}', $pay_con, $xmlRequest);
							$xmlRequest = str_replace('{org_pos}', $orgin_postalcode_or_city, $xmlRequest);
							$xmlRequest = str_replace('{mail_date}', $message_date, $xmlRequest);
							$xmlRequest = str_replace('{dim_unit}', $dim_unit, $xmlRequest);
							$xmlRequest = str_replace('{weight_unit}', $weight_unit, $xmlRequest);
							$xmlRequest = str_replace('{pieces}', $pieces, $xmlRequest);
							$xmlRequest = str_replace('{fetch_accountrates}', $fetch_accountrates, $xmlRequest);
							$xmlRequest = str_replace('{is_dutiable}', $dutiable, $xmlRequest);
							$xmlRequest = str_replace('{additional_insurance_details}', '', $xmlRequest);
							$xmlRequest = str_replace('{insurance_details}', $insurance_details, $xmlRequest);
							$xmlRequest = str_replace('{danger_goods}', $danger_goods, $xmlRequest);
							$xmlRequest = str_replace('{duty_tax}', $duty_tax, $xmlRequest);
							$xmlRequest = str_replace('{customerAddressIso}', $order_data['shipping']['country'], $xmlRequest);
							$xmlRequest = str_replace('{destination_postcode_city}', $destination_postcode_city, $xmlRequest);
							$xmlRequest = str_replace('{dutiable_content}', $dutiable_content, $xmlRequest);

							$request_url = (isset($general_settings['a2z_dhlexpress_test']) && $general_settings['a2z_dhlexpress_test'] != 'yes') ? 'https://xmlpi-ea.dhl.com/XMLShippingServlet?isUTF8Support=true' : 'https://xmlpitest-ea.dhl.com/XMLShippingServlet?isUTF8Support=true';
							$result = wp_remote_post($request_url, array(
								'method' => 'POST',
								'timeout' => 70,
								'body' => $xmlRequest,
								'sslverify'   => true
							));

							libxml_use_internal_errors(true);
							if (is_array($result) && isset($result['body'])) {
								@$xml = simplexml_load_string(utf8_encode($result['body']));
							}
						}

						if ($xml && (!empty($xml->GetQuoteResponse->BkgDetails->QtdShp) || isset($xml->products)) ) {
							$rate = $quotes = array();
							if (isset($xml->GetQuoteResponse->BkgDetails->QtdShp)) {
								$quotes = $xml->GetQuoteResponse->BkgDetails->QtdShp;
							} elseif ($xml->products) {
								$quotes = $xml->products;
							}
							if (empty($quotes)) {
								update_option('hit_dhl_status_'.$order_id, "No service data found.");
								return;
							}
							$rate = array();
							foreach ($quotes as $quote) {
								$rate_code = (string) (isset($quote->GlobalProductCode) ? $quote->GlobalProductCode : $quote->productCode);
								$rate_cost = $quote_cur_code = "";
								if (isset($quote->totalPrice)) {
									$price_info = $quote->totalPrice;
									$price_types = array_column($price_info, "currencyType");
									if (array_search( 'BILLC', $price_types ) !== false) {
										$price_index = array_search( 'BILLC', $price_types );
										if (isset($price_info[$price_index]->price) && isset($price_info[$price_index]->priceCurrency)) {
											$rate_cost = $price_info[$price_index]->price;
											$quote_cur_code = $price_info[$price_index]->priceCurrency;
										}
									} 
									if ((array_search( 'PULCL', $price_types ) !== false) && empty($quote_cur_code)) {
										$price_index = array_search( 'PULCL', $price_types );
										if (isset($price_info[$price_index]->price) && isset($price_info[$price_index]->priceCurrency)) {
											$rate_cost = $price_info[$price_index]->price;
											$quote_cur_code = $price_info[$price_index]->priceCurrency;
										}
									}
									if ((array_search( 'BASEC', $price_types ) !== false) && empty($quote_cur_code)) {
										$price_index = array_search( 'BASEC', $price_types );
										if (isset($price_info[$price_index]->price) && isset($price_info[$price_index]->priceCurrency)) {
											$rate_cost = $price_info[$price_index]->price;
											$quote_cur_code = $price_info[$price_index]->priceCurrency;
										}
									}
								} else {
									$rate_cost = (float)((string) $quote->ShippingCharge);
									$quote_cur_code = (string)$quote->CurrencyCode;
								}

								if (isset($general_settings['a2z_dhlexpress_excul_tax']) && $general_settings['a2z_dhlexpress_excul_tax'] == "yes") {
									$rate_tax = isset($quote->TotalTaxAmount) ? (float)((string) $quote->TotalTaxAmount) : 0;
									if (!empty($rate_tax) && $rate_tax > 0) {
										$rate_cost -= $rate_tax;
									}
								}

								$quote_cur_code = (string)$quote->CurrencyCode;

								if ($general_settings['a2z_dhlexpress_currency'] != $quote_cur_code) {
									if (isset($quote->QtdSInAdCur)) {
										foreach ($quote->QtdSInAdCur as $c => $con) {
											$con_curr_code = (string)$con->CurrencyCode;
											if (isset($con_curr_code) && $con_curr_code == $general_settings['a2z_dhlexpress_currency']) {
												$rate_cost = (float)(string)$con->TotalAmount;
											}
										}
									}
								}

								if ($general_settings['a2z_dhlexpress_currency'] != get_option('woocommerce_currency')) {
									if (isset($general_settings['a2z_dhlexpress_auto_con_rate']) && $general_settings['a2z_dhlexpress_auto_con_rate'] == "yes") {
										$get_ex_rate = get_option('a2z_dhl_ex_rate'.$key, '');
										$get_ex_rate = !empty($get_ex_rate) ? $get_ex_rate : array();
										$exchange_rate = ( !empty($get_ex_rate) && isset($get_ex_rate['ex_rate']) ) ? $get_ex_rate['ex_rate'] : 0;
									}else{
										$exchange_rate = $value['a2z_dhlexpress_con_rate'];
									}
									if ($exchange_rate && $exchange_rate > 0) {
										$rate_cost /= $exchange_rate;
									}
								}

								if (!empty($general_settings['a2z_dhlexpress_carrier_adj_percentage'][$rate_code])) {
									$rate_cost += $rate_cost * ($general_settings['a2z_dhlexpress_carrier_adj_percentage'][$rate_code] / 100);
								}
								if (!empty($general_settings['a2z_dhlexpress_carrier_adj'][$rate_code])) {
									$rate_cost += $general_settings['a2z_dhlexpress_carrier_adj'][$rate_code];
								}
								$rate[$rate_code] = $rate_cost;
								// $etd_time = '';
								// if (isset($quote->DeliveryDate) && isset($quote->DeliveryTime)) {

								// 	$formated_date = DateTime::createFromFormat('Y-m-d h:i:s', (string)$quote->DeliveryDate->DlvyDateTime);
								// 	$etd_date = $formated_date->format('d/m/Y');
								// 	$etd = apply_filters('hitstacks_dhlexpres_delivery_date', " (Etd.Delivery " . $etd_date . ")", $etd_date, $etd_time);
								// }
							}

							$shipping_rates[$get_rates_for] = $rate;
							update_option('hit_dhl_order_rates_'.$order_id, json_encode($shipping_rates));
						} elseif (isset($xml->Response->Status->Condition->ConditionData)) {
							$err = (string)$xml->Response->Status->Condition->ConditionData;
							update_option('hit_dhl_status_'.$order_id, $err);
						} elseif (isset($xml->additionalDetails)) {
							$err = "";
							if (is_array($xml->additionalDetails)) {
								foreach ($xml->additionalDetails as $key => $err_msg) {
									$err .= $err_msg;
								}
							} else {
								$err = $xml->additionalDetails;
							}
							update_option('hit_dhl_status_'.$order_id, $err);
						} elseif (isset($xml->detail)) {
							$err = $xml->detail;
							update_option('hit_dhl_status_'.$order_id, $err);
						} else {
							update_option('hit_dhl_status_'.$order_id, "Unknown issue found.");
						}
				    }
		    	}

		    	if (  isset( $_POST['hit_dhl_create_label']) ) {
		    		$create_shipment_for = sanitize_text_field($_POST['hit_dhl_create_label']);
		    		if (!isset($_POST['hit_dhl_express_service_code_'.$create_shipment_for])) {
		    			update_option('hit_dhl_status_'.$order_id, "Select any service and continue.");
		    			return;
		    		}
		        	$service_code = sanitize_text_field($_POST['hit_dhl_express_service_code_'.$create_shipment_for]);
		        	$ship_content = !empty($_POST['hit_dhl_shipment_content_'.$create_shipment_for]) ? sanitize_text_field($_POST['hit_dhl_shipment_content_'.$create_shipment_for]) : 'Shipment Content';
		        	$hit_dhl_export_reason = !empty($_POST['hit_dhl_export_reason_'.$create_shipment_for]) ? sanitize_text_field($_POST['hit_dhl_export_reason_'.$create_shipment_for]) : 'P';
		        	$hit_dhl_duty_type = !empty($_POST['hit_dhl_duty_type_'.$create_shipment_for]) ? sanitize_text_field($_POST['hit_dhl_duty_type_'.$create_shipment_for]) : 'S';
		        	$hit_dhl_pickup_type = !empty($_POST['hit_dhl_pickup_type_'.$create_shipment_for]) ? sanitize_text_field($_POST['hit_dhl_pickup_type_'.$create_shipment_for]) : 'S';
					$pickup_mode = (isset($_POST['hit_dhl_add_pickup_'.$create_shipment_for]) && $_POST['hit_dhl_add_pickup_'.$create_shipment_for]) ? 'auto' : 'manual';

				   $order = wc_get_order( $order_id );
			       if($order){
		       		$order_data = $order->get_data();
			       		$order_id = $order_data['id'];
			       		$order_currency = $order_data['currency'];

			       		$custom_packs = [];
			        	if (isset($_POST['hits_dhl_add_cus_pack_'.$create_shipment_for])) {
							if (!isset($_POST['hits_dhl_pack_weight_'.$create_shipment_for])) {
								update_option('hit_dhl_status_'.$order_id, "Need packs for custom packing.");
			    				return;
							}
							foreach ($_POST['hits_dhl_pack_weight_'.$create_shipment_for] as $key => $pack_heights) {
								$curr_pack = [];
								$curr_pack['GroupNumber'] = $key+1;
								$curr_pack['GroupPackageCount'] = $key+1;
								$curr_pack['Weight'] = [
									"Value" => isset($_POST['hits_dhl_pack_weight_'.$create_shipment_for][$key]) ? $_POST['hits_dhl_pack_weight_'.$create_shipment_for][$key] : 0,
									"Units" => isset($_POST['hits_dhl_pack_weg_unit_'.$create_shipment_for][$key]) ? $_POST['hits_dhl_pack_weg_unit_'.$create_shipment_for][$key] : "KG"
								];
								if (isset($_POST['hits_dhl_pack_height_'.$create_shipment_for][$key]) && isset($_POST['hits_dhl_pack_length_'.$create_shipment_for][$key]) && isset($_POST['hits_dhl_pack_width_'.$create_shipment_for][$key])) {
									if (!empty($_POST['hits_dhl_pack_height_'.$create_shipment_for][$key]) && !empty($_POST['hits_dhl_pack_length_'.$create_shipment_for][$key]) && !empty($_POST['hits_dhl_pack_width_'.$create_shipment_for][$key])) {
										$curr_pack['Dimensions'] = [
											"Length" => $_POST['hits_dhl_pack_length_'.$create_shipment_for][$key],
											"Width" => $_POST['hits_dhl_pack_width_'.$create_shipment_for][$key],
											"Height" => $_POST['hits_dhl_pack_height_'.$create_shipment_for][$key],
											"Units" =>$_POST['hits_dhl_pack_dim_unit_'.$create_shipment_for][$key]
										];
									}
								}
								$curr_pack['InsuredValue'] = [
									"Amount" => (isset($_POST['hits_dhl_pack_cost_'.$create_shipment_for][$key]) && !empty($_POST['hits_dhl_pack_cost_'.$create_shipment_for][$key])) ? $_POST['hits_dhl_pack_cost_'.$create_shipment_for][$key] : 1,
									"Currency" => $order_currency
								];
								$curr_pack['packed_products'] = [];
								$curr_pack['package_id'] = "Custom_Pack_".$key;
								$curr_pack['packtype'] = "BOX";
								$custom_packs[] = $curr_pack;
							}
						}

						$shipping_arr = (isset($order_data['shipping']['first_name']) && $order_data['shipping']['first_name'] != "") ? $order_data['shipping'] : $order_data['billing'];
						$order_shipping_first_name = $shipping_arr['first_name'];
						$order_shipping_last_name = $shipping_arr['last_name'];
						$order_shipping_company = empty($shipping_arr['company']) ? $shipping_arr['first_name'] :  $shipping_arr['company'];
						$order_shipping_address_1 = $shipping_arr['address_1'];
						$order_shipping_address_2 = $shipping_arr['address_2'];
						$order_shipping_city = $shipping_arr['city'];
						$order_shipping_state = $shipping_arr['state'];
						$order_shipping_postcode = $shipping_arr['postcode'];
						$order_shipping_country = $shipping_arr['country'];
						$order_shipping_phone = $order_data['billing']['phone'];
						$order_shipping_email = $order_data['billing']['email'];

			       		// $order_shipping_first_name = $order_data['shipping']['first_name'];
						// $order_shipping_last_name = $order_data['shipping']['last_name'];
						// $order_shipping_company = empty($order_data['shipping']['company']) ? $order_data['shipping']['first_name'] :  $order_data['shipping']['company'];
						// $order_shipping_address_1 = $order_data['shipping']['address_1'];
						// $order_shipping_address_2 = $order_data['shipping']['address_2'];
						// $order_shipping_city = $order_data['shipping']['city'];
						// $order_shipping_state = $order_data['shipping']['state'];
						// $order_shipping_postcode = $order_data['shipping']['postcode'];
						// $order_shipping_country = $order_data['shipping']['country'];
						// $order_shipping_phone = $order_data['billing']['phone'];
						// $order_shipping_email = $order_data['billing']['email'];

						$items = $order->get_items();
						$pack_products = array();
						$general_settings = get_option('a2z_dhl_main_settings',array());

						foreach ( $items as $item ) {
							$product_data = $item->get_data();
						    $product = array();
						    $product['product_name'] = str_replace('"', '', $product_data['name']);
						    $product['product_quantity'] = $product_data['quantity'];
						   	$product['product_id'] = $product_data['product_id'];

						   	if ($this->hpos_enabled) {
							    $hpos_prod_data = wc_get_product($product_data['product_id']);
							    $saved_cc = $hpos_prod_data->get_meta("hits_dhl_cc");
							    $saved_cc_inb = $hpos_prod_data->get_meta("hits_dhl_cc_inb");
							    $saved_desc = $hpos_prod_data->get_meta("hits_dhl_desc");
								$dgs_contentid = $hpos_prod_data->get_meta("hits_dhl_danger_good_content_id");
								$dgs_label_des = $hpos_prod_data->get_meta("hits_dhl_danger_good_label_description");
								$dgs_uncode = $hpos_prod_data->get_meta("hits_dhl_danger_good_un_code");
							} else {
							   	$saved_cc = get_post_meta( $product_data['product_id'], 'hits_dhl_cc', true);
							   	$saved_cc_inb = get_post_meta( $product_data['product_id'], 'hits_dhl_cc_inb', true);
							   	$saved_desc = get_post_meta( $product_data['product_id'], 'hits_dhl_desc', true);
								$dgs_contentid = get_post_meta( $product_data['product_id'], 'hits_dhl_danger_good_content_id', true);
								$dgs_label_des = get_post_meta( $product_data['product_id'], 'hits_dhl_danger_good_label_description', true);
								$dgs_uncode = get_post_meta( $product_data['product_id'], 'hits_dhl_danger_good_un_code', true);
							}
							if(!empty($saved_cc)){
								$product['commodity_code'] = $saved_cc;
							}
							if(!empty($saved_cc_inb)){
								$product['commodity_code_inb'] = apply_filters("a2z_dhlexpress_cc_inb", $saved_cc_inb, $product_data['product_id'], $order_shipping_country);
							}
							if(!empty($saved_desc)){
								$product['invoice_desc'] = $saved_desc;
							}
							if(!empty($dgs_contentid)){
								$product['danger_good_contentid'] = $dgs_contentid;
							}
							if(!empty($dgs_label_des)){
								$product['danger_good_label_desci'] = $dgs_label_des;
							}
							if(!empty($dgs_uncode)){
								$product['danger_good_uncode'] = $dgs_uncode;
							}
							
						    $product_variation_id = $item->get_variation_id();
						    if(empty($product_variation_id)){
						    	$getproduct = wc_get_product( $product_data['product_id'] );
						    }else{
						    	$getproduct = wc_get_product( $product_variation_id );
						    	if ($this->hpos_enabled) {
								    $prod_variation_desc = $getproduct->get_meta("hit_dhl_prod_variation_desc");
								} else {
									$prod_variation_desc = get_post_meta( $product_variation_id, 'hit_dhl_prod_variation_desc', true );
								}
								if (!empty($prod_variation_desc)) {
									$product['invoice_desc'] = $prod_variation_desc;
								}
						    }
						    
						    $skip = apply_filters("a2z_dhlexpress_skip_sku_from_label", false, $getproduct->get_sku());
							if($skip){
								continue;
							}
						    $woo_weight_unit = get_option('woocommerce_weight_unit');
							$woo_dimension_unit = get_option('woocommerce_dimension_unit');

							$dhl_mod_weight_unit = $dhl_mod_dim_unit = '';

							if(!empty($general_settings['a2z_dhlexpress_weight_unit']) && $general_settings['a2z_dhlexpress_weight_unit'] == 'KG_CM')
							{
								$dhl_mod_weight_unit = 'kg';
								$dhl_mod_dim_unit = 'cm';
							}elseif(!empty($general_settings['a2z_dhlexpress_weight_unit']) && $general_settings['a2z_dhlexpress_weight_unit'] == 'LB_IN')
							{
								$dhl_mod_weight_unit = 'lbs';
								$dhl_mod_dim_unit = 'in';
							}
							else
							{
								$dhl_mod_weight_unit = 'kg';
								$dhl_mod_dim_unit = 'cm';
							}

							$product['sku'] =  $getproduct->get_sku();
							$product['price'] = (isset($product_data['total']) && isset($product_data['quantity'])) ? number_format(($product_data['total'] / $product_data['quantity']), 2) : 0;
							

						    if ($woo_dimension_unit != $dhl_mod_dim_unit) {
					    	$prod_width = $getproduct->get_width();
					    	$prod_height = $getproduct->get_height();
					    	$prod_depth = $getproduct->get_length();
					    	//wc_get_dimension( $dimension, $to_unit, $from_unit );
					    	$product['width'] =  (!empty($prod_width) && $prod_width > 0) ? round(wc_get_dimension( $prod_width, $dhl_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;
					    	$product['height'] = (!empty($prod_height) && $prod_height > 0) ? round(wc_get_dimension( $prod_height, $dhl_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;
							$product['depth'] =  (!empty($prod_depth) && $prod_depth > 0) ? round(wc_get_dimension( $prod_depth, $dhl_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;

						    }else {
						    	$product['width'] = $getproduct->get_width();
						    	$product['height'] = $getproduct->get_height();
						    	$product['depth'] = $getproduct->get_length();
						    }
						    
						    if ($woo_weight_unit != $dhl_mod_weight_unit) {
						    	$prod_weight = $getproduct->get_weight();
								
						    	$product['weight'] =  (!empty($prod_weight) && $prod_weight > 0) ? round(wc_get_dimension( $prod_weight, $dhl_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;
						    }else{
						    	$product['weight'] = $getproduct->get_weight();
							}

						    $pack_products[] = $product;
						    
						}
						if (empty($pack_products)) {
							update_option('hit_dhl_status_'.$order_id, 'No product data found.');
							return;
						}
						$custom_settings = array();
						$custom_settings['default'] = array(
											'a2z_dhlexpress_api_type' => isset($general_settings['a2z_dhlexpress_api_type']) ? $general_settings['a2z_dhlexpress_api_type'] : "",
											'a2z_dhlexpress_site_id' => $general_settings['a2z_dhlexpress_site_id'],
											'a2z_dhlexpress_site_pwd' => $general_settings['a2z_dhlexpress_site_pwd'],
											'a2z_dhlexpress_acc_no' => $general_settings['a2z_dhlexpress_acc_no'],
											'a2z_dhlexpress_import_no' => $general_settings['a2z_dhlexpress_import_no'],
											'a2z_dhlexpress_shipper_name' => $general_settings['a2z_dhlexpress_shipper_name'],
											'a2z_dhlexpress_company' => $general_settings['a2z_dhlexpress_company'],
											'a2z_dhlexpress_mob_num' => $general_settings['a2z_dhlexpress_mob_num'],
											'a2z_dhlexpress_email' => $general_settings['a2z_dhlexpress_email'],
											'a2z_dhlexpress_address1' => $general_settings['a2z_dhlexpress_address1'],
											'a2z_dhlexpress_address2' => $general_settings['a2z_dhlexpress_address2'],
											'a2z_dhlexpress_city' => $general_settings['a2z_dhlexpress_city'],
											'a2z_dhlexpress_state' => $general_settings['a2z_dhlexpress_state'],
											'a2z_dhlexpress_zip' => $general_settings['a2z_dhlexpress_zip'],
											'a2z_dhlexpress_country' => $general_settings['a2z_dhlexpress_country'],
											'a2z_dhlexpress_gstin' => $general_settings['a2z_dhlexpress_gstin'],
											'a2z_dhlexpress_con_rate' => $general_settings['a2z_dhlexpress_con_rate'],
											'service_code' => $service_code,
											'a2z_dhlexpress_label_email' => $general_settings['a2z_dhlexpress_label_email'],
											'a2z_dhlexpress_sig_img_url' => isset($general_settings['a2z_dhlexpress_sig_img_url']) ? $general_settings['a2z_dhlexpress_sig_img_url'] : ""
										);
						$vendor_settings = array();
						if(isset($general_settings['a2z_dhlexpress_v_enable']) && $general_settings['a2z_dhlexpress_v_enable'] == 'yes' && isset($general_settings['a2z_dhlexpress_v_labels']) && $general_settings['a2z_dhlexpress_v_labels'] == 'yes'){
						// Multi Vendor Enabled
						foreach ($pack_products as $key => $value) {
							$product_id = $value['product_id'];
							if ($this->hpos_enabled) {
							    $hpos_prod_data = wc_get_product($product_id);
							    $dhl_account = $hpos_prod_data->get_meta("dhl_express_address");
							} else {
								$dhl_account = get_post_meta($product_id,'dhl_express_address', true);
							}
							if(empty($dhl_account) || $dhl_account == 'default'){
								$dhl_account = 'default';
								if (!isset($vendor_settings[$dhl_account])) {
									$vendor_settings[$dhl_account] = $custom_settings['default'];
								}
								
								$vendor_settings[$dhl_account]['products'][] = $value;
							}

							if($dhl_account != 'default'){
								$user_account = get_post_meta($dhl_account,'a2z_dhl_vendor_settings', true);
								$user_account = empty($user_account) ? array() : $user_account;
								if(!empty($user_account)){
									if(!isset($vendor_settings[$dhl_account])){

										$vendor_settings[$dhl_account] = $custom_settings['default'];
										
									if($user_account['a2z_dhlexpress_site_id'] != '' && $user_account['a2z_dhlexpress_site_pwd'] != '' && $user_account['a2z_dhlexpress_acc_no'] != ''){
										$vendor_settings[$dhl_account]['a2z_dhlexpress_api_type'] = isset($user_account['a2z_dhlexpress_api_type']) ? $user_account['a2z_dhlexpress_api_type'] : "";
										$vendor_settings[$dhl_account]['a2z_dhlexpress_site_id'] = $user_account['a2z_dhlexpress_site_id'];

										if($user_account['a2z_dhlexpress_site_pwd'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_site_pwd'] = $user_account['a2z_dhlexpress_site_pwd'];
										}

										if($user_account['a2z_dhlexpress_acc_no'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_acc_no'] = $user_account['a2z_dhlexpress_acc_no'];
										}

										$vendor_settings[$dhl_account]['a2z_dhlexpress_import_no'] = !empty($user_account['a2z_dhlexpress_import_no']) ? $user_account['a2z_dhlexpress_import_no'] : '';
										
									}

									if ($user_account['a2z_dhlexpress_address1'] != '' && $user_account['a2z_dhlexpress_city'] != '' && $user_account['a2z_dhlexpress_state'] != '' && $user_account['a2z_dhlexpress_zip'] != '' && $user_account['a2z_dhlexpress_country'] != '' && $user_account['a2z_dhlexpress_shipper_name'] != '') {
										
										if($user_account['a2z_dhlexpress_shipper_name'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_shipper_name'] = $user_account['a2z_dhlexpress_shipper_name'];
										}

										if($user_account['a2z_dhlexpress_company'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_company'] = $user_account['a2z_dhlexpress_company'];
										}

										if($user_account['a2z_dhlexpress_mob_num'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_mob_num'] = $user_account['a2z_dhlexpress_mob_num'];
										}

										if($user_account['a2z_dhlexpress_email'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_email'] = $user_account['a2z_dhlexpress_email'];
										}

										if ($user_account['a2z_dhlexpress_address1'] != '') {
											$vendor_settings[$dhl_account]['a2z_dhlexpress_address1'] = $user_account['a2z_dhlexpress_address1'];
										}

										$vendor_settings[$dhl_account]['a2z_dhlexpress_address2'] = $user_account['a2z_dhlexpress_address2'];
										
										if($user_account['a2z_dhlexpress_city'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_city'] = $user_account['a2z_dhlexpress_city'];
										}

										if($user_account['a2z_dhlexpress_state'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_state'] = $user_account['a2z_dhlexpress_state'];
										}

										if($user_account['a2z_dhlexpress_zip'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_zip'] = $user_account['a2z_dhlexpress_zip'];
										}

										if($user_account['a2z_dhlexpress_country'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_country'] = $user_account['a2z_dhlexpress_country'];
										}

										$vendor_settings[$dhl_account]['a2z_dhlexpress_gstin'] = $user_account['a2z_dhlexpress_gstin'];
										$vendor_settings[$dhl_account]['a2z_dhlexpress_con_rate'] = $user_account['a2z_dhlexpress_con_rate'];

									}
										
										if (isset($user_account['a2z_dhlexpress_sig_img_url']) && !empty($user_account['a2z_dhlexpress_sig_img_url'])) {
											$vendor_settings[$dhl_account]['a2z_dhlexpress_sig_img_url'] = $user_account['a2z_dhlexpress_sig_img_url'];
										}
										if(isset($general_settings['a2z_dhlexpress_v_email']) && $general_settings['a2z_dhlexpress_v_email'] == 'yes'){
											$user_dat = get_userdata($dhl_account);
											$vendor_settings[$dhl_account]['a2z_dhlexpress_label_email'] = $user_dat->data->user_email;
										}
										

										if($order_data['shipping']['country'] != $vendor_settings[$dhl_account]['a2z_dhlexpress_country']){
											$vendor_settings[$dhl_account]['service_code'] = empty($service_code) ? $user_account['a2z_dhlexpress_def_inter'] : $service_code;
										}else{
											$vendor_settings[$dhl_account]['service_code'] = empty($service_code) ? $user_account['a2z_dhlexpress_def_dom'] : $service_code;
										}
									}
									$vendor_settings[$dhl_account]['products'][] = $value;
								}
							}

						}

					}

					if(empty($vendor_settings)){
						$custom_settings['default']['products'] = $pack_products;
					}else{
						$custom_settings = $vendor_settings;
					}
					if(!empty($general_settings) && isset($general_settings['a2z_dhlexpress_integration_key']) && isset($custom_settings[$create_shipment_for])){
						$mode = 'live';
						if(isset($general_settings['a2z_dhlexpress_test']) && $general_settings['a2z_dhlexpress_test']== 'yes'){
							$mode = 'test';
						}

						$execution = 'manual';
						
						$boxes_to_shipo = array();
						if (isset($general_settings['a2z_dhlexpress_packing_type']) && $general_settings['a2z_dhlexpress_packing_type'] == "box") {
							if (isset($general_settings['a2z_dhlexpress_boxes']) && !empty($general_settings['a2z_dhlexpress_boxes'])) {
								foreach ($general_settings['a2z_dhlexpress_boxes'] as $box) {
									if ($box['enabled'] != 1) {
										continue;
									}else {
										$boxes_to_shipo[] = $box;
									}
								}
							}
						}

						global $dhl_core;
						$frm_curr = get_option('woocommerce_currency');
						$to_curr = isset($dhl_core[$custom_settings[$create_shipment_for]['a2z_dhlexpress_country']]) ? $dhl_core[$custom_settings[$create_shipment_for]['a2z_dhlexpress_country']]['currency'] : '';
						$curr_con_rate = ( isset($custom_settings[$create_shipment_for]['a2z_dhlexpress_con_rate']) && !empty($custom_settings[$create_shipment_for]['a2z_dhlexpress_con_rate']) ) ? $custom_settings[$create_shipment_for]['a2z_dhlexpress_con_rate'] : 0;

						if (!empty($frm_curr) && !empty($to_curr) && ($frm_curr != $to_curr) ) {
							if (isset($general_settings['a2z_dhlexpress_auto_con_rate']) && $general_settings['a2z_dhlexpress_auto_con_rate'] == "yes") {
								$current_date = date('m-d-Y', time());
								$ex_rate_data = get_option('a2z_dhl_ex_rate'.$create_shipment_for);
								$ex_rate_data = !empty($ex_rate_data) ? $ex_rate_data : array();
								if (empty($ex_rate_data) || (isset($ex_rate_data['date']) && $ex_rate_data['date'] != $current_date) ) {
									if (isset($custom_settings[$create_shipment_for]['a2z_dhlexpress_country']) && !empty($custom_settings[$create_shipment_for]['a2z_dhlexpress_country']) && isset($general_settings['a2z_dhlexpress_integration_key']) && !empty($general_settings['a2z_dhlexpress_integration_key'])) {
													
										$ex_rate_Request = json_encode(array('integrated_key' => $general_settings['a2z_dhlexpress_integration_key'],
															'from_curr' => $frm_curr,
															'to_curr' => $to_curr));

										$ex_rate_url = "https://app.myshipi.com/get_exchange_rate.php";
										$ex_rate_response = wp_remote_post( $ex_rate_url , array(
														'method'      => 'POST',
														'timeout'     => 45,
														'redirection' => 5,
														'httpversion' => '1.0',
														'blocking'    => true,
														'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
														'body'        => $ex_rate_Request,
														'sslverify'   => true
														)
													);

										$ex_rate_result = ( is_array($ex_rate_response) && isset($ex_rate_response['body'])) ? json_decode($ex_rate_response['body'], true) : array();

										if ( !empty($ex_rate_result) && isset($ex_rate_result['ex_rate']) && $ex_rate_result['ex_rate'] != "Not Found" ) {
											$ex_rate_result['date'] = $current_date;
											update_option('a2z_dhl_ex_rate'.$create_shipment_for, $ex_rate_result);
										}else {
											if (!empty($ex_rate_data)) {
												$ex_rate_data['date'] = $current_date;
												update_option('a2z_dhl_ex_rate'.$create_shipment_for, $ex_rate_data);
											}
										}
									}
								}
								$get_ex_rate = get_option('a2z_dhl_ex_rate'.$create_shipment_for, '');
								$get_ex_rate = !empty($get_ex_rate) ? $get_ex_rate : array();
								$curr_con_rate = ( !empty($get_ex_rate) && isset($get_ex_rate['ex_rate']) ) ? $get_ex_rate['ex_rate'] : 0;
							}
						}

						$c_codes = [];

						foreach($custom_settings[$create_shipment_for]['products'] as $prod_to_shipo_key => $prod_to_shipo){
							if ($this->hpos_enabled) {
							    $hpos_prod_data = wc_get_product($prod_to_shipo['product_id']);
							    $saved_cc = $hpos_prod_data->get_meta("hits_dhl_cc");
							} else {
								$saved_cc = get_post_meta( $prod_to_shipo['product_id'], 'hits_dhl_cc', true);
							}
							if(!empty($saved_cc)){
								$c_codes[] = $saved_cc;
							}

							if (!empty($frm_curr) && !empty($to_curr) && ($frm_curr != $to_curr) ) {
								if ($curr_con_rate > 0 && apply_filters("hit_do_conversion_while_label_generation", true, $order_shipping_country)) {
									$custom_settings[$create_shipment_for]['products'][$prod_to_shipo_key]['price'] = (float)$prod_to_shipo['price'] * $curr_con_rate;
								}
							}
						}
						$insurance = apply_filters("hitshipo_ins_ship", $general_settings['a2z_dhlexpress_insure'], $create_shipment_for, $order);
						$insurance_value = apply_filters("hitshipo_ins_val_ship", 0, $create_shipment_for, $order);
						
						$data = array();
						$data['integrated_key'] = $general_settings['a2z_dhlexpress_integration_key'];
						$data['order_id'] = $order_id;
						$data['exec_type'] = $execution;
						$data['mode'] = $mode;
						$data['carrier_type'] = 'dhl';
						$data['meta'] = array(
							"api_type" => (isset($custom_settings[$create_shipment_for]['a2z_dhlexpress_api_type']) && !empty($custom_settings[$create_shipment_for]['a2z_dhlexpress_api_type'])) ? $custom_settings[$create_shipment_for]['a2z_dhlexpress_api_type'] : "SOAP",
							"site_id" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_site_id'],
							"password"  => $custom_settings[$create_shipment_for]['a2z_dhlexpress_site_pwd'],
							"accountnum" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_acc_no'],
							"t_company" => $order_shipping_company,
							"t_address1" => str_replace('"', '', $order_shipping_address_1),
							"t_address2" => str_replace('"', '', $order_shipping_address_2),
							"t_city" => $order_shipping_city,
							"t_state" => $order_shipping_state,
							"t_postal" => $order_shipping_postcode,
							"t_country" => $order_shipping_country,
							"t_name" => $order_shipping_first_name . ' '. $order_shipping_last_name,
							"t_phone" => $order_shipping_phone,
							"t_email" => $order_shipping_email,
							"t_gstin" => apply_filters("hitshipo_dhlexpress_receiver_vat", "", $order),
							"dutiable" => $hit_dhl_duty_type,
							"insurance" => $insurance,
							"cus_ins_val" => $insurance_value,
							"danger_goods_item" => isset($general_settings['a2z_dhlexpress_dgs']) ? $general_settings['a2z_dhlexpress_dgs'] : 'no',
							"pack_this" => "Y",
							"products" => apply_filters("hitshipo_prods_to_ship", $custom_settings[$create_shipment_for]['products'], $order, $create_shipment_for),
							"pack_algorithm" => $general_settings['a2z_dhlexpress_packing_type'],
							"boxes" => $boxes_to_shipo,
							"max_weight" => $general_settings['a2z_dhlexpress_max_weight'],
							"plt" => ($general_settings['a2z_dhlexpress_ppt'] == 'yes') ? "Y" : "N",
							"airway_bill" => ($general_settings['a2z_dhlexpress_aabill'] == 'yes') ? "Y" : "N",
							"sd" => ($general_settings['a2z_dhlexpress_sat'] == 'yes') ? "Y" : "N",
							"cod" => ($general_settings['a2z_dhlexpress_cod'] == 'yes') ? "Y" : "N",
							"service_code" => $custom_settings[$create_shipment_for]['service_code'],
							"shipment_content" => $ship_content,
							"email_alert" => ( isset($general_settings['a2z_dhlexpress_email_alert']) && ($general_settings['a2z_dhlexpress_email_alert'] == 'yes') ) ? "Y" : "N",
							"s_company" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_company'],
							"s_address1" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_address1'],
							"s_address2" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_address2'],
							"s_city" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_city'],
							"s_state" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_state'],
							"s_postal" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_zip'],
							"s_country" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_country'],
							"gstin" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_gstin'],
							"s_name" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_shipper_name'],
							"s_phone" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_mob_num'],
							"s_email" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_email'],
							"label_size" => $general_settings['a2z_dhlexpress_print_size'],
							"sent_email_to" => $custom_settings[$create_shipment_for]['a2z_dhlexpress_label_email'],
							"sig_img_url" => isset($custom_settings[$create_shipment_for]['a2z_dhlexpress_sig_img_url']) ? $custom_settings[$create_shipment_for]['a2z_dhlexpress_sig_img_url'] : "",
							"pic_exec_type" => $pickup_mode,
			                "pic_loc_type" => (isset($general_settings['a2z_dhlexpress_pickup_loc_type']) ? $general_settings['a2z_dhlexpress_pickup_loc_type'] : ''),
				            "pic_pac_loc" => (isset($general_settings['a2z_dhlexpress_pickup_pac_loc']) ? $general_settings['a2z_dhlexpress_pickup_pac_loc'] : ''),
				            "pic_contact_per" => (isset($general_settings['a2z_dhlexpress_pickup_per_name']) ? $general_settings['a2z_dhlexpress_pickup_per_name'] : ''),
				            "pic_contact_no" => (isset($general_settings['a2z_dhlexpress_pickup_per_contact_no']) ? $general_settings['a2z_dhlexpress_pickup_per_contact_no'] : ''),
				            "pic_door_to" => (isset($general_settings['a2z_dhlexpress_pickup_door_to']) ? $general_settings['a2z_dhlexpress_pickup_door_to'] : ''),
				            "pic_type" => ($hit_dhl_pickup_type != "S" ? "A" : "S"),
				            "pic_days_after" => ($hit_dhl_pickup_type == "S" ? 0 : $hit_dhl_pickup_type),
				            "pic_open_time" => (isset($general_settings['a2z_dhlexpress_pickup_open_time']) ? $general_settings['a2z_dhlexpress_pickup_open_time'] : ''),
				            "pic_close_time" => (isset($general_settings['a2z_dhlexpress_pickup_close_time']) ? $general_settings['a2z_dhlexpress_pickup_close_time'] : ''),
				            "pic_mail_date" => date('c'),
		    				"pic_date" => date("Y-m-d"),
		    				"payment_con" => (isset($general_settings['a2z_dhlexpress_pay_con']) ? $general_settings['a2z_dhlexpress_pay_con'] : 'S'),
							"cus_payment_con" => (isset($general_settings['a2z_dhlexpress_cus_pay_con']) ? $general_settings['a2z_dhlexpress_cus_pay_con'] : ''),
							"translation" => ( (isset($general_settings['a2z_dhlexpress_translation']) && $general_settings['a2z_dhlexpress_translation'] == "yes" ) ? 'Y' : 'N'),
							"translation_key" => (isset($general_settings['a2z_dhlexpress_translation_key']) ? $general_settings['a2z_dhlexpress_translation_key'] : ''),
							"commodity_code" => $c_codes,
							"ship_price" => isset($order_data['shipping_total']) ? $order_data['shipping_total'] : 0,
							"order_total" => isset($order_data['total']) ? $order_data['total'] : 0,
							"order_total_tax" => isset($order_data['total_tax']) ? $order_data['total_tax'] : 0,
							"inv_type" => isset($general_settings['a2z_dhlexpress_inv_type']) ? $general_settings['a2z_dhlexpress_inv_type'] : "",
							"inv_temp_type" => isset($general_settings['a2z_dhlexpress_inv_temp_type']) ? $general_settings['a2z_dhlexpress_inv_temp_type'] : "",
							"label" => $create_shipment_for,
							"export_reason" => $hit_dhl_export_reason,
							"custom" => apply_filters("hit_dhl_custom_values", array(), $_POST),
							"custom_packs" => $custom_packs
						);
			
						//Manual Shipment
						$manual_ship_url = "https://app.myshipi.com/label_api/create_shipment.php";
						$response = wp_remote_post( $manual_ship_url , array(
							'method'      => 'POST',
							'timeout'     => 45,
							'redirection' => 5,
							'httpversion' => '1.0',
							'blocking'    => true,
							'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
							'body'        => json_encode($data),
							'sslverify'   => true
							)
						);

						$output = (is_array($response) && isset($response['body'])) ? json_decode($response['body'],true) : [];
						
							if($output){
								if(isset($output['status']) || isset($output['pic_status'])){

									if(isset($output['status']) && $output['status'] != 'success'){
										   update_option('hit_dhl_status_'.$order_id, $output['status']);

									}else if(isset($output['status']) && $output['status'] == 'success'){
										$output['user_id'] = $create_shipment_for;
										$val = get_option('hit_dhl_values_'.$order_id, []);
										$result_arr = array();
										if(!empty($val)){
											$result_arr = json_decode($val, true);
										}
										
										$result_arr[] = $output;

										update_option('hit_dhl_values_'.$order_id, json_encode(apply_filters("shipi_dhl_express_save_output", $result_arr, $order_id)));

										
									}
									if (isset($output['pic_status']) && $output['pic_status'] != 'success') {
										$pic_res['status'] = "failed";
										update_option('hit_dhl_pickup_values_'.$order_id, json_encode($pic_res));
									}elseif (isset($output['pic_status']) && $output['pic_status'] == 'success') {
										$pic_res['confirm_no'] = $output['confirm_no'];
										$pic_res['ready_time'] = $output['ready_time'];
										$pic_res['pickup_date'] = $output['pickup_date'];
										$pic_res['status'] = "success";

										update_option('hit_dhl_pickup_values_'.$order_id, json_encode($pic_res));
									}
								}else{
									update_option('hit_dhl_status_'.$order_id, 'Site not Connected with Shipi. Contact Shipi Team.');
																		}
							}else{
								update_option('hit_dhl_status_'.$order_id, 'Site not Connected with Shipi. Contact Shipi Team.');
							}
						}	
			       }
		        }
		    }

		    // Save the data of the Meta field
			public function hit_create_dhl_return_shipping( $order_id ) {
				if ($this->hpos_enabled) {
	 		        if ('shop_order' !== Automattic\WooCommerce\Utilities\OrderUtil::get_order_type($order_id)) {
	 		            return;
	 		        }
	 		    } else {
			    	$post = get_post($order_id);
			    	if($post->post_type !='shop_order' ){
			    		return;
			    	}
			    }
		    	
		    	if (  isset( $_POST[ 'hit_dhl_reset' ] ) ) {
		    		delete_option('hit_dhl_return_values_'.$order_id);
				}
				
				if (  isset( $_POST[ 'hit_dhl_return_reset' ] ) ) {
		    		delete_option('hit_dhl_return_values_'.$order_id);
		    	}
				$general_settings = get_option('a2z_dhl_main_settings',array());

		    	if (  isset( $_POST['hit_dhl_create_return_label']) && isset( $_POST[ 'hit_dhl_express_return_service_code' ] ) ) {
		           $service_code = sanitize_text_field($_POST['hit_dhl_express_return_service_code']);
		           $ship_content = 'Return Shipment';
		           $enabled_products = isset($_POST['return_products']) ? sanitize_meta('return_products',$_POST['return_products'], 'post') : array();
		           $qty_products = isset($_POST['qty_products']) ? sanitize_meta('qty_products',$_POST['qty_products'], 'post') : array();
		           $order = wc_get_order( $order_id );

		   //      	if (isset($enabled_products['ID'])) {
					// 	unset($enabled_products['ID']);
					// }
					// if (isset($enabled_products['filter'])) {
					// 	unset($enabled_products['filter']);
					// }
					// if (isset($qty_products['ID'])) {
					// 	unset($qty_products['ID']);
					// }
					// if (isset($qty_products['filter'])) {
					// 	unset($qty_products['filter']);
					// }

			       if($order && !empty($enabled_products)){

			       		$order_data = $order->get_data();
			       		$order_id = $order_data['id'];
			       		$order_currency = $order_data['currency'];

						   $shipping_arr = (isset($order_data['shipping']['first_name']) && $order_data['shipping']['first_name'] != "") ? $order_data['shipping'] : $order_data['billing'];
						   $order_shipping_first_name = $shipping_arr['first_name'];
						   $order_shipping_last_name = $shipping_arr['last_name'];
						   $order_shipping_company = empty($shipping_arr['company']) ? $shipping_arr['first_name'] :  $shipping_arr['company'];
						   $order_shipping_address_1 = $shipping_arr['address_1'];
						   $order_shipping_address_2 = $shipping_arr['address_2'];
						   $order_shipping_city = $shipping_arr['city'];
						   $order_shipping_state = $shipping_arr['state'];
						   $order_shipping_postcode = $shipping_arr['postcode'];
						   $order_shipping_country = $shipping_arr['country'];
						   $order_shipping_phone = $order_data['billing']['phone'];
						   $order_shipping_email = $order_data['billing']['email'];

			       		// $order_shipping_first_name = $order_data['shipping']['first_name'];
						// $order_shipping_last_name = $order_data['shipping']['last_name'];
						// $order_shipping_company = empty($order_data['shipping']['company']) ? $order_data['shipping']['first_name'] :  $order_data['shipping']['company'];
						// $order_shipping_address_1 = $order_data['shipping']['address_1'];
						// $order_shipping_address_2 = $order_data['shipping']['address_2'];
						// $order_shipping_city = $order_data['shipping']['city'];
						// $order_shipping_state = $order_data['shipping']['state'];
						// $order_shipping_postcode = $order_data['shipping']['postcode'];
						// $order_shipping_country = $order_data['shipping']['country'];
						// $order_shipping_phone = $order_data['billing']['phone'];
						// $order_shipping_email = $order_data['billing']['email'];

						$items = $order->get_items();
						$pack_products = array();

						foreach ( $items as $item ) {
							$product_data = $item->get_data();
						    $product = array();
						    $product['product_name'] = str_replace('"', '', $product_data['name']);
						    $product['product_quantity'] = $product_data['quantity'];
						    $product['product_id'] = $product_data['product_id'];

						    if ($this->hpos_enabled) {
							    $hpos_prod_data = wc_get_product($product_data['product_id']);
							    $saved_cc = $hpos_prod_data->get_meta("hits_dhl_cc");
							    $saved_cc_inb = $hpos_prod_data->get_meta("hits_dhl_cc_inb");
							    $saved_desc = $hpos_prod_data->get_meta("hits_dhl_desc");
							} else {
							    $saved_cc = get_post_meta( $product_data['product_id'], 'hits_dhl_cc', true);
							    $saved_cc_inb = get_post_meta( $product_data['product_id'], 'hits_dhl_cc_inb', true);
							    $saved_desc = get_post_meta( $product_data['product_id'], 'hits_dhl_desc', true);
							}
							if(!empty($saved_cc)){
								$product['commodity_code'] = $saved_cc;
							}
							if(!empty($saved_cc_inb)){
								$product['commodity_code_inb'] = apply_filters("a2z_dhlexpress_cc_inb", $saved_cc_inb, $product_data['product_id'], $order_shipping_country);
							}
							if(!empty($saved_desc)){
								$product['invoice_desc'] = $saved_desc;
							}

						    $product_variation_id = $item->get_variation_id();
						    $product_id = $product_data['product_id'];
						    if(empty($product_variation_id) || $product_variation_id == 0){
						    	$getproduct = wc_get_product( $product_data['product_id'] );
						    }else{
						    	$getproduct = wc_get_product( $product_variation_id );
						    	$product_id = $product_variation_id;
						    	if ($this->hpos_enabled) {
								    $hpos_prod_data = wc_get_product($product_variation_id);
								    $prod_variation_desc = $hpos_prod_data->get_meta("hit_dhl_prod_variation_desc");
								} else {
									$prod_variation_desc = get_post_meta( $product_variation_id, 'hit_dhl_prod_variation_desc', true );
								}
								if (!empty($prod_variation_desc)) {
									$product['invoice_desc'] = $prod_variation_desc;
								}
						    }
						    
						    if(!in_array($product_id, $enabled_products)){
						    	continue;
						    }else{
						    	if($qty_products[$product_id] == 0){
						    		continue;
						    	}else{
						    		$product['product_quantity'] = $qty_products[$product_id];

						    	}
						    }
						    $woo_weight_unit = get_option('woocommerce_weight_unit');
							$woo_dimension_unit = get_option('woocommerce_dimension_unit');

							$dhl_mod_weight_unit = $dhl_mod_dim_unit = '';

							if(!empty($general_settings['a2z_dhlexpress_weight_unit']) && $general_settings['a2z_dhlexpress_weight_unit'] == 'KG_CM')
							{
								$dhl_mod_weight_unit = 'kg';
								$dhl_mod_dim_unit = 'cm';
							}elseif(!empty($general_settings['a2z_dhlexpress_weight_unit']) && $general_settings['a2z_dhlexpress_weight_unit'] == 'LB_IN')
							{
								$dhl_mod_weight_unit = 'lbs';
								$dhl_mod_dim_unit = 'in';
							}
							else
							{
								$dhl_mod_weight_unit = 'kg';
								$dhl_mod_dim_unit = 'cm';
							}

							$product['sku'] =  $getproduct->get_sku();
							$product['price'] = (isset($product_data['total']) && isset($product_data['quantity'])) ? number_format(($product_data['total'] / $product_data['quantity']), 2) : 0;
							

						    if ($woo_dimension_unit != $dhl_mod_dim_unit) {
					    	$prod_width = $getproduct->get_width();
					    	$prod_height = $getproduct->get_height();
					    	$prod_depth = $getproduct->get_length();

					    	//wc_get_dimension( $dimension, $to_unit, $from_unit );							
					    	$product['width'] = (!empty($prod_width) && $prod_width > 0) ? round(wc_get_dimension( $prod_width, $dhl_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;
					    	$product['height'] = (!empty($prod_height) && $prod_height >0)  ? round(wc_get_dimension( $prod_height, $dhl_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;
							$product['depth'] = (!empty($prod_depth) && $prod_depth > 0) ? round(wc_get_dimension( $prod_depth, $dhl_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;

						    }else {
						    	$product['width'] = $getproduct->get_width();
						    	$product['height'] = $getproduct->get_height();
						    	$product['depth'] = $getproduct->get_length();
						    }
						    
						    if ($woo_weight_unit != $dhl_mod_weight_unit) {
						    	$prod_weight = $getproduct->get_weight();
								
						    	$product['weight'] = (!empty($prod_weight) && $prod_weight > 0) ? round(wc_get_dimension( $prod_weight, $dhl_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;
						    }else{
						    	$product['weight'] = $getproduct->get_weight();
							}

						    $pack_products[] = $product;
						    
						}

						
						if(!empty($general_settings) && isset($general_settings['a2z_dhlexpress_integration_key']) && isset($general_settings['a2z_dhlexpress_import_no'])){
							$mode = 'live';
							if(isset($general_settings['a2z_dhlexpress_test']) && $general_settings['a2z_dhlexpress_test']== 'yes'){
								$mode = 'test';
							}

							$execution = 'manual';
							
							$boxes_to_shipo = array();
							if (isset($general_settings['a2z_dhlexpress_packing_type']) && $general_settings['a2z_dhlexpress_packing_type'] == "box") {
								if (isset($general_settings['a2z_dhlexpress_boxes']) && !empty($general_settings['a2z_dhlexpress_boxes'])) {
									foreach ($general_settings['a2z_dhlexpress_boxes'] as $box) {
										if ($box['enabled'] != 1) {
											continue;
										}else {
											$boxes_to_shipo[] = $box;
										}
									}
								}
							}

							global $dhl_core;
							$frm_curr = get_option('woocommerce_currency');
							$to_curr = isset($dhl_core[$general_settings['a2z_dhlexpress_country']]) ? $dhl_core[$general_settings['a2z_dhlexpress_country']]['currency'] : '';
							$curr_con_rate = ( isset($general_settings['a2z_dhlexpress_con_rate']) && !empty($general_settings['a2z_dhlexpress_con_rate']) ) ? $general_settings['a2z_dhlexpress_con_rate'] : 0;

							if (!empty($frm_curr) && !empty($to_curr) && ($frm_curr != $to_curr) ) {
								if (isset($general_settings['a2z_dhlexpress_auto_con_rate']) && $general_settings['a2z_dhlexpress_auto_con_rate'] == "yes") {
									$key = "default";
									$current_date = date('m-d-Y', time());
									$ex_rate_data = get_option('a2z_dhl_ex_rate'.$key);
									$ex_rate_data = !empty($ex_rate_data) ? $ex_rate_data : array();
									if (empty($ex_rate_data) || (isset($ex_rate_data['date']) && $ex_rate_data['date'] != $current_date) ) {
										if (isset($general_settings['a2z_dhlexpress_country']) && !empty($general_settings['a2z_dhlexpress_country']) && isset($general_settings['a2z_dhlexpress_integration_key']) && !empty($general_settings['a2z_dhlexpress_integration_key'])) {
														
											$ex_rate_Request = json_encode(array('integrated_key' => $general_settings['a2z_dhlexpress_integration_key'],
																'from_curr' => $frm_curr,
																'to_curr' => $to_curr));

											$ex_rate_url = "https://app.myshipi.com/get_exchange_rate.php";
											$ex_rate_response = wp_remote_post( $ex_rate_url , array(
															'method'      => 'POST',
															'timeout'     => 45,
															'redirection' => 5,
															'httpversion' => '1.0',
															'blocking'    => true,
															'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
															'body'        => $ex_rate_Request,
															'sslverify'   => true
															)
														);

											$ex_rate_result = ( is_array($ex_rate_response) && isset($ex_rate_response['body'])) ? json_decode($ex_rate_response['body'], true) : array();

											if ( !empty($ex_rate_result) && isset($ex_rate_result['ex_rate']) && $ex_rate_result['ex_rate'] != "Not Found" ) {
												$ex_rate_result['date'] = $current_date;
												update_option('a2z_dhl_ex_rate'.$key, $ex_rate_result);
											}else {
												if (!empty($ex_rate_data)) {
													$ex_rate_data['date'] = $current_date;
													update_option('a2z_dhl_ex_rate'.$key, $ex_rate_data);
												}
											}
										}
									}
									$get_ex_rate = get_option('a2z_dhl_ex_rate'.$key, '');
									$get_ex_rate = !empty($get_ex_rate) ? $get_ex_rate : array();
									$curr_con_rate = ( !empty($get_ex_rate) && isset($get_ex_rate['ex_rate']) ) ? $get_ex_rate['ex_rate'] : 0;
								}
							}

							$c_codes = [];

							foreach($pack_products as $prod_to_shipo_key => $prod_to_shipo){
								if ($this->hpos_enabled) {
								    $hpos_prod_data = wc_get_product($prod_to_shipo['product_id']);
								    $saved_cc = $hpos_prod_data->get_meta("hits_dhl_cc");
								} else {
									$saved_cc = get_post_meta( $prod_to_shipo['product_id'], 'hits_dhl_cc', true);
								}
								if(!empty($saved_cc)){
									$c_codes[] = $saved_cc;
								}
								if (!empty($frm_curr) && !empty($to_curr) && ($frm_curr != $to_curr) ) {
									if ($curr_con_rate > 0 && apply_filters("hit_do_conversion_while_label_generation", true, $order_shipping_country)) {
										$pack_products[$prod_to_shipo_key]['price'] = $prod_to_shipo['price'] * $curr_con_rate;
									}
								}
							}
							
							$data = array();
							$data['integrated_key'] = $general_settings['a2z_dhlexpress_integration_key'];
							$data['order_id'] = $order_id;
							$data['exec_type'] = $execution;
							$data['mode'] = $mode;
							$data['carrier_type'] = 'dhl';
							$data['meta'] = array(
								"api_type" => (isset($general_settings['a2z_dhlexpress_api_type']) && !empty($general_settings['a2z_dhlexpress_api_type'])) ? $general_settings['a2z_dhlexpress_api_type'] : "SOAP",
								"site_id" => $general_settings['a2z_dhlexpress_site_id'],
								"password"  => $general_settings['a2z_dhlexpress_site_pwd'],
								"accountnum" => $general_settings['a2z_dhlexpress_import_no'],
								"s_company" => $order_shipping_company,
								"s_address1" => $order_shipping_address_1,
								"s_address2" => $order_shipping_address_2,
								"s_city" => $order_shipping_city,
								"s_state" => $order_shipping_state,
								"s_postal" => $order_shipping_postcode,
								"s_country" => $order_shipping_country,
								"s_name" => $order_shipping_first_name . ' '. $order_shipping_last_name,
								"s_phone" => $order_shipping_phone,
								"s_email" => $order_shipping_email,
								"dutiable" => $general_settings['a2z_dhlexpress_duty_payment'],
								"insurance" => "no",
								"pack_this" => "Y",
								"products" => apply_filters("hitshipo_prods_to_ship", $pack_products, $order, "default"),
								"pack_algorithm" => $general_settings['a2z_dhlexpress_packing_type'],
								"boxes" => $boxes_to_shipo,
								"max_weight" => $general_settings['a2z_dhlexpress_max_weight'],
								"plt" => (isset($general_settings['a2z_dhlexpress_ppt']) && $general_settings['a2z_dhlexpress_ppt'] == 'yes') ? "Y" : "N",
								"airway_bill" => ($general_settings['a2z_dhlexpress_aabill'] == 'yes') ? "Y" : "N",
								"sd" => "N",
								"cod" => "N",
								"service_code" => $service_code,
								"danger_goods_item" => isset($general_settings['a2z_dhlexpress_dgs']) ? $general_settings['a2z_dhlexpress_dgs'] : 'no',
								"shipment_content" => $ship_content,
								"email_alert" => ( isset($general_settings['a2z_dhlexpress_email_alert']) && ($general_settings['a2z_dhlexpress_email_alert'] == 'yes') ) ? "Y" : "N",
								"t_company" => $general_settings['a2z_dhlexpress_company'],
								"t_address1" => str_replace('"', '', $general_settings['a2z_dhlexpress_address1']),
								"t_address2" => str_replace('"', '', $general_settings['a2z_dhlexpress_address2']),
								"t_city" => $general_settings['a2z_dhlexpress_city'],
								"t_state" => $general_settings['a2z_dhlexpress_state'],
								"t_postal" => $general_settings['a2z_dhlexpress_zip'],
								"t_country" => $general_settings['a2z_dhlexpress_country'],
								"gstin" => $general_settings['a2z_dhlexpress_gstin'],
								"t_name" => $general_settings['a2z_dhlexpress_shipper_name'],
								"t_phone" => $general_settings['a2z_dhlexpress_mob_num'],
								"t_email" => $general_settings['a2z_dhlexpress_email'],
								"label_size" => $general_settings['a2z_dhlexpress_print_size'],
								"sent_email_to" => $general_settings['a2z_dhlexpress_label_email'],
								"sig_img_url" => isset($general_settings['a2z_dhlexpress_sig_img_url']) ? $general_settings['a2z_dhlexpress_sig_img_url'] : "",
								"pic_exec_type" => 'manual',
				                "pic_loc_type" => (isset($general_settings['a2z_dhlexpress_pickup_loc_type']) ? $general_settings['a2z_dhlexpress_pickup_loc_type'] : ''),
					            "pic_pac_loc" => (isset($general_settings['a2z_dhlexpress_pickup_pac_loc']) ? $general_settings['a2z_dhlexpress_pickup_pac_loc'] : ''),
					            "pic_contact_per" => (isset($general_settings['a2z_dhlexpress_pickup_per_name']) ? $general_settings['a2z_dhlexpress_pickup_per_name'] : ''),
					            "pic_contact_no" => (isset($general_settings['a2z_dhlexpress_pickup_per_contact_no']) ? $general_settings['a2z_dhlexpress_pickup_per_contact_no'] : ''),
					            "pic_door_to" => (isset($general_settings['a2z_dhlexpress_pickup_door_to']) ? $general_settings['a2z_dhlexpress_pickup_door_to'] : ''),
					            "pic_type" => (isset($general_settings['a2z_dhlexpress_pickup_type']) ? $general_settings['a2z_dhlexpress_pickup_type'] : ''),
					            "pic_days_after" => (isset($general_settings['a2z_dhlexpress_pickup_date']) ? $general_settings['a2z_dhlexpress_pickup_date'] : ''),
					            "pic_open_time" => (isset($general_settings['a2z_dhlexpress_pickup_open_time']) ? $general_settings['a2z_dhlexpress_pickup_open_time'] : ''),
					            "pic_close_time" => (isset($general_settings['a2z_dhlexpress_pickup_close_time']) ? $general_settings['a2z_dhlexpress_pickup_close_time'] : ''),
					            "pic_mail_date" => date('c'),
			    				"pic_date" => date("Y-m-d"),
			    				"payment_con" => (isset($general_settings['a2z_dhlexpress_pay_con']) ? $general_settings['a2z_dhlexpress_pay_con'] : 'S'),
								"cus_payment_con" => (isset($general_settings['a2z_dhlexpress_cus_pay_con']) ? $general_settings['a2z_dhlexpress_cus_pay_con'] : ''),
								"translation" => ( (isset($general_settings['a2z_dhlexpress_translation']) && $general_settings['a2z_dhlexpress_translation'] == "yes" ) ? 'Y' : 'N'),
								"translation_key" => (isset($general_settings['a2z_dhlexpress_translation_key']) ? $general_settings['a2z_dhlexpress_translation_key'] : ''),
								"commodity_code" => $c_codes,
								"ship_price" => isset($order_data['shipping_total']) ? $order_data['shipping_total'] : 0,
								"order_total" => isset($order_data['total']) ? $order_data['total'] : 0,
								"order_total_tax" => isset($order_data['total_tax']) ? $order_data['total_tax'] : 0,
								"inv_type" => isset($general_settings['a2z_dhlexpress_inv_type']) ? $general_settings['a2z_dhlexpress_inv_type'] : "",
								"inv_temp_type" => isset($general_settings['a2z_dhlexpress_inv_temp_type']) ? $general_settings['a2z_dhlexpress_inv_temp_type'] : "",
								"return"=>'1',
								"export_reason" => 'R'
							);

							//Return Shipment
							$return_label_url = "https://app.myshipi.com/label_api/create_shipment.php";
							$response = wp_remote_post( $return_label_url , array(
								'method'      => 'POST',
								'timeout'     => 45,
								'redirection' => 5,
								'httpversion' => '1.0',
								'blocking'    => true,
								'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
								'body'        => json_encode($data),
								'sslverify'   => true
								)
							);
							
								$output = (is_array($response) && isset($response['body'])) ? json_decode($response['body'],true) : [];
								
								if($output){
									if(isset($output['status']) || isset($output['pic_status'])){

										if(isset($output['status']) && $output['status'] != 'success'){
											   update_option('hit_dhl_return_status_'.$order_id, $output['status']);
										}else if(isset($output['status']) && $output['status'] == 'success'){
											update_option('hit_dhl_return_values_'.$order_id, json_encode($output));
											if (!empty($output['tracking_num'])) {
												$track = $output['tracking_num'];
												$order = wc_get_order($order_id);
												$order->update_meta_data( apply_filters('a2z_rtracking_id_meta_name', 'a2z_rtracking_num'), $track );
					    						$order->save();
												// update_post_meta($order_id, apply_filters('a2z_rtracking_id_meta_name', 'a2z_rtracking_num'), $track);
											}
										}
									}else{
										update_option('hit_dhl_return_status_'.$order_id, 'Site not Connected with Shipi. Contact Shipi Team.');
									}
								}else{
									update_option('hit_dhl_return_status_'.$order_id, 'Site not Connected with Shipi. Contact Shipi Team.');
								}
						}	
			       }else{
			       		update_option('hit_dhl_return_status_'.$order_id, 'Enable atleast one product to create Return label.');
			       }
		        }
		    }

		    private function get_vendors_on_order($general_settings = [], $pack_products = [], $order = [], $service_code = "")
		    {
		    	$custom_settings = array();
				$custom_settings['default'] = array(
					'a2z_dhlexpress_api_type' => isset($general_settings['a2z_dhlexpress_api_type'])? $general_settings['a2z_dhlexpress_api_type'] : '',
					'a2z_dhlexpress_site_id' => isset($general_settings['a2z_dhlexpress_site_id'])? $general_settings['a2z_dhlexpress_site_id'] : '',
					'a2z_dhlexpress_site_pwd' => isset($general_settings['a2z_dhlexpress_site_pwd'])? $general_settings['a2z_dhlexpress_site_pwd'] : '',
					'a2z_dhlexpress_acc_no' => isset($general_settings['a2z_dhlexpress_acc_no'])? $general_settings['a2z_dhlexpress_acc_no'] : '',
					'a2z_dhlexpress_import_no' => isset($general_settings['a2z_dhlexpress_import_no'])? $general_settings['a2z_dhlexpress_import_no']: '',
					'a2z_dhlexpress_shipper_name' => isset($general_settings['a2z_dhlexpress_shipper_name'])?$general_settings['a2z_dhlexpress_site_pwd'] : '',
					'a2z_dhlexpress_company' => isset($general_settings['a2z_dhlexpress_company'])?$general_settings['a2z_dhlexpress_company'] : '',
					'a2z_dhlexpress_mob_num' => isset($general_settings['a2z_dhlexpress_mob_num'])?$general_settings['a2z_dhlexpress_mob_num'] : '',
					'a2z_dhlexpress_email' => isset($general_settings['a2z_dhlexpress_email'])?$general_settings['a2z_dhlexpress_email'] : '',
					'a2z_dhlexpress_address1' => isset($general_settings['a2z_dhlexpress_address1'])?$general_settings['a2z_dhlexpress_address1'] : '',
					'a2z_dhlexpress_address2' => isset($general_settings['a2z_dhlexpress_address2'])?$general_settings['a2z_dhlexpress_address2'] : '',
					'a2z_dhlexpress_city' => isset($general_settings['a2z_dhlexpress_city'])?$general_settings['a2z_dhlexpress_city'] : '',
					'a2z_dhlexpress_state' => isset($general_settings['a2z_dhlexpress_state'])? $general_settings['a2z_dhlexpress_state']: '',
					'a2z_dhlexpress_zip' => isset($general_settings['a2z_dhlexpress_zip'])?$general_settings['a2z_dhlexpress_zip'] : '',
					'a2z_dhlexpress_country' => isset($general_settings['a2z_dhlexpress_country'])?$general_settings['a2z_dhlexpress_country'] : '',
					'a2z_dhlexpress_gstin' => isset($general_settings['a2z_dhlexpress_gstin'])?$general_settings['a2z_dhlexpress_gstin'] : '',
					'a2z_dhlexpress_con_rate' => isset($general_settings['a2z_dhlexpress_con_rate'])? $general_settings['a2z_dhlexpress_con_rate']: '',
					'a2z_dhlexpress_sig_img_url' => isset($general_settings['a2z_dhlexpress_sig_img_url']) ? $general_settings['a2z_dhlexpress_sig_img_url'] : ""
				);
				$vendor_settings = array();
				if(isset($general_settings['a2z_dhlexpress_v_enable']) && $general_settings['a2z_dhlexpress_v_enable'] == 'yes' && isset($general_settings['a2z_dhlexpress_v_labels']) && $general_settings['a2z_dhlexpress_v_labels'] == 'yes'){
					// Multi Vendor Enabled
					foreach ($pack_products as $key => $value) {
						$product_id = $value['product_id'];
						if ($this->hpos_enabled) {
						    $hpos_prod_data = wc_get_product($product_id);
						    $dhl_account = $hpos_prod_data->get_meta("dhl_express_address");
						} else {
							$dhl_account = get_post_meta($product_id,'dhl_express_address', true);
						}
						if(empty($dhl_account) || $dhl_account == 'default'){
							$dhl_account = 'default';
							if (!isset($vendor_settings[$dhl_account])) {
								$vendor_settings[$dhl_account] = $custom_settings['default'];
							}
							$vendor_settings[$dhl_account]['products'][] = $value;
						}

						if($dhl_account != 'default'){
							$user_account = get_post_meta($dhl_account,'a2z_dhl_vendor_settings', true);
							$user_account = empty($user_account) ? array() : $user_account;
							if(!empty($user_account)){
								if(!isset($vendor_settings[$dhl_account])){
									$vendor_settings[$dhl_account] = $custom_settings['default'];
									if($user_account['a2z_dhlexpress_site_id'] != '' && $user_account['a2z_dhlexpress_site_pwd'] != '' && $user_account['a2z_dhlexpress_acc_no'] != ''){
										$vendor_settings[$dhl_account]['a2z_dhlexpress_api_type'] = isset($user_account['a2z_dhlexpress_api_type']) ? $user_account['a2z_dhlexpress_api_type'] : "";
										$vendor_settings[$dhl_account]['a2z_dhlexpress_site_id'] = $user_account['a2z_dhlexpress_site_id'];
										if($user_account['a2z_dhlexpress_site_pwd'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_site_pwd'] = $user_account['a2z_dhlexpress_site_pwd'];
										}
										if($user_account['a2z_dhlexpress_acc_no'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_acc_no'] = $user_account['a2z_dhlexpress_acc_no'];
										}
										$vendor_settings[$dhl_account]['a2z_dhlexpress_import_no'] = !empty($user_account['a2z_dhlexpress_import_no']) ? $user_account['a2z_dhlexpress_import_no'] : '';
									}

									if ($user_account['a2z_dhlexpress_address1'] != '' && $user_account['a2z_dhlexpress_city'] != '' && $user_account['a2z_dhlexpress_state'] != '' && $user_account['a2z_dhlexpress_zip'] != '' && $user_account['a2z_dhlexpress_country'] != '' && $user_account['a2z_dhlexpress_shipper_name'] != '') {
										if($user_account['a2z_dhlexpress_shipper_name'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_shipper_name'] = $user_account['a2z_dhlexpress_shipper_name'];
										}
										if($user_account['a2z_dhlexpress_company'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_company'] = $user_account['a2z_dhlexpress_company'];
										}
										if($user_account['a2z_dhlexpress_mob_num'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_mob_num'] = $user_account['a2z_dhlexpress_mob_num'];
										}
										if($user_account['a2z_dhlexpress_email'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_email'] = $user_account['a2z_dhlexpress_email'];
										}
										if ($user_account['a2z_dhlexpress_address1'] != '') {
											$vendor_settings[$dhl_account]['a2z_dhlexpress_address1'] = $user_account['a2z_dhlexpress_address1'];
										}
										$vendor_settings[$dhl_account]['a2z_dhlexpress_address2'] = $user_account['a2z_dhlexpress_address2'];
										if($user_account['a2z_dhlexpress_city'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_city'] = $user_account['a2z_dhlexpress_city'];
										}
										if($user_account['a2z_dhlexpress_state'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_state'] = $user_account['a2z_dhlexpress_state'];
										}
										if($user_account['a2z_dhlexpress_zip'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_zip'] = $user_account['a2z_dhlexpress_zip'];
										}
										if($user_account['a2z_dhlexpress_country'] != ''){
											$vendor_settings[$dhl_account]['a2z_dhlexpress_country'] = $user_account['a2z_dhlexpress_country'];
										}
										$vendor_settings[$dhl_account]['a2z_dhlexpress_gstin'] = $user_account['a2z_dhlexpress_gstin'];
										$vendor_settings[$dhl_account]['a2z_dhlexpress_con_rate'] = $user_account['a2z_dhlexpress_con_rate'];
									}

									if (isset($user_account['a2z_dhlexpress_sig_img_url']) && !empty($user_account['a2z_dhlexpress_sig_img_url'])) {
										$vendor_settings[$dhl_account]['a2z_dhlexpress_sig_img_url'] = $user_account['a2z_dhlexpress_sig_img_url'];
									}
									
									if(isset($general_settings['a2z_dhlexpress_v_email']) && $general_settings['a2z_dhlexpress_v_email'] == 'yes'){
										$user_dat = get_userdata($dhl_account);
										$vendor_settings[$dhl_account]['a2z_dhlexpress_label_email'] = $user_dat->data->user_email;
									}
									$order_data = !empty($order) ? $order->get_data() : [];
									if(isset($order_data['shipping']['country']) && $order_data['shipping']['country'] != $vendor_settings[$dhl_account]['a2z_dhlexpress_country']){
										$vendor_settings[$dhl_account]['service_code'] = empty($service_code) ? $user_account['a2z_dhlexpress_def_inter'] : $service_code;
									}else{
										$vendor_settings[$dhl_account]['service_code'] = empty($service_code) ? $user_account['a2z_dhlexpress_def_dom'] : $service_code;
									}
								}
								$vendor_settings[$dhl_account]['products'][] = $value;
							}
						}
					}
				}
				if(empty($vendor_settings)){
					$custom_settings['default']['products'] = $pack_products;
				}else{
					$custom_settings = $vendor_settings;
				}
				return $custom_settings;
		    }

		    private function get_products_on_order($general_settings = [], $order = [])
		    {
		    	$items = $order->get_items();
		    	$order_data = $order->get_data();
				$desination_country = (isset($order_data['shipping']['country']) && $order_data['shipping']['country'] != '') ? $order_data['shipping']['country'] : $order_data['billing']['country'];
				$pack_products = array();
				foreach ( $items as $item ) {
					$product_data = $item->get_data();
					$product = array();
					$product['product_name'] = str_replace('"', '', $product_data['name']);
					$product['product_quantity'] = $product_data['quantity'];
					$product['product_id'] = $product_data['product_id'];

					if ($this->hpos_enabled) {
					    $hpos_prod_data = wc_get_product($product_data['product_id']);
					    $saved_cc = $hpos_prod_data->get_meta("hits_dhl_cc");
					    $saved_cc_inb = $hpos_prod_data->get_meta("hits_dhl_cc_inb");
					    $saved_desc = $hpos_prod_data->get_meta("hits_dhl_desc");
					} else {
						$saved_cc = get_post_meta( $product_data['product_id'], 'hits_dhl_cc', true);
						$saved_cc_inb = get_post_meta( $product_data['product_id'], 'hits_dhl_cc_inb', true);
						$saved_desc = get_post_meta( $product_data['product_id'], 'hits_dhl_desc', true);
					}
					if(!empty($saved_cc)){
						$product['commodity_code'] = $saved_cc;
					}
					if(!empty($saved_cc_inb)){
						$product['commodity_code_inb'] = apply_filters("a2z_dhlexpress_cc_inb", $saved_cc_inb, $product_data['product_id'], $desination_country);
					}
					if(!empty($saved_desc)){
						$product['invoice_desc'] = $saved_desc;
					}

					$product_variation_id = $item->get_variation_id();
					if(empty($product_variation_id)){
						$getproduct = wc_get_product( $product_data['product_id'] );
					}else{
						$getproduct = wc_get_product( $product_variation_id );
						if ($this->hpos_enabled) {
						    $hpos_prod_data = wc_get_product($product_variation_id);
						    $prod_variation_desc = $hpos_prod_data->get_meta("hit_dhl_prod_variation_desc");
						} else {
							$prod_variation_desc = get_post_meta( $product_variation_id, 'hit_dhl_prod_variation_desc', true );
						}
						if (!empty($prod_variation_desc)) {
							$product['invoice_desc'] = $prod_variation_desc;
						}
					}
					if (empty($getproduct)) {
						continue;
					}
					$skip = apply_filters("a2z_dhlexpress_skip_sku_from_label", false, $getproduct->get_sku());
				if($skip){
					continue;
				}
						    
					$woo_weight_unit = get_option('woocommerce_weight_unit');
					$woo_dimension_unit = get_option('woocommerce_dimension_unit');

					$dhl_mod_weight_unit = $dhl_mod_dim_unit = '';

					if(!empty($general_settings['a2z_dhlexpress_weight_unit']) && $general_settings['a2z_dhlexpress_weight_unit'] == 'KG_CM')
					{
						$dhl_mod_weight_unit = 'kg';
						$dhl_mod_dim_unit = 'cm';
					}elseif(!empty($general_settings['a2z_dhlexpress_weight_unit']) && $general_settings['a2z_dhlexpress_weight_unit'] == 'LB_IN'){
						$dhl_mod_weight_unit = 'lbs';
						$dhl_mod_dim_unit = 'in';
					} else {
						$dhl_mod_weight_unit = 'kg';
						$dhl_mod_dim_unit = 'cm';
					}

					$product['sku'] =  $getproduct->get_sku();
					$product['price'] = (isset($product_data['total']) && isset($product_data['quantity'])) ? round(($product_data['total'] / $product_data['quantity']), 2) : 0;
					

					if ($woo_dimension_unit != $dhl_mod_dim_unit) {
						$prod_width = $getproduct->get_width();
						$prod_height = $getproduct->get_height();
						$prod_depth = $getproduct->get_length();
						
						//wc_get_dimension( $dimension, $to_unit, $from_unit );
						$product['width'] = (!empty($prod_width) && $prod_width > 0) ? round(wc_get_dimension( $prod_width, $dhl_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;
						$product['height'] =  (!empty($prod_height) && $prod_height > 0) ? round(wc_get_dimension( $prod_height, $dhl_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;
						$product['depth'] =  (!empty($prod_depth) && $prod_depth > 0) ? round(wc_get_dimension( $prod_depth, $dhl_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;
					}else {
						$product['width'] = $getproduct->get_width();
						$product['height'] = $getproduct->get_height();
						$product['depth'] = $getproduct->get_length();
					}
					if ($woo_weight_unit != $dhl_mod_weight_unit) {
						$prod_weight = $getproduct->get_weight();
						$product['weight'] =  (!empty($prod_weight) && $prod_weight > 0 ) ? round(wc_get_dimension( $prod_weight, $dhl_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;
					}else{
						$product['weight'] = $getproduct->get_weight();
					}
					$pack_products[] = $product;
				}
				return $pack_products;
		    }
		    private function a2z_get_zipcode_or_city($country, $city, $postcode)
			{
				$no_postcode_country = array(
					'AE', 'AF', 'AG', 'AI', 'AL', 'AN', 'AO', 'AW', 'BB', 'BF', 'BH', 'BI', 'BJ', 'BM', 'BO', 'BS', 'BT', 'BW', 'BZ', 'CD', 'CF', 'CG', 'CI', 'CK',
					'CL', 'CM', 'CR', 'CV', 'DJ', 'DM', 'DO', 'EC', 'EG', 'ER', 'ET', 'FJ', 'FK', 'GA', 'GD', 'GH', 'GI', 'GM', 'GN', 'GQ', 'GT', 'GW', 'GY', 'HK', 'HN', 'HT', 'IE', 'IQ', 'IR',
					'JM', 'JO', 'KE', 'KH', 'KI', 'KM', 'KN', 'KP', 'KW', 'KY', 'LA', 'LB', 'LC', 'LK', 'LR', 'LS', 'LY', 'ML', 'MM', 'MO', 'MR', 'MS', 'MT', 'MU', 'MW', 'MZ', 'NA', 'NE', 'NG', 'NI',
					'NP', 'NR', 'NU', 'OM', 'PA', 'PE', 'PF', 'PY', 'QA', 'RW', 'SA', 'SB', 'SC', 'SD', 'SL', 'SN', 'SO', 'SR', 'SS', 'ST', 'SV', 'SY', 'TC', 'TD', 'TG', 'TL', 'TO', 'TT', 'TV', 'TZ',
					'UG', 'UY', 'VC', 'VE', 'VG', 'VN', 'VU', 'WS', 'XA', 'XB', 'XC', 'XE', 'XL', 'XM', 'XN', 'XS', 'YE', 'ZM', 'ZW'
				);

				$postcode_city = !in_array($country, $no_postcode_country) ? $postcode_city = "<Postalcode>{$postcode}</Postalcode>" : '';
				if (!empty($city)) {
					$postcode_city .= "<City>{$city}</City>";
				}
				return $postcode_city;
			}
			public function hit_get_dhl_packages($package, $general_settings, $orderCurrency, $chk = false)
			{
				if (empty($package)) {
					return;
				}
				$sel_algo = isset($general_settings['a2z_dhlexpress_packing_type']) ? $general_settings['a2z_dhlexpress_packing_type'] : "per_item";
				switch ($sel_algo) {
					case 'box':
						return $this->box_shipping($package, $general_settings, $orderCurrency, $chk);
						break;
					case 'weight_based':
						return $this->weight_based_shipping($package, $general_settings, $orderCurrency, $chk);
						break;
					case 'per_item':
					default:
						return $this->per_item_shipping($package, $general_settings, $orderCurrency, $chk);
						break;
				}
			}
			private function weight_based_shipping($package, $general_settings, $orderCurrency, $chk = false)
			{
				// echo '<pre>';
				// print_r($package);
				// die();
				if (!class_exists('WeightPack')) {
					include_once 'controllors/classes/weight_pack/class-hit-weight-packing.php';
				}
				$max_weight = isset($general_settings['a2z_dhlexpress_max_weight']) && $general_settings['a2z_dhlexpress_max_weight'] != ''  ? $general_settings['a2z_dhlexpress_max_weight'] : 10;
				$weight_pack = new WeightPack('pack_ascending');
				$weight_pack->set_max_weight($max_weight);

				$package_total_weight = 0;
				$insured_value = 0;

				$ctr = 0;
				foreach ($package as $item_id => $product_data) {
					$ctr++;

					$chk_qty = $product_data['product_quantity'];
					$prod_wt = (isset($product_data['weight']) && !empty($product_data['weight'])) ? $product_data['weight'] : 0.1;
					$weight_pack->add_item($prod_wt, $product_data, $chk_qty);
				}

				$pack   =   $weight_pack->pack_items();
				$errors =   $pack->get_errors();
				if (!empty($errors)) {
					//do nothing
					return;
				} else {
					$boxes    =   $pack->get_packed_boxes();
					$unpacked_items =   $pack->get_unpacked_items();

					$insured_value        =   0;

					$packages      =   array_merge($boxes, $unpacked_items); // merge items if unpacked are allowed
					$package_count  =   sizeof($packages);
					// get all items to pass if item info in box is not distinguished
					$packable_items =   $weight_pack->get_packable_items();
					$all_items    =   array();
					if (is_array($packable_items)) {
						foreach ($packable_items as $packable_item) {
							$all_items[]    =   $packable_item['data'];
						}
					}
					//pre($packable_items);
					$order_total = '';

					$to_ship  = array();
					$group_id = 1;
					foreach ($packages as $package) {
						$packed_products = array();
						if (isset($package['items'])) {
							foreach ($package['items'] as $key => $value) {
								$insured_value += isset($value['price']) ? $value['price'] : 0;
							}
						}
						$packed_products    =   isset($package['items']) ? $package['items'] : $all_items;
						// Creating package request
						$package_total_weight   = $package['weight'];

						$insurance_array = array(
							'Amount' => $insured_value,
							'Currency' => $orderCurrency
						);

						$group = array(
							'GroupNumber' => $group_id,
							'GroupPackageCount' => 1,
							'Weight' => array(
								'Value' => round($package_total_weight, 3),
								'Units' => (isset($general_settings['weg_dim']) && $general_settings['weg_dim'] === 'yes') ? 'KG' : 'LBS'
							),
							'packed_products' => $packed_products,
						);
						$group['InsuredValue'] = $insurance_array;
						$group['packtype'] = 'BOX';

						$to_ship[] = $group;
						$group_id++;
					}
				}
				return $to_ship;
			}
			private function box_shipping($package, $general_settings, $orderCurrency, $chk = false)
			{
				if (!class_exists('HIT_Boxpack')) {
					include_once 'controllors/classes/hit-box-packing.php';
				}
				$boxpack = new HIT_Boxpack();
				$boxes = isset($general_settings['a2z_dhlexpress_boxes']) ? $general_settings['a2z_dhlexpress_boxes'] : array();
				if (empty($boxes)) {
					return false;
				}
				// $boxes = unserialize($boxes);
				// Define boxes
				foreach ($boxes as $key => $box) {
					if (!$box['enabled']) {
						continue;
					}
					$box['pack_type'] = !empty($box['pack_type']) ? $box['pack_type'] : 'BOX';

					$newbox = $boxpack->add_box($box['length'], $box['width'], $box['height'], $box['box_weight'], $box['pack_type']);

					if (isset($box['id'])) {
						$newbox->set_id(current(explode(':', $box['id'])));
					}

					if ($box['max_weight']) {
						$newbox->set_max_weight($box['max_weight']);
					}

					if ($box['pack_type']) {
						$newbox->set_packtype($box['pack_type']);
					}
				}

				// Add items
				foreach ($package as $item_id => $product_data) {

					if (isset($product_data['weight']) && !empty($product_data['weight'])) {
						$item_weight = round($product_data['weight'] > 0.001 ? $product_data['weight'] : 0.001, 3);
					}

					if (isset($product_data['width']) && isset($product_data['height']) && isset($product_data['depth']) && !empty($product_data['width']) && !empty($product_data['height']) && !empty($product_data['depth'])) {
						$item_dimension = array(
							'Length' => max(1, round($product_data['depth'], 3)),
							'Width' => max(1, round($product_data['width'], 3)),
							'Height' => max(1, round($product_data['height'], 3))
						);
					}

					if (isset($item_weight) && isset($item_dimension)) {

						// $dimensions = array($values['depth'], $values['height'], $values['width']);
						$chk_qty = $product_data['product_quantity'];
						for ($i = 0; $i < $chk_qty; $i++) {
							$boxpack->add_item($item_dimension['Width'], $item_dimension['Height'], $item_dimension['Length'], $item_weight, round($product_data['price']), array(
								'data' => $product_data
							));
						}
					} else {
						//    $this->debug(sprintf(__('Product #%s is missing dimensions. Aborting.', 'wf-shipping-dhl'), $item_id), 'error');
						return;
					}
				}

				// Pack it
				$boxpack->pack();
				$packages = $boxpack->get_packages();
				$to_ship = array();
				$group_id = 1;
				foreach ($packages as $package) {
					if ($package->unpacked === true) {
						//$this->debug('Unpacked Item');
					} else {
						//$this->debug('Packed ' . $package->id);
					}

					$dimensions = array($package->length, $package->width, $package->height);

					sort($dimensions);
					$insurance_array = array(
						'Amount' => round($package->value),
						'Currency' => $orderCurrency
					);


					$group = array(
						'GroupNumber' => $group_id,
						'GroupPackageCount' => 1,
						'Weight' => array(
							'Value' => round($package->weight, 3),
							'Units' => (isset($general_settings['weg_dim']) && $general_settings['weg_dim'] === 'yes') ? 'KG' : 'LBS'
						),
						'Dimensions' => array(
							'Length' => max(1, round($dimensions[2], 3)),
							'Width' => max(1, round($dimensions[1], 3)),
							'Height' => max(1, round($dimensions[0], 3)),
							'Units' => (isset($general_settings['weg_dim']) && $general_settings['weg_dim'] === 'yes') ? 'CM' : 'IN'
						),
						'InsuredValue' => $insurance_array,
						'packed_products' => array(),
						'package_id' => $package->id,
						'packtype' => 'BOX'
					);

					if (!empty($package->packed) && is_array($package->packed)) {
						foreach ($package->packed as $packed) {
							$group['packed_products'][] = $packed->get_meta('data');
						}
					}

					if (!$package->packed) {
						foreach ($package->unpacked as $unpacked) {
							$group['packed_products'][] = $unpacked->get_meta('data');
						}
					}

					$to_ship[] = $group;

					$group_id++;
				}

				return $to_ship;
			}
			private function per_item_shipping($package, $general_settings, $orderCurrency, $chk = false)
			{
				// echo '<pre>';
				// print_r($package);
				// die();
				$to_ship = array();
				$group_id = 1;

				// Get weight of order
				foreach ($package as $item_id => $product_data) {

					$group = array();
					$insurance_array = array(
						'Amount' => round($product_data['price']),
						'Currency' => $orderCurrency
					);
					$dhl_per_item_weight = 0 ;
					if (isset($product_data['weight']) && !empty($product_data['weight'])) {
						$dhl_per_item_weight = round($product_data['weight'] > 0.001 ? $product_data['weight'] : 0.001, 3);
					}

					$group = array(
						'GroupNumber' => $group_id,
						'GroupPackageCount' => 1,
						'Weight' => array(
							'Value' => $dhl_per_item_weight,
							'Units' => (isset($general_settings['a2z_dhlexpress_weight_unit']) && $general_settings['a2z_dhlexpress_weight_unit'] == 'KG_CM') ? 'KG' : 'LBS'
						),
						'packed_products' => $product_data
					);

					if (isset($product_data['width']) && isset($product_data['height']) && isset($product_data['depth']) && !empty($product_data['width']) && !empty($product_data['height']) && !empty($product_data['depth'])) {

						$group['Dimensions'] = array(
							'Length' => max(1, round($product_data['depth'], 3)),
							'Width' => max(1, round($product_data['width'], 3)),
							'Height' => max(1, round($product_data['height'], 3)),
							'Units' => (isset($general_settings['a2z_dhlexpress_weight_unit']) && $general_settings['a2z_dhlexpress_weight_unit'] == 'KG_CM') ? 'CM' : 'IN'
						);
					}

					$group['packtype'] = 'BOX';

					$group['InsuredValue'] = $insurance_array;

					$chk_qty = $product_data['product_quantity'];

					for ($i = 0; $i < $chk_qty; $i++)
						$to_ship[] = $group;

					$group_id++;
				}

				return $to_ship;
			}
			public function a2z_dhl_is_eu_country ($countrycode, $destinationcode) {
				$eu_countrycodes = array(
					'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 
					'ES', 'FI', 'FR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV',
					'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
					'HR', 'GR'

				);
				return(in_array($countrycode, $eu_countrycodes) && in_array($destinationcode, $eu_countrycodes));
			}
			private function getFormatedShipAddr($ven_set=[])
			{
				$ship_addr = [];
				$ship_addr['site_id'] = isset($ven_set['a2z_dhlexpress_site_id']) ? $ven_set['a2z_dhlexpress_site_id'] : "";
				$ship_addr['site_pwd'] = isset($ven_set['a2z_dhlexpress_site_pwd']) ? $ven_set['a2z_dhlexpress_site_pwd'] : "";
				$ship_addr['acc_no'] = isset($ven_set['a2z_dhlexpress_acc_no']) ? $ven_set['a2z_dhlexpress_acc_no'] : "";
				$ship_addr['name'] = isset($ven_set['a2z_dhlexpress_shipper_name']) ? $ven_set['a2z_dhlexpress_shipper_name'] : "";
				$ship_addr['company'] = isset($ven_set['a2z_dhlexpress_company']) ? $ven_set['a2z_dhlexpress_company'] : "";
				$ship_addr['address_1'] = isset($ven_set['a2z_dhlexpress_address1']) ? $ven_set['a2z_dhlexpress_address1'] : "";
				$ship_addr['address_2'] = isset($ven_set['a2z_dhlexpress_address2']) ? $ven_set['a2z_dhlexpress_address2'] : "";
				$ship_addr['city'] = isset($ven_set['a2z_dhlexpress_city']) ? $ven_set['a2z_dhlexpress_city'] : "";
				$ship_addr['postcode'] = isset($ven_set['a2z_dhlexpress_zip']) ? $ven_set['a2z_dhlexpress_zip'] : "";
				$ship_addr['state'] = isset($ven_set['a2z_dhlexpress_state']) ? $ven_set['a2z_dhlexpress_state'] : "";
				$ship_addr['country'] = isset($ven_set['a2z_dhlexpress_country']) ? $ven_set['a2z_dhlexpress_country'] : "";
				$ship_addr['email'] = isset($ven_set['a2z_dhlexpress_email']) ? $ven_set['a2z_dhlexpress_email'] : "";
				$ship_addr['phone'] = isset($ven_set['a2z_dhlexpress_mob_num']) ? $ven_set['a2z_dhlexpress_mob_num'] : "";
				return $ship_addr;
			}
		}

		$dhl_core = array();
		$dhl_core['AD'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['AE'] = array('region' => 'AP', 'currency' =>'AED', 'weight' => 'KG_CM');
		$dhl_core['AF'] = array('region' => 'AP', 'currency' =>'AFN', 'weight' => 'KG_CM');
		$dhl_core['AG'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_core['AI'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_core['AL'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['AM'] = array('region' => 'AP', 'currency' =>'AMD', 'weight' => 'KG_CM');
		$dhl_core['AN'] = array('region' => 'AM', 'currency' =>'ANG', 'weight' => 'KG_CM');
		$dhl_core['AO'] = array('region' => 'AP', 'currency' =>'AOA', 'weight' => 'KG_CM');
		$dhl_core['AR'] = array('region' => 'AM', 'currency' =>'ARS', 'weight' => 'KG_CM');
		$dhl_core['AS'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_core['AT'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['AU'] = array('region' => 'AP', 'currency' =>'AUD', 'weight' => 'KG_CM');
		$dhl_core['AW'] = array('region' => 'AM', 'currency' =>'AWG', 'weight' => 'LB_IN');
		$dhl_core['AZ'] = array('region' => 'AM', 'currency' =>'AZN', 'weight' => 'KG_CM');
		$dhl_core['AZ'] = array('region' => 'AM', 'currency' =>'AZN', 'weight' => 'KG_CM');
		$dhl_core['GB'] = array('region' => 'EU', 'currency' =>'GBP', 'weight' => 'KG_CM');
		$dhl_core['BA'] = array('region' => 'AP', 'currency' =>'BAM', 'weight' => 'KG_CM');
		$dhl_core['BB'] = array('region' => 'AM', 'currency' =>'BBD', 'weight' => 'LB_IN');
		$dhl_core['BD'] = array('region' => 'AP', 'currency' =>'BDT', 'weight' => 'KG_CM');
		$dhl_core['BE'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['BF'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_core['BG'] = array('region' => 'EU', 'currency' =>'BGN', 'weight' => 'KG_CM');
		$dhl_core['BH'] = array('region' => 'AP', 'currency' =>'BHD', 'weight' => 'KG_CM');
		$dhl_core['BI'] = array('region' => 'AP', 'currency' =>'BIF', 'weight' => 'KG_CM');
		$dhl_core['BJ'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_core['BM'] = array('region' => 'AM', 'currency' =>'BMD', 'weight' => 'LB_IN');
		$dhl_core['BN'] = array('region' => 'AP', 'currency' =>'BND', 'weight' => 'KG_CM');
		$dhl_core['BO'] = array('region' => 'AM', 'currency' =>'BOB', 'weight' => 'KG_CM');
		$dhl_core['BR'] = array('region' => 'AM', 'currency' =>'BRL', 'weight' => 'KG_CM');
		$dhl_core['BS'] = array('region' => 'AM', 'currency' =>'BSD', 'weight' => 'LB_IN');
		$dhl_core['BT'] = array('region' => 'AP', 'currency' =>'BTN', 'weight' => 'KG_CM');
		$dhl_core['BW'] = array('region' => 'AP', 'currency' =>'BWP', 'weight' => 'KG_CM');
		$dhl_core['BY'] = array('region' => 'AP', 'currency' =>'BYR', 'weight' => 'KG_CM');
		$dhl_core['BZ'] = array('region' => 'AM', 'currency' =>'BZD', 'weight' => 'KG_CM');
		$dhl_core['CA'] = array('region' => 'AM', 'currency' =>'CAD', 'weight' => 'LB_IN');
		$dhl_core['CF'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
		$dhl_core['CG'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
		$dhl_core['CH'] = array('region' => 'EU', 'currency' =>'CHF', 'weight' => 'KG_CM');
		$dhl_core['CI'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_core['CK'] = array('region' => 'AP', 'currency' =>'NZD', 'weight' => 'KG_CM');
		$dhl_core['CL'] = array('region' => 'AM', 'currency' =>'CLP', 'weight' => 'KG_CM');
		$dhl_core['CM'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
		$dhl_core['CN'] = array('region' => 'AP', 'currency' =>'CNY', 'weight' => 'KG_CM');
		$dhl_core['CO'] = array('region' => 'AM', 'currency' =>'COP', 'weight' => 'KG_CM');
		$dhl_core['CR'] = array('region' => 'AM', 'currency' =>'CRC', 'weight' => 'KG_CM');
		$dhl_core['CU'] = array('region' => 'AM', 'currency' =>'CUC', 'weight' => 'KG_CM');
		$dhl_core['CV'] = array('region' => 'AP', 'currency' =>'CVE', 'weight' => 'KG_CM');
		$dhl_core['CY'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['CZ'] = array('region' => 'EU', 'currency' =>'CZK', 'weight' => 'KG_CM');
		$dhl_core['DE'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['DJ'] = array('region' => 'EU', 'currency' =>'DJF', 'weight' => 'KG_CM');
		$dhl_core['DK'] = array('region' => 'AM', 'currency' =>'DKK', 'weight' => 'KG_CM');
		$dhl_core['DM'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_core['DO'] = array('region' => 'AP', 'currency' =>'DOP', 'weight' => 'LB_IN');
		$dhl_core['DZ'] = array('region' => 'AM', 'currency' =>'DZD', 'weight' => 'KG_CM');
		$dhl_core['EC'] = array('region' => 'EU', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_core['EE'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['EG'] = array('region' => 'AP', 'currency' =>'EGP', 'weight' => 'KG_CM');
		$dhl_core['ER'] = array('region' => 'EU', 'currency' =>'ERN', 'weight' => 'KG_CM');
		$dhl_core['ES'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['ET'] = array('region' => 'AU', 'currency' =>'ETB', 'weight' => 'KG_CM');
		$dhl_core['FI'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['FJ'] = array('region' => 'AP', 'currency' =>'FJD', 'weight' => 'KG_CM');
		$dhl_core['FK'] = array('region' => 'AM', 'currency' =>'GBP', 'weight' => 'KG_CM');
		$dhl_core['FM'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_core['FO'] = array('region' => 'AM', 'currency' =>'DKK', 'weight' => 'KG_CM');
		$dhl_core['FR'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['GA'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
		$dhl_core['GB'] = array('region' => 'EU', 'currency' =>'GBP', 'weight' => 'KG_CM');
		$dhl_core['GD'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_core['GE'] = array('region' => 'AM', 'currency' =>'GEL', 'weight' => 'KG_CM');
		$dhl_core['GF'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['GG'] = array('region' => 'AM', 'currency' =>'GBP', 'weight' => 'KG_CM');
		$dhl_core['GH'] = array('region' => 'AP', 'currency' =>'GHS', 'weight' => 'KG_CM');
		$dhl_core['GI'] = array('region' => 'AM', 'currency' =>'GBP', 'weight' => 'KG_CM');
		$dhl_core['GL'] = array('region' => 'AM', 'currency' =>'DKK', 'weight' => 'KG_CM');
		$dhl_core['GM'] = array('region' => 'AP', 'currency' =>'GMD', 'weight' => 'KG_CM');
		$dhl_core['GN'] = array('region' => 'AP', 'currency' =>'GNF', 'weight' => 'KG_CM');
		$dhl_core['GP'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['GQ'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
		$dhl_core['GR'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['GT'] = array('region' => 'AM', 'currency' =>'GTQ', 'weight' => 'KG_CM');
		$dhl_core['GU'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_core['GW'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_core['GY'] = array('region' => 'AP', 'currency' =>'GYD', 'weight' => 'LB_IN');
		$dhl_core['HK'] = array('region' => 'AM', 'currency' =>'HKD', 'weight' => 'KG_CM');
		$dhl_core['HN'] = array('region' => 'AM', 'currency' =>'HNL', 'weight' => 'KG_CM');
		$dhl_core['HR'] = array('region' => 'AP', 'currency' =>'HRK', 'weight' => 'KG_CM');
		$dhl_core['HT'] = array('region' => 'AM', 'currency' =>'HTG', 'weight' => 'LB_IN');
		$dhl_core['HU'] = array('region' => 'EU', 'currency' =>'HUF', 'weight' => 'KG_CM');
		$dhl_core['IC'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['ID'] = array('region' => 'AP', 'currency' =>'IDR', 'weight' => 'KG_CM');
		$dhl_core['IE'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['IL'] = array('region' => 'AP', 'currency' =>'ILS', 'weight' => 'KG_CM');
		$dhl_core['IN'] = array('region' => 'AP', 'currency' =>'INR', 'weight' => 'KG_CM');
		$dhl_core['IQ'] = array('region' => 'AP', 'currency' =>'IQD', 'weight' => 'KG_CM');
		$dhl_core['IR'] = array('region' => 'AP', 'currency' =>'IRR', 'weight' => 'KG_CM');
		$dhl_core['IS'] = array('region' => 'EU', 'currency' =>'ISK', 'weight' => 'KG_CM');
		$dhl_core['IT'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['JE'] = array('region' => 'AM', 'currency' =>'GBP', 'weight' => 'KG_CM');
		$dhl_core['JM'] = array('region' => 'AM', 'currency' =>'JMD', 'weight' => 'KG_CM');
		$dhl_core['JO'] = array('region' => 'AP', 'currency' =>'JOD', 'weight' => 'KG_CM');
		$dhl_core['JP'] = array('region' => 'AP', 'currency' =>'JPY', 'weight' => 'KG_CM');
		$dhl_core['KE'] = array('region' => 'AP', 'currency' =>'KES', 'weight' => 'KG_CM');
		$dhl_core['KG'] = array('region' => 'AP', 'currency' =>'KGS', 'weight' => 'KG_CM');
		$dhl_core['KH'] = array('region' => 'AP', 'currency' =>'KHR', 'weight' => 'KG_CM');
		$dhl_core['KI'] = array('region' => 'AP', 'currency' =>'AUD', 'weight' => 'KG_CM');
		$dhl_core['KM'] = array('region' => 'AP', 'currency' =>'KMF', 'weight' => 'KG_CM');
		$dhl_core['KN'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_core['KP'] = array('region' => 'AP', 'currency' =>'KPW', 'weight' => 'LB_IN');
		$dhl_core['KR'] = array('region' => 'AP', 'currency' =>'KRW', 'weight' => 'KG_CM');
		$dhl_core['KV'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['KW'] = array('region' => 'AP', 'currency' =>'KWD', 'weight' => 'KG_CM');
		$dhl_core['KY'] = array('region' => 'AM', 'currency' =>'KYD', 'weight' => 'KG_CM');
		$dhl_core['KZ'] = array('region' => 'AP', 'currency' =>'KZF', 'weight' => 'LB_IN');
		$dhl_core['LA'] = array('region' => 'AP', 'currency' =>'LAK', 'weight' => 'KG_CM');
		$dhl_core['LB'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_core['LC'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'KG_CM');
		$dhl_core['LI'] = array('region' => 'AM', 'currency' =>'CHF', 'weight' => 'LB_IN');
		$dhl_core['LK'] = array('region' => 'AP', 'currency' =>'LKR', 'weight' => 'KG_CM');
		$dhl_core['LR'] = array('region' => 'AP', 'currency' =>'LRD', 'weight' => 'KG_CM');
		$dhl_core['LS'] = array('region' => 'AP', 'currency' =>'LSL', 'weight' => 'KG_CM');
		$dhl_core['LT'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['LU'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['LV'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['LY'] = array('region' => 'AP', 'currency' =>'LYD', 'weight' => 'KG_CM');
		$dhl_core['MA'] = array('region' => 'AP', 'currency' =>'MAD', 'weight' => 'KG_CM');
		$dhl_core['MC'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['MD'] = array('region' => 'AP', 'currency' =>'MDL', 'weight' => 'KG_CM');
		$dhl_core['ME'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['MG'] = array('region' => 'AP', 'currency' =>'MGA', 'weight' => 'KG_CM');
		$dhl_core['MH'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_core['MK'] = array('region' => 'AP', 'currency' =>'MKD', 'weight' => 'KG_CM');
		$dhl_core['ML'] = array('region' => 'AP', 'currency' =>'COF', 'weight' => 'KG_CM');
		$dhl_core['MM'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_core['MN'] = array('region' => 'AP', 'currency' =>'MNT', 'weight' => 'KG_CM');
		$dhl_core['MO'] = array('region' => 'AP', 'currency' =>'MOP', 'weight' => 'KG_CM');
		$dhl_core['MP'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_core['MQ'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['MR'] = array('region' => 'AP', 'currency' =>'MRO', 'weight' => 'KG_CM');
		$dhl_core['MS'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_core['MT'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['MU'] = array('region' => 'AP', 'currency' =>'MUR', 'weight' => 'KG_CM');
		$dhl_core['MV'] = array('region' => 'AP', 'currency' =>'MVR', 'weight' => 'KG_CM');
		$dhl_core['MW'] = array('region' => 'AP', 'currency' =>'MWK', 'weight' => 'KG_CM');
		$dhl_core['MX'] = array('region' => 'AM', 'currency' =>'MXN', 'weight' => 'KG_CM');
		$dhl_core['MY'] = array('region' => 'AP', 'currency' =>'MYR', 'weight' => 'KG_CM');
		$dhl_core['MZ'] = array('region' => 'AP', 'currency' =>'MZN', 'weight' => 'KG_CM');
		$dhl_core['NA'] = array('region' => 'AP', 'currency' =>'NAD', 'weight' => 'KG_CM');
		$dhl_core['NC'] = array('region' => 'AP', 'currency' =>'XPF', 'weight' => 'KG_CM');
		$dhl_core['NE'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_core['NG'] = array('region' => 'AP', 'currency' =>'NGN', 'weight' => 'KG_CM');
		$dhl_core['NI'] = array('region' => 'AM', 'currency' =>'NIO', 'weight' => 'KG_CM');
		$dhl_core['NL'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['NO'] = array('region' => 'EU', 'currency' =>'NOK', 'weight' => 'KG_CM');
		$dhl_core['NP'] = array('region' => 'AP', 'currency' =>'NPR', 'weight' => 'KG_CM');
		$dhl_core['NR'] = array('region' => 'AP', 'currency' =>'AUD', 'weight' => 'KG_CM');
		$dhl_core['NU'] = array('region' => 'AP', 'currency' =>'NZD', 'weight' => 'KG_CM');
		$dhl_core['NZ'] = array('region' => 'AP', 'currency' =>'NZD', 'weight' => 'KG_CM');
		$dhl_core['OM'] = array('region' => 'AP', 'currency' =>'OMR', 'weight' => 'KG_CM');
		$dhl_core['PA'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_core['PE'] = array('region' => 'AM', 'currency' =>'PEN', 'weight' => 'KG_CM');
		$dhl_core['PF'] = array('region' => 'AP', 'currency' =>'XPF', 'weight' => 'KG_CM');
		$dhl_core['PG'] = array('region' => 'AP', 'currency' =>'PGK', 'weight' => 'KG_CM');
		$dhl_core['PH'] = array('region' => 'AP', 'currency' =>'PHP', 'weight' => 'KG_CM');
		$dhl_core['PK'] = array('region' => 'AP', 'currency' =>'PKR', 'weight' => 'KG_CM');
		$dhl_core['PL'] = array('region' => 'EU', 'currency' =>'PLN', 'weight' => 'KG_CM');
		$dhl_core['PR'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_core['PT'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['PW'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_core['PY'] = array('region' => 'AM', 'currency' =>'PYG', 'weight' => 'KG_CM');
		$dhl_core['QA'] = array('region' => 'AP', 'currency' =>'QAR', 'weight' => 'KG_CM');
		$dhl_core['RE'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['RO'] = array('region' => 'EU', 'currency' =>'RON', 'weight' => 'KG_CM');
		$dhl_core['RS'] = array('region' => 'AP', 'currency' =>'RSD', 'weight' => 'KG_CM');
		$dhl_core['RU'] = array('region' => 'AP', 'currency' =>'RUB', 'weight' => 'KG_CM');
		$dhl_core['RW'] = array('region' => 'AP', 'currency' =>'RWF', 'weight' => 'KG_CM');
		$dhl_core['SA'] = array('region' => 'AP', 'currency' =>'SAR', 'weight' => 'KG_CM');
		$dhl_core['SB'] = array('region' => 'AP', 'currency' =>'SBD', 'weight' => 'KG_CM');
		$dhl_core['SC'] = array('region' => 'AP', 'currency' =>'SCR', 'weight' => 'KG_CM');
		$dhl_core['SD'] = array('region' => 'AP', 'currency' =>'SDG', 'weight' => 'KG_CM');
		$dhl_core['SE'] = array('region' => 'EU', 'currency' =>'SEK', 'weight' => 'KG_CM');
		$dhl_core['SG'] = array('region' => 'AP', 'currency' =>'SGD', 'weight' => 'KG_CM');
		$dhl_core['SH'] = array('region' => 'AP', 'currency' =>'SHP', 'weight' => 'KG_CM');
		$dhl_core['SI'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['SK'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['SL'] = array('region' => 'AP', 'currency' =>'SLL', 'weight' => 'KG_CM');
		$dhl_core['SM'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['SN'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_core['SO'] = array('region' => 'AM', 'currency' =>'SOS', 'weight' => 'KG_CM');
		$dhl_core['SR'] = array('region' => 'AM', 'currency' =>'SRD', 'weight' => 'KG_CM');
		$dhl_core['SS'] = array('region' => 'AP', 'currency' =>'SSP', 'weight' => 'KG_CM');
		$dhl_core['ST'] = array('region' => 'AP', 'currency' =>'STD', 'weight' => 'KG_CM');
		$dhl_core['SV'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_core['SY'] = array('region' => 'AP', 'currency' =>'SYP', 'weight' => 'KG_CM');
		$dhl_core['SZ'] = array('region' => 'AP', 'currency' =>'SZL', 'weight' => 'KG_CM');
		$dhl_core['TC'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_core['TD'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
		$dhl_core['TG'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_core['TH'] = array('region' => 'AP', 'currency' =>'THB', 'weight' => 'KG_CM');
		$dhl_core['TJ'] = array('region' => 'AP', 'currency' =>'TJS', 'weight' => 'KG_CM');
		$dhl_core['TL'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_core['TN'] = array('region' => 'AP', 'currency' =>'TND', 'weight' => 'KG_CM');
		$dhl_core['TO'] = array('region' => 'AP', 'currency' =>'TOP', 'weight' => 'KG_CM');
		$dhl_core['TR'] = array('region' => 'AP', 'currency' =>'TRY', 'weight' => 'KG_CM');
		$dhl_core['TT'] = array('region' => 'AM', 'currency' =>'TTD', 'weight' => 'LB_IN');
		$dhl_core['TV'] = array('region' => 'AP', 'currency' =>'AUD', 'weight' => 'KG_CM');
		$dhl_core['TW'] = array('region' => 'AP', 'currency' =>'TWD', 'weight' => 'KG_CM');
		$dhl_core['TZ'] = array('region' => 'AP', 'currency' =>'TZS', 'weight' => 'KG_CM');
		$dhl_core['UA'] = array('region' => 'AP', 'currency' =>'UAH', 'weight' => 'KG_CM');
		$dhl_core['UG'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_core['US'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_core['UY'] = array('region' => 'AM', 'currency' =>'UYU', 'weight' => 'KG_CM');
		$dhl_core['UZ'] = array('region' => 'AP', 'currency' =>'UZS', 'weight' => 'KG_CM');
		$dhl_core['VC'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_core['VE'] = array('region' => 'AM', 'currency' =>'VEF', 'weight' => 'KG_CM');
		$dhl_core['VG'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_core['VI'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_core['VN'] = array('region' => 'AP', 'currency' =>'VND', 'weight' => 'KG_CM');
		$dhl_core['VU'] = array('region' => 'AP', 'currency' =>'VUV', 'weight' => 'KG_CM');
		$dhl_core['WS'] = array('region' => 'AP', 'currency' =>'WST', 'weight' => 'KG_CM');
		$dhl_core['XB'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'LB_IN');
		$dhl_core['XC'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'LB_IN');
		$dhl_core['XE'] = array('region' => 'AM', 'currency' =>'ANG', 'weight' => 'LB_IN');
		$dhl_core['XM'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'LB_IN');
		$dhl_core['XN'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_core['XS'] = array('region' => 'AP', 'currency' =>'SIS', 'weight' => 'KG_CM');
		$dhl_core['XY'] = array('region' => 'AM', 'currency' =>'ANG', 'weight' => 'LB_IN');
		$dhl_core['YE'] = array('region' => 'AP', 'currency' =>'YER', 'weight' => 'KG_CM');
		$dhl_core['YT'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['ZA'] = array('region' => 'AP', 'currency' =>'ZAR', 'weight' => 'KG_CM');
		$dhl_core['ZM'] = array('region' => 'AP', 'currency' =>'ZMW', 'weight' => 'KG_CM');
		$dhl_core['ZW'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
		
	}
	$a2z_dhlexpress = new a2z_dhlexpress_parent();
}
?>
