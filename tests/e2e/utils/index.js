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

const schedulePost = async() => {
    await page.waitForSelector( '.edit-post-post-schedule__toggle' );

    await page.click( '.edit-post-post-schedule__toggle' );

    // wait for popout animation
    await page.waitFor(200);

    // Get the date after two weeks since today
    const today = new Date();
    const futureDate = new Date();
    futureDate.setDate( today.getDate() + 14 );
    const [ month, day, year ] = futureDate
        .toLocaleDateString( 'en-US' )
        .split( '/' );

    // Set the future date in the post editing screen
    await page.$eval(
        '.components-datetime__time-field-day-input',
        ( el, day ) => el.value = day,
        day
    );

    await page.$eval(
        '.components-datetime__time-field-month-select',
        ( el, month ) => el.value = month.length === 1 ? '0' + month : month,
        month
    );

    await page.$eval(
        '.components-datetime__time-field-year-input',
        ( el, year ) => el.value = year,
        year
    );

    await publishPost();

}

export { addCategoryToPost, publishPost, schedulePost }