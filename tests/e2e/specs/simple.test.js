/**
 * WordPress dependencies
 */

import { visitAdminPage, switchUserToAdmin } from "@wordpress/e2e-test-utils";


describe("Edit Flow", () => {

  it("the plugin settings page loads", async () => {
    await visitAdminPage("admin.php", "page=ef-settings");
    const editFlow = await page.$(".edit-flow-admin h2");
    const html = await editFlow.evaluate(ef => ef.innerHTML);

    expect(html).toEqual("Edit Flow");
  });
});
