import React, { useState, useEffect } from 'react';
import { Flex, ChakraProvider, Stack } from '@chakra-ui/react';
import Settings from './Settings';
import SyncSettings from './SyncSettings';
import SyncStatus from './SyncStatus';
import apiCall from './helpers/apiCall';

import theme from './helpers/theme';

function App() {
	const [ enabled, setEnabled ] = useState( null );
	const [ contactEnabled, setContactEnabled ] = useState( null );

	useEffect( () => {
		Promise.all( [
			apiCall( 'GET', 'syncSettings', { type: 'products' } ),
			apiCall( 'GET', 'syncSettings', { type: 'contacts' } ),
		] ).then( ( [ productRes, contactRes ] ) => {
			if ( productRes?.data ) {
				setEnabled( productRes.data.enabled );
			}
			if ( contactRes?.data ) {
				setContactEnabled( contactRes.data.enabled );
			}
		} );
	}, [] );

	return (
		<ChakraProvider theme={ theme }>
			<Stack gap="3">
				<Flex
					flexDirection="column"
					alignItems="center"
					justifyContent="flex-start"
					className="pv_bexio_connector_main"
				>
					<SyncStatus
						enabled={ enabled }
						contactEnabled={ contactEnabled }
					/>
					<SyncSettings setSettingsInterval={ setEnabled } />
					<Settings />
				</Flex>
			</Stack>
		</ChakraProvider>
	);
}

export default App;
