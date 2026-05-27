# Demo Seeding — Safe Commands (read before any demo/seeder work)

> **The real working database is `nexus_os`. Demo + seeder-verification work
> belongs in `nexus_os_demo` ONLY.** On 2026-05-19 a
> `migrate:fresh --seed --seeder=DemoDataSeeder` run against `nexus_os`
> wiped real local data. These rules + the in-code guard exist so it cannot
> happen again.

## The `demo` connection

`config/database.php` defines a dedicated **`demo`** connection — identical
to `mysql` but pinned to a separate database:

```
database => env('DB_DEMO_DATABASE', 'nexus_os_demo')
```

`.env` keeps `DB_DATABASE=nexus_os` (the app's real DB) untouched. Optionally
set `DB_DEMO_DATABASE=nexus_os_demo` in `.env` (the default already is that).

## The hard guard

`DemoDataSeeder::protectedDatabaseRefusal()` aborts with a clear error if the
target database is in `DemoDataSeeder::PROTECTED_DATABASES` (`['nexus_os']`).
It runs inside `DemoDataSeeder::run()` and as a pre-flight in `demo:seed` and
`demo:cleanup`. A guard pass GUARANTEES you are not on the real DB.

## Safe commands — use these

| Goal | Command |
|------|---------|
| Seed the demo dataset | `php artisan demo:seed` — auto-targets the `demo` connection |
| Clean demo data | `php artisan demo:cleanup` — auto-targets the `demo` connection |
| Rebuild demo schema + data from scratch | `php artisan migrate:fresh --database=demo --seed --seeder="Database\Seeders\DemoDataSeeder"` |
| Run any one seeder for demo verification | `php artisan db:seed --database=demo --class="Database\Seeders\<Seeder>"` |

## NEVER do this

```
# DROPS the real working DB — the guard fires AFTER migrate:fresh has
# already dropped tables, so it cannot save you here. Just never run it.
php artisan migrate:fresh --seed --seeder="Database\Seeders\DemoDataSeeder"
php artisan db:seed --class="Database\Seeders\DemoDataSeeder"   # no --database
```

`migrate:fresh` is a core Artisan command and drops tables **before** any
seeder guard runs. The `--database=demo` flag is therefore mandatory for any
demo `migrate:fresh`. The guard still hard-stops the plain `db:seed` path and
screams loudly if you forget `--database=demo`.

## Rule of thumb

If a command can drop/rewrite tables and you did not type `--database=demo`
(or use `demo:seed`/`demo:cleanup`), STOP — you are about to hit `nexus_os`.
