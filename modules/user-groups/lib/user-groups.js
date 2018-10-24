jQuery(document).ready(function ($) {
	// jQuery('ul#ef-post_following_users li').quicksearch({
	// 	position: 'before',
	// 	attached: 'ul#ef-post_following_users',
	// 	loaderText: '',
	// 	delay: 100
	// })
	// jQuery('#ef-usergroup-users ul').listFilterizer();

    var usergroup_id = $('#usergroup_id').val();

    if( usergroup_id !== undefined ){

        // Options for the list
        var options = {
            // values used for filters
            valueNames: ['user-item-name', 'user-item-email', {
                name: 'user_checked',
                attr: ''
            }, {data: ['user-item-id']}],

            // searchClass is used for filtering values in the list
            searchClass: 'filter-users',

            // item used for user list template
            item: '<li class="user-item" data-user-item-id> <input class="user_checked" type="checkbox" false /> <label class="user-item-name"></label> <label class="user-item-email"></label>  </li>'
        };

        // Initialize the list.js, 'users' is the html class name to fill in the users list
        var userList = new List('users', options);
        var usersPerPage = 10;
        var totalUsers = 0;
        var totalUsersCount = $('#total-users-count').val();

        function fillPaginatedUsersList(totalUsers, usersPerPage, searchKeyword) {

            // remove pagination if it existed
            if ($('#users-pagination').data("twbs-pagination")) {
                $('#users-pagination').twbsPagination('destroy');
            }

            $('#users-pagination').twbsPagination({
                totalPages: Math.ceil(totalUsers / usersPerPage), // The total number of user pages
                visiblePages: usersPerPage, // Number of users displayed in a page
                next: 'Next',
                prev: 'Prev',
                onPageClick: function (event, page) {

                    // clear the users list when the page created
                    userList.clear();

                    // Data sent to WP through ajax for paginated users list
                    var data = {
                        action: 'retrieve_users_in_usergroup',
                        usergroup_id: usergroup_id,
                        page: page,
                        users_per_page: usersPerPage,
                        nonce: ajax_object.ajax_nonce,
                        search_keyword: searchKeyword
                    };

                    jQuery.post(ajax_object.ajax_url, data, function (response) {

                        // Add the users retrieved from wordpress db to list
                        for (var user of response.users) {
                            userList.add(user);
                            if (user.user_checked) {
                                $('li[data-user-item-id=' + user['user-item-id'] + '] input:checkbox').prop("checked", true);
                            }
                        }

                        // Fill in users count info
                        $('.users-total-info-value').text(totalUsers);
                        if (searchKeyword !== '') {
                            $('.users-total-info-text').text('Totals users found');
                        }

                    });

                }
            });
        }

        function fillUsersListByKeyword(searchKeyword) {
            //// Retrieve total user counts for pagination numbering
            // Data sent to WP through ajax for user counts
            var data_user_count = {
                action: 'retrieve_users_count_in_usergroup_by_keyword',
                nonce: ajax_object.ajax_nonce,
                search_keyword: searchKeyword
            };

            jQuery.post(ajax_object.ajax_url, data_user_count, function (response) {

                totalUsers = parseInt(response);

                if (totalUsers > 0) {
                    fillPaginatedUsersList(totalUsers, usersPerPage, searchKeyword);
                } else {
                    $('#users-pagination').twbsPagination('destroy');
                    $('.users-total-info-text').text('Totals users found');
                    $('.users-total-info-value').text(totalUsers);

                }
            });
        }

        $('.search-users').bind('keypress', function (e) {
            if (e.keyCode == 13) {
                clearTimeout($.data(this, 'timer'));

                e.preventDefault();
                var searchKeyword = $('.search-users').val();
                userList.clear();

                var wait = setTimeout(fillUsersListByKeyword(searchKeyword), 10000);

                $(this).data('timer', wait);

            }
        });

        $('.btn-search-users').click(function (e) {
            clearTimeout($.data(this, 'timer'));

            e.preventDefault();
            var searchKeyword = $('.search-users').val();
            userList.clear();

            var wait = setTimeout(fillUsersListByKeyword(searchKeyword), 10000);

            $(this).data('timer', wait);

        });

        $(document).on('click', '.user-item', function (e) {

            var item_element = $(this);
            var checkbox = item_element.find("input[type='checkbox']");

            // check the checkbox when .user-item element is clicked
            if (!$(e.target).is(':checkbox') && !checkbox.is(':checked')) {
                checkbox.attr('checked', true);
            } else if ((!$(e.target).is(':checkbox') && checkbox.is(':checked'))) {
                checkbox.attr('checked', false);
            }

            var params = {
                action: 'save_user_to_usergroup',
                usergroup_id: usergroup_id,
                nonce: ajax_object.ajax_nonce,
            	user_id: ($(this).data('user-item-id')),
            };

            if (checkbox.is(':checked')) {
                params.add = true;
            } else {
                params.remove = true;
            }

            jQuery.post(ajaxurl, params)
                .done(function (response) {

                    // Trigger visual effect when ajax successful
                    var backgroundColor = item_element.parent().css('background-color');
                    item_element
                        .animate({'backgroundColor': '#CCEEBB'}, 200)
                        .animate({'backgroundColor': backgroundColor}, 200);
                })
                .fail(function (xhr, status, error) {
                    $('#ef-post_following_users_box').prev().append(' <p class="error">There was an error. Please reload the page.</p>');
                });


        });

        // Fill the initial users list on document load
        fillPaginatedUsersList(totalUsersCount, usersPerPage, '');

    } // check on usergroup

});