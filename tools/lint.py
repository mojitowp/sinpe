#!/usr/bin/env python3
"""Lint shipped PHP with the active PHP binary; avoid dev dependencies."""
import pathlib
import subprocess
import os
root = pathlib.Path(__file__).resolve().parents[1]
files = [root / 'mojito-sinpe.php']
for folder in ('includes', 'public', 'admin', 'languages'):
    files.extend(sorted((root / folder).rglob('*.php')))
for file in files:
    subprocess.run([os.environ.get('SINPE_PHP', 'php'), '-l', str(file)], check=True, stdout=subprocess.DEVNULL)
print(f'PHP lint: {len(files)} files passed')
