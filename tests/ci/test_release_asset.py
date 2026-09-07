import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[2]


class ReleaseAssetTest(unittest.TestCase):
    def run_asset(self, mode, existing=None):
        with tempfile.TemporaryDirectory() as directory:
            temp = Path(directory)
            archive = temp / "plugin.zip"
            archive.write_bytes(b"verified candidate")
            remote = temp / "remote.zip"
            remote.write_bytes(existing if existing is not None else b"")
            commands = temp / "commands"
            gh = temp / "gh"
            gh.write_text('''#!/usr/bin/env python3
import json, os, pathlib, shutil, sys
args = sys.argv[1:]
with open(os.environ["TEST_COMMANDS"], "a") as log:
    log.write(json.dumps(args) + "\\n")
if args[:2] == ["release", "view"]:
    print(os.environ["TEST_RELEASE_JSON"])
elif args[:2] == ["release", "download"]:
    directory = pathlib.Path(args[args.index("--dir") + 1])
    shutil.copyfile(os.environ["TEST_REMOTE"], directory / "plugin.zip")
elif args[:2] != ["release", "upload"]:
    sys.exit(1)
''')
            gh.chmod(0o755)
            env = dict(os.environ, PATH=f"{temp}:{os.environ['PATH']}", RELEASE_TAG="v-1.0.8",
                       ZIP_PATH=str(archive), TEST_COMMANDS=str(commands), TEST_REMOTE=str(remote),
                       TEST_RELEASE_JSON=json.dumps({"assets": [] if existing is None else [{"name": "plugin.zip"}]}))
            result = subprocess.run(["bash", str(ROOT / "scripts/publish-release-asset.sh"), mode],
                                    env=env, capture_output=True, text=True)
            calls = [json.loads(line) for line in commands.read_text().splitlines()] if commands.exists() else []
            return result, [call for call in calls if call[:2] == ["release", "upload"]]

    def test_check_missing_asset_does_not_upload(self):
        result, uploads = self.run_asset("check")
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual([], uploads)

    def test_upload_missing_asset_never_uses_clobber(self):
        result, uploads = self.run_asset("upload")
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(1, len(uploads))
        self.assertNotIn("--clobber", uploads[0])

    def test_identical_asset_is_noop(self):
        for mode in ("check", "upload"):
            result, uploads = self.run_asset(mode, b"verified candidate")
            self.assertEqual(0, result.returncode, result.stderr)
            self.assertEqual([], uploads)

    def test_different_asset_is_never_overwritten(self):
        for mode in ("check", "upload"):
            result, uploads = self.run_asset(mode, b"different candidate")
            self.assertNotEqual(0, result.returncode)
            self.assertIn("refusing to overwrite", result.stderr)
            self.assertEqual([], uploads)

    def test_unknown_mode_fails(self):
        result, uploads = self.run_asset("replace")
        self.assertNotEqual(0, result.returncode)
        self.assertEqual([], uploads)


if __name__ == "__main__":
    unittest.main()
