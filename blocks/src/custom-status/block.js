import './editor.scss';
import './style.scss';

let { __ } = wp.i18n;
let { PluginPostStatusInfo } = wp.editPost;
let { registerPlugin } = wp.plugins;
let { withSelect, withDispatch } = wp.data;
let { compose } = wp.compose;
let { SelectControl } = wp.components;

/**
 * Map Custom Statuses as options for SelectControl
 */
let statuses = window.EditFlowCustomStatuses.map( s => ({ label: s.name, value: s.slug }) );

let getStatusLabel = slug => statuses.find( s => s.value === slug ).label;

// Hack :(
// @see https://github.com/WordPress/gutenberg/issues/3144
let sideEffectL10nManipulation = status => {
  let node = document.querySelector('.editor-post-save-draft');
  if ( node ) {
    document.querySelector('.editor-post-save-draft').innerText = `${__( 'Save' ) } ${status}`
  }
}

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

let plugin = compose(
  withSelect((select) => ({
    status: select('core/editor').getEditedPostAttribute('status'),
  })),
  withDispatch((dispatch) => ({
    onUpdate(status) {
      dispatch('core/editor').editPost( { status } );
      sideEffectL10nManipulation( getStatusLabel( status ) );
    }
  }))
)(EditFlowCustomPostStati);

/**
 * Kick it off
 */
registerPlugin( 'edit-flow-custom-status', {
  icon: 'edit-flow',
  render: plugin
} );
