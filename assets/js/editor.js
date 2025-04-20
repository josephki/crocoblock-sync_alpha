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
        
        // Debug-Ausgabe, falls aktiviert
        if (irSyncData.debugMode) {
            console.log('IR Tours Sync - Mappings:', irSyncData.mappings);
            console.log('IR Tours Sync - Post-Typ:', irSyncData.postType);
            console.log('IR Tours Sync - Elementor:', irSyncData.isElementor || false);
        }
        
        // Nachrichten aus den Plugin-Einstellungen
        const messages = irSyncData.messages || {
            multiple_themes: 'Sie haben 2 oder mehr Reisethemen gewählt. Sind Sie sicher, dass Sie speichern möchten?',
            sync_button: 'Synchronisieren & Speichern',
            sync_reminder: 'Sie haben vergessen zu synchronisieren. Bitte drücken Sie zuerst den Synchronisations-Button. Danke.',
            sync_success: 'Felder erfolgreich synchronisiert. (%d Terme gesetzt)',
            sync_error: 'Synchronisation fehlgeschlagen. Bitte versuchen Sie es erneut.'
        };
        
        // 1. Warnung beim Speichern ohne vorherige Synchronisation
        $(document).on('click', '.editor-post-publish-button, .editor-post-publish-panel__header-publish-button', function(e) {
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
                    alert(messages.sync_reminder);
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    return false;
                }
            }
            
            // Warnung bei mehreren ausgewählten Reisethemen
            const reisethemen_mapping = findReisethemenMapping();
            if (reisethemen_mapping) {
                const meta_field = reisethemen_mapping.meta_field;
                const reisethemenChecked = $(`input[name^="${meta_field}["][value="true"]:checked`);
                
                if (reisethemenChecked.length >= 2) {
                    if (!confirm(messages.multiple_themes)) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        return false;
                    }
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
        const syncButton = $(`<button type="button" class="components-button is-primary ir-sync-button">${messages.sync_button}</button>`);
        const syncStatus = $('<span style="margin-left:0.75em;font-weight:normal;font-size:0.9em;" class="ir-sync-status"></span>');
        
        // Button-Funktion: Synchronisieren und speichern
        syncButton.on('click', function() {
            // Verhindert Doppelklicks
            if (isSyncing) return;
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
            
            if (!postId) {
                syncStatus.text('Fehler: Konnte Post-ID nicht ermitteln.');
                isSyncing = false;
                return;
            }
            
            const btn = $(this);
            btn.prop('disabled', true).text('⏳ Synchronisiere...');
            syncStatus.text('');
            
            $.post(irSyncData.ajaxurl, {
                action: 'ir_manual_sync',
                post_id: postId,
                nonce: irSyncData.nonce
            }).done(function(response) {
                if (response.success) {
                    btn.text('✅ Synchronisiert – speichere...');
                    syncStatus.text('Letzte Synchronisation: erfolgreich');
                    
                    // Speichern je nach Editor
                    if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch && wp.data.dispatch('core/editor')) {
                        // Gutenberg
                        try {
                            wp.data.dispatch('core/editor').savePost().then(() => {
                                btn.prop('disabled', false).text(messages.sync_button);
                                setTimeout(() => { isSyncing = false; }, 1000);
                            }).catch(error => {
                                console.error('Speichern fehlgeschlagen:', error);
                                syncStatus.text('Fehler beim Speichern');
                                btn.prop('disabled', false).text(messages.sync_button);
                                isSyncing = false;
                            });
                        } catch (error) {
                            console.error('Fehler beim Speichern:', error);
                            btn.prop('disabled', false).text(messages.sync_button);
                            isSyncing = false;
                        }
                    } else if (typeof elementor !== 'undefined' && elementor.saver) {
                        // Elementor
                        try {
                            elementor.saver.update().then(() => {
                                btn.prop('disabled', false).text(messages.sync_button);
                                setTimeout(() => { isSyncing = false; }, 1000);
                            }).catch(error => {
                                console.error('Speichern fehlgeschlagen:', error);
                                syncStatus.text('Fehler beim Speichern');
                                btn.prop('disabled', false).text(messages.sync_button);
                                isSyncing = false;
                            });
                        } catch (error) {
                            console.error('Fehler beim Speichern:', error);
                            btn.prop('disabled', false).text(messages.sync_button);
                            isSyncing = false;
                        }
                    } else {
                        // Fallback: Manuelles Speichern durch den Benutzer
                        alert('Synchronisation erfolgreich. Bitte speichern Sie den Beitrag jetzt manuell.');
                        btn.prop('disabled', false).text(messages.sync_button);
                        isSyncing = false;
                    }
                } else {
                    btn.text('❌ Fehler');
                    syncStatus.text('Letzte Synchronisation: fehlgeschlagen - ' + 
                        (response.data && typeof response.data === 'string' ? response.data : messages.sync_error));
                    setTimeout(() => {
                        btn.prop('disabled', false).text(messages.sync_button);
                        isSyncing = false;
                    }, 3000);
                }
            }).fail(function(xhr, status, error) {
                btn.text('❌ Fehler');
                syncStatus.text('Letzte Synchronisation: AJAX-Fehler - ' + error);
                setTimeout(() => {
                    btn.prop('disabled', false).text(messages.sync_button);
                    isSyncing = false;
                }, 3000);
            });
        });
        
        // 3. Button in Editor einfügen (verschiedene Editor-Versionen)
        function insertSyncButton() {
            // Verschiedene Selektoren für verschiedene Editoren
            const selectors = [
                '.editor-header__settings',               // Ältere Gutenberg-Version
                '.edit-post-header__settings',            // Neuere Gutenberg-Version
                '.edit-post-header-toolbar__right',       // Alternative Gutenberg-Position
                '.editor-post-publish-panel__header',     // Publish-Panel
                '.jet-engine-meta-box-holder',            // JetEngine Meta Box
                '.elementor-panel-footer-content'         // Elementor
            ];
            
            let buttonInserted = false;
            
            // Durch alle Selektoren iterieren und den ersten verwenden, der gefunden wird
            for (let i = 0; i < selectors.length; i++) {
                const selector = selectors[i];
                const controls = $(selector);
                
                if (controls.length && !controls.find('.ir-sync-button-added').length) {
                    const wrapper = $('<div class="ir-sync-button-added" style="display:flex; align-items:center; gap:0.75em; margin-left:auto;"></div>');
                    wrapper.append(syncButton).append(syncStatus);
                    controls.append(wrapper);
                    buttonInserted = true;
                    
                    if (irSyncData.debugMode) {
                        console.log('IR Tours Sync - Button eingefügt in:', selector);
                    }
                    
                    break;
                }
            }
            
            // Fallback - in der Nähe des Speichern-Buttons platzieren
            if (!buttonInserted) {
                const saveBtn = $('.editor-post-publish-button__button');
                
                if (saveBtn.length && !$('.ir-sync-button-added').length) {
                    const wrapper = $('<div class="ir-sync-button-added" style="display:flex; align-items:center; gap:0.75em; margin:10px 0;"></div>');
                    wrapper.append(syncButton).append(syncStatus);
                    saveBtn.parent().before(wrapper);
                    buttonInserted = true;
                    
                    if (irSyncData.debugMode) {
                        console.log('IR Tours Sync - Button als Fallback eingefügt');
                    }
                } else {
                    // Zweiter Fallback: Für jedes konfigurierte Mapping einen Button hinzufügen
                    if (irSyncData.mappings) {
                        $.each(irSyncData.mappings, function(id, mapping) {
                            if (mapping.active && mapping.post_type === irSyncData.postType) {
                                const metaFieldSelector = `input[name^="${mapping.meta_field}["], select[name^="${mapping.meta_field}"]`;
                                const metaField = $(metaFieldSelector).first();
                                
                                if (metaField.length && !metaField.closest('.cx-control, .components-panel').find('.ir-sync-button-added').length) {
                                    const wrapper = $('<div class="ir-sync-button-added" style="display:flex; align-items:center; gap:0.75em; margin:10px 0 20px;"></div>');
                                    const clonedButton = syncButton.clone(true);
                                    const clonedStatus = syncStatus.clone();
                                    
                                    // Event-Handler an den geklonten Button binden
                                    clonedButton.on('click', function() {
                                        syncButton.trigger('click');
                                    });
                                    
                                    wrapper.append(clonedButton).append(clonedStatus);
                                    metaField.closest('.cx-control, .components-panel').after(wrapper);
                                    buttonInserted = true;
                                    
                                    if (irSyncData.debugMode) {
                                        console.log(`IR Tours Sync - Button neben "${mapping.meta_field}" eingefügt`);
                                    }
                                }
                            }
                        });
                    }
                }
            }
            
            return buttonInserted;
        }
        
        // Beobachter für DOM-Änderungen
        const observer = new MutationObserver(function(mutations) {
            if (!$('.ir-sync-button-added').length) {
                insertSyncButton();
            }
        });
        
        // Beobachtungskonfiguration mit besserer Performance
        observer.observe(document.body, { 
            childList: true, 
            subtree: true,
            attributes: false,
            characterData: false
        });
        
        // Mehrere Versuche, um sicherzustellen, dass der Button eingefügt wird
        setTimeout(insertSyncButton, 500);
        setTimeout(insertSyncButton, 1500);
        setTimeout(insertSyncButton, 3000);
    });
})(jQuery);