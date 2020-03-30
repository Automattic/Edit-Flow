/**
 * WordPress dependencies
 */

import { createNewPost, saveDraft } from "@wordpress/e2e-test-utils";

describe("Editorial Comments", () => {

  it("expects a user can create an editorial comment on a post", async () => {
    await createNewPost({title: 'Title'});
    await saveDraft();

    // todo: Eventually, we should show the "Respond to post" button when a post is saved in Gutenberg
    // without having to reload the page
    await page.reload({ waitUntil: ["networkidle0", "domcontentloaded"] });

    const COMMENT_TEXT = 'Hello';

    const respondButton = await page.$('#ef-comment_respond');
    await respondButton.click();

    await page.type('#ef-replycontent', COMMENT_TEXT);

    const saveReplyButton = await page.$('.ef-replysave');
    await saveReplyButton.click();

    const commentNodes = await page.waitFor('#ef-comments .comment-content');

    const comments = await commentNodes.$$eval('p', nodes => nodes.map(n => {
      return n.innerText
    }));

    expect(comments).toContain(COMMENT_TEXT);
  });
});