import React, { useState, useEffect, useRef } from "react"
import axios from "axios"
import _ from "lodash"
import { parse, isValid, format } from "date-fns"
import { Step, StepDescription, StepIcon, StepIndicator, StepNumber, StepSeparator, StepStatus, StepTitle, Stepper, useSteps, Flex, ChakraProvider, extendTheme, Button, Spinner, Alert, AlertIcon, AlertTitle, AlertDescription, Stack, Box, List, ListItem, ListIcon, OrderedList, UnorderedList, Text, Tabs, TabList, TabPanels, Tab, TabPanel, Grid, GridItem } from "@chakra-ui/react"

function Active({ activeProfile }) {
  const [lastSync, setLastSync] = useState(false)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    setLoading(true)
    let form_data = new FormData()
    form_data.append("action", "wpAction")
    form_data.append("performAction", "getLastSync")
    form_data.append("payload", "")

    axios
      .post(pvLoonityAppLocalizer.ajaxUrl, form_data)
      .then(function (response) {
        if (response) {
          const date = response.data.data
          if (date) {
            const stringDate = new Date(date)
            setLastSync(stringDate.toLocaleDateString() + " - " + stringDate.toLocaleTimeString())
          }
        }
        setLoading(false)
      })
      .catch(function (error) {
        console.log(error)
      })
  }, [])

  return (
    <Alert status="success" variant="subtle" flexDirection="row" alignItems="flex-start" justifyContent="flex-start" textAlign="left" p={4}>
      <AlertIcon boxSize="40px" mr={8} />
      <AlertDescription maxWidth="sm">
        <AlertTitle mt={0} mb={1} fontSize="lg">
          Product synchronization is currently <u>active</u>!
        </AlertTitle>
        <UnorderedList pl={2}>
          <ListItem>
            <Text size="m">
              Sync direction: {pvLoonityAppLocalizer.settings["bexio_product_sync_mode"] === "woo" ? "WooCommerce" : "Bexio"} to {pvLoonityAppLocalizer.settings["bexio_product_sync_mode"] === "woo" ? "Bexio" : "WooCommerce"}
            </Text>
          </ListItem>
          <ListItem>
            <Text size="m">Sync key: Article number</Text>
          </ListItem>
          <ListItem>
            <Text size="m">Sync fields: {activeProfile.syncFields.join(", ")} </Text>
          </ListItem>
          <ListItem>
            <Text size="m">Missing products in Source: {activeProfile.missingRoutine.missingSource === "missing_source_skip" ? "Skip" : "Create"}</Text>
          </ListItem>
          <ListItem>
            <Text size="m">Missing products in Destination: {activeProfile.missingRoutine.missingDestination === "missing_destination_delete" ? "Delete" : "Keep"}</Text>
          </ListItem>
          <ListItem>
            <Text size="m">Sync interval: {activeProfile.syncInterval !== "0" ? activeProfile.syncInterval + " seconds" : "One off"}</Text>
          </ListItem>
          <ListItem>
            <Text size="m">Last synchronization: {lastSync ? lastSync : loading ? <Spinner size="xs" /> : "never"}</Text>
          </ListItem>
        </UnorderedList>
      </AlertDescription>
    </Alert>
  )
}

export default Active
