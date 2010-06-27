jQuery(document).ready(function () {
//    alert(window.location.pathname);
	jQuery(".week-list").sortable({
		placeholder: 'ui-state-highlight',
		connectWith: '.connectedSortable',
		cursor: 'move',
		handle: '.item-handle',
        stop: function(e, ui) {

            var post = ui.item[0].id;
            var date = ui.item[0].parentNode.id;
                
            // update the post record
            jQuery.post(window.location.href,
                { 
									post_id: post,
									date: date
								},
                function(data) {

                }
            );
            
        }
	});
	jQuery(".week-list").disableSelection();
});

