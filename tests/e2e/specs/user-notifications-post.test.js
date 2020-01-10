/**
 * WordPress dependencies
 */

import { createNewPost } from "@wordpress/e2e-test-utils";
import { AUTHOR1, ADMIN } from "../config/users";

describe("Post User Notifications Signup", () => {

  beforeAll(async () => {
    await createNewPost();
  });

  it("loads the user list", async () => {
    const userList = await page.$('.ef-post_following_list');
    expect(await userList.$$eval('.ef-user_useremail', nodes => nodes.map(n => n.innerText))).toContain(AUTHOR1.email);
  });

  it("expects the user who created the post to be auto-subscribed and marked as author", async () => {
    const userList = await page.$('.ef-post_following_list');

    const users = await userList.$$eval('.ef-user-list-item', nodes => nodes.map(n => {
      return {
        userEmail: n.querySelector('.ef-user_useremail').innerText,
        userBadges: [].slice.call(n.querySelectorAll('.ef-user-badge')).map(b => b.getAttribute('data-badge-id')),
      }
    }));

    expect(users.map((u => u.userEmail))).toContain(ADMIN.email);
    expect(users.find(u => u.userEmail === ADMIN.email).userBadges).toContain("post_author");
    expect(users.find(u => u.userEmail === ADMIN.email).userBadges).toContain("auto_subscribed");
  });

  it("expects the user who created the post to be uncheckable", async () => {
    const userList = await page.$('.ef-post_following_list');

    const users = await userList.$$eval('.ef-user-list-item', nodes => nodes.map(n => {
      return {
        userEmail: n.querySelector('.ef-user_useremail').innerText,
        userBadges: [].slice.call(n.querySelectorAll('.ef-user-badge')).map(b => b.getAttribute('data-badge-id')),
      }
    }));

    expect(users.map((u => u.userEmail))).toContain(ADMIN.email);
    expect(users.find(u => u.userEmail === ADMIN.email).userBadges).toContain("post_author");
    expect(users.find(u => u.userEmail === ADMIN.email).userBadges).toContain("auto_subscribed");
  });

  it("expects the user who created the post to be uncheckable", async () => {
    const userList = await page.$('.ef-post_following_list');

    const postCreatorLabel = (await userList.$x(`//span[@class="ef-user_useremail" and contains(text(),"${ADMIN.email}")]/ancestor::label`));
    await postCreatorLabel[0].click();

    const checkbox = await postCreatorLabel[0].$('input[type="checkbox"]')

    const isChecked = await (await checkbox.getProperty('checked')).jsonValue();

    expect(isChecked).toBe(false);
  });

  it("expects a user who did not create the post to be checkable", async () => {
    const userList = await page.$('.ef-post_following_list');

    const notPostCreatorLabel = await userList.$x(`//span[@class="ef-user_useremail" and not(contains(text(),"test@test.com"))]/ancestor::label`);
    await notPostCreatorLabel[0].click();

    const checkbox = await notPostCreatorLabel[0].$('input[type="checkbox"]')

    const isChecked = await (await checkbox.getProperty('checked')).jsonValue();

    expect(isChecked).toBe(true);
  });

  it("expects a user who does not have access to the post to have a No Access badge", async () => {
    const userList = await page.$('.ef-post_following_list');

    const notPostCreatorLabel = await userList.$x(`//span[@class="ef-user_useremail" and not(contains(text(),"test@test.com"))]/ancestor::label`);
    await notPostCreatorLabel[0].click();

    const noAccessBadge = await notPostCreatorLabel[0].$('.ef-user-badge-error')

    expect(noAccessBadge).not.toBe(null);
  });
});

