<?php

	class dhl_rest
	{
		public $mock_url = "https://api-mock.dhl.com/mydhlapi/";
		public $test_url = "https://express.api.dhl.com/mydhlapi/test/";
		public $live_url = "https://express.api.dhl.com/mydhlapi/";
		public $orderId;
		public $orderCurrency;
		public $dhlCurrency;
		public $dhlCurrConRate = 1;
		public $totPackWeg = 0;
		public $totPackCost = 0;
		public $shipContent;
		public $disableDutiable = "N";
		public $serviceCode;
		public $trk_no;
		public $hitInvoiceB64;
		public function __construct()
		{
			
		}
		public function createRateReq($dhl_packs, $gen_set, $ship_addr, $rec_addr, $add_date=0, $vendor_key = 'default')
		{
			$this->createPackTotals($dhl_packs);
			
			$timezone = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $ship_addr["country"]);
			$selected_timezone = $timezone[0];
			// Create a DateTime object with the current time in the selected timezone
			$current_time_now = new DateTime('now', new DateTimeZone($selected_timezone));

			// Format the current time
			$current_time = $current_time_now->format('Y-m-d\TH:i:s\G\M\TP');

			// a2z_dhlexpress_pickup_date , $ship_aar['country']
			if(isset($gen_set["a2z_dhlexpress_pickup_type"]) && isset($gen_set["a2z_dhlexpress_pickup_date"]) && isset($ship_addr["country"])){
				if($gen_set["a2z_dhlexpress_pickup_type"] != "S"){
					$add_date += $gen_set["a2z_dhlexpress_pickup_date"];
				}
			}

			// Add days and time if add_date is set
			if ($add_date > 0) {
				$current_time_now->modify("+".$add_date." day");
			} else {
				// Add 1 hour if add_date is not set
				$current_time_now->modify('+1 hour');
				
				// Check if the hour is after 10 PM
				$hour = (int)$current_time_now->format('H');
				if ($hour >= 22) {
					// If so, add 1 day
					$current_time_now->modify('+1 day');
					// Set the time to 14:00:00
					$current_time_now->setTime(14, 0, 0);
				}
			}
			$current_time_now->setTime(14, 0, 0);

			// check rating date is week day or not		
			$day = (int) $current_time_now->format('N');
			
			if ($day > 5) {
				// If it's not a weekday, add 2 days if Saturday, 1 day if Sunday
				if($day == 6){
					$current_time_now->modify('+2 day');
				}else{
					$current_time_now->modify('+1 day');
				}
			}

			$new_time_formatted =  $current_time_now->format('Y-m-d\TH:i:s\G\M\TP');


			$rate_req = [];
			$rate_req['customerDetails']['shipperDetails'] = $this->makeAddrInfo($ship_addr);
			$rate_req['customerDetails']['receiverDetails'] = $this->makeAddrInfo($rec_addr);
			$rate_req['accounts'] = array(
				array(
					"typeCode" => "shipper",
					"number" => isset($ship_addr['acc_no']) ? $ship_addr['acc_no'] : ""
				)
			);
			$req_data['payerCountryCode'] = isset($ship_addr['country']) ? $ship_addr['country'] : "";
			if (isset($gen_set['a2z_dhlexpress_pay_con']) && ($gen_set['a2z_dhlexpress_pay_con'] == "R")) {
				$req_data['payerCountryCode'] = isset($rec_addr['country']) ? $rec_addr['country'] : "";
			} elseif (isset($gen_set['a2z_dhlexpress_pay_con']) && ($gen_set['a2z_dhlexpress_pay_con'] == "C") && isset($gen_set['a2z_dhlexpress_cus_pay_con']) && !empty($gen_set['a2z_dhlexpress_cus_pay_con'])) {
				$req_data['payerCountryCode'] = $gen_set['a2z_dhlexpress_cus_pay_con'];
			}
			$rate_req['plannedShippingDateAndTime'] = $new_time_formatted;
			$rate_req['unitOfMeasurement'] = (isset($gen_set['a2z_dhlexpress_weight_unit']) && $gen_set['a2z_dhlexpress_weight_unit'] == 'KG_CM') ? 'metric' : 'imperial';
			$rate_req['isCustomsDeclarable'] = $this->checkDutiable($ship_addr, $rec_addr);
			if ($rate_req['isCustomsDeclarable'] == true) {
				$rate_req['monetaryAmount'][] = ['typeCode' => 'declaredValue', 'value' => round($this->totPackCost, 2), 'currency' => $this->dhlCurrency];
			}
			if (isset($gen_set['a2z_dhlexpress_insure']) && ($gen_set['a2z_dhlexpress_insure'] == "yes")) {
				$rate_req['monetaryAmount'][] = ['typeCode' => 'insuredValue', 'value' => round((float) apply_filters("hitshipo_ins_val_ship", $this->totPackCost, $vendor_key, $dhl_packs), 2), 'currency' => $this->dhlCurrency];
				$rate_req['valueAddedServices'][] = ['serviceCode' => 'II', 'value' => round((float) apply_filters("hitshipo_ins_val_ship", $this->totPackCost, $vendor_key, $dhl_packs), 2), 'currency' => $this->dhlCurrency];
			}
			if (isset($gen_set['a2z_dhlexpress_sat']) && ($gen_set['a2z_dhlexpress_sat'] == "yes")) {
				$rate_req['valueAddedServices'][] = ['serviceCode' => 'AA'];
			}
			$rate_req['estimatedDeliveryDate'] = ['isRequested' => true];
			$rate_req['packages'] = $this->makePackInfo($dhl_packs);
			return $rate_req;
		}
		private function makePackInfo($dhl_packs=[])
		{
			$pack_info = [];
			if (!empty($dhl_packs)) {
				foreach ($dhl_packs as $key => $pack) {
					$pack_info[] = [
						'weight' => isset($pack['Weight']['Value']) ? (float)$pack['Weight']['Value'] : 0.5,
						'dimensions' => [
							'length' => isset($pack['Dimensions']['Length']) ? (float)$pack['Dimensions']['Length'] : 1,
							'width' => isset($pack['Dimensions']['Width']) ? (float)$pack['Dimensions']['Width'] : 1,
							'height' => isset($pack['Dimensions']['Height']) ? (float)$pack['Dimensions']['Height'] : 1
						]
					];
				}
			}
			return $pack_info;
		}
		private function makeAddrInfo($addr=[])
		{
			$addr_info = [];
			$addr_info['postalCode'] = isset($addr['postcode']) ? $addr['postcode'] : "";
			$addr_info['cityName'] = isset($addr['city']) ? $addr['city'] : "";
			$addr_info['countryCode'] = isset($addr['country']) ? $addr['country'] : "";
			if (isset($addr['state']) && !empty($addr['state']) && strlen($addr['state']) > 1) {
				$addr_info['provinceCode'] = $addr['state'];
			}
			if (isset($addr['address_1']) && !empty($addr['address_1'])) {
				$addr_info['addressLine1'] = $addr['address_1'];
			}
			if (isset($addr['address_2']) && !empty($addr['address_2'])) {
				$addr_info['addressLine2'] = $addr['address_2'];
			}
			return $addr_info;
		}
		private function checkDutiable($ship_addr=[], $rec_addr=[])
		{
			$dutiable = true;
			if (isset($ship_addr['country']) && isset($rec_addr['country'])) {
				if ($ship_addr['country'] == $rec_addr['country']) {
					$dutiable = false;
				}
				if ($this->hit_dhl_is_eu_country($ship_addr['country'], $rec_addr['country'])) {
					$dutiable = false;
				}
			}
			return $dutiable;
		}
		private function hit_dhl_is_eu_country($countrycode, $destinationcode)
		{
			$eu_countrycodes = array(
				'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE',
				'ES', 'FI', 'FR', 'GB', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV',
				'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
				'HR', 'GR'
			);
			return (in_array($countrycode, $eu_countrycodes) && in_array($destinationcode, $eu_countrycodes));
		}
		private function createPackTotals($dhl_packages)
		{
			$total_value = 0;
			$total_weg = 0;
			if ($dhl_packages) {
				foreach ($dhl_packages as $key => $parcel) {
					$total_value += $parcel['InsuredValue']['Amount'];
					$total_weg += $parcel['Weight']['Value'];
				}
			}
			$this->totPackWeg = $total_weg;
			$this->totPackCost = round($total_value, 2);
		}
		public function getRes($req_data=[], $mode="test", $api_key="", $api_sec="", $type="")
		{
			$req_url = "";
			if ($type == "rate") {
				$req_url = ($mode == "test") ? $this->test_url."rates" : $this->live_url."rates";
			} elseif ($type == "ship") {
				$req_url = ($mode == "test") ? $this->test_url."shipments" : $this->live_url."shipments";
			} elseif ($type == "pickup") {
				$req_url = ($mode == "test") ? $this->test_url."pickups" : $this->live_url."pickups";
			}
			$response = wp_remote_post( $req_url , array(
					'method'      => 'POST',
					'timeout'     => 45,
					'redirection' => 5,
					'httpversion' => '1.0',
					'blocking'    => true,
					'headers'     => array(
						'Content-Type' => 'application/json',
						'Authorization' => 'Basic '.base64_encode($api_key.':'.$api_sec),
						'Plugin-Name' => 'Shipi - DHL Express'
					),
					'body'        => json_encode($req_data),
					'sslverify'   => FALSE
					)
				);
			$res_arr = [];
			if (isset($response['body']) && !empty($response['body'])) {
				$res_arr = json_decode($response['body']);
			}
			return $res_arr;
		}
	}