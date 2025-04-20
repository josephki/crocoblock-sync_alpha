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
        // Sicherheitsüberprüfung
        check_ajax_referer('ir_sync_admin_nonce', 'nonce');
        
        // Nur für Administratoren
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Nicht autorisiert');
        }
        
        // Parameter abrufen
        $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : '';
        $search_type = isset($_GET['search_type']) ? sanitize_text_field($_GET['search_type']) : 'meta_field';
        
        if (empty($post_type)) {
            wp_send_json_error('Kein Post-Typ angegeben');
        }
        
        // Ergebnisliste initialisieren
        $results = array();
        
        if ($search_type === 'meta_field') {
            // Metafelder für diesen Post-Typ suchen
            $results = $this->get_post_meta_keys($post_type);
        } elseif ($search_type === 'taxonomy') {
            // Taxonomien für diesen Post-Typ suchen
            $results = $this->get_post_taxonomies($post_type);
        }
        
        // Format: [{id: "feldname", text: "feldname"}, ...]
        $formatted_results = array();
        foreach ($results as $result) {
            $formatted_results[] = array(
                'id' => $result,
                'text' => $result
            );
        }
        
        // Debug-Log
        $settings = get_option('ir_sync_general_settings', array());
        $debug_mode = isset($settings['debug_mode']) ? $settings['debug_mode'] : false;
        
        if ($debug_mode) {
            error_log(sprintf(
                'Crocoblock Sync - Feldnamen für %s (Typ: %s): %d Ergebnisse gefunden',
                $post_type,
                $search_type,
                count($formatted_results)
            ));
        }
        
        wp_send_json_success($formatted_results);
    }
    
    /**
     * Holt alle Meta-Keys für einen bestimmten Post-Typ
     */
    private function get_post_meta_keys($post_type) {
        global $wpdb;
        
        // Alle Meta-Keys aus der Datenbank abrufen
        $query = $wpdb->prepare(
            "SELECT DISTINCT meta_key
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.post_type = %s
            ORDER BY meta_key",
            $post_type
        );
        
        $meta_keys = $wpdb->get_col($query);
        
        // Debug-Log
        $settings = get_option('ir_sync_general_settings', array());
        $debug_mode = isset($settings['debug_mode']) ? $settings['debug_mode'] : false;
        
        if ($debug_mode) {
            error_log(sprintf(
                'Crocoblock Sync - Meta-Keys aus DB für %s: %d Keys gefunden',
                $post_type,
                count($meta_keys)
            ));
        }
        
        // ACF-Felder hinzufügen (wenn ACF aktiv ist)
        if (function_exists('acf_get_field_groups')) {
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
                
                foreach ($fields as $field) {
                    // ACF-Feldname hinzufügen, falls nicht bereits in der Liste
                    if (!in_array($field['name'], $meta_keys)) {
                        $meta_keys[] = $field['name'];
                    }
                }
            }
        }
        
        // JetEngine-Felder hinzufügen (wenn JetEngine aktiv ist)
        if (class_exists('Jet_Engine')) {
            // Versuche, JetEngine-Meta-Boxen zu finden
            if (function_exists('jet_engine()->meta_boxes')) {
                $meta_boxes = jet_engine()->meta_boxes->get_meta_boxes();
                
                if ($debug_mode) {
                    error_log(sprintf(
                        'Crocoblock Sync - JetEngine-Metaboxen: %d Boxen gefunden',
                        count($meta_boxes)
                    ));
                }
                
                foreach ($meta_boxes as $meta_box) {
                    if (isset($meta_box['args']['object_type']) && $meta_box['args']['object_type'] === 'post' && 
                        isset($meta_box['args']['post_type']) && 
                        (in_array($post_type, (array)$meta_box['args']['post_type']) || $meta_box['args']['post_type'] === 'all')) {
                        
                        if (isset($meta_box['meta_fields']) && is_array($meta_box['meta_fields'])) {
                            foreach ($meta_box['meta_fields'] as $field) {
                                if (isset($field['name']) && !in_array($field['name'], $meta_keys)) {
                                    $meta_keys[] = $field['name'];
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Häufig genutzte Meta-Field-Namen für Crocoblock hinzufügen
        $common_fields = array(
            'reisethemen_meta',
            'kontinent',
            'land',
            'reiseziel'
        );
        
        foreach ($common_fields as $field) {
            if (!in_array($field, $meta_keys)) {
                $meta_keys[] = $field;
            }
        }
        
        // Liste sortieren und zurückgeben
        sort($meta_keys);
        
        return $meta_keys;
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
        
        // Häufig genutzte Taxonomie-Namen für Crocoblock hinzufügen
        $common_taxonomies = array(
            'reisethemen',
            'kontinent_taxon',
            'land_taxon',
            'reiseziel_taxon'
        );
        
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
        // Die restliche Funktion bleibt unverändert
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
            'sync_error' => 'Fehlermeldung bei Synchronisation'
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
    }
    
    // Der Rest der Klasse bleibt unverändert
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
                    <th width="20%">Meta-Feld</th>
                    <th width="20%">Taxonomie</th>
                    <th width="15%">Post-Typ</th>
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
            'meta_field' => '',
            'taxonomy' => '',
            'post_type' => 'ir-tours',
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
        
        // Post-Typ als Dropdown
        echo '<td>';
        echo '<select name="ir_sync_field_mappings[' . esc_attr($id) . '][post_type]" class="post-type-select" required>';
        
        foreach ($post_types as $post_type) {
            echo '<option value="' . esc_attr($post_type->name) . '" ' . selected($mapping['post_type'], $post_type->name, false) . '>' . esc_html($post_type->label) . '</option>';
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
            'sync_error' => 'Synchronisation fehlgeschlagen. Bitte versuchen Sie es erneut.'
        );
        
        $message = isset($messages[$message_key]) ? $messages[$message_key] : $default_messages[$message_key];
        
        echo '<textarea name="ir_sync_messages[' . esc_attr($message_key) . ']" rows="2" cols="80" class="large-text">' . esc_textarea($message) . '</textarea>';
        
        if ($message_key === 'sync_success') {
            echo '<p class="description">Verwenden Sie %d als Platzhalter für die Anzahl der synchronisierten Terme.</p>';
        }
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
            
            $sanitized_input[$id] = array(
                'meta_field' => sanitize_text_field($mapping['meta_field']),
                'taxonomy' => sanitize_text_field($mapping['taxonomy']),
                'post_type' => sanitize_text_field($mapping['post_type']),
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
            'sync_error' => 'Synchronisation fehlgeschlagen. Bitte versuchen Sie es erneut.'
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