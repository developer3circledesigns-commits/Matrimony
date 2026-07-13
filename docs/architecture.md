# Architecture

The Matrimony platform follows a layered, front-controller design with a public web root.

## Layer map

```
        ┌──────────────────────────────────────┐
        │ public/  (web root, document root)   │
        │   index.php   .htaccess   assets/   │
        └──────────────┬───────────────────────┘
                       │
                       ▼
        ┌──────────────────────────────────────┐
        │ includes/bootstrap.php               │
        │   autoloader, env, helpers, session  │
        └──────────────┬───────────────────────┘
                       │
        ┌──────────────┴──────────────┬────────────────────┐
        ▼                             ▼                    ▼
   modules/                      src/                  config/
   feature views +              framework code       env-driven config
   services (users,             (DB, HTTP, etc.)
   profiles, search…)
```

## Request lifecycle

1. Apache serves `public/index.php` (everything else is rewritten via `public/.htaccess`).
2. `public/index.php` requires `includes/bootstrap.php`, which:
   - registers the PSR-4 autoloader for `Matrimony\`,
   - loads `.env` into `$_ENV` / `getenv()`,
   - requires helper files (`env`, `html`, `url`),
   - starts the session,
   - configures error reporting based on `APP_ENV`.
3. The router maps the path to a module controller, which renders a view inside the `main` layout.

## Folder responsibilities

| Folder | Purpose |
|--------|---------|
| `public/`         | Web root. The only folder exposed to the browser. |
| `public/partials/` | Header, footer, and layouts shared by all pages. |
| `includes/`        | Bootstrap, helpers, and shared include files. |
| `src/`             | Framework code (PSR-4 autoloaded as `Matrimony\…`). |
| `config/`          | PHP files returning config arrays. |
| `modules/`         | Feature modules — each owns its views, controllers, services. |
| `api/`             | JSON API endpoints (one PHP file per route). |
| `database/`        | One-shot SQL for fresh setups (schema.sql, init.sql). |
| `migrations/`      | Numbered, sequential ALTER scripts. |
| `storage/`         | Logs, cache, sessions, mail — writable, never served. |
| `uploads/`         | User-uploaded files. Reachable via PHP, not directly. |
| `tests/`           | PHPUnit tests. |
| `docs/`            | Design notes, decisions, runbooks. |
