# Cleanup checkpoint — 2026-09-26

- Git HEAD before cleanup: `ffb68ca` (`Refactor code structure for improved readability and maintainability`).
- `git status --short` before cleanup: clean. Previous implementation is tracked in Git.
- Scope reviewed: 256 tracked application/template/asset files in [cleanup-inventory.csv](cleanup-inventory.csv).
- Classified: 129 `DEMO_UNUSED`, 6 `USED`, 2 `SHARED`, 119 `UNCERTAIN`.
- `DEMO_UNUSED` contains only TailAdmin demo views, auto-discovered classes used by those views, two old controllers, one old menu helper, and unimported demo JS modules.
- Active Besofton views use `admin.layout` or `public.layout`; active includes use `public.partials.card`; active Livewire component is `quiz-player`. No active Blade `<x-...>` reference to old TailAdmin components. No active `view($variable)`, dynamic Blade component, or Vite import points to the candidate files.
- `php artisan route:list --json`: 175 routes before cleanup, zero listed demo URIs, zero admin routes lacking auth other than login.
- `resources/js/app.js` and `vite.config.js` do not import `resources/js/components/*`. `resources/css/app.css` is an active Vite entry and is retained.
- `public/images/*` stays in place except none selected for deletion; content/media/profile paths can reference public images dynamically. `public/images/user/owner.jpg` is explicitly used by User profile fallback.
- No `.env`, uploads, database files, migrations, production assets, or user data are cleanup targets. No database reset is planned.

Rollback of deleted tracked files is available from Git HEAD. Do not run a blanket restore if later fixes need to remain; restore only paths listed as `DEMO_UNUSED` in the inventory.