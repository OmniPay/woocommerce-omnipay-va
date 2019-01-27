<?php

defined('ABSPATH') || exit;

return array(
    'title' => array(
        'title' => __('Title', 'woocommerce'),
        'type' => 'text',
        'description' => __('Judul yang ditampilkan saat user melakukan checkout.', 'woocommerce'),
        'default' => __('OmniPay Virtual Account', 'woocommerce'),
        'desc_tip' => true,
    ),
    'description' => array(
        'title' => __('Description', 'woocommerce'),
        'type' => 'text',
        'desc_tip' => true,
        'description' => __('Deskripsi yang ditampilkan pada saat checkout.', 'woocommerce'),
        'default' => __("Pembayaran melalui Virtual Account", 'woocommerce'),
    ),
    'instructions'    => array(
        'title'       => __( 'Instruksi', 'woocommerce' ),
        'type'        => 'textarea',
        'description' => __( 'Instruksi mengenai cara bayar yang akan diperlihatkan di halaman "thank you" dan "email".', 'woocommerce' ),
        'default'     => '',
        'desc_tip'    => true,
    ),
    'fee' => array(
        'title' => __('Fee', 'woocommerce'),
        'type' => 'number',
        'desc_tip' => true,
        'description' => __('Fee yang akan ditambahkan pada order total, jika diisi nol', 'woocommerce'),
        'default' => 0,
    ),
    'expiry_minutes' => array(
        'title' => __('Expired (minute)', 'woocommerce'),
        'type' => 'number',
        'description' => __('Waktu dalam menit sampai dengan nomor virtual account tidak berlaku lagi (1 hari adalah 1440 menit)', 'woocommerce'),
        'default' => 2880,
        'desc_tip' => true,
    ),
    'merchant_id' => array(
        'title' => __('Merchant ID', 'woocommerce'),
        'type' => 'text',
        'description' => __('Merchant ID anda.', 'woocommerce'),
        'default' => __('', 'woocommerce'),
        'desc_tip' => true,
    ),
    'verify_key' => array(
        'title' => __('Verify Key', 'woocommerce'),
        'type' => 'password',
        'description' => __('Verify Key diperlukan untuk keamanan notifikasi pembayaran', 'woocommerce'),
        'default' => __('', 'woocommerce'),
        'desc_tip' => true,
    ),
    'permata' => array(
        'title' => __('VA Permata', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Tampilkan virtual account Bank Permata', 'woocommerce'),
        'default' => 'yes',
    ),
    'artajasa' => array(
        'title' => __('VA Bank Jaringan Bersama', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Tampilkan virtual account Bank Jaringan Bersama', 'woocommerce'),
        'default' => 'yes',
    ),
    'cimb' => array(
        'title' => __('VA CIMB', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Tampilkan virtual account Bank CIMB', 'woocommerce'),
        'default' => 'yes',
    ),
);
