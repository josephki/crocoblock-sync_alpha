<?php
/**
 * Admin-Einstellungen für das Crocoblock Sync Plugin
 * 
 * @version 1.0
 */

// Direktzugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Erstellt die Admin-Einstellungsseite und Funktionen
 */
class Crocoblock_Sync_Admin {
    /**
     * Konstruktor - initialisiert Hooks
     */
    public function __construct() {
        // Admin-Menü hinzufügen
        add_action('admin_menu', array($this, 'add_settings_page'));
        
        // Einstellungen registrieren
        add_action('admin_init', array($this, 'register_settings'));
        
        // Admin-Styles und Skripte laden
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX-Handler für die Feldnamen-Suche
        add_action('wp_ajax_ir_get_field_names', array($this, 'ajax_get_field_names'));
        
        // Debug-Hook - zeigt Debugging-Informationen
        add_action('admin_footer', array($this, 'display_debug_info'));
    }
    
    /**
     * Fügt Debug-Informationen in das Admin-Footer ein
     */
    public function display_debug_info() {
        $settings = get_option('ir_sync_general_settings', array());
        $debug_mode = isset($settings['debug_mode']) ? $settings['debug_mode'] : false;
        
        // Nur im Debug-Modus anzeigen
        if (!$debug_mode) {
            return;
        }
        
        // Nur auf der Plugin-Einstellungsseite anzeigen
        $screen = get_current_screen();
        if ($screen->id !== 'settings_page_ir-tours-sync-settings') {
            return;
        }
        
        // Debug-Panel vorbereiten
        ?>
        <div id="crocoblock-sync-debug-panel" style="
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 600px;
            height: 500px;
            max-height: 80vh;
            overflow: auto;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            font-family: monospace;
            font-size: 13px;
            z-index: 9999;
            box-shadow: 0 0 20px rgba(0,0,0,0.15);
            display: flex;
            flex-direction: column;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 10px;">
                <h2 style="margin: 0; font-size: 18px;">Crocoblock Sync Debug-Konsole</h2>
                <div>
                    <button id="toggle-debug-panel" class="button button-small" style="margin-left: 10px;">Minimieren</button>
                </div>
            </div>
            
            <div style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
                <!-- Tabs -->
                <div style="display: flex; border-bottom: 1px solid #ddd; margin-bottom: 10px;">
                    <button id="tab-console" class="debug-tab button button-small active" data-tab="console" style="margin-right: 5px; background: #fff; border-bottom: 0; border-bottom-left-radius: 0; border-bottom-right-radius: 0;">Konsole</button>
                    <button id="tab-info" class="debug-tab button button-small" data-tab="info" style="margin-right: 5px; background: #f0f0f1; border-bottom: 0; border-bottom-left-radius: 0; border-bottom-right-radius: 0;">System-Info</button>
                    <button id="tab-mappings" class="debug-tab button button-small" data-tab="mappings" style="margin-right: 5px; background: #f0f0f1; border-bottom: 0; border-bottom-left-radius: 0; border-bottom-right-radius: 0;">SQL</button>
                    <button id="tab-messages" class="debug-tab button button-small" data-tab="messages" style="background: #f0f0f1; border-bottom: 0; border-bottom-left-radius: 0; border-bottom-right-radius: 0;">Nachrichten</button>
                </div>
                
                <!-- Tab-Inhalte -->
                <div id="debug-tab-content" style="flex: 1; overflow: hidden; display: flex; flex-direction: column;">
                    <!-- Konsolen-Tab -->
                    <div id="tab-content-console" class="tab-content" style="display: flex; flex-direction: column; height: 100%;">
                        <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <label style="font-weight: bold;">Debug-Ausgabe:</label>
                            <button id="clear-debug-log" class="button button-small">Log leeren</button>
                        </div>
                        
                        <div id="crocoblock-sync-debug-log" style="
                            flex: 1;
                            overflow: auto;
                            background: #2c3338;
                            color: #a7aaad;
                            padding: 10px;
                            border-radius: 5px;
                            font-family: Consolas, Monaco, 'Courier New', monospace;
                            font-size: 13px;
                            line-height: 1.5;">
                            <div class="log-entries"></div>
                        </div>
                    </div>
                    
                    <!-- System-Info-Tab -->
                    <div id="tab-content-info" class="tab-content" style="display: none; height: 100%; overflow: auto;">
                        <table class="widefat" style="border: none; background: transparent;">
                            <tbody>
                                <tr>
                                    <th style="width: 30%;">Plugin-Version</th>
                                    <td><?php echo CROCOBLOCK_SYNC_VERSION; ?></td>
                                </tr>
                                <tr>
                                    <th>WordPress-Version</th>
                                    <td><?php echo get_bloginfo('version'); ?></td>
                                </tr>
                                <tr>
                                    <th>PHP-Version</th>
                                    <td><?php echo PHP_VERSION; ?></td>
                                </tr>
                                <tr>
                                    <th>JetEngine installiert</th>
                                    <td><?php echo class_exists('Jet_Engine') ? 'Ja' : 'Nein'; ?></td>
                                </tr>
                                <tr>
                                    <th>ACF installiert</th>
                                    <td><?php echo function_exists('acf_get_field_groups') ? 'Ja' : 'Nein'; ?></td>
                                </tr>
                                <tr>
                                    <th>Debug-Modus</th>
                                    <td>Aktiviert</td>
                                </tr>
                                <tr>
                                    <th>JavaScript-Umgebung</th>
                                    <td>
                                        <script>document.write(navigator.userAgent);</script>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Verfügbare Post-Typen</th>
                                    <td>
                                        <?php 
                                        $post_types = get_post_types(array('public' => true), 'objects');
                                        foreach ($post_types as $post_type) {
                                            echo esc_html($post_type->name) . ' (' . esc_html($post_type->label) . ')<br>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Mappings-Tab -->
                    <div id="tab-content-mappings" class="tab-content" style="display: none; height: 100%; overflow: auto;">
                        <pre style="margin: 0; background: #f0f0f1; padding: 10px; border-radius: 5px; overflow: auto;"><?php echo esc_html(print_r(get_option('ir_sync_field_mappings', array()), true)); ?></pre>
                    </div>
                    
                    <!-- Nachrichten-Tab -->
                    <div id="tab-content-messages" class="tab-content" style="display: none; height: 100%; overflow: auto;">
                        <pre style="margin: 0; background: #f0f0f1; padding: 10px; border-radius: 5px; overflow: auto;"><?php echo esc_html(print_r(get_option('ir_sync_messages', array()), true)); ?></pre>
                    </div>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                // Tab-Steuerung
                $('.debug-tab').on('click', function() {
                    const tabId = $(this).data('tab');
                    
                    // Tabs deaktivieren
                    $('.debug-tab').removeClass('active').css('background', '#f0f0f1');
                    $('.tab-content').hide();
                    
                    // Gewählten Tab aktivieren
                    $(this).addClass('active').css('background', '#fff');
                    $(`#tab-content-${tabId}`).show();
                });
                
                // Debug-Panel-Interaktionen
                var panelState = {
                    minimized: false,
                    originalHeight: $('#crocoblock-sync-debug-panel').height()
                };
                
                $('#toggle-debug-panel').on('click', function() {
                    const $panel = $('#crocoblock-sync-debug-panel');
                    
                    if (panelState.minimized) {
                        // Maximieren
                        $panel.animate({
                            height: panelState.originalHeight + 'px'
                        }, 300);
                        $(this).text('Minimieren');
                        panelState.minimized = false;
                    } else {
                        // Minimieren
                        panelState.originalHeight = $panel.height();
                        $panel.animate({
                            height: '40px'
                        }, 300);
                        $(this).text('Maximieren');
                        panelState.minimized = true;
                    }
                });
                
                // Log leeren
                $('#clear-debug-log').on('click', function() {
                    $('#crocoblock-sync-debug-log .log-entries').empty();
                });
                
                // Log-Nachrichten abfangen
                var originalConsoleLog = console.log;
                var originalConsoleError = console.error;
                var originalConsoleWarn = console.warn;
                
                // Konsolen-Methoden überschreiben
                console.log = function() {
                    // Original-Funktion aufrufen
                    originalConsoleLog.apply(console, arguments);
                    
                    // Nur Crocoblock-Sync-Meldungen anzeigen
                    var args = Array.from(arguments);
                    if (args[0] && typeof args[0] === 'string' && args[0].includes('Crocoblock Sync')) {
                        appendToDebugLog('log', args);
                    }
                };
                
                console.error = function() {
                    // Original-Funktion aufrufen
                    originalConsoleError.apply(console, arguments);
                    
                    // Nur Crocoblock-Sync-Meldungen anzeigen
                    var args = Array.from(arguments);
                    if (args[0] && typeof args[0] === 'string' && args[0].includes('Crocoblock Sync')) {
                        appendToDebugLog('error', args);
                    }
                };
                
                console.warn = function() {
                    // Original-Funktion aufrufen
                    originalConsoleWarn.apply(console, arguments);
                    
                    // Nur Crocoblock-Sync-Meldungen anzeigen
                    var args = Array.from(arguments);
                    if (args[0] && typeof args[0] === 'string' && args[0].includes('Crocoblock Sync')) {
                        appendToDebugLog('warn', args);
                    }
                };
                
                // Zum Log hinzufügen
                function appendToDebugLog(type, args) {
                    var $logEntries = $('#crocoblock-sync-debug-log .log-entries');
                    var color = type === 'error' ? '#f86368' : (type === 'warn' ? '#ffc107' : '#94E7A8');
                    
                    var timestamp = new Date().toLocaleTimeString();
                    var messageText = args.map(function(arg) {
                        if (typeof arg === 'object') {
                            try {
                                return JSON.stringify(arg, null, 2);
                            } catch (e) {
                                return '[Objekt]';
                            }
                        }
                        return String(arg);
                    }).join(' ');
                    
                    $logEntries.append(
                        '<div style="margin-bottom: 5px;">' +
                        '<span style="color: #8c8f94; margin-right: 5px;">[' + timestamp + ']</span>' +
                        '<span style="color: ' + color + ';">' + messageText + '</span>' +
                        '</div>'
                    );
                    
                    // Zum Ende scrollen
                    var $debugLog = $('#crocoblock-sync-debug-log');
                    $debugLog.scrollTop($debugLog[0].scrollHeight);
                }
            });
            </script>
        </div>
        <?php
    }
    
    /**
     * Admin-Skripte und Stile laden
     * 
     * @param string $hook Hook-Name der aktuellen Seite
     */
    public function enqueue_admin_scripts($hook) {
        if ('settings_page_ir-tours-sync-settings' !== $hook) {
            return;
        }
        
        // Debug-Modus-Einstellung abrufen
        $settings = get_option('ir_sync_general_settings', array());
        $debug_mode = isset($settings['debug_mode']) ? $settings['debug_mode'] : false;
        
        wp_enqueue_style(
            'crocoblock-sync-admin', 
            CROCOBLOCK_SYNC_URL . 'assets/css/admin.css', 
            array(), 
            CROCOBLOCK_SYNC_VERSION
        );
        
        wp_enqueue_script(
            'crocoblock-sync-select2',
            CROCOBLOCK_SYNC_URL . 'assets/js/select2.min.js',
            array('jquery'),
            '4.1.0',
            true
        );
        
        wp_enqueue_style(
            'crocoblock-sync-select2-css',
            CROCOBLOCK_SYNC_URL . 'assets/css/select2.min.css',
            array(),
            '4.1.0'
        );
        
        wp_enqueue_script(
            'crocoblock-sync-admin', 
            CROCOBLOCK_SYNC_URL . 'assets/js/admin.js', 
            array('jquery', 'jquery-ui-autocomplete', 'crocoblock-sync-select2'), 
            CROCOBLOCK_SYNC_VERSION, 
            true
        );
        
        // Übermittlung von Daten an JavaScript
        wp_localize_script('crocoblock-sync-admin', 'irSyncAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ir_sync_admin_nonce'),
            'debug_mode' => $debug_mode,
            'version' => CROCOBLOCK_SYNC_VERSION
        ));
    }
    
    /**
     * AJAX-Handler für die Suche nach Feldnamen
     */
    public function ajax_get_field_names() {
        // Debug-Modus-Einstellung abrufen
        $settings = get_option('ir_sync_general_settings', array());
        $debug_mode = isset($settings['debug_mode']) ? $settings['debug_mode'] : false;
        
        if ($debug_mode) {
            error_log('Crocoblock Sync - AJAX-Anfrage erhalten: ' . json_encode($_GET));
        }
        
        // Sicherheitsüberprüfung
        check_ajax_referer('ir_sync_admin_nonce', 'nonce');
        
        // Nur für Administratoren
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Nicht autorisiert');
            return;
        }
        
        // Parameter abrufen
        $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : '';
        $search_type = isset($_GET['search_type']) ? sanitize_text_field($_GET['search_type']) : 'meta_field';
        
        if (empty($post_type)) {
            wp_send_json_error('Kein Post-Typ angegeben');
            return;
        }
        
        // Ergebnisliste initialisieren
        $results = array();
        
        if ($debug_mode) {
            error_log('Crocoblock Sync - Suche nach ' . $search_type . ' für Post-Typ: ' . $post_type);
        }
        
        try {
            if ($search_type === 'meta_field') {
                // Metafelder für diesen Post-Typ suchen
                $results = $this->get_post_meta_keys($post_type);
                
                if ($debug_mode) {
                    error_log('Crocoblock Sync - Meta-Felder gefunden: ' . json_encode($results));
                }
            } elseif ($search_type === 'taxonomy') {
                // Taxonomien für diesen Post-Typ suchen
                $results = $this->get_post_taxonomies($post_type);
                
                if ($debug_mode) {
                    error_log('Crocoblock Sync - Taxonomien gefunden: ' . json_encode($results));
                }
            }
            
            // Sicherstellen, dass wir ein Array zurückgeben, selbst wenn ein Fehler auftritt
            if (!is_array($results)) {
                $results = array();
                if ($debug_mode) {
                    error_log('Crocoblock Sync - Ergebnisse waren kein Array, leeres Array zurückgegeben');
                }
            }
            
            // Format: [{id: "feldname", text: "feldname"}, ...]
            $formatted_results = array();
            foreach ($results as $result) {
                $formatted_results[] = array(
                    'id' => $result,
                    'text' => $result
                );
            }
            
            if ($debug_mode) {
                error_log(sprintf(
                    'Crocoblock Sync - %s für %s: %d Ergebnisse gefunden',
                    $search_type === 'meta_field' ? 'Meta-Felder' : 'Taxonomien',
                    $post_type,
                    count($formatted_results)
                ));
            }
            
            wp_send_json_success($formatted_results);
        } catch (Exception $e) {
            if ($debug_mode) {
                error_log('Crocoblock Sync - Fehler beim Laden der Feldnamen: ' . $e->getMessage());
            }
            wp_send_json_error('Fehler beim Laden der Feldnamen: ' . $e->getMessage());
        }
    }
    
 /**
 * Holt alle Meta-Keys für einen bestimmten Post-Typ mit verbesserter Methode
 * 
 * @param string $post_type Der Post-Typ
 * @return array Array mit Meta-Keys
 */
private function get_post_meta_keys($post_type) {
    global $wpdb;
    
    // Debug-Log aktivieren
    $settings = get_option('ir_sync_general_settings', array());
    $debug_mode = isset($settings['debug_mode']) ? $settings['debug_mode'] : false;
    
    if ($debug_mode) {
        error_log('Crocoblock Sync - Starte Meta-Key-Abfrage für ' . $post_type);
    }
    
    // Methode 1: Direkte Datenbankabfrage - alle Meta-Keys für diesen Post-Typ abrufen
    $query = $wpdb->prepare(
        "SELECT DISTINCT pm.meta_key 
        FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE p.post_type = %s
										   
        ORDER BY pm.meta_key",
        $post_type
    );
    
    $meta_keys = $wpdb->get_col($query);
    
    if ($debug_mode) {
        error_log('Crocoblock Sync - Meta-Keys aus DB: ' . count($meta_keys) . ' gefunden');
    }
    
    // Methode 2: Alle Posts des Typs abrufen und nach Meta-Feldern suchen
    $posts = get_posts(array(
        'post_type' => $post_type,
        'posts_per_page' => 10, // Begrenzung auf 10 Posts für bessere Performance
        'post_status' => 'any'
    ));
    
    foreach ($posts as $post) {
        $post_meta = get_post_meta($post->ID);
        foreach (array_keys($post_meta) as $meta_key) {
            if (!in_array($meta_key, $meta_keys)) {
                $meta_keys[] = $meta_key;
            }
        }
    }
    
    if ($debug_mode) {
        error_log('Crocoblock Sync - Meta-Keys nach Post-Abfrage: ' . count($meta_keys) . ' gefunden');
    }
    
    // JetEngine Meta-Boxen spezifisch abfragen
    if (class_exists('Jet_Engine')) {
        // Methode 3a: JetEngine-Meta-Boxen direkt über die API abrufen
        if (function_exists('jet_engine') && method_exists(jet_engine(), 'meta_boxes') && method_exists(jet_engine()->meta_boxes, 'get_meta_boxes')) {
            try {
                $meta_boxes = jet_engine()->meta_boxes->get_meta_boxes();
                
                if ($debug_mode) {
									  
																				   
                    error_log('Crocoblock Sync - JetEngine-Metaboxen: ' . (is_array($meta_boxes) ? count($meta_boxes) : 0) . ' gefunden');
					   
                }
                
                if (is_array($meta_boxes)) {
                    foreach ($meta_boxes as $meta_box) {
                        // Prüfen, ob die Meta-Box für diesen Post-Typ gilt
                        $applies_to_post_type = false;
                        
                        if (isset($meta_box['args']['object_type']) && $meta_box['args']['object_type'] === 'post') {
                            if (isset($meta_box['args']['post_type'])) {
                                if ($meta_box['args']['post_type'] === 'all' || 
                                    $meta_box['args']['post_type'] === $post_type || 
                                    (is_array($meta_box['args']['post_type']) && in_array($post_type, $meta_box['args']['post_type']))) {
                                    $applies_to_post_type = true;
                                }
                            }
                        }
                        
                        if ($applies_to_post_type && isset($meta_box['meta_fields']) && is_array($meta_box['meta_fields'])) {
                            foreach ($meta_box['meta_fields'] as $field) {
                                if (isset($field['name']) && !empty($field['name'])) {
                                    // Standard-Feld-Name hinzufügen
                                    if (!in_array($field['name'], $meta_keys)) {
                                        $meta_keys[] = $field['name'];
                                    }
                                    
                                    // JetEngine speichert Checkboxen im Format "meta[field]" und als Array "field[]"
                                    $meta_key_with_meta = $field['name'] . '_meta';
                                    if (!in_array($meta_key_with_meta, $meta_keys)) {
                                        $meta_keys[] = $meta_key_with_meta;
                                    }
                                    
                                    // Felder für Wiederholungen hinzufügen
                                    $meta_key_array = $field['name'] . '[]';
                                    if (!in_array($meta_key_array, $meta_keys)) {
                                        $meta_keys[] = $meta_key_array;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                if ($debug_mode) {
                    error_log('Crocoblock Sync - Fehler bei JetEngine-Metaboxen: ' . $e->getMessage());
                }
            }
        }
        
        // Methode 3b: JetEngine Meta-Boxen über die WP-Option abrufen
        $jet_post_types_option = get_option('jet_engine_meta_boxes', array());
        if (is_array($jet_post_types_option) && !empty($jet_post_types_option)) {
            foreach ($jet_post_types_option as $meta_box_data) {
                if (isset($meta_box_data['args']['object_type']) && $meta_box_data['args']['object_type'] === 'post') {
                    if (isset($meta_box_data['args']['post_type']) && 
                        ($meta_box_data['args']['post_type'] === $post_type || 
                         $meta_box_data['args']['post_type'] === 'all' || 
                         (is_array($meta_box_data['args']['post_type']) && in_array($post_type, $meta_box_data['args']['post_type'])))) {
                        
                        if (isset($meta_box_data['meta_fields']) && is_array($meta_box_data['meta_fields'])) {
                            foreach ($meta_box_data['meta_fields'] as $field) {
                                if (isset($field['name']) && !empty($field['name'])) {
                                    if (!in_array($field['name'], $meta_keys)) {
                                        $meta_keys[] = $field['name'];
										 
																							  
																					
										 
                                    }
                                    if (!in_array($field['name'] . '_meta', $meta_keys)) {
                                        $meta_keys[] = $field['name'] . '_meta';
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Methode 3c: JetEngine CPT-Metadaten überprüfen
        $jet_cpt_option = get_option('jet_engine_meta_boxes', array());
        if (!empty($jet_cpt_option)) {
            foreach ($jet_cpt_option as $cpt_data) {
                if (isset($cpt_data['meta_fields'])) {
                    foreach ($cpt_data['meta_fields'] as $field) {
                        if (!empty($field['name'])) {
                            $meta_keys[] = $field['name'];
                            $meta_keys[] = $field['name'] . '_meta';
                        }
                    }
                }
            }
        }
    }
    
    if ($debug_mode) {
        error_log('Crocoblock Sync - Meta-Keys nach JetEngine: ' . count($meta_keys) . ' gefunden');
    }
    
    // Methode 4: ACF-Felder hinzufügen (wenn ACF aktiv ist)
    if (function_exists('acf_get_field_groups')) {
        // Verwende die verbesserte ACF-Integration
        $meta_keys = $this->add_acf_fields($meta_keys, $post_type, $debug_mode);
    }
    
    // Methode 5: Benutzerdefinierte Meta-Felder einbeziehen
    $custom_fields_option = get_option('ir_sync_custom_meta_fields', array());
    
    // Standard-Werte für die Option, falls sie noch nicht existiert
    if (empty($custom_fields_option)) {
        $default_fields = array(
            'reisethemen_meta', 'reisethemen', 'kontinent', 'kontinent_meta', 
            'land', 'land_meta', 'reiseziel', 'reiseziel_meta', 'destination', 
            'destination_meta', 'theme', 'theme_meta', 'continent', 'continent_meta', 
            'country', 'country_meta', 'region', 'region_meta'
        );
        
																		 
										   
        update_option('ir_sync_custom_meta_fields', $default_fields);
        $custom_fields_option = $default_fields;
    }
    
    // Filter hinzufügen, damit andere Plugins die Liste erweitern können
			  
			
    $custom_fields = apply_filters('crocoblock_sync_custom_meta_fields', $custom_fields_option, $post_type);
    
    // Benutzerdefinierte Felder zur Liste hinzufügen
    foreach ($custom_fields as $field) {
        if (!in_array($field, $meta_keys)) {
            $meta_keys[] = $field;
        }
    }
    
    // Spezifische Felder für ir-tours
    if ($post_type === 'ir-tours') {
        $ir_tours_fields = array(
            'reisethemen', 'reisethemen_meta',
            'kontinent', 'kontinent_meta',
            'land', 'land_meta',
            'reiseziel', 'reiseziel_meta',
            'destination', 'destination_meta',
            'theme', 'theme_meta',
            'continent', 'continent_meta',
            'country', 'country_meta',
            'region', 'region_meta'
        );
        
														  
        foreach ($ir_tours_fields as $field) {
            if (!in_array($field, $meta_keys)) {
                $meta_keys[] = $field;
            }
        }
    }
    
    // Einmalige Filterung und Bereinigung
    $unique_meta_keys = array();
    foreach ($meta_keys as $key) {
        $key = trim($key);
        if (!empty($key) && !in_array($key, $unique_meta_keys)) {
																					
						   
            $unique_meta_keys[] = $key;
			   
        }
		
						  
    }
    
    // Liste sortieren
    sort($unique_meta_keys);
    
    if ($debug_mode) {
        error_log('Crocoblock Sync - Finale Meta-Key-Liste: ' . count($unique_meta_keys) . ' Felder gefunden');
    }
    
    return $unique_meta_keys;
}
    
    /**
     * ACF-Felder hinzufügen (verbesserte Integration für ACF und ACF Pro)
     * 
     * @param array $meta_keys Bestehende Meta-Keys Liste
     * @param string $post_type Post-Typ
     * @param bool $debug_mode Debug-Modus aktiviert?
     * @return array Erweiterte Meta-Keys Liste
     */
    private function add_acf_fields($meta_keys, $post_type, $debug_mode = false) {
        if (!function_exists('acf_get_field_groups')) {
            return $meta_keys;
        }
        
        // ACF-Feldgruppen für diesen Post-Typ abrufen
        $field_groups = acf_get_field_groups(array('post_type' => $post_type));
        
        if ($debug_mode) {
            error_log(sprintf(
                'Crocoblock Sync - ACF-Feldgruppen für %s: %d Gruppen gefunden',
                $post_type,
                count($field_groups)
            ));
        }
        
        foreach ($field_groups as $field_group) {
            $fields = acf_get_fields($field_group);
            
            if ($fields) {
                // Rekursive Funktion zum Hinzufügen aller Felder, einschließlich verschachtelter
                $this->add_acf_fields_recursive($fields, $meta_keys, '', $debug_mode);
            }
        }
        
        return $meta_keys;
    }
    
    /**
     * Fügt ACF-Felder rekursiv zur Meta-Fields-Liste hinzu
     * 
     * @param array $fields ACF-Felder
     * @param array &$meta_keys Meta-Keys Liste (Referenz)
     * @param string $prefix Präfix für verschachtelte Felder
     * @param bool $debug_mode Debug-Modus aktiviert?
     */
    private function add_acf_fields_recursive($fields, &$meta_keys, $prefix = '', $debug_mode = false) {
        if (!is_array($fields)) {
            return;
        }
        
        foreach ($fields as $field) {
            // Grundlegendes Feld hinzufügen
            $field_name = $prefix . $field['name'];
            
            if (!in_array($field_name, $meta_keys)) {
                $meta_keys[] = $field_name;
                
                // ACF speichert auch Metadaten mit Unterstrich-Präfix
                $meta_keys[] = '_' . $field_name;
            }
            
            // Bei bestimmten Feldtypen müssen wir auch untergeordnete Felder berücksichtigen
            if ($field['type'] === 'repeater' && isset($field['sub_fields']) && is_array($field['sub_fields'])) {
                // Für Repeater-Felder: füge grundlegende Struktur und untergeordnete Felder hinzu
                $this->add_acf_fields_recursive($field['sub_fields'], $meta_keys, $field_name . '_', $debug_mode);
                
                // Typische Meta-Schlüssel für Repeater
                $meta_keys[] = $field_name . '_0';
                $meta_keys[] = $field_name . '_1';
            } 
            else if ($field['type'] === 'group' && isset($field['sub_fields']) && is_array($field['sub_fields'])) {
                // Für Group-Felder: füge untergeordnete Felder mit Präfix hinzu
                $this->add_acf_fields_recursive($field['sub_fields'], $meta_keys, $field_name . '_', $debug_mode);
            }
            else if ($field['type'] === 'flexible_content' && isset($field['layouts']) && is_array($field['layouts'])) {
                // Für Flexible Content: füge Layouts und deren Felder hinzu
                foreach ($field['layouts'] as $layout) {
                    if (isset($layout['sub_fields']) && is_array($layout['sub_fields'])) {
                        // Layout-spezifische Felder
                        $this->add_acf_fields_recursive(
                            $layout['sub_fields'], 
                            $meta_keys, 
                            $field_name . '_' . $layout['name'] . '_', 
                            $debug_mode
                        );
                    }
                }
                
                // Typische Meta-Schlüssel für Flexible Content
                $meta_keys[] = $field_name . '_0_layout';
                $meta_keys[] = $field_name . '_1_layout';
            }
        }
    }
    
    /**
     * Holt alle verfügbaren Taxonomien für einen bestimmten Post-Typ
     */
    private function get_post_taxonomies($post_type) {
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        $tax_list = array();
        
        foreach ($taxonomies as $taxonomy) {
            $tax_list[] = $taxonomy->name;
        }
        
        // Debug-Log
        $settings = get_option('ir_sync_general_settings', array());
        $debug_mode = isset($settings['debug_mode']) ? $settings['debug_mode'] : false;
        
        if ($debug_mode) {
            error_log(sprintf(
                'Crocoblock Sync - Taxonomien für %s: %d Taxonomien gefunden',
                $post_type,
                count($tax_list)
            ));
        }
        
        // Häufig genutzte Taxonomie-Namen hinzufügen
        $common_taxonomies = get_option('ir_sync_custom_taxonomies', array());
        
        // Standard-Werte für die Option, falls sie noch nicht existiert
        if (empty($common_taxonomies)) {
            $default_taxonomies = array(
                'reisethemen', 'kontinent_taxon', 'land_taxon', 'reiseziel_taxon',
                'destination_taxon', 'theme_taxon', 'continent_taxon', 'country_taxon', 'region_taxon'
            );
            
            update_option('ir_sync_custom_taxonomies', $default_taxonomies);
            $common_taxonomies = $default_taxonomies;
        }
        
        // Filter hinzufügen, damit andere Plugins die Liste erweitern können
        $common_taxonomies = apply_filters('crocoblock_sync_custom_taxonomies', $common_taxonomies, $post_type);
        
        foreach ($common_taxonomies as $tax) {
            if (!in_array($tax, $tax_list) && taxonomy_exists($tax)) {
                $tax_list[] = $tax;
            }
        }
        
        // Liste sortieren und zurückgeben
        sort($tax_list);
        
        return $tax_list;
    }
    
    /**
     * Fügt die Einstellungsseite zum Admin-Menü hinzu
     */
    public function add_settings_page() {
        add_options_page(
            'Crocoblock Sync Einstellungen', // Seitentitel
            'Crocoblock Sync',               // Menütitel
            'manage_options',              // Erforderliche Berechtigung
            'ir-tours-sync-settings',      // Menü-Slug
            array($this, 'render_settings_page') // Callback-Funktion
        );
    }
    
    /**
     * Registriert die Plugin-Einstellungen
     */
    public function register_settings() {
        // Einstellungs-Gruppe registrieren
        register_setting(
            'ir_sync_settings_group',
            'ir_sync_field_mappings',
            array($this, 'sanitize_field_mappings')
        );
        
        register_setting(
            'ir_sync_settings_group',
            'ir_sync_messages',
            array($this, 'sanitize_messages')
        );
        
        // Einstellungs-Gruppe registrieren für allgemeine Einstellungen
        register_setting(
            'ir_sync_settings_group',
            'ir_sync_general_settings',
            array($this, 'sanitize_general_settings')
        );
        
        // Neue Einstellungsgruppe für benutzerdefinierte Meta-Felder
        register_setting(
            'ir_sync_settings_group',
            'ir_sync_custom_meta_fields',
            array($this, 'sanitize_custom_fields')
        );
        
        // Neue Einstellungsgruppe für benutzerdefinierte Taxonomien
        register_setting(
            'ir_sync_settings_group',
            'ir_sync_custom_taxonomies',
            array($this, 'sanitize_custom_fields')
        );
        
        // Abschnitt für allgemeine Einstellungen
        add_settings_section(
            'ir_sync_general_section',
            'Allgemeine Einstellungen',
            array($this, 'render_general_section'),
            'ir-tours-sync-settings'
        );
        
        // Abschnitt für Feld-Mappings hinzufügen
        add_settings_section(
            'ir_sync_mappings_section',
            'Feld-Zuordnungen',
            array($this, 'render_mappings_section'),
            'ir-tours-sync-settings'
        );
        
        // Abschnitt für benutzerdefinierte Nachrichten hinzufügen
        add_settings_section(
            'ir_sync_messages_section',
            'Benutzerdefinierte Nachrichten',
            array($this, 'render_messages_section'),
            'ir-tours-sync-settings'
        );
        
        // Abschnitt für benutzerdefinierte Meta-Felder hinzufügen
        add_settings_section(
            'ir_sync_custom_fields_section',
            'Benutzerdefinierte Meta-Felder',
            array($this, 'render_custom_fields_section'),
            'ir-tours-sync-settings'
        );
        
        // Abschnitt für benutzerdefinierte Taxonomien hinzufügen
        add_settings_section(
            'ir_sync_custom_taxonomies_section',
            'Benutzerdefinierte Taxonomien',
            array($this, 'render_custom_taxonomies_section'),
            'ir-tours-sync-settings'
        );
        
        // Allgemeine Einstellungsfelder
        add_settings_field(
            'ir_sync_debug_mode',
            'Debug-Modus aktivieren',
            array($this, 'render_debug_field'),
            'ir-tours-sync-settings',
            'ir_sync_general_section'
        );
        
        // Nachrichtenfelder hinzufügen
        $message_fields = array(
            'multiple_themes' => 'Warnung bei mehreren Themen',
            'sync_button' => 'Text für Sync-Button',
            'sync_reminder' => 'Erinnerung zum Synchronisieren',
            'sync_success' => 'Erfolgreiche Synchronisation (%d für Anzahl)',
            'sync_error' => 'Fehlermeldung bei Synchronisation',
            'terms_created' => 'Neue Terms erstellt (%s für Liste der Terms)'
        );
        
        foreach ($message_fields as $key => $label) {
            add_settings_field(
                'ir_sync_message_' . $key,
                $label,
                array($this, 'render_message_field'),
                'ir-tours-sync-settings',
                'ir_sync_messages_section',
                array('message_key' => $key)
            );
        }
        
        // Feld für benutzerdefinierte Meta-Felder hinzufügen
        add_settings_field(
            'ir_sync_custom_meta_fields',
            'Meta-Felder Liste',
            array($this, 'render_custom_fields_field'),
            'ir-tours-sync-settings',
            'ir_sync_custom_fields_section'
        );
        
        // Feld für benutzerdefinierte Taxonomien hinzufügen
        add_settings_field(
            'ir_sync_custom_taxonomies',
            'Taxonomien Liste',
            array($this, 'render_custom_taxonomies_field'),
            'ir-tours-sync-settings',
            'ir_sync_custom_taxonomies_section'
        );
    }
    
    /**
     * Render-Funktion für allgemeine Einstellungen
     */
    public function render_general_section() {
        echo '<p>Grundlegende Einstellungen für das Plugin.</p>';
    }
    
    /**
     * Render-Funktion für Debug-Modus Checkbox
     */
    public function render_debug_field() {
        $settings = get_option('ir_sync_general_settings', array());
        $debug_mode = isset($settings['debug_mode']) ? $settings['debug_mode'] : false;
        
        echo '<label><input type="checkbox" name="ir_sync_general_settings[debug_mode]" value="1" ' 
            . checked(true, $debug_mode, false) . '/> Fehlerinformationen im Browser-Konsolenfenster anzeigen</label>';
        echo '<p class="description">Aktiviere diese Option, um detaillierte Informationen bei der Fehlersuche zu erhalten. Es wird auch ein Debug-Panel am unteren Bildschirmrand angezeigt.</p>';
    }
    
    /**
     * Sanitize-Funktion für allgemeine Einstellungen
     */
    public function sanitize_general_settings($input) {
        $sanitized = array();
        $sanitized['debug_mode'] = isset($input['debug_mode']) ? (bool) $input['debug_mode'] : false;
        return $sanitized;
    }
    
    /**
     * Render-Funktion für den Mapping-Abschnitt
     */
    public function render_mappings_section() {
        echo '<p>Definieren Sie hier, welche Meta-Felder mit welchen Taxonomien synchronisiert werden sollen.</p>';
        
        // Bestehende Mappings abrufen
        $mappings = get_option('ir_sync_field_mappings', array());
        
        // Standard-Mappings, falls keine vorhanden sind
        if (empty($mappings)) {
            $mappings = array(
                1 => array(
                    'meta_field' => 'reisethemen_meta',
                    'taxonomy' => 'reisethemen',
                    'post_type' => 'ir-tours',
                    'active' => true,
                    'allow_multiple' => false // Neue Option
                )
            );
            
            // Prüfen, ob kontinent_taxon existiert
            if (taxonomy_exists('kontinent_taxon')) {
                $mappings[2] = array(
                    'meta_field' => 'kontinent',
                    'taxonomy' => 'kontinent_taxon',
                    'post_type' => 'ir-tours',
                    'active' => true,
                    'allow_multiple' => false // Neue Option
                );
            }
        }
        
        // Aufbau der Tabelle
        echo '<table class="widefat ir-sync-mappings-table" id="ir-sync-mappings-table">';
        echo '<thead>
                <tr>
                    <th width="5%">Aktiv</th>
                    <th width="15%">Post-Typ</th>
                    <th width="20%">Meta-Feld</th>
                    <th	width="20%">Taxonomie</th>
                    <th width="15%">Mehrere erlauben</th>
                    <th width="25%">Aktionen</th>
                </tr>
              </thead>';
        echo '<tbody>';
        
        foreach ($mappings as $id => $mapping) {
            $this->render_mapping_row($id, $mapping);
        }
        
        echo '</tbody>';
        echo '</table>';
        
        echo '<p><button type="button" class="button add-mapping" id="add-mapping">Neues Mapping hinzufügen</button></p>';
        
        // Template für neue Zeilen
        echo '<script type="text/template" id="mapping-row-template">';
        $this->render_mapping_row('{{id}}', array(
            'post_type' => 'ir-tours',
            'meta_field' => '',
            'taxonomy' => '',
            'active' => true,
            'allow_multiple' => false // Neue Option
        ));
        echo '</script>';
        
        // Post-Typen für JavaScript-Auswahl
        $post_types = get_post_types(array('public' => true), 'objects');
        echo '<script type="text/javascript">
            var irSyncPostTypes = {';
        
        $post_type_array = array();
        foreach ($post_types as $post_type) {
            $post_type_array[] = '"' . esc_js($post_type->name) . '": "' . esc_js($post_type->label) . '"';
        }
        
        echo implode(', ', $post_type_array);
        echo '};
        </script>';
    }
    
    /**
     * Rendert eine einzelne Mapping-Zeile
     */
    private function render_mapping_row($id, $mapping) {
        // Sicherstellen, dass alle Felder existieren (für ältere Installationen)
        if (!isset($mapping['allow_multiple'])) {
            $mapping['allow_multiple'] = false;
        }
        
        // Verfügbare Post-Typen abrufen
        $post_types = get_post_types(array('public' => true), 'objects');
        
        echo '<tr class="mapping-row">';
        
        // Aktiv-Checkbox
        echo '<td>';
        echo '<input type="checkbox" name="ir_sync_field_mappings[' . esc_attr($id) . '][active]" value="1" ' . checked(true, isset($mapping['active']) ? $mapping['active'] : true, false) . '/>';
        echo '</td>';
        
        // Post-Typ als Dropdown
        echo '<td>';
        echo '<select name="ir_sync_field_mappings[' . esc_attr($id) . '][post_type]" class="post-type-select" required>';
        
        foreach ($post_types as $post_type) {
            echo '<option value="' . esc_attr($post_type->name) . '" ' . selected($mapping['post_type'], $post_type->name, false) . '>' . esc_html($post_type->label) . '</option>';
        }
        
        echo '</select>';
        echo '</td>';
        
        // Meta-Feld als Select2 Dropdown
        echo '<td>';
        echo '<select name="ir_sync_field_mappings[' . esc_attr($id) . '][meta_field]" class="meta-field-select" style="width: 100%" data-placeholder="Meta-Feld auswählen">';
        if (!empty($mapping['meta_field'])) {
            echo '<option value="' . esc_attr($mapping['meta_field']) . '" selected>' . esc_html($mapping['meta_field']) . '</option>';
        }
        echo '</select>';
        echo '</td>';
        
        // Taxonomie als Select2 Dropdown
        echo '<td>';
        echo '<select name="ir_sync_field_mappings[' . esc_attr($id) . '][taxonomy]" class="taxonomy-select" style="width: 100%" data-placeholder="Taxonomie auswählen">';
        if (!empty($mapping['taxonomy'])) {
            echo '<option value="' . esc_attr($mapping['taxonomy']) . '" selected>' . esc_html($mapping['taxonomy']) . '</option>';
        }
        echo '</select>';
        echo '</td>';
        
        // Neue Option: Mehrere Einträge erlauben
        echo '<td>';
        echo '<label><input type="checkbox" name="ir_sync_field_mappings[' . esc_attr($id) . '][allow_multiple]" value="1" ' . checked(true, $mapping['allow_multiple'], false) . '/> Mehrere erlauben</label>';
        echo '</td>';
        
        // Aktionen
        echo '<td>';
        if ($id !== '{{id}}') { // Nicht für das Template anzeigen
            echo '<button type="button" class="button button-secondary remove-mapping">Entfernen</button>';
        } else {
            echo '<button type="button" class="button button-secondary remove-mapping">Entfernen</button>';
        }
        echo '</td>';
        
        echo '</tr>';
    }
    
    /**
     * Render-Funktion für den Nachrichten-Abschnitt
     */
    public function render_messages_section() {
        echo '<p>Passen Sie die Nachrichten an, die dem Benutzer angezeigt werden.</p>';
    }
    
    /**
     * Rendert ein Nachrichtenfeld
     */
    public function render_message_field($args) {
        $message_key = $args['message_key'];
        $messages = get_option('ir_sync_messages', array());
        
        // Standardnachrichten
        $default_messages = array(
            'multiple_themes' => 'Sie haben 2 oder mehr Reisethemen gewählt. Sind Sie sicher, dass Sie speichern möchten?',
            'sync_button' => 'Synchronisieren & Speichern',
            'sync_reminder' => 'Sie haben vergessen zu synchronisieren. Bitte drücken Sie zuerst den Synchronisations-Button. Danke.',
            'sync_success' => 'Felder erfolgreich synchronisiert. (%d Terme gesetzt)',
            'sync_error' => 'Synchronisation fehlgeschlagen. Bitte versuchen Sie es erneut.',
            'terms_created' => 'Neue Terms erstellt: %s'
        );
        
        $message = isset($messages[$message_key]) ? $messages[$message_key] : $default_messages[$message_key];
        
        echo '<textarea name="ir_sync_messages[' . esc_attr($message_key) . ']" rows="2" cols="80" class="large-text">' . esc_textarea($message) . '</textarea>';
        
        if ($message_key === 'sync_success') {
            echo '<p class="description">Verwenden Sie %d als Platzhalter für die Anzahl der synchronisierten Terme.</p>';
        } else if ($message_key === 'terms_created') {
            echo '<p class="description">Verwenden Sie %s als Platzhalter für die Liste der neu erstellten Terme.</p>';
        }
    }
    
    /**
     * Render-Funktion für den benutzerdefinierten Meta-Felder-Abschnitt
     */
    public function render_custom_fields_section() {
        echo '<p>Fügen Sie hier häufig verwendete Meta-Felder hinzu, die in den Dropdown-Listen angezeigt werden sollen, auch wenn sie noch nicht in der Datenbank existieren.</p>';
        echo '<p>Diese Felder werden zusätzlich zu den automatisch erkannten Feldern angezeigt und erleichtern die Konfiguration neuer Mappings.</p>';
    }
    
    /**
     * Render-Funktion für den benutzerdefinierten Taxonomien-Abschnitt
     */
    public function render_custom_taxonomies_section() {
        echo '<p>Fügen Sie hier häufig verwendete Taxonomien hinzu, die in den Dropdown-Listen angezeigt werden sollen, auch wenn sie nicht direkt mit dem Post-Typ verknüpft sind.</p>';
    }
    
    /**
     * Rendert das Feld für benutzerdefinierte Meta-Felder
     */
    public function render_custom_fields_field() {
        $custom_fields = get_option('ir_sync_custom_meta_fields', array());
        
        // Falls noch keine benutzerdefinierten Felder existieren, Standardwerte verwenden
        if (empty($custom_fields)) {
            $custom_fields = array(
                'reisethemen_meta', 'reisethemen', 'kontinent', 'kontinent_meta', 
                'land', 'land_meta', 'reiseziel', 'reiseziel_meta', 'destination', 
                'destination_meta', 'theme', 'theme_meta', 'continent', 'continent_meta', 
                'country', 'country_meta', 'region', 'region_meta'
            );
        }
        
        // Container mit Scrollbereich und horizontalem Layout
        echo '<div id="custom-fields-container" style="width: 100%; max-width: 800px;">';
        
        // Textfeld mit Komma-getrennten Werten
        echo '<textarea id="custom-fields-textarea" name="ir_sync_custom_meta_fields_combined" rows="4" style="width: 100%; margin-bottom: 10px;">' . esc_textarea(implode(', ', $custom_fields)) . '</textarea>';
        
        // Hilfetext
        echo '<p class="description">Geben Sie die Feldnamen durch Kommas getrennt ein. Leerzeichen werden automatisch entfernt.</p>';
        
        // Versteckte Felder für das tatsächliche Formular-Speichern
        echo '<div id="custom-fields-hidden" style="display: none;"></div>';
        
        echo '</div>';
        
        // JavaScript für die Verarbeitung der Komma-getrennten Liste
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Bei Formular-Absendung die Komma-getrennte Liste in einzelne Felder umwandeln
            $('form').on('submit', function() {
                var fieldsContainer = $('#custom-fields-hidden');
                fieldsContainer.empty();
                
                // Komma-getrennte Werte in ein Array umwandeln
                var fieldsText = $('#custom-fields-textarea').val();
                var fieldsArray = fieldsText.split(',');
                
                // Für jeden Wert ein verstecktes Feld erstellen
                $.each(fieldsArray, function(index, field) {
                    // Leerzeichen entfernen und prüfen, ob der Wert nicht leer ist
                    field = field.trim();
                    if (field) {
                        fieldsContainer.append('<input type="hidden" name="ir_sync_custom_meta_fields[]" value="' + field + '">');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Rendert das Feld für benutzerdefinierte Taxonomien
     */
    public function render_custom_taxonomies_field() {
        $custom_taxonomies = get_option('ir_sync_custom_taxonomies', array());
        
        // Falls noch keine benutzerdefinierten Taxonomien existieren, Standardwerte verwenden
        if (empty($custom_taxonomies)) {
            $custom_taxonomies = array(
                'reisethemen', 'kontinent_taxon', 'land_taxon', 'reiseziel_taxon', 
                'destination_taxon', 'theme_taxon', 'continent_taxon', 'country_taxon', 'region_taxon'
            );
        }
        
        // Container mit Scrollbereich und horizontalem Layout
        echo '<div id="custom-taxonomies-container" style="width: 100%; max-width: 800px;">';
        
        // Textfeld mit Komma-getrennten Werten
        echo '<textarea id="custom-taxonomies-textarea" name="ir_sync_custom_taxonomies_combined" rows="4" style="width: 100%; margin-bottom: 10px;">' . esc_textarea(implode(', ', $custom_taxonomies)) . '</textarea>';
        
        // Hilfetext
        echo '<p class="description">Geben Sie die Taxonomienamen durch Kommas getrennt ein. Leerzeichen werden automatisch entfernt.</p>';
        
        // Versteckte Felder für das tatsächliche Formular-Speichern
        echo '<div id="custom-taxonomies-hidden" style="display: none;"></div>';
        
        echo '</div>';
        
        // JavaScript für die Verarbeitung der Komma-getrennten Liste
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Bei Formular-Absendung die Komma-getrennte Liste in einzelne Felder umwandeln
            $('form').on('submit', function() {
                var taxonomiesContainer = $('#custom-taxonomies-hidden');
                taxonomiesContainer.empty();
                
                // Komma-getrennte Werte in ein Array umwandeln
                var taxonomiesText = $('#custom-taxonomies-textarea').val();
                var taxonomiesArray = taxonomiesText.split(',');
                
                // Für jeden Wert ein verstecktes Feld erstellen
                $.each(taxonomiesArray, function(index, taxonomy) {
                    // Leerzeichen entfernen und prüfen, ob der Wert nicht leer ist
                    taxonomy = taxonomy.trim();
                    if (taxonomy) {
                        taxonomiesContainer.append('<input type="hidden" name="ir_sync_custom_taxonomies[]" value="' + taxonomy + '">');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Validiert und bereinigt die benutzerdefinierten Meta-Felder und Taxonomien
     */
    public function sanitize_custom_fields($input) {
        $sanitized_input = array();
        
        if (is_array($input)) {
            foreach ($input as $field) {
                $field = trim(sanitize_text_field($field));
                if (!empty($field) && !in_array($field, $sanitized_input)) {
                    $sanitized_input[] = $field;
                }
            }
        }
        
        return $sanitized_input;
    }
    
    /**
     * Validiert und bereinigt die Mapping-Einstellungen
     */
    public function sanitize_field_mappings($input) {
        $sanitized_input = array();
        
        if (!is_array($input)) {
            return $sanitized_input;
        }
        
        foreach ($input as $id => $mapping) {
            if (empty($mapping['meta_field']) || empty($mapping['taxonomy'])) {
                continue; // Überspringe unvollständige Mappings
            }
            
            /* geaenderte reihenfolge --- 21-04-2025 --- */
            $sanitized_input[$id] = array(
                'post_type' => sanitize_text_field($mapping['post_type']),
                'meta_field' => sanitize_text_field($mapping['meta_field']),
                'taxonomy' => sanitize_text_field($mapping['taxonomy']),
                'active' => isset($mapping['active']) ? true : false,
                'allow_multiple' => isset($mapping['allow_multiple']) ? true : false // Neue Option
            );
        }
        
        return $sanitized_input;
    }
    
    /**
     * Validiert und bereinigt die Nachrichteneinstellungen
     */
    public function sanitize_messages($input) {
        $sanitized_input = array();
        
        // Standardnachrichten
        $default_messages = array(
            'multiple_themes' => 'Sie haben 2 oder mehr Reisethemen gewählt. Sind Sie sicher, dass Sie speichern möchten?',
            'sync_button' => 'Synchronisieren & Speichern',
            'sync_reminder' => 'Sie haben vergessen zu synchronisieren. Bitte drücken Sie zuerst den Synchronisations-Button. Danke.',
            'sync_success' => 'Felder erfolgreich synchronisiert. (%d Terme gesetzt)',
            'sync_error' => 'Synchronisation fehlgeschlagen. Bitte versuchen Sie es erneut.',
            'terms_created' => 'Neue Terms erstellt: %s'
        );
        
        foreach ($default_messages as $key => $default) {
            $sanitized_input[$key] = isset($input[$key]) ? sanitize_text_field($input[$key]) : $default;
        }
        
        return $sanitized_input;
    }
    
    /**
     * Rendert die Einstellungsseite
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('ir_sync_settings_group');
                do_settings_sections('ir-tours-sync-settings');
                submit_button('Einstellungen speichern');
                ?>
            </form>
        </div>
        <?php
    }
}

// Admin-Instanz erstellen
new Crocoblock_Sync_Admin();