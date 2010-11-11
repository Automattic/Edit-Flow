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
		var options = $.extend({}, $.fn.listFilterizer.defaults, options);
        
		var filtersEnabled = options.filters.length > 0;
		
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
				var inputType = ('placeholder' in document.createElement('input')) ? 'search' : 'text';
				
				$input = $('<input/>')
					.attr('type', inputType)
					.attr('placeholder', options.inputPlaceholder)
					.attr(options.inputAttrs)
					.addClass(options.inputClass)
					.bind('search', _filterInputSearch)
					.bind('keydown', _filterInputKeydown)
					.bind('keyup', _filterInputKeyup)
					;
				
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
				if(e.keyCode == 13) e.preventDefault();
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
				return $input.val() || '';
			}
			
			function getActiveTab() {
				if(filtersEnabled)
					return $tabs.find('.'+options.activeTabClass).attr(options._filterIdAttr) || -1;
			}
			
			// Do the init thing!
			init(this);
		});
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
		, toolsClass: 'list-filterer-tools'
		, toolsAttrs: {}
		
		, tabsClass: 'list-filterer-tabs'
		, tabsAttrs: {}
		, activeTabClass: 'active'
		
		, inputClass: 'list-filterer-search'
		, inputPlaceholder: 'Search...'
		, inputAttrs: {}
		
		, noResultsText: 'No results found' // TODO: implement this
		
		,_filterIdAttr: 'data-listfilterid'
	}
    
})(jQuery);