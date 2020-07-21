<?php
namespace Elementor\Core\Kits;

use Elementor\Core\Kits\Controls\Repeater;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Plugin;
use Elementor\Core\Files\CSS\Post as Post_CSS;
use Elementor\Core\Files\CSS\Post_Preview as Post_Preview;
use Elementor\Core\Documents_Manager;
use Elementor\Core\Kits\Documents\Kit;
use Elementor\TemplateLibrary\Source_Local;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Manager {

	const OPTION_ACTIVE = 'elementor_active_kit';

	public function get_active_id() {
		$id = get_option( self::OPTION_ACTIVE );
		$kit_post = null;

		if ( $id ) {
			$kit_post = get_post( $id );
		}

		if ( ! $id || ! $kit_post || 'trash' === $kit_post->post_status ) {
			$id = $this->create_default();
			update_option( self::OPTION_ACTIVE, $id );
		}
		return $id;
	}

	public function get_active_kit() {
		$id = $this->get_active_id();

		return Plugin::$instance->documents->get( $id );
	}

	public function get_active_kit_for_frontend() {
		$id = $this->get_active_id();

		return Plugin::$instance->documents->get_doc_for_frontend( $id );
	}


	public function get_current_settings( $setting = null ) {
		$kit = $this->get_active_kit_for_frontend();

		if ( ! $kit ) {
			return '';
		}

		return $kit->get_settings( $setting );
	}

	private function create_default() {
		$kit = Plugin::$instance->documents->create( 'kit', [
			'post_type' => Source_Local::CPT,
			'post_title' => __( 'Default Kit', 'elementor' ),
			'post_status' => 'publish',
		] );

		return $kit->get_id();
	}

	/**
	 * @param Documents_Manager $documents_manager
	 */
	public function register_document( $documents_manager ) {
		$documents_manager->register_document_type( 'kit', Kit::get_class_full_name() );
	}

	public function localize_settings( $settings ) {
		$kit = $this->get_active_kit();

		$settings = array_replace_recursive( $settings, [
			'kit_id' => $kit->get_main_id(),
			'kit_config' => [
				'typography_prefix' => Global_Typography::TYPOGRAPHY_GROUP_PREFIX,
			],
			'user' => [
				'can_edit_kit' => $kit->is_editable_by_current_user(),
			],
			'i18n' => [
				'close' => __( 'Close', 'elementor' ),
				'back' => __( 'Back', 'elementor' ),
				'site_identity' => __( 'Site Identity', 'elementor' ),
				'lightbox' => __( 'Lightbox', 'elementor' ),
				'layout' => __( 'Layout', 'elementor' ),
				'theme_style' => __( 'Theme Style', 'elementor' ),
				'add_color' => __( 'Add Color', 'elementor' ),
				'add_style' => __( 'Add Style', 'elementor' ),
				'new_item' => __( 'New Item', 'elementor' ),
				'new_global' => __( 'New Global', 'elementor' ),
				'global_color' => __( 'Global Color', 'elementor' ),
				'global_typography' => __( 'Global Typography', 'elementor' ),
				'global_colors' => __( 'Global Colors', 'elementor' ),
				'invalid' => __( 'Invalid', 'elementor' ),
				'color_cannot_be_deleted' => __( 'System Colors can\'t be deleted', 'elementor' ),
				'typography_cannot_be_deleted' => __( 'System Typography can\'t be deleted', 'elementor' ),
				'design_system' => __( 'Design System', 'elementor' ),
				'buttons' => __( 'Buttons', 'elementor' ),
				'images' => __( 'Images', 'elementor' ),
				'form_fields' => __( 'Form Fields', 'elementor' ),
				'custom_css' => __( 'Custom CSS', 'elementor' ),
			],
		] );

		return $settings;
	}

	public function preview_enqueue_styles() {
		$kit = $this->get_kit_for_frontend();

		if ( $kit ) {
			// On preview, the global style is not enqueued.
			$this->frontend_before_enqueue_styles();

			Plugin::$instance->frontend->print_fonts_links();
		}
	}

	public function frontend_before_enqueue_styles() {
		$kit = $this->get_kit_for_frontend();

		if ( $kit ) {
			if ( $kit->is_autosave() ) {
				$css_file = Post_Preview::create( $kit->get_id() );
			} else {
				$css_file = Post_CSS::create( $kit->get_id() );
			}

			$css_file->enqueue();

			Plugin::$instance->frontend->add_body_class( 'elementor-kit-' . $kit->get_main_id() );
		}
	}

	public function render_panel_html() {
		require __DIR__ . '/views/panel.php';
	}

	public function get_kit_for_frontend() {
		$kit = false;
		$active_kit = $this->get_active_kit();
		$is_kit_preview = is_preview() && isset( $_GET['preview_id'] ) && $active_kit->get_main_id() === (int) $_GET['preview_id'];

		if ( $is_kit_preview ) {
			$kit = Plugin::$instance->documents->get_doc_or_auto_save( $active_kit->get_main_id(), get_current_user_id() );
		} elseif ( 'publish' === $active_kit->get_main_post()->post_status ) {
			$kit = $active_kit;
		}

		return $kit;
	}

	public function register_controls() {
		$controls_manager = Plugin::$instance->controls_manager;

		$controls_manager->register_control( Repeater::CONTROL_TYPE, new Repeater() );
	}

	public function is_custom_colors_enabled() {
		return ! get_option( 'elementor_disable_color_schemes' );
	}

	public function is_custom_typography_enabled() {
		return ! get_option( 'elementor_disable_typography_schemes' );
	}

	public function __construct() {
		add_action( 'elementor/documents/register', [ $this, 'register_document' ] );
		add_filter( 'elementor/editor/localize_settings', [ $this, 'localize_settings' ] );
		add_filter( 'elementor/editor/footer', [ $this, 'render_panel_html' ] );
		add_action( 'elementor/frontend/after_enqueue_global', [ $this, 'frontend_before_enqueue_styles' ], 0 );
		add_action( 'elementor/preview/enqueue_styles', [ $this, 'preview_enqueue_styles' ], 0 );
		add_action( 'elementor/controls/controls_registered', [ $this, 'register_controls' ] );

		/*
		 * Init kit controls in order to avoid init kit group control from within another group control.
		 * (After moving the `default_font` to the kit, the Typography group control cause initialize the kit controls at: https://github.com/elementor/elementor/blob/e6e1db9eddef7e3c1a5b2ba0c2338e2af2a3bfe3/includes/controls/groups/typography.php#L91
		 * and because the group control is a singleton, its args are changed to the last kit group control.)
		 */
		add_action( 'elementor/init', [ $this, 'get_active_kit_for_frontend' ] );
	}
}
