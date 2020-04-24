/**
 * External Dependencies
 */
import React, { useReducer, useRef } from 'react';
import PropTypes from 'prop-types';
import { __ } from '@wordpress/i18n';
import { SelectControl, Button } from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal Dependencies
 */
import { ComboBox } from '../combobox';
import './style.react.scss';

/**
 * The actions for our `useReducer`
 */
const ACTIONS = {
	CHANGE_FILTER_VALUE: 'CHANGE_FILTER_VALUE',
};

/**
 * An action to be passed to our reducer
 * @typedef {Object} Action
 * @property {string} type represents the type of the action
 * @property {*} payload represents the payload of the action
 */

/**
 * The reducer for our `useReducer`
 * @param {*} state represents the current state
 * @param {Action} action represents the type of the action
 * @returns {*} the current state
 */
function reducer( state, action ) {
	switch ( action.type ) {
		case ACTIONS.CHANGE_FILTER_VALUE:
			return { ...state, [ action.name ]: action.value };
		default:
			throw new Error();
	}
}

function init( {
	filters,
} ) {
	return {
		...filters.reduce( ( acc, next ) => {
			const filter = {
				[ next.name ]: next.initialValue || '',
			};

			if ( next.filterType === 'combobox' ) {
				filter[ `${ next.name }InputValue` ] = next.initialValue ? next.initialValue.name : '';
			}

			return {
				...acc,
				...filter,
			};
		}, [] ),
	};
}

const CalendarFilters = props => {
	const formRef = useRef( null );

	const [ state, dispatch ] = useReducer( reducer, props, init );

	const { filters, pageUrl } = props;

	return (
		<div className="ef-calendar-navigation">
			<div className="ef-calendar-filters">
				<form ref={formRef} action="" method="GET" className="ef-calendar-filters-form">
					<input type="hidden" name="page" value="calendar" />
					{
						filters.map( filter => {
							switch ( filter.filterType ) {
								case 'select':
									return (
										<div className={`ef-calendar-filter ef-calendar-filter-${ filter.name }`} key={`ef-calendar-filter-${ filter.name }`}>
											<SelectControl
												name={filter.name}
												hideLabelFromVision={true}
												label={filter.label}
												value={state[ filter.name ]}
												options={filter.options}
												onChange={newValue =>
													dispatch( {
														type: ACTIONS.CHANGE_FILTER_VALUE,
														name: filter.name,
														value: newValue,
													} )
												}
											/>
										</div>
									);
								case 'combobox':
									return (
										<div className={`ef-calendar-filter ef-calendar-filter-${ filter.name }`} key={`ef-calendar-filter-${ filter.name }`}>
											<ComboBox
												className="ef-calendar-filter-combobox"
												inputLabel={filter.inputLabel}
												buttonOpenLabel={filter.buttonOpenLabel}
												buttonCloseLabel={filter.buttonCloseLabel}
												buttonClearLabel={filter.buttonClearLabel}
												placeholder={filter.placeholder}
												hideLabelFromVision={true}
												items={filter.options}
												selectedItem={state[ filter.name ]}
												inputValue={state[ `${ filter.name }InputValue` ]}
												itemToString={item => item ? item.name : ''}
												onInputBlur={( items, inputValue ) => {
													/**
                           * If this is set, if a user has typed out a name
                           * and it matches an item in the list, select it for them
                           */
													if ( ! filter.selectFirstItemOnBlur ||
                            items.length < 1 ||
                            inputValue !== items[ 0 ].name ) {
														return;
													}

													dispatch( {
														type: ACTIONS.CHANGE_FILTER_VALUE,
														name: filter.name,
														value: items[ 0 ],
													} );
												}}
												onStateChange={changes => {
													if (
														changes.hasOwnProperty( 'selectedItem' )
													) {
														dispatch( {
															type: ACTIONS.CHANGE_FILTER_VALUE,
															name: filter.name,
															value: changes.selectedItem,
														} );
													} else if (
														changes.hasOwnProperty( 'inputValue' )
													) {
														dispatch( {
															type: ACTIONS.CHANGE_FILTER_VALUE,
															name: `${ filter.name }InputValue`,
															value: changes.inputValue,
														} );
													}
												}}
											/>
											<input type="hidden" name={filter.name} value={state[ filter.name ] ? state[ filter.name ].id : ''} />
										</div>
									);
							}
						} )
					}
					<div className="ef-calendar-filters-buttons">
						<Button type="submit" isPrimary={true}>{__( 'Apply', 'edit-flow' )}</Button>
						<Button
							type="button'"
							href={addQueryArgs( pageUrl, filters.reduce( ( acc, filter ) => {
								return {
									...acc,
									[ filter.name ]: '',
								};
							}, {} ) )}
							name="ef-calendar-reset-filters"
							isSecondary={true}>{__( 'Reset', 'edit-flow' )}</Button>
					</div>
				</form>
			</div>
		</div>
	);
};

CalendarFilters.propTypes = {
	filters: PropTypes.arrayOf( PropTypes.shape( {
		name: PropTypes.string,
		filterType: PropTypes.string,
		label: PropTypes.string,
		options: PropTypes.arrayOf( PropTypes.shape( {
			name: PropTypes.string,
			value: PropTypes.any,
		} ) ),
		initialValue: PropTypes.any,
	} ) ),
	pageUrl: PropTypes.string,
};

export { CalendarFilters };
