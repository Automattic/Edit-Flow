function setup() {
	if ( EditFlowCustomStatuses ) {
		const statuses = EditFlowCustomStatuses.map( status => ( {
			label: status.name,
			value: status.slug,
		} ) );

		console.log( statuses );
	}
}

setup();
