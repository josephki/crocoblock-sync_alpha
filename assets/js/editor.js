/**
 * Editor-Skript für IR Tours Sync
 */
// Nativer JavaScript-Code zur DOM-Manipulation
document.addEventListener('DOMContentLoaded', function() {
    console.log('IR Tours Sync - DOM READY - NATIVER CODE');
    
    // Prüfen, ob jQuery verfügbar ist
    if (typeof jQuery === 'undefined') {
        console.error('IR Tours Sync - jQuery ist nicht verfügbar! Verwende native DOM-Manipulation');
    } else {
        console.log('IR Tours Sync - jQuery ist verfügbar, Version:', jQuery.fn.jquery);
    }
    
    // Variablen initialisieren
    let syncWasClicked = false;
    let isSyncing = false;
    
    // Funktion zum gezielten Einfügen des Sync-Buttons neben dem Aktualisieren-Button
    function addSyncButtonNextToPublish() {
        console.log('IR Tours Sync - Versuche Button neben dem Aktualisieren-Button einzufügen');
        
        // Finden des publish-Buttons im publishing-action Container
        const publishingAction = document.getElementById('publishing-action');
        const publishButton = document.getElementById('publish');
        
        // Wenn beide Elemente existieren und der Sync-Button noch nicht vorhanden ist
        if (publishingAction && publishButton && !document.getElementById('ir-sync-inline-button')) {
            console.log('IR Tours Sync - Publishing-Action und Publish-Button gefunden');
            
            // Neuen Button erstellen
            const syncButton = document.createElement('button');
            syncButton.id = 'ir-sync-inline-button';
            syncButton.type = 'button';
            syncButton.className = 'button button-primary';
            syncButton.textContent = 'SYNCHRONISIEREN';
            
            // Wichtige Inline-Stile für korrekte Ausrichtung
            syncButton.style.cssText = `
                display: inline-block !important;
                margin-right: 10px !important;
                vertical-align: middle !important;
                background-color: #d63638 !important;
                color: white !important;
                border-color: #d63638 !important;
                min-height: 30px !important;
                line-height: 2.15384615 !important;
                padding: 0 10px !important;
            `;
            
            // Button vor dem Aktualisieren-Button einfügen
            publishingAction.insertBefore(syncButton, publishButton);
            
            // Event-Listener hinzufügen
            syncButton.addEventListener('click', handleCombinedClick);
            
            console.log('IR Tours Sync - Button erfolgreich neben dem Aktualisieren-Button eingefügt');
            return true;
        }
        
        console.log('IR Tours Sync - Publishing-Action oder Publish-Button nicht gefunden');
        return false;
    }
    
    // Funktion zum Einfügen des Buttons
    function addSyncButton() {
        console.log('IR Tours Sync - Versuche Button einzufügen');
        
        // Zuerst versuchen, den Button neben dem Aktualisieren-Button zu platzieren
        if (addSyncButtonNextToPublish()) {
            console.log('IR Tours Sync - Button wurde neben dem Aktualisieren-Button platziert');
            return;
        }
        
        // Wenn die optimale Platzierung nicht geklappt hat, Alternativmethoden verwenden
        // Durch einen Selektor versuchen, das publishing-action Element zu finden
        let targets = [
            document.querySelector('#publishing-action'),
            document.querySelector('.submitbox #publishing-action'),
            document.querySelector('#submitdiv .submitbox'),
            document.querySelector('#submitpost'),
            document.querySelector('#submitdiv'),
            document.querySelector('#post')
        ];
        
        // Debug-Ausgabe der DOM-Struktur 
        console.log('IR Tours Sync - DOM-Struktur:');
        console.log('publishing-action gefunden:', !!document.querySelector('#publishing-action'));
        console.log('submitbox gefunden:', !!document.querySelector('.submitbox'));
        console.log('submitdiv gefunden:', !!document.querySelector('#submitdiv'));
        console.log('publish-button gefunden:', !!document.querySelector('#publish'));
        
        // HTML-Code für den Button mit allen Inline-Styles
        const buttonHtml = `
            <button type="button" 
                    id="ir-sync-button" 
                    class="button button-primary" 
                    style="display:block !important; 
                           margin:10px 0 !important; 
                           padding:10px !important; 
                           background-color:red !important; 
                           color:white !important; 
                           font-weight:bold !important;
                           width:100% !important;
                           text-align:center !important;
                           border:2px solid black !important;
                           font-size:16px !important;">
                SYNCHRONISIEREN
            </button>
        `;
        
        // Versuchen, den Button in das erste verfügbare Target einzufügen
        let buttonInserted = false;
        for (let i = 0; i < targets.length; i++) {
            const target = targets[i];
            if (target && !buttonInserted) {
                console.log('IR Tours Sync - Target gefunden:', target);
                
                try {
                    // Div für den Button erstellen
                    const buttonWrapper = document.createElement('div');
                    buttonWrapper.id = 'ir-sync-button-wrapper';
                    buttonWrapper.style.cssText = 'display:block !important; margin:10px 0 !important; width:100% !important;';
                    buttonWrapper.innerHTML = buttonHtml;
                    
                    // Vor dem ersten Kind einfügen
                    if (target.firstChild) {
                        target.insertBefore(buttonWrapper, target.firstChild);
                    } else {
                        target.appendChild(buttonWrapper);
                    }
                    
                    console.log('IR Tours Sync - Button erfolgreich eingefügt in:', target);
                    buttonInserted = true;
                    
                    // Event-Listener zum Button hinzufügen
                    document.getElementById('ir-sync-button').addEventListener('click', handleCombinedClick);
                    
                    break;
                } catch (e) {
                    console.error('IR Tours Sync - Fehler beim Einfügen in Target:', e);
                }
            }
        }
        
        // Als absoluten Notfall: Direktes document.write in einen iframe
        if (!buttonInserted) {
            console.log('IR Tours Sync - Kein Target gefunden, verwende Floating-Button');
            
            // Floating-Button erstellen
            const floatDiv = document.createElement('div');
            floatDiv.id = 'ir-sync-float-container';
            floatDiv.style.cssText = `
                position: fixed !important;
                bottom: 20px !important;
                right: 20px !important;
                z-index: 999999 !important;
                background-color: white !important;
                padding: 10px !important;
                border: 3px solid black !important;
                border-radius: 8px !important;
                box-shadow: 0 0 10px rgba(0,0,0,0.5) !important;
            `;
            
            // Button HTML in den Container einfügen
            floatDiv.innerHTML = `
                <button type="button" 
                        id="ir-sync-float-button" 
                        style="display:block !important; 
                               padding:15px !important; 
                               margin:0 !important;
                               background-color:red !important; 
                               color:white !important; 
                               font-weight:bold !important;
                               font-size:18px !important;
                               border:none !important;
                               border-radius:4px !important;
                               cursor:pointer !important;">
                    ⚡ SYNCHRONISIEREN ⚡
                </button>
            `;
            
            // Am Ende des Body einfügen
            document.body.appendChild(floatDiv);
            
            // Event-Listener zum Float-Button hinzufügen
            document.getElementById('ir-sync-float-button').addEventListener('click', handleCombinedClick);
            
            console.log('IR Tours Sync - Floating-Button eingefügt');
        }
    }
    
    // Funktion für den kombinierten Button (Aktualisieren & Sync)
    function handleCombinedClick(e) {
        e.preventDefault();
        
        // Button-Status aktualisieren
        var button = e.target;
        var originalText = button.textContent;
        button.disabled = true;
        button.textContent = '⏳ Synchronisiere...';
        
        // Post-ID aus URL holen
        var urlParams = new URLSearchParams(window.location.search);
        var postId = urlParams.get('post');
        
        if (!postId) {
            // Prüfen, ob die Fehlermeldung angezeigt werden soll
            if (irSyncData && irSyncData.messages && irSyncData.messages.sync_error_active) {
                alert('Fehler: Konnte Post-ID nicht ermitteln.');
            }
            button.disabled = false;
            button.textContent = originalText;
            return;
        }
        
        // AJAX-Request für die Synchronisation
        var xhr = new XMLHttpRequest();
        xhr.open('POST', irSyncData.ajaxurl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    
                    if (response.success) {
                        button.textContent = '✅ Synchronisiert';
                        
                        // Zeige die Erfolgsmeldung nur, wenn sie aktiviert ist
                        if (response.data && response.data.message) {
                            alert(response.data.message);
                        }
                        
                        // Nach erfolgreicher Synchronisation den Publish-Button klicken
                        var publishButton = document.getElementById('publish');
                        if (publishButton) {
                            console.log('IR Tours Sync - Klicke Aktualisieren-Button nach erfolgreicher Synchronisation');
                            publishButton.click();
                        } else {
                            console.log('IR Tours Sync - Aktualisieren-Button nicht gefunden, lade Seite neu');
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        }
                    } else {
                        button.textContent = '❌ Fehler';
                        
                        // Zeige die Fehlermeldung nur, wenn sie aktiviert ist
                        if (irSyncData && irSyncData.messages && irSyncData.messages.sync_error_active) {
                            alert('Fehler bei der Synchronisation: ' + (response.data || 'Unbekannter Fehler'));
                        }
                        
                        setTimeout(function() {
                            button.disabled = false;
                            button.textContent = originalText;
                        }, 2000);
                    }
                } catch (e) {
                    button.textContent = '❌ Fehler';
                    
                    // Zeige die Fehlermeldung nur, wenn sie aktiviert ist
                    if (irSyncData && irSyncData.messages && irSyncData.messages.sync_error_active) {
                        alert('Fehler beim Verarbeiten der Antwort: ' + e.message);
                    }
                    
                    setTimeout(function() {
                        button.disabled = false;
                        button.textContent = originalText;
                    }, 2000);
                }
            } else {
                button.textContent = '❌ Fehler';
                
                // Zeige die Fehlermeldung nur, wenn sie aktiviert ist
                if (irSyncData && irSyncData.messages && irSyncData.messages.sync_error_active) {
                    alert('AJAX-Fehler: ' + xhr.statusText);
                }
                
                setTimeout(function() {
                    button.disabled = false;
                    button.textContent = originalText;
                }, 2000);
            }
        };
        
        xhr.onerror = function() {
            button.textContent = '❌ Fehler';
            
            // Zeige die Fehlermeldung nur, wenn sie aktiviert ist
            if (irSyncData && irSyncData.messages && irSyncData.messages.sync_error_active) {
                alert('Netzwerkfehler bei der Anfrage.');
            }
            
            setTimeout(function() {
                button.disabled = false;
                button.textContent = originalText;
            }, 2000);
        };
        
        // Nonce aus globaler irSyncData verwenden
        var nonce = irSyncData.nonce;
        
        // Daten senden
        xhr.send('action=ir_manual_sync&post_id=' + postId + '&nonce=' + nonce);
    }
    
    // Mehrere Versuche zur Button-Platzierung
    // Direkte Ausführung und verzögerte Ausführungen
    addSyncButton();
    
    // Wiederholt versuchen, den Button speziell neben dem Aktualisieren-Button zu platzieren
    addSyncButtonNextToPublish();
    setTimeout(addSyncButtonNextToPublish, 500);
    setTimeout(addSyncButtonNextToPublish, 1000);
    setTimeout(addSyncButtonNextToPublish, 2000);
    setTimeout(addSyncButtonNextToPublish, 3000);
    
    // Alternativmethoden mit Verzögerung
    setTimeout(addSyncButton, 1000);
    setTimeout(addSyncButton, 3000);
    
    // Sicherheitscheck nach 5 Sekunden
    setTimeout(function() {
        if (!document.getElementById('ir-sync-button') && 
            !document.getElementById('ir-sync-float-button') && 
            !document.getElementById('ir-sync-inline-button')) {
            
            console.log('IR Tours Sync - KEIN BUTTON NACH 5 SEKUNDEN, DIREKTER DOM-EINGRIFF');
            
            // Versuchen, das publish-Element durch Suche im gesamten DOM zu finden
            let publishingAction = null;
            document.querySelectorAll('*').forEach(function(el) {
                if (el.id === 'publishing-action' || 
                    (el.className && el.className.includes && el.className.includes('publishing-action'))) {
                    publishingAction = el;
                    console.log('IR Tours Sync - Publishing-Action durch DOM-Suche gefunden:', el);
                }
            });
            
            if (publishingAction) {
                // Button erstellen
                const button = document.createElement('button');
                button.id = 'ir-sync-emergency-button';
                button.className = 'button button-primary';
                button.type = 'button';
                button.textContent = 'NOTFALL-SYNC';
                button.style.cssText = 'display:inline-block !important; margin-right:10px !important; padding:10px !important; background-color:red !important; color:white !important; font-weight:bold !important;';
                
                // Button einfügen
                const publishButton = publishingAction.querySelector('#publish');
                if (publishButton) {
                    publishingAction.insertBefore(button, publishButton);
                } else {
                    publishingAction.insertBefore(button, publishingAction.firstChild);
                }
                
                // Event-Listener hinzufügen
                button.addEventListener('click', handleCombinedClick);
                
                console.log('IR Tours Sync - Notfall-Button eingefügt');
            } else {
                // Letzter Ausweg: In die submitbox einfügen
                const floatButton = document.createElement('button');
                floatButton.id = 'ir-sync-last-resort-button';
                floatButton.type = 'button';
                floatButton.textContent = '💾 SYNCHRO 💾';
                floatButton.style.cssText = `
                    position: fixed !important;
                    bottom: 30px !important;
                    right: 30px !important;
                    padding: 20px !important;
                    background-color: red !important;
                    color: white !important;
                    font-weight: bold !important;
                    font-size: 20px !important;
                    border: 4px solid black !important;
                    border-radius: 10px !important;
                    box-shadow: 0 0 15px rgba(0,0,0,0.7) !important;
                    z-index: 9999999 !important;
                    cursor: pointer !important;
                `;
                
                document.body.appendChild(floatButton);
                floatButton.addEventListener('click', handleCombinedClick);
                
                console.log('IR Tours Sync - Absoluter Notfall-Button eingefügt');
            }
        }
    }, 5000);
});

// Sofort nach dem DOM-Laden einen aggressiven Ansatz zum Einfügen des Buttons verwenden
$(document).ready(function() {
    // Direkte Funktion für den letzten Versuch
    function forceInsertButton() {
        console.log('IR Tours Sync - DIREKTER HTML-INJECT VERSUCH');
        
        // Zuerst versuchen, den Button direkt neben dem Aktualisieren-Button zu platzieren
        const publishingAction = document.getElementById('publishing-action');
        const publishButton = document.getElementById('publish');
        
        if (publishingAction && publishButton && !document.getElementById('ir-sync-jquery-button')) {
            // Direktes Einfügen vor dem Publish-Button
            const syncButton = document.createElement('button');
            syncButton.id = 'ir-sync-jquery-button';
            syncButton.type = 'button';
            syncButton.className = 'button button-primary';
            syncButton.textContent = 'SYNCHRONISIEREN';
            syncButton.style.cssText = `
                display: inline-block !important;
                margin-right: 10px !important;
                vertical-align: middle !important;
                background-color: #d63638 !important;
                color: white !important;
                border-color: #d63638 !important;
                min-height: 30px !important;
                line-height: 2.15384615 !important;
                padding: 0 10px !important;
            `;
            
            publishingAction.insertBefore(syncButton, publishButton);
            
            $(syncButton).on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('IR Tours Sync - jQuery-Button geklickt');
                
                // Verhindert Doppelklicks
                if (window.isSyncing) return;
                
                window.isSyncing = true;
                window.syncWasClicked = true;
                
                // Post-ID aus URL holen
                const urlParams = new URLSearchParams(window.location.search);
                const postId = urlParams.get('post');
                
                if (!postId) {
                    alert('Fehler: Konnte Post-ID nicht ermitteln.');
                    window.isSyncing = false;
                    return;
                }
                
                // Button-Status aktualisieren
                $(this).prop('disabled', true).text('⏳ Synchronisiere...');
                
                // AJAX-Aufruf
                $.ajax({
                    url: irSyncData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ir_manual_sync',
                        post_id: postId,
                        nonce: irSyncData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $(syncButton).text('✅ Synchronisiert');
                            
                            let successMessage = 'Felder erfolgreich synchronisiert.';
                            if (response.data && response.data.count) {
                                successMessage = `Felder erfolgreich synchronisiert. (${response.data.count} Terme gesetzt)`;
                            }
                            
                            alert(successMessage);
                            
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        } else {
                            $(syncButton).text('❌ Fehler');
                            alert('Fehler bei der Synchronisation: ' + (response.data || 'Unbekannter Fehler'));
                            
                            setTimeout(function() {
                                $(syncButton).prop('disabled', false).text('SYNCHRONISIEREN');
                                window.isSyncing = false;
                            }, 2000);
                        }
                    },
                    error: function(xhr, status, error) {
                        $(syncButton).text('❌ Fehler');
                        alert('AJAX-Fehler: ' + error);
                        
                        setTimeout(function() {
                            $(syncButton).prop('disabled', false).text('SYNCHRONISIEREN');
                            window.isSyncing = false;
                        }, 2000);
                    }
                });
            });
            
            console.log('IR Tours Sync - jQuery-Button direkt neben dem Aktualisieren-Button eingefügt');
            return true;
        }
        
        // Wenn die optimale Platzierung nicht funktioniert hat, setze als Notfall den Floating-Button
        console.log('IR Tours Sync - Konnte Button nicht optimal platzieren, verwende Floating-Button');
        
        // Sofort den Floating-Button einfügen als absoluten Notfall
        const floatButtonHtml = `
            <div id="ir-sync-absolute-wrapper" style="
                position: fixed !important;
                bottom: 20px !important;
                right: 20px !important;
                z-index: 999999 !important;
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            ">
                <button 
                    type="button" 
                    id="ir-sync-absolute-button"
                    class="button button-primary ir-sync-button" 
                    style="
                        display: block !important;
                        visibility: visible !important;
                        opacity: 1 !important;
                        padding: 15px 20px !important;
                        background-color: #e44 !important;
                        color: white !important;
                        text-align: center !important;
                        font-weight: bold !important;
                        font-size: 16px !important;
                        border: 3px solid black !important;
                        border-radius: 8px !important;
                        box-shadow: 0 4px 8px rgba(0,0,0,0.5) !important;
                        cursor: pointer !important;
                        text-transform: uppercase !important;
                    ">
                    📦 Synchronisieren 📦
                </button>
            </div>
        `;
        
        // An body anhängen mit reinem JavaScript
        document.body.insertAdjacentHTML('beforeend', floatButtonHtml);
        console.log('IR Tours Sync - Absoluter Button an body angehängt');
        
        // Event Listener zum Absolut-Button hinzufügen
        document.getElementById('ir-sync-absolute-button').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('IR Tours Sync - Absolut-Button geklickt');
            
            // Verhindert Doppelklicks
            if (window.isSyncing) return;
            
            window.isSyncing = true;
            window.syncWasClicked = true;
            
            // Post-ID aus URL holen
            const urlParams = new URLSearchParams(window.location.search);
            const postId = urlParams.get('post');
            
            if (!postId) {
                alert('Fehler: Konnte Post-ID nicht ermitteln.');
                window.isSyncing = false;
                return;
            }
            
            // Button-Status aktualisieren
            const button = document.getElementById('ir-sync-absolute-button');
            button.disabled = true;
            button.textContent = '⏳ Synchronisiere...';
            
            // AJAX-Aufruf für die Synchronisation mit nativem JavaScript
            const xhr = new XMLHttpRequest();
            xhr.open('POST', irSyncData.ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            button.textContent = '✅ Synchronisiert';
                            
                            let successMessage = 'Felder erfolgreich synchronisiert.';
                            if (response.data && response.data.count) {
                                successMessage = `Felder erfolgreich synchronisiert. (${response.data.count} Terme gesetzt)`;
                            }
                            
                            alert(successMessage);
                            
                            // Nach erfolgreicher Synchronisation Seite neu laden
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        } else {
                            button.textContent = '❌ Fehler';
                            alert('Fehler bei der Synchronisation: ' + (response.data || 'Unbekannter Fehler'));
                            
                            setTimeout(function() {
                                button.disabled = false;
                                button.textContent = '📦 Synchronisieren 📦';
                                window.isSyncing = false;
                            }, 2000);
                        }
                    } catch (e) {
                        button.textContent = '❌ Fehler';
                        alert('Fehler beim Verarbeiten der Antwort: ' + e.message);
                        
                        setTimeout(function() {
                            button.disabled = false;
                            button.textContent = '📦 Synchronisieren 📦';
                            window.isSyncing = false;
                        }, 2000);
                    }
                } else {
                    button.textContent = '❌ Fehler';
                    alert('AJAX-Fehler: ' + xhr.statusText);
                    
                    setTimeout(function() {
                        button.disabled = false;
                        button.textContent = '📦 Synchronisieren 📦';
                        window.isSyncing = false;
                    }, 2000);
                }
            };
            
            xhr.onerror = function() {
                button.textContent = '❌ Fehler';
                alert('Netzwerkfehler bei der Anfrage.');
                
                setTimeout(function() {
                    button.disabled = false;
                    button.textContent = '📦 Synchronisieren 📦';
                    window.isSyncing = false;
                }, 2000);
            };
            
            // Daten senden
            xhr.send('action=ir_manual_sync&post_id=' + postId + '&nonce=' + irSyncData.nonce);
        });
    }
    
    // Direkt den Button einfügen und zusätzlich nach kurzer Verzögerung nochmals versuchen
    forceInsertButton();
    setTimeout(forceInsertButton, 1000);
});