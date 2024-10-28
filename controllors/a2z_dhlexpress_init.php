<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}
use Google\Cloud\Translate\TranslateClient;
if (!class_exists('A2Z_Dhlexpress')) {
	class A2Z_Dhlexpress extends WC_Shipping_Method
	{
		/**
		 * Constructor for your shipping class
		 *
		 * @access public
		 * @return void
		 */
		public $hpos_enabled = false;
		public function __construct($instance_id = 0)
		{
			$this->id                 = 'a2z_dhlexpress';
			$this->method_title       = __('DHL Express');  // Title shown in admin
			$this->title       = __('DHL Express Shipping');
			$this->method_description = __('DHL Express shipping method for calculating shipping rates.'); // 
			$this->instance_id        = absint($instance_id);
			$this->enabled            = "yes"; // This can be added as an setting but for this example its forced enabled
			$this->supports           = array(
				'shipping-zones',
				'instance-settings',
				'instance-settings-modal',
			);
			$this->init();
			if (get_option("woocommerce_custom_orders_table_enabled") === "yes") {
 		        $this->hpos_enabled = true;
 		    }
		}

		/**
		 * Init your settings
		 *
		 * @access public
		 * @return void
		 */
		function init()
		{
			// Load the settings API
			$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
			$this->init_settings(); // This is part of the settings API. Loads settings you previously init.
			$this->title      = $this->get_option('title');

			// Save settings in admin if you have any defined
			add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
		}

		/**
		 * calculate_shipping function.
		 *
		 * @access public
		 * @param mixed $package
		 * @return void
		 */
		public function calculate_shipping($package = array())
		{
			// $Curr = get_option('woocommerce_currency');
			//      	global $WOOCS;
			//      	if ($WOOCS->default_currency) {
			// $Curr = $WOOCS->default_currency;
			//      	print_r($Curr);
			//      	}else{
			//      		print_r("No");
			//      	}
			//      	die();
			$general_settings = get_option('a2z_dhl_main_settings');
			if(isset($general_settings['a2z_dhlexpress_rates']) && $general_settings['a2z_dhlexpress_rates'] == 'no'){
				return;
			}
			
			$execution_status = get_option('a2z_dhl_express_working_status');
			if(!empty($execution_status)){
				if($execution_status == 'stop_working'){
					return;
				}
			}

			$pack_aft_hook = apply_filters('a2z_dhlexpress_rate_packages', $package);

			if (empty($pack_aft_hook)) {
				return;
			}
//flat rate code
			$manual_flat_rates = apply_filters('a2z_dhlexpress_manual_flat_rates', $package);

			if (!empty($manual_flat_rates) && is_array($manual_flat_rates) && isset($manual_flat_rates[0]['rate_code']) && isset($manual_flat_rates[0]['name']) && isset($manual_flat_rates[0]['rate'])) {
				foreach ($manual_flat_rates as $manual_flat_rate) {
					$rate = array(
						'id'       => 'a2z_dhlexpress:' . $manual_flat_rate['rate_code'],
						'label'    => $manual_flat_rate['name'],
						'cost'     => $manual_flat_rate['rate'],
						'meta_data' => array('a2z_multi_ven' => '', 'a2z_dhl_service' => $manual_flat_rate['rate_code'])
					);
	
					// Register the rate
	
					$this->add_rate($rate);
				}
				return;
			}

			$general_settings = empty($general_settings) ? array() : $general_settings;

			if (!is_array($general_settings)) {
				return;
			}

			//excluded Countries
			if(isset($general_settings['a2z_dhlexpress_exclude_countries'])){

				if(in_array($pack_aft_hook['destination']['country'],$general_settings['a2z_dhlexpress_exclude_countries'])){
					return;
				}
				}

			$dhl_core = array();
			$dhl_core['AD'] = array('region' => 'EU', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['AE'] = array('region' => 'AP', 'currency' => 'AED', 'weight' => 'KG_CM');
			$dhl_core['AF'] = array('region' => 'AP', 'currency' => 'AFN', 'weight' => 'KG_CM');
			$dhl_core['AG'] = array('region' => 'AM', 'currency' => 'XCD', 'weight' => 'LB_IN');
			$dhl_core['AI'] = array('region' => 'AM', 'currency' => 'XCD', 'weight' => 'LB_IN');
			$dhl_core['AL'] = array('region' => 'AP', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['AM'] = array('region' => 'AP', 'currency' => 'AMD', 'weight' => 'KG_CM');
			$dhl_core['AN'] = array('region' => 'AM', 'currency' => 'ANG', 'weight' => 'KG_CM');
			$dhl_core['AO'] = array('region' => 'AP', 'currency' => 'AOA', 'weight' => 'KG_CM');
			$dhl_core['AR'] = array('region' => 'AM', 'currency' => 'ARS', 'weight' => 'KG_CM');
			$dhl_core['AS'] = array('region' => 'AM', 'currency' => 'USD', 'weight' => 'LB_IN');
			$dhl_core['AT'] = array('region' => 'EU', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['AU'] = array('region' => 'AP', 'currency' => 'AUD', 'weight' => 'KG_CM');
			$dhl_core['AW'] = array('region' => 'AM', 'currency' => 'AWG', 'weight' => 'LB_IN');
			$dhl_core['AZ'] = array('region' => 'AM', 'currency' => 'AZN', 'weight' => 'KG_CM');
			$dhl_core['AZ'] = array('region' => 'AM', 'currency' => 'AZN', 'weight' => 'KG_CM');
			$dhl_core['GB'] = array('region' => 'EU', 'currency' => 'GBP', 'weight' => 'KG_CM');
			$dhl_core['BA'] = array('region' => 'AP', 'currency' => 'BAM', 'weight' => 'KG_CM');
			$dhl_core['BB'] = array('region' => 'AM', 'currency' => 'BBD', 'weight' => 'LB_IN');
			$dhl_core['BD'] = array('region' => 'AP', 'currency' => 'BDT', 'weight' => 'KG_CM');
			$dhl_core['BE'] = array('region' => 'EU', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['BF'] = array('region' => 'AP', 'currency' => 'XOF', 'weight' => 'KG_CM');
			$dhl_core['BG'] = array('region' => 'EU', 'currency' => 'BGN', 'weight' => 'KG_CM');
			$dhl_core['BH'] = array('region' => 'AP', 'currency' => 'BHD', 'weight' => 'KG_CM');
			$dhl_core['BI'] = array('region' => 'AP', 'currency' => 'BIF', 'weight' => 'KG_CM');
			$dhl_core['BJ'] = array('region' => 'AP', 'currency' => 'XOF', 'weight' => 'KG_CM');
			$dhl_core['BM'] = array('region' => 'AM', 'currency' => 'BMD', 'weight' => 'LB_IN');
			$dhl_core['BN'] = array('region' => 'AP', 'currency' => 'BND', 'weight' => 'KG_CM');
			$dhl_core['BO'] = array('region' => 'AM', 'currency' => 'BOB', 'weight' => 'KG_CM');
			$dhl_core['BR'] = array('region' => 'AM', 'currency' => 'BRL', 'weight' => 'KG_CM');
			$dhl_core['BS'] = array('region' => 'AM', 'currency' => 'BSD', 'weight' => 'LB_IN');
			$dhl_core['BT'] = array('region' => 'AP', 'currency' => 'BTN', 'weight' => 'KG_CM');
			$dhl_core['BW'] = array('region' => 'AP', 'currency' => 'BWP', 'weight' => 'KG_CM');
			$dhl_core['BY'] = array('region' => 'AP', 'currency' => 'BYR', 'weight' => 'KG_CM');
			$dhl_core['BZ'] = array('region' => 'AM', 'currency' => 'BZD', 'weight' => 'KG_CM');
			$dhl_core['CA'] = array('region' => 'AM', 'currency' => 'CAD', 'weight' => 'LB_IN');
			$dhl_core['CF'] = array('region' => 'AP', 'currency' => 'XAF', 'weight' => 'KG_CM');
			$dhl_core['CG'] = array('region' => 'AP', 'currency' => 'XAF', 'weight' => 'KG_CM');
			$dhl_core['CH'] = array('region' => 'EU', 'currency' => 'CHF', 'weight' => 'KG_CM');
			$dhl_core['CI'] = array('region' => 'AP', 'currency' => 'XOF', 'weight' => 'KG_CM');
			$dhl_core['CK'] = array('region' => 'AP', 'currency' => 'NZD', 'weight' => 'KG_CM');
			$dhl_core['CL'] = array('region' => 'AM', 'currency' => 'CLP', 'weight' => 'KG_CM');
			$dhl_core['CM'] = array('region' => 'AP', 'currency' => 'XAF', 'weight' => 'KG_CM');
			$dhl_core['CN'] = array('region' => 'AP', 'currency' => 'CNY', 'weight' => 'KG_CM');
			$dhl_core['CO'] = array('region' => 'AM', 'currency' => 'COP', 'weight' => 'KG_CM');
			$dhl_core['CR'] = array('region' => 'AM', 'currency' => 'CRC', 'weight' => 'KG_CM');
			$dhl_core['CU'] = array('region' => 'AM', 'currency' => 'CUC', 'weight' => 'KG_CM');
			$dhl_core['CV'] = array('region' => 'AP', 'currency' => 'CVE', 'weight' => 'KG_CM');
			$dhl_core['CY'] = array('region' => 'AP', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['CZ'] = array('region' => 'EU', 'currency' => 'CZK', 'weight' => 'KG_CM');
			$dhl_core['DE'] = array('region' => 'AP', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['DJ'] = array('region' => 'EU', 'currency' => 'DJF', 'weight' => 'KG_CM');
			$dhl_core['DK'] = array('region' => 'AM', 'currency' => 'DKK', 'weight' => 'KG_CM');
			$dhl_core['DM'] = array('region' => 'AM', 'currency' => 'XCD', 'weight' => 'LB_IN');
			$dhl_core['DO'] = array('region' => 'AP', 'currency' => 'DOP', 'weight' => 'LB_IN');
			$dhl_core['DZ'] = array('region' => 'AM', 'currency' => 'DZD', 'weight' => 'KG_CM');
			$dhl_core['EC'] = array('region' => 'EU', 'currency' => 'USD', 'weight' => 'KG_CM');
			$dhl_core['EE'] = array('region' => 'AP', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['EG'] = array('region' => 'AP', 'currency' => 'EGP', 'weight' => 'KG_CM');
			$dhl_core['ER'] = array('region' => 'EU', 'currency' => 'ERN', 'weight' => 'KG_CM');
			$dhl_core['ES'] = array('region' => 'AP', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['ET'] = array('region' => 'AU', 'currency' => 'ETB', 'weight' => 'KG_CM');
			$dhl_core['FI'] = array('region' => 'AP', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['FJ'] = array('region' => 'AP', 'currency' => 'FJD', 'weight' => 'KG_CM');
			$dhl_core['FK'] = array('region' => 'AM', 'currency' => 'GBP', 'weight' => 'KG_CM');
			$dhl_core['FM'] = array('region' => 'AM', 'currency' => 'USD', 'weight' => 'LB_IN');
			$dhl_core['FO'] = array('region' => 'AM', 'currency' => 'DKK', 'weight' => 'KG_CM');
			$dhl_core['FR'] = array('region' => 'EU', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['GA'] = array('region' => 'AP', 'currency' => 'XAF', 'weight' => 'KG_CM');
			$dhl_core['GB'] = array('region' => 'EU', 'currency' => 'GBP', 'weight' => 'KG_CM');
			$dhl_core['GD'] = array('region' => 'AM', 'currency' => 'XCD', 'weight' => 'LB_IN');
			$dhl_core['GE'] = array('region' => 'AM', 'currency' => 'GEL', 'weight' => 'KG_CM');
			$dhl_core['GF'] = array('region' => 'AM', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['GG'] = array('region' => 'AM', 'currency' => 'GBP', 'weight' => 'KG_CM');
			$dhl_core['GH'] = array('region' => 'AP', 'currency' => 'GHS', 'weight' => 'KG_CM');
			$dhl_core['GI'] = array('region' => 'AM', 'currency' => 'GBP', 'weight' => 'KG_CM');
			$dhl_core['GL'] = array('region' => 'AM', 'currency' => 'DKK', 'weight' => 'KG_CM');
			$dhl_core['GM'] = array('region' => 'AP', 'currency' => 'GMD', 'weight' => 'KG_CM');
			$dhl_core['GN'] = array('region' => 'AP', 'currency' => 'GNF', 'weight' => 'KG_CM');
			$dhl_core['GP'] = array('region' => 'AM', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['GQ'] = array('region' => 'AP', 'currency' => 'XAF', 'weight' => 'KG_CM');
			$dhl_core['GR'] = array('region' => 'EU', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['GT'] = array('region' => 'AM', 'currency' => 'GTQ', 'weight' => 'KG_CM');
			$dhl_core['GU'] = array('region' => 'AM', 'currency' => 'USD', 'weight' => 'LB_IN');
			$dhl_core['GW'] = array('region' => 'AP', 'currency' => 'XOF', 'weight' => 'KG_CM');
			$dhl_core['GY'] = array('region' => 'AP', 'currency' => 'GYD', 'weight' => 'LB_IN');
			$dhl_core['HK'] = array('region' => 'AM', 'currency' => 'HKD', 'weight' => 'KG_CM');
			$dhl_core['HN'] = array('region' => 'AM', 'currency' => 'HNL', 'weight' => 'KG_CM');
			$dhl_core['HR'] = array('region' => 'AP', 'currency' => 'HRK', 'weight' => 'KG_CM');
			$dhl_core['HT'] = array('region' => 'AM', 'currency' => 'HTG', 'weight' => 'LB_IN');
			$dhl_core['HU'] = array('region' => 'EU', 'currency' => 'HUF', 'weight' => 'KG_CM');
			$dhl_core['IC'] = array('region' => 'EU', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['ID'] = array('region' => 'AP', 'currency' => 'IDR', 'weight' => 'KG_CM');
			$dhl_core['IE'] = array('region' => 'EU', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['IL'] = array('region' => 'AP', 'currency' => 'ILS', 'weight' => 'KG_CM');
			$dhl_core['IN'] = array('region' => 'AP', 'currency' => 'INR', 'weight' => 'KG_CM');
			$dhl_core['IQ'] = array('region' => 'AP', 'currency' => 'IQD', 'weight' => 'KG_CM');
			$dhl_core['IR'] = array('region' => 'AP', 'currency' => 'IRR', 'weight' => 'KG_CM');
			$dhl_core['IS'] = array('region' => 'EU', 'currency' => 'ISK', 'weight' => 'KG_CM');
			$dhl_core['IT'] = array('region' => 'EU', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['JE'] = array('region' => 'AM', 'currency' => 'GBP', 'weight' => 'KG_CM');
			$dhl_core['JM'] = array('region' => 'AM', 'currency' => 'JMD', 'weight' => 'KG_CM');
			$dhl_core['JO'] = array('region' => 'AP', 'currency' => 'JOD', 'weight' => 'KG_CM');
			$dhl_core['JP'] = array('region' => 'AP', 'currency' => 'JPY', 'weight' => 'KG_CM');
			$dhl_core['KE'] = array('region' => 'AP', 'currency' => 'KES', 'weight' => 'KG_CM');
			$dhl_core['KG'] = array('region' => 'AP', 'currency' => 'KGS', 'weight' => 'KG_CM');
			$dhl_core['KH'] = array('region' => 'AP', 'currency' => 'KHR', 'weight' => 'KG_CM');
			$dhl_core['KI'] = array('region' => 'AP', 'currency' => 'AUD', 'weight' => 'KG_CM');
			$dhl_core['KM'] = array('region' => 'AP', 'currency' => 'KMF', 'weight' => 'KG_CM');
			$dhl_core['KN'] = array('region' => 'AM', 'currency' => 'XCD', 'weight' => 'LB_IN');
			$dhl_core['KP'] = array('region' => 'AP', 'currency' => 'KPW', 'weight' => 'LB_IN');
			$dhl_core['KR'] = array('region' => 'AP', 'currency' => 'KRW', 'weight' => 'KG_CM');
			$dhl_core['KV'] = array('region' => 'AM', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['KW'] = array('region' => 'AP', 'currency' => 'KWD', 'weight' => 'KG_CM');
			$dhl_core['KY'] = array('region' => 'AM', 'currency' => 'KYD', 'weight' => 'KG_CM');
			$dhl_core['KZ'] = array('region' => 'AP', 'currency' => 'KZF', 'weight' => 'LB_IN');
			$dhl_core['LA'] = array('region' => 'AP', 'currency' => 'LAK', 'weight' => 'KG_CM');
			$dhl_core['LB'] = array('region' => 'AP', 'currency' => 'USD', 'weight' => 'KG_CM');
			$dhl_core['LC'] = array('region' => 'AM', 'currency' => 'XCD', 'weight' => 'KG_CM');
			$dhl_core['LI'] = array('region' => 'AM', 'currency' => 'CHF', 'weight' => 'LB_IN');
			$dhl_core['LK'] = array('region' => 'AP', 'currency' => 'LKR', 'weight' => 'KG_CM');
			$dhl_core['LR'] = array('region' => 'AP', 'currency' => 'LRD', 'weight' => 'KG_CM');
			$dhl_core['LS'] = array('region' => 'AP', 'currency' => 'LSL', 'weight' => 'KG_CM');
			$dhl_core['LT'] = array('region' => 'EU', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['LU'] = array('region' => 'EU', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['LV'] = array('region' => 'EU', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['LY'] = array('region' => 'AP', 'currency' => 'LYD', 'weight' => 'KG_CM');
			$dhl_core['MA'] = array('region' => 'AP', 'currency' => 'MAD', 'weight' => 'KG_CM');
			$dhl_core['MC'] = array('region' => 'AM', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['MD'] = array('region' => 'AP', 'currency' => 'MDL', 'weight' => 'KG_CM');
			$dhl_core['ME'] = array('region' => 'AM', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['MG'] = array('region' => 'AP', 'currency' => 'MGA', 'weight' => 'KG_CM');
			$dhl_core['MH'] = array('region' => 'AM', 'currency' => 'USD', 'weight' => 'LB_IN');
			$dhl_core['MK'] = array('region' => 'AP', 'currency' => 'MKD', 'weight' => 'KG_CM');
			$dhl_core['ML'] = array('region' => 'AP', 'currency' => 'COF', 'weight' => 'KG_CM');
			$dhl_core['MM'] = array('region' => 'AP', 'currency' => 'USD', 'weight' => 'KG_CM');
			$dhl_core['MN'] = array('region' => 'AP', 'currency' => 'MNT', 'weight' => 'KG_CM');
			$dhl_core['MO'] = array('region' => 'AP', 'currency' => 'MOP', 'weight' => 'KG_CM');
			$dhl_core['MP'] = array('region' => 'AM', 'currency' => 'USD', 'weight' => 'LB_IN');
			$dhl_core['MQ'] = array('region' => 'AM', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['MR'] = array('region' => 'AP', 'currency' => 'MRO', 'weight' => 'KG_CM');
			$dhl_core['MS'] = array('region' => 'AM', 'currency' => 'XCD', 'weight' => 'LB_IN');
			$dhl_core['MT'] = array('region' => 'AP', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['MU'] = array('region' => 'AP', 'currency' => 'MUR', 'weight' => 'KG_CM');
			$dhl_core['MV'] = array('region' => 'AP', 'currency' => 'MVR', 'weight' => 'KG_CM');
			$dhl_core['MW'] = array('region' => 'AP', 'currency' => 'MWK', 'weight' => 'KG_CM');
			$dhl_core['MX'] = array('region' => 'AM', 'currency' => 'MXN', 'weight' => 'KG_CM');
			$dhl_core['MY'] = array('region' => 'AP', 'currency' => 'MYR', 'weight' => 'KG_CM');
			$dhl_core['MZ'] = array('region' => 'AP', 'currency' => 'MZN', 'weight' => 'KG_CM');
			$dhl_core['NA'] = array('region' => 'AP', 'currency' => 'NAD', 'weight' => 'KG_CM');
			$dhl_core['NC'] = array('region' => 'AP', 'currency' => 'XPF', 'weight' => 'KG_CM');
			$dhl_core['NE'] = array('region' => 'AP', 'currency' => 'XOF', 'weight' => 'KG_CM');
			$dhl_core['NG'] = array('region' => 'AP', 'currency' => 'NGN', 'weight' => 'KG_CM');
			$dhl_core['NI'] = array('region' => 'AM', 'currency' => 'NIO', 'weight' => 'KG_CM');
			$dhl_core['NL'] = array('region' => 'EU', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['NO'] = array('region' => 'EU', 'currency' => 'NOK', 'weight' => 'KG_CM');
			$dhl_core['NP'] = array('region' => 'AP', 'currency' => 'NPR', 'weight' => 'KG_CM');
			$dhl_core['NR'] = array('region' => 'AP', 'currency' => 'AUD', 'weight' => 'KG_CM');
			$dhl_core['NU'] = array('region' => 'AP', 'currency' => 'NZD', 'weight' => 'KG_CM');
			$dhl_core['NZ'] = array('region' => 'AP', 'currency' => 'NZD', 'weight' => 'KG_CM');
			$dhl_core['OM'] = array('region' => 'AP', 'currency' => 'OMR', 'weight' => 'KG_CM');
			$dhl_core['PA'] = array('region' => 'AM', 'currency' => 'USD', 'weight' => 'KG_CM');
			$dhl_core['PE'] = array('region' => 'AM', 'currency' => 'PEN', 'weight' => 'KG_CM');
			$dhl_core['PF'] = array('region' => 'AP', 'currency' => 'XPF', 'weight' => 'KG_CM');
			$dhl_core['PG'] = array('region' => 'AP', 'currency' => 'PGK', 'weight' => 'KG_CM');
			$dhl_core['PH'] = array('region' => 'AP', 'currency' => 'PHP', 'weight' => 'KG_CM');
			$dhl_core['PK'] = array('region' => 'AP', 'currency' => 'PKR', 'weight' => 'KG_CM');
			$dhl_core['PL'] = array('region' => 'EU', 'currency' => 'PLN', 'weight' => 'KG_CM');
			$dhl_core['PR'] = array('region' => 'AM', 'currency' => 'USD', 'weight' => 'LB_IN');
			$dhl_core['PT'] = array('region' => 'EU', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['PW'] = array('region' => 'AM', 'currency' => 'USD', 'weight' => 'KG_CM');
			$dhl_core['PY'] = array('region' => 'AM', 'currency' => 'PYG', 'weight' => 'KG_CM');
			$dhl_core['QA'] = array('region' => 'AP', 'currency' => 'QAR', 'weight' => 'KG_CM');
			$dhl_core['RE'] = array('region' => 'AP', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['RO'] = array('region' => 'EU', 'currency' => 'RON', 'weight' => 'KG_CM');
			$dhl_core['RS'] = array('region' => 'AP', 'currency' => 'RSD', 'weight' => 'KG_CM');
			$dhl_core['RU'] = array('region' => 'AP', 'currency' => 'RUB', 'weight' => 'KG_CM');
			$dhl_core['RW'] = array('region' => 'AP', 'currency' => 'RWF', 'weight' => 'KG_CM');
			$dhl_core['SA'] = array('region' => 'AP', 'currency' => 'SAR', 'weight' => 'KG_CM');
			$dhl_core['SB'] = array('region' => 'AP', 'currency' => 'SBD', 'weight' => 'KG_CM');
			$dhl_core['SC'] = array('region' => 'AP', 'currency' => 'SCR', 'weight' => 'KG_CM');
			$dhl_core['SD'] = array('region' => 'AP', 'currency' => 'SDG', 'weight' => 'KG_CM');
			$dhl_core['SE'] = array('region' => 'EU', 'currency' => 'SEK', 'weight' => 'KG_CM');
			$dhl_core['SG'] = array('region' => 'AP', 'currency' => 'SGD', 'weight' => 'KG_CM');
			$dhl_core['SH'] = array('region' => 'AP', 'currency' => 'SHP', 'weight' => 'KG_CM');
			$dhl_core['SI'] = array('region' => 'EU', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['SK'] = array('region' => 'EU', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['SL'] = array('region' => 'AP', 'currency' => 'SLL', 'weight' => 'KG_CM');
			$dhl_core['SM'] = array('region' => 'EU', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['SN'] = array('region' => 'AP', 'currency' => 'XOF', 'weight' => 'KG_CM');
			$dhl_core['SO'] = array('region' => 'AM', 'currency' => 'SOS', 'weight' => 'KG_CM');
			$dhl_core['SR'] = array('region' => 'AM', 'currency' => 'SRD', 'weight' => 'KG_CM');
			$dhl_core['SS'] = array('region' => 'AP', 'currency' => 'SSP', 'weight' => 'KG_CM');
			$dhl_core['ST'] = array('region' => 'AP', 'currency' => 'STD', 'weight' => 'KG_CM');
			$dhl_core['SV'] = array('region' => 'AM', 'currency' => 'USD', 'weight' => 'KG_CM');
			$dhl_core['SY'] = array('region' => 'AP', 'currency' => 'SYP', 'weight' => 'KG_CM');
			$dhl_core['SZ'] = array('region' => 'AP', 'currency' => 'SZL', 'weight' => 'KG_CM');
			$dhl_core['TC'] = array('region' => 'AM', 'currency' => 'USD', 'weight' => 'LB_IN');
			$dhl_core['TD'] = array('region' => 'AP', 'currency' => 'XAF', 'weight' => 'KG_CM');
			$dhl_core['TG'] = array('region' => 'AP', 'currency' => 'XOF', 'weight' => 'KG_CM');
			$dhl_core['TH'] = array('region' => 'AP', 'currency' => 'THB', 'weight' => 'KG_CM');
			$dhl_core['TJ'] = array('region' => 'AP', 'currency' => 'TJS', 'weight' => 'KG_CM');
			$dhl_core['TL'] = array('region' => 'AP', 'currency' => 'USD', 'weight' => 'KG_CM');
			$dhl_core['TN'] = array('region' => 'AP', 'currency' => 'TND', 'weight' => 'KG_CM');
			$dhl_core['TO'] = array('region' => 'AP', 'currency' => 'TOP', 'weight' => 'KG_CM');
			$dhl_core['TR'] = array('region' => 'AP', 'currency' => 'TRY', 'weight' => 'KG_CM');
			$dhl_core['TT'] = array('region' => 'AM', 'currency' => 'TTD', 'weight' => 'LB_IN');
			$dhl_core['TV'] = array('region' => 'AP', 'currency' => 'AUD', 'weight' => 'KG_CM');
			$dhl_core['TW'] = array('region' => 'AP', 'currency' => 'TWD', 'weight' => 'KG_CM');
			$dhl_core['TZ'] = array('region' => 'AP', 'currency' => 'TZS', 'weight' => 'KG_CM');
			$dhl_core['UA'] = array('region' => 'AP', 'currency' => 'UAH', 'weight' => 'KG_CM');
			$dhl_core['UG'] = array('region' => 'AP', 'currency' => 'USD', 'weight' => 'KG_CM');
			$dhl_core['US'] = array('region' => 'AM', 'currency' => 'USD', 'weight' => 'LB_IN');
			$dhl_core['UY'] = array('region' => 'AM', 'currency' => 'UYU', 'weight' => 'KG_CM');
			$dhl_core['UZ'] = array('region' => 'AP', 'currency' => 'UZS', 'weight' => 'KG_CM');
			$dhl_core['VC'] = array('region' => 'AM', 'currency' => 'XCD', 'weight' => 'LB_IN');
			$dhl_core['VE'] = array('region' => 'AM', 'currency' => 'VEF', 'weight' => 'KG_CM');
			$dhl_core['VG'] = array('region' => 'AM', 'currency' => 'USD', 'weight' => 'LB_IN');
			$dhl_core['VI'] = array('region' => 'AM', 'currency' => 'USD', 'weight' => 'LB_IN');
			$dhl_core['VN'] = array('region' => 'AP', 'currency' => 'VND', 'weight' => 'KG_CM');
			$dhl_core['VU'] = array('region' => 'AP', 'currency' => 'VUV', 'weight' => 'KG_CM');
			$dhl_core['WS'] = array('region' => 'AP', 'currency' => 'WST', 'weight' => 'KG_CM');
			$dhl_core['XB'] = array('region' => 'AM', 'currency' => 'EUR', 'weight' => 'LB_IN');
			$dhl_core['XC'] = array('region' => 'AM', 'currency' => 'EUR', 'weight' => 'LB_IN');
			$dhl_core['XE'] = array('region' => 'AM', 'currency' => 'ANG', 'weight' => 'LB_IN');
			$dhl_core['XM'] = array('region' => 'AM', 'currency' => 'EUR', 'weight' => 'LB_IN');
			$dhl_core['XN'] = array('region' => 'AM', 'currency' => 'XCD', 'weight' => 'LB_IN');
			$dhl_core['XS'] = array('region' => 'AP', 'currency' => 'SIS', 'weight' => 'KG_CM');
			$dhl_core['XY'] = array('region' => 'AM', 'currency' => 'ANG', 'weight' => 'LB_IN');
			$dhl_core['YE'] = array('region' => 'AP', 'currency' => 'YER', 'weight' => 'KG_CM');
			$dhl_core['YT'] = array('region' => 'AP', 'currency' => 'EUR', 'weight' => 'KG_CM');
			$dhl_core['ZA'] = array('region' => 'AP', 'currency' => 'ZAR', 'weight' => 'KG_CM');
			$dhl_core['ZM'] = array('region' => 'AP', 'currency' => 'ZMW', 'weight' => 'KG_CM');
			$dhl_core['ZW'] = array('region' => 'AP', 'currency' => 'USD', 'weight' => 'KG_CM');

			$custom_settings = array();
			$custom_settings['default'] = array(
				'a2z_dhlexpress_site_id' => isset($general_settings['a2z_dhlexpress_site_id'])? $general_settings['a2z_dhlexpress_site_id'] : '',
				'a2z_dhlexpress_site_pwd' => isset($general_settings['a2z_dhlexpress_site_pwd'])? $general_settings['a2z_dhlexpress_site_pwd'] : '',
				'a2z_dhlexpress_acc_no' => isset($general_settings['a2z_dhlexpress_acc_no'])? $general_settings['a2z_dhlexpress_acc_no'] : '',
				'a2z_dhlexpress_import_no' => isset($general_settings['a2z_dhlexpress_import_no'])? $general_settings['a2z_dhlexpress_import_no']: '',
				'a2z_dhlexpress_shipper_name' => isset($general_settings['a2z_dhlexpress_shipper_name'])?$general_settings['a2z_dhlexpress_shipper_name'] : '',
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
			);
			$vendor_settings = array();

			if (isset($general_settings['a2z_dhlexpress_v_enable']) && $general_settings['a2z_dhlexpress_v_enable'] == 'yes' && isset($general_settings['a2z_dhlexpress_v_rates']) && $general_settings['a2z_dhlexpress_v_rates'] == 'yes') {
				// Multi Vendor Enabled
				foreach ($pack_aft_hook['contents'] as $key => $value) {
					$product_id = $value['product_id'];
					if ($this->hpos_enabled) {
					    $hpos_prod_data = wc_get_product($product_id);
					    $dhl_account = $hpos_prod_data->get_meta("dhl_express_address");
					} else {
						$dhl_account = get_post_meta($product_id, 'dhl_express_address', true);
					}
					if (empty($dhl_account) || $dhl_account == 'default') {
						$dhl_account = 'default';
						if (!isset($vendor_settings[$dhl_account])) {
							$vendor_settings[$dhl_account] = $custom_settings['default'];
						}

						$vendor_settings[$dhl_account]['products'][] = $value;
					}

					if ($dhl_account != 'default') {
						$user_account = get_post_meta($dhl_account, 'a2z_dhl_vendor_settings', true);
						$user_account = empty($user_account) ? array() : $user_account;
						if (!empty($user_account)) {
							if (!isset($vendor_settings[$dhl_account])) {

								$vendor_settings[$dhl_account] = $custom_settings['default'];

								if ($user_account['a2z_dhlexpress_site_id'] != '' && $user_account['a2z_dhlexpress_site_pwd'] != '' && $user_account['a2z_dhlexpress_acc_no'] != '') {

									$vendor_settings[$dhl_account]['a2z_dhlexpress_site_id'] = $user_account['a2z_dhlexpress_site_id'];

									if ($user_account['a2z_dhlexpress_site_pwd'] != '') {
										$vendor_settings[$dhl_account]['a2z_dhlexpress_site_pwd'] = $user_account['a2z_dhlexpress_site_pwd'];
									}

									if ($user_account['a2z_dhlexpress_acc_no'] != '') {
										$vendor_settings[$dhl_account]['a2z_dhlexpress_acc_no'] = $user_account['a2z_dhlexpress_acc_no'];
									}

									$vendor_settings[$dhl_account]['a2z_dhlexpress_import_no'] = !empty($user_account['a2z_dhlexpress_import_no']) ? $user_account['a2z_dhlexpress_import_no'] : '';
								}

								if ($user_account['a2z_dhlexpress_address1'] != '' && $user_account['a2z_dhlexpress_city'] != '' && $user_account['a2z_dhlexpress_state'] != '' && $user_account['a2z_dhlexpress_zip'] != '' && $user_account['a2z_dhlexpress_country'] != '' && $user_account['a2z_dhlexpress_shipper_name'] != '') {

									if ($user_account['a2z_dhlexpress_shipper_name'] != '') {
										$vendor_settings[$dhl_account]['a2z_dhlexpress_shipper_name'] = $user_account['a2z_dhlexpress_shipper_name'];
									}

									if ($user_account['a2z_dhlexpress_company'] != '') {
										$vendor_settings[$dhl_account]['a2z_dhlexpress_company'] = $user_account['a2z_dhlexpress_company'];
									}

									if ($user_account['a2z_dhlexpress_mob_num'] != '') {
										$vendor_settings[$dhl_account]['a2z_dhlexpress_mob_num'] = $user_account['a2z_dhlexpress_mob_num'];
									}

									if ($user_account['a2z_dhlexpress_email'] != '') {
										$vendor_settings[$dhl_account]['a2z_dhlexpress_email'] = $user_account['a2z_dhlexpress_email'];
									}

									if ($user_account['a2z_dhlexpress_address1'] != '') {
										$vendor_settings[$dhl_account]['a2z_dhlexpress_address1'] = $user_account['a2z_dhlexpress_address1'];
									}

									$vendor_settings[$dhl_account]['a2z_dhlexpress_address2'] = $user_account['a2z_dhlexpress_address2'];

									if ($user_account['a2z_dhlexpress_city'] != '') {
										$vendor_settings[$dhl_account]['a2z_dhlexpress_city'] = $user_account['a2z_dhlexpress_city'];
									}

									if ($user_account['a2z_dhlexpress_state'] != '') {
										$vendor_settings[$dhl_account]['a2z_dhlexpress_state'] = $user_account['a2z_dhlexpress_state'];
									}

									if ($user_account['a2z_dhlexpress_zip'] != '') {
										$vendor_settings[$dhl_account]['a2z_dhlexpress_zip'] = $user_account['a2z_dhlexpress_zip'];
									}

									if ($user_account['a2z_dhlexpress_country'] != '') {
										$vendor_settings[$dhl_account]['a2z_dhlexpress_country'] = $user_account['a2z_dhlexpress_country'];
									}

									$vendor_settings[$dhl_account]['a2z_dhlexpress_gstin'] = $user_account['a2z_dhlexpress_gstin'];
									$vendor_settings[$dhl_account]['a2z_dhlexpress_con_rate'] = $user_account['a2z_dhlexpress_con_rate'];
								}
							}

							$vendor_settings[$dhl_account]['products'][] = $value;
						}
					}
				}
			}

			if (empty($vendor_settings)) {
				$custom_settings['default']['products'] = $pack_aft_hook['contents'];
			} else {
				$custom_settings = $vendor_settings;
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
			$admin_user = false;
			if (is_user_logged_in()) {
				$user = wp_get_current_user();
				if (isset($user->roles) && !empty($user->roles) && in_array("administrator", $user->roles)) {
					$admin_user = true;
				}
			}
			if (isset($general_settings['a2z_dhlexpress_developer_rate']) && $general_settings['a2z_dhlexpress_developer_rate'] == 'yes' && $admin_user) {
				echo "<pre><b style='color:red;'>Debug log will be shown only for admins and it can be disabled by unchecking 'Enable Debug Mode' option in configuration.</b>";
			}

			foreach ($custom_settings as $key => $value) {

			if (isset($general_settings['a2z_dhlexpress_auto_con_rate']) && $general_settings['a2z_dhlexpress_auto_con_rate'] == "yes") {
				$current_date = date('m-d-Y', time());
				$ex_rate_data = get_option('a2z_dhl_ex_rate'.$key);
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
										'sslverify'   => FALSE
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
						foreach($pack_aft_hook['destination'] as $dkey => $dvalue){
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
									$pack_aft_hook['destination'][$dkey] = $response['text'];
								  }
								}
							}
						}
						
					}
				}
				$to_city = $pack_aft_hook['destination']['city'];

				$shipping_rates[$key] = array();

				$orgin_postalcode_or_city = $this->a2z_get_zipcode_or_city($value['a2z_dhlexpress_country'], $value['a2z_dhlexpress_city'], $value['a2z_dhlexpress_zip']);

				$destination_postcode_city = $this->a2z_get_zipcode_or_city($pack_aft_hook['destination']['country'], $to_city, $pack_aft_hook['destination']['postcode']);

				$general_settings['a2z_dhlexpress_currency'] = isset($dhl_core[(isset($value['a2z_dhlexpress_country']) ? $value['a2z_dhlexpress_country'] : 'A2Z')]) ? $dhl_core[$value['a2z_dhlexpress_country']]['currency'] : '';

				$value['products'] = apply_filters('a2z_dhlexpress_rate_based_product', $value['products'],'true');
				$dhl_packs = $this->hit_get_dhl_packages($value['products'], $general_settings, $general_settings['a2z_dhlexpress_currency']);
				$order_total = 0;
				foreach ($pack_aft_hook['contents'] as $item_id => $values) {
					$order_total += (float) $values['line_subtotal'];
				}
				if (isset($general_settings['a2z_dhlexpress_insure'])) {
					$general_settings['a2z_dhlexpress_insure'] = apply_filters("hitshipo_ins_ship", $general_settings['a2z_dhlexpress_insure'], $key, $package);
				}
				
				if (isset($general_settings['a2z_dhlexpress_api_type']) && $general_settings['a2z_dhlexpress_api_type'] == "REST") {
					$rec_address = isset($pack_aft_hook['destination']) ? $pack_aft_hook['destination'] : [];
					$ship_address = $this->getFormatedShipAddr($value);
					if (!class_exists("dhl_rest")) {
						include_once('classes/dhl_rest_main.php');
					}
					$dhl_rest_obj = new dhl_rest();
					$dhl_rest_obj->dhlCurrency = $general_settings['a2z_dhlexpress_currency'];
					$mode = (isset($general_settings['a2z_dhlexpress_test']) && $general_settings['a2z_dhlexpress_test'] != 'yes') ? "live" : "test";
					$add_date = 0;
					do{

						$xmlRequest = $dhl_rest_obj->createRateReq($dhl_packs, $general_settings, $ship_address, $rec_address, $add_date, $key);
						
				
						$xml = $dhl_rest_obj->getRes($xmlRequest, $mode, $value['a2z_dhlexpress_site_id'], $value['a2z_dhlexpress_site_pwd'], "rate");
												
						if(isset($xml->detail)){
							if((strpos((string)$xml->detail, "996") !== false))
						    $re_run = true;
						    $add_date++;
					    } else {
					    	$re_run = false;
					    }
					} while ($re_run);
					
				} else {
					$pieces = "";
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
						}
					}

					$fetch_accountrates = (isset($general_settings['a2z_dhlexpress_account_rates']) && $general_settings['a2z_dhlexpress_account_rates'] == "yes") ? "<PaymentAccountNumber>" . $value['a2z_dhlexpress_acc_no'] . "</PaymentAccountNumber>" : "";
	//  $this->a2z_dhl_is_eu_country($value['a2z_dhlexpress_country'], $pack_aft_hook['destination']['country'])
					
					$dutiable = ( ($pack_aft_hook['destination']['country'] == $value['a2z_dhlexpress_country']) ) ? "N" : "Y";
					if($this->a2z_dhl_is_eu_country($value['a2z_dhlexpress_country'], $pack_aft_hook['destination']['country'])){
						$dutiable = "N";
					}
					if($pack_aft_hook['destination']['country'] == 'AT' && $value['a2z_dhlexpress_country'] == 'CZ'){
						$dutiable = "N";
					}
					if($pack_aft_hook['destination']['country'] == 'NL' && $value['a2z_dhlexpress_country'] == 'SE'){
						$dutiable = "N";
					}
					$cart_total = 0;

					if (isset($pack_aft_hook['cart_subtotal'])) {
						$cart_total += $pack_aft_hook['cart_subtotal'];
					}else{
						foreach ($pack_aft_hook['contents'] as $item_id => $values) {
							$cart_total += (float) $values['line_subtotal'];
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
							$cart_total *= $exchange_rate;
						}
					}


					$dutiable_content = ($dutiable == "Y") ? "<Dutiable><DeclaredCurrency>" . $general_settings['a2z_dhlexpress_currency'] . "</DeclaredCurrency><DeclaredValue>" . $cart_total . "</DeclaredValue></Dutiable>" : "";
					
					$insurance_details = (isset($general_settings['a2z_dhlexpress_insure']) && $general_settings['a2z_dhlexpress_insure'] == 'yes')  ? "<QtdShp><QtdShpExChrg><SpecialServiceType>II</SpecialServiceType><LocalSpecialServiceType>XCH</LocalSpecialServiceType></QtdShpExChrg></QtdShp><InsuredValue>" . apply_filters("hitshipo_ins_val_ship", round($cart_total, 2), $key, $package) . "</InsuredValue><InsuredCurrency>" . $general_settings['a2z_dhlexpress_currency'] . "</InsuredCurrency>" : ""; //insurance type
					$danger_goods = (isset($general_settings['a2z_dhlexpress_dgs']) && $general_settings['a2z_dhlexpress_dgs'] == 'yes')  ? "<QtdShp><QtdShpExChrg><SpecialServiceType>HE</SpecialServiceType><LocalSpecialServiceType>XCH</LocalSpecialServiceType></QtdShpExChrg></QtdShp>" : ''; //danger goods type
					$duty_tax = (isset($general_settings['a2z_dhlexpress_duty_payment']) && ($general_settings['a2z_dhlexpress_duty_payment'] == "S") && ($dutiable == "Y")) ? "<QtdShp><QtdShpExChrg><SpecialServiceType>DD</SpecialServiceType></QtdShpExChrg></QtdShp>" : "";
					$xmlRequest =  file_get_contents(dirname(__FILE__) . '/xml/rate.xml');

					$pay_con = $value['a2z_dhlexpress_country'];

					if (isset($general_settings['a2z_dhlexpress_pay_con']) && $general_settings['a2z_dhlexpress_pay_con'] == "R") {
						$pay_con = $pack_aft_hook['destination']['country'];
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
					$xmlRequest = str_replace('{danger_goods}', $danger_goods, $xmlRequest);
					$xmlRequest = str_replace('{duty_tax}', $duty_tax, $xmlRequest);
					$xmlRequest = str_replace('{insurance_details}', $insurance_details, $xmlRequest);
					$xmlRequest = str_replace('{customerAddressIso}', $pack_aft_hook['destination']['country'], $xmlRequest);
					$xmlRequest = str_replace('{destination_postcode_city}', $destination_postcode_city, $xmlRequest);
					$xmlRequest = str_replace('{dutiable_content}', $dutiable_content, $xmlRequest);
					$request_url = (isset($general_settings['a2z_dhlexpress_test']) && $general_settings['a2z_dhlexpress_test'] != 'yes') ? 'https://xmlpi-ea.dhl.com/XMLShippingServlet?isUTF8Support=true' : 'https://xmlpitest-ea.dhl.com/XMLShippingServlet?isUTF8Support=true';
					$result = wp_remote_post($request_url, array(
						'method' => 'POST',
						'timeout' => 70,
						'sslverify' => 0,
						'body' => $xmlRequest,
						'sslverify'   => FALSE
					));

					libxml_use_internal_errors(true);
					if (is_array($result) && isset($result['body'])) {
						@$xml = simplexml_load_string(utf8_encode($result['body']));
					}
				}

				if (isset($general_settings['a2z_dhlexpress_developer_rate']) && $general_settings['a2z_dhlexpress_developer_rate'] == 'yes' && $admin_user) {
					echo "<h1> Request </h1><br/>";
					if (is_array($xmlRequest)) {
						print_r($xmlRequest);
					} else {
						print_r(htmlspecialchars($xmlRequest));
					}
					echo "<br/><h1> Response </h1><br/>";
					print_r($xml);
				}

				
				if ($xml && (isset($xml->GetQuoteResponse->BkgDetails->QtdShp) || isset($xml->products)) ) {
					$rate = $quotes = array();
					if (isset($xml->GetQuoteResponse->BkgDetails->QtdShp)) {
						$quotes = $xml->GetQuoteResponse->BkgDetails->QtdShp;
					} elseif ($xml->products) {
						$quotes = $xml->products;
					}
					if (empty($quotes)) {
						return;
					}
					
					foreach ($quotes as $quote) {
						$rate_code = isset($quote->GlobalProductCode) ? (string)$quote->GlobalProductCode : (string)$quote->productCode;
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
						if (empty($rate_cost) || (!empty($rate_cost) && $rate_cost <= 0)) {
							continue;
						}
						if (isset($general_settings['a2z_dhlexpress_excul_tax']) && $general_settings['a2z_dhlexpress_excul_tax'] == "yes") {
							$rate_tax = isset($quote->TotalTaxAmount) ? (float)((string) $quote->TotalTaxAmount) : 0;
							if (!empty($rate_tax) && $rate_tax > 0) {
								$rate_cost -= $rate_tax;
							}
						}

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
						
						$rate[$rate_code] = $rate_cost;
						$etd_time = '';
						if (isset($quote->DeliveryDate) && isset($quote->DeliveryTime)) {

							$formated_date = DateTime::createFromFormat('Y-m-d h:i:s', (string)$quote->DeliveryDate->DlvyDateTime);
							$etd_date = $formated_date->format('d/m/Y');
							$etd = apply_filters('hitstacks_dhlexpres_delivery_date', " (Etd.Delivery " . $etd_date . ")", $etd_date, $etd_time);
							// print_r($etd_date);print_r($etd_time);
							// print_r($etd);

							// die();
						} elseif (isset($quote->deliveryCapabilities->estimatedDeliveryDateAndTime)) {
							$dateTime = new DateTime((string)$quote->deliveryCapabilities->estimatedDeliveryDateAndTime);
							$etd_date = $dateTime->format('d/m/Y');
							$etd = apply_filters('hitstacks_dhlexpres_delivery_date', " (Etd.Delivery " . $etd_date . ")", $etd_date, $etd_time);
						}
					}

					$shipping_rates[$key] = $rate;
				}else{
					return;
				}
			}

			if (isset($general_settings['a2z_dhlexpress_developer_rate']) && $general_settings['a2z_dhlexpress_developer_rate'] == 'yes' && $admin_user) {
				die();
			}

			// Rate Processing



			if (!empty($shipping_rates)) {
				$i = 0;
				$final_price = array();
				foreach ($shipping_rates as $mkey => $rate) {
					$cheap_p = 0;
					$cheap_s = '';
					foreach ($rate as $key => $cvalue) {
						if ($i > 0) {

							if (!in_array($key, array('C', 'Q'))) {
								if ($cheap_p == 0 && $cheap_s == '') {
									$cheap_p = $cvalue;
									$cheap_s = $key;
								} else if ($cheap_p > $cvalue) {
									$cheap_p = $cvalue;
									$cheap_s = $key;
								}
							}
						} else {
							$final_price[] = array('price' => $cvalue, 'code' => $key, 'multi_v' => $mkey . '_' . $key);
						}
					}

					if ($cheap_p != 0 && $cheap_s != '') {
						foreach ($final_price as $key => $value) {
							$value['price'] = $value['price'] + $cheap_p;
							$value['multi_v'] = $value['multi_v'] . '|' . $mkey . '_' . $cheap_s;
							$final_price[$key] = $value;
						}
					}

					$i++;
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

				foreach ($final_price as $key => $value) {

					$rate_cost = $value['price'];
					$rate_code = $value['code'];
					$multi_ven = $value['multi_v'];

					if (!empty($general_settings['a2z_dhlexpress_carrier_adj_percentage'][$rate_code])) {
						$rate_cost += $rate_cost * ($general_settings['a2z_dhlexpress_carrier_adj_percentage'][$rate_code] / 100);
					}
					if (!empty($general_settings['a2z_dhlexpress_carrier_adj'][$rate_code])) {
						$rate_cost += $general_settings['a2z_dhlexpress_carrier_adj'][$rate_code];
					}

					$rate_cost = round($rate_cost, 2);

					$carriers_available = isset($general_settings['a2z_dhlexpress_carrier']) && is_array($general_settings['a2z_dhlexpress_carrier']) ? $general_settings['a2z_dhlexpress_carrier'] : array();

					$carriers_name_available = isset($general_settings['a2z_dhlexpress_carrier_name']) && is_array($general_settings['a2z_dhlexpress_carrier']) ? $general_settings['a2z_dhlexpress_carrier_name'] : array();

					if (array_key_exists($rate_code, $carriers_available)) {
						$name = isset($carriers_name_available[$rate_code]) && !empty($carriers_name_available[$rate_code]) ? $carriers_name_available[$rate_code] : $_dhl_carriers[$rate_code];

						$rate_cost = apply_filters('hitstacks_dhlexpress_rate_cost', $rate_cost, $rate_code, $order_total,$pack_aft_hook['destination']['country']);
						$rate_check_based_product = apply_filters("a2z_dhlexpress_rate_based_product", $dhl_packs,'false');
						if ($rate_cost <= 0) {
							$name .= ' - Free';
						}
						if (isset($general_settings['a2z_dhlexpress_etd_date']) && $general_settings['a2z_dhlexpress_etd_date'] == 'yes') {

							$name .= $etd;
						}

						if (!isset($general_settings['a2z_dhlexpress_v_rates']) || $general_settings['a2z_dhlexpress_v_rates'] != 'yes') {
							$multi_ven = '';
						}
										
						$rate = array(
							'id'       => 'a2z_dhlexpress:' . $rate_code,
							'label'    => $name,
							'cost'     => apply_filters("hitstacks_shipping_cost_conversion", $rate_cost, $package),
							'meta_data' => array('a2z_multi_ven' => $multi_ven, 'a2z_dhl_service' => $rate_code)
						);

						// Register the rate

						$this->add_rate($rate);
					}
				}
			}
		}

		public function hit_get_dhl_packages($package, $general_settings, $orderCurrency, $chk = false)
		{
			switch ($general_settings['a2z_dhlexpress_packing_type']) {
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
				include_once 'classes/weight_pack/class-hit-weight-packing.php';
			}
			$max_weight = isset($general_settings['a2z_dhlexpress_max_weight']) && $general_settings['a2z_dhlexpress_max_weight'] != ''  ? $general_settings['a2z_dhlexpress_max_weight'] : 10;
			$weight_pack = new WeightPack('pack_ascending');
			$weight_pack->set_max_weight($max_weight);

			$package_total_weight = 0;
			$insured_value = 0;

			$ctr = 0;
			foreach ($package as $item_id => $values) {
				$ctr++;
				$product = $values['data'];
				$product_data = $product->get_data();

				$get_prod = wc_get_product($values['product_id']);

				if (!isset($product_data['weight']) || empty($product_data['weight'])) {

					if ($get_prod->is_type('variable')) {
						$parent_prod_data = $product->get_parent_data();

						if (isset($parent_prod_data['weight']) && !empty($parent_prod_data['weight'])) {
							$product_data['weight'] = !empty($parent_prod_data['weight'] ? $parent_prod_data['weight'] : 0.001);
						} else {
							$product_data['weight'] = 0.001;
						}
					} else {
						$product_data['weight'] = 0.001;
					}
				}

				$chk_qty = $chk ? $values['product_quantity'] : $values['quantity'];

				$weight_pack->add_item($product_data['weight'], $values, $chk_qty);
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
				foreach ($packages as $package) { //pre($package);
					$packed_products = array();
					if (($package_count  ==  1) && isset($order_total)) {
						$insured_value  =  (isset($product_data['product_price']) ? $product_data['product_price'] : $product_data['price']) * (isset($values['product_quantity']) ? $values['product_quantity'] : $values['quantity']);
					} else {
						$insured_value  =   0;
						if (!empty($package['items'])) {
							foreach ($package['items'] as $item) {

								$insured_value        =   $insured_value; //+ $item->price;
							}
						} else {
							if (isset($order_total) && $package_count) {
								$insured_value  =   $order_total / $package_count;
							}
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
				include_once 'classes/hit-box-packing.php';
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
			foreach ($package as $item_id => $values) {

				$product = $values['data'];
				$product_data = $product->get_data();
				$get_prod = wc_get_product($values['product_id']);
				$parent_prod_data = [];

				if ($get_prod->is_type('variable')) {
					$parent_prod_data = $product->get_parent_data();
				}

				if (isset($product_data['weight']) && !empty($product_data['weight'])) {
					$item_weight = round($product_data['weight'] > 0.001 ? $product_data['weight'] : 0.001, 3);
				} else {
					$item_weight = (isset($parent_prod_data['weight']) && !empty($parent_prod_data['weight'])) ? (round($parent_prod_data['weight'] > 0.001 ? $parent_prod_data['weight'] : 0.001, 3)) : 0.001;
				}

				if (isset($product_data['width']) && isset($product_data['height']) && isset($product_data['length']) && !empty($product_data['width']) && !empty($product_data['height']) && !empty($product_data['length'])) {
					$item_dimension = array(
						'Length' => max(1, round($product_data['length'], 3)),
						'Width' => max(1, round($product_data['width'], 3)),
						'Height' => max(1, round($product_data['height'], 3))
					);
				} elseif (isset($parent_prod_data['width']) && isset($parent_prod_data['height']) && isset($parent_prod_data['length']) && !empty($parent_prod_data['width']) && !empty($parent_prod_data['height']) && !empty($parent_prod_data['length'])) {
					$item_dimension = array(
						'Length' => max(1, round($parent_prod_data['length'], 3)),
						'Width' => max(1, round($parent_prod_data['width'], 3)),
						'Height' => max(1, round($parent_prod_data['height'], 3))
					);
				}

				if (isset($item_weight) && isset($item_dimension)) {

					// $dimensions = array($values['depth'], $values['height'], $values['width']);
					$chk_qty = $chk ? $values['product_quantity'] : $values['quantity'];
					for ($i = 0; $i < $chk_qty; $i++) {
						$boxpack->add_item($item_dimension['Width'], $item_dimension['Height'], $item_dimension['Length'], $item_weight, round($product_data['price']), array(
							'data' => $values
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
			$to_ship = array();
			$group_id = 1;

			// Get weight of order
			foreach ($package as $item_id => $values) {
				$product = $values['data'];
				$product_data = $product->get_data();
				$get_prod = wc_get_product($values['product_id']);
				$parent_prod_data = [];

				if ($get_prod->is_type('variable')) {
					$parent_prod_data = $product->get_parent_data();
				}

				$group = array();
				$insurance_array = array(
					'Amount' => round($product_data['price']),
					'Currency' => $orderCurrency
				);

				if (isset($product_data['weight']) && !empty($product_data['weight'])) {
					$dhl_per_item_weight = round($product_data['weight'] > 0.001 ? $product_data['weight'] : 0.001, 3);
				} else {
					$dhl_per_item_weight = (isset($parent_prod_data['weight']) && !empty($parent_prod_data['weight'])) ? (round($parent_prod_data['weight'] > 0.001 ? $parent_prod_data['weight'] : 0.001, 3)) : 0.001;
				}

				$group = array(
					'GroupNumber' => $group_id,
					'GroupPackageCount' => 1,
					'Weight' => array(
						'Value' => $dhl_per_item_weight,
						'Units' => (isset($general_settings['a2z_dhlexpress_weight_unit']) && $general_settings['a2z_dhlexpress_weight_unit'] == 'KG_CM') ? 'KG' : 'LBS'
					),
					'packed_products' => $product
				);

				if (isset($product_data['width']) && isset($product_data['height']) && isset($product_data['length']) && !empty($product_data['width']) && !empty($product_data['height']) && !empty($product_data['length'])) {

					$group['Dimensions'] = array(
						'Length' => max(1, round($product_data['length'], 3)),
						'Width' => max(1, round($product_data['width'], 3)),
						'Height' => max(1, round($product_data['height'], 3)),
						'Units' => (isset($general_settings['a2z_dhlexpress_weight_unit']) && $general_settings['a2z_dhlexpress_weight_unit'] == 'KG_CM') ? 'CM' : 'IN'
					);
				} elseif (isset($parent_prod_data['width']) && isset($parent_prod_data['height']) && isset($parent_prod_data['length']) && !empty($parent_prod_data['width']) && !empty($parent_prod_data['height']) && !empty($parent_prod_data['length'])) {
					$group['Dimensions'] = array(
						'Length' => max(1, round($parent_prod_data['length'], 3)),
						'Width' => max(1, round($parent_prod_data['width'], 3)),
						'Height' => max(1, round($parent_prod_data['height'], 3)),
						'Units' => (isset($general_settings['a2z_dhlexpress_weight_unit']) && $general_settings['a2z_dhlexpress_weight_unit'] == 'KG_CM') ? 'CM' : 'IN'
					);
				}

				$group['packtype'] = 'BOX';

				$group['InsuredValue'] = $insurance_array;

				$chk_qty = $chk ? $values['product_quantity'] : $values['quantity'];

				for ($i = 0; $i < $chk_qty; $i++)
					$to_ship[] = $group;

				$group_id++;
			}

			return $to_ship;
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
		public function a2z_dhl_is_eu_country ($countrycode, $destinationcode) {
			$eu_countrycodes = array(
				'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 
				'ES', 'FI', 'FR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV',
				'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
				'HR', 'GR'

			);
			return(in_array($countrycode, $eu_countrycodes) && in_array($destinationcode, $eu_countrycodes));
		}
		/**
		 * Initialise Gateway Settings Form Fields
		 */
		public function init_form_fields()
		{
			$this->form_fields = array(
            'title'      => array(
                'title'       => __('Name', 'shipi'),
                'type'        => 'text',
                'description' => __('Your customers will see the name of this shipping method during checkout.', 'shipi'),
                'default'     => $this->method_title,
                'placeholder' => __('e.g. DHL Express', 'shipi'),
                'desc_tip'    => true,
            )
        );
		}
		public function generate_a2z_dhlexpress_html()
		{
			$general_settings = get_option('a2z_dhl_main_settings');
			$general_settings = empty($general_settings) ? array() : $general_settings;
			if(!empty($general_settings)){
				wp_redirect(admin_url('options-general.php?page=hit-dhl-express-configuration'));
			}

			if(isset($_POST['configure_the_plugin'])){
				// global $woocommerce;
				// $countries_obj   = new WC_Countries();
				// $countries   = $countries_obj->__get('countries');
				// $default_country = $countries_obj->get_base_country();

				// if(!isset($general_settings['a2z_dhlexpress_country'])){
				// 	$general_settings['a2z_dhlexpress_country'] = $default_country;
				// 	update_option('a2z_dhl_main_settings', $general_settings);
				
				// }
				wp_redirect(admin_url('options-general.php?page=hit-dhl-express-configuration'));	
			}
		?>
			<style>

			.card {
				background-color: #fff;
				border-radius: 5px;
				width: 800px;
				max-width: 800px;
				height: auto;
				text-align:center;
				margin: 10px auto 100px auto;
				box-shadow: 0px 1px 20px 1px hsla(213, 33%, 68%, .6);
			}  

			.content {
				padding: 20px 20px;
			}


			h2 {
				text-transform: uppercase;
				color: #000;
				font-weight: bold;
			}


			.boton {
				text-align: center;
			}

			.boton button {
				font-size: 18px;
				border: none;
				outline: none;
				color: #166DB4;
				text-transform: capitalize;
				background-color: #fff;
				cursor: pointer;
				font-weight: bold;
			}

			button:hover {
				text-decoration: underline;
				text-decoration-color: #166DB4;
			}
						</style>
						<!-- Fuente Mulish -->
						

			<div class="card">
				<div class="content">
					<div class="logo">
					<img src="<?php echo plugin_dir_url(__FILE__); ?>views/dhl.png" style="width:150px;" alt="logo DHL" />
					</div>
					<h2><strong>Shipi + DHL Express</strong></h2>
					<p style="font-size: 14px;line-height: 27px;">
					<?php _e('Welcome to Shipi! You are at just one-step ahead to configure the DHL Express with Shipi.','a2z_dhlexpress') ?><br>
					<?php _e('We have lot of features that will take your e-commerce store to another level.','a2z_dhlexpress') ?><br><br>
					<?php _e('Shipi helps you to save time, reduce errors, and worry less when you automate your tedious, manual tasks. Shipi + our plugin can generate shipping labels, Commercial invoice, display real time rates, track orders, audit shipments, and supports both domestic & international DHL services.','a2z_dhlexpress') ?><br><br>
					<?php _e('Make your customers happier by reacting faster and handling their service requests in a timely manner, meaning higher store reviews and more revenue.','a2z_dhlexpress') ?><br>
					</p>
						
				</div>
				<div class="boton" style="padding-bottom:10px;">
				<button class="button-primary" name="configure_the_plugin" style="padding:8px;">Configure the plugin</button>
				</div>
				</div>
			<?php
			echo '<style>button.button-primary.woocommerce-save-button{display:none;}</style>';
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
}

function a2z_dhlexpress_method( $methods )
{
	$methods['a2z_dhlexpress'] = 'A2Z_Dhlexpress'; 
	return $methods;
}
add_filter( 'woocommerce_shipping_methods', 'a2z_dhlexpress_method' );
