#!/usr/bin/env python3
"""Fail CI if committed source contains obvious private credential material.

This is intentionally conservative and dependency-free. It complements GitHub's
platform security features and package-manager vulnerability audits; it is not a
replacement for provider-side secret scanning.
"""
from __future__ import annotations

import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[1]
TEXT_SUFFIXES = {
    ".md", ".txt", ".php", ".py", ".ts", ".js", ".mjs", ".json", ".yaml", ".yml",
    ".toml", ".xml", ".env", ".example", ".sh", ".ini", ".conf",
}
IGNORE_PARTS = {".git", "vendor", "node_modules", "dist", "build", ".venv", "__pycache__"}

PATTERNS: list[tuple[str, re.Pattern[str]]] = [
    ("private key", re.compile(r"-----BEGIN (?:EC |RSA |OPENSSH )?PRIVATE KEY-----")),
    ("live Connect access token", re.compile(r"\bpct_live_[A-Za-z0-9_-]{24,}\b")),
    ("test Connect access token", re.compile(r"\bpct_test_[A-Za-z0-9_-]{24,}\b")),
    ("live Connect client id with secret-like entropy", re.compile(r"\bps_live_[A-Za-z0-9_-]{40,}\b")),
    ("test Connect client id with secret-like entropy", re.compile(r"\bps_test_[A-Za-z0-9_-]{40,}\b")),
]

ALLOW_MARKERS = (
    "example",
    "xxxxxxxx",
    "<opaque-access-token>",
    "<token>",
    "your_",
)


def iter_files():
    for path in ROOT.rglob("*"):
        if not path.is_file() or any(part in IGNORE_PARTS for part in path.parts):
            continue
        if path.suffix.lower() in TEXT_SUFFIXES or path.name in {"Dockerfile", "Makefile"}:
            yield path


def main() -> int:
    findings: list[str] = []
    for path in iter_files():
        try:
            text = path.read_text(encoding="utf-8")
        except UnicodeDecodeError:
            continue
        for line_no, line in enumerate(text.splitlines(), start=1):
            lowered = line.lower()
            if any(marker in lowered for marker in ALLOW_MARKERS):
                continue
            for label, pattern in PATTERNS:
                if pattern.search(line):
                    findings.append(f"{path.relative_to(ROOT)}:{line_no}: possible {label}")
    if findings:
        print("Potential credential material detected:", file=sys.stderr)
        for finding in findings:
            print(f"  - {finding}", file=sys.stderr)
        return 1
    print("Credential guard passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
