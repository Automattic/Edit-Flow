/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./blocks/src/custom-status/editor.scss":
/*!**********************************************!*\
  !*** ./blocks/src/custom-status/editor.scss ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./blocks/src/custom-status/style.scss":
/*!*********************************************!*\
  !*** ./blocks/src/custom-status/style.scss ***!
  \*********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = React;

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
(() => {
/*!*******************************************!*\
  !*** ./blocks/src/custom-status/block.js ***!
  \*******************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _editor_scss__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./editor.scss */ "./blocks/src/custom-status/editor.scss");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./style.scss */ "./blocks/src/custom-status/style.scss");

function _createForOfIteratorHelper(o, allowArrayLike) { var it = typeof Symbol !== "undefined" && o[Symbol.iterator] || o["@@iterator"]; if (!it) { if (Array.isArray(o) || (it = _unsupportedIterableToArray(o)) || allowArrayLike && o && typeof o.length === "number") { if (it) o = it; var i = 0; var F = function F() {}; return { s: F, n: function n() { if (i >= o.length) return { done: true }; return { done: false, value: o[i++] }; }, e: function e(_e) { throw _e; }, f: F }; } throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); } var normalCompletion = true, didErr = false, err; return { s: function s() { it = it.call(o); }, n: function n() { var step = it.next(); normalCompletion = step.done; return step; }, e: function e(_e2) { didErr = true; err = _e2; }, f: function f() { try { if (!normalCompletion && it["return"] != null) it["return"](); } finally { if (didErr) throw err; } } }; }
function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }
function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) arr2[i] = arr[i]; return arr2; }


var __ = wp.i18n.__;
var PluginPostStatusInfo = wp.editPost.PluginPostStatusInfo;
var registerPlugin = wp.plugins.registerPlugin;
var _wp$data = wp.data,
  subscribe = _wp$data.subscribe,
  dispatch = _wp$data.dispatch,
  select = _wp$data.select,
  withSelect = _wp$data.withSelect,
  withDispatch = _wp$data.withDispatch;
var compose = wp.compose.compose;
var SelectControl = wp.components.SelectControl;

/**
 * Map Custom Statuses as options for SelectControl
 */
var statuses = window.EditFlowCustomStatuses.map(function (s) {
  return {
    label: s.name,
    value: s.slug
  };
});

/**
 * Subscribe to changes so we can set a default status and update a button's text.
 */
var buttonTextObserver = null;
subscribe(function () {
  var postId = select('core/editor').getCurrentPostId();
  if (!postId) {
    // Post isn't ready yet so don't do anything.
    return;
  }

  // For new posts, we need to force the default custom status.
  var isCleanNewPost = select('core/editor').isCleanNewPost();
  if (isCleanNewPost) {
    dispatch('core/editor').editPost({
      status: ef_default_custom_status
    });
  }

  // If the save button exists, let's update the text if needed.
  maybeUpdateButtonText(document.querySelector('.editor-post-save-draft'));

  // The post is being saved, so we need to set up an observer to update the button text when it's back.
  if (buttonTextObserver === null && window.MutationObserver && select('core/editor').isSavingPost()) {
    buttonTextObserver = createButtonObserver(document.querySelector('.edit-post-header__settings'));
  }
});

/**
 * Create a mutation observer that will update the
 * save button text right away when it's changed/re-added.
 *
 * Ideally there will be better ways to go about this in the future.
 * @see https://github.com/Automattic/Edit-Flow/issues/583
 */
function createButtonObserver(parentNode) {
  if (!parentNode) {
    return null;
  }
  var observer = new MutationObserver(function (mutationsList) {
    var _iterator = _createForOfIteratorHelper(mutationsList),
      _step;
    try {
      for (_iterator.s(); !(_step = _iterator.n()).done;) {
        var mutation = _step.value;
        var _iterator2 = _createForOfIteratorHelper(mutation.addedNodes),
          _step2;
        try {
          for (_iterator2.s(); !(_step2 = _iterator2.n()).done;) {
            var node = _step2.value;
            maybeUpdateButtonText(node);
          }
        } catch (err) {
          _iterator2.e(err);
        } finally {
          _iterator2.f();
        }
      }
    } catch (err) {
      _iterator.e(err);
    } finally {
      _iterator.f();
    }
  });
  observer.observe(parentNode, {
    childList: true
  });
  return observer;
}
function maybeUpdateButtonText(saveButton) {
  /*
   * saveButton.children < 1 accounts for when a user hovers over the save button
   * and a tooltip is rendered
   */
  if (saveButton && saveButton.children < 1 && (saveButton.innerText === __('Save Draft') || saveButton.innerText === __('Save as Pending'))) {
    saveButton.innerText = __('Save');
  }
}

/**
 * Custom status component
 * @param object props
 */
var EditFlowCustomPostStati = function EditFlowCustomPostStati(_ref) {
  var onUpdate = _ref.onUpdate,
    status = _ref.status;
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(PluginPostStatusInfo, {
    className: "edit-flow-extended-post-status edit-flow-extended-post-status-".concat(status)
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement("h4", null, status !== 'publish' ? __('Extended Post Status', 'edit-flow') : __('Extended Post Status Disabled.', 'edit-flow')), status !== 'publish' ? /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(SelectControl, {
    label: "",
    value: status,
    options: statuses,
    onChange: onUpdate
  }) : null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement("small", {
    className: "edit-flow-extended-post-status-note"
  }, status !== 'publish' ? __('Note: this will override all status settings above.', 'edit-flow') : __('To select a custom status, please unpublish the content first.', 'edit-flow')));
};
var mapSelectToProps = function mapSelectToProps(select) {
  return {
    status: select('core/editor').getEditedPostAttribute('status')
  };
};
var mapDispatchToProps = function mapDispatchToProps(dispatch) {
  return {
    onUpdate: function onUpdate(status) {
      dispatch('core/editor').editPost({
        status: status
      });
    }
  };
};
var plugin = compose(withSelect(mapSelectToProps), withDispatch(mapDispatchToProps))(EditFlowCustomPostStati);

/**
 * Kick it off
 */
registerPlugin('edit-flow-custom-status', {
  icon: 'edit-flow',
  render: plugin
});
})();

/******/ })()
;
//# sourceMappingURL=custom-status.build.js.map