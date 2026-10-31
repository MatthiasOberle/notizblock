from pathlib import Path
import subprocess
root = Path(__file__).resolve().parents[1]
for p in root.rglob('*.php'):
    subprocess.run(['php', '-l', str(p)], check=True)
for p in [root / 'public/app.js', root / 'scripts/merge.js', root / 'scripts/client-v3.js']:
    subprocess.run(['node', '--check', str(p)], check=True)
assert (root / 'public/app.js').read_bytes() == b"'use strict';\n" + (root / 'scripts/merge.js').read_bytes() + b'\n' + (root / 'scripts/client-v3.js').read_bytes(), 'Bitte python3 scripts/build.py ausführen'
print('Syntax und erzeugtes JavaScript geprüft')
