# GroupScholar Casework Ledger

A lightweight PHP CLI for logging scholar casework signals (attendance, academic, financial, wellness) with prioritization, follow-up dates, and quick exports. Built for operations teams who need an auditable, queryable ledger for scholar support.

## Features
- Log casework notes with priorities, tags, and follow-up dates.
- List and filter notes by scholar, priority, or time window.
- Resolve completed follow-ups to keep outreach queues clean.
- Generate quick stats snapshots for recent activity.
- Track upcoming or overdue follow-ups for outreach planning.
- See open queue summaries by scholar for workload balancing.
- Export filtered notes to CSV for reporting.

## Setup
1. Ensure PHP 8.2+ is installed.
2. Set environment variables (production DB only):

```bash
export GS_CASEWORK_DSN="pgsql:host=db-acupinir.groupscholar.com;port=23947;dbname=postgres"
export GS_CASEWORK_DB_USER="ralph"
export GS_CASEWORK_DB_PASS="<set-in-vercel-or-shell>"
export GS_CASEWORK_SCHEMA="groupscholar_casework_ledger"
```

3. Initialize schema:

```bash
./bin/gs-casework init
```

4. Load sample casework notes (optional):

```bash
./bin/gs-casework seed
```

## Usage
```bash
./bin/gs-casework add --scholar="Maya Chen" --type="financial" --note="FAFSA verification pending" --priority=medium --tags=fafsa,docs --follow-up=2026-02-15
./bin/gs-casework list --priority=high --since="2026-01-01"
./bin/gs-casework resolve --id=12 --completed="2026-02-05 16:30:00"
./bin/gs-casework stats --days=30
./bin/gs-casework followups --days=21
./bin/gs-casework followups --overdue=true --priority=high
./bin/gs-casework queue --priority=high --since="2026-01-01" --limit=10
./bin/gs-casework export --output=casework.csv --status=open
./bin/gs-casework seed --force=true
```

## Tests
```bash
php tests/run.php
```

## Tech
- PHP 8.x
- PDO (PostgreSQL/SQLite)
