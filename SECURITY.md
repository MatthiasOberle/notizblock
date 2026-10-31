# Sicherheitsmodell

Notizblock ist für einen kleinen vertrauenswürdigen Kreis mit gemeinsamem Passwort gedacht. Alle angemeldeten Personen dürfen alle Inhalte lesen und ändern. Der Administrator des Servers kann Daten und Passwort-Hash auslesen. Keine Ende-zu-Ende-Verschlüsselung, keine einzelnen Benutzerrechte, kein zweiter Faktor.

## Vorhandene Maßnahmen

- Argon2id-Hash außerhalb des Webroots, Passwort-Setzen über CLI ohne Klartextargumente.
- Zufällige 256-Bit-Sitzungstoken; nur ihr SHA-256-Hash liegt in der Datenbank. Cookie: Secure, HttpOnly, SameSite=Strict, Pfad `/notizblock/`. Laufzeit ein Jahr, bei Aufruf der Startseite erneuert. Kein IP-Binding.
- Serverseitige Authentifizierung auch für Notizen- und Bildabrufe. Direkte Datenpfade werden durch das Nginx-Beispiel nicht bereitgestellt.
- CSRF-Token für authentifizierte Schreibzugriffe, Abweisung erkennbarer Cross-Site-POSTs, parametrisierte SQL-Abfragen und textbasierte DOM-Ausgabe.
- Login-Begrenzung auf 10 Versuche je IP in 15 Minuten und 100 insgesamt. Beim erfolgreichen Login wird der IP-Zähler zurückgesetzt. Hinter einem Proxy muss die echte Client-IP sicher konfiguriert werden; beliebige Forwarded-Header nicht vertrauen. Das globale Limit kann bei Angriffen auch berechtigte Logins vorübergehend blockieren.
- MIME- und Bildprüfung, begrenzte Größen, zufällige Uploadnamen außerhalb des Webroots, `nosniff`, strikte Content Security Policy und `no-store`.
- `noindex` in HTML und HTTP-Headern. Das ist eine Suchmaschinenanweisung und keine Zugangskontrolle. Die Loginseite bleibt ohne Anmeldung erreichbar. `robots.txt` ist kein Geheimnisschutz; ein Disallow kann verhindern, dass Suchmaschinen noindex sehen.

## Verantwortung beim Betrieb

HTTPS für die ganze Sitzung, gültige Zertifikate, Updates, sichere Serverzugänge, geschützte Backups und ein starkes gemeinsames Passwort bleiben erforderlich. Auch ein öffentlich nicht verlinkter Pfad kann entdeckt werden. F12 zeigt öffentlich ausgelieferten Clientcode und bei einer eigenen gültigen Anmeldung auch die eigenen Anwendungsdaten; das ist kein Umgehen der Zugangskontrolle.

Server, Datenbank und Bilder sind auf Anwendungsebene nicht verschlüsselt. Bildmetadaten bleiben erhalten. Kompromittierte Geräte oder gestohlene gültige Sitzungen liegen außerhalb dessen, was Cookie-Attribute allein verhindern können. Das verborgene Archiv und Textrevisionen bewahren alte Inhalte auf; es gibt keine zugesicherte endgültige Löschfrist.

Die Tests prüfen konkrete Funktionen und Angriffsabwehr, sind aber kein unabhängiger Penetrationstest und keine Sicherheitszertifizierung. Die lange Sitzungsdauer ist eine bewusste Komfortentscheidung für private Geräte; auf gemeinsam benutzten Geräten abmelden.

## Schwachstellen melden

Vertraulich an **hallo@mowst.de** mit Betreff „Notizblock Sicherheitsmeldung“. Bitte keine privaten Notizen, Passwörter, Sitzungen oder nutzbaren Exploits als öffentliches Issue veröffentlichen. Keine Tests gegen fremde oder private Installationen ohne Erlaubnis.

## Primärquellen

Für die Dokumentation am 31.10.2026 herangezogen:

- [PHP password_hash und Argon2id](https://www.php.net/manual/en/function.password-hash.php)
- [OWASP Session Management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
- [Google: noindex](https://developers.google.com/search/docs/crawling-indexing/block-indexing)
- [Nginx Locations](https://nginx.org/en/docs/http/ngx_http_core_module.html#location)
