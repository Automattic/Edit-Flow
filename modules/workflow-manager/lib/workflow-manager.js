import { Button } from '@wordpress/components';
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import './style.scss';

export default function WorkflowManager() {
	return <Button variant="primary">My button 123</Button>;
}

domReady( () => {
	const root = createRoot( document.getElementById( 'workflow-manager' ) );
	root.render( <WorkflowManager /> );
} );
