# plaan

A Laravel web app that helps theatre tech teams collect and manage the technical
needs of a production.

Performers fill in a guided wizard describing their sound,
scenes, equipment and other requirements; the result is a shareable **technical
plan** the tech crew can review, comment on and prepare against.

Very opinionated and specific to one theatre.

## Features

- **Technical plan wizard** — a step-by-step form for sound, scenes, equipment and
  notes, with file attachments.
- **Shareable plans** — every plan gets a stable token and a public link
  (`/tehnikaplaan/p/{token}`) that can be shared without an account.
- **AI review** — plans can be run through an automated review pass before submission.
- **Teams** — authenticated crew members belong to teams, with invitations and
  per-team dashboards.

## Tech stack

- **Backend:** Laravel 13, PHP 8.3+
- **Frontend:** Inertia v3 + Vue 3, Tailwind CSS v4
- **Auth:** Laravel Fortify
- **Files:** Spatie Media Library
- **Tooling:** Vite, Pint, Larastan, PHPUnit, Laravel Sail

## Local development

Requirements: Docker (for Sail) and Node.js.

```bash
composer install
cp .env.example .env
php artisan key:generate

./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed

npm install
npm run dev
```

The app is then available from http://localhost

## Testing & quality

```bash
composer test          # lint + static analysis + PHPUnit
./vendor/bin/pint      # format PHP
./vendor/bin/sail artisan test --compact
```

## License

MIT License

Copyright (c) 2026 Ando Roots
