# Bedienung

Im Browser die eigene Adresse mit `/notizblock/` öffnen und das gemeinsame Passwort eingeben. Auf iOS oder Android kann eine Browser-Verknüpfung zum Startbildschirm angelegt werden. Die Anwendung benötigt eine Netzwerkverbindung und bietet keinen Offlinebetrieb.

## Notizen, Listen und Bilder

Das Plus-Menü öffnet entweder eine neue Notiz oder die direkte Bildauswahl. Ein Bild braucht keinen Titel oder Text; über „Text hinzufügen“ lässt sich später etwas ergänzen. „Alle Notizen“ zeigt aktive Einträge, „Bilder“ nur aktive Einträge mit Bildern, „Papierkorb“ die gelöschten Einträge während der Wiederherstellungsfrist. Die Suche durchsucht den aktiven Bestand der gewählten Ansicht.

Bei Listen öffnet „＋ Listenpunkt“ ein neues Feld. Enter oder die Diskette übernimmt den Eintrag, das Kreuz verwirft den Entwurf. Ein nicht leerer Listenentwurf wird beim manuellen Speichern oder Wechseln der Notiz ebenfalls übernommen.

Text und bestehende Listenpunkte speichern nach etwa 800 ms Tipp-Pause automatisch. „Gespeichert“ bedeutet, dass der Server bestätigt hat. Bei Verbindungsfehlern bleiben die Eingaben zunächst im offenen Browser und es folgen erneute Versuche. Vor Schließen oder Neuladen sicherstellen, dass gespeichert wurde. Die Warnung beim Verlassen kann vom Browser eingeschränkt werden.

## Gemeinsam bearbeiten

Beide Personen arbeiten im selben Datenbestand. Sichtbare Browseransichten prüfen ungefähr alle drei Sekunden auf Änderungen. Unabhängige Änderungen werden zusammengeführt. Bei überlappenden Änderungen stehen beide Textvarianten untereinander, bei Titeln mit „ / “ getrennt. Komplexe Überschneidungen können Wiederholungen erzeugen, die man anschließend selbst bereinigt. Die App entscheidet nicht, welche Aussage richtig ist.

Text und Häkchen eines Listenpunkts werden getrennt verglichen. Gleichzeitiges Entfernen und Bearbeiten erhält den bearbeiteten Punkt. Gelöschte ganze Notizen werden nicht durch Autosave wieder aktiv. Bei einer Fehlermeldung zuerst die eigenen Eingaben sichern, bevor neu geladen wird.

## Papierkorb und Speicher

„Löschen“ verschiebt die ganze Notiz einschließlich Bilder in den Papierkorb. Dort ist sie 30 Tage wiederherstellbar. Danach folgt ein verborgenes Serverarchiv; **die Inhalte werden nicht endgültig gelöscht**. Der Betreiber kann sie nach einer Sicherung gezielt wiederherstellen. Eine sichtbare Versionshistorie gibt es bisher nicht.

JPEG, PNG, WebP und GIF bis 10 MiB pro Datei; kein HEIC. Maximal 2 GiB gespeicherte Bilder insgesamt, inklusive Papierkorb und Archiv. Ab 1600 MiB erscheint ein Hinweis. Das ist kein gesamtes Speicherlimit: Datenbank, Textrevisionen und Backups können weiter wachsen. Bilder lassen sich bisher nur zusammen mit ihrem Eintrag in den Papierkorb verschieben.

Abmelden beendet die aktuelle Sitzung. Ein Passwortwechsel beendet alle Sitzungen.
