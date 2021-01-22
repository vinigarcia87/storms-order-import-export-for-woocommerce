<?php

if (!defined('ABSPATH')) {
    exit;
}

class WF_OrderImpExpCsv_Exporter {

    /**
     * Order Exporter Tool
     */
    
    public static function do_export($post_type = 'shop_order') {
        global $wpdb;
        $limit = !empty($_POST['limit']) ? absint($_POST['limit']) : 999999999;
        $export_offset = !empty($_POST['offset']) ? absint($_POST['offset']) : 0;
        $csv_columns = include( 'data/data-wf-post-columns.php' );
        $user_columns_name           = ! empty( $_POST['columns_name'] ) ? wc_clean($_POST['columns_name']) : $csv_columns;
        $export_columns              = ! empty( $_POST['columns'] ) ? wc_clean($_POST['columns']) : array();
        $export_order_statuses = !empty($_POST['order_status']) ? wc_clean($_POST['order_status']) : 'any';
        $delimiter = !empty($_POST['delimiter']) ? wc_clean( wp_unslash($_POST['delimiter'])) : ',';
        $end_date = empty($_POST['end_date']) ? date('Y-m-d 23:59:59.99', current_time('timestamp')) : sanitize_text_field($_POST['end_date']) . ' 23:59:59.99';
        $start_date = empty($_POST['start_date']) ? date('Y-m-d 00:00:00', 0) : sanitize_text_field($_POST['start_date']). ' 00:00:00';

        $wpdb->hide_errors();
        @set_time_limit(0);
        if (function_exists('apache_setenv'))
            @apache_setenv('no-gzip', 1);
        @ini_set('zlib.output_compression', 0);
        @ob_end_clean();

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename=woocommerce-order-export.csv');
            header('Pragma: no-cache');
            header('Expires: 0');

            $fp = fopen('php://output', 'w');
        
        // Headers

        $query_args = array(
            'fields' => 'ids',
            'post_type' => 'shop_order',
            'post_status' => $export_order_statuses,
            'posts_per_page' => $limit,
            'offset' => $export_offset,
             'date_query' => array(
                                    array(
                                            'before' => $end_date,
                                            'after' => $start_date,
                                            'inclusive' => true,
                                    ),
                            ),
                
        );

        $query = new WP_Query($query_args);
        $order_ids = $query->posts;
        
        // Variable to hold the CSV data we're exporting
        $row = array();
        // Export header rows
        foreach ($csv_columns as $column => $value) {
            $temp_head =    esc_attr( $user_columns_name[$column] );
            if ( ! $export_columns || in_array( $column, $export_columns ) ) 
                $row[] = $temp_head;
        }
        $max_line_items = WF_OrderImpExpCsv_Exporter::get_max_line_items($order_ids);
        for ($i = 1; $i <= $max_line_items; $i++) {
            $row[] = "line_item_{$i}";
        }

		$max_meta_data = 0;
		foreach ($order_ids as $order_id) {
			$order = wc_get_order($order_id);
			$meta_data_count = count($order->get_meta_data());
			if ($meta_data_count >= $max_meta_data) {
				$max_meta_data = $meta_data_count;
			}
		}
		//return $max_line_items;

        $row = array_map('WF_OrderImpExpCsv_Exporter::wrap_column', $row);

        //\StormsFramework\Helper::debug( $row, 'gravando cabecalhos' );

        fwrite($fp, implode($delimiter, $row) . "\n");
        unset($row);
        // Loop orders
        foreach ($order_ids as $order_id) {
            //$row = array();   
            $data = WF_OrderImpExpCsv_Exporter::get_orders_csv_row($order_id , $export_columns);
            // Add to csv
            $row = array_map('WF_OrderImpExpCsv_Exporter::wrap_column', $data);
            fwrite($fp, implode($delimiter, $row) . "\n");
            unset($row);
            unset($data);
        }
        fclose($fp);
        exit;
    }

    public static function format_data($data) {
        if (!is_array($data))
            ;
        $data = (string) urldecode($data);
        $enc = mb_detect_encoding($data, 'UTF-8, ISO-8859-1', true);
        $data = ( $enc == 'UTF-8' ) ? $data : utf8_encode($data);
        return $data;
    }

    /**
     * Wrap a column in quotes for the CSV
     * @param  string data to wrap
     * @return string wrapped data
     */
    public static function wrap_column($data) {
        return '"' . str_replace('"', '""', $data) . '"';
    }

    public static function get_max_line_items($order_ids) {
        $max_line_items = 0;
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            $line_items_count = count($order->get_items());
            if ($line_items_count >= $max_line_items) {
                $max_line_items = $line_items_count;
            }
        }
        return $max_line_items;
    }

    public static function get_orders_csv_row($order_id , $export_columns) {
        $order = wc_get_order($order_id);
        $line_items = $shipping_items = $fee_items = $tax_items = $coupon_items = $refund_items = array();

        // get line items
        foreach ($order->get_items() as $item_id => $item) {
            $product = (WC()->version < '4.4.0') ? $order->get_product_from_item($item) : $item->get_product();   //  get_product_from_item() deprecated since version 4.4.0 
            if (!is_object($product)) {
                $product = new WC_Product(0);
            }

            $line_item = array(
                'name' => html_entity_decode($product->get_title() ? $product->get_title() : $item['name'], ENT_NOQUOTES, 'UTF-8'),
                'product_id' => (WC()->version < '2.7.0')?$product->id:$product->get_id(),
                'sku' => $product->get_sku(),
                'quantity' => $item['qty'],
                'total' => wc_format_decimal($order->get_line_total($item), 2),
                'sub_total' => wc_format_decimal($order->get_line_subtotal($item), 2),
      		);

            // add line item tax
            $line_tax_data = isset($item['line_tax_data']) ? $item['line_tax_data'] : array();
            $tax_data = maybe_unserialize($line_tax_data);
          
            $line_item['tax'] = isset($tax_data['total']) ? wc_format_decimal(wc_round_tax_total(array_sum((array) $tax_data['total'])), 2) : '';
            $line_tax_ser = maybe_serialize($line_tax_data); 
            if(isset($line_tax_data['total'])){
                foreach ($line_tax_data['total'] as $rate_key => $rate_value) {
                   $tdata =  WC_Tax::get_rate_label($rate_key);
                   $line_tax_total_data[] = $tdata."=".$rate_value;
                }
            }
            if(!empty($line_tax_total_data)){
            $line_tax_totat = implode(",", $line_tax_total_data);
            $line_item['tax_total'] = !empty($line_tax_totat)?$line_tax_totat:'';
            }
            
             foreach ($line_tax_data['subtotal'] as $srate_key => $srate_value) {
               $stdata =  WC_Tax::get_rate_label($srate_key);
               $line_tax_subtotal_data[] = $stdata."=".$srate_value;
            }
             if(!empty($line_tax_subtotal_data)){
            $line_tax_subtotat = implode(",", $line_tax_subtotal_data);
            $line_item['tax_subtotal'] = !empty($line_tax_subtotat)?$line_tax_subtotat:'';
             }
           // $line_item['tax_data'] = $line_tax_ser;
            $line_items[] = $line_item;
        }     
        
        $line_items_shipping = $order->get_items('shipping');
        
        foreach ($line_items_shipping as $item_id => $item) {
            $item_meta = self::get_order_line_item_meta($item_id);
            foreach ($item_meta as $key => $value) {
                switch ($key){
                    case 'Items':
                    case 'method_id':
                    case 'taxes':
                        if(is_object($value))
                            $value = $value->meta_value;
                        if (is_array($value))
                            $value = implode(',', $value);
                        $meta[$key] = $value;
                        break;
                        
                }
            }
            foreach (array('Items','method_id','taxes') as $value){
                if(!isset($meta[$value])){
                    $meta[$value] = '';
                }
            }
            $shipping_items[] = trim(implode('|', array('items:' .$meta['Items'], 'method_id:' .$meta['method_id'], 'taxes:' .$meta['taxes'])));  
        }

        // get fee items & total
        $fee_total = 0;
        $fee_tax_total = 0;

        foreach ($order->get_fees() as $fee_id => $fee) {
            $fee_items[] = implode('|', array(
                'name:' . html_entity_decode($fee['name'], ENT_NOQUOTES, 'UTF-8'),
                'total:' . wc_format_decimal($fee['line_total'], 2),
                'tax:' . wc_format_decimal($fee['line_tax'], 2),
            ));
            $fee_total += $fee['line_total'];
            $fee_tax_total += $fee['line_tax'];
        }
        add_filter('woocommerce_order_hide_zero_taxes','__return_false');
        // get tax items
        foreach ($order->get_tax_totals() as $tax_code => $tax) {
            $tax_items[] = implode('|', array(
                'rate_id:'.$tax->rate_id,
                'code:' . $tax_code,
                'total:' . wc_format_decimal($tax->amount, 2),
                'label:'.$tax->label,                
                'tax_rate_compound:'.$tax->is_compound,
            ));
        }

        // add coupons
        foreach ($order->get_items('coupon') as $_ => $coupon_item) {
            $discount_amount = !empty($coupon_item['discount_amount']) ? $coupon_item['discount_amount'] : 0;
            $coupon_items[] = implode('|', array(
                    'code:' . $coupon_item['name'],
                    'amount:' . wc_format_decimal($discount_amount, 2),
            ));
        }
        
        foreach ($order->get_refunds() as $refunded_items){
           
           if ((WC()->version < '2.7.0')) {
               $refund_items[] = implode('|', array(
                   'amount:' . $refunded_items->get_refund_amount(),
                   'reason:' . $refunded_items->reason,
                   'date:' . date('Y-m-d H:i:s', strtotime( $refunded_items->date_created )),
               ));
           } else {
               $refund_items[] = implode('|', array(
                   'amount:' . $refunded_items->get_amount(),
                   'reason:' . $refunded_items->get_reason(),
                   'date:' . date('Y-m-d H:i:s', strtotime( $refunded_items->get_date_created())),
               ));
           }      
           
       }

        if (version_compare(WC_VERSION, '2.7', '<')) {
            $order_data = array(
                'order_id' => $order->id,
                'order_number' => $order->get_order_number(),
                'order_date' => date('Y-m-d H:i:s', strtotime(get_post($order->id)->post_date)),
                'status' => $order->get_status(),
                'shipping_total' => $order->get_total_shipping(),
                'shipping_tax_total' => wc_format_decimal($order->get_shipping_tax(), 2),
                'fee_total' => wc_format_decimal($fee_total, 2),
                'fee_tax_total' => wc_format_decimal($fee_tax_total, 2),
                'tax_total' => wc_format_decimal($order->get_total_tax(), 2),
                'cart_discount' => (defined( 'WC_VERSION' ) && (WC_VERSION >= 2.3)) ? wc_format_decimal($order->get_total_discount(), 2) : wc_format_decimal($order->get_cart_discount(), 2),
                'order_discount' => (defined( 'WC_VERSION' ) && (WC_VERSION >= 2.3)) ? wc_format_decimal($order->get_total_discount(), 2) : wc_format_decimal($order->get_order_discount(), 2),
                'discount_total' => wc_format_decimal($order->get_total_discount(), 2),
                'order_total' => wc_format_decimal($order->get_total(), 2),
//                'refunded_total' => wc_format_decimal($order->get_total_refunded(), 2),
                'order_currency' => $order->get_order_currency(),
                'payment_method' => $order->payment_method,
                'shipping_method' => $order->get_shipping_method(),
                'customer_id' => $order->get_user_id(),
                'customer_user' => $order->get_user_id(),
                'customer_email' => ($a = get_userdata($order->get_user_id() )) ? $a->user_email : '',
                'billing_first_name' => $order->billing_first_name,
                'billing_last_name' => $order->billing_last_name,
                'billing_company' => $order->billing_company,
                'billing_email' => $order->billing_email,
                'billing_phone' => $order->billing_phone,
                'billing_address_1' => $order->billing_address_1,
                'billing_address_2' => $order->billing_address_2,
                'billing_postcode' => $order->billing_postcode,
                'billing_city' => $order->billing_city,
                'billing_state' => $order->billing_state,
                'billing_country' => $order->billing_country,
                'shipping_first_name' => $order->shipping_first_name,
                'shipping_last_name' => $order->shipping_last_name,
                'shipping_company' => $order->shipping_company,
                'shipping_address_1' => $order->shipping_address_1,
                'shipping_address_2' => $order->shipping_address_2,
                'shipping_postcode' => $order->shipping_postcode,
                'shipping_city' => $order->shipping_city,
                'shipping_state' => $order->shipping_state,
                'shipping_country' => $order->shipping_country,
                'customer_note' => $order->customer_note,
                'shipping_items' => implode(';', $shipping_items),
                'fee_items' => implode(';', $fee_items),
                'tax_items' => implode(';', $tax_items),
                'coupon_items' => implode(';', $coupon_items),
                'refund_items' => implode(';', $refund_items),
                'order_notes' => implode('||', WF_OrderImpExpCsv_Exporter::get_order_notes($order)),
                'download_permissions' => $order->download_permissions_granted ? $order->download_permissions_granted : 0,
            );
        }else{
            $order_data = array(
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'order_date' => date('Y-m-d H:i:s', strtotime(get_post($order->get_id())->post_date)),
                'status' => $order->get_status(),
                'shipping_total' => $order->get_total_shipping(),
                'shipping_tax_total' => wc_format_decimal($order->get_shipping_tax(), 2),
                'fee_total' => wc_format_decimal($fee_total, 2),
                'fee_tax_total' => wc_format_decimal($fee_tax_total, 2),
                'tax_total' => wc_format_decimal($order->get_total_tax(), 2),
                'cart_discount' => (defined('WC_VERSION') && (WC_VERSION >= 2.3)) ? wc_format_decimal($order->get_total_discount(), 2) : wc_format_decimal($order->get_cart_discount(), 2),
                'order_discount' => (defined('WC_VERSION') && (WC_VERSION >= 2.3)) ? wc_format_decimal($order->get_total_discount(), 2) : wc_format_decimal($order->get_order_discount(), 2),
                'discount_total' => wc_format_decimal($order->get_total_discount(), 2),
                'order_total' => @wc_format_decimal($order->get_total(), 2),
//                'refunded_total' => wc_format_decimal($order->get_total_refunded(), 2),
                'order_currency' => $order->get_currency(),
                'payment_method' => $order->get_payment_method(),
                'shipping_method' => $order->get_shipping_method(),
                'customer_id' => $order->get_user_id(),
                'customer_user' => $order->get_user_id(),
                'customer_email' => ($a = get_userdata($order->get_user_id() )) ? $a->user_email : '',
                'billing_first_name' => $order->get_billing_first_name(),
                'billing_last_name' => $order->get_billing_last_name(),
                'billing_company' => $order->get_billing_company(),
                'billing_email' => $order->get_billing_email(),
                'billing_phone' => $order->get_billing_phone(),
                'billing_address_1' => $order->get_billing_address_1(),
                'billing_address_2' => $order->get_billing_address_2(),
                'billing_postcode' => $order->get_billing_postcode(),
                'billing_city' => $order->get_billing_city(),
                'billing_state' => $order->get_billing_state(),
                'billing_country' => $order->get_billing_country(),
                'shipping_first_name' => $order->get_shipping_first_name(),
                'shipping_last_name' => $order->get_shipping_last_name(),
                'shipping_company' => $order->get_shipping_company(),
                'shipping_address_1' => $order->get_shipping_address_1(),
                'shipping_address_2' => $order->get_shipping_address_2(),
                'shipping_postcode' => $order->get_shipping_postcode(),
                'shipping_city' => $order->get_shipping_city(),
                'shipping_state' => $order->get_shipping_state(),
                'shipping_country' => $order->get_shipping_country(),
                'customer_note' => $order->get_customer_note(),
                'shipping_items' => implode(';', $shipping_items),
                'fee_items' => implode(';', $fee_items),
                'tax_items' => implode(';', $tax_items),
                'coupon_items' => implode(';', $coupon_items),
                'refund_items' => implode(';', $refund_items),
                'order_notes' => implode('||', (defined('WC_VERSION') && (WC_VERSION >= 3.2)) ? WF_OrderImpExpCsv_Exporter::get_order_notes_new($order) : WF_OrderImpExpCsv_Exporter::get_order_notes($order)),
                'download_permissions' => $order->is_download_permitted() ? $order->is_download_permitted() : 0,
                'customer_ip_address' => $order->get_customer_ip_address() ? $order->get_customer_ip_address() : '',
                'paid_date' => '',
                'completed_date' => '',
            ); 
            if($order->get_date_paid()){
                $paid_date = $order->get_date_paid();
                $paid_date_timestamp = strtotime($paid_date);
                $formatted_paid_date = date('Y-m-d H:i:s', $paid_date_timestamp);    
                $order_data['paid_date'] = $formatted_paid_date ;
                
            }
            if($order->get_date_completed()){
                $date_completed = $order->get_date_completed();
                $date_completed_timestamp = strtotime($date_completed);
                $formatted_date_completed = date('Y-m-d H:i:s', $date_completed_timestamp);    
                $order_data['completed_date'] = $formatted_date_completed ;
                
            }
        }

		/**************************************************************************************************************/

		//\StormsFramework\Helper::debug( $order->get_meta(), 'order->get_meta' );

		// WooCommerce 3.0 or later.
		if ( method_exists( $order, 'get_meta' ) ) {
			$order_data['storms_wc_receipt_number'] = $order->get_meta( '_storms_wc_receipt_number' );
			$order_data['storms_wc_receipt_serie'] 	= $order->get_meta( '_storms_wc_receipt_serie' );
			$order_data['storms_wc_receipt_key'] 	= $order->get_meta( '_storms_wc_receipt_key' );

			foreach( $order->get_meta( '_wc_pagseguro_payment_data' ) as $key => $value ) {
				$order_data['wc_pagseguro_payment_data'][] = $key . ':' . $value;
			}
			$order_data['wc_pagseguro_payment_data'] = implode('|', $order_data['wc_pagseguro_payment_data'] );

			$order_data[ __( 'Payer email', 'woocommerce-pagseguro' ) ]         = $order->get_meta( __( 'Payer email', 'woocommerce-pagseguro' ) );
			$order_data[ __( 'Payer name', 'woocommerce-pagseguro' ) ]          = $order->get_meta( __( 'Payer name', 'woocommerce-pagseguro' ) );
			$order_data[ __( 'Payment type', 'woocommerce-pagseguro' ) ]        = $order->get_meta( __( 'Payment type', 'woocommerce-pagseguro' ) );
			$order_data[ __( 'Payment method', 'woocommerce-pagseguro' ) ]      = $order->get_meta( __( 'Payment method', 'woocommerce-pagseguro' ) );
			$order_data[ __( 'Installments', 'woocommerce-pagseguro' ) ]        = $order->get_meta( __( 'Installments', 'woocommerce-pagseguro' ) );
			$order_data[ __( 'Payment URL', 'woocommerce-pagseguro' ) ]         = $order->get_meta( __( 'Payment URL', 'woocommerce-pagseguro' ) );
			$order_data[ __( 'Intermediation Rate', 'woocommerce-pagseguro' ) ] = $order->get_meta( __( 'Intermediation Rate', 'woocommerce-pagseguro' ) );
			$order_data[ __( 'Intermediation Fee', 'woocommerce-pagseguro' ) ]  = $order->get_meta( __( 'Intermediation Fee', 'woocommerce-pagseguro' ) );

			// Billing fields.
			$order_data['billing_persontype']   = $order->get_meta( '_billing_persontype' );
			$order_data['billing_cpf']          = $order->get_meta( '_billing_cpf' );
			$order_data['billing_rg']           = $order->get_meta( '_billing_rg' );
			$order_data['billing_cnpj']         = $order->get_meta( '_billing_cnpj' );
			$order_data['billing_ie']           = $order->get_meta( '_billing_ie' );
			$order_data['billing_birthdate']    = $order->get_meta( '_billing_birthdate' );
			$order_data['billing_sex']          = $order->get_meta( '_billing_sex' );
			$order_data['billing_number']       = $order->get_meta( '_billing_number' );
			$order_data['billing_neighborhood'] = $order->get_meta( '_billing_neighborhood' );
			$order_data['billing_cellphone']    = $order->get_meta( '_billing_cellphone' );

			// Shipping fields.
			$order_data['shipping_number']       = $order->get_meta( '_shipping_number' );
			$order_data['shipping_neighborhood'] = $order->get_meta( '_shipping_neighborhood' );

			$order_data['billing_tipo_compra']	 	= $order->get_meta( '_billing_tipo_compra' );
			$order_data['billing_is_contribuinte']	= $order->get_meta( '_billing_is_contribuinte' );

			$order_data['created_via']  	    = $order->get_created_via();
			$order_data['customer_user_agent']	= $order->get_customer_user_agent();
			$order_data['is_vat_exempt']	    = $order->get_meta( 'is_vat_exempt' );
			$order_data['transaction_id']	    = $order->get_transaction_id();

		} else {
			$order_data['storms_wc_receipt_number'] = get_post_meta( $order->id, '_axado_receipt_number', true );
			$order_data['storms_wc_receipt_serie'] 	= get_post_meta( $order->id, '_axado_receipt_serie', true );
			$order_data['storms_wc_receipt_key'] 	= get_post_meta( $order->id, '_axado_receipt_key', true );

			foreach( get_post_meta( $order->id, '_wc_pagseguro_payment_data', true ) as $key => $value ) {
				$order_data['wc_pagseguro_payment_data'][] = $key . ':' . $value;
			}
			$order_data['wc_pagseguro_payment_data'] = implode('|', $order_data['wc_pagseguro_payment_data'] );

			$order_data[ __( 'Payer email', 'woocommerce-pagseguro' ) ]         = get_post_meta( $order->id, __( 'Payer email', 'woocommerce-pagseguro' ), true  );
			$order_data[ __( 'Payer name', 'woocommerce-pagseguro' ) ]          = get_post_meta( $order->id, __( 'Payer name', 'woocommerce-pagseguro' ), true  );
			$order_data[ __( 'Payment type', 'woocommerce-pagseguro' ) ]        = get_post_meta( $order->id, __( 'Payment type', 'woocommerce-pagseguro' ), true  );
			$order_data[ __( 'Payment method', 'woocommerce-pagseguro' ) ]      = get_post_meta( $order->id, __( 'Payment method', 'woocommerce-pagseguro' ), true  );
			$order_data[ __( 'Installments', 'woocommerce-pagseguro' ) ]        = get_post_meta( $order->id, __( 'Installments', 'woocommerce-pagseguro' ), true  );
			$order_data[ __( 'Payment URL', 'woocommerce-pagseguro' ) ]         = get_post_meta( $order->id, __( 'Payment URL', 'woocommerce-pagseguro' ), true  );
			$order_data[ __( 'Intermediation Rate', 'woocommerce-pagseguro' ) ] = get_post_meta( $order->id, __( 'Intermediation Rate', 'woocommerce-pagseguro' ), true  );
			$order_data[ __( 'Intermediation Fee', 'woocommerce-pagseguro' ) ]  = get_post_meta( $order->id, __( 'Intermediation Fee', 'woocommerce-pagseguro' ), true  );

			// Billing fields.
			$order_data['billing_persontype']   = $order->billing_persontype;
			$order_data['billing_cpf']          = $order->billing_cpf;
			$order_data['billing_rg']           = $order->billing_rg;
			$order_data['billing_cnpj']         = $order->billing_cnpj;
			$order_data['billing_ie']           = $order->billing_ie;
			$order_data['billing_birthdate']    = $order->billing_birthdate;
			$order_data['billing_sex']          = $order->billing_sex;
			$order_data['billing_number']       = $order->billing_number;
			$order_data['billing_neighborhood'] = $order->billing_neighborhood;
			$order_data['billing_cellphone']    = $order->billing_cellphone;

			// Shipping fields.
			$order_data['shipping_number']       = $order->shipping_number;
			$order_data['shipping_neighborhood'] = $order->shipping_neighborhood;

			$order_data['billing_tipo_compra']	 	= get_post_meta( $order->id, '_billing_tipo_compra', true  );
			$order_data['billing_is_contribuinte']	= get_post_meta( $order->id, '_billing_is_contribuinte', true  );

			$order_data['created_via']  	    = $order->created_via;
			$order_data['customer_user_agent']	= $order->customer_user_agent;
			$order_data['is_vat_exempt']	    = get_post_meta( $order->id, 'is_vat_exempt', true  );
			$order_data['transaction_id']	    = $order->transaction_id;
		}

		//\StormsFramework\Helper::debug( $order_data );

		/**************************************************************************************************************/

		foreach ($order_data as $key => $value) {
			if (!$export_columns || in_array( $key, $export_columns ) ){
				// need to modify code
			}else{
				unset($order_data[$key]);
			}
		}
		$li = 1;
		foreach ($line_items as $line_item) {
			foreach ($line_item as $name => $value) {
				$line_item[$name] = $name . ':' . $value;
			}
			$line_item = implode('|', $line_item);
			$order_data["line_item_{$li}"] = $line_item;
			$li++;
		}

        return $order_data;
    }

    public static function get_order_notes($order) {
        $callback = array('WC_Comments', 'exclude_order_comments');
        $args = array(
            'post_id' => (WC()->version < '2.7.0')?$order->id:$order->get_id(),
            'approve' => 'approve',
            'type' => 'order_note'
        );

        remove_filter('comments_clauses', $callback);
        $notes = get_comments($args);
        add_filter('comments_clauses', $callback);
        $notes = array_reverse($notes);
        $order_notes = array();
        foreach ($notes as $note) {
            $date = $note->comment_date;
            $customer_note = 0;
            if (get_comment_meta($note->comment_ID, 'is_customer_note', '1')){
                    $customer_note = 1;
            }
            $order_notes[] = implode('|', array(
                'content:' .str_replace(array("\r", "\n"), ' ', $note->comment_content),
                'date:'.(!empty($date) ? $date : current_time( 'mysql' )),
                'customer:'.$customer_note,
                'added_by:'.$note->added_by
             ));
        }
        return $order_notes;
    }
    
    public static function get_order_notes_new($order) {
        $notes = wc_get_order_notes(array('order_id' => $order->get_id(),'order_by' => 'date_created','order' => 'ASC'));
        $order_notes = array();
        foreach ($notes as $note) {
            $order_notes[] = implode('|', array(
                'content:' .str_replace(array("\r", "\n"), ' ', $note->content),
                'date:'.$note->date_created->date('Y-m-d H:i:s'),
                'customer:'.$note->customer_note,
                'added_by:'.$note->added_by
             ));
        }
        return $order_notes;
    }
    
     public static function get_order_line_item_meta($item_id){
        global $wpdb;
        $filtered_meta = apply_filters('wt_order_export_select_line_item_meta',array());
        $filtered_meta = !empty($filtered_meta) ? implode("','",$filtered_meta) : '';
        $query = "SELECT meta_key,meta_value
            FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = '$item_id'";
        if(!empty($filtered_meta)){
            $query .= " AND meta_key IN ('".$filtered_meta."')";
        }
        $meta_keys = $wpdb->get_results($query , OBJECT_K );
        return $meta_keys;
    }
}
