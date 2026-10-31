# Entwicklung

Benötigt: PHP mit PDO SQLite, Fileinfo und Argon2id, Python 3 und Node.js. Keine Composer- oder npm-Installation nötig.

Aus dem Repository-Verzeichnis:

```sh
python3 scripts/build.py
python3 scripts/check.py
python3 tests/test_merge.py
python3 tests/test_integration.py
```

`public/app.js` wird aus `scripts/merge.js` und `scripts/client-v3.js` erzeugt. Änderungen dort vornehmen und die erzeugte Datei mit committen. Der Prüfer stellt sicher, dass Quellen und erzeugte Datei zusammenpassen.

Integrationstests starten PHP ausschließlich auf `127.0.0.1` an einem freien Port mit temporärem Datenordner und zufälligem Testpasswort. Sie prüfen unter anderem unberechtigte Zugriffe, Cookie-Attribute, CSRF, Bildprüfung, Versionszusammenführung, Wiederholungen, Papierkorb, Archivgrenze und Login-Limit. Sie schreiben keine produktiven Daten. Der HTTP-Test setzt Cookies ausdrücklich selbst und ersetzt keinen Browser-/HTTPS-Test.

Für lokale Browserarbeit einen getrennten Testbestand und einen lokalen HTTPS-Proxy verwenden. Secure-Cookies und fester Cookiepfad `/notizblock/` müssen erhalten bleiben; Sicherheitsmerkmale nicht für einen bequemen HTTP-Preview abschalten. Keine privaten Daten als Testfixture oder Screenshot verwenden.

Entwicklungszweige verwenden, Änderungen per Pull Request nach `main`. Keine Deployment-Automatik auf einen privaten Server. Bei Änderungen an Datenmodell oder Speicherung Migrations-, Backup- und Rückfallverhalten dokumentieren.
