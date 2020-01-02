/**
 * WordPress dependencies
 */

import { visitAdminPage, switchUserToAdmin } from "@wordpress/e2e-test-utils";


describe("Edit Flow", () => {
  beforeAll(async () => {
    await visitAdminPage("admin.php", "page=ef-settings");
  });

  it("the plugin settings page loads", async () => {
    const editFlow = await page.$(".edit-flow-admin h2");
    const html = await page.evaluate(body => body.innerHTML, editFlow);

    expect(html).toEqual("Edit Flow");
  });
});
