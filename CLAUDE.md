# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Logpager is a lightweight security log inspection web interface that provides a heatmap-based dashboard for analyzing threats. It processes logs using UNIX commands within a Docker container, treating the log file as the source of truth with zero resource usage when idle.

## Build and Development Commands

```bash
# Build release image (jonbirge/logpager:latest)
make build

# Build dev image and start local test stack with MariaDB + sample logs
make up                    # Access at http://localhost:8080/logs/

# Shut down test stack
make down

# Build dev image only (lighter, no stack startup)
make dev

# Clean Docker builder cache before major Dockerfile changes
make clean

# Direct Docker build for ad-hoc testing
docker build -t logpager-dev .
```

**Development workflow**: Make changes to `src/`, run `make up`, test in browser at localhost:8080/logs/, check `docker logs logpager-dev` for PHP errors.

## Architecture

### Technology Stack
- **Backend**: PHP 8.4 + Nginx on Alpine Linux 3.22
- **Database**: MariaDB (for geo cache and IP blacklist)
- **Frontend**: Vanilla JavaScript + D3.js v5 for heatmap visualization
- **Container**: Docker-first design, intended for reverse proxy integration (Traefik)

### Core Workflow

Logpager uses a **quasi-OOP plugin pattern** where each log type is a self-contained module:

```
User clicks heatmap tile
  ↓
JavaScript: logtail.php?type=X&page=N
  ↓
Dispatcher routes to X/tail.php
  ↓
getLogFilesFromDirectory('/log/X/') → finds newest 2 log files
  ↓
Executes: cat log.1 log | tac | head -100
  ↓
parseXLogLine() extracts [IP, timestamp, message, status]
  ↓
Returns JSON to frontend → renders table
```

### Log Type Plugin Structure

Each log type (auth, clf, traefik) implements this interface in `src/<type>/`:

```
<type>/
├── <type>parse.php    # parseXLogLine($line, $year) → [ip, timestamp, message, status]
├── tail.php           # tail($page, $linesPerPage) → JSON log entries
├── heatmap.php        # heatmap($searchDict) → aggregated counts by IP/time
├── search.php         # search($searchDict, $doSummary) → filtered results
└── loghead.json       # ["IP", "Timestamp", "Details", "Status"]
```

**Adding a new log type**:
1. Create `src/<newtype>/` with the 5 files above
2. Implement parse function to extract IP, timestamp, message, status
3. Create `/log/<newtype>/` mount point in Docker setup
4. The dispatcher (`logtail.php`, `logsearch.php`, `heatmap.php`) auto-discovers it

### Key Backend Files

| File | Purpose |
|------|---------|
| `logtail.php` | Display paginated logs - routes to `<type>/tail.php` |
| `logsearch.php` | Search with boolean syntax - routes to `<type>/search.php` |
| `heatmap.php` | Time-based aggregation - routes to `<type>/heatmap.php` |
| `manifest.php` | Auto-discover available log types by scanning `/log/` dirs |
| `geo.php` | IP geolocation with database caching (ip-api.com) |
| `blacklist.php` | CIDR-aware IP blacklist management |
| `logfiles.php` | **getLogFilesFromDirectory()** - discovers log files by mtime |
| `searchparse.php` | Parse boolean search queries with field aliases |

### Log Discovery (Directory-Based)

**Recent change**: Replaced hardcoded log paths with directory scanning.

```php
getLogFilesFromDirectory('/log/traefik/', 2, 'access')
// Scans for *.log and *.log.[0-9]+ files
// Returns newest 2 files sorted by modification time
// Example: ['/log/traefik/access.log', '/log/traefik/access.log.1']
```

This allows flexible log rotation without code changes.

### Search Syntax

**Boolean mode** (preferred):
```
ip:192.168.1.0 AND stat:404
ip:10.0.0.0/8 OR ip:172.16.0.0/12
NOT stat:200 AND details:error
```

**Field aliases** (case-insensitive):
- `ip` / `addr` / `address`
- `stat` / `status` / `code`
- `date` / `time` / `when`
- `serv` / `service` / `server`
- `details` / `detail` / `det`

**Implementation**: Each log type implements `evaluateBooleanSearch($searchDict, $data, $line)` to match parsed fields against the query.

### Frontend Architecture

**logpager.js** (44KB bundle):
- `updateTabs()` - Auto-discover log types via manifest.php
- `plotHeatmap()` - D3.js tile rendering with time buckets
- `pollLog()` / `searchLog()` - Fetch and display log data
- `updateTable()` - Render log lines with sortable columns
- `runWatch()` - Live polling mode (10-second intervals)

**Batch operations**:
- POST to `geo.php` for batch IP geolocation lookup
- POST to `blacklist.php` for batch blacklist checks
- Results cached in `geoCache` object for UI enrichment

### Docker Architecture

**Container design**:
- Alpine 3.22 base with PHP 8.4-FPM + Nginx
- Network tools: whois, nmap, tcptraceroute (setuid binaries)
- Entry point: `docker/entry.sh` initializes DB schema, starts php-fpm + nginx

**Environment variables**:
```bash
SQL_HOST=localhost    # MariaDB host
SQL_USER=root         # Database user
SQL_PASS=""          # Database password
SQL_DB=logpager      # Database name
```

**Volume mounts** (production setup):
```
/log/auth/*.log       # sshd, login logs
/log/clf/*.log        # Apache/Nginx common log format
/log/traefik/*.log    # Traefik reverse proxy logs
```

**Test stack** (`test/test-stack/`):
- docker-compose.yml with Traefik + logpager-dev + MariaDB
- Mounts `test/test-logs/` into `/log/`
- Accessible at http://localhost:8080/logs/

### Performance Patterns

1. **UNIX tool leverage**: Uses `cat`, `tac`, `grep` via `popen()` for streaming I/O
2. **Heatmap caching**: 15-minute TTL at `/tmp/<type>_heatmap_cache.json`
3. **Lazy geolocation**: Only looks up IPs appearing in current view
4. **Pagination**: Limits to 100 lines per request
5. **Directory scanning**: Processes only newest 2 log files (configurable via MAX_LOG_FILES)

### Database Schema

```sql
geo_cache (
  ip VARCHAR(64) PRIMARY KEY,
  cache_time TIMESTAMP,
  json_data JSON
)

ip_blacklist (
  cidr VARCHAR(64) PRIMARY KEY,
  add_time TIMESTAMP,
  last_seen TIMESTAMP,
  log_type VARCHAR(32),
  log_line TEXT
)
```

Schema updates go in `conf/db.sql` and auto-apply via `docker/db-init.php` on container startup.

## Testing

**Manual testing workflow**:
1. Drop sample logs into `test/test-logs/<type>/`
2. Run `make up` to start local stack
3. Navigate to http://localhost:8080/logs/
4. Exercise: heatmap clicks, search filters, blacklist operations
5. Monitor `docker logs logpager-dev` for PHP errors
6. Verify geo cache hits before external API calls

**Syntax validation**:
```bash
php -l src/<file>.php         # PHP lint
node --check logpager.js      # JavaScript syntax check
```

## Code Conventions

- **Indentation**: 4 spaces (no tabs) for PHP and JavaScript
- **Naming**: camelCase for functions/variables, snake_case for SQL identifiers
- **Comments**: Only when intent is not obvious
- **Commit style**: Imperative subjects with optional issue refs (e.g., "Fix Traefik log ordering issue (#292)")

## Configuration Files

| File | Purpose |
|------|---------|
| `conf/www.conf` | PHP-FPM pool settings |
| `conf/default.conf` | Nginx virtual host config |
| `conf/db.sql` | MariaDB schema (auto-applied on startup) |

Changes to web server or PHP-FPM settings go in these files, not the Dockerfile.

## Security Notes

- **No authentication**: Designed for reverse proxy + VPN environments
- **No SSL**: Handled by reverse proxy (Traefik)
- **Shell safety**: Uses `escapeshellarg()` for all user input to shell commands
- **SQL safety**: Prepared statements in blacklist queries
- **Environment secrets**: Never commit SQL_PASS or other credentials
