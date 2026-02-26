import axios from 'axios';

/* global pvbexioAppLocalizer */

const apiCall = async ( method, endpoint, params = null ) => {
	try {
		if ( method === 'GET' ) {
			const response = await axios.get(
				`${ pvbexioAppLocalizer.bexioApiUrl }/${ endpoint }`,
				{
					headers: {
						'X-WP-Nonce': pvbexioAppLocalizer.nonce,
					},
					params: params || undefined,
				}
			);

			return response;
		}

		if ( method === 'POST' ) {
			const response = await axios.post(
				`${ pvbexioAppLocalizer.bexioApiUrl }/${ endpoint }`,
				params,
				{
					headers: {
						'X-WP-Nonce': pvbexioAppLocalizer.nonce,
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
