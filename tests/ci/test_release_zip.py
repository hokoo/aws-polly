import importlib.util
import json
from pathlib import Path
import stat
import tempfile
import unittest
import warnings
import zipfile


ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location("validator", ROOT / "scripts/validate-release-zip.py")
VALIDATOR = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(VALIDATOR)


class ReleaseZipTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.path = Path(self.temp.name) / "plugin.zip"
        self.files = {
            path.relative_to(ROOT / "plugin-dir").as_posix(): path.read_bytes()
            for path in (ROOT / "plugin-dir").rglob("*")
            if path.is_file() and "vendor" not in path.relative_to(ROOT / "plugin-dir").parts
            and not path.name.startswith(".") and path.name != "composer.lock"
        }
        self.files.update({
            "vendor/autoload.php": b"<?php // fixture",
            "vendor/aws/aws-sdk-php/src/Polly/PollyClient.php": b"<?php // fixture",
            "vendor/aws/aws-sdk-php/src/S3/S3Client.php": b"<?php // fixture",
            "vendor/composer/installed.json": json.dumps({
                "dev": False,
                "packages": [{"name": name} for name in ("aws/aws-sdk-php", "hokoo/wp-lock", "psr/log")],
            }).encode(),
        })

    def build(self):
        with zipfile.ZipFile(self.path, "w") as archive:
            for name, contents in self.files.items():
                archive.writestr(f"{VALIDATOR.SLUG}/{name}", contents)

    def test_valid_archive_and_tag_normalization(self):
        self.build()
        version = VALIDATOR.validate(self.path)
        for prefix in ("", "v", "v-", "version-", "version"):
            self.assertEqual(version, VALIDATOR.validate(self.path, prefix + version))

    def test_expected_version_mismatch(self):
        self.build()
        with self.assertRaisesRegex(ValueError, "does not match"):
            VALIDATOR.validate(self.path, "99.99.99")

    def test_readme_mismatch(self):
        self.files["readme.txt"] = b"Stable tag: 99.99.99\n"
        self.build()
        with self.assertRaisesRegex(ValueError, "must match"):
            VALIDATOR.validate(self.path)

    def test_required_files(self):
        del self.files["composer.json"]
        self.build()
        with self.assertRaisesRegex(ValueError, "required file"):
            VALIDATOR.validate(self.path)

    def test_forbidden_entries(self):
        for name in ("../outside", ".env", "src/../../outside", "src/link\\file", "tests/test.php", "vendor/pkg/tests/test.php", "vendor/pkg/composer.json", "composer.lock", "secret.key"):
            with self.subTest(name=name):
                self.build()
                with zipfile.ZipFile(self.path, "a") as archive:
                    archive.writestr(f"{VALIDATOR.SLUG}/{name}", "fixture")
                with self.assertRaises(ValueError):
                    VALIDATOR.validate(self.path)

    def test_symlink_and_duplicates(self):
        for duplicate in (False, True):
            self.build()
            with zipfile.ZipFile(self.path, "a") as archive:
                entry = zipfile.ZipInfo(f"{VALIDATOR.SLUG}/readme.txt" if duplicate else f"{VALIDATOR.SLUG}/link")
                if not duplicate:
                    entry.external_attr = (stat.S_IFLNK | 0o777) << 16
                with warnings.catch_warnings():
                    warnings.simplefilter("ignore", UserWarning)
                    archive.writestr(entry, "fixture")
            with self.assertRaises(ValueError):
                VALIDATOR.validate(self.path)

    def test_dev_dependencies(self):
        self.files["vendor/composer/installed.json"] = b'{"dev":true,"packages":[]}'
        self.build()
        with self.assertRaisesRegex(ValueError, "no-dev"):
            VALIDATOR.validate(self.path)


if __name__ == "__main__":
    unittest.main()
