import importlib.util
from pathlib import Path
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location(
    "tree_release_metadata", ROOT / "scripts/tree-release-metadata.py"
)
METADATA = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(METADATA)


class TreeReleaseMetadataTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        (self.root / "nested").mkdir()
        (self.root / "nested" / "file.txt").write_text("fixture", encoding="ascii")

    def test_metadata_is_deterministic_and_counts_tree(self):
        first = METADATA.inspect_tree(self.root)
        second = METADATA.inspect_tree(self.root)
        self.assertEqual(first, second)
        self.assertEqual(1, first["files"])
        self.assertEqual(2, first["entries"])
        self.assertEqual(7, first["bytes"])

    def test_content_and_path_changes_affect_digest(self):
        original = METADATA.inspect_tree(self.root)["digest"]
        (self.root / "nested" / "file.txt").write_text("changed", encoding="ascii")
        changed_content = METADATA.inspect_tree(self.root)["digest"]
        (self.root / "nested" / "file.txt").rename(self.root / "renamed.txt")
        changed_path = METADATA.inspect_tree(self.root)["digest"]
        self.assertNotEqual(original, changed_content)
        self.assertNotEqual(changed_content, changed_path)

    def test_svn_metadata_is_ignored(self):
        before = METADATA.inspect_tree(self.root)
        (self.root / ".svn").mkdir()
        (self.root / ".svn" / "wc.db").write_text("metadata", encoding="ascii")
        self.assertEqual(before, METADATA.inspect_tree(self.root))

    def test_asset_properties_affect_digest(self):
        (self.root / "screenshot.png").write_bytes(b"png")
        plain = METADATA.inspect_tree(self.root)
        assets = METADATA.inspect_tree(self.root, include_asset_properties=True)
        self.assertEqual(plain["files"], assets["files"])
        self.assertNotEqual(plain["digest"], assets["digest"])

    def test_symbolic_links_are_rejected(self):
        (self.root / "link").symlink_to(self.root / "nested" / "file.txt")
        with self.assertRaisesRegex(ValueError, "symbolic link"):
            METADATA.inspect_tree(self.root)


if __name__ == "__main__":
    unittest.main()
