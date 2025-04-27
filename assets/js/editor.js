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
        
        // Globalen Refresh-Lock einrichten, um mehrfache Refreshes zu verhindern
        // Diese Variable ist für andere Skripte sichtbar und kann als Indikator dienen
        window.irSyncRefreshInProgress = false;
        
        // WICHTIG: Eindeutig prüfen, ob wir uns auf einer Post-Edit-Seite befinden
        const isEditPage = window.location.href.includes('post.php') && 
                           window.location.href.includes('action=edit');
        
        // Prüfen, ob ein externes Snippet aktiv ist (über URL-Parameter)
        const hasExternalSnippet = window.location.href.includes('snippet=') || 
                                  window.location.href.includes('shortcode=') ||
                                  window.location.href.includes('code_snippet=');
        
        // Wenn ein externes Snippet aktiv ist, deaktivieren wir den Auto-Refresh
        if (hasExternalSnippet) {
            console.log('Externes Snippet erkannt, Auto-Refresh wird deaktiviert');
            window.irSyncRefreshInProgress = true; // Block setzen, um Refreshes zu verhindern
        }
        
        // Noch striktere Prüfung, nur auf tatsächlichen Post-Editor-Seiten neuladen
        let isRelevantPostType = false;
        if (isEditPage && irSyncData && irSyncData.postType && irSyncData.mappings) {
            // Prüfen, ob der aktuelle Post-Typ in den Mappings konfiguriert ist
            for (const id in irSyncData.mappings) {
                if (irSyncData.mappings[id].active && 
                    irSyncData.mappings[id].post_type === irSyncData.postType) {
                    isRelevantPostType = true;
                    break;
                }
            }
        }
        
        console.log('IR Tours Sync - Debug Info:');
        console.log('Aktuelle Seite ist Post-Editor-Seite:', isEditPage);
        console.log('Aktueller Post-Typ ist relevant:', isRelevantPostType);
        console.log('Externes Snippet erkannt:', hasExternalSnippet);
        console.log('Refresh-Lock aktiv:', window.irSyncRefreshInProgress);
        console.log('irSyncData vollständig:', irSyncData);
        
        // Debug-Ausgabe der Originalnachrichten
        console.log('Originale Nachrichten aus irSyncData:', irSyncData.messages);
        
        // Nachrichten aus den Plugin-Einstellungen mit Fallback-Werten
        let buttonText = 'Synchronisieren & Speichern';
        let multipleThemesMessage = 'Sie haben 2 oder mehr Reisethemen gewählt. Sind Sie sicher, dass Sie speichern möchten?';
        let syncReminderMessage = 'Sie haben vergessen zu synchronisieren. Bitte drücken Sie zuerst den Synchronisations-Button. Danke.';
        let syncSuccessMessage = 'Felder erfolgreich synchronisiert. (%d Terme gesetzt)';
        let syncErrorMessage = 'Synchronisation fehlgeschlagen. Bitte versuchen Sie es erneut.';
        let termsCreatedMessage = 'Neue Terms erstellt: %s';
        
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
            
            if (irSyncData.messages.terms_created && irSyncData.messages.terms_created.trim() !== '') {
                termsCreatedMessage = irSyncData.messages.terms_created;
                console.log('Terms-Created-Nachricht überschrieben mit:', termsCreatedMessage);
            }
        }
        
        console.log('Verwendete Nachrichten:');
        console.log('Button-Text:', buttonText);
        console.log('Multiple Themes:', multipleThemesMessage);
        console.log('Sync Reminder:', syncReminderMessage);
        console.log('Sync Success:', syncSuccessMessage);
        console.log('Sync Error:', syncErrorMessage);
        console.log('Terms Created:', termsCreatedMessage);
        
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
                    // Fallback zu genereller Prüfung auf Formularelemente
                    const formElements = $('select[name*="tax_input"], input[type="checkbox"][name*="tax_input"]:checked');
                    if (formElements.length > 0) {
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
                        
                        // Anstelle von direkter Überschreibung einen Wrapper erstellen
                        const proxyDispatch = function(storeName) {
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
                                
                                return store;
                            }
                            return store;
                        };
                        
                        // Temporär die Dispatcher-Funktion durch Proxy ersetzen
                        // Dabei nicht direkt die Eigenschaft überschreiben
                        const originalSelect = wp.data.select;
                        const originalRegistry = wp.data.registry;
                        
                        // Verwende einen Monkey-Patch auf Select statt auf Dispatch
                        wp.data.select = function(storeName) {
                            // Bei Speicheranfragen intervenieren
                            if (storeName === 'core/editor') {
                                console.log('Speicheranfrage abgefangen');
                                // Abfangen von Speicheraktionen, wenn die Store-Anfrage von einem Save-Versuch kommt
                                const stack = new Error().stack;
                                if (stack && (stack.includes('savePost') || stack.includes('save'))) {
                                    return {}; // Leeren Store zurückgeben
                                }
                            }
                            return originalSelect(storeName);
                        };
                        
                        // Nach kurzer Zeit wiederherstellen
                        setTimeout(() => {
                            wp.data.select = originalSelect;
                        }, 500);
                    }
                    
                    return false;
                }
            }
            
            // Wenn der Sync-Button bereits geklickt wurde, zeigen wir die Warnung bei mehreren Termen nicht erneut an
            // Da dies bereits bei der Synchronisation abgefangen wurde
            if (syncWasClicked) {
                console.log('Synchronisation bereits durchgeführt, keine weitere Warnung nötig');
                return; // Normale Speicherung fortsetzen
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
        
        // Hilfsfunktion: Findet ein Mapping für eine bestimmte Taxonomie
        function findTaxonomyMapping(taxonomyName) {
            if (!irSyncData.mappings) {
                return null;
            }
            
            let result = null;
            $.each(irSyncData.mappings, function(id, mapping) {
                if (mapping.active && mapping.taxonomy === taxonomyName) {
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
                        
                        // Variablen sichern, bevor die Antwort verarbeitet wird
                        let termCount = 0;
                        let createdTerms = [];
                        
                        if (response.data && typeof response.data.count !== 'undefined') {
                            termCount = response.data.count;
                        }
                        
                        // Neu erstellte Terms erfassen
                        if (response.data && response.data.created_terms && response.data.created_terms.length > 0) {
                            createdTerms = response.data.created_terms;
                        }
                        
                        // Aktualisierte Taxonomien aus der Antwort abrufen und UI aktualisieren
                        if (response.data && response.data.updated_taxonomies) {
                            console.log('Aktualisierte Taxonomien empfangen:', response.data.updated_taxonomies);
                            updateUIWithNewTerms(response.data.updated_taxonomies);
                            
                            // Direkte Aktualisierung spezieller Meta-Felder für alle aktualisierten Taxonomien
                            processMetaFieldsUpdate(response.data.updated_taxonomies);
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
                        
                        // Berechne die tatsächliche Anzahl der synchronisierten Terms
                        if (response.data && response.data.updated_taxonomies) {
                            // Zähle die Gesamtzahl der Terms in allen aktualisierten Taxonomien
                            let total_terms_all_taxonomies = 0;
                            let termsPerTaxonomy = {};
                            
                            for (const taxonomy in response.data.updated_taxonomies) {
                                const taxonomyTerms = response.data.updated_taxonomies[taxonomy];
                                termsPerTaxonomy[taxonomy] = taxonomyTerms.length;
                                total_terms_all_taxonomies += taxonomyTerms.length;
                                console.log(`Taxonomie ${taxonomy}: ${taxonomyTerms.length} Terms verfügbar`);
                            }
                            
                            console.log(`Gesamtanzahl aller Terms in allen Taxonomien: ${total_terms_all_taxonomies}`);
                            
                            // Fallback: Wenn die termCount zu niedrig aussieht (oft der Fall bei Dropdown-Feldern)
                            if (termCount < 1 && total_terms_all_taxonomies > 0) {
                                // Wir nehmen die Anzahl der aktualisierten Taxonomien als Mindestanzahl
                                termCount = Math.max(Object.keys(response.data.updated_taxonomies).length, 1);
                                console.log(`Term-Anzahl korrigiert auf: ${termCount}`);
                            }
                            
                            console.log(`Endgültige Term-Anzahl für die Erfolgsmeldung: ${termCount}`);
                        } 
                        
                        // Format-String mit der anzuzeigenden Anzahl ersetzen
                        let successMessage = syncSuccessMessage.replace(/%d/g, termCount);
                        
                        // Meldung über neu erstellte Terms ergänzen, falls vorhanden
                        if (createdTerms.length > 0) {
                            let formattedTermsCreatedMessage = termsCreatedMessage.replace(/%s/g, createdTerms.join(', '));
                            successMessage += ' ' + formattedTermsCreatedMessage;
                        }
                        
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
                                    
                                    // Status zurücksetzen
                                    isSyncing = false;
                                    
                                    // Nur auf CPT-Edit-Seiten neuladen und nur, wenn kein anderer Refresh läuft
                                    if (isEditPage && isRelevantPostType && !window.irSyncRefreshInProgress) {
                                        console.log('Lade Seite neu (nur auf relevanter Edit-Seite)...');
                                        // Lock setzen, um mehrfache Refreshes zu verhindern
                                        window.irSyncRefreshInProgress = true;
                                        window.location.reload();
                                    } else {
                                        if (window.irSyncRefreshInProgress) {
                                            console.log('Refresh bereits im Gange, verhindere weiteren Refresh');
                                        } else {
                                            console.log('Kein Reload: Nicht auf einer relevanten Edit-Seite', 
                                                       'isEditPage:', isEditPage, 
                                                       'isRelevantPostType:', isRelevantPostType);
                                        }
                                    }
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
                                    
                                    // Status zurücksetzen
                                    isSyncing = false;
                                    
                                    // Nur auf CPT-Edit-Seiten neuladen und nur, wenn kein anderer Refresh läuft
                                    if (isEditPage && isRelevantPostType && !window.irSyncRefreshInProgress) {
                                        console.log('Lade Seite neu (nur auf relevanter Edit-Seite)...');
                                        // Lock setzen, um mehrfache Refreshes zu verhindern
                                        window.irSyncRefreshInProgress = true;
                                        window.location.reload();
                                    } else {
                                        if (window.irSyncRefreshInProgress) {
                                            console.log('Refresh bereits im Gange, verhindere weiteren Refresh');
                                        } else {
                                            console.log('Kein Reload: Nicht auf einer relevanten Edit-Seite', 
                                                       'isEditPage:', isEditPage, 
                                                       'isRelevantPostType:', isRelevantPostType);
                                        }
                                    }
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
                            
                            // Dem Benutzer Zeit zum manuellen Speichern geben, dann reload (nur auf Edit-Seite)
                            setTimeout(() => {
                                // Nur auf CPT-Edit-Seiten neuladen und nur, wenn kein anderer Refresh läuft
                                if (isEditPage && isRelevantPostType && !window.irSyncRefreshInProgress) {
                                    console.log('Lade Seite neu (nur auf relevanter Edit-Seite) nach manuellem Speichern...');
                                    // Lock setzen, um mehrfache Refreshes zu verhindern
                                    window.irSyncRefreshInProgress = true;
                                    window.location.reload();
                                } else {
                                    if (window.irSyncRefreshInProgress) {
                                        console.log('Refresh bereits im Gange, verhindere weiteren Refresh');
                                    } else {
                                        console.log('Kein Reload: Nicht auf einer relevanten Edit-Seite', 
                                                   'isEditPage:', isEditPage, 
                                                   'isRelevantPostType:', isRelevantPostType);
                                    }
                                }
                            }, 5000); // 5 Sekunden Wartezeit für manuelles Speichern
                        }
                        
                    } 
                    // Dieser Abschnitt ersetzt den else-Block in der AJAX-Antwortbehandlung
                    else {
                        console.error('Synchronisation fehlgeschlagen:', response.data);
                        
                        // Spezifische Behandlung für "nicht gespeicherten" Beitrag
                        let errorMsg = '';
                        let isNotSavedError = false;
                        
                        if (response.data && typeof response.data === 'string') {
                            // Prüft verschiedene mögliche Fehlermeldungen für nicht gespeicherte Beiträge
                            if (response.data.includes('Bitte speichern Sie den Beitrag zuerst') || 
                                response.data.includes('Konnte nicht synchronisiert werden')) {
                                
                                isNotSavedError = true;
                                errorMsg = 'Bitte speichern Sie den Beitrag zuerst als Entwurf oder veröffentlichen Sie ihn, bevor Sie synchronisieren.';
                                
                                // Status auf klare Anweisung setzen
                                syncStatus.html('<strong style="color:#d63638;">Bitte zuerst als Entwurf speichern oder veröffentlichen</strong>');
                                
                                // Temporären Hilfe-Button einfügen, der auf "Entwurf speichern" klickt
                                const saveBtn = $('.editor-post-save-draft');
                                if (saveBtn.length) {
                                    // Entferne vorhandene temporäre Buttons, falls vorhanden
                                    $('.temp-save-draft-btn').remove();
                                    
                                    const helpBtn = $('<button type="button" class="components-button is-secondary temp-save-draft-btn" style="margin-left:10px;">Jetzt als Entwurf speichern</button>');
                                    
                                    // Temporären Button neben dem Synchronisierungs-Button einfügen
                                    btn.after(helpBtn);
                                    
                                    // Event-Handler für den Hilfe-Button
                                    helpBtn.on('click', function() {
                                        $(this).prop('disabled', true).text('Speichere...');
                                        saveBtn.trigger('click');
                                        
                                        // Nach einer kurzen Verzögerung den temporären Button entfernen
                                        setTimeout(() => {
                                            $(this).remove();
                                        }, 2000);
                                    });
                                    
                                    // Button nach einiger Zeit automatisch entfernen
                                    setTimeout(() => {
                                        $('.temp-save-draft-btn').fadeOut(function() {
                                            $(this).remove();
                                        });
                                    }, 15000);
                                }
                            } else {
                                // Standard-Fehlerbehandlung für andere Fehler
                                errorMsg = (response.data && typeof response.data === 'string') ? 
                                    response.data : syncErrorMessage;
                            }
                        } else {
                            errorMsg = syncErrorMessage;
                        }
                        
                        btn.text('❌ Fehler');
                        
                        if (!isNotSavedError) {
                            syncStatus.text('Letzte Synchronisation: fehlgeschlagen');
                        }
                        
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
        
        /**
         * Aktualisiert die UI-Elemente mit neuen Taxonomie-Terms
         * @param {Object} updatedTaxonomies - Objekt mit Taxonomie-Schlüsseln und Term-Arrays
         */
        function updateUIWithNewTerms(updatedTaxonomies) {
            if (!updatedTaxonomies || Object.keys(updatedTaxonomies).length === 0) {
                console.log('Keine aktualisierten Taxonomien vorhanden');
                return;
            }
            
            console.log('Aktualisiere UI mit neuen Terms für Taxonomien:', Object.keys(updatedTaxonomies));
            
            try {
                // Für jede aktualisierte Taxonomie die UI aktualisieren
                for (const taxonomy in updatedTaxonomies) {
                    const terms = updatedTaxonomies[taxonomy];
                    console.log(`Aktualisiere UI für Taxonomie ${taxonomy} mit ${terms.length} Terms`);
                    
                    try {
                        // 1. JetEngine Checkboxen und Selects aktualisieren
                        updateJetEngineElements(taxonomy, terms);
                    } catch (error) {
                        console.error(`Fehler bei der JetEngine-Aktualisierung für ${taxonomy}:`, error);
                    }
                    
                    try {
                        // 2. WordPress Standard-Taxonomie-Checkboxen und Selects aktualisieren
                        updateWordPressStandardElements(taxonomy, terms);
                    } catch (error) {
                        console.error(`Fehler bei der WordPress-Standard-Aktualisierung für ${taxonomy}:`, error);
                    }
                    
                    try {
                        // 3. Meta-Box-Selects aktualisieren
                        updateMetaBoxElements(taxonomy, terms);
                    } catch (error) {
                        console.error(`Fehler bei der Meta-Box-Aktualisierung für ${taxonomy}:`, error);
                    }
                    
                    try {
                        // 4. Alle weiteren möglichen Elemente suchen und aktualisieren
                        updateAllPossibleElements(taxonomy, terms);
                    } catch (error) {
                        console.error(`Fehler bei der Suche nach weiteren Elementen für ${taxonomy}:`, error);
                    }
                }
                
                // Abschließende Meldung
                console.log('UI-Aktualisierung für alle Taxonomien abgeschlossen');
                
            } catch (error) {
                console.error('Fehler bei der UI-Aktualisierung:', error);
            }
        }
        
        /**
         * Sucht und aktualisiert alle weiteren UI-Elemente, die von anderen Funktionen nicht abgedeckt werden
         * @param {string} taxonomy - Taxonomie-Slug
         * @param {Array} terms - Array von Term-Objekten mit id, name und slug
         */
        function updateAllPossibleElements(taxonomy, terms) {
            console.log(`Suche nach weiteren UI-Elementen für ${taxonomy}`);
            
            // Passende Mappings für diese Taxonomie finden
            let metaFieldName = taxonomy;
            if (irSyncData && irSyncData.mappings) {
                for (const id in irSyncData.mappings) {
                    if (irSyncData.mappings[id].taxonomy === taxonomy && irSyncData.mappings[id].active) {
                        metaFieldName = irSyncData.mappings[id].meta_field;
                        break;
                    }
                }
            }
            
            // 1. Alle Selects suchen, die wir noch nicht bearbeitet haben
            const possibleSelects = $(`select:not(.updated-with-terms-${taxonomy})`);
            
            // Die bereits aktualisierten Selects markieren
            $(`.jet-engine-select[data-field-name="${metaFieldName}"], 
               select[name^="${metaFieldName}"], 
               select[name="${metaFieldName}"],
               select[name="tax_input[${taxonomy}][]"], 
               select[name="tax_input[${taxonomy}]"], 
               select[name^="${taxonomy}"],
               .rwmb-taxonomy-wrapper select[data-taxonomy="${taxonomy}"], 
               .rwmb-input select[data-taxonomy="${taxonomy}"]`).addClass(`updated-with-terms-${taxonomy}`);
            
            console.log(`Mögliche zusätzliche Selects gefunden: ${possibleSelects.length}`);
            
            // Jedes mögliche Select-Element prüfen
            possibleSelects.each(function() {
                const select = $(this);
                
                // Nach Attributen oder Kontexten suchen, die auf Taxonomie hinweisen
                if (select.attr('id')?.includes(taxonomy) || 
                    select.attr('name')?.includes(taxonomy) || 
                    select.attr('class')?.includes(taxonomy) ||
                    select.attr('id')?.includes(metaFieldName) || 
                    select.attr('name')?.includes(metaFieldName) || 
                    select.attr('class')?.includes(metaFieldName) ||
                    select.closest(`[data-taxonomy="${taxonomy}"]`).length ||
                    select.closest(`[data-field="${taxonomy}"]`).length ||
                    select.closest(`[data-field="${metaFieldName}"]`).length) {
                    
                    // Diese könnten relevant sein
                    const existingOptions = select.find('option').map(function() {
                        return $(this).val();
                    }).get();
                    const currentValue = select.val();
                    
                    // Nur fehlende Terms hinzufügen
                    let termAdded = false;
                    terms.forEach(term => {
                        if (!existingOptions.includes(term.id.toString()) && !existingOptions.includes(term.slug)) {
                            select.append(`<option value="${term.id}">${term.name}</option>`);
                            termAdded = true;
                        }
                    });
                    
                    // Aktuellen Wert wiederherstellen
                    if (currentValue) {
                        select.val(currentValue);
                    }
                    
                    if (termAdded) {
                        console.log(`Terms zu zusätzlichem Select hinzugefügt:`, select);
                        select.addClass(`updated-with-terms-${taxonomy}`);
                    }
                }
            });
            
            // 2. Nach Checkbox-Containern suchen, die auf Taxonomie hinweisen
            const possibleCheckboxContainers = $(`ul:not(.updated-with-terms-${taxonomy}), 
                                                 div.checklist:not(.updated-with-terms-${taxonomy}), 
                                                 div.checkbox-list:not(.updated-with-terms-${taxonomy}),
                                                 div.checkbox-group:not(.updated-with-terms-${taxonomy})`);
            
            console.log(`Mögliche zusätzliche Checkbox-Container gefunden: ${possibleCheckboxContainers.length}`);
            
            possibleCheckboxContainers.each(function() {
                const container = $(this);
                
                // Nach Attributen oder Kontexten suchen, die auf Taxonomie hinweisen
                if (container.attr('id')?.includes(taxonomy) || 
                    container.attr('class')?.includes(taxonomy) ||
                    container.attr('id')?.includes(metaFieldName) || 
                    container.attr('class')?.includes(metaFieldName) ||
                    container.closest(`[data-taxonomy="${taxonomy}"]`).length ||
                    container.closest(`[data-field="${taxonomy}"]`).length ||
                    container.closest(`[data-field="${metaFieldName}"]`).length) {
                    
                    // Prüfen, ob es Checkboxen enthält
                    const checkboxes = container.find('input[type="checkbox"]');
                    
                    if (checkboxes.length > 0) {
                        const existingOptions = checkboxes.map(function() {
                            return $(this).val();
                        }).get();
                        
                        // Nur fehlende Terms hinzufügen
                        let termAdded = false;
                        terms.forEach(term => {
                            if (!existingOptions.includes(term.id.toString()) && !existingOptions.includes(term.slug)) {
                                if (container.is('ul')) {
                                    container.append(`
                                        <li>
                                            <label>
                                                <input type="checkbox" name="${metaFieldName}[]" value="${term.id}">
                                                ${term.name}
                                            </label>
                                        </li>
                                    `);
                                } else {
                                    container.append(`
                                        <div class="checkbox-item">
                                            <label>
                                                <input type="checkbox" name="${metaFieldName}[]" value="${term.id}">
                                                ${term.name}
                                            </label>
                                        </div>
                                    `);
                                }
                                termAdded = true;
                            }
                        });
                        
                        if (termAdded) {
                            console.log(`Terms zu zusätzlichem Checkbox-Container hinzugefügt:`, container);
                            container.addClass(`updated-with-terms-${taxonomy}`);
                        }
                    }
                }
            });
            
            console.log(`Suche nach weiteren UI-Elementen für ${taxonomy} abgeschlossen`);
        }
        
        /**
         * Aktualisiert JetEngine UI-Elemente für eine bestimmte Taxonomie
         * @param {string} taxonomy - Taxonomie-Slug
         * @param {Array} terms - Array von Term-Objekten mit id, name und slug
         */
        function updateJetEngineElements(taxonomy, terms) {
            console.log(`Detaillierte JetEngine-Aktualisierung für ${taxonomy} beginnt`);
            
            // Passende Mappings für diese Taxonomie finden
            let metaFieldName = taxonomy;
            let mapping = null;
            
            if (irSyncData && irSyncData.mappings) {
                for (const id in irSyncData.mappings) {
                    if (irSyncData.mappings[id].taxonomy === taxonomy && irSyncData.mappings[id].active) {
                        mapping = irSyncData.mappings[id];
                        metaFieldName = mapping.meta_field;
                        console.log(`Mapping gefunden: Taxonomie ${taxonomy} → Meta-Feld ${metaFieldName}`);
                        break;
                    }
                }
            }
            
            // JetEngine Checkbox-Listen finden und aktualisieren (mit dem korrekten Meta-Feldnamen)
            const jetCheckboxLists = $(`.jet-engine-checkbox-list[data-field-name="${metaFieldName}"]`);
            console.log(`JetEngine Checkbox-Listen für ${metaFieldName} gefunden: ${jetCheckboxLists.length}`);
            
            jetCheckboxLists.each(function() {
                const checkboxContainer = $(this);
                const existingOptions = checkboxContainer.find('.jet-engine-checkbox-list__input').map(function() {
                    return $(this).val();
                }).get();
                
                console.log(`Vorhandene Optionen in Checkbox-Liste:`, existingOptions);
                
                // Nur fehlende Terms hinzufügen
                terms.forEach(term => {
                    if (!existingOptions.includes(term.id.toString()) && !existingOptions.includes(term.slug)) {
                        const itemTemplate = `
                            <div class="jet-engine-checkbox-list__row">
                                <label class="jet-engine-checkbox-list__item">
                                    <input type="checkbox" class="jet-engine-checkbox-list__input" name="${metaFieldName}[]" value="${term.id}">
                                    <span class="jet-engine-checkbox-list__label">${term.name}</span>
                                </label>
                            </div>
                        `;
                        checkboxContainer.append(itemTemplate);
                        console.log(`Term ${term.name} (ID: ${term.id}) zur Checkbox-Liste hinzugefügt`);
                    }
                });
            });
            
            // JetEngine formatierte Checkbox-Listen finden und aktualisieren
            const jetFormattedCheckboxes = $(`.jet-engine-checkbox-list[data-field-name="${metaFieldName}_meta"]`);
            console.log(`JetEngine formatierte Checkbox-Listen für ${metaFieldName}_meta gefunden: ${jetFormattedCheckboxes.length}`);
            
            jetFormattedCheckboxes.each(function() {
                const checkboxContainer = $(this);
                const existingOptions = checkboxContainer.find('.jet-engine-checkbox-list__input').map(function() {
                    return $(this).attr('data-value');
                }).get();
                
                console.log(`Vorhandene Optionen in formatierter Checkbox-Liste:`, existingOptions);
                
                // Nur fehlende Terms hinzufügen
                terms.forEach(term => {
                    if (!existingOptions.includes(term.id.toString()) && !existingOptions.includes(term.slug)) {
                        const itemTemplate = `
                            <div class="jet-engine-checkbox-list__row">
                                <label class="jet-engine-checkbox-list__item">
                                    <input type="checkbox" class="jet-engine-checkbox-list__input" name="${metaFieldName}_meta[${term.id}]" value="true" data-value="${term.id}">
                                    <span class="jet-engine-checkbox-list__label">${term.name}</span>
                                </label>
                            </div>
                        `;
                        checkboxContainer.append(itemTemplate);
                        console.log(`Term ${term.name} (ID: ${term.id}) zur formatierten Checkbox-Liste hinzugefügt`);
                    }
                });
            });
            
            // JetEngine Select-Felder finden und aktualisieren (mit dem korrekten Meta-Feldnamen)
            const jetSelects = $(`.jet-engine-select[data-field-name="${metaFieldName}"], select[name^="${metaFieldName}"], select[name="${metaFieldName}"]`);
            console.log(`JetEngine Select-Felder für ${metaFieldName} gefunden: ${jetSelects.length}`);
            
            jetSelects.each(function() {
                const select = $(this);
                const existingOptions = select.find('option').map(function() {
                    return $(this).val();
                }).get();
                const currentValue = select.val(); // Aktuell ausgewählter Wert beibehalten
                
                console.log(`Vorhandene Optionen in Select-Feld:`, existingOptions);
                console.log(`Aktueller Wert im Select-Feld:`, currentValue);
                
                // Nur fehlende Terms hinzufügen
                terms.forEach(term => {
                    if (!existingOptions.includes(term.id.toString()) && !existingOptions.includes(term.slug)) {
                        select.append(`<option value="${term.id}">${term.name}</option>`);
                        console.log(`Term ${term.name} (ID: ${term.id}) zum Select-Feld hinzugefügt`);
                    }
                });
                
                // Aktuellen Wert wiederherstellen
                if (currentValue) {
                    select.val(currentValue);
                }
            });
            
            console.log(`Detaillierte JetEngine-Aktualisierung für ${taxonomy} abgeschlossen`);
        }
        
        /**
         * Aktualisiert WordPress Standard-UI-Elemente für eine bestimmte Taxonomie
         * @param {string} taxonomy - Taxonomie-Slug
         * @param {Array} terms - Array von Term-Objekten mit id, name und slug
         */
        function updateWordPressStandardElements(taxonomy, terms) {
            console.log(`Detaillierte WordPress-Standard-Aktualisierung für ${taxonomy} beginnt`);
            
            // Passende Mappings für diese Taxonomie finden
            let metaFieldName = taxonomy;
            if (irSyncData && irSyncData.mappings) {
                for (const id in irSyncData.mappings) {
                    if (irSyncData.mappings[id].taxonomy === taxonomy && irSyncData.mappings[id].active) {
                        metaFieldName = irSyncData.mappings[id].meta_field;
                        console.log(`Mapping gefunden für WordPress-Elemente: Taxonomie ${taxonomy} → Meta-Feld ${metaFieldName}`);
                        break;
                    }
                }
            }
            
            // 1. WordPress Standard-Taxonomie-Checkboxen in der Sidebar
            const taxonomyCheckboxes = $(`.${taxonomy}-checklist, #${taxonomy}checklist`);
            console.log(`WordPress Taxonomie-Checkboxen gefunden: ${taxonomyCheckboxes.length}`);
            
            taxonomyCheckboxes.each(function() {
                const checkboxContainer = $(this);
                const existingOptions = checkboxContainer.find('input').map(function() {
                    return $(this).val();
                }).get();
                
                console.log(`Vorhandene Optionen in WP-Checkbox-Liste:`, existingOptions);
                
                // Nur fehlende Terms hinzufügen
                terms.forEach(term => {
                    if (!existingOptions.includes(term.id.toString())) {
                        const itemTemplate = `
                            <li id="${taxonomy}-${term.id}">
                                <label class="selectit">
                                    <input type="checkbox" name="tax_input[${taxonomy}][]" id="in-${taxonomy}-${term.id}" value="${term.id}">
                                    ${term.name}
                                </label>
                            </li>
                        `;
                        checkboxContainer.append(itemTemplate);
                        console.log(`Term ${term.name} (ID: ${term.id}) zur WP-Checkbox-Liste hinzugefügt`);
                    }
                });
            });
            
            // 2. WordPress Standard-Taxonomie-Selects
            const taxonomySelects = $(`select[name="tax_input[${taxonomy}][]"], select[name="tax_input[${taxonomy}]"], select[name^="${taxonomy}"]`);
            console.log(`WordPress Taxonomie-Selects gefunden: ${taxonomySelects.length}`);
            
            taxonomySelects.each(function() {
                const select = $(this);
                const existingOptions = select.find('option').map(function() {
                    return $(this).val();
                }).get();
                const currentValue = select.val(); // Aktuell ausgewählter Wert beibehalten
                
                console.log(`Vorhandene Optionen in WP-Select:`, existingOptions);
                console.log(`Aktueller Wert in WP-Select:`, currentValue);
                
                // Nur fehlende Terms hinzufügen
                terms.forEach(term => {
                    if (!existingOptions.includes(term.id.toString())) {
                        select.append(`<option value="${term.id}">${term.name}</option>`);
                        console.log(`Term ${term.name} (ID: ${term.id}) zum WP-Select hinzugefügt`);
                    }
                });
                
                // Aktuellen Wert wiederherstellen
                if (currentValue) {
                    select.val(currentValue);
                }
            });
            
            // 3. Quick-Edit und Bulk-Edit Taxonomie-Checkboxen
            const quickEditCheckboxes = $(`.inline-edit-col .${taxonomy}-checklist`);
            console.log(`Quick-Edit Taxonomie-Checkboxen gefunden: ${quickEditCheckboxes.length}`);
            
            quickEditCheckboxes.each(function() {
                const checkboxContainer = $(this);
                const existingOptions = checkboxContainer.find('input').map(function() {
                    return $(this).val();
                }).get();
                
                // Nur fehlende Terms hinzufügen
                terms.forEach(term => {
                    if (!existingOptions.includes(term.id.toString())) {
                        const itemTemplate = `
                            <li id="${taxonomy}-${term.id}">
                                <label class="selectit">
                                    <input type="checkbox" name="tax_input[${taxonomy}][]" id="in-${taxonomy}-${term.id}" value="${term.id}">
                                    ${term.name}
                                </label>
                            </li>
                        `;
                        checkboxContainer.append(itemTemplate);
                        console.log(`Term ${term.name} (ID: ${term.id}) zu Quick-Edit-Checkboxen hinzugefügt`);
                    }
                });
            });
            
            // 4. Nach allen möglichen Elementen mit der Taxonomie im Namen oder in Attributen suchen
            const allPossibleElements = $(`[data-taxonomy="${taxonomy}"], [name*="${taxonomy}"], [id*="${taxonomy}"]`).not('script,style');
            console.log(`Alle möglichen Taxonomie-Elemente gefunden: ${allPossibleElements.length}`);
            
            // Durchlaufen und prüfen, ob es sich um ein Select- oder Checkbox-Element handelt
            allPossibleElements.each(function() {
                const element = $(this);
                
                // Wenn es ein Select-Element ist
                if (element.is('select') && !taxonomySelects.is(element)) {
                    const existingOptions = element.find('option').map(function() {
                        return $(this).val();
                    }).get();
                    const currentValue = element.val();
                    
                    // Nur fehlende Terms hinzufügen
                    terms.forEach(term => {
                        if (!existingOptions.includes(term.id.toString()) && !existingOptions.includes(term.slug)) {
                            element.append(`<option value="${term.id}">${term.name}</option>`);
                            console.log(`Term ${term.name} zu zusätzlichem Select hinzugefügt:`, element);
                        }
                    });
                    
                    // Aktuellen Wert wiederherstellen
                    if (currentValue) {
                        element.val(currentValue);
                    }
                }
                
                // Wenn es ein potentieller Checkbox-Container ist
                if (element.is('ul, div, fieldset')) {
                    const checkboxes = element.find('input[type="checkbox"]');
                    if (checkboxes.length > 0) {
                        const existingOptions = checkboxes.map(function() {
                            return $(this).val();
                        }).get();
                        
                        let termAdded = false;
                        
                        terms.forEach(term => {
                            if (!existingOptions.includes(term.id.toString()) && !existingOptions.includes(term.slug)) {
                                // Wir versuchen, den passenden Container-Typ zu erkennen
                                if (element.is('ul')) {
                                    element.append(`
                                        <li>
                                            <label>
                                                <input type="checkbox" name="${taxonomy}[]" value="${term.id}">
                                                ${term.name}
                                            </label>
                                        </li>
                                    `);
                                    termAdded = true;
                                } else if (element.find('.checkbox-item, .checkbox-wrapper, .checkbox-container').length) {
                                    const checkboxItemClass = element.find('.checkbox-item').length ? 'checkbox-item' : 
                                                            element.find('.checkbox-wrapper').length ? 'checkbox-wrapper' : 'checkbox-container';
                                    
                                    element.append(`
                                        <div class="${checkboxItemClass}">
                                            <label>
                                                <input type="checkbox" name="${taxonomy}[]" value="${term.id}">
                                                ${term.name}
                                            </label>
                                        </div>
                                    `);
                                    termAdded = true;
                                }
                            }
                        });
                        
                        if (termAdded) {
                            console.log(`Terms zu zusätzlichem Checkbox-Container hinzugefügt:`, element);
                        }
                    }
                }
            });
            
            console.log(`Detaillierte WordPress-Standard-Aktualisierung für ${taxonomy} abgeschlossen`);
        }
        
        /**
         * Aktualisiert Meta-Box-UI-Elemente für eine bestimmte Taxonomie
         * @param {string} taxonomy - Taxonomie-Slug
         * @param {Array} terms - Array von Term-Objekten mit id, name und slug
         */
        function updateMetaBoxElements(taxonomy, terms) {
            console.log(`Detaillierte Meta-Box-Aktualisierung für ${taxonomy} beginnt`);
            
            // Passende Mappings für diese Taxonomie finden
            let metaFieldName = taxonomy;
            if (irSyncData && irSyncData.mappings) {
                for (const id in irSyncData.mappings) {
                    if (irSyncData.mappings[id].taxonomy === taxonomy && irSyncData.mappings[id].active) {
                        metaFieldName = irSyncData.mappings[id].meta_field;
                        console.log(`Mapping gefunden für Meta-Box-Elemente: Taxonomie ${taxonomy} → Meta-Feld ${metaFieldName}`);
                        break;
                    }
                }
            }
            
            // 1. Meta-Box-Selects für Taxonomien mit direkter data-taxonomy-Verknüpfung
            const directTaxonomySelects = $(`.rwmb-taxonomy-wrapper select[data-taxonomy="${taxonomy}"], .rwmb-input select[data-taxonomy="${taxonomy}"]`);
            console.log(`Meta-Box direkter Taxonomie-Selects gefunden: ${directTaxonomySelects.length}`);
            
            directTaxonomySelects.each(function() {
                const select = $(this);
                const existingOptions = select.find('option').map(function() {
                    return $(this).val();
                }).get();
                const currentValue = select.val(); // Aktuell ausgewählter Wert beibehalten
                
                console.log(`Vorhandene Optionen in Meta-Box direktem Taxonomie-Select:`, existingOptions);
                
                // Nur fehlende Terms hinzufügen
                terms.forEach(term => {
                    if (!existingOptions.includes(term.id.toString()) && !existingOptions.includes(term.slug)) {
                        select.append(`<option value="${term.id}">${term.name}</option>`);
                        console.log(`Term ${term.name} (ID: ${term.id}) zum direkten Meta-Box-Select hinzugefügt`);
                    }
                });
                
                // Aktuellen Wert wiederherstellen
                if (currentValue) {
                    select.val(currentValue);
                }
            });
            
            // 2. Meta-Box-Selects über das Mapping-Feld
            const metaFieldSelects = $(`.rwmb-taxonomy-wrapper select[name^="${metaFieldName}"], .rwmb-input select[name^="${metaFieldName}"]`);
            console.log(`Meta-Box Meta-Field-Selects gefunden: ${metaFieldSelects.length}`);
            
            metaFieldSelects.each(function() {
                const select = $(this);
                if (!directTaxonomySelects.is(select)) { // Nur wenn nicht bereits als direktes Taxonomie-Select behandelt
                    const existingOptions = select.find('option').map(function() {
                        return $(this).val();
                    }).get();
                    const currentValue = select.val();
                    
                    console.log(`Vorhandene Optionen in Meta-Box Meta-Field-Select:`, existingOptions);
                    
                    // Nur fehlende Terms hinzufügen
                    terms.forEach(term => {
                        if (!existingOptions.includes(term.id.toString()) && !existingOptions.includes(term.slug)) {
                            select.append(`<option value="${term.id}">${term.name}</option>`);
                            console.log(`Term ${term.name} (ID: ${term.id}) zum Meta-Box-Field-Select hinzugefügt`);
                        }
                    });
                    
                    // Aktuellen Wert wiederherstellen
                    if (currentValue) {
                        select.val(currentValue);
                    }
                }
            });
            
            // 3. Meta-Box-Checkboxen für Taxonomien mit direkter data-taxonomy-Verknüpfung
            const metaBoxCheckboxes = $(`.rwmb-taxonomy-wrapper .rwmb-checkbox-list[data-taxonomy="${taxonomy}"], .rwmb-input .rwmb-checkbox-list[data-taxonomy="${taxonomy}"]`);
            console.log(`Meta-Box Taxonomie-Checkboxen gefunden: ${metaBoxCheckboxes.length}`);
            
            metaBoxCheckboxes.each(function() {
                const checkboxContainer = $(this);
                const existingOptions = checkboxContainer.find('input').map(function() {
                    return $(this).val();
                }).get();
                
                console.log(`Vorhandene Optionen in Meta-Box-Checkboxen:`, existingOptions);
                
                // Nur fehlende Terms hinzufügen
                terms.forEach(term => {
                    if (!existingOptions.includes(term.id.toString()) && !existingOptions.includes(term.slug)) {
                        const itemTemplate = `
                            <li>
                                <label>
                                    <input type="checkbox" name="${taxonomy}[]" value="${term.id}" class="rwmb-checkbox">
                                    ${term.name}
                                </label>
                            </li>
                        `;
                        checkboxContainer.append(itemTemplate);
                        console.log(`Term ${term.name} (ID: ${term.id}) zu Meta-Box-Checkboxen hinzugefügt`);
                    }
                });
            });
            
            // 4. Meta-Box-Checkboxen über das Mapping-Feld
            const metaFieldCheckboxes = $(`.rwmb-taxonomy-wrapper .rwmb-checkbox-list[name^="${metaFieldName}"], 
                                          .rwmb-input .rwmb-checkbox-list[name^="${metaFieldName}"],
                                          .rwmb-field[data-name="${metaFieldName}"] .rwmb-checkbox-list,
                                          .rwmb-field[data-name="${metaFieldName}"] ul`);
            
            console.log(`Meta-Box Meta-Field-Checkboxen gefunden: ${metaFieldCheckboxes.length}`);
            
            metaFieldCheckboxes.each(function() {
                const checkboxContainer = $(this);
                if (!metaBoxCheckboxes.is(checkboxContainer)) { // Nur wenn nicht bereits als direkte Taxonomie-Checkboxen behandelt
                    const existingOptions = checkboxContainer.find('input').map(function() {
                        return $(this).val();
                    }).get();
                    
                    console.log(`Vorhandene Optionen in Meta-Box Meta-Field-Checkboxen:`, existingOptions);
                    
                    // Nur fehlende Terms hinzufügen
                    terms.forEach(term => {
                        if (!existingOptions.includes(term.id.toString()) && !existingOptions.includes(term.slug)) {
                            // Angepasst an das Format von Meta-Box
                            let itemTemplate = '';
                            
                            if (checkboxContainer.is('ul')) {
                                itemTemplate = `
                                    <li>
                                        <label>
                                            <input type="checkbox" name="${metaFieldName}[]" value="${term.id}" class="rwmb-checkbox">
                                            ${term.name}
                                        </label>
                                    </li>
                                `;
                            } else {
                                itemTemplate = `
                                    <div class="rwmb-checkbox-wrapper">
                                        <label>
                                            <input type="checkbox" name="${metaFieldName}[]" value="${term.id}" class="rwmb-checkbox">
                                            ${term.name}
                                        </label>
                                    </div>
                                `;
                            }
                            
                            checkboxContainer.append(itemTemplate);
                            console.log(`Term ${term.name} (ID: ${term.id}) zu Meta-Box Meta-Field-Checkboxen hinzugefügt`);
                        }
                    });
                }
            });
            
            // 5. Nach weiteren Meta-Box-Elementen für diese Taxonomie suchen
            const additionalMetaBoxElements = $(`.rwmb-field[data-name="${metaFieldName}"] select, 
                                               .rwmb-field[data-name="${metaFieldName}_meta"] select,
                                               .rwmb-field[data-name="${taxonomy}"] select`);
            
            console.log(`Zusätzliche Meta-Box-Selects gefunden: ${additionalMetaBoxElements.length}`);
            
            additionalMetaBoxElements.each(function() {
                const select = $(this);
                if (!directTaxonomySelects.is(select) && !metaFieldSelects.is(select)) { 
                    const existingOptions = select.find('option').map(function() {
                        return $(this).val();
                    }).get();
                    const currentValue = select.val();
                    
                    // Nur fehlende Terms hinzufügen
                    terms.forEach(term => {
                        if (!existingOptions.includes(term.id.toString()) && !existingOptions.includes(term.slug)) {
                            select.append(`<option value="${term.id}">${term.name}</option>`);
                            console.log(`Term ${term.name} (ID: ${term.id}) zu zusätzlichem Meta-Box-Select hinzugefügt`);
                        }
                    });
                    
                    // Aktuellen Wert wiederherstellen
                    if (currentValue) {
                        select.val(currentValue);
                    }
                }
            });
            
            console.log(`Detaillierte Meta-Box-Aktualisierung für ${taxonomy} abgeschlossen`);
        }
        
        /**
         * Verarbeitet die Meta-Feld-Aktualisierungen nach der Synchronisation
         * @param {Object} updatedTaxonomies - Objekt mit Taxonomie-Schlüsseln und Term-Arrays
         */
        function processMetaFieldsUpdate(updatedTaxonomies) {
            console.log('Starte Aktualisierung der Meta-Felder für alle Taxonomien...');
            
            // Für jede aktualisierte Taxonomie die zugehörigen Meta-Felder aktualisieren
            for (const taxonomy in updatedTaxonomies) {
                const terms = updatedTaxonomies[taxonomy];
                console.log(`Verarbeite Taxonomie ${taxonomy} mit ${terms.length} Terms`);
                
                // Das entsprechende Meta-Feld für diese Taxonomie finden
                let metaField = taxonomy; // Standard: gleicher Name
                
                // In den Mappings nach der Zuordnung suchen
                if (irSyncData && irSyncData.mappings) {
                    for (const id in irSyncData.mappings) {
                        if (irSyncData.mappings[id].taxonomy === taxonomy && irSyncData.mappings[id].active) {
                            metaField = irSyncData.mappings[id].meta_field;
                            console.log(`Mapping gefunden: Taxonomie ${taxonomy} → Meta-Feld ${metaField}`);
                            break;
                        }
                    }
                }
                
                // Jetzt gezielt Felder aktualisieren, die mit diesem Meta-Feld zu tun haben
                updateMetaFieldSelectors(metaField, taxonomy, terms);
            }
            
            console.log('Meta-Feld-Aktualisierung für alle Taxonomien abgeschlossen');
        }
        
        /**
         * Aktualisiert UI-Elemente basierend auf Meta-Feld- und Taxonomie-Namen
         * @param {string} metaField - Name des Meta-Feldes
         * @param {string} taxonomy - Name der Taxonomie
         * @param {Array} terms - Array von Term-Objekten
         */
        function updateMetaFieldSelectors(metaField, taxonomy, terms) {
            console.log(`Aktualisiere UI-Elemente für Meta-Feld: ${metaField}, Taxonomie: ${taxonomy}`);
            
            // 1. Direkte Feldselektion - mehrere mögliche Varianten
            const directSelectors = [
                `select[name="${metaField}"]`,                  // Standard-Select
                `select[name="${metaField}[]"]`,                // Multi-Select
                `#${metaField}`,                                // ID-basiert
                `.${metaField}-select`,                         // Klassen-basiert
                `select.${metaField}`,                          // Klassen-basiert (nur Selects)
                `.jet-engine-select[data-field-name="${metaField}"]` // JetEngine-spezifisch
            ];
            
            // Alle direkten Selektoren durchgehen
            $(directSelectors.join(', ')).each(function() {
                const select = $(this);
                console.log(`Direktes Feld für ${metaField} gefunden:`, select);
                
                // Aktuelle Auswahl speichern
                const currentValue = select.val();
                console.log('Aktueller Wert:', currentValue);
                
                // Existierende Optionen erfassen
                const existingOptions = select.find('option').map(function() {
                    return $(this).val();
                }).get();
                console.log('Existierende Optionen:', existingOptions);
                
                // Neue Optionen hinzufügen
                let optionsAdded = false;
                terms.forEach(term => {
                    if (!existingOptions.includes(term.id.toString()) && 
                        !existingOptions.includes(term.slug) && 
                        !existingOptions.includes(term.name)) {
                        
                        select.append(`<option value="${term.id}">${term.name}</option>`);
                        console.log(`Option für ${metaField} hinzugefügt: ${term.name} (${term.id})`);
                        optionsAdded = true;
                    }
                });
                
                // Wenn Optionen hinzugefügt wurden, die aktuelle Auswahl wiederherstellen
                if (optionsAdded && currentValue) {
                    select.val(currentValue);
                    console.log('Auswahl wiederhergestellt:', currentValue);
                }
            });
            
            // 2. JetEngine formatierte Checkbox-Listen
            $(`.jet-engine-checkbox-list[data-field-name="${metaField}"], .jet-engine-checkbox-list[data-field-name="${metaField}_meta"]`).each(function() {
                const checkboxContainer = $(this);
                console.log(`JetEngine Checkbox-Liste für ${metaField} gefunden:`, checkboxContainer);
                
                // Format bestimmen
                const isMetaFormat = $(this).attr('data-field-name').endsWith('_meta');
                
                // Existierende Optionen erfassen
                const existingOptions = checkboxContainer.find('.jet-engine-checkbox-list__input').map(function() {
                    return isMetaFormat ? $(this).attr('data-value') : $(this).val();
                }).get();
                console.log('Existierende Optionen:', existingOptions);
                
                // Neue Optionen hinzufügen
                terms.forEach(term => {
                    if (!existingOptions.includes(term.id.toString()) && !existingOptions.includes(term.slug)) {
                        let itemTemplate;
                        
                        if (isMetaFormat) {
                            // Für Meta-Format (name="field_meta[term_id]" value="true")
                            itemTemplate = `
                                <div class="jet-engine-checkbox-list__row">
                                    <label class="jet-engine-checkbox-list__item">
                                        <input type="checkbox" class="jet-engine-checkbox-list__input" name="${metaField}_meta[${term.id}]" value="true" data-value="${term.id}">
                                        <span class="jet-engine-checkbox-list__label">${term.name}</span>
                                    </label>
                                </div>
                            `;
                        } else {
                            // Für Standard-Format (name="field[]" value="term_id")
                            itemTemplate = `
                                <div class="jet-engine-checkbox-list__row">
                                    <label class="jet-engine-checkbox-list__item">
                                        <input type="checkbox" class="jet-engine-checkbox-list__input" name="${metaField}[]" value="${term.id}">
                                        <span class="jet-engine-checkbox-list__label">${term.name}</span>
                                    </label>
                                </div>
                            `;
                        }
                        
                        checkboxContainer.append(itemTemplate);
                        console.log(`Checkbox für ${metaField} hinzugefügt: ${term.name} (${term.id})`);
                    }
                });
            });
            
            // 3. WordPress Standard-Taxonomie-Checkboxen
            $(`.${taxonomy}-checklist, #${taxonomy}checklist`).each(function() {
                const checkboxContainer = $(this);
                console.log(`WordPress Taxonomie-Checkboxen für ${taxonomy} gefunden:`, checkboxContainer);
                
                // Existierende Optionen erfassen
                const existingOptions = checkboxContainer.find('input').map(function() {
                    return $(this).val();
                }).get();
                console.log('Existierende Optionen:', existingOptions);
                
                // Neue Optionen hinzufügen
                terms.forEach(term => {
                    if (!existingOptions.includes(term.id.toString())) {
                        const itemTemplate = `
                            <li id="${taxonomy}-${term.id}">
                                <label class="selectit">
                                    <input type="checkbox" name="tax_input[${taxonomy}][]" id="in-${taxonomy}-${term.id}" value="${term.id}">
                                    ${term.name}
                                </label>
                            </li>
                        `;
                        checkboxContainer.append(itemTemplate);
                        console.log(`WP Checkbox für ${taxonomy} hinzugefügt: ${term.name} (${term.id})`);
                    }
                });
            });
            
            // 4. WordPress Standard-Taxonomie-Selects
            $(`select[name="tax_input[${taxonomy}][]"], select[name="tax_input[${taxonomy}]"]`).each(function() {
                const select = $(this);
                console.log(`WordPress Taxonomie-Select für ${taxonomy} gefunden:`, select);
                
                // Existierende Optionen erfassen
                const existingOptions = select.find('option').map(function() {
                    return $(this).val();
                }).get();
                const currentValue = select.val();
                
                // Neue Optionen hinzufügen
                let optionsAdded = false;
                terms.forEach(term => {
                    if (!existingOptions.includes(term.id.toString())) {
                        select.append(`<option value="${term.id}">${term.name}</option>`);
                        console.log(`WP Select-Option für ${taxonomy} hinzugefügt: ${term.name} (${term.id})`);
                        optionsAdded = true;
                    }
                });
                
                // Wenn Optionen hinzugefügt wurden, die aktuelle Auswahl wiederherstellen
                if (optionsAdded && currentValue) {
                    select.val(currentValue);
                }
            });
            
            // 5. Meta-Box-Elemente
            $(`.rwmb-taxonomy-wrapper select[data-taxonomy="${taxonomy}"], .rwmb-input select[data-taxonomy="${taxonomy}"]`).each(function() {
                const select = $(this);
                console.log(`Meta-Box-Select für ${taxonomy} gefunden:`, select);
                
                // Existierende Optionen erfassen
                const existingOptions = select.find('option').map(function() {
                    return $(this).val();
                }).get();
                const currentValue = select.val();
                
                // Neue Optionen hinzufügen
                let optionsAdded = false;
                terms.forEach(term => {
                    if (!existingOptions.includes(term.id.toString()) && !existingOptions.includes(term.slug)) {
                        select.append(`<option value="${term.id}">${term.name}</option>`);
                        console.log(`Meta-Box-Option für ${taxonomy} hinzugefügt: ${term.name} (${term.id})`);
                        optionsAdded = true;
                    }
                });
                
                // Wenn Optionen hinzugefügt wurden, die aktuelle Auswahl wiederherstellen
                if (optionsAdded && currentValue) {
                    select.val(currentValue);
                }
            });
            
            // 6. Dynamic-Finder für weitere UI-Elemente
            const dynamicSelectors = [
                `select[id*="${metaField}"]`,
                `select[name*="${metaField}"]`, 
                `select[id*="${taxonomy}"]`,
                `select[name*="${taxonomy}"]`,
                `select[class*="${metaField}"]`,
                `select[class*="${taxonomy}"]`
            ];
            
            // Alle zusätzlichen, potenziell passenden Selektoren durchgehen
            // Aber nur, wenn sie nicht bereits durch die vorherigen Selektoren abgedeckt wurden
            $(dynamicSelectors.join(', ')).not(`select[name="${metaField}"], select[name="${metaField}[]"], #${metaField}, .${metaField}-select, select.${metaField}, .jet-engine-select[data-field-name="${metaField}"]`).each(function() {
                const select = $(this);
                console.log(`Dynamisch gefundenes Feld für ${metaField}/${taxonomy}:`, select);
                
                // Existierende Optionen erfassen
                const existingOptions = select.find('option').map(function() {
                    return $(this).val();
                }).get();
                const currentValue = select.val();
                
                // Neue Optionen hinzufügen
                let optionsAdded = false;
                terms.forEach(term => {
                    if (!existingOptions.includes(term.id.toString()) && !existingOptions.includes(term.slug)) {
                        select.append(`<option value="${term.id}">${term.name}</option>`);
                        console.log(`Option für dynamisches Feld hinzugefügt: ${term.name} (${term.id})`);
                        optionsAdded = true;
                    }
                });
                
                // Wenn Optionen hinzugefügt wurden, die aktuelle Auswahl wiederherstellen
                if (optionsAdded && currentValue) {
                    select.val(currentValue);
                }
            });
            
            // 7. Globalen Event auslösen für custom Integrationen
            const eventName = `sync_terms_updated_${taxonomy.replace(/-/g, '_')}`;
            
            // Event mit Termsdaten auslösen
            const event = new CustomEvent(eventName, {
                detail: { 
                    terms: terms,
                    taxonomy: taxonomy,
                    metaField: metaField
                }
            });
            document.dispatchEvent(event);
            console.log(`Globales Event "${eventName}" für Custom-Integrationen ausgelöst`);
            
            console.log(`UI-Update für ${metaField}/${taxonomy} abgeschlossen`);
        }
    });
})(jQuery);