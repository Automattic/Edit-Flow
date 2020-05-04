/**
 * WordPress dependencies
 */

import { createNewPost, visitAdminPage, saveDraft } from "@wordpress/e2e-test-utils";
import { publishPost, schedulePost } from '../utils';

describe("Calendar Body", () => {

    it("expects a published post cannot be dragged and dropped", async () => {
        await createNewPost({title: 'Published Post' });
        await publishPost();

        await visitAdminPage("index.php", "page=calendar");

        await page.waitForSelector('.ef-calendar-header');

        const dayUnit = await page.$('.day-unit');
        const dayUnitBoundingBox = await dayUnit.boundingBox();

        const publishedPost = (await page.$x('//strong[text()="Published Post"]'))[0];
        const publishedPostParent = await publishedPost.evaluateHandle((node) => node.closest('.day-item'));
        const publishedPostParentBounding = await publishedPostParent.boundingBox();
        const publishedPostDay = await publishedPost.evaluateHandle((node) => node.closest('.post-list'));
        const publishedPostDayBounding = await publishedPostDay.boundingBox();

        await page.mouse.move(publishedPostParentBounding.x + publishedPostParentBounding.width / 2, publishedPostParentBounding.y + publishedPostParentBounding.height / 2);
        await page.mouse.down();
        await page.mouse.move(publishedPostParentBounding.x, publishedPostParentBounding.y + publishedPostDayBounding.height);
        await page.mouse.up();


        expect(await publishedPostDay.evaluate((node) => {
            return Array.prototype.slice.call(node.querySelectorAll('.item-headline.post-title strong')).map((n) => n.innerText)
        })).toContain('Published Post');
    });

    it("expects an unpublished post can be dragged and dropped", async () => {
        await createNewPost({title: 'Unpublished Post' });
        await saveDraft();

        await visitAdminPage("index.php", "page=calendar");

        await page.waitForSelector('.ef-calendar-header');

        const unpublishedPost = (await page.$x('//strong[text()="Unpublished Post"]'))[0];
        const unpublishedPostParent = await unpublishedPost.evaluateHandle((node) => node.closest('.day-item'));
        const unpublishedPostParentBounding = await unpublishedPostParent.boundingBox();
        const unpublishedPostDay = await unpublishedPost.evaluateHandle((node) => node.closest('.post-list'));
        const unpublishedPostDayBounding = await unpublishedPostDay.boundingBox();

        /** 
         * Simulate drag and drop
         */
        await page.mouse.move(unpublishedPostParentBounding.x + unpublishedPostParentBounding.width / 2, unpublishedPostParentBounding.y + unpublishedPostParentBounding.height / 2);
        await page.mouse.down();
        await page.mouse.move(unpublishedPostParentBounding.x + unpublishedPostParentBounding.width / 2, unpublishedPostParentBounding.y + unpublishedPostDayBounding.height + 20);
        await page.mouse.up();

        await page.waitFor(200);

        expect(await unpublishedPostDay.evaluate((node) => {
            return Array.prototype.slice.call(node.querySelectorAll('.item-headline.post-title strong')).map((n) => n.innerText)
        })).not.toContain('Unpublished Post');
    });

    it("expects a scheduled post can be dragged and dropped", async () => {
        await createNewPost({title: 'Scheduled Post' });
        await schedulePost();

        await visitAdminPage("index.php", "page=calendar");

        await page.waitForSelector('.ef-calendar-header');

        const scheduledPost = (await page.$x('//strong[text()="Scheduled Post"]'))[0];
        const scheduledPostParent = await scheduledPost.evaluateHandle((node) => node.closest('.day-item'));
        const scheduledPostParentBounding = await scheduledPostParent.boundingBox();
        const scheduledPostDay = await scheduledPost.evaluateHandle((node) => node.closest('.post-list'));
        const scheduledPostDayBounding = await scheduledPostDay.boundingBox();

        /** 
         * Simulate drag and drop
         */
        await page.mouse.move(scheduledPostParentBounding.x + scheduledPostParentBounding.width / 2, scheduledPostParentBounding.y + scheduledPostParentBounding.height / 2);
        await page.mouse.down();
        await page.waitFor(3000);
        await page.mouse.move(scheduledPostParentBounding.x + scheduledPostParentBounding.width / 2, scheduledPostParentBounding.y - scheduledPostDayBounding.height - 20);
        await page.waitFor(3000);
        await page.mouse.up();
        await page.waitFor(3000);

        await page.waitFor(200);

        expect(await scheduledPostDay.evaluate((node) => {
            return Array.prototype.slice.call(node.querySelectorAll('.item-headline.post-title strong')).map((n) => n.innerText)
        })).not.toContain('Scheduled Post');
    });
});