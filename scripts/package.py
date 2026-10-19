#!/usr/bin/env python3
"""Build a deterministic installable ZIP from an explicit runtime allowlist."""
from pathlib import Path
import hashlib
import re
import zipfile

root = Path(__file__).resolve().parents[1]
version = re.search(r"\* Version: ([0-9.]+)", (root / "simcloud-woocommerce.php").read_text()).group(1)
files = [root / p for p in ("simcloud-woocommerce.php", "uninstall.php", "readme.txt", "LICENSE")]
files += sorted((root / "includes").glob("*.php"))
files += sorted(p for p in (root / "assets").rglob("*") if p.is_file())
files += sorted(p for p in (root / "languages").rglob("*") if p.is_file())
output = root / "dist" / f"simcloud-woocommerce-{version}.zip"
output.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED) as archive:
    for path in files:
        info = zipfile.ZipInfo("simcloud-woocommerce/" + str(path.relative_to(root)), (2026, 1, 1, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        archive.writestr(info, path.read_bytes())
checksum = hashlib.sha256(output.read_bytes()).hexdigest()
output.with_suffix(".zip.sha256").write_text(f"{checksum}  {output.name}\n")
print(f"{output}\nSHA256 {checksum}\n{len(files)} runtime files")
