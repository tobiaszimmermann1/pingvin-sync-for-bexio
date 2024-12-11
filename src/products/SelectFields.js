import React, { useState, useEffect, useRef } from "react"
import axios from "axios"
import _ from "lodash"
import { VStack, FormControl, FormLabel, FormErrorMessage, FormHelperText, CheckboxGroup, Checkbox, useCheckboxGroup, Step, StepDescription, StepIcon, StepIndicator, StepNumber, StepSeparator, StepStatus, StepTitle, Stepper, useSteps, Flex, ChakraProvider, extendTheme, Button, Spinner, Alert, AlertIcon, AlertTitle, AlertDescription, Stack, Box, List, ListItem, ListIcon, OrderedList, UnorderedList, Text, Tabs, TabList, TabPanels, Tab, TabPanel, Grid, GridItem, HStack } from "@chakra-ui/react"
import bexioApi from "../helpers/apiCall"

function SelectFields({ products, setNextStep, setSyncFields }) {
  const allValues = ["sku", "name", "price", "stock", "vat", "weight", "height", "width"]
  const [value, setValue] = useState(["sku", "name", "price", "stock", "vat", "weight", "height", "width"])

  function setCheckboxValues(val, checked) {
    let oldValues = value
    let newValues = []

    if (checked && !oldValues.includes(val)) {
      newValues = oldValues
      newValues.push(val)
    } else {
      allValues.forEach(allValue => {
        if (oldValues.includes(allValue) && allValue !== val) newValues.push(allValue)
      })
    }

    setValue(newValues)
  }

  useEffect(() => {
    setSyncFields(value)
  }, [value])

  return (
    <>
      {products && (
        <>
          <FormControl as="fieldset">
            <FormLabel as="legend" fontSize="xl" mb={4}>
              Select the fields you wish to synchronize {pvLoonityAppLocalizer.settings["bexio_product_sync_mode"] === "bexio" ? "from Bexio to WooCommerce" : "from WooCommerce to Bexio"}.
            </FormLabel>
            <Box width="100%" backgroundColor="#f4f4f4" border="1px" borderColor="#ccc" p={4}>
              <CheckboxGroup defaultValue={value}>
                <VStack spacing="10px" justifyContent="flex-start" alignItems="flex-start">
                  <Checkbox value="sku" isDisabled>
                    Article # / SKU (used to synchronize products)
                  </Checkbox>
                  <Checkbox value="name" onChange={e => setCheckboxValues(e.target.value, e.target.checked)}>
                    Product name
                  </Checkbox>
                  <Checkbox value="price" onChange={e => setCheckboxValues(e.target.value, e.target.checked)}>
                    Price
                  </Checkbox>
                  <Checkbox value="stock" onChange={e => setCheckboxValues(e.target.value, e.target.checked)}>
                    Avail. Stock
                  </Checkbox>
                  <Checkbox value="vat" onChange={e => setCheckboxValues(e.target.value, e.target.checked)}>
                    VAT
                  </Checkbox>
                  <Checkbox value="weight" onChange={e => setCheckboxValues(e.target.value, e.target.checked)}>
                    Weight
                  </Checkbox>
                  <Checkbox value="height" onChange={e => setCheckboxValues(e.target.value, e.target.checked)}>
                    Height
                  </Checkbox>
                  <Checkbox value="width" onChange={e => setCheckboxValues(e.target.value, e.target.checked)}>
                    Width
                  </Checkbox>
                </VStack>
              </CheckboxGroup>
            </Box>
          </FormControl>

          <HStack gap={4}>
            <Button
              textTransform="uppercase"
              variant="outline"
              colorScheme="blue"
              onClick={() => {
                setNextStep(1)
              }}
            >
              Back
            </Button>
            <Button
              textTransform="uppercase"
              colorScheme="blue"
              onClick={() => {
                setNextStep(3)
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

export default SelectFields
