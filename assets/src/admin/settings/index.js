/* eslint-disable react-hooks/exhaustive-deps */

import { useState, useEffect, useCallback, createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Snackbar } from '@wordpress/components';
import SiteTable from '../../components/SiteTable';
import SiteModal from '../../components/SiteModal';
import GitHubRepoToken from '../../components/GitHubRepoToken';
import S3Credentials from '../../components/S3Credentials';
import SiteSettings from '../../components/SiteSettings';

const API_NAMESPACE = OneUpdateSettings.restUrl + '/oneupdate/v1';
const NONCE = OneUpdateSettings.restNonce;

const OneUpdateSettingsPage = () => {
	const [ siteType, setSiteType ] = useState( '' );
	const [ showModal, setShowModal ] = useState( false );
	const [ editingIndex, setEditingIndex ] = useState( null );
	const [ sites, setSites ] = useState( [] );
	const [ formData, setFormData ] = useState( { siteName: '', siteUrl: '', apiKey: '' } );
	const [ notice, setNotice ] = useState( {
		type: 'success',
		message: '',
	} );

	useEffect( () => {
		const token = ( NONCE );

		const fetchData = async () => {
			try {
				const [ siteTypeRes, sitesRes ] = await Promise.all( [
					fetch( `${ API_NAMESPACE }/site-type`, {
						headers: { 'Content-Type': 'application/json', 'X-WP-NONCE': token },
					} ),
					fetch( `${ API_NAMESPACE }/shared-sites`, {
						headers: { 'Content-Type': 'application/json', 'X-WP-NONCE': token },
					} ),
				] );

				const siteTypeData = await siteTypeRes.json();
				const sitesData = await sitesRes.json();

				if ( siteTypeData?.site_type ) {
					setSiteType( siteTypeData.site_type );
				}
				if ( Array.isArray( sitesData?.shared_sites ) ) {
					setSites( sitesData?.shared_sites );
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

	useEffect( () => {
		if ( siteType === 'governing-site' && sites.length > 0 ) {
			document.body.classList.remove( 'oneupdate-missing-brand-sites' );
		}
	}, [ sites, siteType ] );

	const [ allGitHubRepos, setAllGitHubRepos ] = useState( [] );
	const fetchAllAvailableGitHubRepos = useCallback( async () => {
		const response = await fetch(
			`${ API_NAMESPACE }/github-repos`,
			{
				method: 'GET',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-NONCE': NONCE,
				},
			},
		);
		const data = await response.json();
		if ( data?.repos ) {
			setAllGitHubRepos( data.repos );
		} else {
			setAllGitHubRepos( [] );
		}
	}, [] );

	useEffect( () => {
		if ( siteType === 'governing-site' ) {
			fetchAllAvailableGitHubRepos();
		}
	}, [ siteType ] );

	const handleFormSubmit = async () => {
		const updated = editingIndex !== null
			? sites.map( ( item, i ) => ( i === editingIndex ? formData : item ) )
			: [ ...sites, formData ];

		const token = ( NONCE );
		try {
			const response = await fetch( `${ API_NAMESPACE }/shared-sites`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-NONCE': token,
				},
				body: JSON.stringify( { sites_data: updated } ),
			} );
			if ( ! response.ok ) {
				console.error( 'Error saving Brand site:', response.statusText ); // eslint-disable-line no-console
				return response;
			}

			if ( sites.length === 0 ) {
				window.location.reload();
			}

			setSites( updated );
			setNotice( {
				type: 'success',
				message: __( 'Brand Site saved successfully.', 'oneupdate' ),
			} );
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Error saving Brand site. Please try again later.', 'oneupdate' ),
			} );
		}

		setFormData( { siteName: '', siteUrl: '', apiKey: '' } );
		setShowModal( false );
		setEditingIndex( null );
	};

	const handleDelete = async ( index ) => {
		const updated = sites.filter( ( _, i ) => i !== index );
		const token = ( NONCE );

		try {
			const response = await fetch( `${ API_NAMESPACE }/shared-sites`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-NONCE': token,
				},
				body: JSON.stringify( { sites_data: updated } ),
			} );
			if ( ! response.ok ) {
				setNotice( {
					type: 'error',
					message: __( 'Failed to delete Brand site. Please try again.', 'oneupdate' ),
				} );
				return;
			}
			setNotice( {
				type: 'success',
				message: __( 'Brand Site deleted successfully.', 'oneupdate' ),
			} );
			setSites( updated );
			if ( updated.length === 0 ) {
				window.location.reload();
			} else {
				document.body.classList.remove( 'oneupdate-missing-brand-sites' );
			}
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Error deleting Brand site. Please try again later.', 'oneupdate' ),
			} );
		}
	};

	return (
		<>
			<>
				{ notice?.message?.length > 0 &&
					<Snackbar
						status={ notice?.type ?? 'success' }
						isDismissible={ true }
						onRemove={ () => setNotice( null ) }
						className={ notice?.type === 'error' ? 'oneupdate-error-notice' : 'oneupdate-success-notice' }
					>
						{ notice?.message }
					</Snackbar>
				}
			</>

			{
				siteType === 'brand-site' && (
					<SiteSettings />
				)
			}

			{ siteType === 'governing-site' && (
				<SiteTable sites={ sites } onEdit={ setEditingIndex } onDelete={ handleDelete } setFormData={ setFormData } setShowModal={ setShowModal } />
			) }

			{ siteType === 'governing-site' && (
				<GitHubRepoToken setNotice={ setNotice } fetchAllAvailableGitHubRepos={ fetchAllAvailableGitHubRepos } />
			) }

			{ siteType === 'governing-site' && (
				<S3Credentials setNotice={ setNotice } />
			) }

			{ showModal && (
				<SiteModal
					formData={ formData }
					setFormData={ setFormData }
					onSubmit={ handleFormSubmit }
					onClose={ () => {
						setShowModal( false );
						setEditingIndex( null );
						setFormData( { siteName: '', siteUrl: '', apiKey: '' } );
					} }
					editing={ editingIndex !== null }
					allGitHubRepos={ allGitHubRepos }
				/>
			) }
		</>
	);
};

// Render to Gutenberg admin page with ID: oneupdate-settings-page
const target = document.getElementById( 'oneupdate-settings-page' );
if ( target ) {
	const root = createRoot( target );
	root.render( <OneUpdateSettingsPage /> );
}

/* eslint-enable react-hooks/exhaustive-deps */
