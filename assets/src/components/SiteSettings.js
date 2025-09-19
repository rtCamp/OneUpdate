import { useEffect, useState, useCallback } from '@wordpress/element';
import { TextareaControl, Button, Card, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const API_NAMESPACE = OneUpdateSettings.restUrl + '/oneupdate/v1';
const NONCE = OneUpdateSettings.restNonce;

const SiteSettings = () => {
	const [ apiKey, setApiKey ] = useState( '' );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const fetchApiKey = useCallback( async () => {
		try {
			setIsLoading( true );
			const response = await fetch( API_NAMESPACE + '/secret-key', {
				method: 'GET',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': NONCE,
				},
			} );
			if ( ! response.ok ) {
				throw new Error( 'Network response was not ok' );
			}
			const data = await response.json();
			setApiKey( data?.secret_key || '' );
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'Failed to fetch API key. Please try again later.', 'oneupdate' ),
			} );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	// regenerateApiKey from rest API.
	const regenerateApiKey = useCallback( async () => {
		try {
			const response = await fetch( API_NAMESPACE + '/secret-key', {
				method: 'POST',
				headers: {
					'X-WP-Nonce': NONCE,
				},
			} );
			if ( ! response.ok ) {
				throw new Error( 'Network response was not ok' );
			}
			const data = await response.json();
			if ( data?.secret_key ) {
				setApiKey( data.secret_key );
				setNotice( {
					type: 'warning',
					message: __( 'API key regenerated successfully. Please update your old key with this newly generated key to make sure plugin works properly.', 'oneupdate' ),
				} );
			} else {
				setNotice( {
					type: 'error',
					message: __( 'Failed to regenerate API key. Please try again later.', 'oneupdate' ),
				} );
			}
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'Error regenerating API key. Please try again later.', 'oneupdate' ),
			} );
		}
	}, [] );

	useEffect( () => {
		fetchApiKey();
	}, [ fetchApiKey ] );

	if ( isLoading ) {
		return <Spinner style={ { width: '40px', height: '40px' } } />;
	}

	return (
		<Card className="brand-site-settings"
			style={ { padding: '20px', marginTop: '30px' } }
		>
			{ notice && (
				<Notice
					status={ notice.type }
					isDismissible={ true }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }
			<div
				style={ {
					marginTop: '1rem',
					marginBottom: '1rem',
				} }
			>
				<TextareaControl
					label={ __( 'API Key', 'oneupdate' ) }
					value={ apiKey }
					disabled={ true }
					help={ __( 'This key is used for secure communication with the Governing site.', 'oneupdate' ) }
				/>
			</div>
			{ /* Copy to clipboard button */ }
			<Button
				variant="primary"
				onClick={ () => {
					navigator?.clipboard?.writeText( apiKey )
						.then( () => {
							setNotice( {
								type: 'success',
								message: __( 'API key copied to clipboard.', 'oneupdate' ),
							} );
						} )
						.catch( ( error ) => {
							setNotice( {
								type: 'error',
								message: __( 'Failed to copy API key. Please try again.', 'oneupdate' ) + ' ' + error,
							} );
						} );
				} }
			>
				{ __( 'Copy API Key', 'oneupdate' ) }
			</Button>
			{ /* Regenerate key button */ }
			<Button
				variant="secondary"
				onClick={ regenerateApiKey }
				style={ { marginLeft: '10px' } }
			>
				{ __( 'Regenerate API Key', 'oneupdate' ) }
			</Button>
		</Card>
	);
};

export default SiteSettings;
