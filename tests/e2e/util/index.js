import { visitAdminPage, loginUser } from "@wordpress/e2e-test-utils";

async function createUser(username, email, password, role = 'author') {
	await loginUser('admin', 'password');

	await visitAdminPage('user-new.php');
	
	await page.$eval('#user_login', (el, username) => el.value = username, username);
	await page.$eval('#email', (el, email) => el.value = email, email);

	await page.click(".wp-generate-pw");

	await page.$eval('#pass1', (el, password) => el.value = password, password);
	await page.$eval('#pass2', (el, password) => el.value = password, password);

	await page.$eval('.pw-checkbox', (el) => el.checked = true);

	await page.select('#role', role);


	await page.click("#createusersub");
}

export { createUser }