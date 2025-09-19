import DOMPurify from 'dompurify';

const isURL = ( str ) => {
	const pattern = new RegExp(
		'^https?:\\/\\/' +
		'(?:[a-z\\d](?:[a-z\\d-]*[a-z\\d])?\\.)?' +
		'[a-z\\d](?:[a-z\\d-]*[a-z\\d])?\\.' +
		'[a-z]{2,}' +
		'(?::\\d+)?' +
		'(?:\\/[^\\s]*)?' +
		'$', 'i',
	);
	return pattern.test( str );
};

const isValidUrl = ( url ) => {
	try {
		const parsedUrl = new URL( url );
		return isURL( parsedUrl.href );
	} catch ( e ) {
		return false;
	}
};

const PurifyElement = ( item ) => {
	return DOMPurify.sanitize( item, { ALLOWED_TAGS: [] } );
};

export {
	isURL,
	isValidUrl,
	PurifyElement,
};
