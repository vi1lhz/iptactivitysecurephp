#!/usr/bin/env bash
# Runs the automated checks against a throwaway SQLite copy of the database.
set -euo pipefail
cd "$(dirname "$0")/.."
DB="tests/test.sqlite"; rm -f "$DB"
sqlite3 "$DB" < tests/sqlite_schema.sql 2>/dev/null || php -r '$p=new PDO("sqlite:'"$DB"'");$p->exec(file_get_contents("tests/sqlite_schema.sql"));'
LAB_DB_DSN="sqlite:$PWD/$DB" php -S 127.0.0.1:8001 router.php >/dev/null 2>tests/server.log &
PID=$!
env -u LAB_DB_USER -u LAB_DB_PASSWORD -u LAB_DB_DSN php -S 127.0.0.1:8002 router.php >/dev/null 2>tests/server-nodb.log &
PID2=$!; trap 'kill $PID $PID2' EXIT; sleep 1
python3 tests/run_tests.py http://127.0.0.1:8001 "$DB" http://127.0.0.1:8002
