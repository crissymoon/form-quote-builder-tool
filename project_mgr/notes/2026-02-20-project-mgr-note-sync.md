# project_mgr note sync

- Date: 2026-02-20
- Author: CrissyMoon
- Tags: none

## Notes

sync_readme.php reads the last four dated notes and writes a Recent Updates block at the top of README.md. add_note.php calls sync automatically after every note. The README block uses HTML comment markers so it can be replaced cleanly on every sync.
