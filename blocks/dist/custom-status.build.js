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
/******/ 	return __webpack_require__(__webpack_require__.s = "./blocks/src/custom-status/block.js");
/******/ })
/************************************************************************/
/******/ ({

/***/ "./blocks/src/custom-status/block.js":
/*!*******************************************!*\
  !*** ./blocks/src/custom-status/block.js ***!
  \*******************************************/
/*! no exports provided */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _editor_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./editor.scss */ "./blocks/src/custom-status/editor.scss");
/* harmony import */ var _editor_scss__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_editor_scss__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./style.scss */ "./blocks/src/custom-status/style.scss");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_style_scss__WEBPACK_IMPORTED_MODULE_1__);


var __ = wp.i18n.__;
var PluginPostStatusInfo = wp.editPost.PluginPostStatusInfo;
var registerPlugin = wp.plugins.registerPlugin;
var _wp$data = wp.data,
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

var getStatusLabel = function getStatusLabel(slug) {
  return statuses.find(function (s) {
    return s.value === slug;
  }).label;
}; // Hack :(
// @see https://github.com/WordPress/gutenberg/issues/3144


var sideEffectL10nManipulation = function sideEffectL10nManipulation(status) {
  var node = document.querySelector('.editor-post-save-draft');

  if (node) {
    document.querySelector('.editor-post-save-draft').innerText = "".concat(__('Save'), " ").concat(status);
  }
};
/**
 * Custom status component
 * @param object props
 */


var EditFlowCustomPostStati = function EditFlowCustomPostStati(_ref) {
  var onUpdate = _ref.onUpdate,
      status = _ref.status;
  return wp.element.createElement(PluginPostStatusInfo, {
    className: "edit-flow-extended-post-status edit-flow-extended-post-status-".concat(status)
  }, wp.element.createElement("h4", null, status !== 'publish' ? __('Extended Post Status', 'edit-flow') : __('Extended Post Status Disabled.', 'edit-flow')), status !== 'publish' ? wp.element.createElement(SelectControl, {
    label: "",
    value: status,
    options: statuses,
    onChange: onUpdate
  }) : null, wp.element.createElement("small", {
    className: "edit-flow-extended-post-status-note"
  }, status !== 'publish' ? __("Note: this will override all status settings above.", 'edit-flow') : __('Please switch to draft first.')));
};

var plugin = compose(withSelect(function (select) {
  return {
    status: select('core/editor').getEditedPostAttribute('status')
  };
}), withDispatch(function (dispatch) {
  return {
    onUpdate: function onUpdate(status) {
      dispatch('core/editor').editPost({
        status: status
      });
      sideEffectL10nManipulation(getStatusLabel(status));
    }
  };
}))(EditFlowCustomPostStati);
/**
 * Kick it off
 */

registerPlugin('edit-flow-custom-status', {
  icon: 'edit-flow',
  render: plugin
});

/***/ }),

/***/ "./blocks/src/custom-status/editor.scss":
/*!**********************************************!*\
  !*** ./blocks/src/custom-status/editor.scss ***!
  \**********************************************/
/*! no static exports found */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ }),

/***/ "./blocks/src/custom-status/style.scss":
/*!*********************************************!*\
  !*** ./blocks/src/custom-status/style.scss ***!
  \*********************************************/
/*! no static exports found */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ })

/******/ });
//# sourceMappingURL=custom-status.build.js.map