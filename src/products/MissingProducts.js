import React, { useState, useEffect, useRef } from "react"
import axios from "axios"
import _ from "lodash"
import { HStack, VStack, FormControl, FormLabel, FormErrorMessage, FormHelperText, RadioGroup, Radio, useCheckboxGroup, Step, StepDescription, StepIcon, StepIndicator, StepNumber, StepSeparator, StepStatus, StepTitle, Stepper, useSteps, Flex, ChakraProvider, extendTheme, Button, Spinner, Alert, AlertIcon, AlertTitle, AlertDescription, Stack, Box, List, ListItem, ListIcon, OrderedList, UnorderedList, Text, Tabs, TabList, TabPanels, Tab, TabPanel, Grid, GridItem, Divider } from "@chakra-ui/react"
import bexioApi from "../helpers/apiCall"

function MissingProducts({ products, setNextStep, setMissingRoutine, syncFields }) {
  const [missingSource, setMissingSource] = useState("missing_source_skip")
  const [missingDestination, setMissingDestination] = useState("missing_destination_keep")

  function changeMissingSource(val) {
    setMissingSource(val)
  }

  function changeMissingDestination(val) {
    setMissingDestination(val)
  }

  return (
    <>
      {products && syncFields && (
        <>
          <FormControl as="fieldset">
            <FormLabel as="legend" fontSize="xl" mb={4}>
              Select how to handle products that are missing either in the <strong>Source ({pvLoonityAppLocalizer.settings["bexio_product_sync_mode"] === "woo" ? "WooCommerce" : "Bexio"})</strong> or the <strong>Destination ({pvLoonityAppLocalizer.settings["bexio_product_sync_mode"] === "bexio" ? "WooCommerce" : "Bexio"})</strong>
            </FormLabel>
            <Box width="100%" backgroundColor="#f4f4f4" border="1px" borderColor="#ccc" p={4}>
              <RadioGroup defaultValue={missingSource} onChange={e => changeMissingSource(e)}>
                <VStack spacing="10px" justifyContent="flex-start" alignItems="flex-start">
                  <Text fontSize="xl" m={0} mb={2}>
                    If a product is missing in {pvLoonityAppLocalizer.settings["bexio_product_sync_mode"] === "bexio" ? "WooCommerce" : "Bexio"}, but exists in {pvLoonityAppLocalizer.settings["bexio_product_sync_mode"] === "woo" ? "WooCommerce" : "Bexio"}:
                  </Text>
                  <Radio value="missing_source_skip">Skip product</Radio>
                  <Radio value="missing_source_create">Create product in {pvLoonityAppLocalizer.settings["bexio_product_sync_mode"] === "bexio" ? "WooCommerce" : "Bexio"}</Radio>
                  <Divider mt={2} mb={2} />
                </VStack>
              </RadioGroup>
              <RadioGroup defaultValue={missingDestination} onChange={e => changeMissingDestination(e)}>
                <VStack spacing="10px" justifyContent="flex-start" alignItems="flex-start">
                  <Text fontSize="xl" m={0} mb={2}>
                    If a product is missing in {pvLoonityAppLocalizer.settings["bexio_product_sync_mode"] === "woo" ? "WooCommerce" : "Bexio"}, but exists in {pvLoonityAppLocalizer.settings["bexio_product_sync_mode"] === "bexio" ? "WooCommerce" : "Bexio"}:
                  </Text>
                  <Radio value="missing_destination_keep">Keep product in {pvLoonityAppLocalizer.settings["bexio_product_sync_mode"] === "bexio" ? "WooCommerce" : "Bexio"}</Radio>
                  <Radio value="missing_destination_delete">Delete product in {pvLoonityAppLocalizer.settings["bexio_product_sync_mode"] === "bexio" ? "WooCommerce" : "Bexio"}</Radio>
                </VStack>
              </RadioGroup>
            </Box>
          </FormControl>

          <HStack gap={4}>
            <Button
              textTransform="uppercase"
              variant="outline"
              colorScheme="blue"
              onClick={() => {
                setNextStep(2)
              }}
            >
              Back
            </Button>
            <Button
              textTransform="uppercase"
              colorScheme="blue"
              onClick={() => {
                setMissingRoutine({
                  missingSource: missingSource,
                  missingDestination: missingDestination
                })
                setNextStep(4)
              }}
            >
              Proceed
            </Button>
          </HStack>
        </>
      )}
    </>
  )
}

export default MissingProducts
