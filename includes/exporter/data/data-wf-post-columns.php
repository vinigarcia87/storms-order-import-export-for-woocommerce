<?php

if (!defined('ABSPATH')) {
    exit;
}

return apply_filters('woocommerce_csv_order_post_columns', array(
    'order_id' => 'order_id',
    'order_number' => 'order_number',
    'order_date' => 'order_date',
    'status' => 'status',
    'shipping_total' => 'shipping_total',
    'shipping_tax_total' => 'shipping_tax_total',
    'fee_total' => 'fee_total',
    'fee_tax_total' => 'fee_tax_total',
    'tax_total' => 'tax_total',
    'cart_discount' => 'cart_discount',
    'order_discount' => 'order_discount',
    'discount_total' => 'discount_total',
    'order_total' => 'order_total',
//    'refunded_total' => 'refunded_total',
    'order_currency' => 'order_currency',
    'payment_method' => 'payment_method',
    'shipping_method' => 'shipping_method',
    'customer_id' => 'customer_id',
    'customer_user' => 'customer_user',
    'customer_email' => 'customer_email',
    'billing_first_name' => 'billing_first_name',
    'billing_last_name' => 'billing_last_name',
    'billing_company' => 'billing_company',
    'billing_email' => 'billing_email',
    'billing_phone' => 'billing_phone',
    'billing_address_1' => 'billing_address_1',
    'billing_address_2' => 'billing_address_2',
    'billing_postcode' => 'billing_postcode',
    'billing_city' => 'billing_city',
    'billing_state' => 'billing_state',
    'billing_country' => 'billing_country',
    'shipping_first_name' => 'shipping_first_name',
    'shipping_last_name' => 'shipping_last_name',
    'shipping_company' => 'shipping_company',
    'shipping_address_1' => 'shipping_address_1',
    'shipping_address_2' => 'shipping_address_2',
    'shipping_postcode' => 'shipping_postcode',
    'shipping_city' => 'shipping_city',
    'shipping_state' => 'shipping_state',
    'shipping_country' => 'shipping_country',
    'customer_note' => 'customer_note',
    'shipping_items' => 'shipping_items',
    'fee_items' => 'fee_items',
    'tax_items' => 'tax_items',
    'coupon_items' => 'coupon_items',
    'refund_items' => 'refund_items',
    'order_notes' => 'order_notes',
    'download_permissions' => 'download_permissions',
    'customer_ip_address' => 'customer_ip_address',
    'paid_date' => 'paid_date',
    'completed_date'=>'completed_date',

	'storms_wc_receipt_number' => 'storms_wc_receipt_number',
	'storms_wc_receipt_serie' => 'storms_wc_receipt_serie',
	'storms_wc_receipt_key' => 'storms_wc_receipt_key',
	'wc_pagseguro_payment_data' => 'wc_pagseguro_payment_data',
	__( 'Payer email', 'woocommerce-pagseguro' ) => __( 'Payer email', 'woocommerce-pagseguro' ),
	__( 'Payer name', 'woocommerce-pagseguro' ) => __( 'Payer name', 'woocommerce-pagseguro' ),
	__( 'Payment type', 'woocommerce-pagseguro' ) => __( 'Payment type', 'woocommerce-pagseguro' ),
	__( 'Payment method', 'woocommerce-pagseguro' ) => __( 'Payment method', 'woocommerce-pagseguro' ),
	__( 'Installments', 'woocommerce-pagseguro' ) => __( 'Installments', 'woocommerce-pagseguro' ),
	__( 'Payment URL', 'woocommerce-pagseguro' ) => __( 'Payment URL', 'woocommerce-pagseguro' ),
	__( 'Intermediation Rate', 'woocommerce-pagseguro' ) => __( 'Intermediation Rate', 'woocommerce-pagseguro' ),
	__( 'Intermediation Fee', 'woocommerce-pagseguro' ) => __( 'Intermediation Fee', 'woocommerce-pagseguro' ),
	'billing_persontype' => 'billing_persontype',
	'billing_cpf' => 'billing_cpf',
	'billing_rg' => 'billing_rg',
	'billing_cnpj' => 'billing_cnpj',
	'billing_ie' => 'billing_ie',
	'billing_birthdate' => 'billing_birthdate',
	'billing_sex' => 'billing_sex',
	'billing_number' => 'billing_number',
	'billing_neighborhood' => 'billing_neighborhood',
	'billing_cellphone' => 'billing_cellphone',
	'shipping_number' => 'shipping_number',
	'shipping_neighborhood' => 'shipping_neighborhood',
	'billing_tipo_compra' => 'billing_tipo_compra',
	'billing_is_contribuinte' => 'billing_is_contribuinte',
	'created_via' => 'created_via',
	'customer_user_agent' => 'customer_user_agent',
	'is_vat_exempt' => 'is_vat_exempt',
	'transaction_id' => 'transaction_id',
));
