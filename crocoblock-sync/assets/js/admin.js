/**
 * Admin-Skript für IR Tours Sync Einstellungsseite
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Mapping-Zeilen-Verwaltung
        const mappingTable = $('#ir-mapping-table');
        
        // Mapping hinzufügen
        $('.add-mapping').on('click', function() {
            const template = $('#mapping-row-template').html();
            const tableBody = $('.ir-sync-mappings-table tbody');
            const newId = tableBody.find('tr').length + 1;
            
            // Template-Platzhalter ersetzen
            const newRow = template.replace(/\{\{id\}\}/g, newId);
            
            // Neue Zeile anhängen
            tableBody.append(newRow);
        });
        
        // Mapping entfernen (dynamisch hinzugefügte Elemente)
        $(document).on('click', '.remove-mapping', function() {
            const tableBody = $('.ir-sync-mappings-table tbody');
            const rowCount = tableBody.find('tr').length;
            
            if (rowCount > 1) {
                $(this).closest('tr').remove();
            } else {
                alert('Sie müssen mindestens ein Mapping behalten.');
            }
        });
        
        // Post-Typen und Taxonomien automatisch vervollständigen
        function initAutocomplete() {
            // Liste aller bekannten Taxonomien und Post-Typen abrufen
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ir_get_taxonomies_post_types'
                },
                success: function(response) {
                    if (response.success && response.data) {
                        const taxonomies = response.data.taxonomies || [];
                        const postTypes = response.data.post_types || [];
                        
                        // Taxonomien-Autocomplete
                        $('input[name$="[taxonomy]"]').autocomplete({
                            source: taxonomies,
                            minLength: 2
                        });
                        
                        // Post-Typen-Autocomplete
                        $('input[name$="[post_type]"]').autocomplete({
                            source: postTypes,
                            minLength: 2
                        });
                    }
                }
            });
        }
        
        // Wenn jQuery UI Autocomplete verfügbar ist
        if ($.fn.autocomplete) {
            initAutocomplete();
        }
        
        // Formularvalidierung
        $('form').on('submit', function(e) {
            let isValid = true;
            
            // Prüfen, ob erforderliche Felder ausgefüllt sind
            $('.ir-sync-mappings-table tbody tr').each(function() {
                const metaField = $(this).find('input[name$="[meta_field]"]').val();
                const taxonomy = $(this).find('input[name$="[taxonomy]"]').val();
                const postType = $(this).find('input[name$="[post_type]"]').val();
                
                if (!metaField || !taxonomy || !postType) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                alert('Bitte füllen Sie alle erforderlichen Felder aus (Meta-Feld, Taxonomie, Post-Typ).');
                e.preventDefault();
                return false;
            }
            
            return true;
        });
        
        // Tooltips für Hilfe-Hinweise
        $('.ir-help-icon').hover(
            function() {
                $(this).next('.ir-tooltip').show();
            },
            function() {
                $(this).next('.ir-tooltip').hide();
            }
        );
    });
})(jQuery);