# Terminfinder
Eine Applikation um im Freundeskreis Termine zu synchronisieren



EIn Clickdummy der auch die Optik vorgibt ist in clickdummy.html hinterlegt, bitte daran orientieren.
Hier sind die User Stories für die Verabredungs-Synchronisierungs-App:

## Epic: Verabredung Synchronisieren - Terminkoordination mit Zeitfenstern

---

### **User Story 1: Gruppe erstellen und beitreten**
**Als** Nutzer  
**möchte ich** eine neue Gruppe mit einem Code und Passwort erstellen oder einer bestehenden Gruppe beitreten  
**damit** ich meine Verfügbarkeit mit anderen teilen kann.

**Akzeptanzkriterien:**
- Eingabefelder für Name, Gruppen-Code und Passwort sind vorhanden
- Beim ersten Speichern mit einem neuen Code wird das Passwort für diese Gruppe festgelegt
- Bei bestehenden Gruppen muss das korrekte Passwort eingegeben werden
- Fehlermeldung bei falschem Passwort
- Validierung: Alle Felder sind Pflichtfelder

---

### **User Story 2: Zeitfenster für einzelne Tage auswählen**
**Als** Nutzer  
**möchte ich** für jeden Tag einzelne Zeitfenster (Vormittag, Nachmittag, Abend) auswählen können  
**damit** ich genau angeben kann, wann ich verfügbar bin.

**Akzeptanzkriterien:**
- Kalender-Ansicht mit Monatsnavigation (Vor/Zurück-Buttons)
- Jeder Tag zeigt drei Zeitfenster: Vormittag (VM), Nachmittag (NM), Abend (AB)
- Einzelne Zeitfenster sind durch Klick auswählbar/abwählbar
- Ausgewählte Zeitfenster werden visuell hervorgehoben (z.B. blau)
- Mehrfachauswahl pro Tag möglich

---

### **User Story 3: Ganzen Tag schnell auswählen**
**Als** Nutzer  
**möchte ich** durch Klick auf die Tageszahl alle drei Zeitfenster gleichzeitig an- oder abwählen  
**damit** ich schnell ganze Tage markieren kann.

**Akzeptanzkriterien:**
- Klick auf Tageszahl wählt alle drei Zeitfenster (VM, NM, AB) aus
- Wenn bereits alle drei ausgewählt sind, werden sie durch erneuten Klick alle abgewählt
- Visuelles Feedback beim Hover über die Tageszahl

---

### **User Story 4: Verfügbarkeit speichern**
**Als** Nutzer  
**möchte ich** meine ausgewählten Zeitfenster speichern  
**damit** andere Gruppenmitglieder meine Verfügbarkeit sehen können.

**Akzeptanzkriterien:**
- "Verfügbarkeit speichern" Button ist vorhanden
- Validierung: Mindestens ein Zeitfenster muss ausgewählt sein
- Validierung: Name, Code und Passwort müssen ausgefüllt sein
- Erfolgsbestätigung nach dem Speichern (z.B. grüne Meldung)
- Daten werden persistent gespeichert (shared storage)
- Bestehende Einträge des gleichen Nutzers werden überschrieben

---

### **User Story 5: Gemeinsame Termine finden (Perfect Matches)**
**Als** Nutzer  
**möchte ich** sehen, an welchen Tagen und zu welchen Zeitfenstern ALLE Gruppenmitglieder verfügbar sind  
**damit** wir den optimalen Termin finden.

**Akzeptanzkriterien:**
- Separate Ansicht "Gemeinsame Termine" (Tab)
- Liste aller Tage, an denen alle Teilnehmer Zeit haben
- Pro Tag Anzeige aller gemeinsamen Zeitfenster (VM, NM, AB)
- Formatierung: Datum in deutscher Schreibweise mit Wochentag
- Visuell hervorgehoben (z.B. grüne Karten)
- Sortierung chronologisch nach Datum
- Hinweis wenn noch keine gemeinsamen Termine gefunden wurden

---

### **User Story 6: Teilweise verfügbare Termine sehen**
**Als** Nutzer  
**möchte ich** auch Termine sehen, an denen nur einige (aber nicht alle) Teilnehmer verfügbar sind  
**damit** ich alternative Optionen in Betracht ziehen kann.

**Akzeptanzkriterien:**
- Separate Sektion für "Teilweise verfügbar"
- Anzeige von Datum und Zeitfenster
- Liste der verfügbaren Teilnehmer
- Liste der nicht verfügbaren Teilnehmer
- Visuell anders dargestellt als perfekte Matches (z.B. gelb/orange)
- Sortierung chronologisch

---

### **User Story 7: Alle Teilnehmer und deren Verfügbarkeit einsehen**
**Als** Nutzer  
**möchte ich** eine Übersicht aller Gruppenmitglieder und deren komplette Verfügbarkeit sehen  
**damit** ich nachvollziehen kann, wer wann Zeit hat.

**Akzeptanzkriterien:**
- Separate Ansicht "Alle Teilnehmer" (Tab)
- Liste aller Teilnehmer mit Namen
- Pro Teilnehmer: Alle verfügbaren Tage mit Zeitfenstern
- Format: "TT.MM.JJJJ (VM, NM, AB)"
- Hinweis wenn noch keine Teilnehmer vorhanden sind

---

### **User Story 8: Navigation zwischen verschiedenen Ansichten**
**Als** Nutzer  
**möchte ich** einfach zwischen verschiedenen Ansichten wechseln können  
**damit** ich alle Informationen übersichtlich finde.

**Akzeptanzkriterien:**
- Tab-Navigation mit drei Bereichen:
  - "Meine Verfügbarkeit" (Eingabe & Kalender)
  - "Gemeinsame Termine" (Perfect & Partial Matches)
  - "Alle Teilnehmer" (Übersicht)
- Aktiver Tab ist visuell hervorgehoben
- Smooth Transition zwischen Tabs
- Responsive Design für Mobile und Desktop

---

### **User Story 9: Mehrere Monate durchsuchen**
**Als** Nutzer  
**möchte ich** durch verschiedene Monate navigieren können  
**damit** ich auch weit in der Zukunft liegende Termine planen kann.

**Akzeptanzkriterien:**
- Vor- und Zurück-Buttons für Monatsnavigation
- Aktuelle Monatsanzeige (z.B. "Januar 2026")
- Kalender passt sich dynamisch an
- Bereits ausgewählte Zeitfenster bleiben beim Monatswechsel erhalten

---

### **User Story 10: Passwortschutz für Gruppenintegrität**
**Als** Nutzer  
**möchte ich** dass nur Personen mit dem richtigen Passwort Zugriff auf die Gruppendaten haben  
**damit** unsere Termine geschützt sind.

**Akzeptanzkriterien:**
- Passwort wird beim ersten Speichern einer neuen Gruppe gesetzt
- Alle weiteren Zugriffe benötigen das korrekte Passwort
- Ohne korrektes Passwort: Keine Daten sichtbar, kein Speichern möglich
- Fehlermeldung bei falschem Passwort
- Passwort-Feld als Password-Type (Eingabe nicht sichtbar)

---

### **User Story 11: Gruppen laden und synchronisieren**
**Als** Nutzer  
**möchte ich** dass die Gruppendaten automatisch geladen werden, wenn ich Code und Passwort eingebe  
**damit** ich sofort die aktuellen Verfügbarkeiten sehe.

**Akzeptanzkriterien:**
- Automatisches Laden beim Verlassen der Eingabefelder (blur event)
- Laden auch möglich durch Enter-Taste
- Shared Storage für gruppenübergreifende Synchronisation
- Fallback auf lokale Speicherung wenn Storage nicht verfügbar
- Fehlermeldung bei Verbindungsproblemen

---

### **User Story 12: Responsive Design**
**Als** Nutzer auf verschiedenen Geräten  
**möchte ich** die App auf Desktop, Tablet und Smartphone nutzen können  
**damit** ich überall meine Verfügbarkeit eintragen kann.

**Akzeptanzkriterien:**
- Responsive Layout für alle Bildschirmgrößen
- Mobile: Kalender-Grid passt sich an
- Mobile: Tabs werden untereinander dargestellt
- Touch-optimierte Buttons und Zeitfenster
- Lesbare Schriftgrößen auf allen Geräten

---

## Technische Anforderungen

### **Tech Story 1: Datenspeicherung**
- Shared Storage API für gruppenübergreifende Datenpersistenz
- Datenstruktur: `{ [userName]: { [date]: [timeSlots] } }`
- Separate Speicherung von Passwörtern: `meeting_pw_${code}`
- Daten-Keys: `meeting_${code}`

### **Tech Story 2: Datenformat**
- Datumsformat: YYYY-MM-DD (ISO 8601)
- Zeitfenster: Array mit Werten ["morning", "afternoon", "evening"]
- Namen als String-Keys im Gruppen-Objekt

### **Tech Story 3: UI Framework & Styling**
- Pure HTML/CSS/JavaScript (kein Framework erforderlich)
- Modernes, gradient-basiertes Design
- Animationen für bessere UX (fade-in, slide-up)
- Farbschema:
  - Primary: #667eea bis #764ba2 (Gradient)
  - Success/Perfect Match: #10b981
  - Warning/Partial Match: #fbbf24
  - Selection: #667eea

---

## Nicht-funktionale Anforderungen

1. **Performance**: Kalender-Rendering unter 100ms
2. **Usability**: Intuitive Bedienung ohne Anleitung
3. **Accessibility**: Semantisches HTML, Keyboard-Navigation
4. **Security**: Passwörter im Storage (Basic Protection, keine Verschlüsselung erforderlich)
5. **Browser-Kompatibilität**: Moderne Browser (Chrome, Firefox, Safari, Edge - letzte 2 Versionen)

---

Möchtest du noch zusätzliche Details zu bestimmten Stories oder weitere User Stories für spezielle Features?
