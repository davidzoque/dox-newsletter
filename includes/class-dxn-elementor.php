<?php
/**
 * Widget de Elementor: el formulario de suscripción, eligiendo cuál.
 * Pinta lo mismo que el shortcode: el diseño se cambia en Newsletter > Formularios.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DXN_Elementor_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'dox_newsletter';
	}

	public function get_title() {
		return 'Dox Newsletter';
	}

	public function get_icon() {
		return 'eicon-mail';
	}

	public function get_categories() {
		return [ 'general' ];
	}

	public function get_keywords() {
		return [ 'newsletter', 'subscribe', 'email', 'boletín', 'suscripción', 'dox' ];
	}

	protected function register_controls() {
		$options = [];
		foreach ( DXN_Forms::all() as $slug => $f ) {
			$options[ $slug ] = $f['name'];
		}
		$this->start_controls_section( 'content', [ 'label' => __( 'Form', 'dox-newsletter' ) ] );
		$this->add_control( 'form', [
			'label'   => __( 'Form', 'dox-newsletter' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => $options,
			'default' => (string) array_key_first( $options ),
		] );
		$this->add_control( 'note', [
			'type' => \Elementor\Controls_Manager::RAW_HTML,
			'raw'  => esc_html__( 'Texts, colors and the list are changed in Dox Plugins > Newsletter > Forms.', 'dox-newsletter' ),
			'content_classes' => 'elementor-descriptor',
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo do_shortcode( '[dox_newsletter form="' . esc_attr( $s['form'] ?? '' ) . '"]' );
	}
}
