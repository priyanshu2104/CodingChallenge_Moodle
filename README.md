# User Import Application

A PHP + React application that imports users from a CSV file via both a **Web UI** and a **CLI**.

---

## Overview

The import flow is:

```
Upload → Parse → Validate → Preview → Import
```

The same core PHP services (`CsvParser`, `UserValidator`, `UserImporter`) are shared between the CLI and the HTTP API, so validation behaviour is identical in both interfaces.

---

## Technology Stack

| Layer      | Technology              |
|------------|-------------------------|
| Backend    | PHP 8.3+                |
| Database   | PostgreSQL 16           |
| Frontend   | React 19 + TypeScript (Vite) |
| Testing    | PHPUnit 11              |
| Env config | vlucas/phpdotenv        |
| Dev env    | Docker Compose          |

---

## Project Structure

```
.
├── backend/
│   ├── cli/
│   │   └── user_upload.php      # CLI entry point
│   ├── public/
│   │   └── index.php            # HTTP API entry point
│   ├── src/
│   │   ├── Database/
│   │   │   ├── Connection.php   # PDO singleton
│   │   │   └── Schema.php       # DDL helper (CREATE TABLE)
│   │   ├── Http/
│   │   │   └── ApiController.php
│   │   └── Service/
│   │       ├── CsvParser.php    # Parse CSV → raw rows
│   │       ├── UserValidator.php # Validate + normalise rows
│   │       ├── UserImporter.php  # Insert valid rows into DB
│   │       └── ValidatedUser.php # DTO
│   ├── tests/
│   │   ├── CsvParserTest.php
│   │   └── UserValidatorTest.php
│   ├── .env.example
│   ├── composer.json
│   └── phpunit.xml
├── frontend/
│   └── src/
│       ├── components/
│       │   ├── FileUpload.tsx
│       │   ├── PreviewTable.tsx
│       │   └── ImportResult.tsx
│       ├── App.tsx
│       ├── types.ts
│       └── index.css
├── docker-compose.yml
├── users.csv                    # Sample data (50 rows)
└── README.md
```

---

## Requirements

### Without Docker
- PHP 8.2+ (8.3 recommended) with `pdo` and `pdo_pgsql` extensions
- Composer 2
- PostgreSQL 16+
- Node.js 20+ and npm

### With Docker
- Docker Desktop (or Docker Engine + Compose plugin)

---

## Setup & Installation

### Option A — Docker Compose (recommended)

```bash
# 1. Clone the repository
git clone <repo-url>
cd CodingChallenge_Moodle

# 2. Start PostgreSQL and the PHP dev server
docker compose up -d

# 3. Create the users table
docker compose exec php php cli/user_upload.php --create-table

# 4. Start the React dev server (in a separate terminal)
cd frontend && npm install && npm run dev
```

Open **http://localhost:5173** in your browser.

---

### Option B — Native (no Docker)

#### 1. Configure the database

```bash
cp backend/.env.example backend/.env
# Edit backend/.env with your PostgreSQL credentials
```

#### 2. Install PHP dependencies

```bash
cd backend
composer install
```

#### 3. Create the users table

```bash
php cli/user_upload.php --create-table
```

#### 4. Start the PHP API server

```bash
php -S localhost:8080 -t public
```

#### 5. Start the React dev server (separate terminal)

```bash
cd frontend
npm install
npm run dev
```

Open **http://localhost:5173** in your browser.

---

## Database Configuration

All connection details are read from environment variables (never hard-coded):

| Variable  | Default        | Description           |
|-----------|----------------|-----------------------|
| `DB_HOST` | `127.0.0.1`    | PostgreSQL host       |
| `DB_PORT` | `5432`         | PostgreSQL port       |
| `DB_NAME` | `moodle_users` | Database name         |
| `DB_USER` | `postgres`     | Database user         |
| `DB_PASS` | `secret`       | Database password     |

Copy `backend/.env.example` to `backend/.env` and edit as needed.

---

## Using the Web UI

1. Navigate to **http://localhost:5173**
2. Drag and drop (or click to select) a CSV file
3. Click **Parse & Preview** — see valid/invalid rows highlighted
4. Click **Import N users** to write valid rows to the database
5. View the import summary (inserted / skipped / errors)

---

## Using the CLI

```bash
# Show help
php backend/cli/user_upload.php --help

# Create (or rebuild) the users table
php backend/cli/user_upload.php --create-table

# Dry run — validate only, no database writes
php backend/cli/user_upload.php --file users.csv --dry-run

# Import users from a CSV file
php backend/cli/user_upload.php --file users.csv

# Create table AND import in one step
php backend/cli/user_upload.php --file users.csv --create-table
```

### CLI Options

| Option              | Description                                    |
|---------------------|------------------------------------------------|
| `--file <filename>` | CSV file to process (**required** for import)  |
| `--dry-run`         | Parse and validate without writing to the DB   |
| `--create-table`    | Create or rebuild the `users` table            |
| `--help`            | Display help text                              |

---

## Running Tests

```bash
cd backend
composer test        # or: php vendor/bin/phpunit --testdox
```

Expected output:
```
OK (28 tests, 64 assertions)
```

### Test coverage

| Suite | Tests | What's covered |
|---|---|---|
| `CsvParserTest` | 10 | Valid CSV, Windows line endings, BOM stripping, whitespace trimming, empty rows, case-insensitive header, missing columns, file not found |
| `UserValidatorTest` | 12 | Name/surname capitalisation, email lowercasing, hyphenated names, valid/invalid status, missing fields, batch deduplication, case-insensitive dedup, malformed emails |
| `UserImporterTest` | 6 | Successful insert, invalid rows skipped (no DB call), unique-violation reported as skip, mixed rows, empty input, non-unique DB errors re-thrown |

---

## Validation Rules

| Field | Rule | Behaviour on failure |
|---|---|---|
| `name` | Must be non-empty | Row rejected — `"name is required"` |
| `surname` | Must be non-empty | Row rejected — `"surname is required"` |
| `email` | Must be non-empty | Row rejected — `"email is required"` |
| `email` | Must pass `filter_var(FILTER_VALIDATE_EMAIL)` | Row rejected — `"invalid email address: \"…\""` |
| `email` | Must be unique within the CSV batch | Second occurrence rejected — `"duplicate email: \"…\" (first seen on line N)"` |
| `email` | Must be unique in the database | Graceful skip at import time — reported in summary |

**Normalisation applied before validation:**
- `name` → `ucfirst(strtolower($name))` — handles hyphenated names (e.g. `anne-marie` → `Anne-Marie`)
- `surname` → same rule
- `email` → `strtolower($email)`

---

## Error Handling

The application handles the following error conditions gracefully:

| Condition | CLI behaviour | API behaviour |
|---|---|---|
| CSV file not found | Prints error to stderr, exits 1 | HTTP 422 with JSON error |
| CSV file unreadable | Prints error to stderr, exits 1 | HTTP 422 with JSON error |
| Empty CSV / missing header | Prints error to stderr, exits 1 | HTTP 422 with JSON error |
| Missing required CSV column | Prints error to stderr, exits 1 | HTTP 422 with JSON error |
| Invalid email address | Row marked invalid in preview, not imported | Row marked invalid in preview, not imported |
| Duplicate email (in batch) | Row marked invalid in preview, not imported | Row marked invalid in preview, not imported |
| Duplicate email (in DB) | Reported as skip in import summary | Reported as skip in import summary |
| Database connection failure | Prints error to stderr, exits 1 | HTTP 500 with JSON error |
| Unknown CLI argument | Usage hint printed, exits 1 | — |
| No `--file` argument | Usage hint printed, exits 1 | — |

---

## CSV Format

```csv
name,surname,email
John,Smith,john@example.com
Jane,Doe,jane@example.com
```

The application will:
- **Capitalise** `name` and `surname` (first letter uppercase, rest lowercase)
- **Lowercase** `email`
- **Reject** records with missing fields, invalid email addresses, or duplicate emails (within the batch and against the database)
- **Report** all validation errors clearly per row

---

## Design Decisions

### Shared Core Logic
`CsvParser`, `UserValidator`, and `UserImporter` are plain PHP classes with no framework dependency. They are used identically by both the CLI and the HTTP API, ensuring consistent behaviour.

### Stateless API
`POST /api/upload` returns the full parsed-and-validated preview to the client. The React UI holds this in state and sends the users back on `POST /api/import`. This avoids server-side session management and makes the API straightforward to test.

### Defence-in-Depth on Import
Even though the client sends users that were already validated by `/api/upload`, the `/api/import` endpoint re-validates them server-side before inserting. The client is never trusted.

### No PHP Framework
Pure PHP was chosen to demonstrate fundamentals and keep dependencies minimal, as appropriate for a small, focused application.

### PostgreSQL UNIQUE Constraint
The `email` column has a `UNIQUE` constraint at the database level. Even if a duplicate slips past application-level deduplication (e.g. a race condition), the database will reject it gracefully — the importer catches the `23505` SQLSTATE and reports it as a skip rather than a fatal error.

### Git History
The repository was built incrementally with one commit per logical change, so the git history demonstrates the development process.

---

## Assumptions

- The CSV always has a header row with columns named `name`, `surname`, and `email` (case-insensitive).
- A "duplicate email" within a batch marks the **second and subsequent** occurrences as invalid; the first occurrence is kept.
- The `--create-table` flag **drops and recreates** the table, which is intentional for development convenience.
