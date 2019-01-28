<?php

namespace WooOmniPayID;

if (!defined('ABSPATH')) {
    exit;
}

// aneh ini dipanggil berkali-kali
if (!class_exists('\WooOmniPayID\OmnipayVa')) {


    class OmnipayVA extends \WC_Payment_Gateway
    {

        /**
         * Whether or not logging is enabled
         *
         * @var bool
         */
        public static $log_enabled = false;

        /**
         * Logger instance
         *
         * @var WC_Logger
         */
        public static $log = false;

        protected $returnHandler = null;

        /**
         * Constructor for the gateway.
         */
        public function __construct()
        {
            $this->id = 'omnipay-va';
            $this->has_fields = true;

            $this->method_title = __('OmniPay VA', 'woocommerce');
            /* translators: %s: Link to WC system status page */
            $this->method_description = __('OmniPay Virtual Account Payment Method.', 'woocommerce');
            $this->supports = array(
                'products',
                //'refunds',
            );

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables.
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');

            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            add_action('woocommerce_order_status_on-hold_to_processing', array($this, 'capture_payment'));
            add_action('woocommerce_order_status_on-hold_to_completed', array($this, 'capture_payment'));

            if (!$this->is_valid_for_use()) {
                $this->enabled = 'no';
            } else {
                include_once __DIR__ . '/includes/return-handler.php';
                $this->returnHandler = new ReturnHandler($this->settings['verify_key']);
            }

            // shows VA on THANK YOU page..
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou'));
            // shows VA on EMAIL
            add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
        }

        /**
         * Return whether or not this gateway still requires setup to function.
         *
         * When this gateway is toggled on via AJAX, if this returns true a
         * redirect will occur to the settings page instead.
         *
         * @since 3.4.0
         * @return bool
         */
        public function needs_setup()
        {
            return !($this->settings['merchant_id'] && $this->settings['verify_key']);
        }

        /**
         * Logging method.
         *
         * @param string $message Log message.
         * @param string $level Optional. Default 'info'. Possible values:
         *                      emergency|alert|critical|error|warning|notice|info|debug.
         */
        public static function log($message, $level = 'info')
        {
            if (self::$log_enabled) {
                if (empty(self::$log)) {
                    self::$log = wc_get_logger();
                }
                self::$log->log($level, $message, array('source' => 'omnipay.id-va'));
            }
        }

        /**
         * Processes and saves options.
         * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
         *
         * @return bool was anything saved?
         */
        public function process_admin_options()
        {
            $saved = parent::process_admin_options();

            // Maybe clear logs.
            if ('yes' !== $this->get_option('debug', 'no')) {
                if (empty(self::$log)) {
                    self::$log = wc_get_logger();
                }
                self::$log->clear('omnipay.id-va');
            }

            return $saved;
        }

        /**
         * Get gateway icon.
         *
         * @return string
         */
        public function get_icon()
        {
            $icon_html = '';
            return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
        }

        /**
         * Check if this gateway is enabled and available in the user's country.
         *
         * @return bool
         */
        public function is_valid_for_use()
        {
            return in_array(
                get_woocommerce_currency(),
                array('IDR'),
                true
            );
        }

        /**
         * Admin Panel Options.
         * - Options for bits like 'title' and availability on a country-by-country basis.
         *
         * @since 1.0.0
         */
        public function admin_options()
        {
            if ($this->is_valid_for_use()) {
                parent::admin_options();
            } else {
                ?>
                <div class="inline error">
                    <p>
                        <strong><?php esc_html_e('Gateway disabled', 'woocommerce'); ?></strong>: <?php esc_html_e('OmniPay does not support your store currency.', 'woocommerce'); ?>
                    </p>
                </div>
                <?php
            }
        }

        /**
         * Initialise Gateway Settings Form Fields.
         */
        public function init_form_fields()
        {
            $this->form_fields = include 'includes/settings-omnipay.php';
        }

        public function payment_fields()
        {
            $description = $this->get_description();
            if ($description) {
                echo wpautop(wptexturize($description)); // @codingStandardsIgnoreLine.
            }

            require_once __DIR__ . '/includes/bank-details.php'

            ?>
            <fieldset>
                <p class="form-row form-row-wide woocommerce-validated">
                    <label for="selected_va">Pilih Bank <span class="required">*</span></label>
                    <select name="<?= $this->id ?>_selected_va" id="selected_va">
                        <?php foreach (BankDetails as $bank => $detail):
                            if ($this->settings[$bank]):
                                ?>
                                <option value="<?= $bank ?>"><?= $detail['nama'] ?></option>
                                <?php
                            endif;
                        endforeach; ?>
                    </select>
                </p>
            </fieldset>
            <script>
                jQuery(function ($) {
                    $('#selected_va').selectWoo()
                })
            </script>
            <?php
        }

        /**
         * Process the payment and return the result.
         *
         * @param  int $order_id Order ID.
         * @return array
         */
        public function process_payment($order_id)
        {
            require_once __DIR__ . '/includes/va-creator.php';
            require_once __DIR__ . '/includes/bank-details.php';

            $order = wc_get_order($order_id);

            $va_channels = array();

            foreach (BankDetails as $bank => $detail) {
                if ($this->settings[$bank])
                    $va_channels[] = $bank;
            }

            $choosen = $_POST['omnipay-va_selected_va'];

            if(in_array($choosen, $va_channels)) {
                $va_channels = array($choosen);
            } else {
                wc_add_notice( __('Payment error:', 'woothemes') . 'invalid BANK selected', 'error' );
                return;
            }

            $amount = $order->get_total() + ($this->settings['fee'] ? $this->settings['fee'] : 0);
            $invoiceid = $order->get_id();
            $bill_name = $order->get_formatted_billing_full_name();
            $bill_email = $order->get_billing_email();
            $bill_mobile = $order->get_billing_phone();
            $bill_desc = get_bloginfo('name') . ' Order: ' . $order_id;
            $expiry_minute = $this->settings['expiry_minutes'] ? $this->settings['expiry_minutes'] : 2880; // default to two days..


            $vas = va_payment_channels($va_channels, $this->settings['merchant_id'], $this->settings['verify_key'],
                $amount, $invoiceid, $bill_name, $bill_email, $bill_mobile, $bill_desc, $expiry_minute);

            $success = 0;
            $failures = array();

            foreach ($vas as $idx => $va) {
                if (is_object($va) && $va->bank && $va->va) {
                    ++$success;
                } else {
                    $failures[] = "{$va_channels[$idx]}: $va";
                }
            }

            if ($success < 1) {
                wc_add_notice( __('Payment error:', 'woothemes') . $failures[0], 'error' );
                return;
            }

            // save the va numbers..
            update_post_meta($order->get_id(), 'vas', json_encode($vas));

            if ($order->get_total() > 0) {
                // Mark as on-hold (we're awaiting the payment).
                $order->update_status('on-hold', __('Awaiting OmniPay Virtual Account payment', 'woocommerce'));
            } else {
                $order->payment_complete();
            }

            // Remove cart.
            WC()->cart->empty_cart();

            // Return thankyou redirect.
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }

        public function can_refund_order($order)
        {
            return false;
        }

        /**
         * Add content to the WC emails.
         *
         * @param WC_Order $order Order object.
         * @param bool $sent_to_admin Sent to admin.
         * @param bool $plain_text Email format: plain text or HTML.
         */
        public function email_instructions($order, $sent_to_admin, $plain_text = false)
        {

            if (!$sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status('on-hold')) {
                $instruction = $this->settings['instructions'];
                if ($instruction) {
                    echo wp_kses_post(wpautop(wptexturize(wp_kses_post($instruction))));
                }
                $this->va_details($order->get_id());
            }

        }

        public function thankyou($order_id)
        {
            $order = wc_get_order($order_id);
            if ($order && $order->get_payment_method() == $this->id) {
                $instruction = $this->settings['instructions'];
                if ($instruction) {
                    echo wp_kses_post(wpautop(wptexturize(wp_kses_post($instruction))));
                }
                $this->va_details($order_id);
            }
        }

        public function va_details($order_id)
        {
            // Get order and store in $order.
            $order = wc_get_order($order_id);

            $vas_string = get_post_meta($order->get_id(), 'vas');

            // array of (stdClass: va, bank, date, amount, due_date)
            $vas = json_decode($vas_string[0]);

            if ($vas && is_array($vas) && !empty($vas)) {

                require_once __DIR__ . '/includes/bank-details.php';
                require_once __DIR__ . '/includes/va-creator.php';

                ?>
                <section>
                    <h2>Virtual Account</h2>
                    <ul class="order_details">
                        <?php foreach ($vas as $va) :
                            $dateObj = new \DateTime($va->due_date);
                            $w = intval($dateObj->format('w'));
                            $days = array('Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu');
                            $due_date = "{$days[$w]}, {$dateObj->format('d-M-y H:i')}";
                            ?>
                            <li>
                                <table style="width: 500px;">
                                    <tr>
                                        <td style="width: 160px;">Bank (Kode Bank)</td>
                                        <td style="width: 10px;">:</td>
                                        <td><?= BankDetails[$va->bank]['nama'] . ' (' . BankDetails[$va->bank]['kode'] . ')' ?></td>
                                    </tr>
                                    <tr>
                                        <td>Account #</td>
                                        <td>:</td>
                                        <td><?= va_number_separator($va->va) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Due #</td>
                                        <td>:</td>
                                        <td><?= $due_date ?></td>
                                    </tr>
                                    <tr>
                                        <td>Amount</td>
                                        <td>:</td>
                                        <td><?= wc_price($va->amount) ?></td>
                                    </tr>
                                </table>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php
            }
        }

        public function admin_scripts()
        {
            $screen = get_current_screen();
            $screen_id = $screen ? $screen->id : '';

            if ('woocommerce_page_wc-settings' !== $screen_id) {
                return;
            }

            wp_enqueue_script('woocommerce_omnipay-va_admin', plugins_url('/woocommerce-omnipay.id-va/omnipay/assets/js/omnipay-va-admin.js'), array(), WC_VERSION, true);
        }
    }

}