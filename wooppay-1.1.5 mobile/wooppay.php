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
 * @wordpress-plugin
 * Plugin Name:       WooCommerce Wooppay Mobile Payment Gateways
 * Plugin URI:
 * Description:       Add Wooppay Mobile Payment Gateways for WooCommerce.
 * Version:           1.1.5 mobile
 * Author:            Kolesnikov Igor
 * License:           The MIT License (MIT)
 *
 */

function wc_cpg_fallback_notice()
{
	echo '<div class="error"><p>' . sprintf(__('WooCommerce Wooppay Mobile Payments depends on the last version of %s to work!',
			'wooppay_mobile'),
			'<a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>') . '</p></div>';
}

function wc_custom_payment_gateway_load()
{
	if (!class_exists('WC_Payment_Gateway')) {
		add_action('admin_notices', 'wc_cpg_fallback_notice');
		return;
	}

	function wc_custom_add_mobile_gateway($methods)
	{
		$methods[] = 'WC_Gateway_Wooppay_Mobile';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'wc_custom_add_mobile_gateway');


	require_once plugin_dir_path(__FILE__) . 'class.wooppay.php';
}

add_action('plugins_loaded', 'wc_custom_payment_gateway_load', 0);

function wc_cpg_action_links($links)
{
	$settings = array(
		'settings' => sprintf(
			'<a href="%s">%s</a>',
			admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_gateway_wooppay_mobile'),
			__('Payment Gateways', 'wooppay_mobile')
		)
	);

	return array_merge($settings, $links);
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_cpg_action_links');

function init_assets(){
	wp_register_script('prefix_bootstrap_js', plugins_url('/assets/bootstrap/js/bootstrap.min.js', __FILE__), array('jquery'));
	wp_enqueue_script('prefix_bootstrap_js');

	wp_register_style('prefix_bootstrap_style', plugins_url('/assets/bootstrap/css/bootstrap.min.css', __FILE__));
	wp_enqueue_style('prefix_bootstrap_style');

	wp_register_style('prefix_modal_style', plugins_url('/css/modal.css', __FILE__));
	wp_enqueue_style('prefix_modal_style');

	wp_register_style('prefix_style', plugins_url('/css/style.css', __FILE__));
	wp_enqueue_style('prefix_style');
}

add_action('woocommerce_checkout_init', 'init_assets');

function add_bootstrap_modal()
{
	?>
	<div class="modal fade" id="pleaseWaitDialog" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
	     aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-body">
					<img class="modal__preloader" src="<?php echo plugins_url() . '/wooppay-1.1.5 mobile/assets/images/preloader.gif'?>" alt="Preloader" width="146" height="146">
				</div>
			</div>
		</div>
	</div>
	<?php
}

add_action('woocommerce_checkout_init', 'add_bootstrap_modal');

add_action('woocommerce_checkout_init', 'modal_action_javascript', 99);
function modal_action_javascript()
{
	?>
	<script>
        document.addEventListener('DOMContentLoaded', function () {
            function beforeSend() {
                jQuery('#pleaseWaitDialog').modal('show');
                jQuery('#pleaseWaitDialog').css("display" , 'flex');
            }
            function logSubmit(event) {
                beforeSend();
                jQuery(document).ajaxComplete(function (){
                    jQuery('#pleaseWaitDialog').modal('hide');
                })
            }
            const form = document.getElementsByClassName('checkout');
            form[0].addEventListener('submit', logSubmit);
        });
	</script>
	<?php
}
?>