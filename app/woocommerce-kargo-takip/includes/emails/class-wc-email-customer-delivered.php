<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Email_Customer_Delivered' ) ) {
	class WC_Email_Customer_Delivered extends WC_Email {
		public function __construct() {
			$this->id             = 'customer_delivered';
			$this->title          = __( 'Müşteri - Teslim Edildi', 'wc-kargo-takip' );
			$this->description    = __( 'Sipariş teslim edildiğinde müşteriye gönderilen e-posta.', 'wc-kargo-takip' );
			$this->customer_email = true;

			$this->template_html  = 'emails/customer-delivered.php';
			$this->template_plain = 'emails/plain/customer-delivered.php';
			$this->template_base  = WC_KARGO_TAKIP_PATH . 'templates/';

			parent::__construct();
			$this->recipient = '';
		}

		public function get_default_subject() {
			return sprintf( __( '[%s] Siparişiniz teslim edildi - {order_number}', 'wc-kargo-takip' ), wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) );
		}

		public function get_default_heading() {
			return __( 'Siparişiniz teslim edildi', 'wc-kargo-takip' );
		}

		public function trigger( $order_id, $order = false ) {
			if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
				$order = wc_get_order( $order_id );
			}
			if ( ! $order ) {
				return;
			}

			$this->object = $order;
			$this->placeholders = array_merge( (array) $this->placeholders, [
				'{order_date}'   => wc_format_datetime( $order->get_date_created() ),
				'{order_number}' => $order->get_order_number(),
			] );
			$this->recipient = $order->get_billing_email();
			if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
				return;
			}
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		public function get_content_html() {
			return wc_get_template_html( $this->template_html, [
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
				'additional_content' => $this->get_additional_content(),
			], '', $this->template_base );
		}

		public function get_content_plain() {
			return wc_get_template_html( $this->template_plain, [
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => true,
				'email'         => $this,
				'additional_content' => $this->get_additional_content(),
			], '', $this->template_base );
		}

		public function init_form_fields() {
			$this->form_fields = [
				'enabled'    => [
					'title'   => __( 'Etkinleştir/Devre dışı', 'wc-kargo-takip' ),
					'type'    => 'checkbox',
					'label'   => __( 'Bu e-postayı etkinleştir', 'wc-kargo-takip' ),
					'default' => 'yes',
				],
				'subject'    => [
					'title'       => __( 'Konu', 'wc-kargo-takip' ),
					'type'        => 'text',
					'description' => sprintf( __( 'Varsayılan: %s', 'wc-kargo-takip' ), $this->get_default_subject() ),
					'placeholder' => '',
					'default'     => ''
				],
				'heading'    => [
					'title'       => __( 'Başlık', 'wc-kargo-takip' ),
					'type'        => 'text',
					'description' => sprintf( __( 'Varsayılan: %s', 'wc-kargo-takip' ), $this->get_default_heading() ),
					'placeholder' => '',
					'default'     => ''
				],
				'additional_content' => [
					'title'       => __( 'Ek içerik', 'wc-kargo-takip' ),
					'description' => __( 'E-postanın altına eklenecek içerik.', 'wc-kargo-takip' ),
					'type'        => 'textarea',
					'default'     => '',
					'placeholder' => __( 'Bizi tercih ettiğiniz için teşekkür ederiz.', 'wc-kargo-takip' ),
				],
			];
		}
	}
}


