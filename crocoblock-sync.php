<?php
/*
Plugin Name: Crocoblock Sync
Description: Synchronisiert Meta-Felder mit Taxonomien für Custom Post Types in WordPress
Version: 1.0
Author: Joseph Kisler - Webwerkstatt
Author URI: https://web-werkstatt.at/
Text Domain: crocoblock-sync
Domain Path: /languages
*/

// Direktzugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Plugin-Konstanten definieren
define('CROCOBLOCK_SYNC_VERSION', '1.0.0');
define('CROCOBLOCK_SYNC_FILE', __FILE__);
define('CROCOBLOCK_SYNC_PATH', plugin_dir_path(__FILE__));
define('CROCOBLOCK_SYNC_URL', plugin_dir_url(__FILE__));
define('CROCOBLOCK_SYNC_BASENAME', plugin_basename(__FILE__));

/**
 * Hauptklasse des Plugins
 */
class Crocoblock_Sync {
    /**
     * Einzelinstanz des Plugins
     * 
     * @var Crocoblock_Sync
     */
    private static $instance = null;
    
    /**
     * Gibt die Einzelinstanz des Plugins zurück
     * 
     * @return Crocoblock_Sync
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Konstruktor - initialisiert das Plugin
     */
    private function __construct() {
        // PHP-Version prüfen
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return;
        }
        
        // Abhängigkeiten laden
        $this->includes();
        
        // Aktivierungshook
        register_activation_hook(CROCOBLOCK_SYNC_FILE, array($this, 'activate'));
        
        // Übersetzungen laden
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Aktions-Links im Plugin-Bereich hinzufügen
        add_filter('plugin_action_links_' . CROCOBLOCK_SYNC_BASENAME, array($this, 'add_plugin_action_links'));
    }
    
    /**
     * Warnung für zu alte PHP-Version anzeigen
     */
    public function php_version_notice() {
        $message = sprintf(
            __('Crocoblock Sync benötigt mindestens PHP 7.4. Ihre aktuelle Version ist %s.', 'crocoblock-sync'),
            PHP_VERSION
        );
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }
    
    /**
     * Inkludiert die benötigten Dateien
     */
    private function includes() {
        // Admin-Bereich
        if (is_admin()) {
            require_once CROCOBLOCK_SYNC_PATH . 'includes/admin.php';
        }
        
        // Kernfunktionalität (immer laden)
        require_once CROCOBLOCK_SYNC_PATH . 'includes/core.php';
    }
    
    /**
     * Wird bei der Plugin-Aktivierung ausgeführt
     */
    public function activate() {
        // Standardeinstellungen setzen, falls noch nicht vorhanden
        if (!get_option('ir_sync_field_mappings')) {
            // Standard-Mappings
            $default_mappings = array(
                1 => array(
                    'meta_field' => 'reisethemen_meta',
                    'taxonomy' => 'reisethemen',
                    'post_type' => 'ir-tours',
                    'active' => true
                )
            );
            
            // Prüfen, ob kontinent_taxon existiert
            if (taxonomy_exists('kontinent_taxon')) {
                $default_mappings[2] = array(
                    'meta_field' => 'kontinent',
                    'taxonomy' => 'kontinent_taxon',
                    'post_type' => 'ir-tours',
                    'active' => true
                );
            }
            
            update_option('ir_sync_field_mappings', $default_mappings);
        }
        
        if (!get_option('ir_sync_messages')) {
            // Standard-Nachrichten
            $default_messages = array(
                'multiple_themes' => 'Sie haben 2 oder mehr Reisethemen gewählt. Sind Sie sicher, dass Sie speichern möchten?',
                'sync_button' => 'Synchronisieren & Speichern',
                'sync_reminder' => 'Sie haben vergessen zu synchronisieren. Bitte drücken Sie zuerst den Synchronisations-Button. Danke.',
                'sync_success' => 'Felder erfolgreich synchronisiert. (%d Terme gesetzt)',
                'sync_error' => 'Synchronisation fehlgeschlagen. Bitte versuchen Sie es erneut.'
            );
            
            update_option('ir_sync_messages', $default_messages);
        }
        
        if (!get_option('ir_sync_general_settings')) {
            // Allgemeine Einstellungen
            $default_settings = array(
                'debug_mode' => false
            );
            
            update_option('ir_sync_general_settings', $default_settings);
        }
        
        // Capabilities setzen
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('manage_crocoblock_sync');
        }
    }
    
    /**
     * Lädt die Übersetzungsdateien
     */
    public function load_textdomain() {
        load_plugin_textdomain('crocoblock-sync', false, dirname(CROCOBLOCK_SYNC_BASENAME) . '/languages');
    }
    
    /**
     * Fügt Links zur Plugin-Aktion hinzu
     */
    public function add_plugin_action_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('options-general.php?page=ir-tours-sync-settings') . '">' . __('Einstellungen', 'crocoblock-sync') . '</a>'
        );
        return array_merge($plugin_links, $links);
    }
}

// Plugin initialisieren
function crocoblock_sync() {
    return Crocoblock_Sync::get_instance();
}

// Starten
crocoblock_sync();