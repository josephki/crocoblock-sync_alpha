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
        
        wp_enqueue_style(
            'crocoblock-sync-admin', 
            CROCOBLOCK_SYNC_URL . 'assets/css/admin.css', 
            array(), 
            CROCOBLOCK_SYNC_VERSION
        );
        
        wp_enqueue_script(
            'crocoblock-sync-admin', 
            CROCOBLOCK_SYNC_URL . 'assets/js/admin.js', 
            array('jquery'), 
            CROCOBLOCK_SYNC_VERSION, 
            true
        );
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
        echo '<p class="description">Aktiviere diese Option, um detaillierte Informationen bei der Fehlersuche zu erhalten.</p>';
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
                    'active' => true
                )
            );
            
            // Prüfen, ob kontinent_taxon existiert
            if (taxonomy_exists('kontinent_taxon')) {
                $mappings[2] = array(
                    'meta_field' => 'kontinent',
                    'taxonomy' => 'kontinent_taxon',
                    'post_type' => 'ir-tours',
                    'active' => true
                );
            }
        }
        
        // Aufbau der Tabelle
        echo '<table class="widefat ir-sync-mappings-table" id="ir-sync-mappings-table">';
        echo '<thead>
                <tr>
                    <th width="5%">Aktiv</th>
                    <th width="25%">Meta-Feld</th>
                    <th width="25%">Taxonomie</th>
                    <th width="25%">Post-Typ</th>
                    <th width="20%">Aktionen</th>
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
            'active' => true
        ));
        echo '</script>';
    }
    
    /**
     * Rendert eine einzelne Mapping-Zeile
     */
    private function render_mapping_row($id, $mapping) {
        echo '<tr class="mapping-row">';
        
        // Aktiv-Checkbox
        echo '<td>';
        echo '<input type="checkbox" name="ir_sync_field_mappings[' . esc_attr($id) . '][active]" value="1" ' . checked(true, isset($mapping['active']) ? $mapping['active'] : true, false) . '/>';
        echo '</td>';
        
        // Meta-Feld
        echo '<td>';
        echo '<input type="text" name="ir_sync_field_mappings[' . esc_attr($id) . '][meta_field]" value="' . esc_attr($mapping['meta_field']) . '" placeholder="z.B. reisethemen_meta" class="regular-text" required />';
        echo '</td>';
        
        // Taxonomie
        echo '<td>';
        echo '<input type="text" name="ir_sync_field_mappings[' . esc_attr($id) . '][taxonomy]" value="' . esc_attr($mapping['taxonomy']) . '" placeholder="z.B. reisethemen" class="regular-text" required />';
        echo '</td>';
        
        // Post-Typ
        echo '<td>';
        echo '<input type="text" name="ir_sync_field_mappings[' . esc_attr($id) . '][post_type]" value="' . esc_attr($mapping['post_type']) . '" placeholder="z.B. ir-tours" class="regular-text" required />';
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
                'active' => isset($mapping['active']) ? true : false
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