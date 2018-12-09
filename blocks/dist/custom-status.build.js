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
//# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJzb3VyY2VzIjpbIndlYnBhY2s6Ly8vd2VicGFjay9ib290c3RyYXAiLCJ3ZWJwYWNrOi8vLy4vYmxvY2tzL3NyYy9jdXN0b20tc3RhdHVzL2Jsb2NrLmpzIiwid2VicGFjazovLy8uL2Jsb2Nrcy9zcmMvY3VzdG9tLXN0YXR1cy9lZGl0b3Iuc2NzcyIsIndlYnBhY2s6Ly8vLi9ibG9ja3Mvc3JjL2N1c3RvbS1zdGF0dXMvc3R5bGUuc2NzcyJdLCJuYW1lcyI6WyJfXyIsIndwIiwiaTE4biIsIlBsdWdpblBvc3RTdGF0dXNJbmZvIiwiZWRpdFBvc3QiLCJyZWdpc3RlclBsdWdpbiIsInBsdWdpbnMiLCJkYXRhIiwid2l0aFNlbGVjdCIsIndpdGhEaXNwYXRjaCIsImNvbXBvc2UiLCJTZWxlY3RDb250cm9sIiwiY29tcG9uZW50cyIsInN0YXR1c2VzIiwid2luZG93IiwiRWRpdEZsb3dDdXN0b21TdGF0dXNlcyIsIm1hcCIsInMiLCJsYWJlbCIsIm5hbWUiLCJ2YWx1ZSIsInNsdWciLCJnZXRTdGF0dXNMYWJlbCIsImZpbmQiLCJzaWRlRWZmZWN0TDEwbk1hbmlwdWxhdGlvbiIsInN0YXR1cyIsIm5vZGUiLCJkb2N1bWVudCIsInF1ZXJ5U2VsZWN0b3IiLCJpbm5lclRleHQiLCJFZGl0Rmxvd0N1c3RvbVBvc3RTdGF0aSIsIm9uVXBkYXRlIiwicGx1Z2luIiwic2VsZWN0IiwiZ2V0RWRpdGVkUG9zdEF0dHJpYnV0ZSIsImRpc3BhdGNoIiwiaWNvbiIsInJlbmRlciJdLCJtYXBwaW5ncyI6IjtBQUFBO0FBQ0E7O0FBRUE7QUFDQTs7QUFFQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTs7QUFFQTtBQUNBOztBQUVBO0FBQ0E7O0FBRUE7QUFDQTtBQUNBOzs7QUFHQTtBQUNBOztBQUVBO0FBQ0E7O0FBRUE7QUFDQTtBQUNBO0FBQ0Esa0RBQTBDLGdDQUFnQztBQUMxRTtBQUNBOztBQUVBO0FBQ0E7QUFDQTtBQUNBLGdFQUF3RCxrQkFBa0I7QUFD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QUE2QyxRQUE3QztBQURjLEdBQWI7QUFBQSxDQUFELENBRFEsRUFJbEJ6QixZQUFZLENBQUMsVUFBQzBCLFFBQUQ7QUFBQSxTQUFlO0FBQzFCSixZQUQwQixvQkFDakJOLE1BRGlCLEVBQ1Q7QUFDZlUsY0FBUSxDQUFDLGFBQUQsQ0FBUixDQUF3Qi9CLFFBQXhCLENBQWtDO0FBQUVxQixjQUFNLEVBQU5BO0FBQUYsT0FBbEM7QUFDQUQsZ0NBQTBCLENBQUVGLGNBQWMsQ0FBRUcsTUFBRixDQUFoQixDQUExQjtBQUNEO0FBSnlCLEdBQWY7QUFBQSxDQUFELENBSk0sQ0FBUCxDQVVYSyx1QkFWVyxDQUFiO0FBWUE7Ozs7QUFHQXpCLGNBQWMsQ0FBRSx5QkFBRixFQUE2QjtBQUMxQytCLE1BQUksRUFBRSxXQURvQztBQUV6Q0MsUUFBTSxFQUFFTDtBQUZpQyxDQUE3QixDQUFkLEM7Ozs7Ozs7Ozs7O0FDaEVBLHlDOzs7Ozs7Ozs7OztBQ0FBLHlDIiwiZmlsZSI6Ii4vY3VzdG9tLXN0YXR1cy5idWlsZC5qcyIsInNvdXJjZXNDb250ZW50IjpbIiBcdC8vIFRoZSBtb2R1bGUgY2FjaGVcbiBcdHZhciBpbnN0YWxsZWRNb2R1bGVzID0ge307XG5cbiBcdC8vIFRoZSByZXF1aXJlIGZ1bmN0aW9uXG4gXHRmdW5jdGlvbiBfX3dlYnBhY2tfcmVxdWlyZV9fKG1vZHVsZUlkKSB7XG5cbiBcdFx0Ly8gQ2hlY2sgaWYgbW9kdWxlIGlzIGluIGNhY2hlXG4gXHRcdGlmKGluc3RhbGxlZE1vZHVsZXNbbW9kdWxlSWRdKSB7XG4gXHRcdFx0cmV0dXJuIGluc3RhbGxlZE1vZHVsZXNbbW9kdWxlSWRdLmV4cG9ydHM7XG4gXHRcdH1cbiBcdFx0Ly8gQ3JlYXRlIGEgbmV3IG1vZHVsZSAoYW5kIHB1dCBpdCBpbnRvIHRoZSBjYWNoZSlcbiBcdFx0dmFyIG1vZHVsZSA9IGluc3RhbGxlZE1vZHVsZXNbbW9kdWxlSWRdID0ge1xuIFx0XHRcdGk6IG1vZHVsZUlkLFxuIFx0XHRcdGw6IGZhbHNlLFxuIFx0XHRcdGV4cG9ydHM6IHt9XG4gXHRcdH07XG5cbiBcdFx0Ly8gRXhlY3V0ZSB0aGUgbW9kdWxlIGZ1bmN0aW9uXG4gXHRcdG1vZHVsZXNbbW9kdWxlSWRdLmNhbGwobW9kdWxlLmV4cG9ydHMsIG1vZHVsZSwgbW9kdWxlLmV4cG9ydHMsIF9fd2VicGFja19yZXF1aXJlX18pO1xuXG4gXHRcdC8vIEZsYWcgdGhlIG1vZHVsZSBhcyBsb2FkZWRcbiBcdFx0bW9kdWxlLmwgPSB0cnVlO1xuXG4gXHRcdC8vIFJldHVybiB0aGUgZXhwb3J0cyBvZiB0aGUgbW9kdWxlXG4gXHRcdHJldHVybiBtb2R1bGUuZXhwb3J0cztcbiBcdH1cblxuXG4gXHQvLyBleHBvc2UgdGhlIG1vZHVsZXMgb2JqZWN0IChfX3dlYnBhY2tfbW9kdWxlc19fKVxuIFx0X193ZWJwYWNrX3JlcXVpcmVfXy5tID0gbW9kdWxlcztcblxuIFx0Ly8gZXhwb3NlIHRoZSBtb2R1bGUgY2FjaGVcbiBcdF9fd2VicGFja19yZXF1aXJlX18uYyA9IGluc3RhbGxlZE1vZHVsZXM7XG5cbiBcdC8vIGRlZmluZSBnZXR0ZXIgZnVuY3Rpb24gZm9yIGhhcm1vbnkgZXhwb3J0c1xuIFx0X193ZWJwYWNrX3JlcXVpcmVfXy5kID0gZnVuY3Rpb24oZXhwb3J0cywgbmFtZSwgZ2V0dGVyKSB7XG4gXHRcdGlmKCFfX3dlYnBhY2tfcmVxdWlyZV9fLm8oZXhwb3J0cywgbmFtZSkpIHtcbiBcdFx0XHRPYmplY3QuZGVmaW5lUHJvcGVydHkoZXhwb3J0cywgbmFtZSwgeyBlbnVtZXJhYmxlOiB0cnVlLCBnZXQ6IGdldHRlciB9KTtcbiBcdFx0fVxuIFx0fTtcblxuIFx0Ly8gZGVmaW5lIF9fZXNNb2R1bGUgb24gZXhwb3J0c1xuIFx0X193ZWJwYWNrX3JlcXVpcmVfXy5yID0gZnVuY3Rpb24oZXhwb3J0cykge1xuIFx0XHRpZih0eXBlb2YgU3ltYm9sICE9PSAndW5kZWZpbmVkJyAmJiBTeW1ib2wudG9TdHJpbmdUYWcpIHtcbiBcdFx0XHRPYmplY3QuZGVmaW5lUHJvcGVydHkoZXhwb3J0cywgU3ltYm9sLnRvU3RyaW5nVGFnLCB7IHZhbHVlOiAnTW9kdWxlJyB9KTtcbiBcdFx0fVxuIFx0XHRPYmplY3QuZGVmaW5lUHJvcGVydHkoZXhwb3J0cywgJ19fZXNNb2R1bGUnLCB7IHZhbHVlOiB0cnVlIH0pO1xuIFx0fTtcblxuIFx0Ly8gY3JlYXRlIGEgZmFrZSBuYW1lc3BhY2Ugb2JqZWN0XG4gXHQvLyBtb2RlICYgMTogdmFsdWUgaXMgYSBtb2R1bGUgaWQsIHJlcXVpcmUgaXRcbiBcdC8vIG1vZGUgJiAyOiBtZXJnZSBhbGwgcHJvcGVydGllcyBvZiB2YWx1ZSBpbnRvIHRoZSBuc1xuIFx0Ly8gbW9kZSAmIDQ6IHJldHVybiB2YWx1ZSB3aGVuIGFscmVhZHkgbnMgb2JqZWN0XG4gXHQvLyBtb2RlICYgOHwxOiBiZWhhdmUgbGlrZSByZXF1aXJlXG4gXHRfX3dlYnBhY2tfcmVxdWlyZV9fLnQgPSBmdW5jdGlvbih2YWx1ZSwgbW9kZSkge1xuIFx0XHRpZihtb2RlICYgMSkgdmFsdWUgPSBfX3dlYnBhY2tfcmVxdWlyZV9fKHZhbHVlKTtcbiBcdFx0aWYobW9kZSAmIDgpIHJldHVybiB2YWx1ZTtcbiBcdFx0aWYoKG1vZGUgJiA0KSAmJiB0eXBlb2YgdmFsdWUgPT09ICdvYmplY3QnICYmIHZhbHVlICYmIHZhbHVlLl9fZXNNb2R1bGUpIHJldHVybiB2YWx1ZTtcbiBcdFx0dmFyIG5zID0gT2JqZWN0LmNyZWF0ZShudWxsKTtcbiBcdFx0X193ZWJwYWNrX3JlcXVpcmVfXy5yKG5zKTtcbiBcdFx0T2JqZWN0LmRlZmluZVByb3BlcnR5KG5zLCAnZGVmYXVsdCcsIHsgZW51bWVyYWJsZTogdHJ1ZSwgdmFsdWU6IHZhbHVlIH0pO1xuIFx0XHRpZihtb2RlICYgMiAmJiB0eXBlb2YgdmFsdWUgIT0gJ3N0cmluZycpIGZvcih2YXIga2V5IGluIHZhbHVlKSBfX3dlYnBhY2tfcmVxdWlyZV9fLmQobnMsIGtleSwgZnVuY3Rpb24oa2V5KSB7IHJldHVybiB2YWx1ZVtrZXldOyB9LmJpbmQobnVsbCwga2V5KSk7XG4gXHRcdHJldHVybiBucztcbiBcdH07XG5cbiBcdC8vIGdldERlZmF1bHRFeHBvcnQgZnVuY3Rpb24gZm9yIGNvbXBhdGliaWxpdHkgd2l0aCBub24taGFybW9ueSBtb2R1bGVzXG4gXHRfX3dlYnBhY2tfcmVxdWlyZV9fLm4gPSBmdW5jdGlvbihtb2R1bGUpIHtcbiBcdFx0dmFyIGdldHRlciA9IG1vZHVsZSAmJiBtb2R1bGUuX19lc01vZHVsZSA/XG4gXHRcdFx0ZnVuY3Rpb24gZ2V0RGVmYXVsdCgpIHsgcmV0dXJuIG1vZHVsZVsnZGVmYXVsdCddOyB9IDpcbiBcdFx0XHRmdW5jdGlvbiBnZXRNb2R1bGVFeHBvcnRzKCkgeyByZXR1cm4gbW9kdWxlOyB9O1xuIFx0XHRfX3dlYnBhY2tfcmVxdWlyZV9fLmQoZ2V0dGVyLCAnYScsIGdldHRlcik7XG4gXHRcdHJldHVybiBnZXR0ZXI7XG4gXHR9O1xuXG4gXHQvLyBPYmplY3QucHJvdG90eXBlLmhhc093blByb3BlcnR5LmNhbGxcbiBcdF9fd2VicGFja19yZXF1aXJlX18ubyA9IGZ1bmN0aW9uKG9iamVjdCwgcHJvcGVydHkpIHsgcmV0dXJuIE9iamVjdC5wcm90b3R5cGUuaGFzT3duUHJvcGVydHkuY2FsbChvYmplY3QsIHByb3BlcnR5KTsgfTtcblxuIFx0Ly8gX193ZWJwYWNrX3B1YmxpY19wYXRoX19cbiBcdF9fd2VicGFja19yZXF1aXJlX18ucCA9IFwiXCI7XG5cblxuIFx0Ly8gTG9hZCBlbnRyeSBtb2R1bGUgYW5kIHJldHVybiBleHBvcnRzXG4gXHRyZXR1cm4gX193ZWJwYWNrX3JlcXVpcmVfXyhfX3dlYnBhY2tfcmVxdWlyZV9fLnMgPSBcIi4vYmxvY2tzL3NyYy9jdXN0b20tc3RhdHVzL2Jsb2NrLmpzXCIpO1xuIiwiaW1wb3J0ICcuL2VkaXRvci5zY3NzJztcbmltcG9ydCAnLi9zdHlsZS5zY3NzJztcblxubGV0IHsgX18gfSA9IHdwLmkxOG47XG5sZXQgeyBQbHVnaW5Qb3N0U3RhdHVzSW5mbyB9ID0gd3AuZWRpdFBvc3Q7XG5sZXQgeyByZWdpc3RlclBsdWdpbiB9ID0gd3AucGx1Z2lucztcbmxldCB7IHdpdGhTZWxlY3QsIHdpdGhEaXNwYXRjaCB9ID0gd3AuZGF0YTtcbmxldCB7IGNvbXBvc2UgfSA9IHdwLmNvbXBvc2U7XG5sZXQgeyBTZWxlY3RDb250cm9sIH0gPSB3cC5jb21wb25lbnRzO1xuXG4vKipcbiAqIE1hcCBDdXN0b20gU3RhdHVzZXMgYXMgb3B0aW9ucyBmb3IgU2VsZWN0Q29udHJvbFxuICovXG5sZXQgc3RhdHVzZXMgPSB3aW5kb3cuRWRpdEZsb3dDdXN0b21TdGF0dXNlcy5tYXAoIHMgPT4gKHsgbGFiZWw6IHMubmFtZSwgdmFsdWU6IHMuc2x1ZyB9KSApO1xuXG5sZXQgZ2V0U3RhdHVzTGFiZWwgPSBzbHVnID0+IHN0YXR1c2VzLmZpbmQoIHMgPT4gcy52YWx1ZSA9PT0gc2x1ZyApLmxhYmVsXG5cbi8vIEhhY2sgOihcbi8vIEBzZWUgaHR0cHM6Ly9naXRodWIuY29tL1dvcmRQcmVzcy9ndXRlbmJlcmcvaXNzdWVzLzMxNDRcbmxldCBzaWRlRWZmZWN0TDEwbk1hbmlwdWxhdGlvbiA9IHN0YXR1cyA9PiB7XG4gIGxldCBub2RlID0gZG9jdW1lbnQucXVlcnlTZWxlY3RvcignLmVkaXRvci1wb3N0LXNhdmUtZHJhZnQnKTtcbiAgaWYgKCBub2RlICkge1xuICAgIGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3IoJy5lZGl0b3ItcG9zdC1zYXZlLWRyYWZ0JykuaW5uZXJUZXh0ID0gYCR7X18oICdTYXZlJyApIH0gJHtzdGF0dXN9YFxuICB9XG59XG5cbi8qKlxuICogQ3VzdG9tIHN0YXR1cyBjb21wb25lbnRcbiAqIEBwYXJhbSBvYmplY3QgcHJvcHNcbiAqL1xubGV0IEVkaXRGbG93Q3VzdG9tUG9zdFN0YXRpID0gKCB7IG9uVXBkYXRlLCBzdGF0dXMgfSApID0+IChcbiAgPFBsdWdpblBvc3RTdGF0dXNJbmZvXG4gICAgY2xhc3NOYW1lPXsgYGVkaXQtZmxvdy1leHRlbmRlZC1wb3N0LXN0YXR1cyBlZGl0LWZsb3ctZXh0ZW5kZWQtcG9zdC1zdGF0dXMtJHtzdGF0dXN9YCB9XG4gID5cbiAgICA8aDQ+eyBzdGF0dXMgIT09ICdwdWJsaXNoJyA/IF9fKCAnRXh0ZW5kZWQgUG9zdCBTdGF0dXMnLCAnZWRpdC1mbG93JyApIDogX18oICdFeHRlbmRlZCBQb3N0IFN0YXR1cyBEaXNhYmxlZC4nLCAnZWRpdC1mbG93JyApIH08L2g0PlxuXG4gICAgeyBzdGF0dXMgIT09ICdwdWJsaXNoJyA/IDxTZWxlY3RDb250cm9sXG4gICAgICBsYWJlbD1cIlwiXG4gICAgICB2YWx1ZT17IHN0YXR1cyB9XG4gICAgICBvcHRpb25zPXsgc3RhdHVzZXMgfVxuICAgICAgb25DaGFuZ2U9eyBvblVwZGF0ZSB9XG4gICAgLz4gOiBudWxsIH1cblxuICAgIDxzbWFsbCBjbGFzc05hbWU9XCJlZGl0LWZsb3ctZXh0ZW5kZWQtcG9zdC1zdGF0dXMtbm90ZVwiPlxuICAgICAgeyBzdGF0dXMgIT09ICdwdWJsaXNoJyA/IF9fKCBgTm90ZTogdGhpcyB3aWxsIG92ZXJyaWRlIGFsbCBzdGF0dXMgc2V0dGluZ3MgYWJvdmUuYCwgJ2VkaXQtZmxvdycgKSA6IF9fKCAnUGxlYXNlIHN3aXRjaCB0byBkcmFmdCBmaXJzdC4nICkgfVxuICAgIDwvc21hbGw+XG4gIDwvUGx1Z2luUG9zdFN0YXR1c0luZm8+XG4pO1xuXG5sZXQgcGx1Z2luID0gY29tcG9zZShcbiAgd2l0aFNlbGVjdCgoc2VsZWN0KSA9PiAoe1xuICAgIHN0YXR1czogc2VsZWN0KCdjb3JlL2VkaXRvcicpLmdldEVkaXRlZFBvc3RBdHRyaWJ1dGUoJ3N0YXR1cycpLFxuICB9KSksXG4gIHdpdGhEaXNwYXRjaCgoZGlzcGF0Y2gpID0+ICh7XG4gICAgb25VcGRhdGUoc3RhdHVzKSB7XG4gICAgICBkaXNwYXRjaCgnY29yZS9lZGl0b3InKS5lZGl0UG9zdCggeyBzdGF0dXMgfSApO1xuICAgICAgc2lkZUVmZmVjdEwxMG5NYW5pcHVsYXRpb24oIGdldFN0YXR1c0xhYmVsKCBzdGF0dXMgKSApO1xuICAgIH1cbiAgfSkpXG4pKEVkaXRGbG93Q3VzdG9tUG9zdFN0YXRpKTtcblxuLyoqXG4gKiBLaWNrIGl0IG9mZlxuICovXG5yZWdpc3RlclBsdWdpbiggJ2VkaXQtZmxvdy1jdXN0b20tc3RhdHVzJywge1xuXHRpY29uOiAnZWRpdC1mbG93JyxcbiAgcmVuZGVyOiBwbHVnaW5cbn0gKTsiLCIvLyByZW1vdmVkIGJ5IGV4dHJhY3QtdGV4dC13ZWJwYWNrLXBsdWdpbiIsIi8vIHJlbW92ZWQgYnkgZXh0cmFjdC10ZXh0LXdlYnBhY2stcGx1Z2luIl0sInNvdXJjZVJvb3QiOiIifQ==