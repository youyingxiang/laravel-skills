---
name: git-commit
description: Smart git commit workflow — auto-generates a conventional commit message from branch name and diff, stages all changes, commits, merges into main with --no-ff, and pushes. Invoke with /git-commit.
version: 1.0.0
---

# Git Commit Workflow Skill

A complete feature-branch commit-and-merge workflow. Analyzes your staged/unstaged changes, extracts the issue number from the branch name, generates a high-quality commit message, then executes the full `commit → merge main → push` pipeline after your confirmation.

## When to Use This Skill

Use `/git-commit` when you have finished work on a feature branch (e.g., `gh-1356`) and want to:
- Automatically generate a descriptive commit message from your diff
- Stage, commit, merge into `main` with `--no-ff`, and push — all in one workflow
- Ensure consistent commit message formatting across your team

## Commit Message Format

```
#<ISSUE> <Imperative-mood title ≤72 chars>
- Specific change bullet 1
- Specific change bullet 2
- Specific change bullet 3
```

### Example

```
#1356 Add import mileage feature for task repairs
- Add importMileage endpoint to update repair mileage via CSV
- CSV format: Repair ID (R-xxxxx), Mileage
- Skip repairs where mileage is already the same
- Add Import Mileage button and modal to task repairs index
```

## Branch Name → Issue Number Mapping

| Branch pattern         | Extracted issue |
|------------------------|-----------------|
| `gh-1356`              | `#1356`         |
| `GH-42`                | `#42`           |
| `issue-99`             | `#99`           |
| `issue/99`             | `#99`           |
| `fix/99`               | `#99`           |
| `feat/99`              | `#99`           |
| `my-feature-99`        | `#99`           |
| No number in branch    | title only      |

## Workflow Steps

1. **Gather context** — reads branch name, full diff (`git diff HEAD`), and status
2. **Extract issue number** — from branch name patterns above
3. **Analyze diff** — identifies the primary purpose and all concrete changes
4. **Generate commit message** — shows it to you for review before touching git
5. **Await confirmation** — you review and edit the message if needed
6. **Execute pipeline** — `git add .` → `git commit` → `git checkout main` → `git pull origin main` → `git merge <branch> --no-ff` → `git push`
7. **Final report** — prints the commit SHA, branch merged, and push result

## Usage

```
/git-commit
```

Or with optional context hints:

```
/git-commit focus on the API changes and CSV validation logic
```

## Installation

Copy `skill.json` to `~/.claude/skills/git-commit/skill.json`:

```bash
mkdir -p ~/.claude/skills/git-commit
cp skill.json ~/.claude/skills/git-commit/skill.json
```

## Safety Features

- **Always confirms before acting** — shows the commit message and asks for approval before running any git command
- **Stops on failure** — if any git command fails, execution halts with the error and a suggested fix
- **Sensitive file detection** — warns if `.env`, `*.pem`, `*.key`, or `secrets.*` files are about to be staged
- **Clean tree check** — reports clearly if there is nothing to commit
- **No destructive flags** — never uses `--force` or `--hard`
- **Wrong-branch protection** — stops if the current branch is already `main`
- **No AI attribution** — commit message contains only the issue reference, title, and bullets; never appends `Made-with: Cursor`, `Co-Authored-By: Claude`, `Generated with Claude Code`, or any similar footer

## Related Files

- `skill.json` — Claude Code skill definition (install this to `~/.claude/skills/git-commit/`)
