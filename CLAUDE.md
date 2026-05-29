<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/mcp (MCP) - v0
- laravel/prompts (PROMPTS) - v0
- livewire/flux (FLUXUI_FREE) - v2
- livewire/flux-pro (FLUXUI_PRO) - v2
- livewire/livewire (LIVEWIRE) - v4
- livewire/volt (VOLT) - v1
- laravel/boost (BOOST) - v2
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `sail npm run build`, `sail npm run dev`, or `sail composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `pa route:list`). Use `pa list` to discover available commands and `pa [command] --help` to check parameters.
- Inspect routes with `pa route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `pa config:show app.name`, `pa config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `pa tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `pa tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `sail up -d` and stop them with `sail stop`.
- Open the application in the browser by running `sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `sail`. Examples:
    - Run Artisan Commands: `pa migrate`
    - Install Composer packages: `sail composer install`
    - Execute Node commands: `sail npm run dev`
    - Execute PHP scripts: `sail php [script]`
- View all available Sail commands by running `sail` without arguments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `pa make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `pa list` and check their parameters with `pa [command] --help`.
- If you're creating a generic PHP class, use `pa make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `pa make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `pa make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `sail npm run build` or ask the user to run `sail npm run dev` or `sail composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== volt/core rules ===

# Livewire Volt

- Single-file Livewire components: PHP logic and Blade templates in one file.
- Always check existing Volt components to determine functional vs class-based style.
- IMPORTANT: Always use `search-docs` tool for version-specific Volt documentation and updated code examples.
- IMPORTANT: Activate `volt-development` every time you're working with a Volt or single-file component-related task.

=== flux/core rules ===

# Flux UI Components

Flux UI (https://fluxui.dev/components/) is the comprehensive component library used throughout this application.

## Component Categories & Usage

**Layouts:** Header, Sidebar - use for page structure and navigation

**Form & Input:** Input, Textarea, Checkbox, Radio, Switch, Select, Autocomplete, Date Picker, Time Picker, OTP Input, File Upload, Field wrapper - for all form needs

**Data Display:** Table, Pagination, Card, Badge, Avatar, Progress, Skeleton, Timeline, Chart - for displaying and organizing data

**Navigation & Menus:** Dropdown, Navbar, Breadcrumbs, Tabs, Command palette, Context menus

**Feedback & Overlays:** Modal, Popover, Tooltip, Toast, Callout - for user feedback and notifications

**Content Components:** Heading, Text, Editor, Composer, Button (variants: primary, filled, danger, ghost, subtle), Icon - basic building blocks

**Advanced:** Accordion, Calendar, Kanban board, Color Picker, Pillbox, Slider, Separator, Profile

## Button Component Guidelines

The Button component is highly versatile:
- Supports variants (primary, filled, danger, ghost, subtle)
- Multiple colors and sizes
- Icon support
- Loading states
- Can function as links or input elements through flexible configuration
- Always use appropriate variant based on action priority

## Documentation Reference

- Full component documentation: https://fluxui.dev/components/
- Check component docs before implementing custom solutions
- Use dot notation for nested components (e.g., `flux:table.columns`, `flux:chart.svg`)

=== blaze/core rules ===

# Livewire Blaze

- Livewire Blaze (https://github.com/livewire/blaze) is a performance optimization package for Blade component rendering.
- Use Blaze when the application has many anonymous Blade components (especially from Flux UI) that need faster rendering.
- Blaze offers three optimization strategies:
  1. **Function Compiler** (default) - 91-97% performance improvement with minimal configuration
  2. **Runtime Memoization** - Caches repeated components with identical props (icons, avatars, buttons)
  3. **Compile-Time Folding** - Most aggressive; pre-renders components to static HTML at compile time
- Enable via `@blaze` directive on individual components or through service provider configuration.
- Drop-in replacement - no code changes needed to enable.
- Particularly beneficial for component-heavy UIs like this application's Flux-based pages.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `sail bin pint --test --format agent`, simply run `sail bin pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `pa make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `pa make:test --pest SomeFeatureTest` instead of `pa make:test --pest Feature/SomeFeatureTest`.
- Run tests: `pa test --compact` or filter: `pa test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>

## Coding Standards
When working on this Laravel/PHP project, first read the coding guidelines at @laravel-php-guidelines.md
