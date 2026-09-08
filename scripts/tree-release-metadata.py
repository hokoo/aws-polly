#!/usr/bin/env python3
"""Produce deterministic metadata for a release directory tree."""

import argparse
import hashlib
import json
from pathlib import Path
import sys


ASSET_MIME_TYPES = {
    ".gif": "image/gif",
    ".jpeg": "image/jpeg",
    ".jpg": "image/jpeg",
    ".png": "image/png",
}


def file_digest(path):
    digest = hashlib.sha256()
    with path.open("rb") as source:
        for chunk in iter(lambda: source.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def inspect_tree(root, include_asset_properties=False):
    root = Path(root)
    if not root.is_dir():
        raise ValueError(f"tree root does not exist: {root}")

    tree_digest = hashlib.sha256()
    files = 0
    entries = 0
    total_bytes = 0

    paths = sorted(
        (path for path in root.rglob("*") if ".svn" not in path.relative_to(root).parts),
        key=lambda path: path.relative_to(root).as_posix(),
    )
    for path in paths:
        relative = path.relative_to(root).as_posix()
        if path.is_symlink():
            raise ValueError(f"symbolic link is not allowed: {relative}")

        record = {"path": relative}
        entries += 1
        if path.is_dir():
            record["type"] = "directory"
        elif path.is_file():
            size = path.stat().st_size
            record.update({"type": "file", "bytes": size, "sha256": file_digest(path)})
            if include_asset_properties and path.suffix.lower() in ASSET_MIME_TYPES:
                record["svn:mime-type"] = ASSET_MIME_TYPES[path.suffix.lower()]
            files += 1
            total_bytes += size
        else:
            raise ValueError(f"unsupported filesystem entry: {relative}")

        tree_digest.update(
            json.dumps(record, ensure_ascii=True, sort_keys=True, separators=(",", ":")).encode()
        )
        tree_digest.update(b"\n")

    return {
        "digest": tree_digest.hexdigest(),
        "files": files,
        "entries": entries,
        "bytes": total_bytes,
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("root")
    parser.add_argument("--asset-properties", action="store_true")
    args = parser.parse_args()
    try:
        metadata = inspect_tree(args.root, args.asset_properties)
    except (OSError, ValueError) as error:
        print(f"Release tree inspection failed: {error}", file=sys.stderr)
        return 1
    print(
        metadata["digest"],
        metadata["files"],
        metadata["entries"],
        metadata["bytes"],
        sep="\t",
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
