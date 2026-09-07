import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[2]


class ReleaseSelectionTest(unittest.TestCase):
    def run_selection(self, tag="v-1.0.8", mode="verify", draft=False, prerelease=False, expected=""):
        with tempfile.TemporaryDirectory() as directory:
            temp = Path(directory)
            gh = temp / "gh"
            gh.write_text('#!/bin/sh\nprintf "%s\\n" "$TEST_RELEASE_JSON"\n')
            gh.chmod(0o755)
            output = temp / "output"
            env = dict(os.environ, PATH=f"{temp}:{os.environ['PATH']}", RELEASE_TAG=tag,
                       RELEASE_MODE=mode, EXPECTED_PRERELEASE=expected,
                       GITHUB_OUTPUT=str(output), GITHUB_EVENT_NAME="workflow_dispatch",
                       TEST_RELEASE_JSON=json.dumps({"tagName": tag, "isDraft": draft, "isPrerelease": prerelease}))
            result = subprocess.run(["bash", str(ROOT / "scripts/resolve-release.sh")], env=env, capture_output=True, text=True)
            return result, output.read_text() if output.exists() else ""

    def test_accepted_tags(self):
        for tag in ("1.0.8", "v1.0.8", "v-1.0.8", "version-1.0.8"):
            result, output = self.run_selection(tag)
            self.assertEqual(0, result.returncode, result.stderr)
            self.assertIn("version=1.0.8\n", output)
            self.assertIn(f"ref=refs/tags/{tag}\n", output)

    def test_verify_without_release(self):
        result, output = self.run_selection("")
        self.assertEqual(0, result.returncode)
        self.assertEqual("", output)

    def test_publish_requires_tag_and_stable_release(self):
        for settings in ({"tag": ""}, {"draft": True}, {"prerelease": True}):
            result, _ = self.run_selection(mode="publish", **settings)
            self.assertNotEqual(0, result.returncode)

    def test_prerelease_dry_run_is_allowed(self):
        result, output = self.run_selection(mode="dry-run", prerelease=True)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertIn("prerelease=true\n", output)

    def test_release_state_change_is_rejected(self):
        result, _ = self.run_selection(prerelease=True, expected="false")
        self.assertNotEqual(0, result.returncode)

    def test_unsafe_tag_is_rejected(self):
        for tag in ("../master", "v-1.0.8\nref=master", "v-1.0.8;true", "master"):
            result, _ = self.run_selection(tag)
            self.assertNotEqual(0, result.returncode)


if __name__ == "__main__":
    unittest.main()
