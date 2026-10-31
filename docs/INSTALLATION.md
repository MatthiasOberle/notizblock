# Installation

Diese Anleitung gilt für eine **neue Installation** auf Ubuntu 24.04 mit Nginx und PHP 8.3-FPM. Für bestehende Installationen siehe [Betrieb und Updates](BETRIEB.md). Keine Konfigurationsdatei einer bestehenden Website ungeprüft ersetzen. Vor Änderungen Webserverkonfiguration und betroffene Daten sichern.

## Voraussetzungen

- Administrativer Serverzugriff, aktuelles Betriebssystem und funktionierende Zeitsynchronisierung.
- Eine gültige HTTPS-Adresse. TLS muss vor Freigabe der Anwendung funktionieren; HTTP muss auf HTTPS umleiten. Secure-Cookies machen eine reine HTTP-Installation unbrauchbar.
- PHP 8.3 oder neuer mit PDO SQLite, Fileinfo und Argon2id. Das Konfigurationsbeispiel und die Befehle verwenden 8.3. PHP 8.4/8.5 brauchen entsprechend angepasste Paket- und Dienstnamen.
- Ein vorhandener Nginx-HTTPS-Host. Domain, DNS, Zertifikatsbeschaffung und Firewall sind Voraussetzung und werden nicht automatisch eingerichtet.

Pakete auf einem Ubuntu-24.04-Server:

```sh
sudo apt update
sudo apt install nginx php8.3-cli php8.3-fpm php8.3-sqlite3 sqlite3 git cron
php -r 'foreach (["pdo_sqlite", "fileinfo"] as $e) { if (!extension_loaded($e)) { fwrite(STDERR, "$e fehlt\n"); exit(1); } } if (!defined("PASSWORD_ARGON2ID")) { exit(1); } echo "PHP bereit\n";'
```

Die Anleitung geht von Nginx als Benutzer `www-data` und den Standard-FastCGI-Dateien von Ubuntu aus.

## Quellcode und Daten getrennt ablegen

```sh
git clone https://github.com/MatthiasOberle/notizblock.git
cd notizblock
sudo useradd --system --home /var/lib/notizblock --shell /usr/sbin/nologin notizblock
sudo install -d -m 0755 /opt/notizblock /opt/notizblock/public
sudo install -m 0644 init.php storage.php merge.php maintenance.php password.php /opt/notizblock/
sudo install -m 0644 public/index.php public/app.js public/style.css /opt/notizblock/public/
sudo install -d -o notizblock -g notizblock -m 0700 /var/lib/notizblock /var/lib/notizblock/uploads /var/lib/notizblock/tmp
sudo runuser -u notizblock -- sh -c 'umask 077; php /opt/notizblock/init.php'
```

`useradd` darf bei vorhandenem Benutzer nicht blind wiederholt werden. Der Code bleibt root-eigen und für den Dienst schreibgeschützt. Daten liegen unter `/var/lib/notizblock`, niemals im Verzeichnis einer öffentlichen Website. Die Initialisierung muss „Datenbank bereit.“ melden.

## Passwort setzen und ändern

In einer interaktiven Serverkonsole mit TTY ausführen, beispielsweise in der angemeldeten Termius-Sitzung:

```sh
sudo runuser -u notizblock -- php /opt/notizblock/password.php
```

1. Eigenes neues Passwort eingeben, mindestens 12 Zeichen. Die Eingabe bleibt unsichtbar, auch Sternchen werden nicht angezeigt.
2. Enter drücken, dasselbe Passwort erneut eingeben und mit Enter bestätigen.
3. Erfolgsmeldung abwarten: „Passwort gespeichert. Bestehende Anmeldungen wurden beendet.“

Das Werkzeug nimmt das Passwort nicht als Argument an. Es gehört weder in Shellbefehle noch in Git, Chats, Screenshots oder Konfigurationsdateien. Eine lange zufällige Passphrase im Passwortmanager ist geeignet. Es gibt kein eingebautes Standardpasswort und keine Passwort-Wiederherstellung per E-Mail.

Zum späteren Ändern oder bei vergessenem Passwort denselben Befehl erneut ausführen. Dafür braucht man administrativen Serverzugriff, das bisherige App-Passwort ist nicht erforderlich. Alle Geräte müssen sich anschließend neu anmelden. Vor einer Änderung eine geschützte Sicherung anlegen; alte Backups enthalten den alten Hash und möglicherweise alte Sitzungen.

Bei „Nicht gespeichert“ Länge und identische Eingabe prüfen. Bei `stty`-Fehlern in eine interaktive Konsole wechseln, nicht über eine Pipeline ausführen. Ein manueller Browser-Login ist kein Ersatz für dieses Werkzeug. Die bestehende private Installation erhält durch das Klonen dieses Projekts kein neues Passwort.

## PHP-FPM isolieren

```sh
sudo install -m 0644 config/notizblock.conf /etc/php/8.3/fpm/pool.d/notizblock.conf
sudo php-fpm8.3 -t
sudo systemctl reload php8.3-fpm
```

Nur bei erfolgreicher Konfigurationsprüfung neu laden. Der Pool läuft als `notizblock`, öffnet `/run/php/notizblock.sock` für Nginx und begrenzt Dateizugriff und Uploadgrößen. Das CLI-Passwortwerkzeug verwendet `system()` für die verdeckte Eingabe; die Sperre dieser Funktion gilt nur für den Web-Pool.

## Nginx anbinden

Den Inhalt von [`config/nginx-location.conf`](../config/nginx-location.conf) in den **bestehenden HTTPS-server-Block** aufnehmen, etwa mit:

```sh
sudo install -d -m 0755 /etc/nginx/snippets
sudo install -m 0644 config/nginx-location.conf /etc/nginx/snippets/notizblock.conf
```

Dann im passenden `server { ... }`-Block mit `listen 443 ssl` ergänzen:

```nginx
include /etc/nginx/snippets/notizblock.conf;
```

Keine zweite gleichnamige Location anlegen. Bestehende reguläre PHP-Routen, `^~`-Präfixe, Zugriffsregeln und TLS-Einstellungen vorab prüfen. Die Beispiele routen nur den festen PHP-Einstieg und die beiden statischen Dateien. Alle anderen Pfade unter `/notizblock/` liefern 404. **Nicht das gesamte Repository oder den Datenordner als Webroot veröffentlichen.** Das Beispiel gehört ausschließlich in den TLS-Host, da es HTTPS an PHP meldet.

```sh
sudo nginx -t
sudo systemctl reload nginx
```

Den Reload nur nach erfolgreicher Prüfung ausführen. Der bisherige HTTP-Host muss weiterhin auf HTTPS umleiten. Danach `https://DEINE-ADRESSE/notizblock/` im Browser öffnen und anmelden.

## Tägliche Archivierung

```sh
sudo install -m 0644 config/notizblock-archive.cron /etc/cron.d/notizblock-archive
sudo systemctl enable --now cron
sudo runuser -u notizblock -- php /opt/notizblock/maintenance.php
```

Cron prüft täglich um 03:15 Uhr in der **Serverzeitzone**, zusätzlich archiviert die App bei angemeldeten Anfragen. Nach 30 mal 24 Stunden im Papierkorb wird ein Eintrag samt Bildern aus der Oberfläche verborgen. Die Dateien bleiben gespeichert.

## Abnahme

Mit `DEINE-ADRESSE` ist immer die eigene HTTPS-Adresse gemeint:

```sh
curl -I https://DEINE-ADRESSE/notizblock/
curl -i 'https://DEINE-ADRESSE/notizblock/?action=list'
curl -i 'https://DEINE-ADRESSE/notizblock/?action=image&id=1'
curl -I https://DEINE-ADRESSE/notizblock/password.php
curl -I https://DEINE-ADRESSE/notizblock/notes.sqlite
```

Erwartet: Login 200 mit `X-Robots-Tag: noindex, nofollow, noarchive`; Liste und Bild ohne Sitzung 401; direkte Dateiabrufe 404. HTTPS ohne Zertifikatswarnung, HTTP-Weiterleitung prüfen. Im Browser eine **eigene Testnotiz** anlegen, auf einem zweiten Gerät ändern, Bild hochladen, Papierkorb und Wiederherstellung prüfen. Andere Websites auf demselben Server ebenfalls kontrollieren.

## Typische Fehler

- **503 / Noch nicht eingerichtet:** Passwort-Hash fehlt oder Datenpfad nicht lesbar; Initialisierung und Passwort setzen als `notizblock` prüfen.
- **502:** Pool startet nicht oder Socket/Berechtigungen passen nicht; `php-fpm8.3 -t` und Dienstprotokoll prüfen.
- **Login klappt, danach wieder Login:** HTTPS, Cookiepfad `/notizblock/`, Uhrzeit und Browser-Cookies prüfen.
- **Bilder scheitern:** echtes JPEG/PNG/WebP/GIF bis 10 MiB; HEIC vorher umwandeln. Nginx- und PHP-Limits aus den Beispielen übernehmen.
- **SQLite nicht schreibbar:** Ordner und Datenbank müssen dem Dienstbenutzer gehören; niemals mit `chmod 777` beheben.

Ein gewöhnlicher Webhosting-Upload in `public_html` ohne passende Serverkonfiguration ist keine gleichwertige Installation.
