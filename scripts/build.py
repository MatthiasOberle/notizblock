from pathlib import Path
root = Path(__file__).resolve().parents[1]
(root / 'public/app.js').write_bytes(b"'use strict';\n" + (root / 'scripts/merge.js').read_bytes() + b'\n' + (root / 'scripts/client-v3.js').read_bytes())
print('public/app.js erstellt')
