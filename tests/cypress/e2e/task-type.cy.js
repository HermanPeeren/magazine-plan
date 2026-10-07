/**
 * The Task Scheduler offers the task type, and its form has every parameter
 * the sync reads, with the defaults the old GitHub workflow used.
 *
 * Nothing here talks to GitHub: running the task needs a repository and a
 * token, which belong to a manual test against the fork (docs/development.md).
 */
describe('The magazine checklist task type', () => {
  beforeEach(() => {
    cy.loginToAdmin();
  });

  it('is offered when creating a new task', () => {
    cy.visit('/administrator/index.php?option=com_scheduler&view=select');

    cy.contains('Magazine: sync checklists').should('be.visible');
  });

  it('has a form with the repository, the token and the checklist settings', () => {
    cy.visit('/administrator/index.php?option=com_scheduler&task=task.add&type=magazinechecklist.sync');

    cy.get('#jform_params_owner').should('exist');
    cy.get('#jform_params_repository').should('exist');
    cy.get('#jform_params_token').should('have.attr', 'type', 'password');
    cy.get('#jform_params_plan_label').should('have.value', 'issue plan');
    cy.get('#jform_params_imagery_label').should('have.value', 'Imagery');
    cy.get('#jform_params_heading').should('have.value', '### Table of contents');
    cy.get('#jform_params_max_pages').should('have.value', '10');

    // A new task starts as a dry run, so a first try writes nothing to GitHub.
    cy.get('#jform_params_dry_run1').should('be.checked');

    // Leave the form through Cancel, so the task is not left checked out.
    cy.get('#toolbar-cancel button, button.button-cancel').first().click();
  });
});
