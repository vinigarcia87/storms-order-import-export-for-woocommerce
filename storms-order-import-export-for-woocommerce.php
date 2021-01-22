<?php
/*
 * 
Plugin Name: Storms Order Export Import Plugin for WooCommerce
Plugin URI: https://wordpress.org/plugins/order-import-export-for-woocommerce/
Description: Export and Import Order detail including line items, From and To your WooCommerce Store.
Author: WebToffee - Storms Edited
Author URI: https://www.webtoffee.com/product/woocommerce-order-coupon-subscription-export-import/
Version: 1.7.2
Text Domain: order-import-export-for-woocommerce
WC tested up to: 4.7.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

if ( ! defined( 'ABSPATH' ) || ! is_admin() ) {
	return;
}

define( "WF_ORDER_IMP_EXP_ID", "wf_order_imp_exp" );
define( "WF_WOOCOMMERCE_ORDER_IM_EX", "wf_woocommerce_order_im_ex" );

define("WF_CPN_IMP_EXP_ID", "wf_cpn_imp_exp");
define("wf_coupon_csv_im_ex", "wf_coupon_csv_im_ex");

if (!defined('WF_ORDERIMPEXP_CURRENT_VERSION')) {
    define("WF_ORDERIMPEXP_CURRENT_VERSION", "1.7.2");
}

/**
 * Check if WooCommerce is active
 */
if ( ! in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) && !array_key_exists( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_site_option( 'active_sitewide_plugins', array() ) ) ) ) { // deactive if woocommerce in not active
    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    deactivate_plugins( plugin_basename(__FILE__) );
}
register_activation_hook(__FILE__, 'wt_order_basic_register_activation_hook_callback');

function wt_order_basic_register_activation_hook_callback() {
    if(!class_exists( 'WooCommerce' )){
        deactivate_plugins(basename(__FILE__));
        wp_die(__("WooCommerce is not installed/actived. it is required for this plugin to work properly. Please activate WooCommerce.", "order-import-export-for-woocommerce"), "", array('back_link' => 1));
    }
    if (is_plugin_active('order-import-export-for-woocommerce-pro/order-import-export.php')) {
        deactivate_plugins(basename(__FILE__));
        wp_die(__("Looks like you have both free and premium version installed on your site! Prior to activating premium, deactivate and delete the free version. For any issue kindly contact our support team here: <a target='_blank' href='https://www.webtoffee.com/support/'>support</a>", "order-import-export-for-woocommerce"), "", array('back_link' => 1));
    }
    update_option('wt_ord_plugin_installed_date', date('Y-m-d H:i:s'));
}
//if (in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) )) {	

	if ( ! class_exists( 'WF_Order_Import_Export_CSV' ) ) :

	/**
	 * Main CSV Import class
	 */
	class WF_Order_Import_Export_CSV {

		/**
		 * Constructor
		 */
		public function __construct() {
			
                        define( 'WF_OrderImpExpCsv_FILE', __FILE__ );
                        define('WF_OrderImpExpCsv_FILE_Url', plugin_dir_url(__FILE__));
                        
                        if (!defined('WT_OrdImpExpCsv_BASE')) {
                            define('WT_OrdImpExpCsv_BASE', plugin_dir_path(__FILE__));
                        }

			add_filter( 'woocommerce_screen_ids', array( $this, 'woocommerce_screen_ids' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'wf_plugin_action_links' ) );
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
			add_action( 'init', array( $this, 'catch_export_request' ), 20 );
			add_action( 'admin_init', array( $this, 'register_importers' ) );
                                               
                        include_once( 'includes/class-wf-orderimpexpcsv-system-status-tools.php' );
			include_once( 'includes/class-wf-orderimpexpcsv-admin-screen.php' );
			include_once( 'includes/importer/class-wf-orderimpexpcsv-importer.php' );

			if ( defined('DOING_AJAX') ) {
				include_once( 'includes/class-wf-orderimpexpcsv-ajax-handler.php' );
			}
		}
		
		public function wf_plugin_action_links( $links ) {
			$plugin_links = array(
				'<a href="' . admin_url( 'admin.php?page=wf_woocommerce_order_im_ex' ) . '">' . __( 'Import Export', 'order-import-export-for-woocommerce' ) . '</a>',
			);
                        if (array_key_exists('deactivate', $links)) {
                            $links['deactivate'] = str_replace('<a', '<a class="wforderimpexp-deactivate-link"', $links['deactivate']);
                        }
			return array_merge( $plugin_links, $links );
		}
		
		/**
		 * Add screen ID
		 */
		public function woocommerce_screen_ids( $ids ) {
			$ids[] = 'admin'; // For import screen
			return $ids;
		}

		/**
		 * Handle localisation
		 */
		public function load_plugin_textdomain() {
			load_plugin_textdomain( 'order-import-export-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		/**
		 * Catches an export request and exports the data. This class is only loaded in admin.
		 */
		public function catch_export_request() {
			if ( ! empty( $_GET['action'] ) && ! empty( $_GET['page'] ) && $_GET['page'] == 'wf_woocommerce_order_im_ex' ) {
				switch ( $_GET['action'] ) {
					case "export" :
                                                $user_ok = self::hf_user_permission();
                                                if ($user_ok) {
						include_once( 'includes/exporter/class-wf-orderimpexpcsv-exporter.php' );
						WF_OrderImpExpCsv_Exporter::do_export( 'shop_order' );
                                                }  else {
                                                    wp_redirect(wp_login_url());
                                                }
					break;
				}
			}
		}
				

		/**
		 * Register importers for use
		 */
		public function register_importers() {
			register_importer( 'woocommerce_wf_order_csv', 'WooCommerce Order (CSV)', __('Import <strong>Orders</strong> to your store via a csv file.', 'order-import-export-for-woocommerce'), 'WF_OrderImpExpCsv_Importer::order_importer' );
		}
               
                public static function hf_user_permission() {
                // Check if user has rights to export
                $current_user = wp_get_current_user();
                $current_user->roles = apply_filters('hf_add_user_roles', $current_user->roles);
                $current_user->roles = array_unique($current_user->roles);
                $user_ok = false;
                $wf_roles = apply_filters('hf_user_permission_roles', array('administrator', 'shop_manager'));
                if ($current_user instanceof WP_User) {
                    $can_users = array_intersect($wf_roles, $current_user->roles);
                    if (!empty($can_users) || is_super_admin($current_user->ID)) {
                        $user_ok = true;
                    }
                }
                return $user_ok;
                }

	}
	endif;

	new WF_Order_Import_Export_CSV();
        
        
        
        if (!class_exists('WF_Coupon_Import_Export_CSV')) :

        class WF_Coupon_Import_Export_CSV {

            public $cron;
            public $cron_import;

            /**
             * Constructor
             */
            public function __construct() {
                
                define('WF_CpnImpExpCsv_FILE', __FILE__);

                if (is_admin()) {
                    add_action('admin_notices', array($this, 'wf_coupon_ie_admin_notice'), 15);
                }

                add_filter('woocommerce_screen_ids', array($this, 'woocommerce_screen_ids'));
                add_action('init', array($this, 'load_plugin_textdomain'));
                add_action('init', array($this, 'catch_export_request'), 20);
                add_action('admin_init', array($this, 'register_importers'));

                include_once( 'includes/class-wf-cpnimpexpcsv-admin-screen.php' );
                include_once( 'includes/importer/class-wf-cpnimpexpcsv-importer.php' );
               
                if (defined('DOING_AJAX')) {
                    include_once( 'includes/class-wf-cpnimpexpcsv-ajax-handler.php' );
                }
                
            }


            function wf_coupon_ie_admin_notice() {
                global $pagenow;
                global $post;

                if (!isset($_GET["wf_coupon_ie_msg"]) && empty($_GET["wf_coupon_ie_msg"])) {
                    return;
                }

                $wf_coupon_ie_msg = $_GET["wf_coupon_ie_msg"];

                switch ($wf_coupon_ie_msg) {
                    case "1":
                        echo '<div class="update"><p>' . __('Successfully uploaded via FTP.', 'order-import-export-for-woocommerce') . '</p></div>';
                        break;
                    case "2":
                        echo '<div class="error"><p>' . __('Error while uploading via FTP.', 'order-import-export-for-woocommerce') . '</p></div>';
                        break;
                    case "3":
                        echo '<div class="error"><p>' . __('Please choose the file in CSV format using Method 1.', 'order-import-export-for-woocommerce') . '</p></div>';
                        break;
                }
            }

            /**
             * Add screen ID
             */
            public function woocommerce_screen_ids($ids) {
                $ids[] = 'admin'; // For import screen
                return $ids;
            }

            /**
             * Handle localisation
             */
            public function load_plugin_textdomain() {
                load_plugin_textdomain('order-import-export-for-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/lang/');
            }

            /**
             * Catches an export request and exports the data. This class is only loaded in admin.
             */
            public function catch_export_request() {
                if (!empty($_GET['action']) && !empty($_GET['page']) && $_GET['page'] == 'wf_coupon_csv_im_ex') {
                    switch ($_GET['action']) {
                        case "export" :
                            $user_ok = self::hf_user_permission();
                            if ($user_ok) {
                                include_once( 'includes/exporter/class-wf-cpnimpexpcsv-exporter.php' );
                                WF_CpnImpExpCsv_Exporter::do_export('shop_coupon');
                            } else {
                                wp_redirect(wp_login_url());
                            }
                            break;
                    }
                }
            }            

            /**
             * Register importers for use
             */
            public function register_importers() {
                register_importer('coupon_csv', 'WooCommerce Coupons (CSV)', __('Import <strong>coupon</strong> to your store via a csv file.', 'order-import-export-for-woocommerce'), 'WF_CpnImpExpCsv_Importer::coupon_importer');
            }    

            public static function hf_user_permission() {
                // Check if user has rights to export
                $current_user = wp_get_current_user();
                $current_user->roles = apply_filters('hf_add_user_roles', $current_user->roles);
                $current_user->roles = array_unique($current_user->roles);
                $user_ok = false;
                $wf_roles = apply_filters('hf_user_permission_roles', array('administrator', 'shop_manager'));
                if ($current_user instanceof WP_User) {
                    $can_users = array_intersect($wf_roles, $current_user->roles);
                    if (!empty($can_users) || is_super_admin($current_user->ID)) {
                        $user_ok = true;
                    }
                }
                return $user_ok;
                }
            
            
        }

        endif;

    new WF_Coupon_Import_Export_CSV();

//}
