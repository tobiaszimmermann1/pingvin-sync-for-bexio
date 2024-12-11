import React, { useState, useEffect, useRef } from "react"
import axios from "axios"
import _ from "lodash"
import { HStack, VStack, FormControl, FormLabel, FormErrorMessage, FormHelperText, RadioGroup, Radio, useCheckboxGroup, Step, StepDescription, StepIcon, StepIndicator, StepNumber, StepSeparator, StepStatus, StepTitle, Stepper, useSteps, Flex, ChakraProvider, extendTheme, Button, Spinner, Alert, AlertIcon, AlertTitle, AlertDescription, Stack, Box, List, ListItem, ListIcon, OrderedList, UnorderedList, Text, Tabs, TabList, TabPanels, Tab, TabPanel, Grid, GridItem, Divider } from "@chakra-ui/react"
import bexioApi from "../helpers/apiCall"

function Synchronize({ products, setNextStep, setSyncRoutine, syncFields, missingRoutine, setSyncSetting, setActiveProfile, setProducts }) {
  const [syncInterval, setSyncInterval] = useState("60")

  function buildSyncSetting() {
    if (products && syncFields && missingRoutine && syncInterval) {
      let syncProfile = {
        products: products,
        syncFields: syncFields,
        missingRoutine: missingRoutine,
        syncInterval: syncInterval
      }
      setSyncSetting(syncProfile)
      setActiveProfile(syncProfile)
      setNextStep(1)
      setProducts(null)
    } else {
      alert("There was en error building the sync setting...")
    }
  }

  return (
    <>
      {products && syncFields && missingRoutine && (
        <>
          <FormControl as="fieldset">
            <FormLabel as="legend" fontSize="xl" mb={4}>
              Select your synchronization routine
            </FormLabel>
            <Box width="100%" backgroundColor="#f4f4f4" border="1px" borderColor="#ccc" p={4}>
              <RadioGroup defaultValue={"60"} onChange={e => setSyncInterval(e)}>
                <VStack spacing="10px" justifyContent="flex-start" alignItems="flex-start">
                  {/*<Radio value={"0"}>One off: Do not synchronize on an automated schedule</Radio>*/}
                  <Radio value={"60"}>Synchronize every minute</Radio>
                  <Radio value={"600"}>Synchronize every 10 minutes</Radio>
                  <Radio value={"3600"}>Synchronize every hour</Radio>
                  <Radio value={"86400"}>Synchronize 24 hours</Radio>
                </VStack>
              </RadioGroup>
            </Box>
          </FormControl>

          <Alert status="warning">
            <AlertIcon />
            You are about to set up a new product synchronization routine. All previously set up product synchronization routines will be overwritten!
          </Alert>

          <Box width="100%" backgroundColor="#f4f4f4" border="1px" borderColor="#ccc" p={4}>
            <Text fontSize="xl" mt={0}>
              Your settings:
            </Text>
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
                <Text size="m">Sync fields: {syncFields.join(", ")} </Text>
              </ListItem>
              <ListItem>
                <Text size="m">Missing products in Source: {missingRoutine.missingSource === "missing_source_skip" ? "Skip" : "Create"}</Text>
              </ListItem>
              <ListItem>
                <Text size="m">Missing products in Destination: {missingRoutine.missingDestination === "missing_destination_delete" ? "Delete" : "Keep"}</Text>
              </ListItem>
              <ListItem>
                <Text size="m">Sync interval: {syncInterval !== "0" ? syncInterval + " seconds" : "One off"}</Text>
              </ListItem>
              <ListItem>
                <Text size="m">Last synchronization: never</Text>
              </ListItem>
            </UnorderedList>
          </Box>

          <HStack gap={4}>
            <Button
              textTransform="uppercase"
              variant="outline"
              colorScheme="blue"
              onClick={() => {
                setNextStep(3)
              }}
            >
              Back
            </Button>
            <Button
              textTransform="uppercase"
              colorScheme="blue"
              onClick={() => {
                setSyncRoutine({
                  syncInterval: syncInterval
                })
                buildSyncSetting()
              }}
            >
              Synchronize!
            </Button>
          </HStack>
        </>
      )}
    </>
  )
}

export default Synchronize
