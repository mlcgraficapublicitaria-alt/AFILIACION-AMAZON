<?php
/**
 * Plugin Name: Cabina Ligera Core
 * Description: Estructura editorial y datos reutilizables de Cabina Ligera.
 * Version: 0.4.0
 */
defined('ABSPATH') || exit;

add_action('template_redirect', function (): void {
    $path = trailingslashit((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    if (($path === '/' || $path === '/aerolineas/') && isset($_GET['aerolinea'])) {
        $airline = sanitize_key(wp_unslash($_GET['aerolinea']));
        if (in_array($airline, ['ryanair', 'iberia', 'vueling', 'easyjet'], true)) {
            wp_safe_redirect(home_url('/aerolineas/' . $airline . '/'), 301);
            exit;
        }
    }

    if ($path === '/categorias/') {
        wp_safe_redirect(home_url('/productos/'), 301);
        exit;
    }
}, 1);

// Las fichas provisionales no deben reaparecer en ningún sitemap mientras sirven noindex.
add_filter('rank_math/sitemap/exclude_post_type', function (bool $exclude, string $post_type): bool {
    return $post_type === 'cl_resena' ? true : $exclude;
}, 10, 2);

add_filter('wp_sitemaps_post_types', function (array $post_types): array {
    unset($post_types['cl_resena']);
    return $post_types;
});

add_filter('rank_math/sitemap/post_type_archive_link', function ($archive_url, string $post_type) {
    return in_array($post_type, ['cl_comparativa', 'cl_guia', 'cl_aerolinea'], true) ? false : $archive_url;
}, 10, 2);

add_action('init', function (): void {
    $types = [
        'cl_comparativa' => ['Comparativas', 'Comparativa', 'comparativas'],
        'cl_resena'      => ['Reseñas', 'Reseña', 'resenas'],
        'cl_guia'        => ['Guías', 'Guía', 'guias'],
        'cl_aerolinea'   => ['Aerolíneas', 'Aerolínea', 'aerolineas'],
    ];
    foreach ($types as $type => [$plural, $singular, $slug]) {
        register_post_type($type, [
            'labels' => ['name' => $plural, 'singular_name' => $singular, 'add_new_item' => "Añadir $singular"],
            'public' => true,
            'show_in_rest' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => $slug, 'with_front' => false],
            'menu_icon' => 'dashicons-media-document',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields'],
        ]);
    }
    register_taxonomy('cl_categoria', array_keys($types), [
        'labels' => ['name' => 'Categorías', 'singular_name' => 'Categoría'],
        'public' => true,
        'show_in_rest' => true,
        'hierarchical' => true,
        'rewrite' => ['slug' => 'categorias'],
    ]);

    foreach (array_keys($types) as $type) {
        register_post_meta($type, 'cl_reviewed_at', [
            'type' => 'string', 'single' => true, 'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => fn() => current_user_can('edit_posts'),
        ]);
        register_post_meta($type, 'cl_source_url', [
            'type' => 'string', 'single' => true, 'show_in_rest' => true,
            'sanitize_callback' => 'esc_url_raw', 'auth_callback' => fn() => current_user_can('edit_posts'),
        ]);
        register_post_meta($type, 'cl_source_name', [
            'type' => 'string', 'single' => true, 'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => fn() => current_user_can('edit_posts'),
        ]);
        register_post_meta($type, 'cl_score', [
            'type' => 'number', 'single' => true, 'show_in_rest' => true,
            'sanitize_callback' => fn($value) => min(10, max(0, (float) $value)), 'auth_callback' => fn() => current_user_can('edit_posts'),
        ]);
        register_post_meta($type, 'cl_verdict', [
            'type' => 'string', 'single' => true, 'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_textarea_field', 'auth_callback' => fn() => current_user_can('edit_posts'),
        ]);
    }
    foreach (['cl_max_height', 'cl_max_width', 'cl_max_depth'] as $key) {
        register_post_meta('cl_aerolinea', $key, [
            'type' => 'number', 'single' => true, 'show_in_rest' => true,
            'sanitize_callback' => fn($value) => max(0, (float) $value),
            'auth_callback' => fn() => current_user_can('edit_posts'),
        ]);
    }
    foreach (['cl_filter_airlines', 'cl_filter_dimensions', 'cl_filter_durations', 'cl_filter_travelers', 'cl_filter_organization'] as $key) {
        register_post_meta('cl_resena', $key, [
            'type' => 'array', 'single' => true, 'show_in_rest' => true,
            'sanitize_callback' => static fn($values) => array_values(array_unique(array_filter(array_map('sanitize_key', (array) $values)))),
            'auth_callback' => fn() => current_user_can('edit_posts'),
            'show_in_rest' => ['schema' => ['type' => 'array', 'items' => ['type' => 'string']]],
        ]);
    }
});

function cl_product_filter_options(): array {
    return [
        'cl_filter_airlines' => ['Aerolíneas', ['ryanair'=>'Ryanair','vueling'=>'Vueling','iberia'=>'Iberia','easyjet'=>'easyJet','wizz-air'=>'Wizz Air','volotea'=>'Volotea']],
        'cl_filter_dimensions' => ['Medidas', ['40-30-20'=>'40 × 30 × 20 cm','40-25-20'=>'40 × 25 × 20 cm','45-36-20'=>'45 × 36 × 20 cm','55-40-20'=>'55 × 40 × 20 cm']],
        'cl_filter_durations' => ['Duración', ['1-2-dias'=>'1–2 días','3-5-dias'=>'3–5 días','una-semana'=>'Una semana','mas-de-una-semana'=>'Más de una semana']],
        'cl_filter_travelers' => ['Tipo de viajero', ['escapada-urbana'=>'Escapada urbana','viaje-trabajo'=>'Viaje de trabajo','familias'=>'Familias','minimalista'=>'Minimalista','nomada-digital'=>'Nómada digital','clima-frio'=>'Clima frío']],
        'cl_filter_organization' => ['Organización', ['bolsas-vacio'=>'Bolsas de vacío','cubos-compresion'=>'Cubos de compresión','organizadores-viaje'=>'Organizadores de viaje']],
    ];
}

add_action('add_meta_boxes_cl_resena', function (): void {
    add_meta_box('cl-product-filters', 'Atributos para los filtros de Productos', function (WP_Post $post): void {
        wp_nonce_field('cl_save_product_filters', 'cl_product_filters_nonce');
        echo '<p>Marca únicamente los atributos que describen realmente este producto. Estos valores controlan dónde aparece en /productos/.</p>';
        foreach (cl_product_filter_options() as $key => [$label, $options]) {
            $selected = (array) get_post_meta($post->ID, $key, true);
            echo '<fieldset style="margin:0 0 16px"><legend><strong>'.esc_html($label).'</strong></legend>';
            foreach ($options as $value => $text) {
                printf('<label style="display:inline-block;min-width:190px;margin:5px 12px 5px 0"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> %4$s</label>', esc_attr($key), esc_attr($value), checked(in_array($value, $selected, true), true, false), esc_html($text));
            }
            echo '</fieldset>';
        }
    }, 'cl_resena', 'normal', 'high');
});

add_action('save_post_cl_resena', function (int $post_id): void {
    if (!isset($_POST['cl_product_filters_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cl_product_filters_nonce'])), 'cl_save_product_filters') || !current_user_can('edit_post', $post_id) || wp_is_post_revision($post_id)) return;
    foreach (array_keys(cl_product_filter_options()) as $key) {
        $values = isset($_POST[$key]) ? array_values(array_unique(array_filter(array_map('sanitize_key', (array) wp_unslash($_POST[$key]))))) : [];
        update_post_meta($post_id, $key, $values);
    }
});

add_filter('rank_math/frontend/breadcrumb/items', function (array $crumbs): array {
    return $crumbs;
});

add_filter('rank_math/json_ld', function (array $data): array {
    if (is_singular(['cl_comparativa', 'cl_resena', 'cl_guia', 'cl_aerolinea'])) {
        $reviewed = get_post_meta(get_queried_object_id(), 'cl_reviewed_at', true);
        if ($reviewed && isset($data['Article'])) {
            $data['Article']['dateModified'] = gmdate('c', strtotime($reviewed));
        }
    }
    return $data;
}, 99);

add_action('init', function (): void {
    register_block_pattern_category('cabina-ligera', ['label' => 'Cabina Ligera']);
    register_block_pattern('cabina-ligera/aviso-afiliacion', [
        'title' => 'Aviso de afiliación', 'categories' => ['cabina-ligera'],
        'content' => '<!-- wp:group {"className":"cl-affiliate-box","layout":{"type":"constrained"}} --><div class="wp-block-group cl-affiliate-box"><!-- wp:paragraph --><p><strong>Transparencia:</strong> este contenido puede incluir enlaces de afiliado. Si compras a través de ellos, Cabina Ligera puede recibir una comisión sin coste adicional para ti.</p><!-- /wp:paragraph --></div><!-- /wp:group -->',
    ]);
    register_block_pattern('cabina-ligera/valoracion-editorial', [
        'title' => 'Valoración editorial', 'categories' => ['cabina-ligera'],
        'content' => '<!-- wp:group {"className":"cl-rating","layout":{"type":"constrained"}} --><div class="wp-block-group cl-rating"><!-- wp:paragraph {"className":"cl-eyebrow"} --><p class="cl-eyebrow">Valoración editorial</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Añade la conclusión principal</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Resume aquí la valoración basada en criterios comprobables.</p><!-- /wp:paragraph --></div><!-- /wp:group -->',
    ]);
    register_block_pattern('cabina-ligera/pros-contras', [
        'title' => 'Pros y contras', 'categories' => ['cabina-ligera'],
        'content' => '<!-- wp:columns {"className":"cl-pros-cons"} --><div class="wp-block-columns cl-pros-cons"><!-- wp:column {"className":"cl-pros"} --><div class="wp-block-column cl-pros"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Lo mejor</h3><!-- /wp:heading --><!-- wp:list --><ul class="wp-block-list"><li>Añade una ventaja verificada</li></ul><!-- /wp:list --></div><!-- /wp:column --><!-- wp:column {"className":"cl-cons"} --><div class="wp-block-column cl-cons"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">A tener en cuenta</h3><!-- /wp:heading --><!-- wp:list --><ul class="wp-block-list"><li>Añade una limitación relevante</li></ul><!-- /wp:list --></div><!-- /wp:column --></div><!-- /wp:columns -->',
    ]);
    register_block_pattern('cabina-ligera/ficha-producto', [
        'title' => 'Ficha editorial de producto', 'categories' => ['cabina-ligera'],
        'content' => '<!-- wp:group {"className":"cl-product-card","layout":{"type":"constrained"}} --><div class="wp-block-group cl-product-card"><!-- wp:paragraph {"className":"cl-eyebrow"} --><p class="cl-eyebrow">Selección editorial</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Nombre del producto</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Explica por qué se ha seleccionado y para qué tipo de viajero resulta adecuado.</p><!-- /wp:paragraph --><!-- wp:list {"className":"cl-product-facts"} --><ul class="wp-block-list cl-product-facts"><li><strong>Medidas:</strong> completar con datos verificados</li><li><strong>Peso:</strong> completar con datos verificados</li><li><strong>Ideal para:</strong> completar según el análisis</li></ul><!-- /wp:list --><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"className":"cl-affiliate-button"} --><div class="wp-block-button cl-affiliate-button"><a class="wp-block-button__link wp-element-button" href="#">Ver en Amazon <small>Enlace de afiliado</small></a></div><!-- /wp:button --></div><!-- /wp:buttons --></div><!-- /wp:group -->',
    ]);
    register_block_pattern('cabina-ligera/comparador-responsive', [
        'title' => 'Comparador responsive', 'categories' => ['cabina-ligera'],
        'content' => '<!-- wp:group {"className":"cl-comparison","layout":{"type":"constrained"}} --><div class="wp-block-group cl-comparison"><!-- wp:heading --><h2 class="wp-block-heading">Comparativa rápida</h2><!-- /wp:heading --><!-- wp:group {"className":"cl-comparison__row","layout":{"type":"grid","columnCount":4,"minimumColumnWidth":null}} --><div class="wp-block-group cl-comparison__row"><!-- wp:paragraph --><p><strong>Producto</strong></p><!-- /wp:paragraph --><!-- wp:paragraph --><p><strong>Medidas</strong></p><!-- /wp:paragraph --><!-- wp:paragraph --><p><strong>Valoración</strong></p><!-- /wp:paragraph --><!-- wp:paragraph --><p><strong>Enlace</strong></p><!-- /wp:paragraph --></div><!-- /wp:group --><p class="cl-editor-note">Duplica la fila y sustituye únicamente con información verificada.</p></div><!-- /wp:group -->',
    ]);
    register_block_pattern('cabina-ligera/preguntas-frecuentes', [
        'title' => 'Preguntas frecuentes', 'categories' => ['cabina-ligera'],
        'content' => '<!-- wp:heading --><h2 class="wp-block-heading">Preguntas frecuentes</h2><!-- /wp:heading --><!-- wp:details {"className":"cl-faq"} --><details class="wp-block-details cl-faq"><summary>Escribe aquí una pregunta real</summary><!-- wp:paragraph {"placeholder":"Escribe la respuesta verificada"} --><p></p><!-- /wp:paragraph --></details><!-- /wp:details -->',
    ]);
    register_block_pattern('cabina-ligera/criterios-analisis', [
        'title' => 'Criterios de análisis', 'categories' => ['cabina-ligera'],
        'content' => '<!-- wp:heading --><h2 class="wp-block-heading">Cómo lo hemos analizado</h2><!-- /wp:heading --><!-- wp:columns {"className":"cl-criteria"} --><div class="wp-block-columns cl-criteria"><!-- wp:column --><div class="wp-block-column"><h3>Medidas</h3><p>Describe el criterio y la fuente utilizada.</p></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><h3>Capacidad útil</h3><p>Explica cómo se ha valorado el espacio aprovechable.</p></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><h3>Comodidad</h3><p>Detalla los factores ergonómicos considerados.</p></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><h3>Calidad-precio</h3><p>Justifica la relación entre materiales y prestaciones.</p></div><!-- /wp:column --></div><!-- /wp:columns -->',
    ]);
});

add_filter('render_block', function (string $content, array $block): string {
    if (($block['blockName'] ?? '') !== 'core/button' || strpos($content, 'cl-affiliate-button') === false) return $content;
    return preg_replace('/<a\s/', '<a rel="sponsored nofollow noopener" target="_blank" ', $content, 1) ?: $content;
}, 10, 2);

add_action('add_meta_boxes_cl_aerolinea', function (): void {
    add_meta_box('cl-airline-data', 'Medidas verificadas', function (WP_Post $post): void {
        wp_nonce_field('cl_save_airline', 'cl_airline_nonce');
        $fields = [
            'cl_max_height' => 'Alto máximo (cm)', 'cl_max_width' => 'Ancho máximo (cm)', 'cl_max_depth' => 'Fondo máximo (cm)',
            'cl_source_name' => 'Nombre de la fuente oficial', 'cl_source_url' => 'URL de la fuente oficial', 'cl_reviewed_at' => 'Fecha de revisión (AAAA-MM-DD)',
        ];
        echo '<p>Solo completa y publica estos datos después de verificarlos en la fuente oficial.</p>';
        foreach ($fields as $key => $label) {
            $type = str_contains($key, 'max_') ? 'number' : ($key === 'cl_source_url' ? 'url' : ($key === 'cl_reviewed_at' ? 'date' : 'text'));
            $step = $type === 'number' ? ' step="0.1" min="0"' : '';
            printf('<p><label for="%1$s"><strong>%2$s</strong></label><br><input class="widefat" id="%1$s" name="%1$s" type="%3$s" value="%4$s"%5$s></p>', esc_attr($key), esc_html($label), esc_attr($type), esc_attr(get_post_meta($post->ID, $key, true)), $step);
        }
    }, 'cl_aerolinea', 'normal', 'high');
});

add_action('save_post_cl_aerolinea', function (int $post_id): void {
    if (!isset($_POST['cl_airline_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cl_airline_nonce'])), 'cl_save_airline') || !current_user_can('edit_post', $post_id) || wp_is_post_revision($post_id)) return;
    foreach (['cl_max_height', 'cl_max_width', 'cl_max_depth'] as $key) {
        if (isset($_POST[$key])) update_post_meta($post_id, $key, max(0, (float) $_POST[$key]));
    }
    if (isset($_POST['cl_source_name'])) update_post_meta($post_id, 'cl_source_name', sanitize_text_field(wp_unslash($_POST['cl_source_name'])));
    if (isset($_POST['cl_source_url'])) update_post_meta($post_id, 'cl_source_url', esc_url_raw(wp_unslash($_POST['cl_source_url'])));
    if (isset($_POST['cl_reviewed_at'])) update_post_meta($post_id, 'cl_reviewed_at', sanitize_text_field(wp_unslash($_POST['cl_reviewed_at'])));
});

add_shortcode('cabina_ligera_checker', function (): string {
    $airlines = get_posts(['post_type' => 'cl_aerolinea', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
    $verified = array_values(array_filter($airlines, fn($post) => get_post_meta($post->ID, 'cl_max_height', true) && get_post_meta($post->ID, 'cl_max_width', true) && get_post_meta($post->ID, 'cl_max_depth', true) && get_post_meta($post->ID, 'cl_source_url', true) && get_post_meta($post->ID, 'cl_reviewed_at', true)));
    ob_start(); ?>
    <div class="cl-checker" data-cabin-checker>
      <p class="cl-eyebrow">Comprobador de medidas</p><h2>¿Tu equipaje cabe en la aerolínea?</h2>
      <?php if (!$verified) : ?><p>La herramienta se activará cuando incorporemos medidas verificadas, su fuente oficial y la fecha de revisión.</p><label for="cl-airline">Aerolínea</label><select id="cl-airline" disabled><option>Datos pendientes de verificación</option></select>
      <?php else : ?>
        <p>Introduce las medidas exteriores de tu equipaje, incluyendo ruedas y asas.</p>
        <form><label for="cl-airline">Aerolínea</label><select id="cl-airline" required><option value="">Selecciona una aerolínea</option><?php foreach ($verified as $airline) : ?><option value="<?php echo esc_attr($airline->ID); ?>" data-dimensions="<?php echo esc_attr(implode(',', [get_post_meta($airline->ID, 'cl_max_height', true), get_post_meta($airline->ID, 'cl_max_width', true), get_post_meta($airline->ID, 'cl_max_depth', true)])); ?>" data-source="<?php echo esc_url(get_post_meta($airline->ID, 'cl_source_url', true)); ?>" data-reviewed="<?php echo esc_attr(get_post_meta($airline->ID, 'cl_reviewed_at', true)); ?>"><?php echo esc_html(get_the_title($airline)); ?></option><?php endforeach; ?></select><div class="cl-dimensions"><label>Alto (cm)<input name="height" type="number" min="1" step="0.1" required></label><label>Ancho (cm)<input name="width" type="number" min="1" step="0.1" required></label><label>Fondo (cm)<input name="depth" type="number" min="1" step="0.1" required></label></div><button class="cl-button" type="submit">Comprobar medidas</button></form>
      <?php endif; ?><div class="cl-checker__result" data-cabin-result aria-live="polite"></div>
    </div><?php return ob_get_clean();
});

add_shortcode('cabina_ligera_categories', function (): string {
    $terms = get_terms(['taxonomy' => 'cl_categoria', 'hide_empty' => false, 'orderby' => 'name']);
    if (is_wp_error($terms) || !$terms) return '<div class="cl-empty"><h2>Categorías en preparación</h2><p>La estructura está lista y se completará únicamente con contenidos editoriales revisados.</p></div>';
    ob_start(); ?><div class="cl-category-grid"><?php foreach ($terms as $term) :
        $count = (int) $term->count; $url = get_term_link($term); if (is_wp_error($url)) continue; ?>
        <article class="cl-category-card"><p class="cl-eyebrow"><?php echo esc_html($count ? sprintf(_n('%d contenido', '%d contenidos', $count, 'cabina-ligera'), $count) : 'Contenido en preparación'); ?></p><h2><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($term->name); ?></a></h2><?php if ($term->description) : ?><p><?php echo esc_html($term->description); ?></p><?php endif; ?><a class="cl-card-link" href="<?php echo esc_url($url); ?>">Explorar categoría <span aria-hidden="true">→</span></a></article>
    <?php endforeach; ?></div><?php return ob_get_clean();
});

add_shortcode('cabina_ligera_products', function (): string {
    $query = new WP_Query([
        'post_type' => 'cl_resena',
        'post_status' => 'publish',
        'posts_per_page' => 30,
        'orderby' => 'date',
        'order' => 'ASC',
        'meta_query' => [[
            'key' => 'cl_source_url',
            'value' => 'https://www.amazon.es/',
            'compare' => 'LIKE',
        ]],
    ]);
    if (!$query->have_posts()) return '<div class="cl-empty"><h2>Productos en preparación</h2></div>';
    ob_start(); ?>
    <div class="cl-catalog-grid">
      <?php while ($query->have_posts()) : $query->the_post();
        $amazon_url = get_post_meta(get_the_ID(), 'cl_source_url', true);
        $source_name = get_post_meta(get_the_ID(), 'cl_source_name', true);
        $status = 'Ficha en revisión editorial'; ?>
        <article class="cl-catalog-card">
          <?php if (has_post_thumbnail()) : ?><a class="cl-catalog-card__image" href="<?php the_permalink(); ?>"><?php the_post_thumbnail('medium_large', ['loading' => 'lazy']); ?></a><?php endif; ?>
          <div class="cl-catalog-card__body">
            <p class="cl-eyebrow"><?php echo esc_html($status); ?></p>
            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
            <?php the_excerpt(); ?>
            <div class="cl-catalog-card__actions">
              <a class="cl-card-link" href="<?php the_permalink(); ?>">Ver ficha <span aria-hidden="true">→</span></a>
              <a class="cl-button cl-button--commercial" href="<?php echo esc_url($amazon_url); ?>" rel="nofollow noopener" target="_blank">Ver en Amazon</a>
            </div>
          </div>
        </article>
      <?php endwhile; ?>
    </div>
    <?php wp_reset_postdata(); return ob_get_clean();
});

function cabina_ligera_post_cards(array $query_args, string $heading, string $archive_url, string $link_label): string {
    $query = new WP_Query($query_args + ['posts_per_page' => 3, 'no_found_rows' => true]);
    if (!$query->have_posts()) {
        $is_featured = ($query_args['post_type'] ?? '') === 'cl_comparativa';
        $cards = $is_featured ? [
            ['Bajo el asiento', 'Equipaje personal', 'Qué medidas, aperturas y organización resultan más prácticas para viajar sin facturar.', 'cl-preview-card--seat'],
            ['Organización', 'Sistemas de compresión', 'Cómo valorar el espacio que ahorran sin perder acceso ni añadir peso innecesario.', 'cl-preview-card--packing'],
            ['Escapadas', 'Viajes de 3 a 5 días', 'Criterios de capacidad, comodidad y distribución para viajes cortos.', 'cl-preview-card--escape'],
        ] : [
            ['Preparación', 'Cómo medir tu equipaje', 'Una guía para comprobar las dimensiones exteriores incluyendo asas, ruedas y bolsillos.', 'cl-preview-card--measure'],
            ['Criterio práctico', 'Qué llevar y qué dejar', 'Decisiones sencillas para reducir volumen según duración, clima y tipo de viaje.', 'cl-preview-card--essentials'],
            ['Antes de volar', 'Comprueba las condiciones', 'Dónde revisar la tarifa y la política oficial antes de llegar al aeropuerto.', 'cl-preview-card--airline'],
        ];
        ob_start(); ?><section class="cl-section cl-preview-section"><div class="ct-container"><div class="cl-section-heading"><div><p class="cl-eyebrow">Contenido en preparación</p><h2><?php echo esc_html($heading); ?></h2></div><a href="<?php echo esc_url($archive_url); ?>"><?php echo esc_html($link_label); ?> <span aria-hidden="true">→</span></a></div><div class="cl-grid"><?php foreach ($cards as [$eyebrow, $title, $description, $class]) : ?><article class="cl-card cl-preview-card"><div class="cl-preview-card__image <?php echo esc_attr($class); ?>" role="img" aria-label="Escena editorial sobre <?php echo esc_attr(strtolower($title)); ?>"></div><div class="cl-preview-card__body"><p class="cl-eyebrow"><?php echo esc_html($eyebrow); ?></p><h3><?php echo esc_html($title); ?></h3><p><?php echo esc_html($description); ?></p><span class="cl-preview-card__status">En preparación</span></div></article><?php endforeach; ?></div></div></section><?php return ob_get_clean();
    }
    ob_start(); ?><section class="cl-section"><div class="ct-container"><div class="cl-section-heading"><h2><?php echo esc_html($heading); ?></h2><a href="<?php echo esc_url($archive_url); ?>"><?php echo esc_html($link_label); ?> <span aria-hidden="true">→</span></a></div><div class="cl-grid"><?php while ($query->have_posts()) : $query->the_post(); ?><article class="cl-card cl-content-card"><?php if (has_post_thumbnail()) : ?><a class="cl-content-card__image" href="<?php the_permalink(); ?>"><?php the_post_thumbnail('large'); ?></a><?php endif; ?><p class="cl-eyebrow"><?php echo esc_html(get_post_type_object(get_post_type())->labels->singular_name); ?></p><h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3><?php the_excerpt(); ?><p class="cl-reviewed">Revisado: <?php echo esc_html(get_the_modified_date('j/m/Y')); ?></p></article><?php endwhile; ?></div></div></section><?php wp_reset_postdata(); return ob_get_clean();
}

add_shortcode('cabina_ligera_featured', fn() => cabina_ligera_post_cards(
    ['post_type' => 'cl_comparativa', 'meta_key' => 'cl_featured', 'meta_value' => '1'],
    'Comparativas destacadas', get_post_type_archive_link('cl_comparativa'), 'Ver todas las comparativas'
));

add_shortcode('cabina_ligera_latest', fn() => cabina_ligera_post_cards(
    ['post_type' => ['cl_comparativa', 'cl_resena', 'cl_guia'], 'orderby' => 'modified', 'order' => 'DESC'],
    'Últimos contenidos revisados', home_url('/guias/'), 'Explorar las guías'
));

add_action('wp_head', function (): void {
    echo "\n<!-- Aviso: los datos comerciales y de aerolíneas deben proceder de fuentes verificadas. -->\n";
});
