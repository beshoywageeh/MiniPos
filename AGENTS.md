# AGENTS.md

## Foundational Context
- This is a Laravel application (PHP 8.3+ required; currently PHP 8.5). Always use APIs that match the installed major version of each package.
- Confirm package versions with `composer show --direct` (PHP) or check `package.json` (JavaScript).

## Skills Activation
- Domain‑specific skills live in `**/skills/**`. Activate the relevant skill before working in that domain—do not wait until you’re stuck.
- Skills declared in `boost.json`: `infer-conventions`, `deploying-to-cloud`, `laravel-best-practices`, `testing-best-practices`, `livewire-development`, `tailwindcss-development`.

## Conventions
- Follow existing code conventions; check sibling files for structure, approach, and naming.
- Use descriptive names for variables and methods (e.g., `isRegisteredForDiscounts` not `discount()`).
- Reuse existing components before writing new ones.

## Frontend Bundling
- Use `pnpm run dev` for Vite dev server, `pnpm run build` to build assets.
- If a UI change does not appear, run `pnpm run build` and verify the Vite manifest (`public/vite/manifest.json`).

## Artisan
- Run all Artisan commands with `--no-interaction` to avoid prompts.
- Common commands:
  - `php artisan key:generate`
  - `php artisan migrate --force`
  - `php artisan boost:install`
  - `php artisan boost:update`
- Inspect routes: `php artisan route:list --method=GET --path=api`.
- Read config: `php artisan config:show app.name`, `php artisan config:show database.default`.

## Tinker
- Execute PHP in app context with single quotes: `php artisan tinker --execute 'Your::code();'`.
- Example: `php artisan tinker --execute 'User::where("active", true)->count();'`.

## PHP Rules
- Always use curly braces for control structures, even for single‑line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public SomeClass $dep) {}`.
- Add explicit return‑type declarations and type hints for all method parameters.
- Enums: use TitleCase keys (`FavoritePerson`, `BestLake`, `Monthly`).
- Prefer PHPDoc blocks over inline comments; add inline comments only for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

## Deployments
- Laravel Cloud is the fastest way to deploy and scale (`https://cloud.laravel.com/`).
- Activate the `deploying-to-cloud` skill when deploying, configuring environments/resources, using the Cloud CLI, or troubleshooting.

## Laravel Core
- Use `php artisan make:` commands (make:model, make:controller, make:migration, etc.) to create new files.
- When creating models, also create factories and seeders with `php artisan make:model --factory --seed {Name}`.
- Pass `--no-interaction` to all Artisan commands.

## APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing routes dictate otherwise.

## URL Generation
- Prefer named routes and the `route()` function when generating links.

## Testing (Pest)
- This project uses **Pest**.
- When creating test models, use the model factories; check for custom states first.
- Faker: use `$this->faker->word()` or `fake()->randomDigit()`; follow existing conventions (`$this->faker` vs `fake()`).
- Create feature tests with `php artisan make:test --pest {Name}` or unit tests with `--unit`.
- Most tests should be feature tests.
- Run a narrow suite: `vendor/bin/pest --filter=testName` or `php artisan test --filter=testName`.
- After each change, rerun the relevant test(s).

## Code Style (Pint)
- Before committing PHP changes, run `vendor/bin/pint --dirty --format agent` to ensure style compliance.
- Do **not** run `vendor/bin/pint --test --format agent`; simply run `vendor/bin/pint --format agent` to fix issues.

## Livewire
- Livewire lets you build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client‑side interactions.
- Keep state server‑side so the UI reflects it; validate and authorize in actions as you would in HTTP requests.

## Documentation
- Only create documentation files if explicitly requested by the user.

## Searching Documentation (Boost)
- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version‑specific syntax.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic‑based queries, e.g. `['rate limiting', 'routing rate limiting', 'routing']`.
- Combine words and phrases: `middleware "rate limit"`.
- Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.