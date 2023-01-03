<?php
/*
Plugin Name: WP10 ACF Field Group Extra Options (ACF AddOn)
Plugin URI:
Description: Add Extra Options to ACF Field Group.
Version: 0.3
Author: PRESSMAN Hiroshi
Author URI:
Text Domain: wp10-acf-field-group-extra-options
License:
License URI:
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Wp10_Acf_Field_Group_Extra_Options class
 */
class Wp10_Acf_Field_Group_Extra_Options {

	/**
	 * Instance of this class. variable
	 *
	 * @var object
	 */
	protected static $_instance = null;

	/**
	 * This plugin's prefix.
	 *
	 * @var string
	 */
	private $prefix = 'wp10-acffg';

	/**
	 * Unique key of "My ID".
	 *
	 * @var string
	 */
	private $key_for_my_id = 'my_id';

	/**
	 * Unique key of "Additional Class".
	 *
	 * @var string
	 */
	private $key_for_additional_class = 'additional_class';

	/**
	 * Unique key of "Hide On Screen".
	 *
	 * @var string
	 */
	private $key_for_hide_one_screen = 'hide_on_screen';

	/**
	 * Temporary data storage.
	 *
	 * @var array
	 */
	private $stored_data = array();

	/**
	 * Get instance.
	 *
	 * @return Wp10_Acf_Field_Group_Extra_Options
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Add extra setting to field group edit screen.
		add_action( 'acf/render_field_group_settings', array( $this, 'add_settings' ), 99 );

		// Collect field group's informations.
		add_filter( 'acf/load_field_group', array( $this, 'store_data' ), 99 );

		// Echo CSS to hide field groups with hide_on_screen flag.
		add_action( 'admin_footer', array( $this, 'hide_on_screen' ), 10 );

		// Add classes to field group (as metabox)  with additional_class setting.
		add_action( 'acf/add_meta_boxes', array( $this, 'add_classes_to_field_group' ), 11, 3 );

		// Sanitize input text before saving.
		add_action( 'acf/update_field_group', [ $this, 'sanitize_input_text_before_saving' ] );
	}

	/**
	 * Sanitize input text before saving.
	 *
	 * @param  array $field_group ACF's field group setting.
	 * @return void
	 */
	public function sanitize_input_text_before_saving( array $field_group ):void {
		$key_list_2_be_sanitized = [ $this->key_for_my_id, $this->key_for_additional_class ];

		foreach ( $key_list_2_be_sanitized as $key ) {
			$_key                 = $this->get_option_key( $key );
			$field_group[ $_key ] = $this->sanitize_text( $field_group[ $_key ] );
		}

		remove_action( 'acf/update_field_group', [ $this, 'sanitize_input_text_before_saving' ] );
		acf_update_field_group( $field_group );// * @since   5.7.10
		add_action( 'acf/update_field_group', [ $this, 'sanitize_input_text_before_saving' ] );
	}

	/**
	 * Add extra setting to field group edit screen.
	 *
	 * @param  array $field_group ACF's field group setting.
	 * @return void
	 */
	public function add_settings( array $field_group ):void {
		// Add "My ID" setting.
		$key = $this->get_option_key( $this->key_for_my_id );
		acf_render_field_wrap(
			array(
				'label'        => 'My ID',
				'name'         => $key,
				'prefix'       => 'acf_field_group',
				'type'         => 'text',
				'instructions' => 'Use as a secondary ID. It will not appear in the HTML source.',
				'value'        => ( isset( $field_group[ $key ] ) ) ? $this->sanitize_text( $field_group[ $key ] ) : '',
				'required'     => false,
			)
		);

		// Add "Additional Class" setting.
		$key = $this->get_option_key( $this->key_for_additional_class );
		acf_render_field_wrap(
			array(
				'label'        => 'Additional Class',
				'name'         => $key,
				'prefix'       => 'acf_field_group',
				'type'         => 'text',
				'instructions' => 'Add classes to metabox. Multiple classes should be separated by spaces.',
				'value'        => ( isset( $field_group[ $key ] ) ) ? $this->sanitize_text( $field_group[ $key ] ) : '',
				'required'     => false,
			)
		);

		// Add "Hide On Screen" setting.
		$key = $this->get_option_key( $this->key_for_hide_one_screen );
		acf_render_field_wrap(
			array(
				'label'        => 'Hide On Screen',
				'name'         => $key,
				'prefix'       => 'acf_field_group',
				'type'         => 'true_false',
				'ui'           => 1,
				'instructions' => 'Hide this field group on screen.',
				'value'        => ( isset( $field_group[ $key ] ) ) ? $field_group[ $key ] : '',
				'required'     => false,
			)
		);
	}

	/**
	 * Collect field group's informations.
	 *
	 * @param  array $field_group ACF's field group setting.
	 * @return array
	 */
	public function store_data( $field_group ):array {

		// If hide_one_screen is true, store $field_group for later use.
		$key = $this->get_option_key( $this->key_for_hide_one_screen );
		if ( isset( $field_group[ $key ] ) ) {
			if ( 1 === (int) $field_group[ $key ] ) {
				$this->stored_data['hide_on_screen'][] = $field_group;
			}
		}

		return $field_group;
	}

	/**
	 * Echo CSS to hide field groups with hide_on_screen flag.
	 *
	 * @return void
	 */
	public function hide_on_screen() {
		// Any field group data stored?
		if ( ! isset( $this->stored_data['hide_on_screen'] ) || empty( $this->stored_data['hide_on_screen'] ) ) {
			return;
		}

		$field_groups = $this->stored_data['hide_on_screen'];
		$key          = $this->get_option_key( $this->key_for_hide_one_screen );

		// Echo css for hiding specific field groups.
		$sign = esc_html( __CLASS__ . '/' . __FUNCTION__ );
		echo "\n<!-- " . $sign . " -->\n";
		echo "<style>\n";
		foreach ( $field_groups as $field_group ) {
			if ( isset( $field_group['key'] ) ) {
				$field_group_key = esc_attr( $field_group['key'] );
				echo '#acf-' . $field_group_key . '{ display: none ! important; }' . "\n";
				echo '#screen-meta .metabox-prefs label[for=acf-' . $field_group_key . '-hide]{ display: none ! important; }' . "\n";
			}
		}
		echo "</style>\n";
		echo '<!-- /' . $sign . " -->\n\n";
	}

	/**
	 * Add classes to field group.
	 *
	 * @param  string  $post_type The post type.
	 * @param  WP_Post $post The post being edited.
	 * @param  array   $field_groups The field groups added.
	 * @return void
	 */
	public function add_classes_to_field_group( string $post_type, WP_Post $post, array $field_groups ):void {
		if ( empty( $field_groups ) ) {
			return;
		}

		foreach ( $field_groups as $field_group ) {
			$this->additional_class_data[ $field_group['key'] ] = $field_group;
			add_filter( "postbox_classes_{$post_type}_acf-{$field_group['key']}", array( $this, 'add_class_to_metabox' ), 99 );
		}
	}

	/**
	 * Add class to metabox.
	 *
	 * @param  array $classes Array of class.
	 * @return array
	 */
	public function add_class_to_metabox( array $classes ):array {
		$current_filter           = current_filter();
		$stored_data              = $this->additional_class_data;
		$key_for_additional_class = $this->get_option_key( $this->key_for_additional_class );
		$key_for_my_id            = $this->get_option_key( $this->key_for_my_id );

		if ( preg_match( '/^postbox_classes_(.+)_acf-(group_[0-9a-z]+)$/', $current_filter, $m ) ) {
			$post_type       = $m[1];
			$field_group_key = $m[2];

			if ( isset( $stored_data[ $field_group_key ] ) ) {

				$field_group = $stored_data[ $field_group_key ];

				// If 'Additional Class' is set, add them.
				if ( isset( $field_group[ $key_for_additional_class ] ) ) {
					$additional = explode( ' ', $field_group[ $key_for_additional_class ] );
					$classes    = array_filter( array_merge( $classes, $additional ) );
				}

				// If 'My ID' is set, add filter hook.
				if ( isset( $field_group[ $key_for_my_id ] ) ) {
					$my_id     = $field_group[ $key_for_my_id ];
					$hook_name = $this->prefix . '/add_class_to_metabox/?my_id=' . $my_id;
					/**
					 * Filter classes.
					 *
					 * @param array $classes Array of class.
					 * @param array $field_group ACF's field group setting.
					 */
					$classes = apply_filters( $hook_name, $classes, $field_group );
				}
			}
		}

		return $classes;
	}

	/**
	 * Get full length option key.
	 *
	 * @param  string $key Unique but partial key.
	 * @return string
	 */
	public function get_option_key( string $key ):string {
		return $this->prefix . '-' . $key;
	}

	/**
	 * Remove disallowed characters.
	 *
	 * @param  string $str target string.
	 * @return string
	 */
	public function sanitize_text( string $str ):string {
		$str = preg_replace( '/[^\w\-\s]+/', '', $str );
		return $str;
	}
}

Wp10_Acf_Field_Group_Extra_Options::instance();
