---
name: laravel-livewire-tailwind-scaffold
description: Setup Laravel + Livewire + TailwindCSS + Alpine.js project scaffold with admin dashboard, model traits, filters, value objects, and complete directory structure. Use this when starting a new Laravel project that needs Livewire, TailwindCSS, and Alpine.js integration.
version: 1.0.0
license: MIT
metadata:
  author: youxingxiang
  version: "1.0.0"
---

# Laravel + Livewire + TailwindCSS + Alpine.js Scaffold

A comprehensive skill for setting up a Laravel project with Livewire, TailwindCSS, and Alpine.js, including admin dashboard, model traits, filters, value objects, and complete project structure.

## When to use this skill

Use this skill when you need to:
- Start a new Laravel project with Livewire, TailwindCSS, and Alpine.js
- Set up an admin dashboard with navigation
- Create reusable model traits (Sortable, Searchable, Filterable)
- Implement filter system for data queries
- Add value objects (Money, Percentage) for domain modeling
- Configure Vite with TailwindCSS
- Set up admin routing structure
- Implement authentication system with login/logout functionality

## Architecture Overview

This scaffold creates a complete Laravel application structure with:

1. **Frontend Stack**: TailwindCSS 4.0 + Alpine.js + Preline UI
2. **Backend Stack**: Laravel 12 + Livewire 3.7
3. **Queue System**: Laravel Horizon for queue monitoring
4. **Admin Structure**: Modular admin routes and controllers
5. **Model Traits**: Reusable query scopes (Sortable, Searchable, Filterable)
6. **Value Objects**: Domain modeling with Money and Percentage
7. **Support Classes**: CSV writer and utilities
8. **Authentication**: Complete login/logout system with FormRequest validation

## Implementation Steps

### 1. Install Composer Packages

Install required Composer packages:

```bash
composer require livewire/livewire:^3.7
composer require laravel/horizon:^5.43
composer require league/csv:^9.28
```

### 2. Install NPM Packages

Install required NPM packages:

```bash
npm install --save-dev @tailwindcss/vite@^4.0.0 tailwindcss@^4.0.0 vite@^7.0.7 laravel-vite-plugin@^2.0.0 axios@^1.11.0
npm install --save-dev @tailwindcss/forms@^0.5.11 @tailwindcss/typography@^0.5.19
npm install --save preline@^3.2.3
```

### 3. Create Directory Structure

Create the following directories:

```
app/
├── Models/
│   ├── Traits/
│   └── Filters/
├── Support/
│   └── Csv/
├── ValueObjects/
│   └── Exception/
└── Admin/
    ├── Dashboard/
    │   └── Http/
    │       └── Controllers/
    └── routes/

resources/
├── views/
│   ├── admin/
│   │   ├── layouts/
│   │   ├── components/
│   │   └── dashboard/
│   ├── livewire/
│   └── components/
├── css/
└── js/
```

### 4. Create Model Traits

#### Sortable Trait (`app/Models/Traits/Sortable.php`)

Provides sorting functionality for Eloquent models:

```php
<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Sortable
{
    public string $defaultDirection = 'desc';

    public function scopeSortable(Builder $query, array $defaultParameters = ['created_at' => 'desc']): Builder
    {
        if (request()->filled(['sort', 'direction'])) {
            return $this->queryOrderBuilder($query, request()->only(['sort', 'direction']));
        }

        $defaultSortArray = $this->formatToParameters($defaultParameters);
        if (! empty($defaultSortArray)) {
            request()->merge($defaultSortArray);
        }

        return $this->queryOrderBuilder($query, $defaultSortArray);
    }

    // ... (see references/sortable-trait.php for full implementation)
}
```

#### Searchable Trait (`app/Models/Traits/Searchable.php`)

Provides search functionality across multiple fields:

```php
<?php

namespace App\Models\Traits;

trait Searchable
{
    public function scopeApplySearch($query, ?string $search, array $searchableFields = [])
    {
        if (empty($search)) {
            return $query;
        }

        if (empty($searchableFields) && property_exists($this, 'searchableFields')) {
            $searchableFields = $this->searchableFields;
        }

        if (empty($searchableFields)) {
            return $query;
        }

        return $query->where(function ($query) use ($search, $searchableFields) {
            foreach ($searchableFields as $field) {
                if (str_contains($field, '.')) {
                    // Handle relation fields
                    $parts = explode('.', $field);
                    $column = array_pop($parts);
                    $relations = $parts;
                    $query->orWhereHas($relations[0], function ($query) use ($relations, $column, $search) {
                        $this->buildNestedWhereHas($query, array_slice($relations, 1), $column, $search);
                    });
                } else {
                    $query->orWhere($field, 'like', "%{$search}%");
                }
            }
        });
    }

    // ... (see references/searchable-trait.php for full implementation)
}
```

#### Filterable Trait (`app/Models/Traits/Filterable.php`)

Provides filter functionality:

```php
<?php

namespace App\Models\Traits;

use App\Models\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

trait Filterable
{
    public function scopeFilter(Builder $query, Filter $filter): Builder
    {
        return $filter->apply($query);
    }
}
```

### 5. Create Filter Base Class

Create `app/Models/Filters/Filter.php`:

```php
<?php

namespace App\Models\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use ReflectionClass;

abstract class Filter
{
    protected Request $request;
    protected Builder $builder;
    protected array $without = [];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    protected function getFilterMethods(): array
    {
        $class = new ReflectionClass(static::class);
        $methods = array_map(
            function ($method) use ($class) {
                if ($method->class === $class->getName()) {
                    return $method->name;
                }
                return null;
            },
            $class->getMethods()
        );

        return array_filter($methods, function ($method) {
            return $method !== null && ! in_array($method, $this->without);
        });
    }

    public function getFilters(): array
    {
        return array_filter($this->request->only($this->getFilterMethods()), function ($val) {
            return isset($val);
        });
    }

    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        foreach ($this->getFilters() as $name => $value) {
            if (method_exists($this, $name)) {
                if (isset($value)) {
                    $this->$name($value);
                } else {
                    $this->$name();
                }
            }
        }

        return $this->builder;
    }

    public function without(string $name): self
    {
        $this->without[] = $name;
        return $this;
    }
}
```

### 6. Create Support Classes

#### CsvWriter (`app/Support/CsvWriter.php`)

```php
<?php

namespace App\Support;

use App\Support\Csv\FilterTranscode;
use League\Csv\Writer;
use SplTempFileObject;

class CsvWriter
{
    public function write($header, $data)
    {
        $writer = Writer::createFromFileObject(new SplTempFileObject());
        $writer->setNewline("\r\n");
        $writer->insertOne($header);
        $writer->insertAll($data);

        stream_filter_register(FilterTranscode::FILTER_NAME . "*", FilterTranscode::class);
        if ($writer->supportsStreamFilter()) {
            $writer->addStreamFilter(FilterTranscode::FILTER_NAME . "UTF-8:UTF-16LE");
        }

        return $writer->getContent();
    }
}
```

#### FilterTranscode (`app/Support/Csv/FilterTranscode.php`)

```php
<?php

namespace App\Support\Csv;

use php_user_filter;

class FilterTranscode extends php_user_filter
{
    const FILTER_NAME = 'convert.transcode.';
    private $encoding_from = 'auto';
    private $encoding_to;

    public function onCreate()
    {
        if (strpos($this->filtername, self::FILTER_NAME) !== 0) {
            return false;
        }
        $params = substr($this->filtername, \strlen(self::FILTER_NAME));
        if (! preg_match('/^([-\w]+)(:([-\w]+))?$/', $params, $matches)) {
            return false;
        }
        if (isset($matches[1])) {
            $this->encoding_from = $matches[1];
        }
        $this->encoding_to = mb_internal_encoding();
        if (isset($matches[3])) {
            $this->encoding_to = $matches[3];
        }
        $this->params['locale'] = setlocale(LC_CTYPE, '0');
        if (stripos($this->params['locale'], 'UTF-8') === false) {
            setlocale(LC_CTYPE, 'en_US.UTF-8');
        }
        return true;
    }

    public function onClose()
    {
        setlocale(LC_CTYPE, $this->params['locale']);
    }

    public function filter($in, $out, &$consumed, $closing)
    {
        while ($res = stream_bucket_make_writeable($in)) {
            $res->data = @mb_convert_encoding($res->data, $this->encoding_to, $this->encoding_from);
            $consumed += $res->datalen;
            stream_bucket_append($out, $res);
        }
        return PSFS_PASS_ON;
    }
}
```

### 7. Create Value Objects

#### Money (`app/ValueObjects/Money.php`)

```php
<?php

namespace App\ValueObjects;

use App\ValueObjects\Exception\InvalidValueObject;
use App\ValueObjects\Exception\MisbehavedValueObject;

class Money
{
    const DEFAULT = 'SGD';

    public string $currency;
    public int $amountInCent;

    public static function withDefaultCurrency(int $amountInCent)
    {
        return new static(static::DEFAULT, $amountInCent);
    }

    public function __construct(string $currency, int $amountInCent)
    {
        if ($amountInCent < 0) {
            InvalidValueObject::throwIt(
                sprintf('Invalid Money amount: %s', $amountInCent)
            );
        }

        $this->currency = $currency;
        $this->amountInCent = $amountInCent;
    }

    public function add(self $money): self
    {
        return new static($money->currency, $money->amountInCent + $this->amountInCent);
    }

    public function toDecimal(): float
    {
        return bcdiv($this->amountInCent, 100, 2);
    }

    public function toString(): string
    {
        return sprintf('%s%s', $this->currency, number_format($this->amountInCent / 100, 2));
    }

    // ... (see references/money.php for full implementation)
}
```

#### Percentage (`app/ValueObjects/Percentage.php`)

```php
<?php

namespace App\ValueObjects;

use App\ValueObjects\Exception\InvalidValueObject;

class Percentage
{
    public float $percent;

    public function __construct(float $percent)
    {
        if ($percent < 0) {
            InvalidValueObject::throwIt(
                'Invalid percentage: %s, Percentage can not be negative',
                $percent
            );
        }
        $this->percent = $percent;
    }

    public static function fromFloat(float $percent)
    {
        return new self($percent);
    }
}
```

#### Exception Classes

Create `app/ValueObjects/Exception/InvalidValueObject.php`:

```php
<?php

namespace App\ValueObjects\Exception;

use Exception;

class InvalidValueObject extends Exception
{
    public static function throwIt(string $message, ...$args): void
    {
        throw new static(sprintf($message, ...$args));
    }
}
```

Create `app/ValueObjects/Exception/MisbehavedValueObject.php`:

```php
<?php

namespace App\ValueObjects\Exception;

use Exception;

class MisbehavedValueObject extends Exception
{
    public static function throwIt(string $message): void
    {
        throw new static($message);
    }
}
```

### 8. Create Dashboard

#### Controller (`app/Admin/Dashboard/Http/Controllers/DashboardController.php`)

```php
<?php

namespace App\Admin\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard.index');
    }
}
```

#### Route (`app/Admin/routes/dashboard.php`)

```php
<?php

declare(strict_types=1);

use App\Admin\Dashboard\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard.index');
```

#### View (`resources/views/admin/dashboard/index.blade.php`)

```blade
@extends('admin.layouts.app')

@section('content')
    <div class="w-full px-4 sm:px-6 md:px-8">
        <div class="flex flex-col">
            <div class="py-4 grid gap-3 md:flex md:justify-between md:items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Dashboard</h2>
                    <p class="text-sm text-gray-600">Welcome to the dashboard.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
```

### 9. Create Nav Link Component

Create the reusable nav-link component first, as it's used in navigation:

#### Nav Link Component Directory

Create directory:
```
resources/views/components/admin/
```

### 10. Create Layout Files

#### Main Layout (`resources/views/admin/layouts/app.blade.php`)

```blade
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <title>{{ config('app.name', 'Laravel') }}</title>
    <meta charset="UTF-8" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="icon" href="/images/favicon.ico">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('styles')
</head>
<body class="min-h-screen bg-gray-100">
    @include('admin.components.mobile-header')

    <main id="content" class="lg:ps-65 pt-15 lg:pt-0">
        <div class="flex flex-col h-full md:h-full min-h-[calc(100vh-56px)] sm:min-h-[calc(100vh)]">
            @include('admin.components.sidebar')
          
            @yield('content')

            <div class="hs-overlay-backdrop transition duration inset-0 bg-gray-900/50"></div>
        </div>
    </main>

    @livewireScripts
    @stack('scripts')
</body>
</html>
```

#### Mobile Header (`resources/views/admin/components/mobile-header.blade.php`)

```blade
<header class="lg:hidden lg:ms-65 fixed top-0 inset-x-0 flex flex-wrap md:justify-start md:flex-nowrap z-50 bg-white border-b border-gray-200">
    <div class="flex justify-between basis-full items-center w-full py-2.5 px-2 sm:px-5">

        <button type="button" class="w-7 h-9.5 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none" aria-haspopup="dialog" aria-expanded="false" aria-controls="hs-pro-sidebar" aria-label="Toggle navigation" data-hs-overlay="#hs-pro-sidebar">
            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 8L21 12L17 16M3 12H13M3 6H13M3 18H13"/></svg>
        </button>

        <div class="">
            <div class="hs-dropdown [--auto-close:inside] relative flex">
                <button id="hs-pro-dnwpd" type="button" class="inline-flex items-center text-start font-semibold text-gray-800 align-middle disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:text-gray-500 " aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                    {{ auth()->user()->name }}
                    <svg class="shrink-0 size-4 ms-1" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                </button>

                <div class="hs-dropdown-menu hs-dropdown-open:opacity-100 w-56 transition-[opacity,margin] duration opacity-0 hidden z-20 bg-white rounded-xl shadow-xl border border-gray-200" role="menu" aria-orientation="vertical" aria-labelledby="hs-pro-dnwpd">
                    <div class="border-t border-gray-200">
                        <form action="{{ route('admin.logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="cursor-pointer w-full flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 disabled:opacity-50 focus:outline-hidden focus:bg-gray-100">
                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="currentColor" stroke-linecap="round" stroke-linejoin="round"> <path d="M16 4V4C16 2.89543 15.1046 2 14 2L6 2C4.89543 2 4 2.89543 4 4L4 20C4 21.1046 4.89543 22 6 22L14 22C15.1046 22 16 21.1046 16 20V20" stroke="currentColor" stroke-width="2" fill="none"></path> <path d="M9.99999 12L21.5 12L21 12" stroke="currentColor" stroke-width="2" fill="none"></path> <path d="M17.2574 16.2427L21.5 12L17.2574 7.75739" stroke="currentColor" stroke-width="2" fill="none"></path> </g></svg>
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</header>
```

#### Nav Link Component (`resources/views/components/admin/nav-link.blade.php`)

First, create the reusable nav-link component:

```blade
@props([
    'route' => null,
    'routePattern' => null,
    'active' => false,
])

@php
    // Determine if this link is active
    $isActive = $active;
    
    if (!$isActive && $route) {
        $isActive = request()->routeIs($route);
    }
    
    if (!$isActive && $routePattern) {
        $isActive = request()->routeIs($routePattern);
    }
    
    // Default base classes
    $defaultBaseClasses = 'flex gap-x-3 py-2 px-3 text-sm rounded-lg hover:bg-white/10 focus:outline-hidden focus:bg-white/10';
    
    // Get custom classes from attributes or use default
    $baseClasses = $attributes->get('class') ?: $defaultBaseClasses;
    
    // Active state classes (always applied)
    $activeClasses = $isActive ? 'bg-white/10 text-white' : 'text-white/80';
    
    // Combine classes
    $classes = $baseClasses . ' ' . $activeClasses;
@endphp

<a class="{{ $classes }}" {{ $attributes->except('class') }}>
    {{ $slot }}
</a>
```

#### Navigation (`resources/views/admin/components/navigation.blade.php`)

```blade
<nav class="hs-accordion-group p-5 pt-0 w-full flex flex-col flex-wrap" data-hs-accordion-always-open>
    <ul class="space-y-1.5">
        <li>
            <x-admin.nav-link href="{{ route('admin.index') }}" route="admin.index">
                <svg class="shrink-0 size-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                    viewBox="0 0 24 24">
                    <g fill="currentColor" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="1 11 12 2 23 11" fill="none" stroke="currentColor" stroke-width="2">
                        </polyline>
                        <path d="m5,13v7c0,1.105.895,2,2,2h10c1.105,0,2-.895,2-2v-7" fill="none"
                            stroke="currentColor" stroke-width="2"></path>
                        <rect x="11" y="11" width="2" height="2" stroke="currentColor" stroke-width="2"
                            fill="currentColor"></rect>
                        <line x1="12" y1="22" x2="12" y2="18" fill="none"
                            stroke="currentColor" stroke-width="2"></line>
                    </g>
                </svg>
                Dashboard
            </x-admin.nav-link>
        </li>
    </ul>
</nav>
```

#### Sidebar (`resources/views/admin/components/sidebar.blade.php`)

```blade
<aside id="hs-pro-sidebar" class="hs-overlay [--auto-close:lg] hs-overlay-open:translate-x-0 -translate-x-full transition-all duration-300 transform w-65 h-full hidden fixed inset-y-0 start-0 z-60 bg-blue-950 lg:block lg:translate-x-0 lg:end-auto lg:bottom-0" tabindex="-1" aria-label="Sidebar" x-data="sidebarNavigation()">
    <div class="flex flex-col h-full max-h-full py-3">
        <header class="py-2 px-8">
            <div class="w-full">
               <span class="font-semibold text-white truncate max-w-48">
                    {{ config('app.name', 'POS System') }}
               </span>
            </div>
        </header>

        <div class="h-full overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-track]:bg-gray-100 [&::-webkit-scrollbar-thumb]:bg-gray-300">
            @include('admin.components.navigation')
        </div>

        @include('admin.components.sidebar-footer')

        <div class="lg:hidden absolute top-3 -end-3 z-10">
            <button type="button" class="w-6 h-7 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-md border border-white/10 bg-blue-950 text-gray-400 hover:text-gray-300 focus:outline-hidden focus:text-gray-300 disabled:opacity-50 disabled:pointer-events-none" data-hs-overlay="#hs-pro-sidebar">
                <svg class="shrink-0 w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="7 8 3 12 7 16"/><line x1="21" x2="11" y1="12" y2="12"/><line x1="21" x2="11" y1="6" y2="6"/><line x1="21" x2="11" y1="18" y2="18"/></svg>
            </button>
        </div>
    </div>
</aside>

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            // Sidebar Navigation Component - only handles accordion expansion
            Alpine.data('sidebarNavigation', () => ({
                init() {
                    // Auto-expand accordion sections based on current route
                    this.$nextTick(() => {
                        this.expandAccordionForCurrentRoute();
                    });
                },

                expandAccordionForCurrentRoute() {
                    // Check if any active link exists within accordion sections
                    const accordions = document.querySelectorAll('.hs-accordion');

                    accordions.forEach(accordion => {
                        const activeLink = accordion.querySelector('.bg-white\\/10.text-white');

                        if (activeLink) {
                            const button = accordion.querySelector('.hs-accordion-toggle');
                            const accordionId = accordion.getAttribute('id');
                            const content = document.getElementById(`${accordionId}-sub`);

                            if (button && content) {
                                button.setAttribute('aria-expanded', 'true');
                                button.classList.add('hs-accordion-active:bg-white/10');
                                content.classList.remove('hidden');
                                content.style.height = 'auto';
                            }
                        }
                    });
                }
            }));
        });
    </script>
@endpush
```

#### Sidebar Footer (`resources/views/admin/components/sidebar-footer.blade.php`)

```blade
<footer class="hidden lg:block sticky bottom-0 inset-x-0 border-t border-white/10">
    <div class="px-7 ">
        <div class="hs-dropdown [--auto-close:inside] relative flex">
            <button id="hs-pro-dnwpd" type="button"
                class="cursor-pointer group w-full inline-flex items-center pt-3 text-start text-white align-middle disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden"
                aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                <div class="flex-shrink-0">
                    <div class="inline-flex items-center justify-center size-8 bg-blue-100 rounded-lg">
                        <span class="text-sm font-medium text-blue-800">{{ auth()->user()->initials }}</span>
                    </div>
                </div>
                <span class="block ms-3">
                    <span
                        class="block text-sm font-medium text-white group-hover:text-white/70 group-focus-hover:text-white/70">
                        {{ auth()->user()->name }}
                    </span>
                </span>
                <svg class="shrink-0 size-3.5 ms-auto" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="m7 15 5 5 5-5" />
                    <path d="m7 9 5-5 5 5" />
                </svg>
            </button>

            <div class="hs-dropdown-menu hs-dropdown-open:opacity-100 w-56 transition-[opacity,margin] duration opacity-0 hidden z-20 bg-white rounded-lg shadow-xl"
                role="menu" aria-orientation="vertical" aria-labelledby="hs-pro-dnwpd">
                <div class="border-t border-gray-200">
                    <form action="{{ route('admin.logout') }}" method="POST">
                        @csrf
                        <button type="submit"
                            class="cursor-pointer w-full flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 disabled:opacity-50 focus:outline-hidden focus:bg-gray-100">
                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" viewBox="0 0 24 24">
                                <g fill="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                    <path
                                        d="M16 4V4C16 2.89543 15.1046 2 14 2L6 2C4.89543 2 4 2.89543 4 4L4 20C4 21.1046 4.89543 22 6 22L14 22C15.1046 22 16 21.1046 16 20V20"
                                        stroke="currentColor" stroke-width="2" fill="none"></path>
                                    <path d="M9.99999 12L21.5 12L21 12" stroke="currentColor" stroke-width="2"
                                        fill="none"></path>
                                    <path d="M17.2574 16.2427L21.5 12L17.2574 7.75739" stroke="currentColor"
                                        stroke-width="2" fill="none"></path>
                                </g>
                            </svg>
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</footer>
```

### 11. Update Service Providers

#### AppServiceProvider (`app/Providers/AppServiceProvider.php`)

Add route mapping method:

```php
public function boot(): void
{
    $this->mapRoutes();
}

protected function mapRoutes(): void
{
    $this->app->booted(function () {
        $dir = new \DirectoryIterator(app_path('Admin/routes'));

        foreach ($dir as $file) {
            if ($file->isFile()) {
                $baseMiddleware = ['web'];
                if (! in_array($file->getFilename(), ['auth.php'])) {
                    $baseMiddleware[] = 'auth';
                }

                Route::middleware($baseMiddleware)
                    ->name('admin.')
                    ->prefix('admin')
                    ->group(app_path('Admin/routes/'.$file->getFilename()));
            }
        }
    });
}
```

#### HorizonServiceProvider (`app/Providers/HorizonServiceProvider.php`)

```php
<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return in_array(optional($user)->email, [
                //
            ]);
        });
    }
}
```

Update `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
];
```

### 12. Create Vite Configuration

Create `vite.config.js`:

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
```

### 13. Create CSS and JS Files

#### CSS (`resources/css/app.css`)

```css
@import "tailwindcss";

/* Plugins */
@plugin "@tailwindcss/forms";
@plugin "@tailwindcss/typography";

/* Preline UI */
@source "../../node_modules/preline/dist/*.js";
@import "../../node_modules/preline/variants.css";

.form-label {
    @apply block text-sm font-medium text-gray-700 mb-2;
}

.form-control {
    @apply py-2 px-4 block w-full border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none transition-colors duration-200;
}

.form-control:focus {
    @apply outline-none ring-1 ring-blue-500;
}

/* Form control variants */
.form-control-sm {
    @apply py-2 px-3 text-xs;
}

.form-control-lg {
    @apply py-3 px-5 text-base;
}

/* Form control states */
.form-control-error {
    @apply border-red-300 focus:border-red-500 focus:ring-red-500;
}

.form-control-success {
    @apply border-green-300 focus:border-green-500 focus:ring-green-500;
}

/* Checkbox styling */
.form-checkbox-default {
    @apply flex items-center cursor-pointer;
}

.form-checkbox-default span {
    @apply text-sm text-gray-700;
}

.form-checkbox {
    @apply shrink-0 border-gray-300 rounded-sm text-blue-600 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none cursor-pointer;
}

/* Radio and Checkbox containers - Soft Pill Design */
.form-radio-group {
    @apply grid grid-cols-2 gap-3;
}

.form-radio-option {
    @apply relative flex items-center justify-center py-2 px-6 w-full bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-600 cursor-pointer transition-all duration-200 hover:bg-gray-100 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1;
}

.form-radio-option input[type="radio"] {
    @apply absolute opacity-0 w-0 h-0;
}

.form-radio-option span {
    @apply text-sm font-medium text-gray-600;
}

/* Selected state for pill radio buttons */
.form-radio-option input[type="radio"]:checked + span {
    @apply text-blue-700;
}

.form-radio-option input[type="radio"]:checked {
    @apply bg-blue-50 border-blue-500 text-blue-700;
}

.form-radio-option:has(input[type="radio"]:checked) {
    @apply bg-blue-50 border-blue-500 shadow-sm;
}

/* Alternative approach for checked state */
.form-radio-option.checked {
    @apply bg-blue-50 border-blue-500 shadow-sm;
}

.form-radio-option.checked span {
    @apply text-blue-700;
}

/* Default Radio Button Design */
.form-radio-default {
    @apply flex gap-x-10;
}

.form-radio-default input[type="radio"] {
    @apply shrink-0 border-gray-400/75 rounded-full text-blue-600 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none cursor-pointer;
}

.form-radio-default label {
    @apply text-sm text-gray-700 cursor-pointer;
}

.form-radio-default label span {
    @apply ml-1;
}

/* Select wrapper for custom styling */
.form-select-wrapper {
    @apply relative;
}

.form-select-wrapper select {
    @apply appearance-none;
}

.form-select-wrapper::after {
    @apply absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none;
    font-size: 0.75rem;
    color: #6b7280;
}

/* Input group for combined inputs (like phone with country code) */
.form-input-group {
    @apply relative;
}

.form-input-group .form-control {
    @apply pl-12;
}

.form-input-group-addon {
    @apply absolute inset-y-0 left-0 flex items-center text-gray-500;
}

/* Combined input component for inputs with addons (like price with currency, phone with country code, etc.) */
.form-combined-input {
    @apply flex rounded-lg;
}

.form-combined-addon {
    @apply px-4 inline-flex items-center min-w-fit rounded-s-md border border-e-0 border-gray-300 bg-gray-50 text-sm text-gray-500;
}

.form-combined-field {
    @apply py-2 px-3 block w-full border-gray-300 rounded-e-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none transition-colors duration-200;
}

.form-combined-field:focus {
    @apply outline-none ring-1 ring-blue-500;
}

/* Preline Button Classes */
.btn {
    @apply inline-flex items-center justify-center gap-x-2 text-sm font-medium rounded-lg border border-transparent disabled:opacity-50 disabled:pointer-events-none focus:outline-none transition-all duration-200 px-4 py-2 active:transform;
}

.btn-primary {
    @apply text-white bg-blue-600 hover:bg-blue-700 focus:ring-blue-500 active:bg-blue-800;
}

.btn-secondary {
    @apply border border-blue-200 bg-blue-100 text-blue-800 hover:bg-blue-200 focus:bg-blue-200 active:bg-blue-200 active:border-gray-300;
}

.btn-default {
    @apply text-gray-700 bg-white border-gray-300 hover:bg-gray-50 focus:ring-blue-500 active:bg-gray-100 active:border-gray-400;
}

.btn-success {
    @apply text-white bg-green-600 hover:bg-green-700 focus:ring-green-500 active:bg-green-800;
}

.btn-warning {
    @apply text-white bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500 active:bg-yellow-800;
}

.btn-error {
    @apply text-white bg-red-600 hover:bg-red-700 focus:ring-red-500 active:bg-red-800;
}

/* Button sizes */
.btn-sm {
    @apply px-3 py-1.5 text-xs;
}

.btn-lg {
    @apply px-6 py-3 text-base;
}

/* Link Primary - Hyperlink styling */
.link-primary {
    @apply text-blue-600 hover:text-blue-800 transition-colors duration-200;
}

.link-primary:hover {
    @apply underline;
}

.link-danger {
    @apply text-red-600 hover:text-red-800 transition-colors duration-200;
}

.link-danger:hover {
    @apply underline;
}

@layer base {
    html {
        /* Override input with icon */
        label.input:focus-within {
            outline-offset: 0 !important;
            outline-width: 1px !important;
        }
    }

    button:not(:disabled),
    [role="button"]:not(:disabled) {
        cursor: pointer;
    }
     /* Alpine.js x-cloak to prevent FOUC (Flash of Unstyled Content) */
     [x-cloak] {
        display: none !important;
    }
}

/* Defaults hover styles on all devices */
@custom-variant hover (&:hover);
```

#### JS (`resources/js/app.js`)

```javascript
import './bootstrap';
import 'preline';
```

#### Bootstrap JS (`resources/js/bootstrap.js`)

```javascript
import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
```

## Usage in Models

### Using Traits

```php
<?php

namespace App\Models;

use App\Models\Traits\Filterable;
use App\Models\Traits\Searchable;
use App\Models\Traits\Sortable;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use Searchable, Sortable, Filterable;

    protected array $searchableFields = [
        'name',
        'email',
    ];

    protected $sortableAs = [
        'customers_count',
    ];
}
```

### Using Filters

```php
<?php

namespace App\Models\Filters;

use Illuminate\Database\Eloquent\Builder;

class UserFilter extends Filter
{
    public function status(int $status): Builder
    {
        return $this->builder->where('status', $status);
    }

    public function role(string $role): Builder
    {
        return $this->builder->where('role', $role);
    }
}
```

In Controller:

```php
$users = User::query()
    ->filter(new UserFilter($request))
    ->applySearch($request->search)
    ->when($request->get('sort'), fn ($builder) => $builder->sortable(), fn ($builder) => $builder->latest())
    ->paginate(30);
```

### 14. Create User Command

#### Create User Command (`app/Console/Commands/CreateUserCommand.php`)

Create a console command to create admin users:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create-user 
                            {--name= : The name of the user}
                            {--email= : The email of the user}
                            {--password= : The password of the user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new admin user with name, email, and password';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Get input from options or prompt
        $name = $this->option('name') ?: text(
            label: 'What is the user\'s name?',
            placeholder: 'John Doe',
            required: true
        );

        $email = $this->option('email') ?: text(
            label: 'What is the user\'s email?',
            placeholder: 'user@example.com',
            required: true,
            validate: fn (string $value) => match (true) {
                ! filter_var($value, FILTER_VALIDATE_EMAIL) => 'The email must be a valid email address.',
                User::where('email', $value)->exists() => 'This email is already taken.',
                default => null
            }
        );

        $passwordInput = $this->option('password') ?: password(
            label: 'What is the user\'s password?',
            placeholder: 'password',
            required: true,
            validate: fn (string $value) => match (true) {
                strlen($value) < 8 => 'The password must be at least 8 characters.',
                default => null
            }
        );

        // Validate all inputs
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $passwordInput,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            $this->error('Validation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->error('  • '.$error);
            }

            return self::FAILURE;
        }

        try {
            // Create the user
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($passwordInput),
            ]);

            $this->info('User created successfully!');
            $this->newLine();
            $this->table(
                ['ID', 'Name', 'Email', 'Created At'],
                [
                    [
                        $user->id,
                        $user->name,
                        $user->email,
                        $user->created_at->format('Y-m-d H:i:s'),
                    ],
                ]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to create user: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
```

#### Usage

**Interactive mode** (recommended):
```bash
php artisan admin:create-user
```

**Command line options**:
```bash
php artisan admin:create-user --name="John Doe" --email="john@example.com" --password="secretpassword"
```

**Mixed mode** (partial options, partial interactive):
```bash
php artisan admin:create-user --name="John Doe" --email="john@example.com"
# Then will prompt for password
```

#### Features

1. **Interactive Input**: Uses Laravel Prompts for interactive input
2. **Command Line Options**: Supports passing parameters via options
3. **Validation**:
   - Email format validation
   - Email uniqueness check
   - Password minimum 8 characters
4. **Security**: Password is hashed using Hash facade
5. **User Feedback**: Displays a table with created user information

### 15. Create Authentication System

#### Login Request (`app/Admin/Auth/Http/Requests/LoginRequest.php`)

Create a FormRequest for login validation:

```php
<?php

declare(strict_types=1);

namespace App\Admin\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'remember' => $this->has('remember') ? true : false,
        ]);
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
        ];
    }
}
```

#### Login Controller (`app/Admin/Auth/Http/Controllers/LoginController.php`)

Create the login controller:

```php
<?php

declare(strict_types=1);

namespace App\Admin\Auth\Http\Controllers;

use App\Admin\Auth\Http\Requests\LoginRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Display the login form.
     */
    public function showLoginForm(): View
    {
        return view('admin.auth.login');
    }

    /**
     * Handle an authentication attempt.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            return redirect()->intended(route('admin.index'))
                ->with('success', 'Welcome back, '.Auth::user()->name.'!');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')
            ->with('success', 'You have been logged out successfully.');
    }
}
```

#### Auth Routes (`app/Admin/routes/auth.php`)

Create authentication routes:

```php
<?php

declare(strict_types=1);

use App\Admin\Auth\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'login'])->middleware('guest');

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');
```

#### Login View (`resources/views/admin/auth/login.blade.php`)

Create the login form with Alpine.js for password visibility toggle:

```blade
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <title>Admin Login - {{ config('app.name', 'Laravel') }}</title>
    <meta charset="UTF-8" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="icon" href="/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-100">
    <div class="flex min-h-full flex-col justify-center py-12 sm:px-6 lg:px-8" x-data="loginForm()">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="flex justify-center">
                <h1 class="text-3xl font-bold text-blue-950">{{ config('app.name', 'Triplebase') }}</h1>
            </div>
        </div>

        <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-[480px]">
            <div class="bg-white px-6 py-12 shadow sm:rounded-lg sm:px-12">
                @if (session('success'))
                    <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-800" role="alert">
                        <svg class="inline w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        {{ session('success') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800" role="alert">
                        <svg class="inline w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form class="space-y-6" action="{{ route('admin.login') }}" method="POST">
                    @csrf

                    <!-- Email Field -->
                    <div>
                        <label for="email" class="block text-sm font-medium leading-6 text-gray-900">
                            Email address
                        </label>
                        <div class="mt-2">
                            <input 
                                id="email" 
                                name="email" 
                                type="email" 
                                autocomplete="email" 
                                required 
                                value="{{ old('email') }}"
                                class="block w-full rounded-md border-0 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6 @error('email') ring-red-500 @enderror"
                                placeholder="you@example.com">
                        </div>
                        @error('email')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password Field -->
                    <div>
                        <label for="password" class="block text-sm font-medium leading-6 text-gray-900">
                            Password
                        </label>
                        <div class="mt-2 relative">
                            <input 
                                :type="showPassword ? 'text' : 'password'"
                                id="password" 
                                name="password" 
                                autocomplete="current-password" 
                                required
                                class="block w-full rounded-md border-0 py-2 px-3 pr-10 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6 @error('password') ring-red-500 @enderror"
                                placeholder="••••••••">
                            <button 
                                type="button" 
                                @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 flex items-center pr-3">
                                <svg x-show="!showPassword" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <svg x-show="showPassword" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input 
                                id="remember" 
                                name="remember" 
                                type="checkbox" 
                                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600">
                            <label for="remember" class="ml-3 block text-sm leading-6 text-gray-700">
                                Remember me
                            </label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button 
                            type="submit"
                            class="flex w-full justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 transition-colors">
                            Sign in
                        </button>
                    </div>
                </form>
            </div>

            <p class="mt-10 text-center text-sm text-gray-500">
                © {{ date('Y') }} {{ config('app.name', 'Triplebase') }}. All rights reserved.
            </p>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('loginForm', () => ({
                showPassword: false
            }));
        });
    </script>
    @livewireScripts
</body>
</html>
```

#### Update Route Mapping

Update `app/Providers/AppServiceProvider.php` to include auth routes:

```php
protected function mapRoutes(): void
{
    $this->app->booted(function () {
        $dir = new \DirectoryIterator(app_path('Admin/routes'));

        foreach ($dir as $file) {
            if ($file->isFile()) {
                $baseMiddleware = ['web'];
                // Auth routes don't require authentication
                if (! in_array($file->getFilename(), ['auth.php'])) {
                    $baseMiddleware[] = 'auth';
                }

                Route::middleware($baseMiddleware)
                    ->name('admin.')
                    ->prefix('admin')
                    ->group(app_path('Admin/routes/'.$file->getFilename()));
            }
        }
    });
}
```

#### Configure Guest Redirect in Bootstrap

Update `bootstrap/app.php` to redirect unauthenticated users to the admin login page:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function ($request) {
            // Check if the request is for admin routes
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login');
            }
            // Default fallback (should rarely be hit)
            return '/login';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

This configuration ensures that:
- Unauthenticated users accessing `/admin` or `/admin/*` routes are redirected to `admin.login`
- Other routes fall back to the default `/login` route
- The redirect is handled automatically by Laravel's authentication middleware

## Build and Run

After setup:

1. Build assets:
   ```bash
   npm run build
   ```

2. Run migrations:
   ```bash
   php artisan migrate
   ```

3. Create an admin user:
   ```bash
   php artisan admin:create-user
   ```
   Or with options:
   ```bash
   php artisan admin:create-user --name="Admin User" --email="admin@example.com" --password="password123"
   ```

4. Publish Horizon assets:
   ```bash
   php artisan horizon:install
   php artisan horizon:publish
   ```

5. Access dashboard:
   Visit `/admin` to see the dashboard

## Dependencies

- Laravel 12+
- Livewire 3.7+
- TailwindCSS 4.0+
- Vite 7.0+
- Laravel Horizon 5.43+
- League CSV 9.28+
- Preline UI 3.2.3+

### 14. Create Authentication System

#### Login Request (`app/Admin/Auth/Http/Requests/LoginRequest.php`)

Create a FormRequest for login validation:

```php
<?php

declare(strict_types=1);

namespace App\Admin\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'remember' => $this->has('remember') ? true : false,
        ]);
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
        ];
    }
}
```

#### Login Controller (`app/Admin/Auth/Http/Controllers/LoginController.php`)

Create the login controller:

```php
<?php

declare(strict_types=1);

namespace App\Admin\Auth\Http\Controllers;

use App\Admin\Auth\Http\Requests\LoginRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Display the login form.
     */
    public function showLoginForm(): View
    {
        return view('admin.auth.login');
    }

    /**
     * Handle an authentication attempt.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            return redirect()->intended(route('admin.index'))
                ->with('success', 'Welcome back, '.Auth::user()->name.'!');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')
            ->with('success', 'You have been logged out successfully.');
    }
}
```

#### Auth Routes (`app/Admin/routes/auth.php`)

Create authentication routes:

```php
<?php

declare(strict_types=1);

use App\Admin\Auth\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'login'])->middleware('guest');

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');
```

#### Login View (`resources/views/admin/auth/login.blade.php`)

Create the login form with Alpine.js for password visibility toggle:

```blade
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <title>Admin Login - {{ config('app.name', 'Laravel') }}</title>
    <meta charset="UTF-8" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="icon" href="/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-100">
    <div class="flex min-h-full flex-col justify-center py-12 sm:px-6 lg:px-8" x-data="loginForm()">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="flex justify-center">
                <h1 class="text-3xl font-bold text-blue-950">{{ config('app.name', 'Triplebase') }}</h1>
            </div>
        </div>

        <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-[480px]">
            <div class="bg-white px-6 py-12 shadow sm:rounded-lg sm:px-12">
                @if (session('success'))
                    <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-800" role="alert">
                        <svg class="inline w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        {{ session('success') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800" role="alert">
                        <svg class="inline w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form class="space-y-6" action="{{ route('admin.login') }}" method="POST">
                    @csrf

                    <!-- Email Field -->
                    <div>
                        <label for="email" class="block text-sm font-medium leading-6 text-gray-900">
                            Email address
                        </label>
                        <div class="mt-2">
                            <input 
                                id="email" 
                                name="email" 
                                type="email" 
                                autocomplete="email" 
                                required 
                                value="{{ old('email') }}"
                                class="block w-full rounded-md border-0 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6 @error('email') ring-red-500 @enderror"
                                placeholder="you@example.com">
                        </div>
                        @error('email')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password Field -->
                    <div>
                        <label for="password" class="block text-sm font-medium leading-6 text-gray-900">
                            Password
                        </label>
                        <div class="mt-2 relative">
                            <input 
                                :type="showPassword ? 'text' : 'password'"
                                id="password" 
                                name="password" 
                                autocomplete="current-password" 
                                required
                                class="block w-full rounded-md border-0 py-2 px-3 pr-10 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6 @error('password') ring-red-500 @enderror"
                                placeholder="••••••••">
                            <button 
                                type="button" 
                                @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 flex items-center pr-3">
                                <svg x-show="!showPassword" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <svg x-show="showPassword" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input 
                                id="remember" 
                                name="remember" 
                                type="checkbox" 
                                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600">
                            <label for="remember" class="ml-3 block text-sm leading-6 text-gray-700">
                                Remember me
                            </label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button 
                            type="submit"
                            class="flex w-full justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 transition-colors">
                            Sign in
                        </button>
                    </div>
                </form>
            </div>

            <p class="mt-10 text-center text-sm text-gray-500">
                © {{ date('Y') }} {{ config('app.name', 'Triplebase') }}. All rights reserved.
            </p>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('loginForm', () => ({
                showPassword: false
            }));
        });
    </script>
    @livewireScripts
</body>
</html>
```

#### Update Route Mapping

Update `app/Providers/AppServiceProvider.php` to include auth routes:

```php
protected function mapRoutes(): void
{
    $this->app->booted(function () {
        $dir = new \DirectoryIterator(app_path('Admin/routes'));

        foreach ($dir as $file) {
            if ($file->isFile()) {
                $baseMiddleware = ['web'];
                // Auth routes don't require authentication
                if (! in_array($file->getFilename(), ['auth.php'])) {
                    $baseMiddleware[] = 'auth';
                }

                Route::middleware($baseMiddleware)
                    ->name('admin.')
                    ->prefix('admin')
                    ->group(app_path('Admin/routes/'.$file->getFilename()));
            }
        }
    });
}
```

#### Add Logout Link to Sidebar

Update `resources/views/admin/components/sidebar-footer.blade.php` to include logout:

```blade
<div class="pt-6 mt-6 border-t border-gray-200">
    <div class="px-6">
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="w-full text-left text-sm text-gray-600 hover:text-gray-900">
                Sign out
            </button>
        </form>
        <p class="mt-4 text-xs text-gray-500">© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</div>
```

### Key Features of the Login System

1. **FormRequest Validation**: Uses `LoginRequest` for clean validation logic
2. **Remember Me**: Supports "remember me" functionality with session persistence
3. **Password Visibility Toggle**: Alpine.js-powered show/hide password button
4. **Error Handling**: Displays validation errors and success messages
5. **Session Regeneration**: Regenerates session ID on login for security
6. **Intended Redirect**: Redirects to intended page after login
7. **Guest Middleware**: Protects login routes from authenticated users
8. **Auth Middleware**: Protects logout route from guests

### Authentication Flow

1. User visits `/admin/login` (guest middleware)
2. User submits login form with email/password
3. `LoginRequest` validates input
4. `LoginController::login()` attempts authentication
5. On success: session regenerated, redirect to intended page
6. On failure: redirect back with error message
7. Logout: invalidates session and regenerates token

## Related Files

- Model Traits: `app/Models/Traits/`
- Filters: `app/Models/Filters/`
- Value Objects: `app/ValueObjects/`
- Support Classes: `app/Support/`
- Admin Routes: `app/Admin/routes/`
- Admin Views: `resources/views/admin/`
- Auth Controllers: `app/Admin/Auth/Http/Controllers/`
- Auth Requests: `app/Admin/Auth/Http/Requests/`
- Console Commands: `app/Console/Commands/`