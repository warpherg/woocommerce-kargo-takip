<?php
defined( 'ABSPATH' ) || exit;

echo esc_html( $email_heading ) . "\n\n";

$order_id = $order->get_id();
$carrier  = $order->get_meta( \WC_Kargo_Takip::META_CARRIER );
$tracking = $order->get_meta( \WC_Kargo_Takip::META_TRACKING );
$delivered_date = $order->get_meta( \WC_Kargo_Takip::META_DELIVERED_DATE );
$link     = \WC_Kargo_Takip::instance()->build_tracking_url( $carrier, $tracking );
$carriers = \WC_Kargo_Takip::instance()->get_supported_carriers();
$carrier_label = isset( $carriers[ $carrier ] ) ? $carriers[ $carrier ] : __( 'Kargo', 'wc-kargo-takip' );

printf( /* translators: %s: first name */ esc_html__( 'Merhaba %s, siparişiniz teslim edildi.', 'wc-kargo-takip' ), esc_html( $order->get_billing_first_name() ) );
echo "\n\n";

if ( ! empty( $tracking ) ) {
	echo esc_html( $carrier_label ) . ': ';
	echo $link ? esc_url_raw( $link ) : esc_html( $tracking );
	echo "\n\n";
}

if ( $delivered_date ) {
	echo 'Teslim Tarihi: ' . esc_html( $delivered_date ) . "\n\n";
}

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( ! empty( $additional_content ) ) {
	echo wp_strip_all_tags( wptexturize( $additional_content ) ) . "\n\n";
}

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );


