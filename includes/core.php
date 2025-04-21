<?php
/**
 * Kernfunktionalität für das Crocoblock Sync Plugin
 * 
 * @version 1.0
 */

// Direktzugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Kernklasse für die Synchronisationsfunktionalität
 */
class Crocoblock_Sync_Core {
    /**
     * Konstruktor - initialisiert Hooks
     */
    public function __construct() {
        // Post-Speicher-Hooks
        add_action('save_post', array($this, 'sync_on_save'), 99, 2);
        
        // AJAX-Hooks für die manuelle Synchronisation
        add_action('wp_ajax_ir_manual_sync', array($this, 'ajax_manual_sync'));
        add_action('wp_ajax_nopriv_ir_manual_sync', array($this, 'ajax_unauthorized'));
        
        // Editor-Skripte laden
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_scripts'));
        add_action('elementor/editor/before_enqueue_scripts', array($this, 'enqueue_elementor_scripts'));
        
        // Admin-Hinweis für mehrere Reisethemen
        add_action('admin_notices', array($this, 'display_reisethemen_warning'));
    }
    
    /**
     * Zeigt eine Warnung an, wenn mehrere Reisethemen ausgewählt wurden
     */
    public function display_reisethemen_warning() {
        if (isset($_GET['reisethemen_warning']) && $_GET['reisethemen_warning'] === '1') {
            // Benutzerdefinierte Nachricht aus den Einstellungen holen
            $messages = get_option('ir_sync_messages', array());
            $warning_message = isset($messages['multiple_themes']) && !empty($messages['multiple_themes']) 
                ? $messages['multiple_themes'] 
                : 'Sie haben mehrere Reisethemen ausgewählt. Sind Sie sicher, dass Sie speichern möchten?';
            
            echo '<div class="notice notice-warning is-dismissible">
                <p><strong>Achtung:</strong> ' . esc_html($warning_message) . '</p>
            </div>';
        }
    }
    
    /**
     * Synchronisiert alle konfigurierten Felder beim Speichern eines Beitrags
     * 
     * @param int $post_id Die Post-ID
     * @param WP_Post $post Das Post-Objekt
     */
    public function sync_on_save($post_id, $post) {
        // Autosave, Revision oder AJAX ignorieren
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || 
            wp_is_post_revision($post_id) || 
            wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Berechtigungen prüfen
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Mappings abrufen
        $mappings = get_option('ir_sync_field_mappings', array());
        
        // Prüfen, ob Mappings für diesen Post-Typ existieren
        $relevant_mappings = array();
        foreach ($mappings as $mapping) {
            if ($mapping['active'] && $mapping['post_type'] === $post->post_type) {
                $relevant_mappings[] = $mapping;
            }
        }
        
        // Wenn keine relevanten Mappings gefunden wurden, abbrechen
        if (empty($relevant_mappings)) {
            return;
        }
        
        // Doppelte Speichervorgänge verhindern
        static $is_saving = false;
        if ($is_saving) {
            return;
        }
        $is_saving = true;
        
        // Alle relevanten Felder synchronisieren
        $results = array();
        $multiple_themes_warning = false;
        
        foreach ($relevant_mappings as $mapping) {
            $result = $this->sync_single_field(
                $post_id,
                $mapping['meta_field'],
                $mapping['taxonomy']
            );
            
            $results[$mapping['meta_field']] = $result;
            
            // Prüfen, ob mehrere Terme ausgewählt sind und ob dies erlaubt ist
            if ($result && 
                isset($result['terms']) && 
                count($result['terms']) >= 2 && 
                (!isset($mapping['allow_multiple']) || !$mapping['allow_multiple'])) {
                $multiple_themes_warning = true;
            }
        }
        
        // Warnung setzen, wenn mehrere Terme ausgewählt sind
        if ($multiple_themes_warning) {
            add_filter('redirect_post_location', function($location) {
                return add_query_arg('reisethemen_warning', '1', $location);
            });
        }
        
        // Nach der Verarbeitung die Sperre zurücksetzen
        $is_saving = false;
    }
    
    /**
     * Synchronisiert ein einzelnes Feld mit einer Taxonomie
     * 
     * @param int $post_id Die Post-ID
     * @param string $meta_field Name des Meta-Feldes
     * @param string $taxonomy Name der Taxonomie
     * @return array|bool Ergebnis der Synchronisation
     */
    public function sync_single_field($post_id, $meta_field, $taxonomy) {
        if (!$post_id) {
            return false;
        }

        // Prüfen, ob Metadaten existieren
        if (!metadata_exists('post', $post_id, $meta_field)) {
            return false;
        }
        
        // Prüfen, ob Taxonomie existiert
        if (!taxonomy_exists($taxonomy)) {
            return false;
        }

        // Meta-Daten abrufen
        $selected_terms = get_post_meta($post_id, $meta_field, true);
        
        // Leere Werte behandeln
        if (empty($selected_terms) || $selected_terms === 'false' || $selected_terms === false || $selected_terms === null) {
            wp_set_object_terms($post_id, array(), $taxonomy);
            return array(
                'status' => 'cleared',
                'meta_field' => $meta_field,
                'taxonomy' => $taxonomy,
                'terms' => array()
            );
        }
        
        // Sicherstellen, dass es ein Array ist
        if (!is_array($selected_terms)) {
            $selected_terms = array($selected_terms);
        }

        // Ungültige Werte filtern
        $selected_terms = array_filter($selected_terms, function($term) {
            return !empty($term) && $term !== 'false';
        });

        // Alphabetisch sortieren
        $terms_sorted = array();
        foreach ($selected_terms as $term_id_or_slug) {
            $term = null;
            if (is_numeric($term_id_or_slug)) {
                $term = get_term_by('id', intval($term_id_or_slug), $taxonomy);
            } else {
                $term = get_term_by('slug', sanitize_text_field($term_id_or_slug), $taxonomy);
                
                // Wenn kein Term gefunden wurde, versuche es mit dem Namen
                if (!$term) {
                    $term = get_term_by('name', sanitize_text_field($term_id_or_slug), $taxonomy);
                }
                
                // Wenn immer noch kein Term gefunden wurde, versuche einen zu erstellen
                if (!$term && !is_numeric($term_id_or_slug)) {
                    $new_term = wp_insert_term(sanitize_text_field($term_id_or_slug), $taxonomy);
                    if (!is_wp_error($new_term)) {
                        $term = get_term_by('id', $new_term['term_id'], $taxonomy);
                    }
                }
            }
            
            if ($term && !is_wp_error($term) && isset($term->name) && isset($term->term_id)) {
                $terms_sorted[$term->name] = $term->term_id;
            }
        }

        // Wenn keine gültigen Terms gefunden wurden
        if (empty($terms_sorted)) {
            wp_set_object_terms($post_id, array(), $taxonomy);
            return array(
                'status' => 'cleared',
                'meta_field' => $meta_field,
                'taxonomy' => $taxonomy,
                'terms' => array()
            );
        }

        // Sortieren und Taxonomie-Terme setzen
        ksort($terms_sorted);
        $result = wp_set_object_terms($post_id, array_values($terms_sorted), $taxonomy, false);
        
        return array(
            'status' => is_wp_error($result) ? 'error' : 'success',
            'meta_field' => $meta_field,
            'taxonomy' => $taxonomy,
            'terms' => array_values($terms_sorted),
            'error' => is_wp_error($result) ? $result->get_error_message() : null
        );
    }
    
    /**
     * AJAX-Handler für nicht autorisierte Anfragen
     */
    public function ajax_unauthorized() {
        wp_send_json_error('Sie müssen angemeldet sein, um diese Aktion durchführen zu können.');
    }
    
	/**
	 * AJAX-Handler für die manuelle Synchronisation
	 */
	public function ajax_manual_sync() {
		// Nonce prüfen
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ir_sync_nonce')) {
			wp_send_json_error('Sicherheitsüberprüfung fehlgeschlagen.');
			return;
		}

		// Berechtigungen prüfen
		if (!current_user_can('edit_posts')) {
			wp_send_json_error('Nicht erlaubt.');
			return;
		}

		// Post-ID validieren
		$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
		if (!$post_id) {
			wp_send_json_error('Keine gültige Post-ID angegeben.');
			return;
		}
		
		// Post-Typ abrufen
		$post_type = get_post_type($post_id);
		if (!$post_type) {
			wp_send_json_error('Ungültiger Beitragstyp.');
			return;
		}
		
		// Prüfen, ob der Beitrag bereits gespeichert wurde
		$post_status = get_post_status($post_id);
		$post = get_post($post_id);
		
		if ($post_status === 'auto-draft' || $post_status === false || empty($post_status) || 
			($post && $post->post_status === 'auto-draft')) {
			wp_send_json_error('Bitte speichern Sie den Beitrag zuerst als Entwurf oder veröffentlichen Sie ihn, bevor Sie synchronisieren.');
			return;
		}
		
		// Prüfen, ob Meta-Felder existieren bevor wir versuchen zu synchronisieren
		$mappings = get_option('ir_sync_field_mappings', array());
		$meta_fields_exist = true;
		$missing_fields = array();
		
		foreach ($mappings as $mapping) {
			if ($mapping['active'] && $mapping['post_type'] === $post_type) {
				if (!metadata_exists('post', $post_id, $mapping['meta_field'])) {
					$meta_fields_exist = false;
					$missing_fields[] = $mapping['meta_field'];
				}
			}
		}
    
    // Wenn Meta-Felder fehlen, gib eine spezifische Fehlermeldung zurück
    if (!$meta_fields_exist) {
        wp_send_json_error('Bitte speichern Sie den Beitrag zuerst als Entwurf oder veröffentlichen Sie ihn, bevor Sie synchronisieren. Die folgenden Felder wurden nicht gefunden: ' . implode(', ', $missing_fields));
        return;
    }

    // Mappings abrufen
    $relevant_mappings = array();
    foreach ($mappings as $mapping) {
        if ($mapping['active'] && $mapping['post_type'] === $post_type) {
            $relevant_mappings[] = $mapping;
        }
    }
    
    // Wenn keine relevanten Mappings gefunden wurden
    if (empty($relevant_mappings)) {
        wp_send_json_error('Keine Mapping-Konfigurationen für diesen Beitragstyp gefunden.');
        return;
    }

    // Alle relevanten Felder synchronisieren
    $results = array();
    foreach ($relevant_mappings as $mapping) {
        $result = $this->sync_single_field(
            $post_id,
            $mapping['meta_field'],
            $mapping['taxonomy']
        );
        
        $results[$mapping['meta_field']] = $result;
    }
    
    // Erfolgs- und Fehlerfälle zählen
    $success_count = 0;
    $error_messages = array();
    $term_count = 0;
    
    foreach ($results as $field => $result) {
        if ($result === false) {
            $error_messages[] = "Feld $field: Konnte nicht synchronisiert werden.";
        } else if ($result['status'] === 'error') {
            $error_messages[] = "Feld $field: " . $result['error'];
        } else {
            $success_count++;
            $term_count += count($result['terms']);
        }
    }
    
    // Wenn Fehler aufgetreten sind
    if (!empty($error_messages)) {
        // Spezielle Behandlung für Fehler, die durch fehlende Meta-Felder verursacht werden
        $test_all_fields_missing = true;
        foreach ($relevant_mappings as $mapping) {
            if (metadata_exists('post', $post_id, $mapping['meta_field'])) {
                $test_all_fields_missing = false;
                break;
            }
        }
        
        if ($test_all_fields_missing) {
            wp_send_json_error('Bitte speichern Sie den Beitrag zuerst als Entwurf oder veröffentlichen Sie ihn, bevor Sie synchronisieren.');
        } else {
            wp_send_json_error(implode('<br>', $error_messages));
        }
        return;
    }
    
    // Prüfen, ob mehrere Terme gefunden wurden und ob dies erlaubt ist
    $multiple_terms_detected = false;
    foreach ($results as $field => $result) {
        if ($result && 
            isset($result['terms']) && 
            count($result['terms']) >= 2) {
            
            // Finde das entsprechende Mapping für dieses Feld
            $mapping_found = false;
            foreach ($relevant_mappings as $mapping) {
                if ($mapping['meta_field'] === $field) {
                    $mapping_found = true;
                    if (!isset($mapping['allow_multiple']) || !$mapping['allow_multiple']) {
                        $multiple_terms_detected = true;
                        break 2; // Beide Schleifen verlassen
                    }
                }
            }
            
            // Wenn kein Mapping gefunden wurde, vorsichtshalber Warnung setzen
            if (!$mapping_found) {
                $multiple_terms_detected = true;
                break;
            }
        }
    }
    
    // Erfolg
    $messages = get_option('ir_sync_messages', array());
    $success_message = isset($messages['sync_success']) 
        ? sprintf($messages['sync_success'], $term_count)
        : sprintf('Felder erfolgreich synchronisiert. (%d Terme gesetzt)', $term_count);
    
    wp_send_json_success(array(
        'message' => $success_message,
        'count' => $term_count,
        'fields' => array_keys($results),
        'show_warning' => $multiple_terms_detected // Neue Eigenschaft für die Warnung
    ));
}
    
    /**
     * Lädt Skripte und Stile für den Block-Editor
     */
	/**
 * Sicherstellen, dass benutzerdefinierte Nachrichten korrekt geladen und weitergegeben werden
 */
public function enqueue_editor_scripts() {
    global $post;
    
    if (!$post) {
        return;
    }
    
    // Prüfen, ob die Skripte bereits geladen wurden
    if (wp_script_is('crocoblock-sync-scripts', 'enqueued')) {
        return;
    }
    
    // Mappings abrufen, um relevante Post-Typen zu ermitteln
    $mappings = get_option('ir_sync_field_mappings', array());
    
    // Post-Typen aus den Mappings extrahieren
    $relevant_post_types = array();
    foreach ($mappings as $mapping) {
        if ($mapping['active'] && !in_array($mapping['post_type'], $relevant_post_types)) {
            $relevant_post_types[] = $mapping['post_type'];
        }
    }
    
    // Wenn keine Mappings definiert sind, Standard-Post-Typ verwenden
    if (empty($relevant_post_types)) {
        $relevant_post_types = array('ir-tours');
    }
    
    // Nur für relevante Post-Typen laden
    if (!in_array($post->post_type, $relevant_post_types)) {
        return;
    }
    
    // Allgemeine Einstellungen abrufen
    $general_settings = get_option('ir_sync_general_settings', array());
    $debug_mode = isset($general_settings['debug_mode']) ? $general_settings['debug_mode'] : false;
    
    // Prüfen, ob wir uns im Elementor-Editor befinden
    $is_elementor = (isset($_GET['action']) && $_GET['action'] === 'elementor') || 
                  (isset($_REQUEST['action']) && $_REQUEST['action'] === 'elementor');
    
    // JS-Datei laden - einheitlicher Handle-Name
    wp_enqueue_script(
        'crocoblock-sync-scripts',
        CROCOBLOCK_SYNC_URL . 'assets/js/editor.js',
        array('jquery', 'wp-data', 'wp-editor'),
        CROCOBLOCK_SYNC_VERSION,
        true
    );
    
    // CSS-Datei laden
    wp_enqueue_style(
        'crocoblock-sync-styles',
        CROCOBLOCK_SYNC_URL . 'assets/css/editor.css',
        array(),
        CROCOBLOCK_SYNC_VERSION
    );
    
    // Nachrichten abrufen - stellt sicher, dass der Wert ein Array ist
    $messages = get_option('ir_sync_messages', array());
    if (!is_array($messages)) {
        $messages = array();
    }

    // Sicherstellen, dass alle erforderlichen Schlüssel vorhanden sind
    $default_messages = array(
        'multiple_themes' => 'Sie haben 2 oder mehr Reisethemen gewählt. Sind Sie sicher, dass Sie speichern möchten?',
        'sync_button' => 'Synchronisieren & Speichern',
        'sync_reminder' => 'Sie haben vergessen zu synchronisieren. Bitte drücken Sie zuerst den Synchronisations-Button. Danke.',
        'sync_success' => 'Felder erfolgreich synchronisiert. (%d Terme gesetzt)',
        'sync_error' => 'Synchronisation fehlgeschlagen. Bitte versuchen Sie es erneut.'
    );

    // Fehlende Schlüssel aus den Standardwerten ergänzen
    foreach ($default_messages as $key => $value) {
        if (!isset($messages[$key]) || empty($messages[$key])) {
            $messages[$key] = $value;
        }
    }

    // Optionales Debug-Logging
    if ($debug_mode) {
        error_log('IR Tours Sync - Messages für JavaScript: ' . print_r($messages, true));
    }
    
    // Daten für JavaScript bereitstellen - wichtig: sicherstellen, dass messages korrekt übergeben wird
    wp_localize_script('crocoblock-sync-scripts', 'irSyncData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ir_sync_nonce'),
        'messages' => $messages, // Benutzerdefinierte Nachrichten
        'mappings' => $mappings,
        'pluginUrl' => CROCOBLOCK_SYNC_URL,
        'debugMode' => $debug_mode,
        'postType' => $post->post_type,
        'isElementor' => $is_elementor
    ));
}
    
    /**
     * Lädt Skripte und Stile für den Elementor-Editor
     */
    public function enqueue_elementor_scripts() {
        // Verwende die gemeinsame Funktion für beide Editoren
        $this->enqueue_editor_scripts();
    }
}

// Core-Instanz erstellen
new Crocoblock_Sync_Core();