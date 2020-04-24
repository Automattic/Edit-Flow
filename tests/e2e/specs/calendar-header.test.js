/**
 * WordPress dependencies
 */

import { createNewPost, visitAdminPage } from "@wordpress/e2e-test-utils";
import { addCategoryToPost, publishPost } from '../utils';

describe("Calendar Header", () => {

    it("expects a user can select a value for all filters save them and reset them", async () => {
        await createNewPost({title: 'Title' });
        await addCategoryToPost('Category A');
        await publishPost();

        await visitAdminPage("index.php", "page=calendar");

        await page.waitForSelector('.ef-calendar-header');

        // Select post status
        await page.select('[name="post_status"]', 'publish');

        await page.click('[placeholder="Select a user"]')
        
        // Wait for selector didn't seem to be working here, subbing for just a simple time wait
        page.waitFor(200);

        await page.click('.ef-calendar-filter-author ul li[aria-label="admin"]');

        await page.click('[placeholder="Select a category"]')

        // Wait for selector didn't seem to be working here, subbing for just a simple time wait
        page.waitFor(200);
        
        await page.click('.ef-calendar-filter-cat ul li[aria-label="Category A"]');

        await page.select('[name="num_weeks"]', '7');

        await page.click('.ef-calendar-filters-buttons button[type="submit"]');

        await page.waitForSelector('.ef-calendar-header');

        const postStatusSelect = await page.$('[name="post_status"]');
        const postStatusValue = await postStatusSelect.evaluate(el => el.value);

        const userValueInput = await page.$('[placeholder="Select a user"]');
        const userValue = await userValueInput.evaluate(el => el.value);

        const categoryValueInput = await page.$('.ef-calendar-filter-cat input');
        const categoryValue = await categoryValueInput.evaluate(el => el.value);

        const numWeeksSelect = await page.$('[name="num_weeks"]');
        const numWeeksSelectValue = await numWeeksSelect.evaluate(el => el.value);
        
        expect(postStatusValue).toBe('publish');
        expect(userValue).toBe('admin');
        expect(categoryValue).toBe('Category A');
        expect(numWeeksSelectValue).toBe('7');

        // Click the reset button
        await page.click('.ef-calendar-filters-buttons a[name="ef-calendar-reset-filters"]');
        
        await page.waitForSelector('.ef-calendar-header');
        
        const postStatusSelectReset = await page.$('[name="post_status"]');
        const postStatusValueReset = await postStatusSelectReset.evaluate(el => el.value);

        const userValueInputReset = await page.$('[placeholder="Select a user"]');
        const userValueReset = await userValueInputReset.evaluate(el => el.value);

        const categoryValueInputReset = await page.$('.ef-calendar-filter-cat input');
        const categoryValueReset = await categoryValueInputReset.evaluate(el => el.value);

        const numWeeksSelectReset = await page.$('[name="num_weeks"]');
        const numWeeksSelectValueReset = await numWeeksSelectReset.evaluate(el => el.value);

        expect(postStatusValueReset).toBe('');
        expect(userValueReset).toBe('');
        expect(categoryValueReset).toBe('');
        expect(numWeeksSelectValueReset).toBe('6');

    });
});