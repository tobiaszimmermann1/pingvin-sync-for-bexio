import React, { useState, useEffect } from 'react';
import { WarningIcon, CheckCircleIcon } from '@chakra-ui/icons';
import {
	Table,
	Thead,
	Tbody,
	Tr,
	Th,
	Td,
	TableContainer,
	Flex,
	Button,
	Spinner,
	Stack,
	Text,
} from '@chakra-ui/react';
import apiCall from './../helpers/apiCall';
import { __ } from '@wordpress/i18n';

function ContactsTable() {
	const [ users, setUsers ] = useState( null );
	const [ loadingTable, setLoadingTable ] = useState( true );
	const [ offset, setOffset ] = useState( 0 );
	const [ total, setTotal ] = useState( 0 );
	const pageSize = 50;

	useEffect( () => {
		let isGetUsers = true;

		setLoadingTable( true );
		apiCall( 'GET', `users`, { offset, page_size: pageSize } )
			.then( ( res ) => {
				if ( ! isGetUsers ) {
					return;
				}
				if ( res && res.data ) {
					setUsers( res.data.items || [] );
					setTotal( res.data.total || 0 );
				} else {
					setUsers( [] );
					setTotal( 0 );
				}
				setLoadingTable( false );
			} )
			.catch( () => {
				setUsers( [] );
				setTotal( 0 );
				setLoadingTable( false );
			} );

		return () => {
			isGetUsers = false;
		};
	}, [ offset ] );

	return (
		<>
			{ loadingTable ? (
				<Flex
					alignItems="center"
					justifyContent="center"
					height="200px"
				>
					<Spinner size="xl" />
				</Flex>
			) : (
				<TableContainer className="pv-table-container">
					<Table
						variant="striped"
						colorScheme="gray"
						size="sm"
						className="pv-table"
					>
						<Thead>
							<Tr>
								<Th>ID</Th>
								<Th>Name</Th>
								<Th>Email</Th>
								<Th>Sync Status</Th>
							</Tr>
						</Thead>
						<Tbody>
							{ users &&
								users.length > 0 &&
								users.map( ( user ) => {
									return (
										<Tr key={ user.ID }>
											<Td>{ user.ID }</Td>
											<Td>{ user.display_name }</Td>
											<Td>{ user.user_email }</Td>
											<Td>
												{ user.pv_bexio_sync ? (
													<Stack>
														<Flex
															flexDirection="row"
															alignItems="flex-start"
															gap="1"
															justifyContent="flex-start"
														>
															<CheckCircleIcon
																color="green"
																mt="1"
															/>
															<Text
																fontSize="sm"
																mt="0"
																mb="0"
																fontStyle="italic"
															>
																{ __(
																	'Zuletzt synchronisiert am',
																	'pingvin-bexio-sync'
																) }{ ' ' }
																{ user.pv_bexio_sync &&
																user
																	.pv_bexio_sync
																	.last_sync
																	? new Date(
																			user.pv_bexio_sync.last_sync
																	  ).toLocaleString()
																	: __(
																			'Unbekannt',
																			'pingvin-bexio-sync'
																	  ) }
															</Text>
														</Flex>
													</Stack>
												) : (
													<WarningIcon color="pingvin.red" />
												) }
											</Td>
										</Tr>
									);
								} ) }
						</Tbody>
					</Table>
				</TableContainer>
			) }
			<Flex mt="3" alignItems="center" justifyContent="space-between">
				<Flex gap="2">
					<Button
						size="sm"
						onClick={ () =>
							setOffset( Math.max( 0, offset - pageSize ) )
						}
						isDisabled={ offset === 0 }
					>
						{ __( 'Zurück', 'pingvin-bexio-sync' ) }
					</Button>
					<Button
						size="sm"
						onClick={ () => setOffset( offset + pageSize ) }
						isDisabled={ offset + pageSize >= total }
					>
						{ __( 'Weiter', 'pingvin-bexio-sync' ) }
					</Button>
				</Flex>
			</Flex>
			<Flex mt="3" alignItems="center" justifyContent="space-between">
				<Text>
					{ __( 'Seite', 'pingvin-bexio-sync' ) }{ ' ' }
					{ Math.floor( offset / pageSize ) + 1 } /{ ' ' }
					{ Math.max( 1, Math.ceil( total / pageSize ) ) }
				</Text>
			</Flex>
		</>
	);
}

export default ContactsTable;
