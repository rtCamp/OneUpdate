import { useState, useEffect, createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Card, CardHeader, CardBody, Notice, Button, SelectControl } from '@wordpress/components';

const API_NAMESPACE = OneUpdateSettings.restUrl + '/oneupdate/v1';
const NONCE = OneUpdateSettings.restNonce;

const SiteTypeSelector = ( { value, setSiteType } ) => (
	<SelectControl
		label={ __( 'Site Type', 'oneupdate' ) }
		value={ value }
		help={ __( 'Choose your site\'s primary purpose. This setting cannot be changed later and affects available features and configurations.', 'oneupdate' ) }
		onChange={ ( v ) => {
			setSiteType( v );
		} }
		options={ [
			{ label: __( 'Select…', 'oneupdate' ), value: '' },
			{ label: __( 'Brand Site', 'oneupdate' ), value: 'brand-site' },
			{ label: __( 'Governing site', 'oneupdate' ), value: 'governing-site' },
		] }
	/>
);

const OneUpdateSettingsPage = () => {
	const [ siteType, setSiteType ] = useState( '' );
	const [ notice, setNotice ] = useState( null );
	const [ isSaving, setIsSaving ] = useState( false );

	useEffect( () => {
		const token = ( NONCE );

		const fetchData = async () => {
			try {
				const [ siteTypeRes ] = await Promise.all( [
					fetch( `${ API_NAMESPACE }/site-type`, {
						headers: { 'Content-Type': 'application/json', 'X-WP-NONCE': token },
					} ),
				] );

				const siteTypeData = await siteTypeRes.json();

				if ( siteTypeData?.site_type ) {
					setSiteType( siteTypeData.site_type );
				}
			} catch {
				setNotice( {
					type: 'error',
					message: __( 'Error fetching site type or Brand sites.', 'oneupdate' ),
				} );
			}
		};

		fetchData();
	}, [] );

	const handleSiteTypeChange = async ( value ) => {
		setSiteType( value );
		const token = ( NONCE );
		setIsSaving( true );

		try {
			const response = await fetch( `${ API_NAMESPACE }/site-type`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-NONCE': token,
				},
				body: JSON.stringify( { site_type: value } ),
			} );

			if ( ! response.ok ) {
				setNotice( {
					type: 'error',
					message: __( 'Error setting site type.', 'oneupdate' ),
				} );
				return;
			}

			const data = await response.json();
			if ( data?.site_type ) {
				setSiteType( data.site_type );

				// redirect user to setup page.
				window.location.href = OneUpdateSettings.setupUrl;
			}
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Error setting site type.', 'oneupdate' ),
			} );
		} finally {
			setIsSaving( false );
		}
	};

	return (
		<>
			<Card>
				<>
					{ notice?.message?.length > 0 &&
					<Notice
						status={ notice?.type ?? 'success' }
						isDismissible={ true }
						onRemove={ () => setNotice( null ) }
					>
						{ notice?.message }
					</Notice>
					}
				</>
				<CardHeader>
					<h2>{ __( 'OneUpdate', 'oneupdate' ) }</h2>
				</CardHeader>
				<CardBody>
					<SiteTypeSelector value={ siteType } setSiteType={ setSiteType } />
					<Button
						variant="primary"
						onClick={ () => handleSiteTypeChange( siteType ) }
						disabled={ isSaving || ! siteType }
						style={ { marginTop: '1.5rem' } }
						className={ isSaving ? 'is-busy' : '' }
					>
						{ __( 'Select current site type', 'oneupdate' ) }
					</Button>
				</CardBody>
			</Card>
		</>
	);
};

// Render to Gutenberg admin page with ID: oneupdate-integrations-page
const target = document.getElementById( 'oneupdate-site-selection-modal' );
if ( target ) {
	const root = createRoot( target );
	root.render( <OneUpdateSettingsPage /> );
}
