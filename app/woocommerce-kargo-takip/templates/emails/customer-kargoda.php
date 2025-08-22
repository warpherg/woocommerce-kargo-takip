<?php
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );

$order_id = $order->get_id();
$carrier  = $order->get_meta( \WC_Kargo_Takip::META_CARRIER );
$tracking = $order->get_meta( \WC_Kargo_Takip::META_TRACKING );
$link     = \WC_Kargo_Takip::instance()->build_tracking_url( $carrier, $tracking );
$carriers = \WC_Kargo_Takip::instance()->get_supported_carriers();
$carrier_label = isset( $carriers[ $carrier ] ) ? $carriers[ $carrier ] : __( 'Kargo', 'wc-kargo-takip' );
?>

<p><?php printf( esc_html__( 'Merhaba %s, siparişiniz kargoya verildi.', 'wc-kargo-takip' ), esc_html( $order->get_billing_first_name() ) ); ?></p>

<?php if ( ! empty( $tracking ) ) : ?>
	<p>
		<strong><?php echo esc_html( $carrier_label ); ?>:</strong>
		<?php if ( $link ) : ?>
			<a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $tracking ); ?></a>
		<?php else : ?>
			<?php echo esc_html( $tracking ); ?>
		<?php endif; ?>
	</p>
<?php endif; ?>

<?php
// Standart sipariş detayı blokları
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( ! empty( $additional_content ) ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );


