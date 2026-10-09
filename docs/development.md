# Developing the magazine checklist plugin

## Layout

`src/` is the root of the installable package, laid out as it will be on a
site:

```
src/
  magazinechecklist.xml                  the manifest; <files folder="plugins/task/magazinechecklist">
  script.php                             install script (a service provider): Joomla 6.1 / PHP 8.3 checks, enables the plugin
  plugins/task/magazinechecklist/
    services/provider.php                the composition root: registers the services, builds the plugin lazily
    forms/sync.xml                       the task's parameters
    language/en-GB/                      plg_task_magazinechecklist(.sys).ini
    sql/                                 the cursor table, and updates/mysql/<version>.sql
    src/
      Extension/MagazineChecklist.php    the task plugin: reads the parameters, runs the sync
      Sync/ChecklistSync.php             one run: read events, replay them, write back, move the cursor
      Sync/ChecklistSyncFactory.php      builds a ChecklistSync from one task's parameters
      Sync/Cursor*.php, SyncSettings.php
      Checklist/ChecklistEditor.php      add and remove "- [ ] #n" lines; pure string work
      Checklist/TitleMatcher.php         does an overview issue's title name the milestone?
      Github/                            the REST API behind GithubGateway and HttpTransport
tests/
  Unit/                                  PHPUnit, no Joomla and no network needed
  Support/                               FakeGithub, MemoryCursorStore, ScriptedTransport
  cypress/                               specs against the development site
build/build.php                          zips src/ into build/plg_task_magazinechecklist-<version>.zip
tools/                                   install-local.php, phpstan-bootstrap.php
```

The namespace is `Yepr\Plugin\Task\MagazineChecklist`. The plugin needs
Joomla 6.1 (the first version with `$container->lazy()`) and PHP 8.3.

## Dependency injection

Services are never built where they are used. `services/provider.php` is the
only place that wires them together, in the child container Joomla gives each
extension:

| Service | Built from |
|---|---|
| `HttpTransport` | `JoomlaHttpTransport` on Joomla's HTTP client |
| `CursorStore` | `DatabaseCursorStore` on the site's `DatabaseInterface` |
| `ChecklistEditor`, `TitleMatcher` | themselves |
| `ChecklistSyncFactory` | the four above |
| `PluginInterface` | `MagazineChecklist`, with the factory, through `$container->lazy()` |

All are shared and built on first use. The plugin is a lazy proxy, so a request
that runs no task builds none of it. The proxy is a PHP 8.4 feature; on PHP 8.3
`lazy()` simply calls the factory, as it does for core's plugins.

The one thing that cannot be built once is the sync itself: which repository,
which token and which labels belong to each task. So the plugin gets
`ChecklistSyncFactory` injected and asks it for a `ChecklistSync` per run. That
factory is the only place a gateway and a sync are made.

Value objects (`Issue`, `IssueEvent`, `EventPage`, `Cursor`, `SyncSettings`,
`HttpResult`) and exceptions are created where they arise; they are data, not
dependencies.

The sync takes GitHub as the `GithubGateway` interface and HTTP as the
`HttpTransport` interface. That is what makes it testable without a network:
`ChecklistSyncTest` runs whole syncs against `FakeGithub`, and
`HttpGithubGatewayTest` checks URLs, headers, paging and errors against
scripted answers.

## The development site

`/joomla` holds Joomla 6.1.4, installed and git-ignored:

- URL: http://localhost/magazine-plan/joomla, login `admin` / `adminadminadmin`
- database `jlatest-magazine-plan` (user `root`, no password), table prefix `jmp_`

To set it up again from scratch:

```bash
curl -sSL -o joomla.tar.gz https://github.com/joomla/joomla-cms/releases/download/6.1.4/Joomla_6.1.4-Stable-Full_Package.tar.gz
mkdir -p joomla && tar -xzf joomla.tar.gz -C joomla && rm joomla.tar.gz
php joomla/installation/joomla.php install -n --site-name="Magazine plan dev" --admin-user=Admin --admin-username=admin --admin-password=adminadminadmin --admin-email=admin@example.test --db-type=mysqli --db-host=localhost --db-user=root --db-pass= --db-name=jlatest-magazine-plan --db-prefix=jmp_
rm -rf joomla/installation
composer install-local
```

## Commands

| Command | What it does |
|---|---|
| `composer test` | PHPUnit |
| `composer analyse` | PHPStan, level 8, against the Joomla in `/joomla` |
| `composer cs` | phpcs: PSR-12, with Joomla's tabs in `src/` |
| `composer cs-fix` | php-cs-fixer on tests, build and tools |
| `composer build` | the package zip in `build/` |
| `composer install-local` | build, then install into `/joomla` through Joomla's CLI |
| `npx cypress run` | the Cypress specs; copy `cypress.env.json.dist` to `cypress.env.json` first |

## Running the task by hand

```bash
php joomla/cli/joomla.php scheduler:list
php joomla/cli/joomla.php scheduler:run --id=<id>
```

The task log is in `joomla/administrator/logs/joomla_scheduler.php`, and on the
task's row in *System → Scheduled Tasks*.

To start a task over from "first run", delete its row in
`jmp_magazinechecklist_cursor`.

## Releasing

Bump `<version>` in `src/magazinechecklist.xml`, add
`sql/updates/mysql/<version>.sql` (a comment is enough when the schema did not
change), commit, and push a tag `v<version>`. The release workflow checks that
tag and manifest agree, runs the checks, builds the package and attaches it to
a GitHub release.
