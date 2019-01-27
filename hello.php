<?php
/**
 * @package Hello_Dolly
 * @version 1.7.1
 */
/*
Plugin Name: WooCommerce OmniPay.id VA
Description: Payment gateway OmniPay.ID Virtual Account for WooCommerce
Author: PT. Aneka Piranti Perkasa
Version: 1.0.0
Author URI: http://omnipay.co.id/developer
URL:
*/

namespace WooOmniPayID;

\add_filter('woocommerce_payment_gateways', 'WooOmniPayID\OmniPayID_init');

function OmniPayID_init($gateways) {
    require 'omnipay/class.php';

    $gateways[] = 'WooOmniPayID\OmnipayVA';
    return $gateways;
}