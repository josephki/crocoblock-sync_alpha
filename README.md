# Crocoblock Sync

Ein konfigurierbares WordPress-Plugin zur Synchronisation von Meta-Feldern mit Taxonomien für JetEngine/Crocoblock.

## Beschreibung

Das Crocoblock Sync Plugin ermöglicht es, Meta-Felder in WordPress automatisch mit entsprechenden Taxonomien zu synchronisieren. Es wurde speziell für den Einsatz mit JetEngine/Crocoblock entwickelt, funktioniert aber auch mit anderen Meta-Feld-Lösungen.

## Hauptfunktionen

- **Bidirektionale Synchronisation**: Synchronisiert Meta-Felder mit Taxonomien und umgekehrt
- **Automatisches Aktualisieren**: Lädt die Seite nach erfolgreicher Synchronisation automatisch neu, damit Änderungen sofort sichtbar sind
- **Intelligente Warnmeldungen**: Zeigt Warnungen nur dann an, wenn sie tatsächlich relevant sind
- **Universelle Feldunterstützung**: Funktioniert mit verschiedenen Meta-Feld-Formaten (JetEngine, Standard-WordPress, usw.)
- **"Synchronisieren & Speichern"-Button**: Integriert einen praktischen Button im Gutenberg/Elementor-Editor
- **Dynamische Termaktualisierung**: Fügt neue Taxonomie-Terme automatisch zu allen relevanten UI-Elementen hinzu
- **Konfigurierbare Mappings**: Erlaubt benutzerdefinierte Zuordnungen zwischen Meta-Feldern und Taxonomien
- **Anpassbare Meldungen**: Alle Nachrichten und Warnungen können über die Einstellungen angepasst werden
- **Multiple-Selection-Warnung**: Optionale Warnungen bei mehreren ausgewählten Termen, wenn dies nicht erwünscht ist
- **Intelligente Seitenaktualisierung**: Aktualisiert nur relevante Post-Editor-Seiten und vermeidet Konflikte mit anderen Skripten
- **Snippet-Kompatibilität**: Erkennt externe Code-Snippets und verhindert unerwünschte Seitenaktualisierungen

## Installation

1. Lade das Plugin als ZIP-Datei herunter
2. Gehe im WordPress-Admin zu "Plugins" > "Installieren" > "Plugin hochladen"
3. Wähle die ZIP-Datei aus und klicke auf "Jetzt installieren"
4. Aktiviere das Plugin

## Konfiguration

Nach der Aktivierung findest du die Einstellungen unter "Einstellungen" > "Crocoblock Sync". Hier kannst du:

### 1. Feld-Zuordnungen konfigurieren

- **Meta-Feld**: Der Name des Meta-Felds (z.B. "reisethemen_meta")
- **Taxonomie**: Die zugehörige Taxonomie (z.B. "reisethemen")
- **Post-Typ**: Der Post-Typ, für den die Synchronisation gelten soll
- **Aktiv**: Schaltet die Zuordnung ein oder aus
- **Mehrere erlauben**: Legt fest, ob mehrere Terme ausgewählt werden dürfen

### 2. Benutzerdefinierte Nachrichten anpassen

- Warnung bei mehreren ausgewählten Termen
- Text für den Synchronisationsbutton
- Erinnerungsnachricht bei vergessener Synchronisation
- Erfolgs- und Fehlermeldungen
- Nachricht bei neu erstellten Terms

## Verwendung

1. Im Editor von Beiträgen der konfigurierten Post-Typen erscheint ein "Synchronisieren & Speichern"-Button in der oberen rechten Ecke
2. Wähle die gewünschten Werte in deinen Meta-Feldern aus
3. Klicke auf den "Synchronisieren & Speichern"-Button
4. Die Synchronisation wird durchgeführt, der Beitrag gespeichert und die Seite automatisch neu geladen

### Ablauf im Hintergrund:

1. Meta-Felder werden ausgelesen und mit Taxonomien synchronisiert
2. Neue Taxonomie-Terme werden bei Bedarf automatisch erstellt
3. Die Benutzeroberfläche wird aktualisiert, um neue Terme anzuzeigen
4. Der Beitrag wird gespeichert
5. Die Seite wird neu geladen, um alle Änderungen anzuzeigen (nur auf Post-Editor-Seiten)

## Technische Informationen

- Erfordert WordPress 5.0 oder höher
- Erfordert PHP 7.4 oder höher
- Kompatibel mit Gutenberg und Elementor
- Unterstützt mehrere Meta-Feld-Taxonomie-Zuordnungen
- Intelligente Fehlererkennung und Behandlung
- Alphabetische Sortierung der Taxonomie-Terme
- Vermeidet Konflikte mit externen Snippets und anderen Plugins

## Kompatibilität mit Snippets

Das Plugin wurde entwickelt, um ordnungsgemäß mit anderen Code-Snippets zu arbeiten:

- Automatische Erkennung von Code-Snippets über URL-Parameter
- Verwendung eines globalen Refresh-Locks zur Vermeidung von Konflikten
- Präzise Erkennung von Post-Editor-Umgebungen
- Ausführliche Konsolenausgaben für Debugging-Zwecke

## Entwickler

Joseph Kisler - Webwerkstatt, Freiung 16/2/4, A-4600 Wels

## Versionsverlauf

### 1.2
- Verbessertes Seitenaktualisierungssystem mit strikter Typ- und URL-Validierung
- Vermeidung unerwünschter wiederholter Refreshes
- Einführung eines globalen Refresh-Locks zur Vermeidung von Konflikten
- Automatische Snippet-Erkennung zur Verbesserung der Kompatibilität
- Intelligentere Seitenaktualisierung, die nur auf relevanten Seiten stattfindet
- Detaillierte Debug-Ausgaben für eine einfachere Fehlerbehebung

### 1.1
- Automatisches Neuladen der Seite nach Synchronisation
- Verbesserte Warnmeldungslogik
- Keine redundanten Warnungen mehr bei bereits durchgeführter Synchronisation
- Entfernung spezifischer Feldbehandlungen zugunsten eines generischen Ansatzes
- Allgemeine Optimierungen und Bugfixes

### 1.0
- Erstveröffentlichung
- Konfigurierbare Feld-Zuordnungen
- Anpassbare Meldungen
- Unterstützung für Gutenberg und Elementor