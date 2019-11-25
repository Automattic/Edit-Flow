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
 * Hack :(
 *
 * @see https://github.com/WordPress/gutenberg/issues/3144
 *
 * Gutenberg overrides the label of the Save button after save (i.e. "Save Draft"). But there's no way to subscribe to a "post save" message.
 *
 * So instead, we're keeping the button label generic ("Save"). There's a brief period where it still flips to "Save Draft" but that's something we need to work upstream to find a good fix for.
 */
let sideEffectL10nManipulation = () => {
  let node = document.querySelector('.editor-post-save-draft');
  if ( node ) {
    document.querySelector( '.editor-post-save-draft' ).innerText = `${ __( 'Save' ) }`
  }
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
      sideEffectL10nManipulation();
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
