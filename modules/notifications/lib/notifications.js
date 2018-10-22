jQuery(document).ready(function($) {
	// $('#ef-post_following_users_box ul').listFilterizer();

    var post_id = $('#post_ID').val();

    // check post_id, only run the following JS if it is in post page
    if (post_id !== undefined) {

        var params = {
            action: 'save_notifications',
            post_id: post_id,
        };

        $(document).on('click', '.ef-post_following_list li input:checkbox, .ef-following_usergroups li input:checkbox', function () {
            var user_group_ids = [];
            var parent_this = $(this);
            params.ef_notifications_name = $(this).attr('name');
            params._nonce = $("#ef_notifications_nonce").val();

            $(this)
                .parent()
                .parent()
                .parent()
                .find('input:checked')
                .map(function () {
                    user_group_ids.push($(this).val());
                })

            params.user_group_ids = user_group_ids;

            $.ajax({
                type: 'POST',
                url: (ajaxurl) ? ajaxurl : wpListL10n.url,
                data: params,
                success: function (x) {

                    // This event is used to show an updated list of who will be notified of editorial comments and status updates.
                    $('#ef-post_following_box').trigger('following_list_updated');

                    var backgroundColor = parent_this.css('background-color');
                    $(parent_this.parent().parent())
                        .animate({'backgroundColor': '#CCEEBB'}, 200)
                        .animate({'backgroundColor': backgroundColor}, 200);
                },
                error: function (r) {
                    $('#ef-post_following_users_box').prev().append(' <p class="error">There was an error. Please reload the page.</p>');
                }
            });
        });

        //// Users list for notifications meta

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
            item: '<li class="user-item" data-user-item-id> <input class="user_checked" type="checkbox" false /> <p class="user-item-name"></p> <p class="user-item-email"></p>  </li>'
        };

        // Initialize the list.js, 'users' is the html class name to fill in the users list
        var userList = new List('users', options);
        var usersPerPage = 10;
        var totalUsers = 0;

        // Fill the initial users list on document load
        function initiateUsersList() {

            //// Retrieve total user counts for pagination numbering
            // Data sent to WP through ajax for user counts
            var data_user_count = {
                action: 'retrieve_users',
                nonce: ajax_object.ajax_nonce,
                count_users: true
            };

            jQuery.post(ajax_object.ajax_url, data_user_count)
                .done(function (response) {
                    totalUsers = parseInt(response);
                    fillPaginatedUsersList(totalUsers, usersPerPage, '');
                })
                .fail(function (xhr, status, error) {
                    $('#ef-post_following_users_box').prev().append(' <p class="error">There was an error. Please reload the page.</p>');
                });

        }

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
                        action: 'retrieve_users',
                        post_id: $('#post_ID').val(),
                        page: page,
                        users_per_page: usersPerPage,
                        nonce: ajax_object.ajax_nonce,
                        search_keyword: searchKeyword,
                        count_users: false
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
                action: 'retrieve_users',
                nonce: ajax_object.ajax_nonce,
                count_users: true,
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
            var user_group_ids = [];


            // check the checkbox when .user-item element is clicked
            if (!$(e.target).is(':checkbox') && !checkbox.is(':checked')) {
                checkbox.attr('checked', true);
            } else if ((!$(e.target).is(':checkbox') && checkbox.is(':checked'))) {
                checkbox.attr('checked', false);
            }

            params.ef_notifications_name = 'ef-selected-users[]';
            params._nonce = $("#ef_notifications_nonce").val();
            user_group_ids.push($(this).data('user-item-id'));
            params.user_group_ids = user_group_ids;

            if (checkbox.is(':checked')) {
                params.follow = true;
            } else {
                params.follow = false;
            }

            jQuery.post(ajaxurl, params)
                .done(function (response) {
                    // This event is used to show an updated list of who will be notified of editorial comments and status updates.
                    $('#ef-post_following_box').trigger('following_list_updated');


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

        initiateUsersList();

    }// checks post_id
});