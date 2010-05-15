jQuery(document).ready(function () {
//    alert(window.location.pathname);
	jQuery(".week-list").sortable({
		placeholder: 'ui-state-highlight',
		connectWith: '.connectedSortable',
		cursor: 'move',
		handle: '.item-handle',
        stop: function(e, ui) {

            var $post = ui.item[0].id;
            var $date = ui.item[0].parentNode.id;
            var $img = jQuery("#" + $post + ' > span.item-handle')
            $img.html($img.html().replace(/drag_handle.jpg/, 'saving.gif'));
            jQuery("#" + $post + " > span.item-handle > img").css("opacity", "1.0");
            setTimeout( function() { 
                $img.html($img.html().replace(/saving.gif/, 'drag_handle.jpg')); 
                jQuery("#" + $post + " > span.item-handle > img").css("opacity", "0.0");                
                jQuery("#" + $post + " > span.item-handle > img").hover(function() {
                    jQuery(this).css("opacity", "1.0");
                });
                }, 1000 );
                
            // update the post record
            jQuery.post(window.location.href,
                { post_id: $post, date: $date },
                function(data) {
                    $img.html($img.html().replace(/saving.gif/, 'drag_handle.jpg')); 
                    jQuery("#" + $post + " > span.item-handle > img").css("opacity", "0.0");     
                    jQuery("#" + $post + " > span.item-handle > img").hover(function() {
                        jQuery(this).css("opacity", "1.0");
                    });
                }
            );
            
        }
	});
	jQuery(".week-list").disableSelection();
});

