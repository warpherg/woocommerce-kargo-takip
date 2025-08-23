<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Kargo_Takip' ) ) {
	class WC_Kargo_Takip {
		private static $instance = null;

		const META_CARRIER = '_wc_kargo_takip_carrier';
		const META_TRACKING = '_wc_kargo_takip_number';
		const META_DELIVERED = '_wc_kargo_teslim_edildi';
		const META_DELIVERED_DATE = '_wc_kargo_teslim_tarihi';
		const STATUS_KEY = 'wc-kargoda';
		const OPTION_URL_PREFIX = 'wc_kargo_takip_url_template_';

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			// Order status
			add_action( 'init', [ $this, 'register_order_status' ] );
			add_filter( 'wc_order_statuses', [ $this, 'inject_order_status' ] );

			// Admin meta box
			add_action( 'add_meta_boxes', [ $this, 'add_order_metabox' ] );
			add_action( 'save_post_shop_order', [ $this, 'save_order_metabox' ], 10, 2 );

			// WooCommerce HPOS/edit screen fields
			add_action( 'woocommerce_admin_order_data_after_order_details', [ $this, 'render_order_fields_hpos' ] );
			add_action( 'woocommerce_process_shop_order_meta', [ $this, 'save_order_fields_hpos' ], 10, 2 );

			// Emails
			add_filter( 'woocommerce_email_classes', [ $this, 'register_email_class' ] );
			add_action( 'woocommerce_order_status_changed', [ $this, 'maybe_trigger_email_on_kargoda' ], 20, 4 );
			// Ek garanti: doğrudan durum kancası
			add_action( 'woocommerce_order_status_kargoda', [ $this, 'trigger_kargoda_email_direct' ], 20, 2 );
			add_filter( 'woocommerce_email_classes', [ $this, 'register_email_class_delivered' ] );
			add_action( 'woocommerce_order_status_changed', [ $this, 'maybe_trigger_email_on_delivered' ], 20, 4 );

			// Frontend display (My Account, Thank you, Shortcode)
			add_action( 'woocommerce_order_details_after_order_table', [ $this, 'render_tracking_block' ] );
			add_action( 'woocommerce_thankyou', [ $this, 'render_tracking_block' ] );
			add_shortcode( 'wc_kargo_takip', [ $this, 'shortcode_tracking' ] );

			// REST webhook endpoint for delivered
			add_action( 'rest_api_init', [ $this, 'register_rest_endpoints' ] );

			// Admin list column and bulk actions
			add_filter( 'manage_edit-shop_order_columns', [ $this, 'add_admin_tracking_column' ] );
			add_action( 'manage_shop_order_posts_custom_column', [ $this, 'render_admin_tracking_column' ], 10, 2 );
			add_filter( 'bulk_actions-edit-shop_order', [ $this, 'register_bulk_actions' ] );
			add_filter( 'handle_bulk_actions-edit-shop_order', [ $this, 'handle_bulk_actions' ], 10, 3 );

			// Admin styles
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_styles' ] );
			// Frontend styles
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_front_styles' ] );
			// Admin menu - settings page
			add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
			// Plugin action links
			add_filter( 'plugin_action_links_' . plugin_basename( WC_KARGO_TAKIP_FILE ), [ $this, 'plugin_action_links' ] );
			// Settings link under WooCommerce > Settings > Shipping
			add_filter( 'woocommerce_get_sections_shipping', [ $this, 'add_settings_section' ] );
			add_filter( 'woocommerce_get_settings_shipping', [ $this, 'add_settings_fields' ], 10, 2 );

			// Refund handling: ensure status is synced when order is fully refunded
			add_action( 'woocommerce_order_fully_refunded', [ $this, 'on_order_fully_refunded' ], 10, 2 );
			add_action( 'woocommerce_order_partially_refunded', [ $this, 'on_order_partially_refunded' ], 10, 2 );
			add_action( 'woocommerce_refund_created', [ $this, 'on_refund_created' ], 10, 1 );
			add_action( 'woocommerce_order_status_refunded', [ $this, 'on_order_status_refunded' ], 10, 2 );

			// Cron işleyici
			add_action( 'wc_kargo_takip_cron', [ $this, 'cron_check_delivered' ] );
		}

		public function register_admin_menu() {
			add_submenu_page(
				'woocommerce',
				__( 'Kargo Takip Ayarları', 'wc-kargo-takip' ),
				__( 'Kargo Takip', 'wc-kargo-takip' ),
				'manage_woocommerce',
				'wc-kargo-takip-settings',
				[ $this, 'render_settings_page' ]
			);
		}

		public function plugin_action_links( $links ) {
			$settings_url = admin_url( 'admin.php?page=wc-kargo-takip-settings' );
			array_unshift( $links, '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Ayarlar', 'wc-kargo-takip' ) . '</a>' );
			return $links;
		}

		public function render_settings_page() {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}
			// Build settings array using the same definition as WC Shipping section
			$settings = $this->add_settings_fields( [], 'wc_kargo_takip' );
			if ( isset( $_POST['save'] ) ) {
				check_admin_referer( 'wc_kargo_takip_save_settings', 'wc_kargo_takip_nonce' );
				if ( function_exists( 'woocommerce_update_options' ) ) {
					woocommerce_update_options( $settings );
				}
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Ayarlar kaydedildi.', 'wc-kargo-takip' ) . '</p></div>';
			}
			echo '<div class="wrap wc-kargo-takip-admin">';
			echo '<h1>' . esc_html__( 'Kargo Takip Ayarları', 'wc-kargo-takip' ) . '</h1>';
			echo '<form method="post">';
			wp_nonce_field( 'wc_kargo_takip_save_settings', 'wc_kargo_takip_nonce' );
			if ( function_exists( 'woocommerce_admin_fields' ) ) {
				woocommerce_admin_fields( $settings );
			}
			echo '<p class="submit"><button type="submit" name="save" class="button-primary">' . esc_html__( 'Kaydet', 'wc-kargo-takip' ) . '</button></p>';
			echo '</form>';
			echo '</div>';
		}
		public function cron_check_delivered() {
			$enabled = 'yes' === get_option( 'wc_kargo_takip_cron_enabled', 'no' );
			$keywords = get_option( 'wc_kargo_takip_cron_keywords', 'TESLIM|TESLİM|DELIVERED' );
			if ( ! $enabled ) {
				return;
			}
			$orders = wc_get_orders( [
				'limit'   => 20,
				'orderby' => 'date',
				'order'   => 'DESC',
				'status'  => [ 'kargoda', 'processing', 'on-hold' ],
				'meta_key' => self::META_TRACKING,
			] );
			foreach ( $orders as $order ) {
				$tracking = $order->get_meta( self::META_TRACKING );
				$carrier  = $order->get_meta( self::META_CARRIER );
				if ( ! $tracking ) {
					continue;
				}
				// Basit kontrol: Aras sayfası gibi HTML içinden anahtar kelime arama
				$response = wp_remote_get( $this->build_tracking_url( $carrier, $tracking ), [ 'timeout' => 12 ] );
				if ( is_wp_error( $response ) ) {
					continue;
				}
				$body = wp_remote_retrieve_body( $response );
				if ( $body && preg_match( '/' . $keywords . '/iu', $body ) ) {
					$order->update_meta_data( self::META_DELIVERED, '1' );
					$order->update_meta_data( self::META_DELIVERED_DATE, current_time( 'mysql' ) );
					if ( 'completed' !== $order->get_status() ) {
						$order->update_status( 'completed', __( 'Otomatik kontrol ile teslim edildi olarak işaretlendi.', 'wc-kargo-takip' ), true );
					}
					$order->save();
				}
			}
		}
		public function add_settings_section( $sections ) {
			$sections['wc_kargo_takip'] = __( 'Kargo Takip', 'wc-kargo-takip' );
			return $sections;
		}

		public function add_settings_fields( $settings, $current_section ) {
			if ( 'wc_kargo_takip' !== $current_section ) {
				return $settings;
			}
			$settings = [];
			$settings[] = [
				'name' => __( 'Kargo Takip Ayarları', 'wc-kargo-takip' ),
				'type' => 'title',
				'id'   => 'wc_kargo_takip_settings_title'
			];
			$settings[] = [
				'name' => __( 'Webhook Secret', 'wc-kargo-takip' ),
				'desc' => __( 'REST endpoint: /wp-json/wc-kargo-takip/v1/delivered', 'wc-kargo-takip' ),
				'id'   => 'wc_kargo_takip_webhook_secret',
				'type' => 'text',
				'css'  => 'min-width:300px;',
				'autoload' => false,
			];
			// Carrier URL templates section
			$settings[] = [
				'name' => __( 'Kargo Link Şablonları', 'wc-kargo-takip' ),
				'type' => 'title',
				'id'   => 'wc_kargo_takip_carrier_urls_title',
				'desc' => __( 'Değişken: {tracking}. Boş bırakılırsa varsayılan şablon kullanılır.', 'wc-kargo-takip' ),
			];
			foreach ( $this->get_supported_carriers() as $key => $label ) {
				$settings[] = [
					'name' => $label,
					'id'   => self::OPTION_URL_PREFIX . $key,
					'type' => 'text',
					'css'  => 'min-width:500px;',
					'autoload' => false,
				];
			}
			$settings[] = [ 'type' => 'sectionend', 'id' => 'wc_kargo_takip_carrier_urls_title' ];
			// Cron settings
			$settings[] = [
				'name' => __( 'Otomatik Teslim Kontrolü', 'wc-kargo-takip' ),
				'type' => 'title',
				'id'   => 'wc_kargo_takip_cron_title',
				'desc' => __( 'Saatlik olarak takip sayfasını kontrol eder ve teslim ifadelerini bulursa siparişi tamamlar.', 'wc-kargo-takip' ),
			];
			$settings[] = [
				'name' => __( 'Etkinleştir', 'wc-kargo-takip' ),
				'id'   => 'wc_kargo_takip_cron_enabled',
				'type' => 'checkbox',
				'default' => 'no',
			];
			$settings[] = [
				'name' => __( 'Anahtar Kelimeler (regex)', 'wc-kargo-takip' ),
				'id'   => 'wc_kargo_takip_cron_keywords',
				'type' => 'text',
				'css'  => 'min-width:400px;',
				'desc' => __( 'Ör: TESLIM|TESLİM|DELIVERED', 'wc-kargo-takip' ),
				'default' => 'TESLIM|TESLİM|DELIVERED',
			];
			$settings[] = [ 'type' => 'sectionend', 'id' => 'wc_kargo_takip_cron_title' ];
			$settings[] = [ 'type' => 'sectionend', 'id' => 'wc_kargo_takip_settings_title' ];
			return $settings;
		}

		public function register_order_status() {
			register_post_status( self::STATUS_KEY, [
				'label'                     => _x( 'Kargoda', 'Order status', 'wc-kargo-takip' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Kargoda <span class="count">(%s)</span>', 'Kargoda <span class="count">(%s)</span>', 'wc-kargo-takip' ),
			] );
		}

		public function inject_order_status( $statuses ) {
			$new_statuses = [];
			foreach ( $statuses as $key => $label ) {
				$new_statuses[ $key ] = $label;
				if ( 'wc-processing' === $key ) {
					$new_statuses[ self::STATUS_KEY ] = _x( 'Kargoda', 'Order status', 'wc-kargo-takip' );
				}
			}
			return $new_statuses;
		}

		public function add_order_metabox() {
			add_meta_box(
				'wc-kargo-takip-metabox',
				__( 'Kargo Takip', 'wc-kargo-takip' ),
				[ $this, 'render_order_metabox' ],
				'shop_order',
				'side',
				'default'
			);
		}

		public function render_order_metabox( $post ) {
			$order_id = $post->ID;
			$carrier   = get_post_meta( $order_id, self::META_CARRIER, true );
			$tracking  = get_post_meta( $order_id, self::META_TRACKING, true );
			$delivered = get_post_meta( $order_id, self::META_DELIVERED, true );
			$delivered_date = get_post_meta( $order_id, self::META_DELIVERED_DATE, true );
			wp_nonce_field( 'wc_kargo_takip_save', 'wc_kargo_takip_nonce' );
			$carriers = $this->get_supported_carriers();
			?>
			<p>
				<label for="wc_kargo_takip_carrier"><strong><?php esc_html_e( 'Kargo Firması', 'wc-kargo-takip' ); ?></strong></label>
				<select name="wc_kargo_takip_carrier" id="wc_kargo_takip_carrier" style="width:100%">
					<option value="">—</option>
					<?php foreach ( $carriers as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $carrier, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label for="wc_kargo_takip_number"><strong><?php esc_html_e( 'Takip Numarası', 'wc-kargo-takip' ); ?></strong></label>
				<input type="text" name="wc_kargo_takip_number" id="wc_kargo_takip_number" value="<?php echo esc_attr( $tracking ); ?>" style="width:100%" />
			</p>
			<p>
				<label><input type="checkbox" name="wc_kargo_teslim_edildi" value="1" <?php checked( (bool) $delivered, true ); ?> /> <?php esc_html_e( 'Teslim edildi', 'wc-kargo-takip' ); ?></label>
			</p>
			<p>
				<label for="wc_kargo_teslim_tarihi"><strong><?php esc_html_e( 'Teslim Tarihi', 'wc-kargo-takip' ); ?></strong></label>
				<input type="datetime-local" name="wc_kargo_teslim_tarihi" id="wc_kargo_teslim_tarihi" value="<?php echo esc_attr( $delivered_date ); ?>" style="width:100%" />
			</p>
			<?php
		}

		public function save_order_metabox( $post_id, $post ) {
			if ( 'shop_order' !== $post->post_type ) {
				return;
			}
			if ( ! isset( $_POST['wc_kargo_takip_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wc_kargo_takip_nonce'] ) ), 'wc_kargo_takip_save' ) ) {
				return;
			}
			if ( ! current_user_can( 'edit_shop_order', $post_id ) ) {
				return;
			}

			$carrier  = isset( $_POST['wc_kargo_takip_carrier'] ) ? sanitize_key( wp_unslash( $_POST['wc_kargo_takip_carrier'] ) ) : '';
			$tracking = isset( $_POST['wc_kargo_takip_number'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_kargo_takip_number'] ) ) : '';

			$order = wc_get_order( $post_id );
			if ( ! $order ) {
				return;
			}

			if ( $carrier ) {
				$order->update_meta_data( self::META_CARRIER, $carrier );
			} else {
				$order->delete_meta_data( self::META_CARRIER );
			}
			if ( $tracking ) {
				$order->update_meta_data( self::META_TRACKING, $tracking );
			} else {
				$order->delete_meta_data( self::META_TRACKING );
			}
			$delivered = isset( $_POST['wc_kargo_teslim_edildi'] ) ? (bool) intval( $_POST['wc_kargo_teslim_edildi'] ) : false;
			$delivered_date = isset( $_POST['wc_kargo_teslim_tarihi'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_kargo_teslim_tarihi'] ) ) : '';
			if ( $delivered ) {
				$order->update_meta_data( self::META_DELIVERED, '1' );
				if ( empty( $delivered_date ) ) {
					$delivered_date = current_time( 'mysql' );
				}
				$order->update_meta_data( self::META_DELIVERED_DATE, $delivered_date );
			} else {
				$order->delete_meta_data( self::META_DELIVERED );
				$order->delete_meta_data( self::META_DELIVERED_DATE );
			}
			// Teslim edilirse otomatik tamamla
			if ( $delivered && 'completed' !== $order->get_status() ) {
				$order->update_status( 'completed', __( 'Kargo teslim edildiği için otomatik tamamlandı.', 'wc-kargo-takip' ), true );
			}
			$order->save();
		}

		public function render_order_fields_hpos( $order ) {
			if ( ! $order instanceof WC_Order ) {
				return;
			}
			$carrier  = $order->get_meta( self::META_CARRIER );
			$tracking = $order->get_meta( self::META_TRACKING );
			$delivered = $order->get_meta( self::META_DELIVERED );
			$delivered_date = $order->get_meta( self::META_DELIVERED_DATE );
			$carriers = $this->get_supported_carriers();
			wp_nonce_field( 'wc_kargo_takip_save', 'wc_kargo_takip_nonce' );
			$link     = $this->build_tracking_url( $carrier, $tracking );
			?>
			<div class="address wc-kargo-takip-admin">
				<h3><?php esc_html_e( 'Kargo Takip', 'wc-kargo-takip' ); ?></h3>
				<p class="form-field form-field-wide">
					<label for="wc_kargo_takip_carrier"><?php esc_html_e( 'Kargo Firması', 'wc-kargo-takip' ); ?></label>
					<select name="wc_kargo_takip_carrier" id="wc_kargo_takip_carrier" style="min-width:240px">
						<option value="">—</option>
						<?php foreach ( $carriers as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $carrier, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="form-field form-field-wide">
					<label for="wc_kargo_takip_number"><?php esc_html_e( 'Takip Numarası', 'wc-kargo-takip' ); ?></label>
					<input type="text" class="short" name="wc_kargo_takip_number" id="wc_kargo_takip_number" value="<?php echo esc_attr( $tracking ); ?>" />
					<?php if ( $tracking ) : ?>
						<span class="description" style="margin-left:8px;">
							<?php if ( $link ) : ?>
								<a href="<?php echo esc_url( $link ); ?>" class="button button-small" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Takip linkini aç', 'wc-kargo-takip' ); ?></a>
							<?php endif; ?>
						</span>
					<?php endif; ?>
				</p>
				<p class="form-field form-field-wide">
					<label for="wc_kargo_teslim_edildi">
						<input type="checkbox" name="wc_kargo_teslim_edildi" id="wc_kargo_teslim_edildi" value="1" <?php checked( (bool) $delivered, true ); ?> />
						<?php esc_html_e( 'Teslim edildi', 'wc-kargo-takip' ); ?>
					</label>
				</p>
				<p class="form-field form-field-wide">
					<label for="wc_kargo_teslim_tarihi"><?php esc_html_e( 'Teslim Tarihi', 'wc-kargo-takip' ); ?></label>
					<input type="datetime-local" class="short" name="wc_kargo_teslim_tarihi" id="wc_kargo_teslim_tarihi" value="<?php echo esc_attr( $delivered_date ); ?>" />
				</p>
			</div>
			<?php
		}

		public function save_order_fields_hpos( $order_id, $post ) {
			if ( ! isset( $_POST['wc_kargo_takip_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wc_kargo_takip_nonce'] ) ), 'wc_kargo_takip_save' ) ) {
				return;
			}
			if ( ! current_user_can( 'edit_shop_order', $order_id ) ) {
				return;
			}
			$carrier  = isset( $_POST['wc_kargo_takip_carrier'] ) ? sanitize_key( wp_unslash( $_POST['wc_kargo_takip_carrier'] ) ) : '';
			$tracking = isset( $_POST['wc_kargo_takip_number'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_kargo_takip_number'] ) ) : '';
			$order   = wc_get_order( $order_id );
			if ( ! $order ) {
				return;
			}
			if ( $carrier ) {
				$order->update_meta_data( self::META_CARRIER, $carrier );
			} else {
				$order->delete_meta_data( self::META_CARRIER );
			}
			if ( $tracking ) {
				$order->update_meta_data( self::META_TRACKING, $tracking );
			} else {
				$order->delete_meta_data( self::META_TRACKING );
			}
			$order->save();
		}

		public function register_email_class( $emails ) {
			require_once WC_KARGO_TAKIP_PATH . 'includes/emails/class-wc-email-customer-kargoda.php';
			$emails['WC_Email_Customer_Kargoda'] = new WC_Email_Customer_Kargoda();
			return $emails;
		}

		public function register_email_class_delivered( $emails ) {
			require_once WC_KARGO_TAKIP_PATH . 'includes/emails/class-wc-email-customer-delivered.php';
			$emails['WC_Email_Customer_Delivered'] = new WC_Email_Customer_Delivered();
			return $emails;
		}

		public function maybe_trigger_email_on_kargoda( $order_id, $old_status, $new_status, $order ) {
			if ( 'kargoda' === $new_status ) {
				$emails = WC()->mailer()->get_emails();
				if ( isset( $emails['WC_Email_Customer_Kargoda'] ) ) {
					$emails['WC_Email_Customer_Kargoda']->trigger( $order_id );
				}
			}
		}

		public function trigger_kargoda_email_direct( $order_id, $order ) {
			$emails = WC()->mailer()->get_emails();
			if ( isset( $emails['WC_Email_Customer_Kargoda'] ) ) {
				$emails['WC_Email_Customer_Kargoda']->trigger( $order_id );
			}
		}

		public function maybe_trigger_email_on_delivered( $order_id, $old_status, $new_status, $order ) {
			if ( 'completed' === $new_status ) {
				$emails = WC()->mailer()->get_emails();
				if ( isset( $emails['WC_Email_Customer_Delivered'] ) ) {
					$emails['WC_Email_Customer_Delivered']->trigger( $order_id );
				}
			}
		}

		public function render_tracking_block( $order_id_or_order ) {
			$order = $order_id_or_order instanceof WC_Order ? $order_id_or_order : wc_get_order( $order_id_or_order );
			if ( ! $order ) {
				return;
			}
			$carrier  = $order->get_meta( self::META_CARRIER );
			$tracking = $order->get_meta( self::META_TRACKING );
			$delivered = $order->get_meta( self::META_DELIVERED );
			$delivered_date = $order->get_meta( self::META_DELIVERED_DATE );
			if ( empty( $tracking ) ) {
				return;
			}
			$carriers = $this->get_supported_carriers();
			$link     = $this->build_tracking_url( $carrier, $tracking );
			$carrier_label = isset( $carriers[ $carrier ] ) ? $carriers[ $carrier ] : __( 'Kargo', 'wc-kargo-takip' );
			echo '<section class="wc-kargo-takip wc-kargo-card">';
			echo '<h2 class="wc-kargo-title">' . esc_html__( 'Kargo Takibi', 'wc-kargo-takip' ) . '</h2>';
			echo '<div class="wc-kargo-row">';
			echo '<div class="wc-kargo-info"><strong>' . esc_html( $carrier_label ) . '</strong>: ';
			if ( $link ) {
				echo '<a class="wc-kargo-link" href="' . esc_url( $link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $tracking ) . '</a>';
			} else {
				echo esc_html( $tracking );
			}
			if ( $delivered ) {
				echo ' <span class="wc-kargo-badge delivered">' . esc_html__( 'Teslim edildi', 'wc-kargo-takip' ) . '</span>';
			}
			echo '</div>';
			echo '<div class="wc-kargo-actions">';
			echo '<button type="button" class="button wc-kargo-copy" data-copy="' . esc_attr( $tracking ) . '">' . esc_html__( 'Kopyala', 'wc-kargo-takip' ) . '</button> ';
			if ( $link ) {
				echo '<a class="button" href="' . esc_url( $link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Takip sayfasını aç', 'wc-kargo-takip' ) . '</a>';
			}
			echo '</div>';
			echo '</div>';
			if ( $delivered && $delivered_date ) {
				echo '<small class="wc-kargo-meta">' . esc_html__( 'Teslim Tarihi', 'wc-kargo-takip' ) . ': ' . esc_html( $delivered_date ) . '</small>';
			}
			echo '<script>(function(){document.addEventListener("click",function(e){var t=e.target;if(t&&t.classList.contains("wc-kargo-copy")){var v=t.getAttribute("data-copy");if(navigator.clipboard){navigator.clipboard.writeText(v).then(function(){t.textContent="' . esc_js( __( 'Kopyalandı', 'wc-kargo-takip' ) ) . '";setTimeout(function(){t.textContent="' . esc_js( __( 'Kopyala', 'wc-kargo-takip' ) ) . '";},1500);});}}});})();</script>';
			echo '</section>';
		}

		/**
		 * Refund integration: auto-set status to refunded for fully refunded orders,
		 * and clear tracking metadata when moved to refunded.
		 */
		public function on_order_fully_refunded( $order_id, $refund_id = 0 ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return;
			}
			if ( 'refunded' !== $order->get_status() ) {
				$order->update_status( 'refunded', __( 'Ödeme iadesi sonrası durum güncellendi.', 'wc-kargo-takip' ), true );
			}
		}

		public function on_order_partially_refunded( $order_id, $refund_id = 0 ) {
			// If an order becomes fully refunded after a partial refund (multiple steps), update status.
			$this->maybe_mark_refunded_if_fully( $order_id );
		}

		public function on_refund_created( $refund_id ) {
			$refund = wc_get_order( $refund_id );
			if ( ! $refund || ! method_exists( $refund, 'get_parent_id' ) ) {
				return;
			}
			$order_id = $refund->get_parent_id();
			$this->maybe_mark_refunded_if_fully( $order_id );
		}

		private function maybe_mark_refunded_if_fully( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return;
			}
			$total          = floatval( $order->get_total() );
			$total_refunded = floatval( $order->get_total_refunded() );
			if ( $total > 0 && $total_refunded >= $total && 'refunded' !== $order->get_status() ) {
				$order->update_status( 'refunded', __( 'Tam iade tamamlandı, durum iade edildi olarak güncellendi.', 'wc-kargo-takip' ), true );
			}
		}

		public function on_order_status_refunded( $order_id, $order ) {
			if ( ! $order instanceof WC_Order ) {
				$order = wc_get_order( $order_id );
			}
			if ( ! $order ) {
				return;
			}
			// Clear tracking-related metadata when order is refunded
			$order->delete_meta_data( self::META_CARRIER );
			$order->delete_meta_data( self::META_TRACKING );
			$order->delete_meta_data( self::META_DELIVERED );
			$order->delete_meta_data( self::META_DELIVERED_DATE );
			$order->save();
		}

		public function shortcode_tracking( $atts ) {
			$atts = shortcode_atts( [ 'order_id' => 0 ], $atts );
			$order = wc_get_order( absint( $atts['order_id'] ) );
			if ( ! $order ) {
				return '';
			}
			ob_start();
			$this->render_tracking_block( $order );
			return ob_get_clean();
		}

		public function register_rest_endpoints() {
			register_rest_route( 'wc-kargo-takip/v1', '/delivered', [
				'methods'  => 'POST',
				'callback' => [ $this, 'rest_mark_delivered' ],
				'permission_callback' => '__return_true',
			] );
			register_rest_route( 'wc-kargo-takip/v1', '/events', [
				'methods'  => 'POST',
				'callback' => [ $this, 'rest_import_events' ],
				'permission_callback' => '__return_true',
			] );
		}

		public function rest_mark_delivered( $request ) {
			$params = $request->get_params();
			$secret = isset( $params['secret'] ) ? sanitize_text_field( $params['secret'] ) : '';
			$expected = get_option( 'wc_kargo_takip_webhook_secret' );
			if ( empty( $expected ) || ! hash_equals( (string) $expected, (string) $secret ) ) {
				return new WP_REST_Response( [ 'ok' => false, 'error' => 'unauthorized' ], 401 );
			}
			$order_id = isset( $params['order_id'] ) ? absint( $params['order_id'] ) : 0;
			$tracking = isset( $params['tracking'] ) ? sanitize_text_field( $params['tracking'] ) : '';
			$order = null;
			if ( $order_id ) {
				$order = wc_get_order( $order_id );
			} elseif ( $tracking ) {
				$orders = wc_get_orders( [
					'limit'    => 1,
					'orderby'  => 'date',
					'order'    => 'DESC',
					'meta_key' => self::META_TRACKING,
					'meta_value' => $tracking,
				] );
				$order = ! empty( $orders ) ? $orders[0] : null;
			}
			if ( ! $order ) {
				return new WP_REST_Response( [ 'ok' => false, 'error' => 'order_not_found' ], 404 );
			}
			$order->update_meta_data( self::META_DELIVERED, '1' );
			$order->update_meta_data( self::META_DELIVERED_DATE, current_time( 'mysql' ) );
			if ( 'completed' !== $order->get_status() ) {
				$order->update_status( 'completed', __( 'Webhook ile teslim edildi olarak işaretlendi.', 'wc-kargo-takip' ), true );
			}
			$order->save();
			return new WP_REST_Response( [ 'ok' => true, 'order_id' => $order->get_id() ], 200 );
		}

		public function rest_import_events( $request ) {
			$params = $request->get_params();
			$secret = isset( $params['secret'] ) ? sanitize_text_field( $params['secret'] ) : '';
			$expected = get_option( 'wc_kargo_takip_webhook_secret' );
			if ( empty( $expected ) || ! hash_equals( (string) $expected, (string) $secret ) ) {
				return new WP_REST_Response( [ 'ok' => false, 'error' => 'unauthorized' ], 401 );
			}
			$order_id = isset( $params['order_id'] ) ? absint( $params['order_id'] ) : 0;
			$events   = isset( $params['events'] ) && is_array( $params['events'] ) ? $params['events'] : [];
			if ( ! $order_id || empty( $events ) ) {
				return new WP_REST_Response( [ 'ok' => false, 'error' => 'bad_request' ], 400 );
			}
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return new WP_REST_Response( [ 'ok' => false, 'error' => 'order_not_found' ], 404 );
			}
			foreach ( $events as $event ) {
				$time = isset( $event['time'] ) ? sanitize_text_field( $event['time'] ) : '';
				$desc = isset( $event['description'] ) ? sanitize_text_field( $event['description'] ) : '';
				if ( $time || $desc ) {
					$order->add_order_note( trim( $time . ' - ' . $desc ) );
				}
			}
			return new WP_REST_Response( [ 'ok' => true ], 200 );
		}

		public function add_admin_tracking_column( $columns ) {
			$new = [];
			foreach ( $columns as $key => $label ) {
				$new[ $key ] = $label;
				if ( 'order_total' === $key ) {
					$new['wc_kargo_tracking'] = __( 'Kargo/Tracking', 'wc-kargo-takip' );
				}
			}
			return $new;
		}

		public function render_admin_tracking_column( $column, $post_id ) {
			if ( 'wc_kargo_tracking' !== $column ) {
				return;
			}
			$order = wc_get_order( $post_id );
			if ( ! $order ) {
				return;
			}
			$carrier  = $order->get_meta( self::META_CARRIER );
			$tracking = $order->get_meta( self::META_TRACKING );
			if ( ! $tracking ) {
				echo '—';
				return;
			}
			$carriers = $this->get_supported_carriers();
			$link     = $this->build_tracking_url( $carrier, $tracking );
			$carrier_label = isset( $carriers[ $carrier ] ) ? $carriers[ $carrier ] : __( 'Kargo', 'wc-kargo-takip' );
			echo esc_html( $carrier_label ) . ': ';
			if ( $link ) {
				echo '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $tracking ) . '</a>';
			} else {
				echo esc_html( $tracking );
			}
		}

		public function register_bulk_actions( $bulk_actions ) {
			$bulk_actions['wc_mark_kargoda']   = __( 'Durumu Kargoda yap', 'wc-kargo-takip' );
			$bulk_actions['wc_mark_delivered'] = __( 'Teslim edildi ve tamamla', 'wc-kargo-takip' );
			return $bulk_actions;
		}

		public function handle_bulk_actions( $redirect_to, $action, $post_ids ) {
			if ( 'wc_mark_kargoda' === $action ) {
				foreach ( (array) $post_ids as $post_id ) {
					$order = wc_get_order( $post_id );
					if ( $order ) {
						$order->update_status( 'kargoda' );
					}
				}
			}
			if ( 'wc_mark_delivered' === $action ) {
				foreach ( (array) $post_ids as $post_id ) {
					$order = wc_get_order( $post_id );
					if ( $order ) {
						$order->update_meta_data( self::META_DELIVERED, '1' );
						$order->update_meta_data( self::META_DELIVERED_DATE, current_time( 'mysql' ) );
						$order->update_status( 'completed', __( 'Toplu işlem ile teslim edildi.', 'wc-kargo-takip' ), true );
						$order->save();
					}
				}
			}
			return $redirect_to;
		}

		public function enqueue_admin_styles() {
			wp_enqueue_style( 'wc-kargo-takip-admin', WC_KARGO_TAKIP_URL . 'assets/admin.css', [], WC_KARGO_TAKIP_VERSION );
		}

		public function get_supported_carriers() {
			return [
				'yurtici' => 'Yurtiçi Kargo',
				'aras'    => 'Aras Kargo',
				'mng'     => 'MNG Kargo',
				'surat'   => 'Sürat Kargo',
				'ptt'     => 'PTT Kargo',
				'hepsijet'=> 'Hepsijet',
				'trendyol-express' => 'Trendyol Express',
			];
		}

		public function build_tracking_url( $carrier, $tracking_number ) {
			$tracking_number = trim( (string) $tracking_number );
			if ( '' === $tracking_number ) {
				return '';
			}
			// Custom template override from settings
			$template = get_option( self::OPTION_URL_PREFIX . (string) $carrier );
			if ( ! empty( $template ) ) {
				return str_replace( '{tracking}', rawurlencode( $tracking_number ), $template );
			}
			switch ( $carrier ) {
				case 'yurtici':
					return 'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code=' . rawurlencode( $tracking_number );
				case 'aras':
					return 'https://kargotakip.araskargo.com.tr/mainpage.aspx?code=' . rawurlencode( $tracking_number );
				case 'mng':
					return 'https://apistage.mngkargo.com.tr/branchtracking/' . rawurlencode( $tracking_number );
				case 'surat':
					return 'https://www.suratkargo.com.tr/KargoTakip/?kargotakipno=' . rawurlencode( $tracking_number );
				case 'ptt':
					return 'https://gonderitakip.ptt.gov.tr/Track/Verify?pTTCode=' . rawurlencode( $tracking_number );
				case 'hepsijet':
					return 'https://www.hepsijet.com/tr/parcel-tracking/' . rawurlencode( $tracking_number );
				case 'trendyol-express':
					return '';
				default:
					return '';
			}
		}

		public function enqueue_front_styles() {
			wp_enqueue_style( 'wc-kargo-takip-front', WC_KARGO_TAKIP_URL . 'assets/frontend.css', [], WC_KARGO_TAKIP_VERSION );
		}
	}
}


