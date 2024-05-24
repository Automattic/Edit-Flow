import { ensureSidebarOpened } from "@wordpress/e2e-test-utils";

const addCategoryToPost = async (categoryName) => {
    await ensureSidebarOpened();
    await page.waitForXPath('//button[text()="Categories"]');

    await page.$$eval(
        '.components-panel__body button',
        ( sidebarButtons ) => {
            const categoriesButton = sidebarButtons.filter( el => el.textContent === 'Categories' );

            if ( categoriesButton.length === 1 && categoriesButton[ 0 ].getAttribute( 'aria-expanded' ) !== true ) {
                categoriesButton[ 0 ].scrollIntoView();
                categoriesButton[ 0 ].click();
            }
        }
    );

    await page.waitForSelector(
        '.editor-post-taxonomies__hierarchical-terms-add',
        { timeout: 3000 }
    );

    // Click the "Add New Category" button
    await page.click(
        '.editor-post-taxonomies__hierarchical-terms-add'
    )

    await page.waitForSelector(
        '.editor-post-taxonomies__hierarchical-terms-input input',
        { timeout: 3000 }
    );

    // Type the category name in the field.
    await page.type(
        '.editor-post-taxonomies__hierarchical-terms-input input',
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
    await new Promise( r => setTimeout( r, 200 ) );

    // Publish the post
    // see: https://github.com/WordPress/gutenberg/pull/20329
    await page.click( '.editor-post-publish-button' );

    // A success notice should show up
    await page.waitForSelector( '.components-snackbar' );

}

const schedulePost = async() => {
    await page.waitForSelector( '.editor-post-schedule__dialog-toggle' );

    await page.click( '.editor-post-schedule__dialog-toggle' );

    // wait for popout animation
    await new Promise( r => setTimeout( r, 200 ) );

    // Get the date after two weeks since today
    const today = new Date();
    const futureDate = new Date();
    futureDate.setDate( today.getDate() + 14 );
    const [ month, day, year ] = futureDate
        .toLocaleDateString( 'en-US' )
        .split( '/' );

    const dayInput = await page.$('.components-datetime__time-field-day input');
    await dayInput.click({ clickCount: 3 });
    await dayInput.type( day );

    await page.select('.components-datetime__time-field-month select', month.length === 1 ? '0' + month : month );

    const yearInput = await page.$('.components-datetime__time-field-year input');
    await yearInput.click({ clickCount: 3 });
    await yearInput.type( year );

    await publishPost();

}

export { addCategoryToPost, publishPost, schedulePost };

