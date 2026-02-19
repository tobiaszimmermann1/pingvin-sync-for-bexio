import React, { useState, useEffect } from 'react';
import {
	Flex,
	Button,
	Spinner,
	Text,
	Stack,
	Box,
	Select,
} from '@chakra-ui/react';
import { CheckCircleIcon, WarningIcon } from '@chakra-ui/icons';
import apiCall from './helpers/apiCall';
import { __ } from '@wordpress/i18n';

function getLogLevelColor( levelName ) {
	if ( levelName === 'ERROR' || levelName === 'CRITICAL' ) {
		return 'red.500';
	}
	if ( levelName === 'WARNING' ) {
		return 'orange.500';
	}
	if ( levelName === 'DEBUG' ) {
		return 'gray.400';
	}
	return 'blue.500';
}

function Settings() {
	const [ resultConnection, setResultConnection ] = useState( null );
	const [ loading, setLaoding ] = useState( false );

	const [ logFiles, setLogFiles ] = useState( [] );
	const [ selectedFile, setSelectedFile ] = useState( '' );
	const [ logLines, setLogLines ] = useState( [] );
	const [ logDownloadUrl, setLogDownloadUrl ] = useState( '' );
	const [ logLoading, setLogLoading ] = useState( false );

	useEffect( () => {
		apiCall( 'GET', 'logs' ).then( ( res ) => {
			const files = res?.data?.files || [];
			setLogFiles( files );
			if ( files.length > 0 ) {
				setSelectedFile( files[ 0 ] );
			}
		} );
	}, [] );

	useEffect( () => {
		if ( ! selectedFile ) {
			return;
		}
		setLogLoading( true );
		apiCall( 'GET', 'logs', { file: selectedFile } ).then( ( res ) => {
			setLogLines( res?.data?.lines || [] );
			setLogDownloadUrl( res?.data?.download_url || '' );
			setLogLoading( false );
		} );
	}, [ selectedFile ] );

	function testApi() {
		apiCall( 'GET', 'article' ).then( ( res ) => {
			if ( res.data.status === 200 ) {
				setResultConnection( {
					type: 'success',
					data: res.data.result,
				} );
			} else {
				setResultConnection( {
					type: 'error',
					data: res.data.status,
				} );
			}
		} );
	}

	useEffect( () => {
		if ( resultConnection !== null ) {
			setLaoding( false );
		}
	}, [ resultConnection ] );

	return (
		<>
			<Flex
				flexDirection="row"
				alignItems="center"
				justifyContent="flex-start"
				gap="2"
				width="100%"
				pb="25px"
				mb="25px"
				borderBottom="1px"
				borderColor="pingvin.border"
			>
				<Stack>
					<Text fontSize="md" mt="0" fontWeight="bold">
						{ __(
							'Bexio API Verbindung prüfen',
							'pingvin-bexio-sync'
						) }
					</Text>
					<Button
						width="200px"
						color="pingvin.white"
						backgroundColor="pingvin.primary"
						_hover={ {
							backgroundColor: 'pingvin.primaryDark',
						} }
						onClick={ () => {
							testApi();
							setLaoding( true );
						} }
						isDisabled={ loading }
						size="sm"
					>
						<>
							{ loading ? (
								<Box>
									<Spinner />
								</Box>
							) : (
								__( 'Verbindung prüfen', 'pingvin-bexio-sync' )
							) }
						</>
					</Button>

					{ ! loading && (
						<Stack direction="horizontal">
							{ resultConnection &&
								resultConnection.type === 'success' && (
									<Box width="50%">
										<Box
											p="0px 20px"
											color="pingvin.fontPrimary"
											mt="4"
											bg="pingvin.border"
											borderColor="pingvin.border"
											borderWidth="1px"
											borderTopRadius="md"
										>
											<Text
												fontSize="sm"
												fontWeight="bold"
											>
												{ __(
													'Bexio API Verbindung',
													'pingvin-bexio-sync'
												) }
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
											<Text
												fontSize="sm"
												fontWeight="regular"
												color="success"
											>
												<CheckCircleIcon mr="10px" />
												{ __(
													'Erfolgreiche Verbindung zur Bexio API',
													'pingvin-bexio-sync'
												) }
											</Text>
											<Text
												fontSize="sm"
												fontWeight="regular"
												color="pingvin.fontPrimary"
											>
												{ __(
													'Das heisst, dass die Authentifizierung funktioniert und WooCommerce eine Verbindung zur Bexio API herstellen kann.',
													'pingvin-bexio-sync'
												) }
											</Text>
										</Box>
									</Box>
								) }

							{ resultConnection &&
								resultConnection.type === 'error' && (
									<Box width="50%">
										<Box
											p="0px 20px"
											color="pingvin.fontPrimary"
											mt="4"
											bg="pingvin.border"
											borderColor="pingvin.border"
											borderWidth="1px"
											borderTopRadius="md"
										>
											<Text
												fontSize="sm"
												fontWeight="bold"
											>
												{ __(
													'Bexio API Verbindung',
													'pingvin-bexio-sync'
												) }
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
											<Stack>
												<Box>
													<Text
														fontSize="sm"
														fontWeight="regular"
														color="error"
													>
														<WarningIcon
															mr="10px"
															color="error"
														/>
														{ __(
															'Keine Verbindung zur Bexio API möglich',
															'pingvin-bexio-sync'
														) }
													</Text>
													<Text
														fontSize="sm"
														fontWeight="regular"
														color="pingvin.fontPrimary"
													>
														{ __(
															'Setze die Authentifizierungseinstellungen zurück und verbinde WooCommerce neu mit Bexio.',
															'pingvin-bexio-sync'
														) }
													</Text>
													<Text fontSize="sm">
														<i>
															{ __(
																'Fehler:',
																'pingvin-bexio-sync'
															) }{ ' ' }
															{
																resultConnection.data
															}
														</i>
													</Text>
												</Box>
											</Stack>
										</Box>
									</Box>
								) }
						</Stack>
					) }
				</Stack>
			</Flex>
			<Flex
				flexDirection="row"
				alignItems="flex-start"
				justifyContent="flex-start"
				gap="2"
				width="100%"
				pb="0px"
				mb="25px"
			>
				<Stack width="100%">
					<Text fontSize="md" mt="0" fontWeight="bold">
						{ __( 'Logs', 'pingvin-bexio-sync' ) }
					</Text>

					<Flex gap="3" alignItems="center">
						<Select
							size="sm"
							width="280px"
							value={ selectedFile }
							onChange={ ( e ) =>
								setSelectedFile( e.target.value )
							}
						>
							{ logFiles.map( ( f ) => (
								<option key={ f } value={ f }>
									{ f }
								</option>
							) ) }
						</Select>
					</Flex>

					{ logLoading ? (
						<Spinner size="sm" mt="2" />
					) : (
						<Box
							fontFamily="mono"
							fontSize="xs"
							bg="gray.50"
							borderColor="pingvin.border"
							borderWidth="1px"
							borderRadius="md"
							p="3"
							maxH="400px"
							overflowY="auto"
						>
							{ logLines.length === 0 && (
								<Text color="gray.400">No log entries.</Text>
							) }
							{ logLines.map( ( line, i ) => (
								<Flex
									key={ i }
									gap="2"
									borderBottom="1px solid"
									borderColor="gray.100"
									py="0"
									my="1"
									alignItems="baseline"
								>
									<Text
										color="gray.400"
										flexShrink={ 0 }
										minW="130px"
										fontSize="xs"
										fontFamily="monospace"
										my="1"
									>
										{ line.datetime
											? line.datetime
													.substring( 0, 19 )
													.replace( 'T', ' ' )
											: '' }
									</Text>
									<Text
										flexShrink={ 0 }
										minW="60px"
										fontWeight="bold"
										color={ getLogLevelColor(
											line.level_name
										) }
										my="1"
									>
										{ line.level_name }
									</Text>
									<Text my="1">{ line.message }</Text>
								</Flex>
							) ) }
						</Box>
					) }
					{ logDownloadUrl && (
						<a
							href={ logDownloadUrl }
							download
							style={ { fontSize: '13px' } }
						>
							{ __( 'Download', 'pingvin-bexio-sync' ) }
						</a>
					) }
				</Stack>
			</Flex>
		</>
	);
}

export default Settings;
