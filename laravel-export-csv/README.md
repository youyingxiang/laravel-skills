# Laravel CSV Export Skill

A production-ready Laravel skill for exporting database records to CSV files with async processing, progress tracking, and user-friendly UI.

## Quick Start

### 1. Include the Export Component

Add to your Blade template:

```blade
<livewire:export 
    :requestParams="request()->input()" 
    exportClass="Orders">
</livewire:export>
```

### 2. Create Your Export Job

Create a new job in `app/Jobs/Exports/` following the pattern in `examples/OrdersExport.php`.

### 3. Ensure Queue Worker is Running

```bash
php artisan queue:work
```

## Features

- ✅ Async processing via Laravel queues
- ✅ Progress tracking with Livewire polling
- ✅ Large dataset support (chunked processing)
- ✅ Error handling and retry logic
- ✅ Cloud storage support (S3)
- ✅ User-friendly UI with modals
- ✅ Filter support via request parameters

## File Structure

```
skills/laravel-export-csv/
├── SKILL.md              # Skill specification
├── README.md             # This file
└── examples/
    └── OrdersExport.php  # Complete example implementation
```

## Usage in Your Project

The skill files are located at:
- `app/Livewire/Export.php` - Main component
- `app/Jobs/Exports/*.php` - Export jobs
- `resources/views/livewire/export.blade.php` - UI template
- `app/Support/CsvWriter.php` - CSV service

## Documentation

See `SKILL.md` for complete documentation including:
- Architecture overview
- Implementation steps
- Code examples
- Best practices
- Error handling
- Testing guidelines

## License

Part of the Laravel application codebase.
