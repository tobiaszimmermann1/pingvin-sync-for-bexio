import React, { useState, useEffect, useRef } from "react"
import _ from "lodash"
import { Flex, ChakraProvider, extendTheme, Button, Spinner, Alert, AlertIcon, AlertTitle, AlertDescription, Stack, Box, List, ListItem, ListIcon, OrderedList, UnorderedList, Text, Tabs, TabList, TabPanels, Tab, TabPanel, Grid, GridItem } from "@chakra-ui/react"
import Settings from "./Settings"
import SyncSettings from "./SyncSettings"
import SyncStatus from "./SyncStatus"
import apiCall from "./helpers/apiCall"
import theme from "./helpers/theme"
import ContactsTable from "./contacts/ContactsTable"

function Contacts() {
  const [enabled, setEnabled] = useState(null)

  useEffect(() => {
    let isGetOptions = true

    apiCall("GET", `syncSettings`).then(res => {
      if (res.data) {
        setEnabled(JSON.parse(res.data).enabled)
      }
    })

    return () => {
      isGetOptions = false
    }
  }, [])

  return (
    <ChakraProvider theme={theme}>
      <Stack gap="3">
        <Flex flexDirection="column" alignItems="center" justifyContent="flex-start" className="" mt="2">
          <ContactsTable />
        </Flex>
      </Stack>
    </ChakraProvider>
  )
}

export default Contacts
