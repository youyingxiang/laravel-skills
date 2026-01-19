---
name: laravel-export-csv
description: Export Laravel Eloquent data to CSV files using Livewire components and queue jobs for efficient async processing
version: 1.0.0
---

# Laravel CSV Export Skill

A reusable Laravel skill for exporting database records to CSV files with async processing, progress tracking, and user-friendly UI.

## When to use this skill

Use this skill when you need to:
- Export large datasets (orders, users, products, etc.) from Laravel applications
- Provide async CSV exports to avoid timeout issues
- Show export progress to users
- Generate CSV files with custom formatting and filtering
- Store exported files in cloud storage (S3) for download

## Architecture Overview

This skill uses a three-component architecture:

1. **Livewire Component** (`Export.php`) - Handles user interactions and status polling
2. **Queue Job** (`Orders.php` or other export jobs) - Processes data asynchronously
3. **CSV Writer Service** (`CsvWriter.php`) - Handles CSV generation

### Workflow

```
User clicks Export → Livewire Component → Dispatch Queue Job → 
Job processes data → Saves to Storage → Caches result → 
Livewire polls for status → Shows download link
```

## Implementation Steps

### 1. Create Export Job

Create a job class in `app/Jobs/Exports/`:

```php
<?php

namespace App\Jobs\Exports;

use App\Support\CsvWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class YourModelExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public CsvWriter $csvWriter,
        public int $userId,
        public array $params,
        public string $exportId
    ) {}

    public function handle(): void
    {
        $csvContent = $this->csvWriter->write($this->header(), $this->data());
        
        $fileName = $this->csvFileName() . '.csv';
        $path = $this->storagePath($fileName);
        
        Storage::put($path, $csvContent, ['ACL' => 'public-read']);
        $url = Storage::url($path);

        Cache::put("export:{$this->userId}:{$this->exportId}", [
            'status' => 'success',
            'url' => $url,
        ], now()->addHours(24));
    }

    protected function header(): array
    {
        return ['Column 1', 'Column 2', 'Column 3'];
    }

    protected function data(): array
    {
        // Query and format your data
        return [];
    }

    protected function csvFileName(): string
    {
        return 'export-' . now()->format('Y-m-d') . '-' . \Illuminate\Support\Str::random(5);
    }

    protected function storagePath(string $fileName): string
    {
        $environment = app()->environment('production') ? 'production' : 'staging';
        $domain = tenant()?->domains->first()?->domain ?? 'default';
        return "{$environment}/{$domain}/csv/{$fileName}";
    }
}
```

### 2. Use Livewire Export Component

In your Blade template:

```blade
<livewire:export 
    :requestParams="request()->input()" 
    exportClass="YourModel">
</livewire:export>
```

The component will:
- Display an "Export" button
- Show a modal with progress indicator
- Poll for export status every 2 seconds
- Display download link when ready

### 3. Configure Queue

Ensure your queue is running:

```bash
php artisan queue:work
```

For production, use a supervisor or queue worker service.

## Key Components

### Export Livewire Component (`app/Livewire/Export.php`)

**Responsibilities:**
- Trigger export action
- Generate unique export ID
- Dispatch queue job
- Poll for export status
- Handle UI state (processing, success, failed)

**Key Methods:**
- `export()` - Initiates the export process
- `checkExportStatus()` - Polls cache for export completion

### Export Job (`app/Jobs/Exports/*.php`)

**Responsibilities:**
- Query database with filters
- Format data for CSV
- Generate CSV content
- Save to storage
- Update cache with result

**Best Practices:**
- Use `chunk()` for large datasets to prevent memory issues
- Include error handling in `failed()` method
- Use meaningful file names with timestamps
- Store files in organized paths (environment/tenant/csv/)

### CSV Writer Service (`app/Support/CsvWriter.php`)

**Responsibilities:**
- Generate CSV content from arrays
- Handle encoding and formatting
- Add headers and rows

## Example: Orders Export

See `app/Jobs/Exports/Orders.php` for a complete implementation example.

**Features:**
- Exports order data with relationships (user, category, tier, participants)
- Includes financial calculations (subtotal, GST, refunds, net amount)
- Filters by search, date range, and sorting
- Formats amounts from cents to dollars
- Handles gateway fees and refunds

## Error Handling

The skill includes error handling:

```php
try {
    // Export logic
} catch (\Throwable $exception) {
    Log::error($exception);
    Cache::put("export:{$userId}:{$exportId}", [
        'status' => 'failed',
        'message' => $exception->getMessage(),
    ], now()->addHours(24));
    throw $exception;
}
```

Failed exports show error messages in the UI.

## Storage Configuration

Exports are stored in:
- **Development/Staging**: `staging/{domain}/csv/{filename}.csv`
- **Production**: `production/{domain}/csv/{filename}.csv`

Files are stored with `public-read` ACL for direct download.

## Cache Keys

Export status is cached with format:
```
export:{userId}:{exportId}
```

Cache expires after 24 hours.

## UI Features

The Livewire component provides:
- Modal overlay for export status
- Loading spinner during processing
- Success state with download link
- Error state with error message
- Auto-polling every 2 seconds while processing

## Testing

To test exports:

1. Ensure queue worker is running
2. Trigger export from UI
3. Check queue for job processing
4. Verify file in storage
5. Confirm cache entry exists

## Dependencies

- Laravel Livewire
- Laravel Queue
- CsvWriter service
- Storage facade (configured for S3 or local)

## Related Files

- `app/Livewire/Export.php` - Main Livewire component
- `app/Jobs/Exports/Orders.php` - Example export job
- `resources/views/livewire/export.blade.php` - Export UI
- `app/Support/CsvWriter.php` - CSV generation service
