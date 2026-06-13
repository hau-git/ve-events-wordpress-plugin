<?php
/**
 * Category taxonomy term meta: the category color field.
 *
 * @package VE_Events
 */

namespace VEV\Admin\TermMeta;

use VEV\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a color picker field to the category taxonomy term forms.
 */
final class CategoryTermMeta extends AbstractTermMeta {

	/**
	 * The category taxonomy.
	 */
	protected static function taxonomy(): string {
		return Constants::TAX_CATEGORY;
	}

	/**
	 * Render the color field on the "add category" form.
	 */
	public static function render_add_fields(): void {
		?>
		<div class="form-field">
			<label for="ve_category_color"><?php esc_html_e( 'Category Color', 've-events' ); ?></label>
			<input type="text" name="ve_category_color" id="ve_category_color" value="" class="vev-color-picker" />
			<p><?php esc_html_e( 'Choose a color for this category. Used in listings and calendar views.', 've-events' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the color field on the "edit category" form.
	 *
	 * @param \WP_Term $term The term being edited.
	 */
	public static function render_edit_fields( \WP_Term $term ): void {
		$color = (string) get_term_meta( $term->term_id, Constants::TERM_META_CATEGORY_COLOR, true );
		?>
		<tr class="form-field">
			<th scope="row"><label for="ve_category_color"><?php esc_html_e( 'Category Color', 've-events' ); ?></label></th>
			<td>
				<input type="text" name="ve_category_color" id="ve_category_color" value="<?php echo esc_attr( $color ); ?>" class="vev-color-picker" />
				<p class="description"><?php esc_html_e( 'Choose a color for this category. Used in listings and calendar views.', 've-events' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Persist the category color term meta.
	 *
	 * @param int $term_id The term ID being saved.
	 */
	public static function save_term_meta( int $term_id ): void {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		// Nonce is verified by WordPress core before the created_/edited_ term hooks fire.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$color = isset( $_POST['ve_category_color'] )
			? sanitize_hex_color( wp_unslash( $_POST['ve_category_color'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		update_term_meta( $term_id, Constants::TERM_META_CATEGORY_COLOR, $color ?? '' );
	}
}
