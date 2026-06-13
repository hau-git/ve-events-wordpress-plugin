<?php
/**
 * Shared scaffolding for taxonomy term-meta admin fields.
 *
 * @package VE_Events
 */

namespace VEV\Admin\TermMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class that registers add/edit/save hooks for a taxonomy's term meta.
 *
 * Concrete subclasses declare their taxonomy and provide the add-form fields,
 * edit-form fields, and save logic.
 */
abstract class AbstractTermMeta {

	/**
	 * Register the add/edit/save term-meta hooks for the subclass taxonomy.
	 */
	public static function init(): void {
		$taxonomy = static::taxonomy();
		add_action( $taxonomy . '_add_form_fields', array( static::class, 'render_add_fields' ) );
		add_action( $taxonomy . '_edit_form_fields', array( static::class, 'render_edit_fields' ) );
		add_action( 'created_' . $taxonomy, array( static::class, 'save_term_meta' ) );
		add_action( 'edited_' . $taxonomy, array( static::class, 'save_term_meta' ) );
	}

	/**
	 * The taxonomy this term-meta handler is bound to.
	 */
	abstract protected static function taxonomy(): string;

	/**
	 * Render the fields on the "add term" form.
	 */
	abstract public static function render_add_fields(): void;

	/**
	 * Render the fields on the "edit term" form.
	 *
	 * @param \WP_Term $term The term being edited.
	 */
	abstract public static function render_edit_fields( \WP_Term $term ): void;

	/**
	 * Persist the submitted term meta.
	 *
	 * @param int $term_id The term ID being saved.
	 */
	abstract public static function save_term_meta( int $term_id ): void;
}
