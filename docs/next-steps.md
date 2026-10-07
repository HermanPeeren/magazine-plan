# Where this stands, and how to continue

Status on 2026-10-07: **version 0.1.0, complete enough to try against a real
repository.** What's left needs the fork of the magazine repository and a
token, which aren't there yet.

## Done

- The task plugin `plg_task_magazinechecklist` with the routine
  **Magazine: sync checklists**. Owner, repository, token, labels, heading,
  maximum pages and dry run are all task parameters. Dry run is on by default.
- The logic of `update-issue-plan.yml`, ported and improved:
  - `#12` no longer counts as present because `#123` is there, or because
    `#12` is mentioned in running text;
  - removing an entry no longer collapses every blank line in the body;
  - CRLF bodies (GitHub's editor) keep their line endings;
  - a milestone has to stand on its own in the title ("May" doesn't match
    "Mayhem", "2026" doesn't match "20261");
  - moving an issue to another milestone removes it from the old plan;
  - pull requests are left out;
  - in a body with no list under the heading yet, the first entry goes straight
    under the heading.
- Polling the issue events with a cursor per task, ETag/304 when nothing
  changed, and a warning when there were more new events than one run may read.
- Repo set-up: git, composer, PHPStan level 8 (clean), phpcs/php-cs-fixer,
  PHPUnit (48 tests), Cypress (2 specs), CI and release workflows, a build
  script, and a development site in `/joomla`. See
  [development.md](development.md).
- Checked on the development site: the package installs and enables itself,
  creates its table and records schema 0.1.0. Running a task through
  `scheduler:run` with a dummy token reaches GitHub and logs "401 Bad
  credentials" as the reason it stopped.

## Next steps

1. **Fork the magazine repository** (privately) as a test copy. Copy over or
   create the labels `issue plan` and `Imagery`, a few milestones, and some
   overview issues whose titles name a milestone and contain
   `### Table of contents`. The old workflow file doesn't need to be in the
   fork.
2. **Create a fine-grained token** for that fork only: *Settings → Developer
   settings → Fine-grained tokens*, repository access "Only select
   repositories", permission **Issues: Read and write**. Set an expiry date.
3. **Create the task** on the development site (or on your private copy of the
   magazine site): *System → Scheduled Tasks → New → Magazine: sync
   checklists*, with owner, repository and token, and dry run on.
4. **First run**: `php joomla/cli/joomla.php scheduler:run --id=<id>`. The log
   should say "First run: starting after event …".
5. **Try it in dry run**: add, change and remove milestones on a few issues in
   the fork. Run the task and check that the "Dry run: would update …" lines
   are what you expect.
6. **Switch dry run off**, run again, and check the overview issues on GitHub.
   Run once more: it should report "No new events since the last run."
7. **Scheduling on the real site**: use a real cron entry for
   `php cli/joomla.php scheduler:run`, or the web-cron trigger from the
   Scheduled Tasks options, rather than the lazy scheduler. The lazy scheduler
   only runs when the site has visitors.
8. **Going live**: install on the magazine site, create the task for the real
   repository with dry run on, check one or two runs, switch dry run off, and
   **disable the old workflow** in the repository's Actions tab.

## Open points

- **The magazine site's Joomla version.** The install script accepts Joomla 5.0
  and PHP 8.1 or later, but the plugin has only been run on Joomla 6.1.4. On a
  Joomla 5 site, check that the plugin installs and the task runs. The
  provider's `new MagazineChecklist(array $config)` constructor style is the one
  core uses since 5.x.
- **Where this repository lives.** As a private repository it would run into
  the same Actions limit. Under your own account and public, CI and releases
  cost nothing.
- **Log messages from the sync are English**, not language strings. They contain
  issue numbers and titles and are read by maintainers in the task log. Turn
  them into `PLG_TASK_MAGAZINECHECKLIST_LOG_*` strings if that's ever wanted.
- **The token is stored in plain text** in the task's parameters, as all Joomla
  parameters are. Keep its scope to one repository and give it an expiry date.
- **Optional later steps**, from the plan:
  - a one-off *reconcile* (compare all open issues with milestones against the
    checklists, and report the differences);
  - a GitHub App instead of a personal token: only `HttpGithubGateway`'s token
    source changes, since an installation token goes in the same header;
  - a webhook endpoint (`com_ajax` plus HMAC check) for instant updates, using
    the same `ChecklistEditor` and gateway, with the task kept as a safety net.

## No remote yet

Everything is committed locally on `main`, but there is no remote. Add one when
the GitHub repository exists, then push:

```bash
git remote add origin https://github.com/HermanPeeren/magazine-plan.git
```
