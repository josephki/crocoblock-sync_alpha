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
     * Wenn ein Term in der Taxonomie nicht existiert, wird er automatisch angelegt
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
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Sync abgebrochen: Meta-Feld '$meta_field' existiert nicht für Post $post_id");
            }
            return false;
        }
        
        // Prüfen, ob Taxonomie existiert
        if (!taxonomy_exists($taxonomy)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Sync abgebrochen: Taxonomie '$taxonomy' existiert nicht");
            }
            return false;
        }

        // Meta-Daten abrufen
        $selected_terms = get_post_meta($post_id, $meta_field, true);
        
        // Debugging-Ausgabe
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Synchronisiere '$meta_field' mit Taxonomie '$taxonomy'. Wert: " . print_r($selected_terms, true));
        }
        
        // Leere Werte behandeln
        if (empty($selected_terms) && $selected_terms !== '0' && $selected_terms !== 0) {
            // Prüfen, ob wir Terms löschen oder beibehalten sollen
            $delete_empty_terms = apply_filters('crocoblock_sync_delete_empty_terms', true, $post_id, $meta_field, $taxonomy);
            
            if ($delete_empty_terms) {
                $result = wp_set_object_terms($post_id, array(), $taxonomy);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Leerer Wert: Alle Terms für Taxonomie '$taxonomy' gelöscht");
                }
                
                return array(
                    'status' => 'cleared',
                    'meta_field' => $meta_field,
                    'taxonomy' => $taxonomy,
                    'terms' => array()
                );
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Leerer Wert: Bestehende Terms für Taxonomie '$taxonomy' beibehalten (durch Filter)");
                }
                
                return array(
                    'status' => 'skipped',
                    'meta_field' => $meta_field,
                    'taxonomy' => $taxonomy,
                    'terms' => array()
                );
            }
        }
        
        // JetEngine- und andere dynamische Felder können verschiedene Formate liefern
        // Wir versuchen, sie in ein einheitliches Format zu bringen
        if (!is_array($selected_terms)) {
            // JetEngine-Felder können durch Komma getrennte Werte liefern
            if (is_string($selected_terms) && strpos($selected_terms, ',') !== false) {
                $selected_terms = array_map('trim', explode(',', $selected_terms));
            } 
            // JetEngine kann auch serialisierte Arrays liefern
            else if (is_string($selected_terms) && is_serialized($selected_terms)) {
                $unserialized = maybe_unserialize($selected_terms);
                if (is_array($unserialized)) {
                    $selected_terms = $unserialized;
                } else {
                    $selected_terms = array($selected_terms);
                }
            }
            else {
                $selected_terms = array($selected_terms);
            }
        }

        // Bei JetEngine-Feldern mit mehreren Werten werden manchmal JSON-Strings geliefert
        if (count($selected_terms) === 1 && is_string($selected_terms[0]) && 
            (strpos($selected_terms[0], '[') === 0 || strpos($selected_terms[0], '{') === 0)) {
            $json_decoded = json_decode($selected_terms[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json_decoded)) {
                $selected_terms = $json_decoded;
            }
        }

        // Ungültige Werte filtern und Whitespace entfernen
        $selected_terms = array_filter($selected_terms, function($term) {
            return ($term !== '' && $term !== false && $term !== null);
        });
        
        // Leere Ergebnisse nach Filterung behandeln
        if (empty($selected_terms)) {
            $delete_empty_terms = apply_filters('crocoblock_sync_delete_empty_terms', true, $post_id, $meta_field, $taxonomy);
            
            if ($delete_empty_terms) {
                $result = wp_set_object_terms($post_id, array(), $taxonomy);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Nach Filterung leere Werte: Alle Terms für Taxonomie '$taxonomy' gelöscht");
                }
                
                return array(
                    'status' => 'cleared',
                    'meta_field' => $meta_field,
                    'taxonomy' => $taxonomy,
                    'terms' => array()
                );
            } else {
                return array(
                    'status' => 'skipped',
                    'meta_field' => $meta_field,
                    'taxonomy' => $taxonomy,
                    'terms' => array()
                );
            }
        }
        
        // Alle Terms der Taxonomie abrufen für besseren Duplikat-Check
        $all_taxonomy_terms = array();
        $terms_by_name = array();
        $terms_by_slug = array();
        
        $all_terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'fields' => 'all'
        ));
        
        if (!is_wp_error($all_terms)) {
            foreach ($all_terms as $term) {
                $all_taxonomy_terms[$term->term_id] = $term;
                $terms_by_name[strtolower(trim($term->name))] = $term;
                $terms_by_slug[$term->slug] = $term;
            }
        }

        // Für die Erfassung neu erstellter Terms
        $created_terms = array();
        
        // Prüfen, ob wir bereits Terms für diesen Post haben
        $existing_post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'ids'));
        if (is_wp_error($existing_post_terms)) {
            $existing_post_terms = array();
        }
        
        // Debug-Ausgabe
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Bestehende Terms für Post $post_id, Taxonomie $taxonomy: " . print_r($existing_post_terms, true));
        }
        
        // Terms verarbeiten und sortieren
        $terms_sorted = array();
        foreach ($selected_terms as $term_id_or_slug) {
            $term = null;
            
            // Sicherstellen, dass wir einen String oder eine Zahl haben
            if (is_object($term_id_or_slug) || is_array($term_id_or_slug)) {
                // Wenn es ein Objekt ist (z.B. aus JetEngine), versuchen Sie ID/Slug zu extrahieren
                if (is_object($term_id_or_slug) && isset($term_id_or_slug->term_id)) {
                    // Es sieht aus wie ein Term-Objekt
                    $term = get_term($term_id_or_slug->term_id, $taxonomy);
                    if (!is_wp_error($term) && $term) {
                        $terms_sorted[$term->name] = $term->term_id;
                        continue;
                    }
                } else if (is_array($term_id_or_slug) && isset($term_id_or_slug['value'])) {
                    // JetEngine Array-Format mit 'value' Eigenschaft
                    $term_id_or_slug = $term_id_or_slug['value'];
                } else if (is_array($term_id_or_slug) && isset($term_id_or_slug['id'])) {
                    // JetEngine Array-Format mit 'id' Eigenschaft
                    $term_id_or_slug = $term_id_or_slug['id'];
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("Überspringe komplexen Term-Wert: " . print_r($term_id_or_slug, true));
                    }
                    continue;
                }
            }
            
            // Whitespace entfernen
            if (is_string($term_id_or_slug)) {
                $term_id_or_slug = trim($term_id_or_slug);
            }
            
            // Multi-Level-Suche für Terms:
            // 1. Wenn es eine Zahl ist, versuche es als ID zu interpretieren
            if (is_numeric($term_id_or_slug)) {
                $term_id = intval($term_id_or_slug);
                if (isset($all_taxonomy_terms[$term_id])) {
                    $term = $all_taxonomy_terms[$term_id];
                } else {
                    $term = get_term_by('id', $term_id, $taxonomy);
                }
            } else if (!empty($term_id_or_slug)) {
                // 2. Versuche zuerst, den Term über den Slug zu finden
                $clean_slug = sanitize_title($term_id_or_slug);
                if (isset($terms_by_slug[$clean_slug])) {
                    $term = $terms_by_slug[$clean_slug];
                } else {
                    $term = get_term_by('slug', sanitize_text_field($term_id_or_slug), $taxonomy);
                }
                
                // 3. Wenn kein Term gefunden wurde, versuche es mit dem Namen (case-insensitive)
                if (!$term) {
                    $clean_name = strtolower(trim($term_id_or_slug));
                    if (isset($terms_by_name[$clean_name])) {
                        $term = $terms_by_name[$clean_name];
                    } else {
                        $term = get_term_by('name', sanitize_text_field($term_id_or_slug), $taxonomy);
                    }
                }
                
                // 4. Wenn immer noch kein Term gefunden wurde, erstelle einen neuen Term
                if (!$term && !empty($term_id_or_slug)) {
                    // Prüfen, ob Term-Erstellung erlaubt ist
                    $allow_term_creation = apply_filters('crocoblock_sync_allow_term_creation', true, $term_id_or_slug, $taxonomy, $post_id);
                    
                    if ($allow_term_creation) {
                        $term_name = sanitize_text_field($term_id_or_slug);
                        
                        // Prüfen, ob bereits ein Term mit ähnlichem Namen existiert (case-insensitive)
                        $clean_name = strtolower($term_name);
                        if (isset($terms_by_name[$clean_name])) {
                            $term = $terms_by_name[$clean_name];
                        } else {
                            // Neuen Term anlegen
                            $new_term = wp_insert_term($term_name, $taxonomy);
                            
                            if (!is_wp_error($new_term)) {
                                $term = get_term_by('id', $new_term['term_id'], $taxonomy);
                                
                                // Term-Listen aktualisieren
                                $all_taxonomy_terms[$term->term_id] = $term;
                                $terms_by_name[strtolower($term->name)] = $term;
                                $terms_by_slug[$term->slug] = $term;
                                
                                // Erfassen des neu erstellten Terms
                                $created_terms[] = $term_name;
                                
                                if (defined('WP_DEBUG') && WP_DEBUG) {
                                    error_log("Neuer Term erstellt: '$term_name' (ID: {$term->term_id})");
                                }
                            } else {
                                // Prüfen, ob der Fehler wegen eines existierenden Terms aufgetreten ist
                                if ($new_term->get_error_code() === 'term_exists') {
                                    $existing_term_id = $new_term->get_error_data();
                                    if (is_array($existing_term_id) && !empty($existing_term_id['term_id'])) {
                                        $existing_term_id = $existing_term_id['term_id'];
                                    }
                                    
                                    if ($existing_term_id) {
                                        $term = get_term_by('id', $existing_term_id, $taxonomy);
                                        
                                        if (defined('WP_DEBUG') && WP_DEBUG) {
                                            error_log("Term existiert bereits: '$term_name' (ID: $existing_term_id)");
                                        }
                                    }
                                } else {
                                    if (defined('WP_DEBUG') && WP_DEBUG) {
                                        error_log("Fehler beim Erstellen des Terms '$term_name': " . $new_term->get_error_message());
                                    }
                                }
                            }
                        }
                    } else {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("Term-Erstellung nicht erlaubt für: '$term_id_or_slug' (durch Filter)");
                        }
                    }
                }
            }
            
            // Term hinzufügen, wenn er gefunden oder erstellt wurde
            if ($term && !is_wp_error($term) && isset($term->name) && isset($term->term_id)) {
                $terms_sorted[$term->name] = $term->term_id;
            }
        }

        // Wenn keine gültigen Terms gefunden wurden
        if (empty($terms_sorted)) {
            // Sollen wir bestehende Terms beibehalten oder löschen?
            $clear_terms = apply_filters('crocoblock_sync_clear_terms_on_empty', true, $post_id, $meta_field, $taxonomy);
            
            if ($clear_terms) {
                $result = wp_set_object_terms($post_id, array(), $taxonomy);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Keine gültigen Terms gefunden. Alle Terms für Taxonomie '$taxonomy' gelöscht.");
                }
                
                return array(
                    'status' => 'cleared',
                    'meta_field' => $meta_field,
                    'taxonomy' => $taxonomy,
                    'terms' => array()
                );
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Keine gültigen Terms gefunden. Bestehende Terms beibehalten (durch Filter).");
                }
                
                return array(
                    'status' => 'skipped',
                    'meta_field' => $meta_field,
                    'taxonomy' => $taxonomy,
                    'terms' => array()
                );
            }
        }

        // Sortieren und Taxonomie-Terme setzen
        ksort($terms_sorted);
        $term_ids = array_values($terms_sorted);
        
        // Bestimmen, ob Terms ersetzt oder hinzugefügt werden sollen
        $append_terms = apply_filters('crocoblock_sync_append_terms', false, $post_id, $meta_field, $taxonomy);
        
        // Wichtig: append=false bedeutet, dass bestehende Terms ersetzt werden
        // append=true bedeutet, dass die neuen Terms zu den bestehenden hinzugefügt werden
        $result = wp_set_object_terms($post_id, $term_ids, $taxonomy, $append_terms);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $operation = $append_terms ? 'hinzugefügt' : 'ersetzt';
            error_log("Terms für Taxonomie '$taxonomy' $operation: " . implode(', ', $term_ids));
        }
        
        return array(
            'status' => is_wp_error($result) ? 'error' : 'success',
            'meta_field' => $meta_field,
            'taxonomy' => $taxonomy,
            'terms' => $term_ids,
            'created_terms' => $created_terms, // Neu erstellte Terms zurückgeben
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
        
        // Mappings aus der admin.php abrufen
        $mappings = get_option('ir_sync_field_mappings', array());
        
        if (empty($mappings)) {
            wp_send_json_error('Keine Mapping-Konfigurationen gefunden. Bitte definieren Sie zuerst Mappings in den Plugin-Einstellungen.');
            return;
        }
        
        // Nur relevante Mappings für diesen Post-Typ filtern
        $relevant_mappings = array();
        foreach ($mappings as $mapping) {
            if ($mapping['active'] && $mapping['post_type'] === $post_type) {
                $relevant_mappings[] = $mapping;
            }
        }
        
        // Wenn keine relevanten Mappings gefunden wurden
        if (empty($relevant_mappings)) {
            wp_send_json_error('Keine aktiven Mapping-Konfigurationen für diesen Beitragstyp ('.$post_type.') gefunden.');
            return;
        }
        
        // Prüfen, ob Meta-Felddaten existieren, bevor wir versuchen zu synchronisieren
        $meta_field_data_exists = false;
        $available_mappings = array();
        $missing_fields = array();
        
        foreach ($relevant_mappings as $mapping) {
            // Prüfen, ob das Meta-Feld existiert (es wurde für diesen Post definiert)
            if (metadata_exists('post', $post_id, $mapping['meta_field'])) {
                // Feldwert abrufen
                $field_value = get_post_meta($post_id, $mapping['meta_field'], true);
                
                // Prüfen, ob das Feld einen Wert hat, der synchronisiert werden kann
                if (!empty($field_value) || $field_value === '0' || $field_value === 0) {
                    $meta_field_data_exists = true;
                    $available_mappings[] = $mapping;
                } else {
                    // Feld existiert, hat aber keinen Wert zum Synchronisieren
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("Feld '{$mapping['meta_field']}' existiert, hat aber keinen Wert zum Synchronisieren.");
                    }
                }
            } else {
                // Feld existiert nicht für diesen Post
                $missing_fields[] = $mapping['meta_field'];
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Feld '{$mapping['meta_field']}' existiert nicht für Post ID $post_id.");
                }
            }
        }
        
        // Wenn keine Metadaten zum Synchronisieren vorhanden sind
        if (!$meta_field_data_exists) {
            if (!empty($missing_fields)) {
                wp_send_json_error('Die folgenden Meta-Felder wurden nicht gefunden oder sind leer: ' . implode(', ', $missing_fields));
            } else {
                wp_send_json_error('Alle konfigurierten Meta-Felder sind leer. Nichts zu synchronisieren.');
            }
            return;
        }
        
        // Jetzt nur die Mappings verarbeiten, für die Daten vorhanden sind
        $results = array();
        foreach ($available_mappings as $mapping) {
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
        $created_terms = array(); // Neu erstellte Terms sammeln
        $updated_taxonomies = array(); // Sammelt alle aktualisierten Taxonomien mit ihren Terms

        foreach ($results as $field => $result) {
            if ($result === false) {
                $error_messages[] = "Feld $field: Konnte nicht synchronisiert werden.";
            } else if ($result['status'] === 'error') {
                $error_messages[] = "Feld $field: " . $result['error'];
            } else {
                $success_count++;
                $term_count += count($result['terms']);
                
                // Neu erstellte Terms erfassen
                if (isset($result['created_terms']) && !empty($result['created_terms'])) {
                    foreach ($result['created_terms'] as $created_term) {
                        $created_terms[] = $created_term;
                    }
                }
                
                // Taxonomie-Informationen für das Frontend sammeln
                if (isset($result['taxonomy']) && !empty($result['taxonomy'])) {
                    $taxonomy = $result['taxonomy'];
                    
                    // Alle Terms für diese Taxonomie abrufen
                    $all_terms = get_terms(array(
                        'taxonomy' => $taxonomy,
                        'hide_empty' => false,
                        'fields' => 'all'
                    ));
                    
                    if (!is_wp_error($all_terms) && !empty($all_terms)) {
                        $terms_data = array();
                        
                        foreach ($all_terms as $term) {
                            $terms_data[] = array(
                                'id' => $term->term_id,
                                'name' => $term->name,
                                'slug' => $term->slug
                            );
                        }
                        
                        // Alphabetisch nach Name sortieren
                        usort($terms_data, function($a, $b) {
                            return strnatcasecmp($a['name'], $b['name']);
                        });
                        
                        $updated_taxonomies[$taxonomy] = $terms_data;
                    }
                }
            }
        }
        
        // Wenn Fehler aufgetreten sind
        if (!empty($error_messages)) {
            wp_send_json_error(implode('<br>', $error_messages));
            return;
        }
        
        // Wenn keine Felder erfolgreich synchronisiert wurden
        if ($success_count === 0) {
            wp_send_json_error('Keine Felder konnten synchronisiert werden.');
            return;
        }
        
        // Rekalkuliere die Gesamtzahl der tatsächlich synchronisierten Terms
        $term_count = 0;
        
        // Die Anzahl der Terms anhand der aktualisierten Taxonomie-Daten ermitteln
        foreach ($updated_taxonomies as $taxonomy => $terms_data) {
            // Zähle, wie viele Terms für dieses Post tatsächlich gesetzt wurden
            $taxonomy_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'ids'));
            if (!is_wp_error($taxonomy_terms)) {
                $term_count += count($taxonomy_terms);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Taxonomie $taxonomy: " . count($taxonomy_terms) . " Terms für Post $post_id");
                }
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Insgesamt $term_count Terms für Post $post_id in allen Taxonomien");
        }
        
        // Prüfen, ob mehrere Terme gefunden wurden und ob dies erlaubt ist
        $multiple_terms_detected = false;
        foreach ($results as $field => $result) {
            if ($result && 
                isset($result['terms']) && 
                count($result['terms']) >= 2) {
                
                // Finde das entsprechende Mapping für dieses Feld
                $mapping_found = false;
                foreach ($available_mappings as $mapping) {
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
        
        // Füge eine Nachricht über neu erstellte Terms hinzu, wenn vorhanden
        if (!empty($created_terms)) {
            $terms_created_message = isset($messages['terms_created']) 
                ? sprintf($messages['terms_created'], implode(', ', $created_terms))
                : sprintf('Neue Terms erstellt: %s', implode(', ', $created_terms));
                
            $success_message .= ' ' . $terms_created_message;
        }
        
        wp_send_json_success(array(
            'message' => $success_message,
            'count' => $term_count,
            'fields' => array_keys($results),
            'show_warning' => $multiple_terms_detected,
            'created_terms' => $created_terms, // Neu erstellte Terms an die Antwort anhängen
            'updated_taxonomies' => $updated_taxonomies // Vollständige Liste aller Terms für betroffene Taxonomien
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
            'sync_error' => 'Synchronisation fehlgeschlagen. Bitte versuchen Sie es erneut.',
            'terms_created' => 'Neue Terms erstellt: %s' // Neue Nachricht für erstellte Terms
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