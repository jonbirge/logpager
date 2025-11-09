# Repository Guidelines

Contributors extend log-pager by wiring new log analyzers into a Docker-first PHP/JS stack. Review these pointers before opening a pull request.

## Project Structure & Module Organization
Core endpoints and UI assets live in `src/`. PHP handlers such as `geo.php` and `blacklist.php` sit alongside the browser bundle (`logpager.js`, `styles.css`, `index.html`). Log-type helpers stay under `src/auth`, `src/clf`, and `src/traefik`, while enrichment code uses `src/intel`. Container glue is under `docker/`, runtime configs under `conf/`, and reproducible fixtures plus docker-compose helpers sit in `test/logs` and `test/stack`.

## Build, Test, and Development Commands
`make build` emits `jonbirge/logpager:latest`; run it whenever PHP or JS changes land. `make dev` builds the lighter `logpager-dev` image, and `make up` (wrapper over `test/stack/up.sh`) launches the local stack with MariaDB and sample logs at `http://localhost:8080`. Shut everything down with `make down`. Use `make clean` before large Dockerfile edits, and fall back to `docker build -t logpager-dev .` for ad-hoc experiments.

## Coding Style & Naming Conventions
Match the existing four-space indentation in PHP and JavaScript; no tabs. Use camelCase for variables/functions (`pollLog`, `getGeoInfo`) and snake_case for SQL identifiers (`cache_time`, `log_type`). There is no auto-formatter, so run `php -l src/<file>.php` and `node --check logpager.js` to catch syntax slips, and write comments only when intent is not obvious.

## Testing Guidelines
Testing is manual. Rebuild (`make dev`) and bring up the stack (`make up`), then exercise heatmap navigation, blacklist writes, and geo lookups against the fixtures in `test/logs`. Watch `docker logs logpager-dev` for PHP notices and confirm cache hits appear before external geo calls. When adding a log adapter, drop representative samples into `test/logs`, document the steps you performed, and include them in the PR.

## Commit & Pull Request Guidelines
Recent commits use short, imperative subjects with optional issue references (`Implement Boolean Search Functionality (#288)`). Keep each commit focused and include schema or config updates alongside dependent code. PRs should outline the change, list validation steps (`make up`, browser smoke tests), link issues, and provide screenshots whenever the UI shifts. Mention any changes to `conf/db.sql` or container entrypoints so reviewers can verify deploy risk.

## Security & Configuration Tips
Do not commit credentials. PHP endpoints read `SQL_HOST`, `SQL_USER`, `SQL_PASS`, and `SQL_DB`, so keep secrets in your runtime environment. Update `conf/db.sql` whenever the schema changes and describe migrations in the PR. Custom web server or PHP-FPM tweaks belong in `conf/default.conf` and `conf/www.conf`.
