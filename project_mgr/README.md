# Project Manager Workspace

This folder manages repository notes and contributor tracking for this project. It provides a simple, auditable workflow for adding internal notes and ensuring contributors are recorded.

## Folder Contents

- notes/ for dated note files
- CONTRIBUTORS.md for tracking note contributors
- add_note.php for creating a note and registering a contributor

## Contribution Rules

- If you are not the repository owner, all changes in this folder must be submitted by pull request so additions can be reviewed before use.
- Every new note must include the contributor in CONTRIBUTORS.md.

## Add a Note (CLI)

Run from the repository root:

php project_mgr/add_note.php --user "Your Name" --title "Note title" --body "Your note text" --tags "tag1,tag2" --source "optional link"

This will:

- Create a note in project_mgr/notes/
- Add the contributor to project_mgr/CONTRIBUTORS.md if not already listed

## Note Format

Notes are Markdown files with a standard header including title, date, author, tags, and optional source.