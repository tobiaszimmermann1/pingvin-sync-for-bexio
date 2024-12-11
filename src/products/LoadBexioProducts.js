import React, { useState, useEffect, useRef } from "react"
import axios from "axios"
import _ from "lodash"
import { HStack, Table, Thead, Tbody, Tfoot, Tr, Th, Td, TableCaption, TableContainer, Step, StepDescription, StepIcon, StepIndicator, StepNumber, StepSeparator, StepStatus, StepTitle, Stepper, useSteps, Flex, ChakraProvider, extendTheme, Button, Spinner, Alert, AlertIcon, AlertTitle, AlertDescription, Stack, Box, List, ListItem, ListIcon, OrderedList, UnorderedList, Text, Tabs, TabList, TabPanels, Tab, TabPanel, Grid, GridItem } from "@chakra-ui/react"
import bexioApi from "../helpers/apiCall"

function LoadBexioProducts({ setProducts, setNextStep, loading, setLoading, products, activeProfile, setActiveProfile }) {
  async function getProducts() {
    setLoading(true)
    bexioApi("/article").then(res => {
      setProducts(res)
      setLoading(false)
    })
  }

  function stopSync() {
    let form_data = new FormData()
    form_data.append("action", "wpAction")
    form_data.append("performAction", "stopProductSync")
    form_data.append("payload", "")

    axios
      .post(pvLoonityAppLocalizer.ajaxUrl, form_data)
      .then(function (response) {
        if (response.data.data === "success") {
          setActiveProfile(false)
          setProducts(null)
        }
      })
      .catch(function (error) {
        console.log(error)
      })
  }

  return products ? (
    <>
      <Text fontSize="xl" fontWeight="bold" m={0}>
        {products.length} products found. Check the data for errors and proceed.
      </Text>

      <TableContainer width="100%" backgroundColor="#f4f4f4" border="1px" borderColor="#ccc" p={2} maxHeight="250px" overflowY="auto">
        <Table size="sm" width="100%">
          <Thead>
            <Tr borderBottom="1px">
              <Th>Article # / SKU</Th>
              <Th>Name</Th>
              <Th>Price</Th>
              <Th>Avail. Stock</Th>
            </Tr>
          </Thead>
          <Tbody>
            {products.map(product => (
              <Tr>
                <Td>{product.intern_code}</Td>
                <Td>{product.intern_name}</Td>
                <Td>{product.sale_price}</Td>
                <Td>{parseFloat(product.stock_available_nr)}</Td>
              </Tr>
            ))}
          </Tbody>
        </Table>
      </TableContainer>

      <Button
        textTransform="uppercase"
        colorScheme="blue"
        onClick={() => {
          setNextStep(2)
        }}
      >
        Proceed
      </Button>
    </>
  ) : (
    <>
      <HStack gap={4}>
        {activeProfile && (
          <Button
            textTransform="uppercase"
            colorScheme="red"
            isLoading={loading}
            loadingText="Stopping synchronization..."
            onClick={() => {
              stopSync()
            }}
            isDisabled={loading}
          >
            Stop Synchronization
          </Button>
        )}
        <Button
          textTransform="uppercase"
          colorScheme="blue"
          isLoading={loading}
          loadingText="Fetching products from Bexio..."
          onClick={() => {
            getProducts()
          }}
          isDisabled={loading}
        >
          New Synchronization
        </Button>
      </HStack>
    </>
  )
}

export default LoadBexioProducts
