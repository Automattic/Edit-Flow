/* global wp, document, ef_default_custom_status */

/**
 * External dependencies
 */
import React from 'react';
import PropTypes from 'prop-type';

/**
 * Internal dependencies
 */
import './editor.scss';
import './style.scss';

const { __ } = wp.i18n;
const { PluginPostStatusInfo } = wp.editPost;
const { registerPlugin } = wp.plugins;
const { subscribe, dispatch, select, withSelect, withDispatch } = wp.data;
const { compose } = wp.compose;
const { SelectControl } = wp.components;

/**
 * Map Custom Statuses as options for SelectControl
 */
const customStatuses = window.EditFlowStatuses.custom_statuses.map( s => ( { label: s.name, value: s.slug } ) );

// Trash isn't in `default_statuses` but we need to check for it
const isCoreStatus = slug => window.EditFlowStatuses.default_statuses.find( s => s.slug === slug ) || slug === 'trash';
const getCustomStatusLabel = slug => customStatuses.find( s => s.value === slug ).label;
const getEditorPostSaveDraftDOM = () => {
	return document.querySelector( '.editor-post-save-draft' );
};

/**
 * Hack :(
 *
 * @param {string} status the status of the post
 *
 * @see https://github.com/WordPress/gutenberg/issues/3144
 */
const sideEffectL10nManipulation = status => {
	const statusText = status ? `${ __( 'Save as' ) } ${ status }` : `${ __( 'Save' ) }`;
	const node = getEditorPostSaveDraftDOM();
	if ( node ) {
		node.innerText = statusText;
	}
};

/**
 * Hack :(
 *
 * @see https://github.com/WordPress/gutenberg/issues/3144
 *
 * Gutenberg will also override the status set in '.editor-post-save-draft' after save, and there isn't yet a way
 * to subscribe to a "post save" message. So instead set a timeout and override the text in '.editor-post-save-draft
 *
 * The timeout for this method is an attempt to counteract https://github.com/WordPress/gutenberg/blob/95e769df1f82f6b0ef587d81af65dd2f48cd1c38/packages/editor/src/components/post-saved-state/index.js#L37-L42
 *
 * It's effectively a mutation observer with a limit on the number of attempts it will poll the DOM
 */
let activePostStatusUpdateTimeout = null;
const schedulePostStatusUpdater = ( timeout = 120, attempts = 20 ) => {
	if ( attempts < 0 ) {
		return;
	}

	const node = getEditorPostSaveDraftDOM();

	if ( node ) {
		const status = select( 'core/editor' ).getEditedPostAttribute( 'status' );
		const statusLabel = getCustomStatusLabel( status );

		if ( typeof status === 'undefined' || typeof statusLabel === 'undefined' || isCoreStatus( status ) ) {
			return;
		}

		sideEffectL10nManipulation( statusLabel );
		clearTimeout( activePostStatusUpdateTimeout );
	} else {
		// Clearing timeouts so we don't stack them
		clearTimeout( activePostStatusUpdateTimeout );
		activePostStatusUpdateTimeout = setTimeout( () => {
			schedulePostStatusUpdater( timeout, attempts - 1 );
		}, timeout );
	}
};

// Set the status to the default custom status.
subscribe( function() {
	const postId = select( 'core/editor' ).getCurrentPostId();
	// Post isn't ready yet so don't do anything.
	if ( ! postId ) {
		return;
	}

	// For new posts, we need to force the our default custom status.
	// Otherwise WordPress will force it to "Draft".
	const isCleanNewPost = select( 'core/editor' ).isCleanNewPost();
	if ( isCleanNewPost ) {
		dispatch( 'core/editor' ).editPost( {
			status: ef_default_custom_status,
		} );

		return;
	}

	// Update the "Save" button.
	const status = select( 'core/editor' ).getEditedPostAttribute( 'status' );
	if ( typeof status !== 'undefined' && status !== 'publish' ) {
		schedulePostStatusUpdater();
	}
} );

/**
 * Custom status component
 * @param {Object} param - An object.
 * @param {string} onUpdate - Function called for updating
 * @param {string} status - Status of the post
 *
 * @return {JSX} - the extended post status panel
 */
const EditFlowCustomPostStati = ( { onUpdate, status } ) => (
	<PluginPostStatusInfo
		className={ `edit-flow-extended-post-status edit-flow-extended-post-status-${ status }` }
	>
		<h4>{ status !== 'publish' ? __( 'Extended Post Status', 'edit-flow' ) : __( 'Extended Post Status Disabled.', 'edit-flow' ) }</h4>

		{ status !== 'publish' ? <SelectControl
			label=""
			value={ status }
			options={ customStatuses }
			onChange={ onUpdate }
		/> : null }

		<small className="edit-flow-extended-post-status-note">
			{ status !== 'publish' ? __( 'Note: this will override all status settings above.', 'edit-flow' ) : __( 'To select a custom status, please unpublish the content first.', 'edit-flow' ) }
		</small>
	</PluginPostStatusInfo>
);

EditFlowCustomPostStati.propTypes = {
	onUpdate: PropTypes.func,
	status: PropTypes.string,
};

const mapSelectToProps = _select => {
	return {
		status: _select( 'core/editor' ).getEditedPostAttribute( 'status' ),
	};
};

const mapDispatchToProps = _dispatch => {
	return {
		onUpdate( status ) {
			_dispatch( 'core/editor' ).editPost( { status } );
			schedulePostStatusUpdater();
		},
	};
};

const plugin = compose(
	withSelect( mapSelectToProps ),
	withDispatch( mapDispatchToProps )
)( EditFlowCustomPostStati );

/**
 * Kick it off
 */
registerPlugin( 'edit-flow-custom-status', {
	icon: 'edit-flow',
	render: plugin,
} );
