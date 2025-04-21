/**
 * Editor-Skript für IR Tours Sync
 */
(function($) {
    'use strict';
    
    // Warten, bis DOM geladen ist
    $(document).ready(function() {
        // Variablen initialisieren
        let syncWasClicked = false;
        let isSyncing = false;
        let headerButtonInserted = false; // Header-Button-Tracking
        
        // Debug-Ausgabe
        console.log('IR Tours Sync - Debug Info:');
        console.log('irSyncData vollständig:', irSyncData);
        
        // Debug-Ausgabe der Originalnachrichten
        console.log('Originale Nachrichten aus irSyncData:', irSyncData.messages);
        
        // Nachrichten aus den Plugin-Einstellungen mit Fallback-Werten
        let buttonText = 'Synchronisieren & Speichern';
        let multipleThemesMessage = 'Sie haben 2 oder mehr Reisethemen gewählt. Sind Sie sicher, dass Sie speichern möchten?';
        let syncReminderMessage = 'Sie haben vergessen zu synchronisieren. Bitte drücken Sie zuerst den Synchronisations-Button. Danke.';
        let syncSuccessMessage = 'Felder erfolgreich synchronisiert. (%d Terme gesetzt)';
        let syncErrorMessage = 'Synchronisation fehlgeschlagen. Bitte versuchen Sie es erneut.';
        
        // Nachrichten direkt mit Werten aus irSyncData überschreiben, wenn verfügbar
        if (irSyncData && irSyncData.messages) {
            if (irSyncData.messages.sync_button && irSyncData.messages.sync_button.trim() !== '') {
                buttonText = irSyncData.messages.sync_button;
                console.log('Button-Text überschrieben mit:', buttonText);
            }
            
            if (irSyncData.messages.multiple_themes && irSyncData.messages.multiple_themes.trim() !== '') {
                multipleThemesMessage = irSyncData.messages.multiple_themes;
                console.log('Multiple-Themes-Nachricht überschrieben mit:', multipleThemesMessage);
            }
            
            if (irSyncData.messages.sync_reminder && irSyncData.messages.sync_reminder.trim() !== '') {
                syncReminderMessage = irSyncData.messages.sync_reminder;
                console.log('Sync-Reminder-Nachricht überschrieben mit:', syncReminderMessage);
            }
            
            if (irSyncData.messages.sync_success && irSyncData.messages.sync_success.trim() !== '') {
                syncSuccessMessage = irSyncData.messages.sync_success;
                console.log('Sync-Success-Nachricht überschrieben mit:', syncSuccessMessage);
            }
            
            if (irSyncData.messages.sync_error && irSyncData.messages.sync_error.trim() !== '') {
                syncErrorMessage = irSyncData.messages.sync_error;
                console.log('Sync-Error-Nachricht überschrieben mit:', syncErrorMessage);
            }
        }
        
        console.log('Verwendete Nachrichten:');
        console.log('Button-Text:', buttonText);
        console.log('Multiple Themes:', multipleThemesMessage);
        console.log('Sync Reminder:', syncReminderMessage);
        console.log('Sync Success:', syncSuccessMessage);
        console.log('Sync Error:', syncErrorMessage);
        
        // Hilfsfunktion: Prüft, ob mehrere Terme ausgewählt sind, obwohl nicht erlaubt
        function checkMultipleTerms() {
            // Prüfen für alle Mappings
            if (!irSyncData.mappings) {
                console.log('Keine Mappings gefunden in irSyncData');
                return false;
            }
            
            console.log('Verfügbare Mappings:', irSyncData.mappings);
            
            for (const id in irSyncData.mappings) {
                const mapping = irSyncData.mappings[id];
                
                if (mapping.active && (!mapping.allow_multiple || mapping.allow_multiple === false)) {
                    const meta_field = mapping.meta_field;
                    console.log('Prüfe Meta-Feld:', meta_field);
                    
                    // Verschiedene Checkbox-Formate überprüfen
                    const jetCheckboxes = $(`input[name^="${meta_field}["][value="true"]:checked`).length;
                    const standardSelect = $(`select[name^="${meta_field}"] option:selected`).length > 1 ? 
                                         $(`select[name^="${meta_field}"] option:selected`).length : 0;
                    const regularCheckboxes = $(`input[name^="${meta_field}"]:checked`).length;
                    const jetListItems = $(`.jet-engine-checkbox-list__input[name^="${meta_field}"]:checked`).length;
                    
                    // Gesamtzahl der ausgewählten Elemente
                    const totalSelected = jetCheckboxes + standardSelect + regularCheckboxes + jetListItems;
                    
                    console.log('Ausgewählte Elemente für', meta_field, ':', totalSelected);
                    
                    if (totalSelected >= 2) {
                        console.log('Mehrere Elemente gefunden, aber nicht erlaubt bei:', meta_field);
                        return true; // Mehrere nicht erlaubte Terme gefunden
                    }
                }
            }
            
            return false;
        }
        
        // 1. Warnung beim Speichern ohne vorherige Synchronisation
        $(document).on('click', '.editor-post-publish-button, .editor-post-save-draft, .editor-post-publish-panel__toggle, .editor-post-publish-button__button', function(e) {
            console.log('Speichern-Button geklickt');
            
            // Prüfen, ob Synchronisation notwendig ist
            if (!syncWasClicked) {
                // Prüfen, ob Meta-Felder vorhanden sind, die synchronisiert werden müssen
                let fieldsToSync = false;
                
                // Für jedes aktive Mapping prüfen
                if (irSyncData.mappings) {
                    $.each(irSyncData.mappings, function(id, mapping) {
                        if (mapping.active && mapping.post_type === irSyncData.postType) {
                            // Für JetEngine-Format (Checkboxen)
                            const jetCheckboxes = $(`input[name^="${mapping.meta_field}["][value="true"]:checked`);
                            // Für Standard-Format (Select/Multiselect)
                            const standardFields = $(`select[name^="${mapping.meta_field}"], input[name^="${mapping.meta_field}"]:checked`);
                            
                            if (jetCheckboxes.length > 0 || standardFields.length > 0) {
                                fieldsToSync = true;
                                return false; // Schleife abbrechen
                            }
                        }
                    });
                } else {
                    // Fallback zu Standard-Feldern
                    const jetCheckboxes = $('input[name^="reisethemen_meta["][value="true"]:checked');
                    if (jetCheckboxes.length > 0) {
                        fieldsToSync = true;
                    }
                }
                
                // Nur warnen, wenn tatsächlich Felder zu synchronisieren sind
                if (fieldsToSync) {
                    console.log('Felder zum Synchronisieren gefunden, zeige Meldung:', syncReminderMessage);
                    
                    // Warum das Speichern verhindert werden muss:
                    alert(syncReminderMessage);
                    
                    // Wichtig: Alle Events verhindern
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    
                    // Verhindern, dass Gutenberg speichert
                    if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch && wp.data.dispatch('core/editor')) {
                        const saveOperations = ['savePost', 'saveEntityRecord', 'validateSaveCall'];
                        const originalDispatch = wp.data.dispatch;
                        
                        // Temporär die Speicher-Methoden überschreiben
                        wp.data.dispatch = function(storeName) {
                            const store = originalDispatch(storeName);
                            if (storeName === 'core/editor' || storeName === 'core') {
                                const originalStore = { ...store };
                                
                                // Überschreibe die Speicher-Methoden
                                saveOperations.forEach(op => {
                                    if (store[op]) {
                                        store[op] = function() {
                                            console.log('Speichervorgang abgebrochen wegen fehlender Synchronisation');
                                            return Promise.resolve({ ok: false });
                                        };
                                    }
                                });
                                
                                // Nach kurzer Zeit wiederherstellen
                                setTimeout(() => {
                                    wp.data.dispatch = originalDispatch;
                                }, 500);
                                
                                return store;
                            }
                            return store;
                        };
                    }
                    
                    return false;
                }
            }
            
            // Überprüfen, ob mehrere Terme ausgewählt wurden
            const hasMultipleTerms = checkMultipleTerms();
            console.log('Hat mehrere nicht erlaubte Terme:', hasMultipleTerms);
            
            // Prüfen, ob mehrere Terme ausgewählt wurden
            if (hasMultipleTerms) {
                console.log('Mehrere Terme erkannt, zeige Warnung');
                if (!confirm(multipleThemesMessage)) {
                    console.log('Benutzer hat abgebrochen');
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    return false;
                } else {
                    console.log('Benutzer hat bestätigt, fahre fort mit Speichern');
                }
            }
        });
        
        // Hilfsfunktion: Finde das Reisethemen-Mapping
        function findReisethemenMapping() {
            if (!irSyncData.mappings) {
                return null;
            }
            
            let result = null;
            $.each(irSyncData.mappings, function(id, mapping) {
                if (mapping.active && mapping.taxonomy === 'reisethemen') {
                    result = mapping;
                    return false; // Schleife abbrechen
                }
            });
            
            return result;
        }
        
        // 2. Sync-Button und Feedback erzeugen
        function createSyncButton() {
            const syncButton = $(`<button type="button" class="components-button is-primary ir-sync-button header-sync-button">${buttonText}</button>`);
            const syncStatus = $('<span style="margin-left:0.75em;font-weight:normal;font-size:0.9em;" class="ir-sync-status"></span>');
            
            // Button-Funktion: Synchronisieren und speichern
            syncButton.on('click', function() {
                console.log('Sync-Button wurde geklickt');
                
                // Verhindert Doppelklicks
                if (isSyncing) {
                    console.log('Sync läuft bereits, doppelter Klick ignoriert');
                    return;
                }
                
                isSyncing = true;
                syncWasClicked = true;
                
                // Post-ID aus Gutenberg oder Elementor holen
                let postId;
                if (typeof wp !== 'undefined' && wp.data && wp.data.select && wp.data.select('core/editor')) {
                    postId = wp.data.select('core/editor').getCurrentPostId();
                } else if (typeof elementor !== 'undefined' && elementor.config && elementor.config.document) {
                    postId = elementor.config.document.id;
                } else {
                    // Versuche, die Post-ID aus der URL zu extrahieren
                    const urlParams = new URLSearchParams(window.location.search);
                    postId = urlParams.get('post');
                }
                
                console.log('Post-ID für Synchronisation:', postId);
                
                if (!postId) {
                    syncStatus.text('Fehler: Konnte Post-ID nicht ermitteln.');
                    console.error('Fehler: Keine Post-ID gefunden');
                    isSyncing = false;
                    return;
                }
                
                // Button-Status aktualisieren
                const btn = $(this);
                btn.prop('disabled', true).text('⏳ Synchronisiere...');
                syncStatus.text('');
                
                console.log('AJAX-Anfrage wird gesendet...');
                // AJAX-Aufruf für die Synchronisation
                $.post(irSyncData.ajaxurl, {
                    action: 'ir_manual_sync',
                    post_id: postId,
                    nonce: irSyncData.nonce
                }).done(function(response) {
                    console.log('AJAX-Antwort erhalten:', response);
                    
                    if (response.success) {
                        console.log('Synchronisation erfolgreich');
                        
                        // Erfolgstext formatieren und anzeigen
                        let termCount = 0;
                        if (response.data && typeof response.data.count !== 'undefined') {
                            termCount = response.data.count;
                        }
                        
                        // Prüfen, ob eine Warnung angezeigt werden soll
                        let shouldProceed = true;
                        if (response.data && response.data.show_warning) {
                            console.log('Mehrere Terme erkannt, zeige Warnung');
                            shouldProceed = confirm(multipleThemesMessage);
                            
                            if (!shouldProceed) {
                                console.log('Benutzer hat Speichern abgebrochen');
                                btn.prop('disabled', false).text(buttonText);
                                isSyncing = false;
                                syncStatus.text('Synchronisation abgebrochen.');
                                return;
                            }
                        }
                        
                        btn.text('✅ Synchronisiert – speichere...');
                        
                        // Hier wird %d durch die tatsächliche Anzahl ersetzt
                        let successMessage = syncSuccessMessage;
                        successMessage = successMessage.replace(/%d/g, termCount);
                        
                        console.log('Formatierte Erfolgsmeldung:', successMessage);
                        syncStatus.text('Letzte Synchronisation: erfolgreich');
                        
                        // Speichern je nach Editor
                        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch && wp.data.dispatch('core/editor')) {
                            // Gutenberg
                            try {
                                console.log('Versuche, in Gutenberg zu speichern...');
                                
                                wp.data.dispatch('core/editor').savePost().then(() => {
                                    btn.prop('disabled', false).text(buttonText);
                                    
                                    // Erfolgsmeldung anzeigen
                                    alert(successMessage);
                                    
                                    setTimeout(() => { isSyncing = false; }, 1000);
                                }).catch(error => {
                                    console.error('Speichern fehlgeschlagen:', error);
                                    syncStatus.text('Fehler beim Speichern');
                                    alert('Fehler beim Speichern: ' + error.message);
                                    btn.prop('disabled', false).text(buttonText);
                                    isSyncing = false;
                                });
                            } catch (error) {
                                console.error('Fehler beim Speichern:', error);
                                syncStatus.text('Fehler beim Speichern: ' + error.message);
                                alert('Fehler beim Speichern-Versuch: ' + error.message);
                                btn.prop('disabled', false).text(buttonText);
                                isSyncing = false;
                            }
                        } else if (typeof elementor !== 'undefined' && elementor.saver) {
                            // Elementor
                            try {
                                console.log('Versuche, in Elementor zu speichern...');
                                
                                elementor.saver.update().then(() => {
                                    btn.prop('disabled', false).text(buttonText);
                                    
                                    // Erfolgsmeldung anzeigen
                                    alert(successMessage);
                                    
                                    setTimeout(() => { isSyncing = false; }, 1000);
                                }).catch(error => {
                                    console.error('Speichern fehlgeschlagen:', error);
                                    syncStatus.text('Fehler beim Speichern');
                                    alert('Fehler beim Speichern: ' + error.message);
                                    btn.prop('disabled', false).text(buttonText);
                                    isSyncing = false;
                                });
                            } catch (error) {
                                console.error('Fehler beim Speichern:', error);
                                syncStatus.text('Fehler beim Speichern: ' + error.message);
                                alert('Fehler beim Speichern-Versuch: ' + error.message);
                                btn.prop('disabled', false).text(buttonText);
                                isSyncing = false;
                            }
                        } else {
                            // Fallback: Manuelles Speichern durch den Benutzer
                            console.log('Kein Editor erkannt, manuelle Speichermeldung');
                            alert(successMessage + '\n\nBitte speichern Sie den Beitrag jetzt manuell.');
                            btn.prop('disabled', false).text(buttonText);
                            isSyncing = false;
                        }
                    } else {
                        console.error('Synchronisation fehlgeschlagen:', response.data);
                        
                        btn.text('❌ Fehler');
                        const errorMsg = (response.data && typeof response.data === 'string') ? 
                            response.data : syncErrorMessage;
                            
                        syncStatus.text('Letzte Synchronisation: fehlgeschlagen');
                        alert('Fehler bei der Synchronisation: ' + errorMsg);
                        
                        setTimeout(() => {
                            btn.prop('disabled', false).text(buttonText);
                            isSyncing = false;
                        }, 3000);
                    }
                }).fail(function(xhr, status, error) {
                    console.error('AJAX-Anfrage fehlgeschlagen:', error);
                    
                    btn.text('❌ Fehler');
                    syncStatus.text('Letzte Synchronisation: AJAX-Fehler');
                    alert('AJAX-Fehler bei der Synchronisation: ' + error);
                    
                    setTimeout(() => {
                        btn.prop('disabled', false).text(buttonText);
                        isSyncing = false;
                    }, 3000);
                });
            });
            
            return { button: syncButton, status: syncStatus };
        }
        
        // 3. Button im Header einfügen - GEZIELT OBEN RECHTS
        function insertHeaderButton() {
            // Wenn bereits ein Button im Header existiert, nicht nochmal einfügen
            if (headerButtonInserted || $('.edit-post-header .ir-sync-button').length > 0) {
                return;
            }
            
            // Button erstellen
            const { button, status } = createSyncButton();
            
            // GENAU DIESE SELEKTOREN PRÜFEN: OBEN RECHTS IM HEADER
            // In WordPress wird oben rechts hauptsächlich durch diese Selektoren repräsentiert
            const rightHeaderSelectors = [
                '.edit-post-header__settings',            // Neue WP Versionen, oben rechts
                '.block-editor-editor-skeleton__header .components-button:last',  // Alternative, oben rechts
                '.edit-post-header > div:last-child',     // Fallback für ältere Versionen
                '.interface-interface-skeleton__actions', // WP 5.5+, oben rechts
                '.edit-post-header-toolbar__right'        // Alternativer Bereich, oben rechts
            ];
            
            // Rechten Header-Bereich finden und Button einfügen
            let inserted = false;
            for (let i = 0; i < rightHeaderSelectors.length && !inserted; i++) {
                const rightHeader = $(rightHeaderSelectors[i]);
                if (rightHeader.length) {
                    // Wenn ein Element gefunden wurde, füge den Button ein
                    const wrapper = $('<div class="ir-sync-button-added header-button" style="display:flex; align-items:center; margin-right:10px;"></div>');
                    wrapper.append(button).append(status);
                    
                    if (rightHeaderSelectors[i] === '.edit-post-header__settings') {
                        // WICHTIG: Zu Beginn einfügen (im rechten Bereich = VOR dem Speichern-Button)
                        rightHeader.prepend(wrapper);
                    } else {
                        // Bei anderen Selektoren als erstes Element einfügen
                        rightHeader.prepend(wrapper);
                    }
                    
                    inserted = true;
                    headerButtonInserted = true;
                    
                    console.log('IR Tours Sync - Header-Button eingefügt in:', rightHeaderSelectors[i]);
                }
            }
            
            // Wenn kein passender Selektor gefunden wurde, versuche einen direkteren Ansatz
            if (!inserted) {
                // Suche den Speichern-Button und füge unseren Button davor ein
                const saveButton = $('.editor-post-publish-button__button, .editor-post-save-draft');
                if (saveButton.length) {
                    const wrapper = $('<div class="ir-sync-button-added header-button" style="display:flex; align-items:center; margin-right:10px; margin-left:0;"></div>');
                    wrapper.append(button).append(status);
                    
                    // Vor dem Speichern-Button einfügen
                    saveButton.parent().before(wrapper);
                    inserted = true;
                    headerButtonInserted = true;
                    
                    console.log('IR Tours Sync - Header-Button vor dem Speichern-Button eingefügt');
                }
            }
            
            return inserted;
        }
        
        // 4. Entferne doppelte Buttons in den Taxonomie-Bereichen
        function removeExtraButtons() {
            // Bereits vorhandene Buttons in Taxonomie-Kästen entfernen
            // WICHTIG: Nicht den Header-Button entfernen!
            $('.edit-post-sidebar button, .components-panel button, .sidebar-section button')
                .filter(function() {
                    // Prüfen Sie, ob der Text "Synchronisieren" enthält UND kein Header-Button ist
                    return $(this).text().includes('Synchronisieren') && 
                           !$(this).hasClass('header-sync-button') && 
                           !$(this).closest('.edit-post-header').length;
                })
                .each(function() {
                    // Den Button und seinen Container entfernen
                    $(this).closest('.ir-sync-button-added, div:contains("Synchronisieren & Speichern")').remove();
                    $(this).remove();
                });
        }
        
        // Initiale Ausführung nach DOM-Erstellung
        setTimeout(function() {
            // 1. Header-Button einfügen
            insertHeaderButton();
            
            // 2. Doppelte Buttons entfernen
            removeExtraButtons();
        }, 500);
        
        // Regelmäßig für die Sidebar-Panels prüfen und ggf. Buttons entfernen
        setInterval(function() {
            // Prüfen, ob der Header-Button existiert, falls nicht - neu einfügen
            if (!headerButtonInserted && !$('.edit-post-header .ir-sync-button').length) {
                insertHeaderButton();
            }
            
            // Doppelte Buttons entfernen
            removeExtraButtons();
        }, 1000);
        
        // MutationObserver für dynamisch geladene Inhalte
        const observer = new MutationObserver(function(mutations) {
            // Beim Ändern des DOM-Baums:
            // 1. Prüfen, ob der Header-Button existiert
            if (!headerButtonInserted && !$('.edit-post-header .ir-sync-button').length) {
                insertHeaderButton();
            }
            
            // 2. Doppelte Buttons entfernen
            removeExtraButtons();
        });
        
        // Observer starten
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: false
        });
    });
})(jQuery);