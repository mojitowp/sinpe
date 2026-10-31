#!/usr/bin/env python3
"""Deterministic production-only ZIP; no Composer, npm, SDK or vendor at runtime."""
from pathlib import Path
import hashlib, json, sys, zipfile
root=Path(__file__).resolve().parents[1]
output=Path(sys.argv[1]).resolve() if len(sys.argv)>1 else Path('/tmp/mojito-sinpe-1.3.0.zip')
files=[root/'mojito-sinpe.php',root/'readme.txt',root/'LICENSE']
files.extend(root/name for name in ('includes/class-mojito-sinpe.php','includes/class-mojito-sinpe-gateway.php','includes/class-mojito-sinpe-payment.php','includes/class-mojito-sinpe-gateway-block.php','includes/checkout.js','public/js/mojito-sinpe-public.js'))
for directory in ('public/img','languages'):
    files.extend(p for p in (root/directory).rglob('*') if p.is_file())
files=sorted(files,key=lambda p:p.relative_to(root).as_posix())
manifest={}
with zipfile.ZipFile(output,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=9) as archive:
    for path in files:
        content=path.read_bytes()
        relative=path.relative_to(root).as_posix()
        info=zipfile.ZipInfo('mojito-sinpe/'+relative,date_time=(2026,1,1,0,0,0))
        info.compress_type=zipfile.ZIP_DEFLATED
        info.external_attr=0o100644<<16
        archive.writestr(info,content,compress_type=zipfile.ZIP_DEFLATED,compresslevel=9)
        manifest[relative]=hashlib.sha256(content).hexdigest()
summary={'file':str(output),'sha256':hashlib.sha256(output.read_bytes()).hexdigest(),'files':manifest}
output.with_suffix('.manifest.json').write_text(json.dumps(summary,indent=2)+'\n')
print(json.dumps({'file':str(output),'sha256':summary['sha256'],'files':len(files)}))
