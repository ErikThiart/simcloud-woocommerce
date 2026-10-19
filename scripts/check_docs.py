#!/usr/bin/env python3
"""Check required documents and local Markdown file targets; no network access."""

from pathlib import Path
import re
import sys
from urllib.parse import unquote, urlsplit


ROOT = Path(__file__).resolve().parents[1]
REQUIRED = (
    "README.md", "LICENSE", "CONTRIBUTING.md", "SECURITY.md", "SUPPORT.md",
    "CHANGELOG.md", "docs/product-specification.md", "docs/architecture.md",
    "docs/api-contract.md", "docs/order-lifecycle.md", "docs/merchant-guide.md",
    "docs/security-and-privacy.md", "docs/testing.md", "docs/releases.md",
    "docs/roadmap.md", "docs/sources.md",
)
LINK = re.compile(r"!?\[[^\]\n]*\]\((<[^>\n]+>|[^\s)]+)(?:\s+\"[^\"]*\")?\)")


def main():
    errors = []
    for name in REQUIRED:
        path = ROOT / name
        if not path.is_file() or not path.read_text(encoding="utf-8").strip():
            errors.append(f"Missing or empty required document: {name}")

    documents = sorted(ROOT.rglob("*.md"))
    checked = 0
    for document in documents:
        relative = document.relative_to(ROOT)
        if ".git" in relative.parts:
            continue
        in_fence = False
        for number, line in enumerate(document.read_text(encoding="utf-8").splitlines(), 1):
            if line.lstrip().startswith(("```", "~~~")):
                in_fence = not in_fence
                continue
            if in_fence:
                continue
            for match in LINK.finditer(line):
                raw = match.group(1).strip("<>")
                parts = urlsplit(raw)
                if parts.scheme or parts.netloc or not parts.path:
                    continue
                checked += 1
                target = (document.parent / unquote(parts.path)).resolve()
                if not target.is_relative_to(ROOT):
                    errors.append(f"{relative}:{number}: target leaves repository: {raw}")
                elif not target.exists():
                    errors.append(f"{relative}:{number}: missing local target: {raw}")

    if errors:
        print("Documentation check failed:")
        print("\n".join(errors))
        return 1
    print(f"Documentation OK: {len(REQUIRED)} required files; {checked} local link targets.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
