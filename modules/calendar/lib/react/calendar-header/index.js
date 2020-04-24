/**
 * External dependencies
 */
import React from 'react';
import PropTypes from 'prop-types';

/**
 * Internal dependencies
 */
import { CalendarFilters } from '../calendar-filters';
import { CalendarDateChangeButtons } from '../calendar-date-change-buttons';
import './style.react.scss';

const CalendarHeader = ( ( { filters, filterValues, numberOfWeeks, beginningOfWeek, pageUrl } ) => {
	return (
		<div className="ef-calendar-header">
			<CalendarFilters pageUrl={pageUrl} filters={filters} />
			<CalendarDateChangeButtons
				beginningOfWeek={beginningOfWeek}
				pageUrl={pageUrl}
				numberOfWeeks={numberOfWeeks}
				filterValues={filterValues}
			/>
		</div>
	);
} );

CalendarHeader.propTypes = {
	filters: PropTypes.arrayOf( PropTypes.shape( {
		name: PropTypes.string,
		filterType: PropTypes.string,
		label: PropTypes.string,
		options: PropTypes.arrayOf( PropTypes.shape( {
			label: PropTypes.string,
			value: PropTypes.any,
		} ) ),
		initialValue: PropTypes.any,
	} ) ),
	filterValues: PropTypes.object, // FilterValues is an object of key value pairs
	numberOfWeeks: PropTypes.number,
	beginningOfWeek: PropTypes.string, // Formatted 'YYYY-MM-DD'
	pageUrl: PropTypes.string,
};

export { CalendarHeader };
