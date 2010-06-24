jQuery(document).ready(function () {
	// THIS IS A HACK! YES, A HACK!
	// Hides all the empty taxonomy links added to the menu due to a WordPress issue
	jQuery('.wp-submenu > ul > li').each(function() {
		var $li = jQuery(this);
		if($li.text() == '') $li.hide();
	});
	
	/*
	// This should probably be moved to usergroups.js when that file is included
	jQuery(".follow_all").click(function() {
		var checked_status = this.checked;
		jQuery("input[name=" + this.id + "[]]").each(function() {
			this.checked = checked_status;
		});
	});
	*/
});
