# Notizblock by MOWST

Gemeinsame Notizen, To-do-Listen und Bilder auf dem eigenen Server. Für Menschen, die ihre privaten Inhalte lieber selbst verwalten, statt sie den vorinstallierten Notizdiensten von Android oder iOS oder einer weiteren App mit eigener Cloud anzuvertrauen.

Notizblock läuft im Browser auf Smartphone, Tablet und PC. Keine App-Installation, kein Konto bei einem zusätzlichen Notizdienst, kein Tracking und keine extern geladenen Schriftarten oder Skripte. Ein gemeinsames Passwort öffnet den gemeinsamen Bestand, beispielsweise für ein Paar oder einen kleinen Haushalt.

**Status:** frühe, funktionierende Version für einen kleinen vertrauten Nutzerkreis. PHP und SQLite, ohne Composer- oder npm-Abhängigkeiten zur Laufzeit. Kein Mehrmandantensystem und kein öffentlicher Dateihoster.

## Was funktioniert?

- Notizen mit Freitext, Suche und Checklisten. Neue Listenpunkte mit Plus öffnen und mit Enter oder Diskette übernehmen.
- Bilder als Anhang oder direkt als eigener Eintrag; zusätzlicher Bilder-Tab.
- Autosave nach etwa 800 ms Tipp-Pause mit sichtbarer Speicheranzeige. Manueller Speicherknopf bleibt verfügbar.
- Gleichzeitiges Bearbeiten: Änderungen werden gegen die gemeinsame Ausgangsversion zusammengeführt. Bei überlappenden Textänderungen bleiben beide Varianten erhalten.
- Papierkorb mit Wiederherstellung; nach 30 Tagen Wechsel in ein verborgenes Archiv. **Keine automatische endgültige Löschung.**
- Responsive Oberfläche für Android, iOS und Desktop.

## Selbst hosten

Die [Installationsanleitung](docs/INSTALLATION.md) beschreibt Ubuntu 24.04, Nginx und PHP 8.3-FPM, einen getrennten Dienstbenutzer und HTTPS. Der Pfad `/notizblock/` ist derzeit fest vorgegeben. Ein eigener Domainname ist nicht zwingend; eine bereits vorhandene HTTPS-Adresse mit Unterpfad genügt.

Das Passwort wird ausschließlich am Server verdeckt eingegeben, zweimal bestätigt und als Argon2id-Hash gespeichert:

```sh
sudo runuser -u notizblock -- php /opt/notizblock/password.php
```

Das funktioniert erst nach der Installation und Initialisierung. Mindestens 12 Zeichen; sinnvoll ist eine lange, zufällig erzeugte Passphrase. **Es gibt kein Standardpasswort.** Derselbe Befehl ändert das Passwort und meldet alle Geräte ab. Details und Fehlerhilfe: [Passwort setzen und ändern](docs/INSTALLATION.md#passwort-setzen-und-ändern).

## Datenschutz und Grenzen

Die Inhalte liegen auf dem eigenen Server. Das erspart einen zusätzlichen Notiz-Cloud-Dienst, macht den Server aber nicht automatisch sicher. Betreiber und Hostinganbieter bleiben Teil des Vertrauensmodells. **Keine Ende-zu-Ende-Verschlüsselung:** Wer administrativen Zugriff auf Server, Datenbank oder Backups hat, kann Inhalte lesen. Bilder werden im Original gespeichert; enthaltene EXIF-Metadaten werden nicht entfernt.

Alle Personen mit dem gemeinsamen Passwort sehen und bearbeiten denselben Bestand. Keine individuellen Konten, Rollen oder getrennten Bereiche. Kein Offlinebetrieb; unbestätigte Eingaben können bei Schließen des Browsers verloren gehen. Bestätigte Sitzungen bleiben bis zu einem Jahr gültig, bei Aufruf der Startseite erneuert.

[Bedienung](docs/NUTZUNG.md) · [Betrieb, Updates und Backups](docs/BETRIEB.md) · [Sicherheitsmodell](SECURITY.md) · [Entwicklung und Tests](CONTRIBUTING.md)

## Technik

`public/` enthält ausschließlich den Web-Einstieg, CSS und JavaScript. Passwortwerkzeug, SQLite-Datenbank und hochgeladene Bilder gehören außerhalb des Webroots. `config/` enthält Beispiele für einen isolierten FPM-Pool, begrenzte Nginx-Routen und die tägliche Archivierung. Die Installation erfordert Serveradministration; Shared Hosting ohne getrennte Datenablage und passende Routen ist mit dieser Anleitung nicht abgedeckt.

Entwickelt von [Matthias Oberle / MOWST](https://mowst.de). MIT-Lizenz, siehe [LICENSE](LICENSE). Das Repository enthält keine privaten Anwendungsdaten und keinen Zugang zu einer privaten Installation.
