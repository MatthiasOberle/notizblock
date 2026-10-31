# Betrieb, Backups und Updates

## Speicher und Aufbewahrung

Daten: `/var/lib/notizblock/notes.sqlite` mit SQLite-WAL-Dateien, `uploads/`, `password.hash`, `tmp/`. `tmp/` ist für temporäre Uploads. Datenverzeichnis und Unterordner 0700, Datenbank/Hash/Bilder nur für `notizblock` lesbar und schreibbar. Code unter `/opt/notizblock` bleibt root-eigen. `NOTIZBLOCK_DATA` dient in der Entwicklung als alternativer Datenpfad; der Produktionspool verwendet den Standardpfad, das Passwortwerkzeug ebenfalls.

`maintenance.php` verschiebt abgelaufene Papierkorbeinträge nach 30 Tagen ins verborgene Archiv (`deleted=2`). Liste, Einzelabruf und Bild-API blenden sie aus, die Dateien bleiben bestehen. `revisions` und `mutations` wachsen derzeit ohne automatische Bereinigung. Den gesamten Datenordner und freien Plattenplatz überwachen; der UI-Hinweis bezieht sich nur auf Bildgrößen. Cron-Protokoll und PHP-FPM-Fehler kontrollieren, keine Inhalte oder Passwörter protokollieren.

## Konsistente Sicherung

Die App richtet **keine automatischen Backups** ein. Betreiber müssen regelmäßige, verschlüsselte und getrennt aufbewahrte Sicherungen organisieren und Wiederherstellungen testen. Archiv und Papierkorb sind keine Sicherung gegen Serververlust.

Für ein einfaches, konsistentes Snapshot mit Bildern: Wartungsfenster, ausschließlich den Notizblock-FPM-Pool vorübergehend über seine Konfiguration deaktivieren oder die Anwendung durch eine befristete, gezielte Nginx-503-Regel abschirmen; Konfiguration sichern und prüfen. Keine anderen Websites pauschal anhalten. Bereits laufende Schreibanfragen auslaufen lassen. Dann als Administrator:

```sh
sudo install -d -m 0700 /root/notizblock-backups
sudo sh -c 'umask 077; tar -czf /root/notizblock-backups/notizblock-$(date +%Y%m%d-%H%M%S).tar.gz -C / opt/notizblock var/lib/notizblock etc/php/8.3/fpm/pool.d/notizblock.conf etc/nginx/snippets/notizblock.conf etc/cron.d/notizblock-archive'
```

Das setzt die Pfade der Installationsanleitung voraus. Zusätzlich den übergeordneten Nginx-Host und seine benötigte Konfiguration sichern, falls diese zur Wiederherstellung erforderlich sind. Den Archivinhalt mit `tar -tzf DATEI` prüfen. Während des Stillstands kopieren sich Datenbank, WAL und Bilder zusammenhängend. Keine aktive SQLite-Datei einzeln mit `cp` sichern; für Online-Datenbanksicherungen die SQLite-Backup-API verwenden und Bildbestand zusätzlich konsistent koordinieren.

Wartungssperre anschließend gezielt zurücknehmen und HTTP/Login prüfen. Die temporäre Sperre dient nur der Sicherung, **nicht** dem Schutz vor Google. Backupzugriff begrenzen, Kopie auf getrenntem Speicher verschlüsseln. Der Hash und Sitzungen im Backup sind vertraulich.

## Wiederherstellen

1. Vorherigen Zustand zusätzlich sichern, Schreibzugriff während der Wiederherstellung stoppen.
2. Archiv in ein separates Stagingverzeichnis auspacken und Inhalt prüfen. Keine ungeprüften Pfade über das Dateisystem entpacken.
3. Code und vollständigen Datenordner passend zusammen wiederherstellen. Vorhandene SQLite-WAL/SHM-Dateien des ersetzten Bestands nicht mit der wiederhergestellten Datenbank mischen; alten Ordner vollständig als Rückfallbestand behalten.
4. Dienstbenutzer, 0700-Verzeichnisse und geschützte Dateirechte wiederherstellen. `PRAGMA integrity_check` gegen die wiederhergestellte Datenbank ausführen; erwartet `ok`.
5. Sitzungen widerrufen, etwa mit `sudo runuser -u notizblock -- sqlite3 /var/lib/notizblock/notes.sqlite 'DELETE FROM sessions;'`. Das Passwortwerkzeug setzt bei Bedarf ein neues Passwort und widerruft ebenfalls Sitzungen.
6. FPM-/Nginx-Konfiguration prüfen, gezielt neu laden, Wartung aufheben. Login, Beispielnotiz und Bilder prüfen.

## Updates

Vorher vollständiges Backup und Rückfallplan anlegen. Quellcode auf einem **getrennten Checkout** aktualisieren und Tests ausführen. Release/Commit notieren. Nicht `git pull` im Datenverzeichnis verwenden.

Die geprüften PHP-Dateien und die drei öffentlichen Dateien als zusammengehörigen Stand nach `/opt/notizblock` übernehmen. `init.php` als Dienstbenutzer ausführen; vorhandene Tabellen bleiben erhalten, Migrationen sind idempotent. PHP-Syntax, Pool und Nginx prüfen, FPM gezielt neu laden und auf allen Geräten einmal die App neu laden. Konfigurationen nur bei tatsächlich benötigten Änderungen übernehmen. Bei Rücknahme eines Updates auch die passende Datenbanksicherung berücksichtigen; keine veraltete Migration blind auf neue Daten anwenden.

## Verborgenen Eintrag wiederherstellen

Nur als Serverwartung nach Sicherung und gezielter Auswahl. In SQLite anhand der gewünschten ID prüfen, dann den einzelnen Eintrag auf `deleted=0, deleted_at=NULL, archived_at=NULL, version=version+1` setzen. Keine pauschale Wiederherstellung. Das Archiv hat keinen Web-Endpunkt und keine Schaltfläche.

Eine endgültige Löschung samt Bildern, Revisionen, Mutationen und Backup-Aufbewahrung ist bisher ein manueller Wartungsvorgang und muss eigens geplant werden. Nicht nur eine Tabellenzeile entfernen und dies als vollständige Löschung bezeichnen.
