<?php
/**
 * Plugin Name: WooCommerce Kargo Takip (TR)
 * Description: Türkiye içi kargo takip entegrasyonu. Sipariş durumuna "Kargoda" ekler, takip no ve kargo firması bilgisiyle müşteri e-postası gönderir ve Müşteri Hesabım sayfasında gösterir.
 * Author: warpherg
 * Version: 1.1.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.5
 * WC requires at least: 5.0
 * WC tested up to: 9.1
 * Text Domain: wc-kargo-takip
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WC_KARGO_TAKIP_FILE', __FILE__ );
define( 'WC_KARGO_TAKIP_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_KARGO_TAKIP_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_KARGO_TAKIP_VERSION', '1.1.0' );

add_action( 'plugins_loaded', function() {
	load_plugin_textdomain( 'wc-kargo-takip', false, basename( dirname( __FILE__ ) ) . '/languages' );
} );

// WooCommerce yüklü mü?
function wc_kargo_takip_wc_active() {
	return class_exists( 'WooCommerce' );
}

// HPOS uyumluluk bildirimi
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

// Ana sınıfı yükle
add_action( 'plugins_loaded', function() {
	if ( ! wc_kargo_takip_wc_active() ) {
		return;
	}
	require_once WC_KARGO_TAKIP_PATH . 'includes/class-wc-kargo-takip.php';
	\WC_Kargo_Takip::instance();
}, 20 );

// Cron zamanlayıcıları
register_activation_hook( __FILE__, function() {
	if ( ! wp_next_scheduled( 'wc_kargo_takip_cron' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'wc_kargo_takip_cron' );
	}
} );

register_deactivation_hook( __FILE__, function() {
	$timestamp = wp_next_scheduled( 'wc_kargo_takip_cron' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'wc_kargo_takip_cron' );
	}
} );

// Güvenlik: Doğrudan erişimi engelle
// Boş satır kasıtlı bırakıldı


