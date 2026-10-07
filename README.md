# Magazine checklist sync

A Joomla task plugin for the Joomla Community Magazine. It keeps the table of
contents of the overview issues on GitHub in step with the milestones of the
article issues:

- an issue gets a milestone → `- [ ] #123` is added under `### Table of contents`
  in the open "issue plan" and "Imagery" issues whose title names that milestone;
- an issue loses its milestone → its entry is removed from all of them.

This used to be the GitHub Actions workflow `update-issue-plan.yml`. That
workflow stops running every month once the private repository's Actions
minutes are spent. This plugin does the same job from a Joomla site, as a
scheduled task, through GitHub's REST API, and uses no Actions minutes.

## How it works

Every run reads the repository's
[issue events](https://docs.github.com/en/rest/issues/events) since the
previous run, keeps the `milestoned` and `demilestoned` ones, and replays them
on the overview issues. Where the last run stopped is kept per task in the
table `#__magazinechecklist_cursor`. When nothing happened, GitHub answers
`304 Not Modified`, which costs no rate limit.

## Setting it up

1. Install the plugin package (it enables itself).
2. Create a fine-grained personal access token on GitHub for the magazine
   repository only, with **Issues: Read and write**.
3. In *System → Scheduled Tasks → New*, choose **Magazine: sync checklists**,
   fill in owner, repository and token, and choose an interval (10 minutes is
   plenty).
4. Leave **Dry run** on for the first runs and read the task log. Switch it off
   when the log says what you expect.
5. Disable the GitHub workflow, so the two don't both write.

The first run only records where the repository is; it does not replay older
milestone changes.

## Development

See [docs/development.md](docs/development.md). Where the work stands and what
comes next is in [docs/next-steps.md](docs/next-steps.md).

## Licence

GPL version 3 or later; see [LICENSE](LICENSE).
