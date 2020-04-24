const addCategoryToPost = async (categoryName) => {
    const categoryPanelButton = await page.$x('//button[text()="Categories"]');

    await categoryPanelButton[0].click();

    await page.waitForXPath(
        '//button[text()="Add New Category"]',
        { timeout: 3000 }
    );

    const addCategoryLink = await page.$x('//button[text()="Add New Category"]');

    addCategoryLink[0].click();

    await page.waitForSelector(
        '.editor-post-taxonomies__hierarchical-terms-input', 
        { timeout: 3000 }
    );

    // Type the category name in the field.
    await page.type(
        '.editor-post-taxonomies__hierarchical-terms-input',
        categoryName
    );

    await page.click(
        '.editor-post-taxonomies__hierarchical-terms-submit'   
    )
}

/**
 * We need to implement our own `publishPost` to test on the admin
 * due to some aniamtion issues: https://github.com/WordPress/gutenberg/pull/20329
 */
const publishPost = async() => {
    await page.waitForSelector(
        '.editor-post-publish-panel__toggle:not([aria-disabled="true"])'
    );
    await page.click( '.editor-post-publish-panel__toggle' );
    await page.waitForSelector( '.editor-post-publish-button' );

    // Wait for the sliding panel animation to complete
    await page.waitFor(200);

    // Publish the post
    // see: https://github.com/WordPress/gutenberg/pull/20329
    await page.click( '.editor-post-publish-button' );

    // A success notice should show up
    await page.waitForSelector( '.components-snackbar' );

}

export { addCategoryToPost, publishPost }