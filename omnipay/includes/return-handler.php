<?php

namespace WooOmniPayID;

if (!defined('ABSPATH')) {
    exit;
}


class ReturnHandler
{
    var $verify_key;
    var $merchant_id;

    /**
     * Constructor.
     *   this will be called when a request is made to
     *   http://localhost/woo/?wc-api=omnipay-va-id
     */
    public function __construct($verify_key, $merchant_id)
    {
        // Now we set that function up to execute when the admin_notices action is called
        add_action('woocommerce_api_omnipay-va-id', array($this, 'check_callback'));

        $this->verify_key = $verify_key;
        $this->merchant_id = $merchant_id;
    }


    public function check_callback()
    {
        if (!empty($_POST)) { // WPCS: CSRF ok.
            $posted = wp_unslash($_POST); // WPCS: CSRF ok, input var ok.

            $posted_note = '';
            foreach ($_POST as $k => $v) {
                $vv = htmlentities($v);
                $kk = htmlentities($k);
                $posted_note .= "\n[$kk] = $vv";
            }

            $verifyKey = $this->verify_key;

            // verify signature here
            $tranID = $_POST['tranID'];
            $orderid = $_POST['orderid'];
            $status = $_POST['status'];
            $domain = $_POST['domain'];
            $amount = $_POST['amount'];
            $currency = $_POST['currency'];
            $appcode = $_POST['appcode'];
            $paydate = $_POST['paydate'];
            $channel = $_POST['channel'];
            $skey = $_POST['skey'];
            $key0 = md5($tranID . $orderid . $status . $domain . $amount . $currency);
            $key1 = md5($paydate . $domain . $key0 . $appcode . $verifyKey);

            $fx_amount = $_POST['fx_amount'];
            $fx_currency = $_POST['fx_currency'];
            $fx_rate = $_POST['fx_rate'];
            $fx_key = $_POST['fx_skey'];

            // we need to check this values
            $check_amount = $amount;
            $check_currency = $currency;

            // asumsi bahwa pembayaran dalam IDR, jadi default pemeriksaan parameter fx adalah "true"
            $fx_ok = true;

            // kalau server mengirimkan $_POST['fx_key'] artinya tagihan tidak dalam IDR,
            // tetapi uang yg diterima dalam BANK, SELALU dalam IDR
            if (!empty($fx_key)) {
                $calc_fx_key = md5($verifyKey . $fx_amount . $fx_currency . $fx_rate . $tranID . $orderid);
                $fx_ok = ($calc_fx_key === $fx_key);

                // we need to check amount and currency before it gets exchanged to IDR
                $check_amount = $fx_amount;
                $check_currency = $fx_currency;

                // check amount paid in IDR
                if ($fx_ok) {
                    // deviasi 5 rupiah
                    $fx_ok = abs($amount - $fx_amount * $fx_rate) < 5.0;
                }
            }

            if ($skey === $key1 && $fx_ok) {
                // proses pembayaran..
                $order = wc_get_order($orderid);
                if ($order && $check_currency == 'IDR') {
                    if ($order->get_total() > $check_amount) {
                        die('Invalid Amount!');
                    }
                    // process_payment($orderid, $amount, $paydate, $tranID, $channel);
                    $order->add_order_note(__("Received Payment Notification:$posted_note", 'woothemes'));
                    return $order->payment_complete($tranID);
                } else {
                    die("Order not found: $orderid");
                }
            }

            die("Invalid Notification");

            exit;
        }

        wp_die('OmniPay Callback failure', 'OmniPay.id Callback', array('response' => 500));
    }

}
