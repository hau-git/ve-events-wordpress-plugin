<?php
/**
 * Location taxonomy term meta: address and Google Maps URL fields.
 *
 * @package VE_Events
 */

namespace VEV\Admin\TermMeta;

use VEV\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds address + maps URL fields to the location taxonomy term forms.
 */
final class LocationTermMeta extends AbstractTermMeta {

	/**
	 * The location taxonomy.
	 */
	protected static function taxonomy(): string {
		return Constants::TAX_LOCATION;
	}

	/**
	 * Render the address + maps URL fields on the "add location" form.
	 */
	public static function render_add_fields(): void {
		?>
		<div class="form-field">
			<label for="ve_location_address"><?php esc_html_e( 'Address', 've-events' ); ?></label>
			<textarea name="ve_location_address" id="ve_location_address" rows="3"></textarea>
			<p><?php esc_html_e( 'Full address used for automatic Google Maps link generation.', 've-events' ); ?></p>
		</div>
		<div class="form-field">
			<label for="ve_location_maps_url"><?php esc_html_e( 'Custom Maps URL (optional)', 've-events' ); ?></label>
			<input type="url" name="ve_location_maps_url" id="ve_location_maps_url" value="" />
			<p><?php esc_html_e( 'Overrides the auto-generated Google Maps link. Leave empty to use the address above.', 've-events' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the address + maps URL fields on the "edit location" form.
	 *
	 * @param \WP_Term $term The term being edited.
	 */
	public static function render_edit_fields( \WP_Term $term ): void {
		$address  = (string) get_term_meta( $term->term_id, Constants::TERM_META_LOCATION_ADDRESS, true );
		$maps_url = (string) get_term_meta( $term->term_id, Constants::TERM_META_LOCATION_MAPS_URL, true );
		$auto_url = $address ? 'https://maps.google.com/?q=' . rawurlencode( $address ) : '';
		?>
		<tr class="form-field">
			<th scope="row"><label for="ve_location_address"><?php esc_html_e( 'Address', 've-events' ); ?></label></th>
			<td>
				<textarea name="ve_location_address" id="ve_location_address" rows="3"><?php echo esc_textarea( $address ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Full address — used to auto-generate a Google Maps link.', 've-events' ); ?></p>
			</td>
		</tr>
		<?php if ( $auto_url && ! $maps_url ) : ?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Maps URL (auto)', 've-events' ); ?></th>
			<td>
				<a href="<?php echo esc_url( $auto_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html( $auto_url ); ?>
				</a>
				<p class="description"><?php esc_html_e( 'Auto-generated from the address. Enter a custom URL below to override.', 've-events' ); ?></p>
			</td>
		</tr>
		<?php endif; ?>
		<tr class="form-field">
			<th scope="row"><label for="ve_location_maps_url"><?php esc_html_e( 'Custom Maps URL', 've-events' ); ?></label></th>
			<td>
				<input type="url" name="ve_location_maps_url" id="ve_location_maps_url" value="<?php echo esc_attr( $maps_url ); ?>" class="regular-text" />
				<p class="description"><?php esc_html_e( 'Optional. Overrides the auto-generated link above.', 've-events' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Persist the location address and maps URL term meta.
	 *
	 * @param int $term_id The term ID being saved.
	 */
	public static function save_term_meta( int $term_id ): void {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		// Nonce is verified by WordPress core before the created_/edited_ term hooks fire.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$address  = isset( $_POST['ve_location_address'] )
			? sanitize_textarea_field( wp_unslash( $_POST['ve_location_address'] ) )
			: '';
		$maps_url = isset( $_POST['ve_location_maps_url'] )
			? esc_url_raw( wp_unslash( $_POST['ve_location_maps_url'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		update_term_meta( $term_id, Constants::TERM_META_LOCATION_ADDRESS, $address );
		update_term_meta( $term_id, Constants::TERM_META_LOCATION_MAPS_URL, $maps_url );
	}
}
