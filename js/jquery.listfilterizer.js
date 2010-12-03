/*
 * ListFilterizer - jQuery plugin to easily add search and filtering options to lists
 *
 * Copyright (c) 2010 Mohammad Jangda (http://digitalize.ca)
 *
 * Licensed under the MIT license:
 *   http://www.opensource.org/licenses/mit-license.php
 *
 */
;(function($) {
	
	// Console buster
	if(typeof(console) === undefined) console = { log: function() {} }
	
	// We need :contains to be case insensitive so lets redefine it as containsi (i for case insensitive)
	// http://stackoverflow.com/questions/187537/is-there-a-case-insensitive-jquery-contains-selector
	jQuery.expr[':'].containsi = function(a, i, m){
		return (a.textContent || a.innerText || '').toLowerCase().indexOf(m[3].toLowerCase()) >= 0;
	};
	
	$.fn.listFilterizer = function(options) {
		var options = $.extend({}, $.fn.listFilterizer.defaults, options)
			, inputElem = document.createElement('input')
			, filtersEnabled = options.filters.length > 0
			;
		
		return this.each(function() {
			
			var $list, $tabs, $input, $tools, $container;
			
			function init(list) {
				
				$list = $(list);
				
				$container = $('<' + options.containerTag + '/>')
					.attr(options.containerAttrs)
					;
				$tools = $('<' + options.toolsTag + '/>')
					.attr(options.toolsAttrs)
					.addClass(options.toolsClass)
					;
				
				if(filtersEnabled) {
					$tabs = $('<ul/>')
						.addClass(options.tabsClass)
						;
					
					for(var i = 0; i < options.filters.length; i++) {
						var filter = options.filters[i];
						var $filterTab = $('<li/>')
							.text(filter.label)
							.attr(options._filterIdAttr, i)
							.bind('click', _filterTabEvent)
							;
						$tabs.append($filterTab);
					}
					
					$tools.append($tabs)
				}
				
				// IE freaks out if you try to set the input type as search
				var inputType = supportsSearch() ? 'search' : 'text';
				
				$input = $('<input/>')
					.attr('type', inputType)
					.attr(options.inputAttrs)
					.addClass(options.inputClass)
					.bind('search', _filterInputSearch)
					.bind('keydown', _filterInputKeydown)
					.bind('keyup', _filterInputKeyup)
					.attr('placeholder', options.inputPlaceholder)
					;
				
				// Fallback for browsers that don't support placeholders
				if(!supportsPlaceholder()) {
					$input
						.addClass(options.inputPlaceholderClass)
						.val(options.inputPlaceholder)
						.focus(function() {
							var $this = $(this);
							if($this.val() == options.inputPlaceholder) {
								$this
									.val('')
									.removeClass(options.inputPlaceholderClass)
									;
							}
						})
						.blur(function() {
							var $this = $(this);
							if(!$.trim($this.val())) {
								$this
									.val(options.inputPlaceholder)
									.addClass(options.inputPlaceholderClass)
									;
							}
						})
						;
				}
				
				$tools.append($input);
				
				// Wrap list in new container	
				$container
					.append($tools)
					.insertBefore($list)
					.append($list)
					;
					
				// Filter to first
				if(filtersEnabled) selectTab($tabs.children().first());
			}
			
			function _filterInputSearch(e) {
				filterList();
				return false;
			}
			
			function _filterInputKeydown(e) {
				// Prevent enter key
				switch(e.keyCode) {
					case 13:
						e.preventDefault();
						break;
					case 27:
						e.target.setAttribute('value', '');
						break;
					default:
						break;
				}
			}
			function _filterInputKeyup(e) {
				filterList();
			}
			
			function _filterTabEvent(e) {
				e.preventDefault();
				selectTab($(this));
			}
			
			function selectTab($tab) {
				$tab
					.siblings()
						.removeClass(options.activeTabClass)
						.end()
					.addClass(options.activeTabClass)
					;
				filterList();
			}
			
			function filterList() {
				var activeSearch = getActiveSearch();
				var activeTab = getActiveTab();
				
				var $filtered = $listItems = getListItems();
				
				// Get visible items based on selected tab
				if(activeTab) {
					var filter = getFilter(activeTab);
					$filtered = $filtered.filter(filter.selector) // TODO: should this be filtered from listitems?
				}
				
				// Filter based on search term
				if(activeSearch) {
					$filtered = $filtered.filter(':containsi('+ activeSearch +')');
				}
				
				$listItems.hide();
				$filtered.show();
			}
			
			function getListItems() {
				return $list.find(options.elementSelector);
			}
			
			function getFilter(filterId) {
				return options.filters[filterId]
			}
			
			function getActiveSearch() {
				var search = $input.val() || '';
				search = (search == options.inputPlaceholder) ? '' : search;
				return search;
			}
			
			function getActiveTab() {
				if(filtersEnabled)
					return $tabs.find('.'+options.activeTabClass).attr(options._filterIdAttr) || -1;
			}
			
			// Do the init thing!
			init(this);
		});
		
		// Borrowed graciously from Modernizr: https://github.com/Modernizr/Modernizr
		function supportsSearch() {
			inputElem.setAttribute('type', 'search');
			return inputElem.type !== 'text';
		}
		function supportsPlaceholder() {
			return !!('placeholder' in inputElem);
		}
	}
	
	$.fn.listFilterizer.defaults = {
		filters: [
			{
				label: 'All'
				, selector: '*'
			}
			,
			{
				label: 'Selected'
				, selector: ':has(input:checked)' // TODO: pass in function as a filter selector
			}
		]
		, showCounts: true // TODO: implement this
		, selectedClass: 'selected'
		, elementSelector: 'li'
		, containerTag: 'div'
		, containerAttrs: {}
		, toolsTag: 'div'
		, toolsClass: 'list-filterizer-tools'
		, toolsAttrs: {}
		
		, tabsClass: 'list-filterizer-tabs'
		, tabsAttrs: {}
		, activeTabClass: 'active'
		
		, inputClass: 'list-filterizer-search'
		, inputPlaceholder: 'Search...'
		, inputPlaceholderClass: 'input-placeholder'
		, inputAttrs: {}
		
		, noResultsText: 'No results found' // TODO: implement this
		
		,_filterIdAttr: 'data-listfilterid'
	}
    
})(jQuery);