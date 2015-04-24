<?php

require_once 'CRM/Core/Page.php';

class CRM_Civicrmpostcodelookup_Page_CraftyClicks extends CRM_Core_Page {

	/*
	 * Function to get the Server URL and login credentials
	 */
	public static function getCraftyClicksCredentials() {
		#################
		#Server settings
		#################
		$settingsStr = CRM_Core_BAO_Setting::getItem('CiviCRM Postcode Lookup', 'api_details');
  	$settingsArray = unserialize($settingsStr);

		$servertarget = $settingsArray['server'];
		$apiKey = $settingsArray['api_key'];

		$querystring = "token=$apiKey&response=data_formatted&sort=asc";
		return $servertarget ."?" . $querystring;
	}

	/*
	 * Function to get address list based on a Post code
	 */
	public static function search() {
		$postcode = CRM_Utils_Request::retrieve('term', 'String', $this, true);

		$querystring = self::getCraftyClicksCredentials();
		$querystring = $querystring . "&postcode=" . urlencode($postcode);

		###############
		#File Handling
		###############

		##Open the JSON Document##
		$filetoparse = fopen("$querystring","r") or die("Error reading JSON data.");
		$data = stream_get_contents($filetoparse);
		$simpleJSONData = json_decode($data);

		if (!empty($simpleJSONData)) {
			if (isset($simpleJSONData->error_code)) {
				$addresslist[0]['value'] = '';
				$addresslist[0]['label'] = $simpleJSONData->error_msg;
			} else {
				$addresslist = self::getAddressList($simpleJSONData, $postcode);
			}
		}

		// highlight search results
		//$addresslist = CRM_Civicrmpostcodelookup_Utils::apply_highlight($addresslist, $postcode);

		##Close the JSON source##
		fclose($filetoparse);

		$config = CRM_Core_Config::singleton();
		if ($config->civiVersion < 4.5) {
			foreach ($addresslist as $key => $val) {
        echo "{$val['label']}|{$val['id']}\n";
      }
		} else {
			echo json_encode($addresslist);
		}
		exit;
	}

	private static function getAddressList($simpleJSONData, $postcode) {
		$addressList = array();
		$addressRow = array();
		$AddressListItem = $simpleJSONData->delivery_points;

		$craftyStore = array();

		foreach ($AddressListItem as $key => $addressItem) {
			$addressLineArray = array();
			//$addressLineArray[] = $addressItem->building_number;
			$addressLineArray[] = $addressItem->organisation_name;
			//$addressLineArray[] = $addressItem->building_name;
			//$addressLineArray[] = $addressItem->sub_building_name;
			$addressLineArray[] = $addressItem->line_1;
			$addressLineArray[] = $addressItem->line_2;
			$addressLineArray[] = $simpleJSONData->town;
			$addressLineArray[] = $simpleJSONData->postcode;

			$addressLineArray = array_filter($addressLineArray);

			$addressRow["id"] = count($addressList);
			$addressRow["value"] = $postcode;
			$addressRow["label"] = @implode(', ', $addressLineArray);
			array_push($addressList, $addressRow);

			$craftyArray["postcode"] = $simpleJSONData->postcode;
			$craftyArray["town"] = $simpleJSONData->town;
			$craftyArray["line_1"] = $addressItem->line_1;
			$craftyArray["line_2"] = $addressItem->line_2;
			$craftyArray["organisation_name"] = $addressItem->organisation_name;

			array_push($craftyStore, $craftyArray);
		}

		if (empty($addressList)) {
			$addressRow["id"] = '';
			$addressRow["value"] = '';
			$addressRow["label"] = 'Postcode Not Found';
			array_push($addressList, $addressRow);
		}
		$_SESSION['craftyStorage'][$postcode] = $craftyStore;
		return $addressList;
	}

	/*
	 * Function to get address details based on the CraftyClicks address id
	 */
	public static function getaddress() {
		$moniker = CRM_Utils_Request::retrieve('id', 'String', $this, true);

		$address = self::getAddressByMoniker($moniker);
		$response = array(
			'address' => $address
		);

		echo json_encode($response);
		exit;
	}

	private static function getAddressByMoniker($moniker) {

		$addressLineArray = $_SESSION['craftyStorage'][$moniker];

		$address["street"] = $addressLineArray["line_1"];
		$address["locality"] = $addressLineArray["line_2"];

		$address["town"] = $addressLineArray["town"];
		$address["postcode"] = $addressLineArray["postcode"];

		return $address;
	}
}
