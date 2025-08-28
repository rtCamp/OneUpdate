import { useState } from '@wordpress/element';
import { Button, Card, CardHeader, CardBody, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const SiteTable = ( { sites, onEdit, onDelete, setFormData, setShowModal } ) => {
	const [ showDeleteModal, setShowDeleteModal ] = useState( false );
	const [ deleteIndex, setDeleteIndex ] = useState( null );

	const handleDeleteClick = ( index ) => {
		setDeleteIndex( index );
		setShowDeleteModal( true );
	};

	const handleDeleteConfirm = () => {
		onDelete( deleteIndex );
		setShowDeleteModal( false );
		setDeleteIndex( null );
	};

	const handleDeleteCancel = () => {
		setShowDeleteModal( false );
		setDeleteIndex( null );
	};

	return (
		<Card style={ { marginTop: '30px' } }>
			<CardHeader>
				<h3>{ __( 'Brand Sites', 'oneupdate' ) }</h3>
				<Button
					style={ { width: 'fit-content' } }
					isPrimary
					onClick={ () => setShowModal( true ) }
				>
					{ __( 'Add Brand Site', 'oneupdate' ) }
				</Button>
			</CardHeader>
			<CardBody>
				<table className="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>{ __( 'Site Name', 'oneupdate' ) }</th>
							<th>{ __( 'Site URL', 'oneupdate' ) }</th>
							<th>{ __( 'GitHub Repo', 'oneupdate' ) }</th>
							<th>{ __( 'API Key', 'oneupdate' ) }</th>
							<th>{ __( 'Actions', 'oneupdate' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ sites.length === 0 && (
							<tr>
								<td colSpan="5" style={ { textAlign: 'center' } }>
									{ __( 'No Brand Sites found.', 'oneupdate' ) }
								</td>
							</tr>
						) }
						{ sites?.map( ( site, index ) => (
							<tr key={ index }>
								<td>{ site?.siteName }</td>
								<td>{ site?.siteUrl }</td>
								<td>
									{ site?.githubRepo }
								</td>
								<td><code>{ site?.apiKey.substring( 0, 10 ) }...</code></td>
								<td>
									<Button
										variant="secondary"
										onClick={ () => {
											setFormData( site );
											onEdit( index );
											setShowModal( true );
										} }
										style={ { marginRight: '8px' } }
									>
										{ __( 'Edit', 'oneupdate' ) }
									</Button>
									<Button
										variant="secondary"
										isDestructive
										onClick={ () => handleDeleteClick( index ) }
									>
										{ __( 'Delete', 'oneupdate' ) }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</CardBody>
			{ showDeleteModal && (
				<DeleteConfirmationModal
					onConfirm={ handleDeleteConfirm }
					onCancel={ handleDeleteCancel }
				/>
			) }
		</Card>
	);
};

const DeleteConfirmationModal = ( { onConfirm, onCancel } ) => (
	<Modal
		title={ __( 'Delete Brand Site', 'oneupdate' ) }
		onRequestClose={ onCancel }
		isDismissible={ true }
	>
		<p>{ __( 'Are you sure you want to delete this Brand Site? This action cannot be undone.', 'oneupdate' ) }</p>
		<Button variant="secondary" isDestructive onClick={ onConfirm }>
			{ __( 'Delete', 'oneupdate' ) }
		</Button>
		<Button variant="secondary" isSecondary onClick={ onCancel } style={ { marginLeft: '10px' } }>
			{ __( 'Cancel', 'oneupdate' ) }
		</Button>
	</Modal>
);

export default SiteTable;
