#!/usr/bin/env bash
#
# sync-qa1-from-staging.sh — Daily Staging → QA1 refresh (code AND database).
#
# WHY THIS EXISTS
#   2026-09-04: QA1's CODE was reset to match Staging, but its DATABASE was
#   never brought across too. QA1 kept running on days-old, drifted data
#   without anyone noticing until Johan couldn't test rental documents days
#   later. Doing code OR database — never both, together, on a schedule — was
#   the whole failure. This script does both, every time, or neither.
#
# WHAT IT DOES (in order — see PHASE comments below for the why of each)
#   0. Preconditions — refuses to run anywhere but /corex-qa1 on branch QA1,
#      refuses if disk is too tight to safely take a backup.
#   1. BACKS UP QA1's current database first, to /root/db-backups/, verifies
#      the backup is valid (not just "the command exited 0"), prunes old ones.
#   2. Merges origin/Staging into QA1's branch (git merge, not a hard reset —
#      a reset would destroy same-day lane work not yet promoted to Staging).
#      A real conflict aborts the merge and fails the run loudly; QA1's code
#      is left exactly as it was, untouched.
#   3. Dumps Staging's database — READ-ONLY, using a dedicated MySQL user
#      that structurally CANNOT write to Staging (see qa1_staging_reader
#      below) — a hard failure at the DB layer if a bug ever tried to write,
#      not something that relies on this script being written carefully.
#   4. Quiesces QA1 (maintenance + worker stop), loads the Staging dump,
#      migrates forward (QA1's code is normally ahead of Staging's schema —
#      that's what broke rental documents on 09-04), re-syncs permissions
#      (missed twice today and made a whole feature invisible), restores
#      what must survive a restore (see PRESERVE_TABLES below), clears
#      caches, reloads php-fpm, restarts the queue worker, fixes ownership.
#   5. PROOF GATE — a real HTTP 200 from the live site, migrations present,
#      permissions present, sessions present. Resumes ONLY if every check
#      passes; a failed gate leaves QA1 in maintenance ON PURPOSE — a
#      half-synced box must never quietly serve like nothing happened.
#   6. Logs the run (success or failure) so "did it run last night" is a
#      one-line check, not a log-spelunking exercise.
#
# STAGING IS READ-ONLY — ENFORCED, NOT ASSUMED
#   Every Staging interaction in this script is either `mysqldump` (a client
#   read operation) run as `qa1_staging_reader`, a MySQL user that holds
#   ONLY SELECT/LOCK TABLES/SHOW VIEW on hfc_staging.* and nothing else
#   (verified: an UPDATE from this user is denied by MySQL itself, not by
#   this script's discipline) — or `git fetch`/reading a remote-tracking ref,
#   which never touches /corex-staging's working tree and never pushes
#   anywhere. PHASE 0 and PHASE 9 additionally fingerprint Staging's git HEAD
#   and a few key Staging tables before and after the whole run; ANY
#   difference is treated as a hard failure even though, by construction,
#   nothing in this script can produce one — a tripwire in case something
#   outside this script touches Staging mid-run, not a substitute for the
#   grant-level guarantee above.
#
# WHAT SURVIVES THE RESTORE
#   - sessions — preserved (dumped from QA1 before the load, re-inserted
#     after) so nobody sitting in the app gets logged out mid-sync.
#   - Everything else in `users` — NOT preserved. Staging's users table wins,
#     exactly like the existing live→qa1 sync (scripts/qa1/sync-from-live.sh)
#     already does for live's users. This is what broke Johan's QA1 login on
#     09-04 (Staging's users table replaced QA1's, invalidating whatever
#     QA1-only account/password he was using) — but it did NOT need to lock
#     him out, because his SESSION should have survived (it does now, with
#     PRESERVE_TABLES below). The honest answer for the case a session ever
#     does expire: log into QA1 with your STAGING credentials from that
#     point on. Say this once, not rediscover it monthly.
#   - No other QA1-only tuned settings are preserved here. If one is found
#     (mirroring THE CLASS RULE in scripts/qa1/README.md), add its table to
#     PRESERVE_TABLES — do not silently lose it a second time.
#
# THE LOOSE, UNTRACKED WEB-TEMPLATE FILES (2026-09-07)
#   `resources/views/docuperfect/web-templates/cds/template-*.blade.php` were
#   copied onto QA1 today as loose, untracked files (not committed). A `git
#   merge` NEVER touches untracked files — they are outside git's view
#   entirely — so PHASE 2 cannot delete them. The one scenario where they
#   are at risk: if Staging's own history later adds TRACKED files at those
#   SAME paths, git will refuse the merge outright ("untracked working tree
#   files would be overwritten by merge") rather than silently overwriting
#   or deleting them — the run fails loudly, exactly as required, and the
#   loose files are still sitting there afterward. If that happens: commit
#   the loose files properly (or move them aside) before the next run: this
#   script will not decide that for you.
#
# USAGE
#   sudo scripts/sync-qa1-from-staging.sh --dry-run     # guards + plan only, no changes
#   sudo scripts/sync-qa1-from-staging.sh --confirm     # real run
#
# CRON — NOT ARMED. Arming a nightly destructive job is Johan's decision, not
#   this script's. Line to add to root's crontab once he says go (root
#   crontab, not /etc/cron.d — matches how the box's other ad-hoc jobs
#   already live in `crontab -e`, not a dedicated file):
#
#     # Staging -> QA1 daily sync (code + database). NOT ARMED until Johan says go.
#     0 1 * * *  /corex-qa1/scripts/sync-qa1-from-staging.sh --confirm >> /var/log/qa1-staging-sync.log 2>&1
#
#   CHOSEN TIME: 01:00 SAST, daily. Why:
#     - This box's existing cron neighbours: node cleanup-temp.js at 02:00,
#       and the off-box restic backup (/etc/cron.d/corex-offbox-backup) at
#       03:30. 01:00 sits a full hour before the first and two and a half
#       hours before the second — comfortable separation on both sides so a
#       slow run (DB dump/load + a possible composer/npm build) doesn't
#       collide with either, and the 03:30 off-box backup always captures a
#       QA1 that's been settled and idle for hours, not one mid-sync.
#     - Late enough that a normal day's Staging promotions (which, per
#       today, can land into the evening) have settled; early enough that
#       QA1 is fully resynced and idle long before anyone's morning start.
#     - Daily, not weekly like the live→qa1 sync — Staging moves far more
#       often than live does, and today's whole incident was exactly QA1
#       drifting from Staging for DAYS before anyone noticed. Daily is what
#       actually satisfies "I don't want to get to the scenario where qa1
#       diverges from staging again."
#
set -euo pipefail

# ── fixed topology (this host) ────────────────────────────────────────────
QA1_PATH="/corex-qa1"
QA1_DB="corex_qa1"
QA1_BRANCH="QA1"
STAGING_DB="hfc_staging"
STAGING_PATH="/corex-staging"
STAGING_REMOTE_REF="origin/Staging"
QA1_STORAGE="/corex-qa1/storage"
QA1_WORKER="corex-qa1-queue.service"
FPM="php8.2-fpm"
QA1_URL="https://qatesting1.corexos.co.za"
BACKUP_DIR="/root/db-backups"
BACKUP_RETENTION_DAYS=5
WORKDIR="/var/tmp/qa1-staging-sync"           # transient files — data volume via /var/tmp, never /root
LOG_FILE="/var/log/qa1-staging-sync.log"
LAST_SUCCESS_FILE="/var/lib/qa1-staging-sync/last-success"
STAGING_READER_ENV="/etc/corex-qa1-sync/staging-reader.env"
ARTISAN=(sudo -u www-data env HOME=/tmp XDG_CONFIG_HOME=/tmp php "$QA1_PATH/artisan")
STAMP="$(date +%Y%m%d-%H%M%S)"

MODE="${1:-}"
[ "$MODE" = "--confirm" ] || [ "$MODE" = "--dry-run" ] || {
  echo "usage: $0 --confirm | --dry-run" >&2; exit 2; }
DRY=false; [ "$MODE" = "--dry-run" ] && DRY=true

log()  { printf '\n\033[1;36m[%s]\033[0m %s\n' "$(date +%H:%M:%S)" "$*" | tee -a "$LOG_FILE"; }
ok()   { printf '   \033[1;32m✓\033[0m %s\n' "$*" | tee -a "$LOG_FILE"; }
warn() { printf '   \033[1;33m! %s\033[0m\n' "$*" | tee -a "$LOG_FILE"; }
die()  { printf '\n\033[1;31m✗ ABORT:\033[0m %s\n' "$*" | tee -a "$LOG_FILE" >&2; exit 1; }

MAINTENANCE_ON=false
STAGING_BEFORE_SHA=""
STAGING_BEFORE_FP=""

on_error() {
  local exit_code=$1 line=$2
  printf '\n\033[1;31m════════════════════════════════════════════════════════════\033[0m\n' | tee -a "$LOG_FILE"
  log "❌ SYNC FAILED at line $line (exit $exit_code)"
  if $MAINTENANCE_ON; then
    log "   QA1 is LEFT IN MAINTENANCE ON PURPOSE — a half-synced box must never serve quietly."
    log "   To restore service on the OLD data while you investigate:"
    log "     mysql -e \"DROP DATABASE $QA1_DB; CREATE DATABASE $QA1_DB;\""
    log "     gunzip -c <latest file in $BACKUP_DIR> | mysql $QA1_DB"
    log "     ${ARTISAN[*]} up"
  fi
  echo "$(date -Iseconds) FAILED (line $line, exit $exit_code)" >> "$LOG_FILE"
  exit "$exit_code"
}
trap 'on_error $? $LINENO' ERR

mkdir -p "$WORKDIR" "$(dirname "$LAST_SUCCESS_FILE")"
touch "$LOG_FILE"
log "════════ Staging → QA1 sync — run $STAMP (mode: $MODE) ════════"

# =============================================================================
# PHASE 0 — PRECONDITIONS (any failure aborts before a single byte changes)
# =============================================================================
log "PHASE 0 — preconditions"
[ "$(id -u)" -eq 0 ] || die "must run as root (mysql socket auth + systemctl + storage chown)"

[ -f "$STAGING_READER_ENV" ] || die "read-only Staging credential missing: $STAGING_READER_ENV — see script header"
# shellcheck source=/dev/null
source "$STAGING_READER_ENV"
[ -n "${STAGING_READER_USER:-}" ] && [ -n "${STAGING_READER_PASSWORD:-}" ] || die "STAGING_READER_USER/PASSWORD not set in $STAGING_READER_ENV"
STAGING_MYSQL=(mysql -u "$STAGING_READER_USER" -p"$STAGING_READER_PASSWORD" -N)
STAGING_MYSQLDUMP=(mysqldump -u "$STAGING_READER_USER" -p"$STAGING_READER_PASSWORD")

# confirm the reader truly cannot write — a live, structural proof, not a memory of having set it up once
if "${STAGING_MYSQL[@]}" -e "UPDATE $STAGING_DB.users SET name=name LIMIT 0;" 2>/dev/null; then
  die "qa1_staging_reader can write to Staging — grants have drifted. FIX THE GRANTS before running this again."
fi
ok "Staging reader user is confirmed read-only (a no-op UPDATE was denied by MySQL itself)"

# target is qa1, not something else
QA1_ENV=$(grep -E '^APP_ENV=' "$QA1_PATH/.env" | head -1 | cut -d= -f2 | tr -d "\"'")
[ "$QA1_ENV" = "qa" ] || die "target APP_ENV is '$QA1_ENV', expected 'qa' — refusing"
ok "target env is qa ($QA1_PATH)"

CURRENT_BRANCH="$(git -C "$QA1_PATH" rev-parse --abbrev-ref HEAD)"
[ "$CURRENT_BRANCH" = "$QA1_BRANCH" ] || die "the shared checkout is on branch '$CURRENT_BRANCH', not '$QA1_BRANCH' — refusing to sync a lane's WIP branch. Switch to $QA1_BRANCH first."
ok "checkout is on branch $QA1_BRANCH"

# Staging fingerprint BEFORE anything — compared again in PHASE 9 as a tripwire.
# This script never runs a write against /corex-staging or hfc_staging, so this
# should always be identical at the end; if it ever isn't, something OUTSIDE
# this script touched Staging mid-run and that is a hard failure, not a note.
STAGING_BEFORE_SHA="$(git -C "$STAGING_PATH" rev-parse HEAD 2>/dev/null || echo 'unknown')"
STAGING_BEFORE_FP="$("${STAGING_MYSQL[@]}" -e "SELECT COUNT(*) FROM $STAGING_DB.users; SELECT MAX(updated_at) FROM $STAGING_DB.users;" 2>/dev/null | tr '\n' '|' || echo 'unknown')"
ok "Staging fingerprint captured (git HEAD $STAGING_BEFORE_SHA) — re-checked at the end untouched"

# disk headroom — / is where /root/db-backups lives and is often the tightest
# filesystem on this box; refuse rather than risk the exact incident the
# box's own disk-hygiene rule exists to prevent.
QA1_DB_MB=$(mysql -N -e "SELECT ROUND(SUM(data_length+index_length)/1024/1024) FROM information_schema.tables WHERE table_schema='$QA1_DB';")
FREE_MB=$(df --output=avail -m / | tail -1 | tr -d ' ')
NEED_MB=$(( QA1_DB_MB / 3 + 200 ))   # gzip'd dump is usually well under 1/3 raw size; +200MB safety margin
if [ "$FREE_MB" -lt "$NEED_MB" ]; then
  die "only ${FREE_MB}MB free on / — need at least ~${NEED_MB}MB for a safe backup. NOT proceeding (this is the exact class of incident the box's disk-hygiene rule exists to prevent). Free up space or move $BACKUP_DIR before re-running."
fi
ok "disk headroom OK: ${FREE_MB}MB free on / (need ~${NEED_MB}MB)"

if $DRY; then
  log "DRY-RUN — all guards passed. No changes made. Would:"
  echo "  1. Back up $QA1_DB → $BACKUP_DIR/qa1-full-${STAMP}.sql.gz, verify, prune >${BACKUP_RETENTION_DAYS}d"
  echo "  2. git -C $QA1_PATH fetch origin Staging && git merge $STAGING_REMOTE_REF (abort+fail loudly on conflict)"
  echo "  3. Dump $STAGING_DB read-only via qa1_staging_reader"
  echo "  4. Quiesce QA1, drop+load $QA1_DB, migrate --force, corex:sync-permissions --merge-defaults,"
  echo "     restore preserved sessions, clear caches, chown www-data, reload $FPM, restart $QA1_WORKER"
  echo "  5. Proof gate (real HTTP 200 + migrations + permissions + sessions present) → resume or stay down"
  echo "  6. Log the result to $LOG_FILE / $LAST_SUCCESS_FILE"
  log "DRY-RUN complete."
  exit 0
fi

# =============================================================================
# PHASE 1 — BACK UP QA1 FIRST (before ANYTHING else touches it)
# =============================================================================
log "PHASE 1 — back up $QA1_DB to $BACKUP_DIR"
mkdir -p "$BACKUP_DIR"
BACKUP_FILE="$BACKUP_DIR/qa1-full-${STAMP}.sql.gz"
mysqldump --single-transaction --quick --no-tablespaces --routines --triggers --events \
  --set-gtid-purged=OFF "$QA1_DB" | gzip > "$BACKUP_FILE"

# verify — not just "the command exited 0"
gzip -t "$BACKUP_FILE" || die "backup $BACKUP_FILE failed gzip integrity check — NOT proceeding with a sync that has no valid backup"
BACKUP_SZ_BYTES=$(stat -c%s "$BACKUP_FILE")
[ "$BACKUP_SZ_BYTES" -gt 10240 ] || die "backup $BACKUP_FILE is suspiciously small (${BACKUP_SZ_BYTES} bytes) — NOT proceeding"
zgrep -q "Dump completed on" "$BACKUP_FILE" || die "backup $BACKUP_FILE has no 'Dump completed on' trailer — looks truncated, NOT proceeding"
ok "backup verified: $BACKUP_FILE ($(du -h "$BACKUP_FILE" | cut -f1)), gzip-valid, has completion trailer"

log "PHASE 1b — prune backups older than ${BACKUP_RETENTION_DAYS} days"
PRUNED=$(find "$BACKUP_DIR" -maxdepth 1 -name 'qa1-full-*.sql.gz' -mtime "+${BACKUP_RETENTION_DAYS}" -print)
if [ -n "$PRUNED" ]; then
  echo "$PRUNED" | while read -r f; do rm -f -- "$f"; ok "pruned old backup: $f"; done
else
  ok "nothing older than ${BACKUP_RETENTION_DAYS} days to prune"
fi
REMAINING=$(find "$BACKUP_DIR" -maxdepth 1 -name 'qa1-full-*.sql.gz' | wc -l)
ok "backups on hand: $REMAINING"

# =============================================================================
# PHASE 2 — CODE: merge Staging into QA1 (never a hard reset — see header)
# =============================================================================
log "PHASE 2 — sync code: merge $STAGING_REMOTE_REF into $QA1_BRANCH"
OLDHEAD="$(git -C "$QA1_PATH" rev-parse HEAD)"
sudo -u www-data git -C "$QA1_PATH" fetch origin Staging 2>&1 | tee -a "$LOG_FILE" | tail -3

if ! sudo -u www-data git -C "$QA1_PATH" merge --no-edit -m "chore: nightly sync from Staging (automated, ${STAMP})" "$STAGING_REMOTE_REF" 2>&1 | tee -a "$LOG_FILE"; then
  sudo -u www-data git -C "$QA1_PATH" merge --abort 2>/dev/null || true
  die "git merge of $STAGING_REMOTE_REF into $QA1_BRANCH FAILED (conflict, or an untracked file collides with an incoming tracked one — see the loose web-template files note in this script's header). Merge aborted, QA1 code left exactly as it was at $OLDHEAD. A human needs to resolve this — DB was NOT touched."
fi
NEWHEAD="$(git -C "$QA1_PATH" rev-parse HEAD)"
ok "code sync: $OLDHEAD → $NEWHEAD"

if [ "$OLDHEAD" != "$NEWHEAD" ] && git -C "$QA1_PATH" diff --name-only "$OLDHEAD" "$NEWHEAD" | grep -qE '^composer\.lock'; then
  log "   composer.lock changed → composer install"
  sudo -u www-data composer install --no-dev --no-interaction --prefer-dist --working-dir="$QA1_PATH" 2>&1 | tee -a "$LOG_FILE" | tail -5
else
  ok "composer.lock unchanged — skip composer install"
fi
if [ "$OLDHEAD" != "$NEWHEAD" ] && git -C "$QA1_PATH" diff --name-only "$OLDHEAD" "$NEWHEAD" \
     | grep -qE '^(resources/js/|resources/css/|vite\.config|package(-lock)?\.json|tailwind\.config)'; then
  log "   frontend changed → npm ci && npm run build"
  sudo -u www-data bash -c "cd '$QA1_PATH' && npm ci && npm run build" 2>&1 | tee -a "$LOG_FILE" | tail -8
else
  ok "no frontend changes — skip npm build"
fi

# =============================================================================
# PHASE 3 — DUMP STAGING (read-only; see header for the enforcement model)
# =============================================================================
log "PHASE 3 — dump Staging $STAGING_DB (read-only via qa1_staging_reader)"
STAGING_DUMP="$WORKDIR/staging-${STAMP}.sql.gz"
"${STAGING_MYSQLDUMP[@]}" --single-transaction --quick --no-tablespaces --routines --triggers --events \
  --set-gtid-purged=OFF "$STAGING_DB" | gzip > "$STAGING_DUMP"
gzip -t "$STAGING_DUMP" || die "Staging dump $STAGING_DUMP failed gzip integrity check"
zgrep -q "Dump completed on" "$STAGING_DUMP" || die "Staging dump $STAGING_DUMP has no completion trailer — looks truncated"
ok "Staging dumped and verified: $STAGING_DUMP ($(du -h "$STAGING_DUMP" | cut -f1))"

# =============================================================================
# PHASE 4 — QUIESCE QA1 — downtime begins
# =============================================================================
log "PHASE 4 — quiesce QA1 (maintenance ON, worker STOP)"
"${ARTISAN[@]}" down --retry=60 --secret="qa1-staging-sync-${STAMP}" >/dev/null 2>&1 || "${ARTISAN[@]}" down >/dev/null 2>&1 || true
MAINTENANCE_ON=true
ok "QA1 in maintenance"
systemctl stop "$QA1_WORKER"
ok "QA1 worker stopped ($QA1_WORKER)"

# preserve what must survive (see header) — snapshot NOW, QA1 still holds it
PRESERVE_TABLES="sessions"
PRESERVE_FILE="$WORKDIR/qa1-preserve-${STAMP}.sql.gz"
SESS_PRE=$(mysql -N -e "SELECT COUNT(*) FROM $QA1_DB.sessions;" 2>/dev/null || echo 0)
mysqldump --no-tablespaces --add-drop-table "$QA1_DB" $PRESERVE_TABLES | gzip > "$PRESERVE_FILE"
ok "preserved: sessions=$SESS_PRE → $PRESERVE_FILE"

# =============================================================================
# PHASE 4b — clean slate (mirrors scripts/qa1/sync-from-live.sh's proven fix:
# mysqldump only DROPs tables IT contains, so a QA1-only table not yet on
# Staging survives the load and migrate then tries to CREATE it → 1050 →
# abort with QA1 left down mid-load. Drop every QA1 table first.)
# =============================================================================
log "PHASE 4b — drop all $QA1_DB tables (clean slate for a pure Staging load)"
mysql -N -e "SELECT CONCAT('DROP TABLE IF EXISTS \`', table_name, '\`;') FROM information_schema.tables WHERE table_schema='$QA1_DB';" \
  | mysql --init-command="SET FOREIGN_KEY_CHECKS=0" "$QA1_DB"
ok "dropped all $QA1_DB tables (Staging untouched — this DROP is scoped to information_schema.tables WHERE table_schema='$QA1_DB' only)"

# =============================================================================
# PHASE 5 — LOAD Staging into QA1 (destructive to QA1 ONLY)
# =============================================================================
log "PHASE 5 — load Staging snapshot into $QA1_DB"
gunzip -c "$STAGING_DUMP" | mysql "$QA1_DB"
ok "loaded Staging snapshot into $QA1_DB"

# =============================================================================
# PHASE 6 — MIGRATE FORWARD, SYNC PERMISSIONS (the two steps missed today)
# =============================================================================
log "PHASE 6 — migrate QA1 forward (QA1's code is normally ahead of Staging's schema)"
MIG_BEFORE=$(mysql -N -e "SELECT COUNT(*) FROM $QA1_DB.migrations;")
"${ARTISAN[@]}" migrate --force 2>&1 | tee -a "$LOG_FILE" | tail -10
MIG_AFTER=$(mysql -N -e "SELECT COUNT(*) FROM $QA1_DB.migrations;")
ok "migrated: $MIG_BEFORE → $MIG_AFTER migrations (+$((MIG_AFTER - MIG_BEFORE)))"

log "PHASE 6b — sync permissions (missed twice today, made a whole feature invisible)"
"${ARTISAN[@]}" corex:sync-permissions --merge-defaults 2>&1 | tee -a "$LOG_FILE" | tail -10
ok "permissions synced (--merge-defaults: adds missing defaults, never overwrites tuned roles)"

log "PHASE 6c — restore preserved sessions"
gunzip -c "$PRESERVE_FILE" | mysql "$QA1_DB"
SESS_POST=$(mysql -N -e "SELECT COUNT(*) FROM $QA1_DB.sessions;" 2>/dev/null || echo 0)
ok "sessions restored: $SESS_PRE → $SESS_POST"

log "PHASE 6d — clear caches"
"${ARTISAN[@]}" config:clear >/dev/null; "${ARTISAN[@]}" route:clear >/dev/null
"${ARTISAN[@]}" view:clear  >/dev/null; "${ARTISAN[@]}" cache:clear >/dev/null
ok "caches cleared (config/route/view/cache)"

log "PHASE 6e — fix ownership (root-owned storage has broken this box before)"
chown -R www-data:www-data "$QA1_PATH"
ok "$QA1_PATH chowned to www-data:www-data"

log "PHASE 6f — reload $FPM, restart worker"
systemctl reload "$FPM"
ok "$FPM reloaded"
systemctl restart "$QA1_WORKER"
ok "$QA1_WORKER restarted"

# =============================================================================
# PHASE 7 — PROOF GATE (must fully pass or QA1 stays DOWN)
# =============================================================================
log "PHASE 7 — PROOF GATE"
GATE_OK=true
gate() { if eval "$2"; then ok "GATE PASS — $1"; else printf '   \033[1;31m✗ GATE FAIL — %s\033[0m\n' "$1" | tee -a "$LOG_FILE"; GATE_OK=false; fi; }

gate "migrations table non-empty" "[ \"\$(mysql -N -e 'SELECT COUNT(*) FROM $QA1_DB.migrations;')\" -gt 0 ]"
gate "permission definitions present (nexus_permissions)" "[ \"\$(mysql -N -e 'SELECT COUNT(*) FROM $QA1_DB.nexus_permissions;' 2>/dev/null || echo 0)\" -gt 0 ]"
gate "role permission mappings present (role_permissions — today's actual incident)" "[ \"\$(mysql -N -e 'SELECT COUNT(*) FROM $QA1_DB.role_permissions;' 2>/dev/null || echo 0)\" -gt 0 ]"
gate "sessions preserved (>= pre-sync count)" "[ \"\$(mysql -N -e 'SELECT COUNT(*) FROM $QA1_DB.sessions;')\" -ge $SESS_PRE ]"
gate "users table non-empty (login is possible)" "[ \"\$(mysql -N -e 'SELECT COUNT(*) FROM $QA1_DB.users;')\" -gt 0 ]"

if ! $GATE_OK; then
  log "RESULT: GATE FAILED — QA1 left in maintenance, worker left stopped. No resume."
  echo "$(date -Iseconds) FAILED (gate)" >> "$LOG_FILE"
  die "PROOF GATE FAILED — see above. QA1 intentionally left DOWN."
fi
ok "ALL GATES PASSED"

# ── resume — ONLY reached on full gate pass ─────────────────────────────
"${ARTISAN[@]}" up >/dev/null 2>&1
MAINTENANCE_ON=false
ok "QA1 maintenance lifted"

log "PHASE 7b — real HTTP check (not a blade/cache artifact — an actual response)"
HTTP_CODE=$(curl -sk -o /dev/null -w '%{http_code}' --max-time 15 "$QA1_URL" || echo "000")
if [ "$HTTP_CODE" != "200" ]; then
  "${ARTISAN[@]}" down --retry=60 --secret="qa1-staging-sync-${STAMP}-posthttp" >/dev/null 2>&1 || true
  MAINTENANCE_ON=true
  die "site returned HTTP $HTTP_CODE (not 200) after resume — QA1 put back into maintenance. Investigate before Johan sees it."
fi
ok "site returns real HTTP 200 ($QA1_URL)"

# =============================================================================
# PHASE 9 — STAGING TRIPWIRE (must be unchanged — see header)
# =============================================================================
log "PHASE 9 — confirm Staging genuinely untouched"
STAGING_AFTER_SHA="$(git -C "$STAGING_PATH" rev-parse HEAD 2>/dev/null || echo 'unknown')"
STAGING_AFTER_FP="$("${STAGING_MYSQL[@]}" -e "SELECT COUNT(*) FROM $STAGING_DB.users; SELECT MAX(updated_at) FROM $STAGING_DB.users;" 2>/dev/null | tr '\n' '|' || echo 'unknown')"
[ "$STAGING_BEFORE_SHA" = "$STAGING_AFTER_SHA" ] || die "STAGING GIT HEAD CHANGED during this run ($STAGING_BEFORE_SHA → $STAGING_AFTER_SHA) — something outside this script wrote to Staging. Investigate immediately."
[ "$STAGING_BEFORE_FP" = "$STAGING_AFTER_FP" ] || die "STAGING users TABLE FINGERPRINT CHANGED during this run — something wrote to Staging. Investigate immediately."
ok "Staging fingerprint unchanged (git HEAD + users table) — confirmed read-only throughout"

# =============================================================================
# PHASE 10 — LOG SUCCESS
# =============================================================================
echo "$(date -Iseconds) SUCCESS  code:$OLDHEAD->$NEWHEAD  db:staging->qa1  backup:$BACKUP_FILE" >> "$LOG_FILE"
echo "$(date -Iseconds)" > "$LAST_SUCCESS_FILE"
log "════════ DONE — Staging → QA1 sync succeeded ($STAMP) ════════"
