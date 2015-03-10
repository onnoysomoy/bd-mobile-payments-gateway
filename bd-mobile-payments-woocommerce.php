<?php
/*
Plugin Name: BD Mobile Payments Gateway
Plugin URI: http://www.areuconnected.com/plugins/bd-mobile-payment-gateway/
Description: BD Mobile Payment gateway and BDT currency symbol support for woocommerce.
Version: 1.1
Author: Jabed Shoeb
Author URI: http://www.areuconnected.com
License: GPLv2
*/


//Additional links on the plugin page
add_filter( 'plugin_row_meta', 'bd_mobile_payments_register_plugin_links', 10, 2 );
function bd_mobile_payments_register_plugin_links($links, $file) {
	$base = plugin_basename(__FILE__);
	if ($file == $base) {
		$links[] = '<a href="http://www.areuconnected.com/" target="_blank">' . __( 'Are You Connected', 'rsb' ) . '</a>';
		$links[] = '<a href="http://www.xbwebhosting.com/" target="_blank">' . __( 'XB Web Hosting', 'rsb' ) . '</a>';
	}
	return $links;
}



add_filter( 'woocommerce_currencies', 'bd_mobile_payments_add_bdt_currency' );
function bd_mobile_payments_add_bdt_currency( $currencies ) {
$currencies['BDT'] = __( 'Bangladeshi Taka', 'woocommerce' );
return $currencies;
}
 
add_filter('woocommerce_currency_symbol', 'bd_mobile_payments_add_bdt_currency_symbol', 10, 2);
function bd_mobile_payments_add_bdt_currency_symbol( $currency_symbol, $currency ) {
switch( $currency ) {
case 'BDT': $currency_symbol = '&#2547;&nbsp;'; break;
}
return $currency_symbol;
}

/**********************************Bangladeshi Mobile Payment Gateways*******************/

add_action('plugins_loaded', 'wc_bd_mobile_payment_gateway', 0);
function wc_bd_mobile_payment_gateway(){
  if(!class_exists('WC_Payment_Gateway')) {
	  add_action( 'admin_notices', 'wc_bd_mobile_payments_gateway_fallback_notice' );
	  
	  /* WooCommerce fallback notice. */
	function wc_bd_mobile_payments_gateway_fallback_notice() {
   			 echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Mobile Banking Payment Gateways depends on the last version of %s to work!', 'woocommerce' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>' ) . '</p></div>';
		}

	  return;
  }
  
    function woocommerce_add_bkash_dbblmb_gateway($methods) {
        $methods[] = 'WC_Gateway_bKash';
        $methods[] = 'WC_Gateway_dbblmb';
        return $methods;
    }
 
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_bkash_dbblmb_gateway' );
	
	// Include the WC_bKash_Gateway class.
    require_once plugin_dir_path( __FILE__ ) . 'includes/gateways/bkash/class-wc-gateway-bKash.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/gateways/dbblmb/class-wc-gateway-dbblmb.php';
}

/* Adds custom settings url in plugins page. */
function wc_bd_mobile_payments_gateway_action_links( $links ) {
    $settings = array(
		'settings' => sprintf(
		'<a href="%s">%s</a>',
		admin_url( 'admin.php?page=wc-settings&tab=checkout' ),
		__( 'Payment Gateways', 'woocommerce' )
		)
    );

    return array_merge( $settings, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_bd_mobile_payments_gateway_action_links' );