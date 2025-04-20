/**
 * Admin-JavaScript für Crocoblock Sync
 */
(function($) {
    'use strict';
    
    // Debug-Funktion
    function debugLog(...args) {
        if (typeof irSyncAdmin !== 'undefined' && irSyncAdmin.debug_mode) {
            console.log('[Crocoblock Sync Debug]', ...args);
        }
    }
    
    // Debug-Fehler-Funktion
    function debugError(...args) {
        if (typeof irSyncAdmin !== 'undefined' && irSyncAdmin.debug_mode) {
            console.error('[Crocoblock Sync Error]', ...args);
        }
    }
    
    $(document).ready(function() {
        debugLog('Admin JS geladen');
        
        // Debug-Informationen ausgeben
        if (typeof irSyncAdmin !== 'undefined' && irSyncAdmin.debug_mode) {
            console.group('Crocoblock Sync Debug-Informationen');
            console.log('AJAX URL:', irSyncAdmin.ajaxurl);
            console.log('Plugin-Version:', irSyncAdmin.version);
            console.log('Nonce:', irSyncAdmin.nonce ? 'Vorhanden' : 'Fehlt');
            console.log('Post-Typen:', window.irSyncPostTypes || 'Nicht definiert');
            console.groupEnd();
        }
        
        // Select2 initialisieren
        initSelect2();
        
        // "Neues Mapping hinzufügen"-Button
        $('#add-mapping').on('click', function() {
            debugLog('Neues Mapping hinzufügen');
            addNewMapping();
            
            // Nach dem Hinzufügen Select2 für die neue Zeile initialisieren
            setTimeout(function() {
                initSelect2ForRow($('#ir-sync-mappings-table tbody tr:last-child'));
            }, 100);
        });
        
        // "Entfernen"-Button für Mappings
        $('#ir-sync-mappings-table').on('click', '.remove-mapping', function() {
            const $row = $(this).closest('tr');
            const rowIndex = $row.index();
            debugLog('Mapping entfernen:', rowIndex);
            $row.remove();
        });
        
        // Änderung beim Post-Typ-Dropdown behandeln
        $('#ir-sync-mappings-table').on('change', '.post-type-select', function() {
            const row = $(this).closest('tr');
            const rowIndex = row.index();
            const postType = $(this).val();
            
            debugLog('Post-Typ geändert in Zeile', rowIndex, 'zu', postType);
            
            // Die Select2-Dropdowns aktualisieren
            updateFieldDropdowns(row, postType);
        });
        
        // Änderungen bei Meta-Feld und Taxonomie überwachen
        $('#ir-sync-mappings-table').on('change', '.meta-field-select', function() {
            const value = $(this).val();
            const row = $(this).closest('tr');
            const rowIndex = row.index();
            debugLog('Meta-Feld geändert in Zeile', rowIndex, 'zu', value);
        });
        
        $('#ir-sync-mappings-table').on('change', '.taxonomy-select', function() {
            const value = $(this).val();
            const row = $(this).closest('tr');
            const rowIndex = row.index();
            debugLog('Taxonomie geändert in Zeile', rowIndex, 'zu', value);
        });
        
        // "Mehrere erlauben" Checkbox überwachen
        $('#ir-sync-mappings-table').on('change', 'input[name*="[allow_multiple]"]', function() {
            const checked = $(this).is(':checked');
            const row = $(this).closest('tr');
            const rowIndex = row.index();
            debugLog('Mehrere erlauben in Zeile', rowIndex, 'geändert zu', checked ? 'Ja' : 'Nein');
        });
    });
    
    /**
     * Initialisiert Select2 für alle vorhandenen Zeilen
     */
    function initSelect2() {
        debugLog('Initialisiere Select2 für alle Zeilen');
        
        try {
            // Für jede Zeile in der Tabelle
            $('#ir-sync-mappings-table tbody tr').each(function(index) {
                debugLog('Initialisiere Zeile', index);
                initSelect2ForRow($(this));
            });
        } catch (error) {
            debugError('Fehler bei der Initialisierung von Select2:', error);
        }
    }
    
    /**
     * Initialisiert Select2 für eine bestimmte Zeile
     */
    function initSelect2ForRow($row) {
        const rowIndex = $row.index();
        const postType = $row.find('.post-type-select').val();
        
        debugLog('Initialisiere Select2 für Zeile', rowIndex, 'mit Post-Typ', postType);
        
        try {
            // Post-Type-Select als reguläres Select2 initialisieren
            $row.find('.post-type-select').select2({
                minimumResultsForSearch: 10 // Suchfeld ab 10 Einträgen anzeigen
            });
            
            // Meta-Feld- und Taxonomie-Selects initialisieren
            updateFieldDropdowns($row, postType);
        } catch (error) {
            debugError('Fehler bei der Initialisierung von Select2 für Zeile', rowIndex, ':', error);
        }
    }
    
    /**
     * Aktualisiert die Meta-Feld- und Taxonomie-Dropdowns basierend auf dem Post-Typ
     */
    function updateFieldDropdowns($row, postType) {
        const rowIndex = $row.index();
        debugLog('Aktualisiere Dropdowns für Zeile', rowIndex, 'mit Post-Typ', postType);
        
        // Aktuelle Werte merken
        const currentMetaField = $row.find('.meta-field-select').val();
        const currentTaxonomy = $row.find('.taxonomy-select').val();
        
        debugLog('Aktuelle Werte:', { metaField: currentMetaField, taxonomy: currentTaxonomy });
        
        // Meta-Feld-Select aktualisieren
        initMetaFieldSelect($row, postType, currentMetaField);
        
        // Taxonomie-Select aktualisieren
        initTaxonomySelect($row, postType, currentTaxonomy);
    }
    
    /**
     * Initialisiert das Meta-Feld-Select
     */
    function initMetaFieldSelect($row, postType, currentValue) {
        const rowIndex = $row.index();
        const $select = $row.find('.meta-field-select');
        
        debugLog('Initialisiere Meta-Feld-Select für Zeile', rowIndex, { postType, currentValue });
        
        try {
            // Select2 zerstören, falls bereits initialisiert
            if ($select.hasClass('select2-hidden-accessible')) {
                debugLog('Zerstöre vorhandenes Select2 für Meta-Feld');
                $select.select2('destroy');
            }
            
            // Select leeren
            $select.empty();
            
            // Lade-Option hinzufügen
            $select.append(new Option('Lade Felder...', '', false, false));
            
            // Select2 initialisieren
            $select.select2({
                placeholder: 'Meta-Feld auswählen oder eingeben',
                allowClear: true,
                tags: true
            });
            
            // Meta-Felder laden
            debugLog('Lade Meta-Felder via AJAX für Post-Typ', postType);
            
            $.ajax({
                url: irSyncAdmin.ajaxurl,
                type: 'GET',
                data: {
                    action: 'ir_get_field_names',
                    nonce: irSyncAdmin.nonce,
                    post_type: postType,
                    search_type: 'meta_field'
                },
                success: function(response) {
                    // Select leeren
                    $select.empty();
                    
                    if (response.success && response.data) {
                        debugLog('Meta-Felder geladen:', response.data.length, 'Felder gefunden');
                        
                        // Optionen hinzufügen
                        $.each(response.data, function(index, field) {
                            $select.append(new Option(field.text, field.id, false, field.id === currentValue));
                        });
                        
                        // Wenn der aktuelle Wert nicht in den Optionen ist, füge ihn hinzu
                        if (currentValue && !$select.find('option[value="' + currentValue + '"]').length) {
                            debugLog('Aktueller Wert nicht in Optionen gefunden, füge hinzu:', currentValue);
                            $select.append(new Option(currentValue, currentValue, false, true));
                        }
                    } else {
                        debugError('Fehler beim Laden der Meta-Felder:', response);
                    }
                    
                    // Select2 aktualisieren
                    $select.trigger('change');
                },
                error: function(xhr, status, error) {
                    debugError('AJAX-Fehler beim Laden der Meta-Felder:', { xhr, status, error });
                    
                    $select.empty();
                    $select.append(new Option('Fehler beim Laden', '', false, false));
                }
            });
        } catch (error) {
            debugError('Fehler bei der Initialisierung des Meta-Feld-Selects:', error);
        }
    }
    
    /**
     * Initialisiert das Taxonomie-Select
     */
    function initTaxonomySelect($row, postType, currentValue) {
        const rowIndex = $row.index();
        const $select = $row.find('.taxonomy-select');
        
        debugLog('Initialisiere Taxonomie-Select für Zeile', rowIndex, { postType, currentValue });
        
        try {
            // Select2 zerstören, falls bereits initialisiert
            if ($select.hasClass('select2-hidden-accessible')) {
                debugLog('Zerstöre vorhandenes Select2 für Taxonomie');
                $select.select2('destroy');
            }
            
            // Select leeren
            $select.empty();
            
            // Lade-Option hinzufügen
            $select.append(new Option('Lade Taxonomien...', '', false, false));
            
            // Select2 initialisieren
            $select.select2({
                placeholder: 'Taxonomie auswählen oder eingeben',
                allowClear: true,
                tags: true
            });
            
            // Taxonomien laden
            debugLog('Lade Taxonomien via AJAX für Post-Typ', postType);
            
            $.ajax({
                url: irSyncAdmin.ajaxurl,
                type: 'GET',
                data: {
                    action: 'ir_get_field_names',
                    nonce: irSyncAdmin.nonce,
                    post_type: postType,
                    search_type: 'taxonomy'
                },
                success: function(response) {
                    // Select leeren
                    $select.empty();
                    
                    if (response.success && response.data) {
                        debugLog('Taxonomien geladen:', response.data.length, 'Taxonomien gefunden');
                        
                        // Optionen hinzufügen
                        $.each(response.data, function(index, field) {
                            $select.append(new Option(field.text, field.id, false, field.id === currentValue));
                        });
                        
                        // Wenn der aktuelle Wert nicht in den Optionen ist, füge ihn hinzu
                        if (currentValue && !$select.find('option[value="' + currentValue + '"]').length) {
                            debugLog('Aktueller Wert nicht in Optionen gefunden, füge hinzu:', currentValue);
                            $select.append(new Option(currentValue, currentValue, false, true));
                        }
                    } else {
                        debugError('Fehler beim Laden der Taxonomien:', response);
                    }
                    
                    // Select2 aktualisieren
                    $select.trigger('change');
                },
                error: function(xhr, status, error) {
                    debugError('AJAX-Fehler beim Laden der Taxonomien:', { xhr, status, error });
                    
                    $select.empty();
                    $select.append(new Option('Fehler beim Laden', '', false, false));
                }
            });
        } catch (error) {
            debugError('Fehler bei der Initialisierung des Taxonomie-Selects:', error);
        }
    }
    
    /**
     * Fügt eine neue Mapping-Zeile zur Tabelle hinzu
     */
    function addNewMapping() {
        debugLog('Füge neues Mapping hinzu');
        
        try {
            // Höchste ID ermitteln
            let maxId = 0;
            $('.mapping-row').each(function() {
                const idMatch = $(this).find('input').first().attr('name').match(/\[(\d+)\]/);
                if (idMatch && idMatch[1]) {
                    const id = parseInt(idMatch[1], 10);
                    if (id > maxId) {
                        maxId = id;
                    }
                }
            });
            
            // Neue ID berechnen
            const newId = maxId + 1;
            debugLog('Neue Mapping-ID:', newId);
            
            // Template abrufen und anpassen
            const template = $('#mapping-row-template').html();
            const newRow = template.replace(/\{\{id\}\}/g, newId);
            
            // Neue Zeile zur Tabelle hinzufügen
            $('#ir-sync-mappings-table tbody').append(newRow);
        } catch (error) {
            debugError('Fehler beim Hinzufügen eines neuen Mappings:', error);
        }
    }
    
})(jQuery);