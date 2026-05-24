<?php
/**
 * Optic product add to cart form.
 *
 * @package WC_Optic_Product
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product->is_purchasable() ) {
	return;
}

echo wc_get_stock_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

if ( ! $product->is_in_stock() ) {
	return;
}

$division = $product->get_meta( '_optic_division', true );
if ( ! $division ) {
	echo '<p class="wc-optic-notice">' . esc_html__( 'This product is not ready for sale yet.', 'wc-optic' ) . '</p>';
	return;
}

$powers     = WC_Optic_Plugin::get_powers_for_division( $division );
$divisions  = WC_Optic_Plugin::get_divisions();
$div_label  = isset( $divisions[ $division ] ) ? $divisions[ $division ]['label'] : $division;
$qty_per_eye_default = 'yes' === $product->get_meta( '_optic_default_qty_per_eye', true );

do_action( 'woocommerce_before_add_to_cart_form' );
?>

<form class="cart wc-optic-cart-form" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype="multipart/form-data">
	<?php wp_nonce_field( 'wc_optic_add_to_cart', 'wc_optic_nonce' ); ?>

	<p class="wc-optic-division">
		<strong><?php esc_html_e( 'Optical division', 'wc-optic' ); ?>:</strong>
		<?php echo esc_html( $div_label ); ?>
	</p>

	<?php
	$unit_price = WC_Optic_Pricing::get_unit_price( $product );
	if ( '' !== (string) $product->get_price() ) :
		$initial_qty = $qty_per_eye_default ? 2 : 1;
		$line_total  = WC_Optic_Pricing::calculate_line_total( $unit_price, $initial_qty );
		?>
		<div class="wc-optic-pricing" data-unit-price="<?php echo esc_attr( (string) $unit_price ); ?>">
			<p class="wc-optic-unit-price">
				<strong><?php esc_html_e( 'Unit price', 'wc-optic' ); ?>:</strong>
				<?php echo wp_kses_post( wc_price( $unit_price ) ); ?>
			</p>
			<p class="wc-optic-line-total">
				<strong><?php esc_html_e( 'Estimated total', 'wc-optic' ); ?>:</strong>
				<span id="wc_optic_line_total_display"><?php echo wp_kses_post( wc_price( $line_total ) ); ?></span>
			</p>
		</div>
	<?php endif; ?>

	<fieldset class="wc-optic-fieldset">
		<legend class="screen-reader-text"><?php esc_html_e( 'Prescription', 'wc-optic' ); ?></legend>

		<p class="wc-optic-toggle">
			<label>
				<input type="checkbox" name="wc_optic_same_power" value="1" checked="checked" id="wc_optic_same_power" />
				<?php esc_html_e( 'Same power for both eyes', 'wc-optic' ); ?>
			</label>
		</p>

		<div class="wc-optic-eyes wc-optic-eyes--stack">
			<div class="wc-optic-eye wc-optic-eye--left" data-eye="left">
				<span class="wc-optic-eye-title wc-optic-title-both"><?php esc_html_e( 'Both eyes', 'wc-optic' ); ?></span>
				<span class="wc-optic-eye-title wc-optic-title-left" hidden><?php esc_html_e( 'Left eye (OS)', 'wc-optic' ); ?></span>
				<?php foreach ( $powers as $p ) : ?>
					<p class="form-row wc-optic-power">
						<label for="wc_optic_left_<?php echo esc_attr( $p ); ?>">
							<span class="wc-optic-ltr" dir="ltr"><?php echo esc_html( WC_Optic_Catalog::get_power_field_label( $p ) ); ?></span>
						</label>
						<input type="text" name="wc_optic_left_<?php echo esc_attr( $p ); ?>" id="wc_optic_left_<?php echo esc_attr( $p ); ?>" class="input-text" autocomplete="off" required />
					</p>
				<?php endforeach; ?>
			</div>

			<div class="wc-optic-eye wc-optic-eye--right wc-optic-eye--secondary" data-eye="right" hidden>
				<span class="wc-optic-eye-title"><?php esc_html_e( 'Right eye (OD)', 'wc-optic' ); ?></span>
				<?php foreach ( $powers as $p ) : ?>
					<p class="form-row wc-optic-power">
						<label for="wc_optic_right_<?php echo esc_attr( $p ); ?>">
							<span class="wc-optic-ltr" dir="ltr"><?php echo esc_html( WC_Optic_Catalog::get_power_field_label( $p ) ); ?></span>
						</label>
						<input type="text" name="wc_optic_right_<?php echo esc_attr( $p ); ?>" id="wc_optic_right_<?php echo esc_attr( $p ); ?>" class="input-text" autocomplete="off" />
					</p>
				<?php endforeach; ?>
			</div>
		</div>
	</fieldset>

	<p class="wc-optic-toggle">
		<label>
			<input type="checkbox" name="wc_optic_qty_per_eye" value="1" id="wc_optic_qty_per_eye" <?php checked( $qty_per_eye_default ); ?> />
			<?php esc_html_e( 'Quantity per eye', 'wc-optic' ); ?>
		</label>
	</p>

	<div class="wc-optic-qty wc-optic-qty--single" <?php echo $qty_per_eye_default ? 'hidden' : ''; ?>>
		<label for="wc_optic_qty"><?php esc_html_e( 'Quantity', 'wc-optic' ); ?></label>
		<input type="number" name="wc_optic_qty" id="wc_optic_qty" min="1" step="1" value="1" class="input-text qty text" />
	</div>

	<div class="wc-optic-qty wc-optic-qty--dual" <?php echo $qty_per_eye_default ? '' : 'hidden'; ?>>
		<p class="form-row">
			<label for="wc_optic_qty_left"><span class="wc-optic-ltr" dir="ltr"><?php esc_html_e( 'OS qty', 'wc-optic' ); ?></span></label>
			<input type="number" name="wc_optic_qty_left" id="wc_optic_qty_left" min="1" step="1" value="1" class="input-text qty text" />
		</p>
		<p class="form-row">
			<label for="wc_optic_qty_right"><span class="wc-optic-ltr" dir="ltr"><?php esc_html_e( 'OD qty', 'wc-optic' ); ?></span></label>
			<input type="number" name="wc_optic_qty_right" id="wc_optic_qty_right" min="1" step="1" value="1" class="input-text qty text" />
		</p>
	</div>

	<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

	<input type="hidden" name="quantity" id="wc_optic_line_quantity" value="<?php echo esc_attr( $qty_per_eye_default ? '2' : '1' ); ?>" />

	<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button button alt<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button>

	<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
</form>

<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
