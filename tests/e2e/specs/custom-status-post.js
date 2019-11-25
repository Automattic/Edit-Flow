/**
 * WordPress dependencies
 */

import { createNewPost, saveDraft } from "@wordpress/e2e-test-utils";


describe("Post Custom Status", () => {
  beforeAll(async () => {
    await createNewPost({title: "Post"});
  });

  it("shows the correct status when a post is saved", async () => {
    const saveButtonText = await page.$eval( '.editor-post-save-draft', node => node.innerText );

    expect(saveButtonText).toEqual("Save as Pitch");
  });

  it("should change the save button text when the status changes", async () => {
    // Trigger a change in the "Extended Post Status" panel
    await page.select('.edit-flow-extended-post-status .components-select-control__input', 'assigned');

    const saveButtonText = await page.$eval( '.editor-post-save-draft', node => node.innerText );

    expect(saveButtonText).toEqual("Save as Assigned");
  });

  it("should change the save button text when the status changes and show the same status when post is saved", async () => {
    await page.select('.edit-flow-extended-post-status .components-select-control__input', 'assigned');

    await saveDraft();

    const saveButton = await page.waitForSelector( '.editor-post-save-draft' );

    // Wait until the status is flipped back from "Save as Draft" to the correctly set status
    await page.waitForFunction(
      'document.querySelector(".editor-post-save-draft").innerText === "Save as Assigned"',
    );

    const saveButtonText = await saveButton.evaluate( node => node.innerText );

    expect(saveButtonText).toEqual("Save as Assigned");
  });
});
