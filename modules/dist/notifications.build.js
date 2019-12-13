/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "";
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = "./modules/notifications/lib/notifications.js");
/******/ })
/************************************************************************/
/******/ ({

/***/ "./modules/notifications/lib/notifications.js":
/*!****************************************************!*\
  !*** ./modules/notifications/lib/notifications.js ***!
  \****************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

var _wp$data = wp.data,
    subscribe = _wp$data.subscribe,
    select = _wp$data.select;
var BADGES_STATUS = {
  error: 'error',
  warning: 'warning',
  success: 'success'
};
var BADGES = {
  NO_ACCESS: {
    id: 'no_access',
    name: ef_notifications_localization.no_access,
    status: BADGES_STATUS['error']
  },
  NO_EMAIL: {
    id: 'no_email',
    name: ef_notifications_localization.no_email,
    status: BADGES_STATUS['error']
  },
  POST_AUTHOR: {
    id: 'post_author',
    name: ef_notifications_localization.post_author,
    class: 'ef-badge-neutral'
  },
  AUTO_SUBSCRIBE: {
    id: 'auto_subscribed',
    name: ef_notifications_localization.auto_subscribed,
    class: 'ef-badge-neutral'
  }
};

var addBadgeToEl = function addBadgeToEl($el, badge) {
  if (getBadge($el, badge)) {
    return;
  }

  $el.append(badgeTemplate(badge));
};

var removeBadgeFromEl = function removeBadgeFromEl($el, badge) {
  var existingBadge = getBadge($el, badge);

  if (!existingBadge) {
    return;
  }

  existingBadge.remove();
};

var getBadge = function getBadge($el, badge) {
  var exists = $el.find("[data-badge-id='".concat(badge.id, "']"));

  if (exists.length) {
    return jQuery(exists[0]);
  } else {
    return null;
  }
};

var badgeTemplate = function badgeTemplate(badge) {
  var classes = 'ef-user-badge';

  if (BADGES_STATUS['error'] === badge.status) {
    classes += ' ef-user-badge-error';
  }

  return "<div class=\"".concat(classes, "\" data-badge-id=\"").concat(badge.id, "\">").concat(badge.name, "</div>");
};

jQuery(document).ready(function ($) {
  jQuery('#ef-post_following_users_box ul').listFilterizer();
  var params = {
    action: 'save_notifications',
    post_id: jQuery('#post_ID').val()
  };

  var toggle_warning_badges = function toggle_warning_badges(container, response) {
    var $el = jQuery(container).parent();
    var $badgesContainer = $el.closest('li').find('.ef-user-list-badges'); // "No Access" If this user was flagged as not having access

    var user_has_no_access = response.data.subscribers_with_no_access.includes(parseInt(jQuery(container).val()));

    if (user_has_no_access) {
      addBadgeToEl($badgesContainer, BADGES['NO_ACCESS']);
    } else {
      removeBadgeFromEl($badgesContainer, BADGES['NO_ACCESS']);
    } // "No Email" If this user was flagged as not having an email


    var user_has_no_email = response.data.subscribers_with_no_email.includes(parseInt(jQuery(container).val()));

    if (user_has_no_email) {
      addBadgeToEl($badgesContainer, BADGES['NO_EMAIL']);
    } else {
      removeBadgeFromEl($badgesContainer, BADGES['NO_EMAIL']);
    }
  };

  var show_post_author_badge = function show_post_author_badge() {
    var $userListItemActions = jQuery("label[for='ef-selected-users-" + ef_post_author_id + "'] .ef-user-list-badges");
    addBadgeToEl($userListItemActions, BADGES['POST_AUTHOR']);
  };

  show_post_author_badge();

  var show_autosubscribed_badge = function show_autosubscribed_badge() {
    var $userListItemActions = jQuery("label[for='ef-selected-users-" + ef_post_author_id + "'] .ef-user-list-badges");
    addBadgeToEl($userListItemActions, BADGES['AUTO_SUBSCRIBE']);
  };

  var disable_autosubscribe_checkbox = function disable_autosubscribe_checkbox() {
    jQuery('#ef-selected-users-' + ef_post_author_id).prop('disabled', true);
  };

  if (typeof ef_post_author_auto_subscribe !== 'undefined') {
    show_autosubscribed_badge();
    disable_autosubscribe_checkbox();
  }

  jQuery(document).on('click', '.ef-post_following_list li input:checkbox, .ef-following_usergroups li input:checkbox', function () {
    var user_group_ids = [];
    var parent_this = jQuery(this);
    params.ef_notifications_name = jQuery(this).attr('name');
    params._nonce = jQuery("#ef_notifications_nonce").val();
    jQuery(this).parents('.ef-post_following_list').find('input:checked').map(function () {
      user_group_ids.push(jQuery(this).val());
    });
    params.user_group_ids = user_group_ids;
    $.ajax({
      type: 'POST',
      url: ajaxurl ? ajaxurl : wpListL10n.url,
      data: params,
      success: function success(response) {
        // Reset background color (set during toggle_warning_badges if there's a warning)
        warning_background = false; // Toggle the warning badges ("No Access" and "No Email") to signal the user won't receive notifications

        if (undefined !== response.data) {
          toggle_warning_badges(jQuery(parent_this), response);
        } // Green 40% by default


        var backgroundHighlightColor = "#90d296";

        if (warning_background) {
          // Red 40% if there's a warning
          var backgroundHighlightColor = "#ea8484";
        }

        var backgroundColor = 'transparent';
        jQuery(parent_this.parents('label')).animate({
          'backgroundColor': backgroundHighlightColor
        }, 200).animate({
          'backgroundColor': backgroundColor
        }, 200); // This event is used to show an updated list of who will be notified of editorial comments and status updates.

        jQuery('#ef-post_following_box').trigger('following_list_updated');
      },
      error: function error(r) {
        jQuery('#ef-post_following_users_box').prev().append(' <p class="error">There was an error. Please reload the page.</p>');
      }
    });
  });
});

/***/ })

/******/ });
//# sourceMappingURL=notifications.build.js.map