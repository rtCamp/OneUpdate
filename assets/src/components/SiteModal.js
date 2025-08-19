import { useState } from '@wordpress/element';
import {
	Modal,
	TextControl,
	TextareaControl,
	Button,
	Notice,
	ComboboxControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { isValidUrl } from '../js/utils';

const SiteModal = ( { formData, setFormData, onSubmit, onClose, editing, allGitHubRepos } ) => {
	const [ errors, setErrors ] = useState( {
		siteName: '',
		siteUrl: '',
		publicKey: '',
		message: '',
	} );
	const [ showNotice, setShowNotice ] = useState( false );
	const [ isProcessing, setIsProcessing ] = useState( false ); // New state for processing

	const handleSubmit = async () => {
		// Validate inputs
		let siteUrlError = '';
		if ( ! formData.siteUrl.trim() ) {
			siteUrlError = __( 'Site URL is required.', 'oneupdate' );
		} else if ( ! isValidUrl( formData.siteUrl ) ) {
			siteUrlError = __( 'Enter a valid URL (must start with http or https).', 'oneupdate' );
		}

		const newErrors = {
			siteName: ! formData.siteName.trim() ? __( 'Site Name is required.', 'oneupdate' ) : '',
			siteUrl: siteUrlError,
			publicKey: ! formData.publicKey.trim() ? __( 'Public Key is required.', 'oneupdate' ) : '',
			message: '',
		};

		// make sure site name is under 20 characters
		if ( formData.siteName.length > 20 ) {
			newErrors.siteName = __( 'Site Name must be under 20 characters.', 'oneupdate' );
		}

		setErrors( newErrors );
		const hasErrors = Object.values( newErrors ).some( ( err ) => err );

		if ( hasErrors ) {
			setShowNotice( true );
			return;
		}

		// Start processing
		setIsProcessing( true );
		setShowNotice( false );

		try {
			// Perform health-check
			const healthCheck = await fetch(
				`${ formData.siteUrl }/wp-json/oneupdate/v1/health-check`,
				{
					method: 'GET',
					headers: {
						'Content-Type': 'application/json',
						'X-OneUpdate-Plugins-Token': formData.publicKey,
					},
				},
			);

			const healthCheckData = await healthCheck.json();
			if ( ! healthCheckData.success ) {
				setErrors( {
					...newErrors,
					message: __( 'Health check failed. Please ensure the site is accessible and the public key is correct.', 'oneupdate' ),
				} );
				setShowNotice( true );
				setIsProcessing( false );
				return;
			}

			setShowNotice( false );
			const submitResponse = await onSubmit();

			if ( ! submitResponse.ok ) {
				const errorData = await submitResponse.json();
				setErrors( {
					...newErrors,
					message: errorData.message || __( 'An error occurred while saving the site. Please try again.', 'oneupdate' ),
				} );
				setShowNotice( true );
			}
			if ( submitResponse?.data?.status === 400 ) {
				setErrors( {
					...newErrors,
					message: submitResponse?.message || __( 'An error occurred while saving the site. Please try again.', 'oneupdate' ),
				} );
				setShowNotice( true );
			}
		} catch ( error ) {
			setErrors( {
				...newErrors,
				message: __( 'An unexpected error occurred. Please try again.', 'oneupdate' ),
			} );
			setShowNotice( true );
			setIsProcessing( false );
			return;
		}

		setIsProcessing( false );
	};

	return (
		<Modal
			title={ editing ? __( 'Edit Brand Site', 'oneupdate' ) : __( 'Add Brand Site', 'oneupdate' ) }
			onRequestClose={ onClose }
			size="medium"
		>
			{ showNotice && (
				<Notice
					status="error"
					isDismissible={ true }
					onRemove={ () => setShowNotice( false ) }
				>
					{ errors.message || errors.siteName || errors.siteUrl || errors.publicKey }
				</Notice>
			) }

			<TextControl
				label={ __( 'Site Name*', 'oneupdate' ) }
				value={ formData.siteName }
				onChange={ ( value ) => setFormData( { ...formData, siteName: value } ) }
				error={ errors.siteName }
				help={ __( 'This is the name of the site that will be registered.', 'oneupdate' ) }
			/>
			<TextControl
				label={ __( 'Site URL*', 'oneupdate' ) }
				value={ formData.siteUrl }
				onChange={ ( value ) => setFormData( { ...formData, siteUrl: value } ) }
				error={ errors.siteUrl }
				help={ __( 'It must start with http or https and end with /, like: https://oneupdate.com/', 'oneupdate' ) }
			/>
			<ComboboxControl
				label={ __( 'GitHub Repository*', 'oneupdate' ) }
				value={ formData.githubRepo }
				onChange={ ( value ) => setFormData( { ...formData, githubRepo: value } ) }
				options={ allGitHubRepos.map( ( repo ) => ( { label: repo.slug, value: repo.slug } ) ) }
				placeholder={ __( 'Select a repository', 'oneupdate' ) }
				__nextHasNoMarginBottom={ false }
				help={ __( 'Select the GitHub repository associated with this site.', 'oneupdate' ) }
			/>
			<TextareaControl
				label={ __( 'Public Key*', 'oneupdate' ) }
				value={ formData.publicKey }
				onChange={ ( value ) => setFormData( { ...formData, publicKey: value } ) }
				error={ errors.publicKey }
				help={ __( 'This is the public key that will be used to authenticate the site for OneUpdate.', 'oneupdate' ) }
			/>

			<Button
				isPrimary
				onClick={ handleSubmit }
				className={ isProcessing ? 'is-busy' : '' }
				disabled={ isProcessing || ! formData.siteName || ! formData.siteUrl || ! formData.publicKey || ! formData.githubRepo }
				style={ { marginTop: '12px' } }
			>
				{ (
					editing ? __( 'Update Site', 'oneupdate' ) : __( 'Add Site', 'oneupdate' )
				) }
			</Button>
		</Modal>
	);
};

export default SiteModal;
