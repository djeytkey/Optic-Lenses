<?php
/**
 * Optic product add to cart form.
 *
 * @package WC_Optic_Product
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

$division = $product->get_meta( '_optic_division', true );
if ( ! $division ) {
	echo '<p class="wc-optic-notice">' . esc_html__( 'This product is not ready for sale yet.', 'wc-optic' ) . '</p>';
	return;
}

$powers     = WC_Optic_Plugin::get_powers_for_division( $division );
$divisions  = WC_Optic_Plugin::get_divisions();
$div_label  = isset( $divisions[ $division ] ) ? $divisions[ $division ]['label'] : $division;
$children   = WC_Optic_Frontend::get_storefront_child_configs( $product );
$buyable_children     = WC_Optic_SKU::get_purchasable_child_configs( $product );
$can_choose_different = count( $buyable_children ) > 1;

if ( ! WC_Optic_Frontend::has_child_options( $product ) ) {
	echo '<p class="wc-optic-notice">' . esc_html__( 'This product is not ready for sale yet. Please configure its internal products in the product admin.', 'wc-optic' ) . '</p>';
	return;
}

echo WC_Optic_Frontend::get_stock_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

if ( ! WC_Optic_Frontend::product_is_in_stock( $product ) ) {
	return;
}

do_action( 'woocommerce_before_add_to_cart_form' );
?>

<form class="cart wc-optic-cart-form" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype="multipart/form-data">
	<?php wp_nonce_field( 'wc_optic_add_to_cart', 'wc_optic_nonce' ); ?>

	<p class="wc-optic-division">
		<strong><?php esc_html_e( 'Optical division', 'wc-optic' ); ?>:</strong>
		<?php echo esc_html( $div_label ); ?>
	</p>

	<?php
	$initial_child      = ! empty( $buyable_children[0] ) ? $buyable_children[0] : ( ! empty( $children[0] ) ? $children[0] : array() );
	$initial_unit_price = ! empty( $initial_child ) ? WC_Optic_SKU::get_child_unit_price( $initial_child ) : 0;
	if ( $initial_unit_price > 0 ) :
		$initial_qty   = 1;
		$initial_total = WC_Optic_Pricing::calculate_line_total( $initial_unit_price, $initial_qty );
		?>
		<div class="wc-optic-pricing" data-unit-price="<?php echo esc_attr( (string) $initial_unit_price ); ?>">
			<p class="wc-optic-unit-price">
				<strong><?php esc_html_e( 'Selected price', 'wc-optic' ); ?>:</strong>
				<span id="wc_optic_unit_price_display"><?php echo wp_kses_post( wc_price( $initial_unit_price ) ); ?></span>
			</p>
			<p class="wc-optic-line-total">
				<strong><?php esc_html_e( 'Estimated total', 'wc-optic' ); ?>:</strong>
				<span id="wc_optic_line_total_display"><?php echo wp_kses_post( wc_price( $initial_total ) ); ?></span>
			</p>
		</div>
	<?php endif; ?>

	<div class="wc-optic-config-card">
		<?php if ( $can_choose_different ) : ?>
			<p class="wc-optic-toggle wc-optic-toggle--question">
				<label for="wc_optic_different_power">
					<input type="checkbox" name="wc_optic_different_power" value="1" id="wc_optic_different_power" />
					<strong><?php esc_html_e( 'Need 2 Different Powers?', 'wc-optic' ); ?></strong>
				</label>
			</p>
		<?php endif; ?>

		<div class="wc-optic-config-table">
			<div class="wc-optic-config-table__row">
				<div class="wc-optic-config-table__label">
					<strong><?php esc_html_e( 'Prescription', 'wc-optic' ); ?></strong>
				</div>
				<div class="wc-optic-config-table__values">
					<fieldset class="wc-optic-fieldset">
						<legend class="screen-reader-text"><?php esc_html_e( 'Internal product', 'wc-optic' ); ?></legend>
						<div class="wc-optic-eyes wc-optic-eyes--stack">
							<div class="wc-optic-eye wc-optic-eye--left" data-eye="left">
								<span class="wc-optic-eye-title wc-optic-title-both"><?php esc_html_e( 'Both eyes', 'wc-optic' ); ?></span>
								<span class="wc-optic-eye-title wc-optic-title-left" hidden><?php esc_html_e( 'Left eye (OS)', 'wc-optic' ); ?></span>
								<?php WC_Optic_Frontend::render_child_selector( $product, 'left', true ); ?>
							</div>

							<div class="wc-optic-eye wc-optic-eye--right wc-optic-eye--secondary" data-eye="right" hidden>
								<span class="wc-optic-eye-title"><?php esc_html_e( 'Right eye (OD)', 'wc-optic' ); ?></span>
								<?php WC_Optic_Frontend::render_child_selector( $product, 'right', false ); ?>
							</div>
						</div>
					</fieldset>
				</div>
			</div>

			<div class="wc-optic-config-table__row">
				<div class="wc-optic-config-table__label">
					<strong><?php esc_html_e( 'Quantity', 'wc-optic' ); ?></strong>
				</div>
				<div class="wc-optic-config-table__values">
					<div class="wc-optic-qty wc-optic-qty--single">
						<label for="wc_optic_qty" class="screen-reader-text"><?php esc_html_e( 'Quantity for both eyes', 'wc-optic' ); ?></label>
						<input type="number" name="wc_optic_qty" id="wc_optic_qty" min="1" step="1" value="1" class="input-text qty text" />
					</div>

					<div class="wc-optic-qty wc-optic-qty--dual" hidden>
						<p class="form-row">
							<label for="wc_optic_qty_left"><span class="wc-optic-ltr" dir="ltr"><?php esc_html_e( 'OS qty', 'wc-optic' ); ?></span></label>
							<input type="number" name="wc_optic_qty_left" id="wc_optic_qty_left" min="1" step="1" value="1" class="input-text qty text" />
						</p>
						<p class="form-row">
							<label for="wc_optic_qty_right"><span class="wc-optic-ltr" dir="ltr"><?php esc_html_e( 'OD qty', 'wc-optic' ); ?></span></label>
							<input type="number" name="wc_optic_qty_right" id="wc_optic_qty_right" min="1" step="1" value="1" class="input-text qty text" />
						</p>
					</div>
				</div>
			</div>
		</div>
	</div>

	<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

	<input type="hidden" name="quantity" id="wc_optic_line_quantity" value="1" />

	<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button button alt<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button>

	<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
</form>

<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
