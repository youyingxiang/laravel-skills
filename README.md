# Laravel Skills

A collection of reusable Laravel skills for Cursor IDE.

## Skills

### git-commit

Smart git commit workflow for feature branches. Analyzes staged/unstaged changes, extracts the issue number from the branch name (e.g. `gh-1356` → `#1356`), auto-generates a conventional commit message, and executes the full pipeline: `git add .` → `git commit` → merge into `main` with `--no-ff` → `git push`.

Invoke with `/git-commit` in Claude Code. Install to `~/.claude/skills/git-commit/skill.json`.

**Commit message format:**
```
#1356 Add import mileage feature for task repairs
- Add importMileage endpoint to update repair mileage via CSV
- CSV format: Repair ID (R-xxxxx), Mileage
- Skip repairs where mileage is already the same
- Add Import Mileage button and modal to task repairs index
```

### laravel-livewire-tailwind-scaffold

Setup Laravel + Livewire + TailwindCSS + Alpine.js project scaffold with admin dashboard, model traits, filters, value objects, and complete directory structure.

### laravel-export-csv

Export Laravel Eloquent data to CSV files using Livewire components and queue jobs for efficient async processing.

## Usage

These skills are designed to be used with Claude Code. Place them in your `~/.claude/skills/` directory to use them across projects.

## License

MIT
