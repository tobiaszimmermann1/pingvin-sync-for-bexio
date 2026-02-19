import axios from 'axios';

/* global pvProductSyncAppLocalizer */

const apiCall = async ( method, endpoint, params = null ) => {
	try {
		if ( method === 'GET' ) {
			const response = await axios.get(
				`${ pvProductSyncAppLocalizer.bexioApiUrl }/${ endpoint }`,
				{
					headers: {
						'X-WP-Nonce': pvProductSyncAppLocalizer.nonce,
					},
					params: params || undefined,
				}
			);

			return response;
		}

		if ( method === 'POST' ) {
			const response = await axios.post(
				`${ pvProductSyncAppLocalizer.bexioApiUrl }/${ endpoint }`,
				params,
				{
					headers: {
						'X-WP-Nonce': pvProductSyncAppLocalizer.nonce,
					},
				}
			);

			return response;
		}
	} catch ( error ) {
		// eslint-disable-next-line no-console
		console.error( 'API call error:', error );
		return error;
	}
};

export default apiCall;
