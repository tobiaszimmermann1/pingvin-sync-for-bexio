import React, { useState, useEffect, useRef } from "react"
import _ from "lodash"
import { Flex, ChakraProvider, extendTheme, Button, Spinner, Alert, AlertIcon, AlertTitle, AlertDescription, Stack, Box, List, ListItem, ListIcon, OrderedList, UnorderedList, Text, Tabs, TabList, TabPanels, Tab, TabPanel, Grid, GridItem } from "@chakra-ui/react"
import Settings from "./Settings"
import SyncSettings from "./SyncSettings"
import SyncStatus from "./SyncStatus"
import apiCall from "./helpers/apiCall"

import theme from "./helpers/theme"

function App() {
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
        <Flex flexDirection="column" alignItems="center" justifyContent="flex-start" gap="2" className="pv_loonity_connector_main" borderRadius="base">
          <SyncStatus enabled={enabled} />
          <SyncSettings setSettingsInterval={setEnabled} />
          <Settings />
        </Flex>
      </Stack>
    </ChakraProvider>
  )
}

export default App
