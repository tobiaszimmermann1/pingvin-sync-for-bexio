import React from 'react';
import { ChakraProvider, Stack, Flex } from '@chakra-ui/react';
import theme from './helpers/theme';
import ContactsTable from './contacts/ContactsTable';

function Contacts() {
	return (
		<ChakraProvider theme={ theme }>
			<Stack gap="3">
				<Flex
					flexDirection="column"
					alignItems="center"
					justifyContent="flex-start"
					className=""
					mt="2"
				>
					<ContactsTable />
				</Flex>
			</Stack>
		</ChakraProvider>
	);
}

export default Contacts;
