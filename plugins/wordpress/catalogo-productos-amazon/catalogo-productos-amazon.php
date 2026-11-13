<?php
/**
 * Plugin Name: Catálogo de Productos Amazon
 * Description: Inventario administrativo de productos Amazon Afiliados y sus revisiones.
 * Version: 0.1.0
 * Author: MLC Design
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Text Domain: catalogo-productos-amazon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MLC_Amazon_Catalog {
	const POST_TYPE = 'mlc_amazon_product';
	const NONCE = 'mlc_amazon_product_meta';

	public function boot() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_product' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_value' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'review_notice' ) );
	}

	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name' => 'Productos Amazon',
					'singular_name' => 'Producto Amazon',
					'add_new_item' => 'Añadir producto Amazon',
					'edit_item' => 'Editar producto Amazon',
				),
				'public' => false,
				'show_ui' => true,
				'show_in_menu' => true,
				'menu_icon' => 'dashicons-products',
				'supports' => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap' => true,
			)
		);
	}

	public function add_meta_boxes() {
		add_meta_box( 'mlc-amazon-details', 'Datos de afiliación y revisión', array( $this, 'render_meta_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE );
		$values = array(
			'asin' => get_post_meta( $post->ID, '_mlc_amazon_asin', true ),
			'tag' => get_post_meta( $post->ID, '_mlc_amazon_tag', true ),
			'status' => get_post_meta( $post->ID, '_mlc_amazon_status', true ) ?: 'revisar',
			'last_review' => get_post_meta( $post->ID, '_mlc_amazon_last_review', true ),
			'locations' => get_post_meta( $post->ID, '_mlc_amazon_locations', true ),
		);
		?>
		<p><label for="mlc-amazon-asin"><strong>ASIN</strong></label><br><input id="mlc-amazon-asin" name="mlc_amazon_asin" type="text" value="<?php echo esc_attr( $values['asin'] ); ?>" class="regular-text" pattern="[A-Za-z0-9]{10}" placeholder="Ejemplo: B08BQVC4WS"></p>
		<p><label for="mlc-amazon-tag"><strong>Identificador de afiliado</strong></label><br><input id="mlc-amazon-tag" name="mlc_amazon_tag" type="text" value="<?php echo esc_attr( $values['tag'] ); ?>" class="regular-text" placeholder="Ejemplo: mlcdesign-21"></p>
		<p><label for="mlc-amazon-status"><strong>Estado</strong></label><br><select id="mlc-amazon-status" name="mlc_amazon_status"><option value="activo" <?php selected( $values['status'], 'activo' ); ?>>Activo</option><option value="revisar" <?php selected( $values['status'], 'revisar' ); ?>>Revisar</option><option value="sustituir" <?php selected( $values['status'], 'sustituir' ); ?>>Sustituir</option></select></p>
		<p><label for="mlc-amazon-last-review"><strong>Última revisión</strong></label><br><input id="mlc-amazon-last-review" name="mlc_amazon_last_review" type="date" value="<?php echo esc_attr( $values['last_review'] ); ?>"></p>
		<p><label for="mlc-amazon-locations"><strong>Páginas donde se utiliza</strong></label><br><textarea id="mlc-amazon-locations" name="mlc_amazon_locations" rows="4" class="large-text" placeholder="Una URL o ruta por línea"><?php echo esc_textarea( $values['locations'] ); ?></textarea></p>
		<?php
	}

	public function save_product( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$asin = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_POST['mlc_amazon_asin'] ?? '' ) ) ) );
		update_post_meta( $post_id, '_mlc_amazon_asin', $asin );
		update_post_meta( $post_id, '_mlc_amazon_tag', sanitize_text_field( wp_unslash( $_POST['mlc_amazon_tag'] ?? '' ) ) );
		update_post_meta( $post_id, '_mlc_amazon_status', in_array( $_POST['mlc_amazon_status'] ?? '', array( 'activo', 'revisar', 'sustituir' ), true ) ? sanitize_text_field( wp_unslash( $_POST['mlc_amazon_status'] ) ) : 'revisar' );
		update_post_meta( $post_id, '_mlc_amazon_last_review', preg_replace( '/[^0-9-]/', '', sanitize_text_field( wp_unslash( $_POST['mlc_amazon_last_review'] ?? '' ) ) ) );
		update_post_meta( $post_id, '_mlc_amazon_locations', sanitize_textarea_field( wp_unslash( $_POST['mlc_amazon_locations'] ?? '' ) ) );
	}

	public function columns( $columns ) {
		return array( 'cb' => $columns['cb'], 'title' => 'Producto', 'asin' => 'ASIN / ficha Amazon', 'status' => 'Estado', 'review' => 'Última revisión', 'locations' => 'Páginas' );
	}

	public function column_value( $column, $post_id ) {
		if ( 'asin' === $column ) {
			$asin = get_post_meta( $post_id, '_mlc_amazon_asin', true );
			$tag = get_post_meta( $post_id, '_mlc_amazon_tag', true );
			$url = add_query_arg( 'tag', $tag, 'https://www.amazon.es/dp/' . rawurlencode( $asin ) );
			echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $asin ) . ' ↗</a>';
		}
		if ( 'status' === $column ) echo esc_html( ucfirst( get_post_meta( $post_id, '_mlc_amazon_status', true ) ?: 'revisar' ) );
		if ( 'review' === $column ) echo esc_html( get_post_meta( $post_id, '_mlc_amazon_last_review', true ) ?: '—' );
		if ( 'locations' === $column ) echo nl2br( esc_html( get_post_meta( $post_id, '_mlc_amazon_locations', true ) ) );
	}

	public function review_notice() {
		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type || ! current_user_can( 'edit_posts' ) ) return;
		$items = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'numberposts' => -1 ) );
		$due = 0;
		foreach ( $items as $item ) {
			$date = get_post_meta( $item->ID, '_mlc_amazon_last_review', true );
			if ( ! $date || strtotime( $date ) < strtotime( '-30 days' ) ) $due++;
		}
		if ( $due ) echo '<div class="notice notice-warning"><p><strong>Productos Amazon:</strong> ' . esc_html( $due ) . ' producto(s) necesitan revisión. Abre su ficha, confirma que sigue vigente y actualiza la fecha.</p></div>';
	}
}

add_action( 'plugins_loaded', static function () { ( new MLC_Amazon_Catalog() )->boot(); } );
