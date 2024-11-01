<?php
/*
Plugin Name: WPC Role-Based Payment Methods for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Role-Based Payment Methods enables the restriction of available Payment Gateways for each user role.
Version: 1.0.0
Author: WPClever
Author URI: https://wpclever.net
Text Domain: wpc-role-based-payment-methods
Domain Path: /languages/
Requires Plugins: woocommerce
Requires at least: 4.0
Tested up to: 6.6
WC requires at least: 3.0
WC tested up to: 9.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) || exit;

! defined( 'WPCRP_VERSION' ) && define( 'WPCRP_VERSION', '1.0.0' );
! defined( 'WPCRP_LITE' ) && define( 'WPCRP_LITE', __FILE__ );
! defined( 'WPCRP_FILE' ) && define( 'WPCRP_FILE', __FILE__ );
! defined( 'WPCRP_URI' ) && define( 'WPCRP_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WPCRP_REVIEWS' ) && define( 'WPCRP_REVIEWS', 'https://wordpress.org/support/plugin/wpc-role-based-payment-methods/reviews/?filter=5' );
! defined( 'WPCRP_CHANGELOG' ) && define( 'WPCRP_CHANGELOG', 'https://wordpress.org/plugins/wpc-role-based-payment-methods/#developers' );
! defined( 'WPCRP_DISCUSSION' ) && define( 'WPCRP_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-role-based-payment-methods' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WPCRP_URI );

include 'includes/dashboard/wpc-dashboard.php';
include 'includes/kit/wpc-kit.php';
include 'includes/hpos.php';

if ( ! function_exists( 'wpcrp_init' ) ) {
	add_action( 'plugins_loaded', 'wpcrp_init', 11 );

	function wpcrp_init() {
		// load text-domain
		load_plugin_textdomain( 'wpc-role-based-payment-methods', false, basename( __DIR__ ) . '/languages/' );

		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'wpcrp_notice_wc' );

			return null;
		}

		if ( ! class_exists( 'WPCleverWpcrp' ) ) {
			class WPCleverWpcrp {
				protected static $settings = [];
				protected static $instance = null;

				public static function instance() {
					if ( is_null( self::$instance ) ) {
						self::$instance = new self();
					}

					return self::$instance;
				}

				function __construct() {
					self::$settings = (array) get_option( 'wpcrp_settings', [] );

					// settings
					add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
					add_action( 'admin_init', [ $this, 'register_settings' ] );
					add_action( 'admin_menu', [ $this, 'admin_menu' ] );

					// links
					add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
					add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

					// available payment gateways
					add_filter( 'woocommerce_available_payment_gateways', [
						$this,
						'available_payment_gateways'
					], 9999 );
				}

				function register_settings() {
					register_setting( 'wpcrp_settings', 'wpcrp_settings' );
				}

				public static function get_settings() {
					return apply_filters( 'wpcrp_get_settings', self::$settings );
				}

				public static function get_setting( $name, $default = false ) {
					if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
						$setting = self::$settings[ $name ];
					} else {
						$setting = get_option( 'wpcrp_' . $name, $default );
					}

					return apply_filters( 'wpcrp_get_setting', $setting, $name, $default );
				}

				function admin_enqueue_scripts( $hook ) {
					if ( str_contains( $hook, 'wpcrp' ) ) {
						wp_enqueue_style( 'wpcrp-backend', WPCRP_URI . 'assets/css/backend.css', [ 'woocommerce_admin_styles' ], WPCRP_VERSION );
						wp_enqueue_script( 'wpcrp-backend', WPCRP_URI . 'assets/js/backend.js', [
							'jquery',
							'selectWoo',
						], WPCRP_VERSION, true );
					}
				}

				function admin_menu() {
					add_submenu_page( 'wpclever', 'WPC Role-Based Payment Methods', 'Role-Based Payment Methods', 'manage_options', 'wpclever-wpcrp', [
						$this,
						'admin_menu_content'
					] );
				}

				function admin_menu_content() {
					$active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
					?>
                    <div class="wpclever_settings_page wrap">
                        <h1 class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Role-Based Payment Methods', 'wpc-role-based-payment-methods' ) . ' ' . esc_html( WPCRP_VERSION ) . ' ' . ( defined( 'WPCRP_PREMIUM' ) ? '<span class="premium" style="display: none">' . esc_html__( 'Premium', 'wpc-role-based-payment-methods' ) . '</span>' : '' ); ?></h1>
                        <div class="wpclever_settings_page_desc about-text">
                            <p>
								<?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-role-based-payment-methods' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                                <br/>
                                <a href="<?php echo esc_url( WPCRP_REVIEWS ); ?>" target="_blank"><?php esc_html_e( 'Reviews', 'wpc-role-based-payment-methods' ); ?></a> |
                                <a href="<?php echo esc_url( WPCRP_CHANGELOG ); ?>" target="_blank"><?php esc_html_e( 'Changelog', 'wpc-role-based-payment-methods' ); ?></a> |
                                <a href="<?php echo esc_url( WPCRP_DISCUSSION ); ?>" target="_blank"><?php esc_html_e( 'Discussion', 'wpc-role-based-payment-methods' ); ?></a>
                            </p>
                        </div>
						<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
                            <div class="notice notice-success is-dismissible">
                                <p><?php esc_html_e( 'Settings updated.', 'wpc-role-based-payment-methods' ); ?></p>
                            </div>
						<?php } ?>
                        <div class="wpclever_settings_page_nav">
                            <h2 class="nav-tab-wrapper">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcrp&tab=settings' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Settings', 'wpc-role-based-payment-methods' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>" class="nav-tab">
									<?php esc_html_e( 'Essential Kit', 'wpc-role-based-payment-methods' ); ?>
                                </a>
                            </h2>
                        </div>
                        <div class="wpclever_settings_page_content">
							<?php if ( $active_tab === 'settings' ) {
								global $wp_roles;
								?>
                                <form method="post" action="options.php">
                                    <table class="form-table">
                                        <tr class="heading">
                                            <th colspan="2">
												<?php esc_html_e( 'Payment Methods', 'wpc-role-based-payment-methods' ); ?>
                                            </th>
                                        </tr>
										<?php
										$gateways = WC()->payment_gateways->payment_gateways();

										if ( is_array( $gateways ) && ! empty( $gateways ) ) {
											foreach ( $gateways as $key => $gateway ) {
												$allowed_roles = (array) self::get_setting( $key, [ 'wpcrp_all' ] );
												?>
                                                <tr>
                                                    <th scope="row">
														<?php
														if ( wc_string_to_bool( $gateway->enabled ) ) {
															echo esc_html( $gateway->title );
														} else {
															echo '<s>' . esc_html( $gateway->title ) . '</s>';
														}
														?>
                                                    </th>
                                                    <td>
                                                        <p class="description"><?php esc_html_e( 'Choose the role(s) that are allowed to use this payment method.', 'wpc-role-based-payment-methods' ); ?></p>
                                                        <label>
                                                            <select name="<?php echo esc_attr( 'wpcrp_settings[' . $key . '][]' ); ?>" class="wpcrp_roles_selector" multiple>
																<?php
																echo '<option value="wpcrp_all" ' . ( in_array( 'wpcrp_all', $allowed_roles ) ? 'selected' : '' ) . '>' . esc_html__( 'All', 'wpc-role-based-payment-methods' ) . '</option>';
																echo '<option value="wpcrp_user" ' . ( in_array( 'wpcrp_user', $allowed_roles ) ? 'selected' : '' ) . '>' . esc_html__( 'User (logged in)', 'wpc-role-based-payment-methods' ) . '</option>';
																echo '<option value="wpcrp_guest" ' . ( in_array( 'wpcrp_guest', $allowed_roles ) ? 'selected' : '' ) . '>' . esc_html__( 'Guest (not logged in)', 'wpc-role-based-payment-methods' ) . '</option>';

																foreach ( $wp_roles->roles as $role => $details ) {
																	echo '<option value="' . esc_attr( $role ) . '" ' . ( in_array( $role, $allowed_roles ) ? 'selected' : '' ) . '>' . esc_html( $details['name'] ) . '</option>';
																}
																?>
                                                            </select> </label>
                                                    </td>
                                                </tr>
												<?php
											}
										}
										?>
                                        <tr class="submit">
                                            <th colspan="2">
												<?php settings_fields( 'wpcrp_settings' ); ?><?php submit_button(); ?>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
							<?php } ?>
                        </div><!-- /.wpclever_settings_page_content -->
                        <div class="wpclever_settings_page_suggestion">
                            <div class="wpclever_settings_page_suggestion_label">
                                <span class="dashicons dashicons-yes-alt"></span> Suggestion
                            </div>
                            <div class="wpclever_settings_page_suggestion_content">
                                <div>
                                    To display custom engaging real-time messages on any wished positions, please install
                                    <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart Messages</a> plugin. It's free!
                                </div>
                                <div>
                                    Wanna save your precious time working on variations? Try our brand-new free plugin
                                    <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC Variation Bulk Editor</a> and
                                    <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC Variation Duplicator</a>.
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
				}

				function available_payment_gateways( $_available_gateways ) {
					if ( is_array( $_available_gateways ) ) {
						foreach ( $_available_gateways as $key => $gateway ) {
							$allowed_roles = (array) self::get_setting( $key, [ 'wpcrp_all' ] );

							if ( ! self::check_roles( $allowed_roles ) ) {
								unset( $_available_gateways[ $key ] );
							}
						}
					}

					return $_available_gateways;
				}

				function check_roles( $roles ) {
					if ( is_string( $roles ) ) {
						$roles = explode( ',', $roles );
					}

					if ( empty( $roles ) || ! is_array( $roles ) || in_array( 'wpcrp_all', $roles ) ) {
						return true;
					}

					if ( is_user_logged_in() ) {
						if ( in_array( 'wpcrp_user', $roles ) ) {
							return true;
						}

						$current_user = wp_get_current_user();

						foreach ( $current_user->roles as $role ) {
							if ( in_array( $role, $roles ) ) {
								return true;
							}
						}
					} else {
						if ( in_array( 'wpcrp_guest', $roles ) ) {
							return true;
						}
					}

					return false;
				}

				function action_links( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$settings = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcrp&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-role-based-payment-methods' ) . '</a>';
						array_unshift( $links, $settings );
					}

					return (array) $links;
				}

				function row_meta( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$row_meta = [
							'support' => '<a href="' . esc_url( WPCRP_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-role-based-payment-methods' ) . '</a>',
						];

						return array_merge( $links, $row_meta );
					}

					return (array) $links;
				}
			}

			return WPCleverWpcrp::instance();
		}

		return null;
	}
}

if ( ! function_exists( 'wpcrp_notice_wc' ) ) {
	function wpcrp_notice_wc() {
		?>
        <div class="error">
            <p><strong>WPC Role-Based Payment Methods</strong> requires WooCommerce version 3.0 or greater.</p>
        </div>
		<?php
	}
}
