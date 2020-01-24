import './editor.scss';
import './style.scss';

let { __ } = wp.i18n;
let { PluginPostStatusInfo } = wp.editPost;
let { registerPlugin } = wp.plugins;
let { subscribe, dispatch, select, withSelect, withDispatch } = wp.data;
let { compose } = wp.compose;
let { SelectControl } = wp.components;

/**
 * Map Custom Statuses as options for SelectControl
 */
let statuses = window.EditFlowCustomStatuses.map( s => ({ label: s.name, value: s.slug }) );

/**
 * Hack. Change the save button's text in Gutenberg.
 *
 * @see https://github.com/WordPress/gutenberg/issues/3144
 * @see https://github.com/Automattic/Edit-Flow/issues/583
 *
 * Gutenberg overrides the label of the Save button after save (i.e. "Save Draft"). But there's no way to subscribe to a "post save" message.
 * So instead, we're just keeping the button label generic ("Save"), while waiting for a better upstream fix.
 */
let sideEffectL10nManipulation = () => {
	// If the button already exists, update the text right away. Occurs on initial page load.
	let saveButton = document.querySelector( '.editor-post-save-draft' );
	if ( saveButton ) {
		if ( saveButton.innerText === __( 'Save Draft' ) || saveButton.innerText === __( 'Save as Pending' ) ) {
			saveButton.innerText = __( 'Save' );
		}
		return;
	}

	// The button does not exist yet, let's set up an observer to wait for it to be ready.
	// Occurs during the time period when a post is saving itself.
	const parentNode = document.querySelector( '.edit-post-header__settings' );
	if ( parentNode && window.MutationObserver ) {
		const observer = buttonTextObserver();
		observer.observe( parentNode, { childList: true } );
	}
}

const buttonTextObserver = () => {
	return new MutationObserver( ( mutationsList, observer ) => {
		for ( const mutation of mutationsList ) {
			if ( ! mutation.addedNodes.length ) {
				continue;
			}

			for ( const node of mutation.addedNodes ) {
				if ( node.innerText === __( 'Save Draft' ) || node.innerText === __( 'Save as Pending' ) ) {
					node.innerText = __( 'Save' );
					observer.disconnect();
				}
			}
		}
	} );
}

// Set the status to the default custom status.
subscribe( function () {
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
      status: ef_default_custom_status
    } );

    return;
  }

  // Update the "Save" button.
  var status = select( 'core/editor' ).getEditedPostAttribute( 'status' );
  if ( typeof status !== 'undefined' && status !== 'publish' ) {
    sideEffectL10nManipulation();
  }
} );

/**
 * Custom status component
 * @param object props
 */
let EditFlowCustomPostStati = ( { onUpdate, status } ) => (
  <PluginPostStatusInfo
    className={ `edit-flow-extended-post-status edit-flow-extended-post-status-${status}` }
  >
    <h4>{ status !== 'publish' ? __( 'Extended Post Status', 'edit-flow' ) : __( 'Extended Post Status Disabled.', 'edit-flow' ) }</h4>

    { status !== 'publish' ? <SelectControl
      label=""
      value={ status }
      options={ statuses }
      onChange={ onUpdate }
    /> : null }

    <small className="edit-flow-extended-post-status-note">
      { status !== 'publish' ? __( `Note: this will override all status settings above.`, 'edit-flow' ) : __( 'To select a custom status, please unpublish the content first.', 'edit-flow' ) }
    </small>
  </PluginPostStatusInfo>
);

const mapSelectToProps = ( select ) => {
  return {
    status: select('core/editor').getEditedPostAttribute('status'),
  };
};

const mapDispatchToProps = ( dispatch ) => {
  return {
    onUpdate( status ) {
      dispatch( 'core/editor' ).editPost( { status } );
    },
  };
};

let plugin = compose(
  withSelect( mapSelectToProps ),
  withDispatch( mapDispatchToProps )
)( EditFlowCustomPostStati );

/**
 * Kick it off
 */
registerPlugin( 'edit-flow-custom-status', {
  icon: 'edit-flow',
  render: plugin
} );
