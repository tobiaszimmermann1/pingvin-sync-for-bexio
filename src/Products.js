import React, { useState, useEffect, useRef } from "react"
import _ from "lodash"
import { WarningIcon, CheckCircleIcon } from "@chakra-ui/icons"
import { Table, Thead, Tbody, Tfoot, Tr, Th, Td, TableCaption, TableContainer, Badge, Flex, ChakraProvider, extendTheme, Button, Spinner, Alert, AlertIcon, AlertTitle, AlertDescription, Stack, Box, List, ListItem, ListIcon, OrderedList, UnorderedList, Text, Tabs, TabList, TabPanels, Tab, TabPanel, Grid, GridItem } from "@chakra-ui/react"
import apiCall from "./helpers/apiCall"
import { __ } from "@wordpress/i18n"
import theme from "./helpers/theme"

function Products() {
  const [products, setProducts] = useState(null)
  const [loadingTable, setLoadingTable] = useState(true)
  const [offset, setOffset] = useState(0)
  const [total, setTotal] = useState(0)
  const pageSize = 50

  useEffect(() => {
    let isGetProducts = true

    setLoadingTable(true)
    apiCall("GET", `products`, { offset: offset, page_size: pageSize })
      .then(res => {
        if (!isGetProducts) return
        if (res && res.data) {
          setProducts(res.data.items || [])
          setTotal(res.data.total || 0)
        } else {
          setProducts([])
          setTotal(0)
        }
        setLoadingTable(false)
      })
      .catch(err => {
        setProducts([])
        setTotal(0)
        setLoadingTable(false)
      })

    return () => {
      isGetProducts = false
    }
  }, [])

  // refetch when offset changes
  useEffect(() => {
    let isGetProducts = true
    setLoadingTable(true)
    apiCall("GET", `products`, { offset: offset, page_size: pageSize })
      .then(res => {
        if (!isGetProducts) return
        if (res && res.data) {
          setProducts(res.data.items || [])
          setTotal(res.data.total || 0)
        } else {
          setProducts([])
          setTotal(0)
        }
        setLoadingTable(false)
      })
      .catch(() => {
        setProducts([])
        setTotal(0)
        setLoadingTable(false)
      })

    return () => {
      isGetProducts = false
    }
  }, [offset])

  return (
    <>
      <ChakraProvider theme={theme}>
        <Stack gap="3">
          <Flex flexDirection="column" alignItems="center" justifyContent="flex-start" className="" mt="2">
            {loadingTable ? (
              <Flex alignItems="center" justifyContent="center" height="200px">
                <Spinner size="xl" />
              </Flex>
            ) : (
              <TableContainer className="pv-table-container">
                <Table variant="striped" colorScheme="gray" size="sm" className="pv-table">
                  <Thead>
                    <Tr>
                      <Th>ID</Th>
                      <Th>Name</Th>
                      <Th>SKU</Th>
                      <Th>Sync Status</Th>
                    </Tr>
                  </Thead>
                  <Tbody>
                    {products &&
                      products.length > 0 &&
                      products.map(product => {
                        return (
                          <Tr key={product.ID}>
                            <Td>{product.ID}</Td>
                            <Td>{product.name}</Td>
                            <Td>{product.sku}</Td>
                            <Td>
                              {product.pv_bexio_sync ? (
                                <Stack>
                                  <Flex flexDirection="row" alignItems="flex-start" gap="1" justifyContent="flex-start">
                                    <CheckCircleIcon color="green" mt="1" />
                                    <Text fontSize="sm" mt="0" mb="0" fontStyle="italic">
                                      {__("Zuletzt synchronisiert am", "pingvin-bexio-sync")} {product.pv_bexio_sync && product.pv_bexio_sync.last_sync ? new Date(product.pv_bexio_sync.last_sync).toLocaleString() : __("Unbekannt", "pingvin-bexio-sync")}
                                    </Text>
                                  </Flex>
                                </Stack>
                              ) : (
                                <WarningIcon color="pingvin.red" />
                              )}
                            </Td>
                          </Tr>
                        )
                      })}
                  </Tbody>
                </Table>
              </TableContainer>
            )}
            <Flex mt="3" alignItems="center" justifyContent="space-between">
              <Flex gap="2">
                <Button size="sm" onClick={() => setOffset(Math.max(0, offset - pageSize))} isDisabled={offset === 0}>
                  {__("Zurück", "pingvin-bexio-sync")}
                </Button>
                <Button size="sm" onClick={() => setOffset(offset + pageSize)} isDisabled={offset + pageSize >= total}>
                  {__("Weiter", "pingvin-bexio-sync")}
                </Button>
              </Flex>
            </Flex>
            <Flex mt="3" alignItems="center" justifyContent="space-between">
              <Text>
                {__("Seite", "pingvin-bexio-sync")} {Math.floor(offset / pageSize) + 1} / {Math.max(1, Math.ceil(total / pageSize))}
              </Text>
            </Flex>
          </Flex>
        </Stack>
      </ChakraProvider>
    </>
  )
}

export default Products
