# Serbian Blueberry Farms - Harvest Management System

A modern Laravel application for managing harvest data, worker payslips, and harvest records for blueberry farms. Built with Laravel 13, Livewire 4, and Volt single-file components.

## Features

- 🌾 **Harvest Record Management** - Track harvest data with date, weight, and pricing
- 💼 **Payslip Generation** - Generate professional payslips with harvest records
- 📄 **Multi-page Printing** - Support for printing large harvest datasets across multiple pages with repeating headers
- 👥 **Harvester Profiles** - Manage harvester information and earnings
- 📊 **Real-time Dashboard** - Interactive Livewire components for data management
- 🎨 **Modern UI** - Built with Flux UI components and Tailwind CSS
- 📱 **Responsive Design** - Works seamlessly on desktop and mobile

## Tech Stack

- **Backend**: Laravel 13, PHP 8.5
- **Frontend**: Livewire 4, Volt, Flux UI v2, Tailwind CSS v4
- **Database**: SQLite/MySQL (configurable)
- **Testing**: Pest v4
- **Code Quality**: Laravel Pint
- **Containerization**: Laravel Sail (Docker)

## Requirements

- Docker & Docker Compose (for Laravel Sail)
- Or PHP 8.5+, Composer, and Node.js

## Installation

### Using Docker (Recommended)

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/berbasaas.git
   cd berbasaas
   ```

2. **Install dependencies**
   ```bash
   docker run --rm \
     -u "$(id -u):$(id -g)" \
     -v "$(pwd):/var/www/html" \
     -w /var/www/html \
     laravelsail/php85-composer:latest \
     composer install --ignore-platform-reqs
   ```

3. **Copy environment file**
   ```bash
   cp .env.example .env
   ```

4. **Generate app key**
   ```bash
   ./vendor/bin/sail artisan key:generate
   ```

5. **Run migrations**
   ```bash
   ./vendor/bin/sail artisan migrate
   ```

### Without Docker

1. **Clone and install**
   ```bash
   git clone https://github.com/yourusername/berbasaas.git
   cd berbasaas
   composer install
   ```

2. **Setup environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Run migrations**
   ```bash
   php artisan migrate
   ```

## Running the Application

### With Docker (Sail)

```bash
# Start services
./vendor/bin/sail up -d

# Run development server
./vendor/bin/sail npm run dev

# Open in browser
./vendor/bin/sail open
```

### Without Docker

```bash
# Start development server
php artisan serve

# In another terminal, build assets
npm run dev
```

## Development

### Running Tests

```bash
./vendor/bin/sail artisan test
```

### Code Formatting

```bash
./vendor/bin/sail bin pint
```

### Database Commands

```bash
# Run migrations
./vendor/bin/sail artisan migrate

# Seed with sample data
./vendor/bin/sail artisan db:seed

# Rollback
./vendor/bin/sail artisan migrate:rollback
```

### Artisan Commands

```bash
# List available commands
./vendor/bin/sail artisan list

# Create new migration
./vendor/bin/sail artisan make:migration create_table_name

# Create new model
./vendor/bin/sail artisan make:model ModelName
```

## Project Structure

```
├── app/
│   ├── Models/          # Eloquent models
│   ├── Http/            # Controllers, middleware
│   ├── Actions/         # Business logic
│   └── Jobs/            # Queued jobs
├── resources/
│   ├── views/           # Blade templates
│   ├── js/              # JavaScript/Volt components
│   └── css/             # Stylesheets
├── database/
│   ├── migrations/      # Database migrations
│   └── seeders/         # Database seeders
├── routes/              # Route definitions
└── tests/               # Pest tests
```

## Key Features

### Harvest Records
- Track harvest data with automatic grid layout
- Support for 120+ record entries per page
- Dynamic column generation (4 columns × 40 rows)

### Payslip Generation
- Professional payslip design with A4 page format
- Automatic page breaks for large datasets
- Repeating headers on continuation pages
- Print-ready formatting with zero margins

### Livewire Components
- Real-time form validation
- Reactive data updates
- Interactive tables with sorting and filtering

## Configuration

Key configuration files:
- `.env` - Application environment variables
- `config/app.php` - Application settings
- `config/database.php` - Database connection
- `config/cache.php` - Caching configuration

## Contributing

1. Create a feature branch (`git checkout -b feature/amazing-feature`)
2. Commit changes (`git commit -m 'Add amazing feature'`)
3. Push to branch (`git push origin feature/amazing-feature`)
4. Open a Pull Request

## License

This project is private. All rights reserved.

## Support

For issues and questions, please contact the development team.

---

Built with ❤️ using Laravel & Livewire
