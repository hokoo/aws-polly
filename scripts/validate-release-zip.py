#!/usr/bin/env python3
"""Validate the production archive before extraction or publication."""

import argparse
import json
import os
from pathlib import PurePosixPath
import re
import stat
import sys
import zipfile


SLUG = "ai-text-to-speech-using-aws-polly"
ENTRYPOINT = "itron-polly-tts.php"
MAX_RELEASE_FILES = 1500
MAX_RELEASE_ENTRIES = 2000
MAX_RELEASE_UNCOMPRESSED_BYTES = 15 * 1024 * 1024
AWS_PRUNE_SCRIPT = "Aws\\Script\\Composer\\Composer::removeUnusedServices"
AWS_SERVICE_DATA_ALLOWLIST = {
    "kms", "polly", "s3", "signin", "sso", "sso-oidc", "sts",
}
AWS_CLIENT_NAMESPACE_ALLOWLIST = {
    "Crypto", "Kms", "Polly", "S3", "SSO", "SSOOIDC", "Signin", "Sts",
}


def validate(
    archive_path,
    expected_version="",
    slug=SLUG,
    entrypoint=ENTRYPOINT,
    max_files=MAX_RELEASE_FILES,
    max_entries=MAX_RELEASE_ENTRIES,
    max_uncompressed_bytes=MAX_RELEASE_UNCOMPRESSED_BYTES,
):
    with zipfile.ZipFile(archive_path) as archive:
        entries = archive.infolist()
        names = {entry.filename for entry in entries}
        if not entries or len(names) != len(entries):
            raise ValueError("archive is empty or contains duplicate entries")
        if archive.testzip() is not None:
            raise ValueError("archive checksum failed")

        file_entries = [entry for entry in entries if not entry.is_dir()]
        uncompressed_bytes = sum(entry.file_size for entry in file_entries)
        if len(file_entries) > max_files:
            raise ValueError(
                f"archive contains {len(file_entries)} files; limit is {max_files}"
            )
        if len(entries) > max_entries:
            raise ValueError(
                f"archive contains {len(entries)} entries; limit is {max_entries}"
            )
        if uncompressed_bytes > max_uncompressed_bytes:
            raise ValueError(
                f"archive expands to {uncompressed_bytes} bytes; "
                f"limit is {max_uncompressed_bytes}"
            )

        for entry in entries:
            name = entry.filename
            parts = name.rstrip("/").split("/")
            if (
                parts[0] != slug
                or len(parts) < 2 and not entry.is_dir()
                or any(part in ("", ".", "..") for part in parts)
                or "\\" in name
                or any(ord(char) < 32 for char in name)
                or stat.S_ISLNK(entry.external_attr >> 16)
            ):
                raise ValueError(f"unsafe archive path: {name}")
            if any(part.startswith(".") for part in parts):
                raise ValueError(f"hidden artifact: {name}")
            relative = PurePosixPath(*parts[1:])
            if parts[1:2] and parts[1] in {
                "tests", "scripts", "docs", "dev", "wordpress", "wp-content",
                "node_modules", "assets",
            }:
                raise ValueError(f"development artifact: {name}")
            if relative.suffix.lower() in {".key", ".pem", ".sql", ".zip", ".tgz", ".log"}:
                raise ValueError(f"non-release artifact: {name}")
            if "composer.lock" in parts:
                raise ValueError(f"lock file: {name}")
            if parts[1:2] == ["vendor"]:
                if any(part.lower() in {"tests", "test", "docs", "doc", "bin"} for part in parts[2:]):
                    raise ValueError(f"vendor development artifact: {name}")
                if relative.name == "composer.json" or str(relative).startswith("vendor/aws/Aws/"):
                    raise ValueError(f"vendor development artifact: {name}")

        required = (
            entrypoint, "readme.txt", "composer.json", "LICENSE.txt", "uninstall.php",
            "vendor/autoload.php", "vendor/composer/installed.json",
            "vendor/aws/aws-sdk-php/src/Polly/PollyClient.php",
            "vendor/aws/aws-sdk-php/src/S3/S3Client.php",
            "src/Plugin.php", "src/PollyService.php",
            "admin/js/audio-consent.js", "admin/js/itron-polly-tts-admin.js",
            "admin/css/itron-polly-tts-admin.css",
            "public/js/itron-polly-tts-public.js", "public/css/itron-polly-tts-public.css",
        )
        for path in required:
            if f"{slug}/{path}" not in names or not archive.read(f"{slug}/{path}"):
                raise ValueError(f"required file missing or empty: {path}")

        def read(path):
            return archive.read(f"{slug}/{path}").decode("utf-8")

        plugin = read(entrypoint)
        header = re.search(r"^\s*\*\s*Version:\s*(\S+)", plugin, re.MULTILINE)
        constant = re.search(r"^const\s+ITRON_POLLY_TTS_VERSION\s*=\s*'([^']+)'", plugin, re.MULTILINE)
        stable = re.search(r"^Stable tag:\s*(\S+)", read("readme.txt"), re.MULTILINE)
        if not header or not constant or not stable:
            raise ValueError("plugin version metadata is missing")
        version = header.group(1)
        if not re.fullmatch(r"\d+\.\d+\.\d+(?:[.-][0-9A-Za-z.-]+)?", version):
            raise ValueError("invalid plugin version")
        if {version, constant.group(1), stable.group(1)} != {version}:
            raise ValueError("plugin header, version constant, and Stable tag must match")
        if expected_version and version != re.sub(r"^(version|v)-?", "", expected_version):
            raise ValueError(f"plugin version {version} does not match {expected_version}")

        manifest = json.loads(read("composer.json"))
        pre_autoload_dump = manifest.get("scripts", {}).get("pre-autoload-dump", [])
        if isinstance(pre_autoload_dump, str):
            pre_autoload_dump = [pre_autoload_dump]
        retained_aws_services = set(
            manifest.get("extra", {}).get("aws/aws-sdk-php", [])
        )
        if (
            AWS_PRUNE_SCRIPT not in pre_autoload_dump
            or not {"Polly", "S3"}.issubset(retained_aws_services)
        ):
            raise ValueError("AWS SDK unused-service pruning is not configured")

        aws_data_prefix = f"{slug}/vendor/aws/aws-sdk-php/src/data/"
        packaged_aws_services = {
            name[len(aws_data_prefix):].split("/", 1)[0]
            for name in names
            if name.startswith(aws_data_prefix)
            and "/" in name[len(aws_data_prefix):]
        }
        if packaged_aws_services != AWS_SERVICE_DATA_ALLOWLIST:
            raise ValueError(
                "unexpected AWS service data: "
                + ", ".join(sorted(packaged_aws_services))
            )
        aws_source_prefix = f"{slug}/vendor/aws/aws-sdk-php/src/"
        packaged_client_namespaces = {
            relative.split("/", 1)[0]
            for name in names
            if name.startswith(aws_source_prefix)
            for relative in [name[len(aws_source_prefix):]]
            if "/" in relative and relative.endswith("Client.php")
        }
        unexpected_client_namespaces = (
            packaged_client_namespaces - AWS_CLIENT_NAMESPACE_ALLOWLIST
        )
        if unexpected_client_namespaces:
            raise ValueError(
                "unused AWS service clients are packaged: "
                + ", ".join(sorted(unexpected_client_namespaces))
            )

        installed = json.loads(read("vendor/composer/installed.json"))
        if installed.get("dev") is not False:
            raise ValueError("dependencies were not installed with --no-dev")
        packages = {package["name"] for package in installed["packages"]}
        if packages.intersection(manifest.get("require-dev", {})):
            raise ValueError("development dependencies are installed")
        if not {"aws/aws-sdk-php", "hokoo/wp-lock", "psr/log"}.issubset(packages):
            raise ValueError("runtime dependencies are missing")
        return version


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("archive")
    args = parser.parse_args()
    try:
        version = validate(
            args.archive, os.getenv("EXPECTED_VERSION", ""),
            os.getenv("PLUGIN_SLUG", SLUG), os.getenv("ENTRYPOINT", ENTRYPOINT),
        )
    except (OSError, ValueError, KeyError, zipfile.BadZipFile) as error:
        print(f"Release ZIP validation failed: {error}", file=sys.stderr)
        return 1
    with zipfile.ZipFile(args.archive) as archive:
        files = [entry for entry in archive.infolist() if not entry.is_dir()]
        uncompressed_bytes = sum(entry.file_size for entry in files)
    print(
        f"Release ZIP validation passed: {args.archive} version {version}; "
        f"{len(files)} files, {uncompressed_bytes} uncompressed bytes"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
