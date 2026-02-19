import React, { useState, useEffect } from 'react';
import {
	SkeletonCircle,
	Flex,
	Badge,
	Spinner,
	Text,
	Stack,
	Box,
	Table,
	Tbody,
	Tr,
	Td,
} from '@chakra-ui/react';
import apiCall from './helpers/apiCall';
import { __ } from '@wordpress/i18n';

const STATUS_COLOR = { idle: 'green', running: 'blue', error: 'red' };
const STATUS_LABEL = {
	idle: __( 'Abgeschlossen', 'pingvin-bexio-sync' ),
	running: __( 'Läuft', 'pingvin-bexio-sync' ),
	error: __( 'Fehler', 'pingvin-bexio-sync' ),
};

/**
 * Reusable state table for one sync type
 * @param {Object} root0       - Component props.
 * @param {string} root0.title - The title to display.
 * @param {Object} root0.state - The sync state object.
 */
function StateBox( { title, state } ) {
	return (
		<Box>
			<Box
				p="0px 20px"
				color="pingvin.fontPrimary"
				bg="pingvin.border"
				borderColor="pingvin.border"
				borderWidth="1px"
				borderTopRadius="md"
			>
				<Text fontSize="sm" fontWeight="bold">
					{ title }
				</Text>
			</Box>
			<Box
				p="5px 20px"
				color="pingvin.fontPrimary"
				mt="-1"
				bg="pingvin.white"
				borderColor="pingvin.border"
				borderWidth="1px"
				borderBottomRadius="md"
			>
				{ ! state ? (
					<Text fontSize="sm" fontStyle="italic">
						{ __( 'Keine Daten', 'pingvin-bexio-sync' ) }
					</Text>
				) : (
					<Table size="sm">
						<Tbody>
							<Tr>
								<Td border="0" pl="0">
									<Text fontSize="sm" my="0">
										{ __(
											'Status:',
											'pingvin-bexio-sync'
										) }
									</Text>
								</Td>
								<Td border="0">
									<Badge
										colorScheme={
											STATUS_COLOR[ state.status ] ||
											'gray'
										}
									>
										{ STATUS_LABEL[ state.status ] ||
											state.status }
									</Badge>
									{ state.status === 'running' && (
										<Spinner size="xs" ml="2" />
									) }
								</Td>
							</Tr>
							<Tr>
								<Td border="0" pl="0">
									<Text fontSize="sm" my="0">
										{ __(
											'Abgeschlossen:',
											'pingvin-bexio-sync'
										) }
									</Text>
								</Td>
								<Td border="0">
									<Text fontSize="sm" my="0">
										{ state.last_completed_at_fmt || '—' }
									</Text>
								</Td>
							</Tr>
							<Tr>
								<Td border="0" pl="0">
									<Text fontSize="sm" my="0">
										{ __(
											'Aktualisiert:',
											'pingvin-bexio-sync'
										) }
									</Text>
								</Td>
								<Td border="0">
									<Text fontSize="sm" my="0">
										{ state.total_processed ?? '—' }
									</Text>
								</Td>
							</Tr>
							<Tr>
								<Td border="0" pl="0">
									<Text fontSize="sm" my="0">
										{ __(
											'Übersprungen:',
											'pingvin-bexio-sync'
										) }
									</Text>
								</Td>
								<Td border="0">
									<Text fontSize="sm" my="0">
										{ state.skipped_count ?? '—' }
									</Text>
								</Td>
							</Tr>
							<Tr>
								<Td border="0" pl="0">
									<Text fontSize="sm" my="0">
										{ __(
											'Fehler:',
											'pingvin-bexio-sync'
										) }
									</Text>
								</Td>
								<Td border="0">
									<Text
										fontSize="sm"
										my="0"
										color={
											state.failed_count > 0
												? 'red.500'
												: 'inherit'
										}
									>
										{ state.failed_count ?? '—' }
									</Text>
								</Td>
							</Tr>
							{ state.last_error && (
								<Tr>
									<Td border="0" pl="0">
										<Text fontSize="sm" my="0">
											{ __(
												'Meldung:',
												'pingvin-bexio-sync'
											) }
										</Text>
									</Td>
									<Td border="0">
										<Text
											fontSize="sm"
											my="0"
											color="red.500"
										>
											{ state.last_error }
										</Text>
									</Td>
								</Tr>
							) }
						</Tbody>
					</Table>
				) }
			</Box>
		</Box>
	);
}

function SyncStatus( { enabled, contactEnabled } ) {
	const [ loading, setLoading ] = useState( true );
	const [ fetching, setFetching ] = useState( false );
	const [ productState, setProductState ] = useState( null );
	const [ contactState, setContactState ] = useState( null );

	function fetchState() {
		setFetching( true );
		Promise.all( [
			apiCall( 'GET', 'syncState', { type: 'products' } ),
			apiCall( 'GET', 'syncState', { type: 'contacts' } ),
		] ).then( ( [ productRes, contactRes ] ) => {
			setProductState( productRes?.data || null );
			setContactState( contactRes?.data || null );
			setLoading( false );
			setFetching( false );
		} );
	}

	useEffect( () => {
		fetchState();
	}, [] );

	useEffect( () => {
		if ( ! enabled && ! contactEnabled ) {
			return;
		}
		const interval = setInterval( fetchState, 5000 );
		return () => clearInterval( interval );
	}, [ enabled, contactEnabled ] );

	return (
		<Flex
			flexDirection="row"
			alignItems="center"
			justifyContent="flex-start"
			gap="2"
			borderBottom="1px"
			borderColor="pingvin.border"
			width="100%"
			pb="25px"
			mb="25px"
		>
			<Stack direction="column" width="100%">
				<Stack
					direction="row"
					width="100%"
					justifyContent="space-between"
				>
					<Text fontSize="md" mt="0" fontWeight="bold">
						{ __( 'Status', 'pingvin-bexio-sync' ) }
					</Text>
					{ fetching && (
						<SkeletonCircle
							size="3"
							startColor="green.500"
							endColor="green.200"
							fadeDuration={ 1 }
						/>
					) }
				</Stack>

				{ loading ? (
					<Box>
						<Spinner />
					</Box>
				) : (
					<Stack direction="row" gap="10" flexWrap="wrap">
						<StateBox
							title={ __(
								'Produkte — Letzte Synchronisierung',
								'pingvin-bexio-sync'
							) }
							state={ productState }
						/>
						<StateBox
							title={ __(
								'Kontakte — Letzte Synchronisierung',
								'pingvin-bexio-sync'
							) }
							state={ contactState }
						/>
					</Stack>
				) }
			</Stack>
		</Flex>
	);
}

export default SyncStatus;
