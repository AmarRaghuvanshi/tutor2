<?php
namespace Opencart\Catalog\Controller\Extension\PurpletreeMultivendor\Multivendor\Events\Checkout;
class Paymentaddress extends \Opencart\System\Engine\Controller {
	public function saveBefore(&$route, &$data) {
		if($this->config->get('module_purpletree_multivendor_status')){
			$this->session->data['config_stock_checkout_payment_addr_1'] = $this->config->get('config_stock_checkout');
			$this->config->set('config_stock_checkout',1);
		}
	}
	public function saveAfter(&$route, &$data, &$output) {
		if($this->config->get('module_purpletree_multivendor_status')){
			$this->config->set('config_stock_checkout',$this->session->data['config_stock_checkout_payment_addr_1']);
			
			$this->load->language('checkout/payment_address');

		$json = [];

		// Validate cart has products and has stock.
		$hasStock = $this->load->controller('extension/purpletree_multivendor/multivendor/events/checkout/cart|hasStock');
		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$hasStock && !$this->config->get('config_stock_checkout'))) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		// Validate minimum quantity requirements.
		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			if (!$product['minimum']) {
				$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);

				break;
			}
		}

		// Validate if customer is logged in or customer session data is not set
		if (!$this->customer->isLogged() || !isset($this->session->data['customer'])) {
			$json['redirect'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'), true);
		}

		// Validate if payment address is set if required in settings
		if (!$this->config->get('config_checkout_address')) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			$keys = [
				'firstname',
				'lastname',
				'company',
				'address_1',
				'address_2',
				'city',
				'postcode',
				'country_id',
				'zone_id',
				'custom_field'
			];

			foreach ($keys as $key) {
				if (!isset($this->request->post[$key])) {
					$this->request->post[$key] = '';
				}
			}
	if(version_compare(VERSION, '4.0.0.0', '>')){
			if ((Helper\Utf8\strlen($this->request->post['firstname']) < 1) || (Helper\Utf8\strlen($this->request->post['firstname']) > 32)) {
				$json['error']['payment_firstname'] = $this->language->get('error_firstname');
			}

			if ((Helper\Utf8\strlen($this->request->post['lastname']) < 1) || (Helper\Utf8\strlen($this->request->post['lastname']) > 32)) {
				$json['error']['payment_lastname'] = $this->language->get('error_lastname');
			}

			if ((Helper\Utf8\strlen($this->request->post['address_1']) < 3) || (Helper\Utf8\strlen($this->request->post['address_1']) > 128)) {
				$json['error']['payment_address_1'] = $this->language->get('error_address_1');
			}

			if ((Helper\Utf8\strlen($this->request->post['city']) < 2) || (Helper\Utf8\strlen($this->request->post['city']) > 32)) {
				$json['error']['payment_city'] = $this->language->get('error_city');
			}
		} else {
			if ((utf8_strlen($this->request->post['firstname']) < 1) || (utf8_strlen($this->request->post['firstname']) > 32)) {
				$json['error']['payment_firstname'] = $this->language->get('error_firstname');
			}

			if ((utf8_strlen($this->request->post['lastname']) < 1) || (utf8_strlen($this->request->post['lastname']) > 32)) {
				$json['error']['payment_lastname'] = $this->language->get('error_lastname');
			}

			if ((utf8_strlen($this->request->post['address_1']) < 3) || (utf8_strlen($this->request->post['address_1']) > 128)) {
				$json['error']['payment_address_1'] = $this->language->get('error_address_1');
			}

			if ((utf8_strlen($this->request->post['city']) < 2) || (utf8_strlen($this->request->post['city']) > 32)) {
				$json['error']['payment_city'] = $this->language->get('error_city');
			}	
		}

			$this->load->model('localisation/country');

			$country_info = $this->model_localisation_country->getCountry((int)$this->request->post['country_id']);
			if(version_compare(VERSION, '4.0.0.0', '>')){
						if ($country_info && $country_info['postcode_required'] && (Helper\Utf8\strlen($this->request->post['postcode']) < 2 || Helper\Utf8\strlen($this->request->post['postcode']) > 10)) {
							$json['error']['payment_postcode'] = $this->language->get('error_postcode');
						}
			} else {
				if ($country_info && $country_info['postcode_required'] && (utf8_strlen($this->request->post['postcode']) < 2 || utf8_strlen($this->request->post['postcode']) > 10)) {
							$json['error']['payment_postcode'] = $this->language->get('error_postcode');
						}
			}

			if ($this->request->post['country_id'] == '') {
				$json['error']['payment_country'] = $this->language->get('error_country');
			}

			if ($this->request->post['zone_id'] == '') {
				$json['error']['payment_zone'] = $this->language->get('error_zone');
			}

			// Custom field validation
			$this->load->model('account/custom_field');

			$custom_fields = $this->model_account_custom_field->getCustomFields($this->customer->getGroupId());

			foreach ($custom_fields as $custom_field) {
				if ($custom_field['location'] == 'address') {
					if ($custom_field['required'] && empty($this->request->post['custom_field'][$custom_field['custom_field_id']])) {
						$json['error']['payment_custom_field_' . $custom_field['custom_field_id']] = sprintf($this->language->get('error_custom_field'), $custom_field['name']);
					} elseif (($custom_field['type'] == 'text') && !empty($custom_field['validation']) && !preg_match(html_entity_decode($custom_field['validation'], ENT_QUOTES, 'UTF-8'), $this->request->post['custom_field'][$custom_field['custom_field_id']])) {
						$json['error']['payment_custom_field_' . $custom_field['custom_field_id']] = sprintf($this->language->get('error_regex'), $custom_field['name']);
					}
				}
			}
		}

		if (!$json) {
			// If no default address add it
			$address_id = $this->customer->getAddressId();

			if (!$address_id) {
				$this->request->post['default'] = 1;
			}

			$this->load->model('account/address');

			$json['address_id'] = $this->model_account_address->addAddress($this->customer->getId(), $this->request->post);

			$json['addresses'] = $this->model_account_address->getAddresses();

			if ($country_info) {
				$country = $country_info['name'];
				$iso_code_2 = $country_info['iso_code_2'];
				$iso_code_3 = $country_info['iso_code_3'];
				$address_format = $country_info['address_format'];
			} else {
				$country = '';
				$iso_code_2 = '';
				$iso_code_3 = '';
				$address_format = '';
			}

			$this->load->model('localisation/zone');

			$zone_info = $this->model_localisation_zone->getZone($this->request->post['zone_id']);

			if ($zone_info) {
				$zone = $zone_info['name'];
				$zone_code = $zone_info['code'];
			} else {
				$zone = '';
				$zone_code = '';
			}

			$this->session->data['payment_address'] = [
				'address_id'     => $json['address_id'],
				'firstname'      => $this->request->post['firstname'],
				'lastname'       => $this->request->post['lastname'],
				'company'        => $this->request->post['company'],
				'address_1'      => $this->request->post['address_1'],
				'address_2'      => $this->request->post['address_2'],
				'city'           => $this->request->post['city'],
				'postcode'       => $this->request->post['postcode'],
				'zone_id'        => $this->request->post['zone_id'],
				'zone'           => $zone,
				'zone_code'      => $zone_code,
				'country_id'     => $this->request->post['country_id'],
				'country'        => $country,
				'iso_code_2'     => $iso_code_2,
				'iso_code_3'     => $iso_code_3,
				'address_format' => $address_format,
				'custom_field'   => isset($this->request->post['custom_field']) ? $this->request->post['custom_field'] : []
			];

			$json['success'] = $this->language->get('text_success');

			unset($this->session->data['payment_methods']);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
		
		}
	}
	
	public function addressBefore(&$route, &$data) {
		if($this->config->get('module_purpletree_multivendor_status')){
			$this->session->data['config_stock_checkout_payment_addr_2'] = $this->config->get('config_stock_checkout');
			$this->config->set('config_stock_checkout',1);
		}
	}
	public function addressAfter(&$route, &$data, &$output) {
		if($this->config->get('module_purpletree_multivendor_status')){
			$this->config->set('config_stock_checkout',$this->session->data['config_stock_checkout_payment_addr_2']);
			
			$this->load->language('checkout/payment_address');

		$json = [];

		if (isset($this->request->get['address_id'])) {
			$address_id = (int)$this->request->get['address_id'];
		} else {
			$address_id = 0;
		}

		if (!isset($this->session->data['customer'])) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		// Validate cart has products and has stock.
		$hasStock = $this->load->controller('extension/purpletree_multivendor/multivendor/events/checkout/cart|hasStock');
		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$hasStock && !$this->config->get('config_stock_checkout'))) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		// Validate minimum quantity requirements.
		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			if (!$product['minimum']) {
				$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);

				break;
			}
		}

		// Validate if customer is logged in or customer session data is not set
		if (!$this->customer->isLogged() || !isset($this->session->data['customer'])) {
			$json['redirect'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'), true);
		}

		// Validate if payment address is set if required in settings
		if (!$this->config->get('config_checkout_address')) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			$this->load->model('account/address');

			$address_info = $this->model_account_address->getAddress($address_id);

			if (!$address_info) {
				$json['error'] = $this->language->get('error_address');
			}
		}

		if (!$json) {
			$this->session->data['payment_address'] = $address_info;

			$json['success'] = $this->language->get('text_success');

			unset($this->session->data['payment_methods']);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
		
		}
	}
}?>