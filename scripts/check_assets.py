#!/usr/bin/env python3
"""Verify the downloaded, bundled image set against its source manifest."""
from pathlib import Path
import hashlib
import json


def check_assets(root):
    directory = root / "assets/images"
    assert (directory / "NOTICE.md").is_file(), "Bundled asset notice is missing"
    records = json.loads((directory / "sources.json").read_text())
    listed = set()
    for record in records:
        path = (directory / record["file"]).resolve()
        assert path.is_relative_to(directory.resolve()), "Asset path leaves image directory"
        data = path.read_bytes()
        assert data.startswith(b"\xff\xd8\xff"), f"Not a JPEG: {record['file']}"
        assert len(data) == record["bytes"], f"Asset size changed: {record['file']}"
        assert hashlib.sha256(data).hexdigest() == record["sha256"], f"Asset checksum changed: {record['file']}"
        assert record["source"].startswith("https://simcloud.co.za/img/"), "Unexpected source host"
        assert path not in listed, "Duplicate manifest asset"
        listed.add(path)
    assert listed == {p.resolve() for p in directory.rglob("*.jpg")}, "Manifest and packaged JPEG set differ"
    print(f"Bundled assets OK: {len(listed)} original JPEGs with matching checksums.")


if __name__ == "__main__":
    check_assets(Path(__file__).resolve().parents[1])
