<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2018-2019 Wooppay
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @copyright   Copyright (c) 2018-2019 Wooppay
 * @author      Kolesnikov Igor <ikolesnikov@wooppay.com>
 * @version     1.1.5 mobile
 */

class WC_Gateway_Wooppay_Mobile extends WC_Payment_Gateway
{
	private const KZ_AVAILABLE_OPERATORS = [
		'beeline',
		'activ',
		'kcell'
	];

	private const TJ_AVAILABLE_OPERATORS = [
		'tcell',
		'megafon',
		'zetmobile',
		'babilon'
	];

	public function __construct()
	{
		$this->id = 'wooppay_mobile';
		$this->icon = apply_filters('woocommerce_wooppay_icon',
			plugins_url() . '/wooppay-1.1.5 mobile/assets/images/wooppay_mobile.svg');
		$this->has_fields = false;
		$this->method_title = __('WOOPPAY', 'Wooppay');
		$this->init_form_fields();
		$this->init_settings();
		$this->title = $this->settings['title'];
		$this->description = $this->settings['description'];
		$this->instructions = $this->get_option('instructions');
		$this->enable_for_methods = $this->get_option('enable_for_methods', array());
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
	}

	public function check_response($order_id, $order_key, $operation_id)
	{
		if (isset($order_id) && isset($order_key) && isset($operation_id)) {
			$order = wc_get_order((int)$order_id);
			if ($order && $order->key_is_valid($order_key)) {
				try {
					include_once('WooppaySoapClient.php');
					$client = new WooppaySoapClient($this->get_option('api_url'), array('trace' => 1));
					if ($client->login($this->get_option('api_username'), $this->get_option('api_password'))) {
						$count = 0;
						do {
							$status = $client->getOperationData((int)$operation_id)->response->records[0]->status;
							sleep(5);
							$count++;
						} while ($count < 10 && $status == WooppayOperationStatus::OPERATION_STATUS_NEW || $status == WooppayOperationStatus::OPERATION_STATUS_CONSIDER);
						$status = $client->getOperationData((int)$operation_id)->response->records[0]->status;
						if ($status == WooppayOperationStatus::OPERATION_STATUS_DONE || $status == WooppayOperationStatus::OPERATION_STATUS_WAITING) {
							$order->update_status('completed', __('Payment completed.', 'woocommerce'));
							return '{"data":1}';
						}
					}
				} catch (Exception $e) {
					$this->add_log($e->getMessage());
					if ($e->getCode() == 606) {
						throw new Exception("auth_failed");
						return null;
//						wc_add_notice('Оплата не прошла аутентификацию', 'error');
					} else {
						wc_add_notice(__('Wooppay error:', 'woocommerce') . $e->getMessage() . print_r($order, true),
							'error');
					}

				}
			} else {
				$this->add_log('Error order key: ' . print_r($_REQUEST, true));
			}
		} else {
			$this->add_log('Error call back: ' . print_r($_REQUEST, true));
		}
		return '{"data":1}';
	}

	/* Admin Panel Options.*/
	public function admin_options()
	{
		?>
        <h3><?php _e('Wooppay', 'wooppay_mobile'); ?></h3>
        <table class="form-table">
			<?php $this->generate_settings_html(); ?>
        </table> <?php
	}

	/* Initialise Gateway Settings Form Fields. */
	public function init_form_fields()
	{
		global $woocommerce;

		$shipping_methods = array();

		if (is_admin()) {
			foreach ($woocommerce->shipping->load_shipping_methods() as $method) {
				$shipping_methods[$method->id] = $method->get_title();
			}
		}

		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable/Disable', 'wooppay_mobile'),
				'type' => 'checkbox',
				'label' => __('Enable Wooppay Mobile', 'wooppay_mobile'),
				'default' => 'no'
			),
			'title' => array(
				'title' => __('Title', 'wooppay_mobile'),
				'type' => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'wooppay_mobile'),
				'desc_tip' => true,
				'default' => __('Wooppay Mobile', 'wooppay_mobile')
			),
			'description' => array(
				'title' => __('Description', 'wooppay_mobile'),
				'type' => 'textarea',
				'description' => __('This controls the description which the user sees during checkout.',
					'wooppay_mobile'),
				'default' => __('Оплата с номера мобильного телефона.', 'wooppay_mobile')
			),
			'instructions' => array(
				'title' => __('Instructions', 'wooppay_mobile'),
				'type' => 'textarea',
				'description' => __('Instructions that will be added to the thank you page.', 'wooppay_mobile'),
				'default' => __('Введите все необходимые данные, нажмите кнопку отправить, введите код из смс и нажмите кнопку отправить повторно.',
					'wooppay_mobile')
			),
			'api_details' => array(
				'title' => __('API Credentials', 'wooppay_mobile'),
				'type' => 'title',
			),
			'api_url' => array(
				'title' => __('API URL', 'wooppay_mobile'),
				'type' => 'text',
				'description' => __('Get your API credentials from Wooppay.', 'wooppay_mobile'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay_mobile')
			),
			'api_username' => array(
				'title' => __('API Username', 'wooppay_mobile'),
				'type' => 'text',
				'description' => __('Get your API credentials from Wooppay.', 'wooppay_mobile'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay_mobile')
			),
			'api_password' => array(
				'title' => __('API Password', 'wooppay_mobile'),
				'type' => 'text',
				'description' => __('Get your API credentials from Wooppay.', 'wooppay_mobile'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay_mobile')
			),
			'order_prefix' => array(
				'title' => __('Order prefix', 'wooppay_mobile'),
				'type' => 'text',
				'description' => __('Order prefix', 'wooppay_mobile'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay_mobile')
			),
			'service_name' => array(
				'title' => __('Service name', 'wooppay_mobile'),
				'type' => 'text',
				'description' => __('Service name', 'wooppay_mobile'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay_mobile')
			),
		);

	}

	function process_payment($order_id)
	{
		include_once('WooppaySoapClient.php');
		global $woocommerce;
		session_start();
		$order = new WC_Order($order_id);
		$code = $order->get_customer_note();
		if (isset($_SESSION[$order_id . 'flag']) && isset($code)) {
			try {
				if (empty($code)) {
					throw new Exception("empty_sms_code");
				}
				$client = new WooppaySoapClient($this->get_option('api_url'));
				$order->save();
				if ($client->login($this->get_option('api_username'), $this->get_option('api_password'))) {
					$phone = preg_replace('/[^0-9]/', '', $order->get_billing_phone());
					$operator = $client->checkOperator($phone);
					$operator = $operator->response->operator;
					if (in_array($operator, self::KZ_AVAILABLE_OPERATORS)) {
						$phone = substr($phone, 1);
					}
					$backUrl = $this->get_return_url($order);
					$requestUrl = WC()->api_request_url('WC_Gateway_Wooppay_Mobile') . '?id_order=' . $order_id . '&key=' . $order->order_key;
					$orderPrefix = $this->get_option('order_prefix');
					$serviceName = $this->get_option('service_name');
					$invoice = $client->createInvoice($orderPrefix . '_' . $order_id, $backUrl, $requestUrl,
						$order->total, $serviceName, $code, '', $order->description, $order->billing_email, $phone);
					$woocommerce->cart->empty_cart();
					$order->update_status('pending', __('Payment Pending.', 'woocommerce'));
					unset($_SESSION["note"]);
					unset($_SESSION[$order_id . 'flag']);
					return array(
						'result' => 'success',
						'redirect' => $backUrl
					);
				}
			} catch (Exception $e) {
				if ($e->getCode() == 603) {
					wc_add_notice(__('Недопустимый сотовый оператор для оплаты с мобильного телефона. Допустимые операторы Activ, Kcell, Beeline, Tcell, Megafon Tj, Zet-Mobile, Babilon-M',
						'woocommerce'), 'error');
				} elseif ($e->getCode() == 223) {
					wc_add_notice(__('Неверный код подтверждения.', 'woocommerce'), 'error');
				} elseif ($e->getCode() == 224) {
					wc_add_notice(__('Вы ввели неверный код подтверждения слишком много раз. Попробуйте через 5 минут.',
						'woocommerce'), 'error');
				} elseif ($e->getCode() == 226) {
					wc_add_notice(__('У вас недостаточно средств на балансе мобильного телефона.', 'woocommerce'),
						'error');
				} elseif ($e->getMessage() == 'empty_sms_code') {
					wc_add_notice('В поле комментария отсутствует смс код', 'error');
				} elseif ($e->getMessage() == 'auth_failed'){
					wc_add_notice('Оплата не прошла аутентификацию', 'error');
				} else {
					$this->add_log($e->getMessage());
					wc_add_notice(__('Wooppay error:', 'woocommerce') . $e->getCode(), 'error');
				}
			}
		} else {
			try {
				if (isset($_SESSION["note"]) || isset($_SESSION[$order_id . 'flag'])) {
					unset($_SESSION["note"]);
					unset($_SESSION[$order_id . 'flag']);
				}
				session_start();
				$phone = $order->get_billing_phone();
				$phone = preg_replace('/[^0-9]/', '', $phone);
				$client = new WooppaySoapClient($this->get_option('api_url'));
				if ($client->login($this->get_option('api_username'), $this->get_option('api_password'))) {
					$operator = $client->checkOperator($phone);
					$operator = $operator->response->operator;
					if (in_array($operator, array_merge(self::KZ_AVAILABLE_OPERATORS, self::TJ_AVAILABLE_OPERATORS))) {
						if (in_array($phone, self::KZ_AVAILABLE_OPERATORS)) {
							$phone = substr($phone, 1);
						}
						$client->requestConfirmationCode($phone);
						wc_add_notice(__('Введите код из смс в поле комментария и нажмите отправить заказ. Ваш комментарий уже был сохранён.',
							'woocommerce'));
						$_SESSION[$order_id . 'flag'] = '';
						$_SESSION["note"] = $order->get_customer_note();
					} else {
						wc_add_notice(__('Недопустимый сотовый оператор для оплаты с мобильного телефона. Допустимые операторы Activ, Kcell, Beeline, Tcell, Megafon Tj, Zet-Mobile, Babilon-M.', 'woocommerce'), 'error');
					}
				}
			} catch (Exception $e) {
				if ($e->getCode() == 222) {
					wc_add_notice(__('Вы уже запрашивали код подтверждения на данный номер в течение предыдущих 5 минут',
						'woocommerce'), 'error');
				} else {
					$this->add_log($e->getMessage());
					wc_add_notice(__('Wooppay error:', 'woocommerce') . $e->getCode(), 'error');
				}
			}
		}
	}

	function thankyou()
	{
		echo $this->instructions != '' ? wpautop($this->instructions) : '';
	}

	function add_log($message)
	{
		if ($this->debug == 'yes') {
			if (empty($this->log)) {
				$this->log = new WC_Logger();
			}
			$this->log->add('Wooppay', $message);
		}
	}
}
