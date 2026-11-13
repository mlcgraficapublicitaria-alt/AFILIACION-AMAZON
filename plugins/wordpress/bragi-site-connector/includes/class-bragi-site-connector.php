<?php

defined( 'ABSPATH' ) || exit;

final class Bragi_Site_Connector {
  const OPTION = 'bragi_site_connector_settings';
  const CRON_HOOK = 'bragi_site_connector_sync';

  public static function activate() {
    if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
      wp_schedule_event( time() + 300, 'hourly', self::CRON_HOOK );
    }
  }

  public function register() {
    add_action( 'admin_menu', array( $this, 'admin_menu' ) );
    add_action( 'admin_post_bragi_site_connector_save', array( $this, 'save_settings' ) );
    add_action( 'admin_post_bragi_site_connector_sync_now', array( $this, 'sync_now' ) );
    add_action( self::CRON_HOOK, array( $this, 'synchronize' ) );
    add_action( 'wp_head', array( $this, 'print_verification_meta' ), 1 );
  }

  public function admin_menu() {
    add_options_page( 'BRAGI Site Connector', 'BRAGI Connector', 'manage_options', 'bragi-site-connector', array( $this, 'render_settings' ) );
  }

  private function settings() {
    $defaults = array( 'bragi_url' => '', 'project_id' => '', 'enrollment_token' => '', 'connector_id' => '', 'connector_secret' => '', 'last_status' => '', 'last_sync_at' => '', 'verification_metas' => array() );
    return wp_parse_args( get_option( self::OPTION, array() ), $defaults );
  }

  public function render_settings() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $settings = $this->settings();
    ?>
    <div class="wrap"><h1>BRAGI Site Connector</h1>
      <p>Vincula este WordPress con un único proyecto de BRAGI. Las credenciales de Google y DataForSEO permanecen en BRAGI.</p>
      <?php if ( ! empty( $_GET['bragi_notice'] ) ) : ?><div class="notice notice-<?php echo esc_attr( $_GET['bragi_notice'] === 'error' ? 'error' : 'success' ); ?>"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['message'] ?? '' ) ) ); ?></p></div><?php endif; ?>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'bragi_site_connector_save' ); ?><input type="hidden" name="action" value="bragi_site_connector_save" />
        <table class="form-table" role="presentation"><tbody>
          <tr><th><label for="bragi_url">URL de BRAGI</label></th><td><input class="regular-text code" id="bragi_url" name="bragi_url" type="url" value="<?php echo esc_attr( $settings['bragi_url'] ); ?>" placeholder="https://bragi.mlcdesign.es" required /></td></tr>
          <tr><th><label for="project_id">ID de proyecto</label></th><td><input class="regular-text code" id="project_id" name="project_id" value="<?php echo esc_attr( $settings['project_id'] ); ?>" required /></td></tr>
          <tr><th><label for="enrollment_token">Token de vinculación</label></th><td><input class="regular-text code" id="enrollment_token" name="enrollment_token" type="password" value="<?php echo esc_attr( $settings['enrollment_token'] ); ?>" autocomplete="new-password" /><p class="description">Solo se usa durante el alta. Después puede eliminarse del sitio.</p></td></tr>
        </tbody></table><?php submit_button( $settings['connector_id'] ? 'Guardar ajustes' : 'Vincular con BRAGI' ); ?>
      </form>
      <?php if ( $settings['connector_id'] ) : ?><hr /><p><strong>Estado:</strong> <?php echo esc_html( $settings['last_status'] ?: 'Conectado' ); ?><br /><strong>Última sincronización:</strong> <?php echo esc_html( $settings['last_sync_at'] ?: 'Pendiente' ); ?></p>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'bragi_site_connector_sync_now' ); ?><input type="hidden" name="action" value="bragi_site_connector_sync_now" /><?php submit_button( 'Sincronizar ahora', 'secondary', 'submit', false ); ?></form><?php endif; ?>
    </div><?php
  }

  public function save_settings() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado.' );
    check_admin_referer( 'bragi_site_connector_save' );
    $settings = $this->settings();
    $settings['bragi_url'] = untrailingslashit( esc_url_raw( wp_unslash( $_POST['bragi_url'] ?? '' ) ) );
    $settings['project_id'] = sanitize_text_field( wp_unslash( $_POST['project_id'] ?? '' ) );
    $token = trim( (string) wp_unslash( $_POST['enrollment_token'] ?? '' ) );
    if ( $token ) $settings['enrollment_token'] = $token;
    update_option( self::OPTION, $settings, false );
    if ( ! $settings['connector_id'] ) {
      $result = $this->enroll();
      $this->redirect( $result['ok'], $result['message'] );
    }
    $this->redirect( true, 'Ajustes guardados.' );
  }

  public function sync_now() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado.' );
    check_admin_referer( 'bragi_site_connector_sync_now' );
    $result = $this->synchronize(); $this->redirect( $result['ok'], $result['message'] );
  }

  private function redirect( $ok, $message ) {
    wp_safe_redirect( add_query_arg( array( 'page' => 'bragi-site-connector', 'bragi_notice' => $ok ? 'success' : 'error', 'message' => rawurlencode( $message ) ), admin_url( 'options-general.php' ) ) ); exit;
  }

  private function enroll() {
    $settings = $this->settings();
    if ( ! $settings['bragi_url'] || ! $settings['project_id'] || ! $settings['enrollment_token'] ) return array( 'ok' => false, 'message' => 'Faltan URL de BRAGI, ID de proyecto o token de vinculación.' );
    $response = wp_remote_post( $settings['bragi_url'] . '/api/site-connectors/register', array( 'timeout' => 20, 'headers' => array( 'Authorization' => 'Bearer ' . $settings['enrollment_token'], 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'clientId' => $settings['project_id'], 'siteUrl' => site_url( '/' ), 'homeUrl' => home_url( '/' ), 'wpVersion' => get_bloginfo( 'version' ), 'phpVersion' => PHP_VERSION ) ) ) );
    if ( is_wp_error( $response ) ) return array( 'ok' => false, 'message' => $response->get_error_message() );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( wp_remote_retrieve_response_code( $response ) >= 300 || empty( $body['ok'] ) || empty( $body['data']['connectorSecret'] ) ) return array( 'ok' => false, 'message' => $body['error']['message'] ?? 'BRAGI rechazó la vinculación.' );
    $settings['connector_id'] = $body['data']['connector']['id']; $settings['connector_secret'] = $body['data']['connectorSecret']; $settings['enrollment_token'] = ''; $settings['last_status'] = 'Conectado'; update_option( self::OPTION, $settings, false );
    return array( 'ok' => true, 'message' => 'WordPress se ha vinculado correctamente con BRAGI.' );
  }

  public function synchronize() {
    $settings = $this->settings();
    if ( ! $settings['connector_id'] || ! $settings['connector_secret'] || ! $settings['bragi_url'] ) return array( 'ok' => false, 'message' => 'El conector aún no está vinculado.' );
    $payload = $this->status_payload();
    $result = $this->signed_request( $settings, '/api/site-connectors/' . rawurlencode( $settings['connector_id'] ) . '/sync', 'POST', $payload );
    if ( ! $result['ok'] ) return $this->record_result( false, $result['message'] );
    $commands = $this->signed_request( $settings, '/api/site-connectors/' . rawurlencode( $settings['connector_id'] ) . '/commands', 'POST', array() );
    if ( $commands['ok'] && ! empty( $commands['data']['commands'] ) ) foreach ( $commands['data']['commands'] as $command ) $this->execute_command( $settings, $command );
    return $this->record_result( true, 'Sincronización completada.' );
  }

  private function status_payload() {
    $plugins = get_option( 'active_plugins', array() );
    return array( 'siteUrl' => site_url( '/' ), 'homeUrl' => home_url( '/' ), 'wpVersion' => get_bloginfo( 'version' ), 'phpVersion' => PHP_VERSION, 'sitemapUrl' => $this->discover_url( '/sitemap_index.xml' ), 'robotsUrl' => $this->discover_url( '/robots.txt' ), 'isHttps' => is_ssl() || stripos( home_url(), 'https://' ) === 0, 'isSearchVisible' => get_option( 'blog_public' ) === '1', 'activePlugins' => array_values( array_map( 'sanitize_text_field', $plugins ) ) );
  }

  private function discover_url( $path ) { $url = home_url( $path ); $response = wp_remote_head( $url, array( 'timeout' => 8 ) ); return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) < 400 ? $url : null; }

  private function signed_request( $settings, $path, $method, $payload ) {
    $body = wp_json_encode( $payload ); $timestamp = (string) round( microtime( true ) * 1000 ); $nonce = wp_generate_uuid4(); $signature = rtrim( strtr( base64_encode( hash_hmac( 'sha256', $timestamp . '.' . $nonce . '.' . $body, $settings['connector_secret'], true ) ), '+/', '-_' ), '=' );
    $response = wp_remote_request( $settings['bragi_url'] . $path, array( 'method' => $method, 'timeout' => 20, 'headers' => array( 'Content-Type' => 'application/json', 'X-BRAGI-Timestamp' => $timestamp, 'X-BRAGI-Nonce' => $nonce, 'X-BRAGI-Signature' => $signature ), 'body' => $body ) );
    if ( is_wp_error( $response ) ) return array( 'ok' => false, 'message' => $response->get_error_message() );
    $data = json_decode( wp_remote_retrieve_body( $response ), true ); return array( 'ok' => wp_remote_retrieve_response_code( $response ) < 300 && ! empty( $data['ok'] ), 'message' => $data['error']['message'] ?? 'BRAGI no pudo procesar la solicitud.', 'data' => $data['data'] ?? array() );
  }

  private function execute_command( $settings, $command ) {
    $ok = false; $result = array(); $error = '';
    if ( $command['action'] === 'SET_META_VERIFICATION' && ! empty( $command['payload']['name'] ) && ! empty( $command['payload']['content'] ) ) { $metas = $this->settings()['verification_metas']; $metas[ sanitize_key( $command['payload']['name'] ) ] = sanitize_text_field( $command['payload']['content'] ); $current = $this->settings(); $current['verification_metas'] = $metas; update_option( self::OPTION, $current, false ); $ok = true; $result = array( 'published' => true ); }
    elseif ( $command['action'] === 'REMOVE_META_VERIFICATION' && ! empty( $command['payload']['name'] ) ) { $current = $this->settings(); unset( $current['verification_metas'][ sanitize_key( $command['payload']['name'] ) ] ); update_option( self::OPTION, $current, false ); $ok = true; $result = array( 'removed' => true ); }
    else { $error = 'Acción no permitida por el conector.'; }
    $this->signed_request( $settings, '/api/site-connectors/' . rawurlencode( $settings['connector_id'] ) . '/commands', 'PATCH', array( 'commandId' => $command['id'], 'ok' => $ok, 'result' => $result, 'error' => $error ) );
  }

  public function print_verification_meta() { foreach ( $this->settings()['verification_metas'] as $name => $content ) { if ( $name && $content ) printf( "<meta name=\"%s\" content=\"%s\" />\n", esc_attr( $name ), esc_attr( $content ) ); } }
  private function record_result( $ok, $message ) { $settings = $this->settings(); $settings['last_status'] = $message; $settings['last_sync_at'] = current_time( 'mysql' ); update_option( self::OPTION, $settings, false ); return array( 'ok' => $ok, 'message' => $message ); }
}
