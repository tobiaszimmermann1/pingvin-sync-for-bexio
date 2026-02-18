import React, { useState, useEffect, useRef } from "react"
import _ from "lodash"
import { WarningIcon, CheckCircleIcon, ExternalLinkIcon, TimeIcon } from "@chakra-ui/icons"
import { Table, Thead, Tbody, Tfoot, Tr, Th, Td, TableCaption, TableContainer, Badge, Flex, ChakraProvider, extendTheme, Button, Spinner, Alert, AlertIcon, AlertTitle, AlertDescription, Stack, Box, List, ListItem, ListIcon, OrderedList, UnorderedList, Text, Tabs, TabList, TabPanels, Tab, TabPanel, Grid, GridItem, Link } from "@chakra-ui/react"
import apiCall from "./helpers/apiCall"
import { __ } from "@wordpress/i18n"
import theme from "./helpers/theme"

function Orders() {
  const [orders, setOrders] = useState(null)
  const [loadingTable, setLoadingTable] = useState(true)
  const [offset, setOffset] = useState(0)
  const [total, setTotal] = useState(0)
  const pageSize = 50

  useEffect(() => {
    let isGetOrders = true

    setLoadingTable(true)
    apiCall("GET", `orders`, { offset: offset, page_size: pageSize })
      .then(res => {
        if (!isGetOrders) return
        if (res && res.data) {
          setOrders(res.data.items || [])
          setTotal(res.data.total || 0)
        } else {
          setOrders([])
          setTotal(0)
        }
        setLoadingTable(false)
      })
      .catch(err => {
        setOrders([])
        setTotal(0)
        setLoadingTable(false)
      })

    return () => {
      isGetOrders = false
    }
  }, [])

  // refetch when offset changes
  useEffect(() => {
    let isGetOrders = true
    setLoadingTable(true)
    apiCall("GET", `orders`, { offset: offset, page_size: pageSize })
      .then(res => {
        if (!isGetOrders) return
        if (res && res.data) {
          setOrders(res.data.items || [])
          setTotal(res.data.total || 0)
        } else {
          setOrders([])
          setTotal(0)
        }
        setLoadingTable(false)
      })
      .catch(() => {
        setOrders([])
        setTotal(0)
        setLoadingTable(false)
      })

    return () => {
      isGetOrders = false
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
                      <Th>Datum</Th>
                      <Th>Kunde</Th>
                      <Th>Preis</Th>
                      <Th>Status</Th>
                      <Th>Sync Status</Th>
                    </Tr>
                  </Thead>
                  <Tbody>
                    {orders &&
                      orders.length > 0 &&
                      orders.map(order => {
                        return (
                          <Tr key={order.ID}>
                            <Td>{order.ID}</Td>
                            <Td>{order.date}</Td>
                            <Td>{order.customer}</Td>
                            <Td>{order.price}</Td>
                            <Td>{order.status}</Td>
                            <Td>
                              {order.pv_bexio_sync ? (
                                <Stack gap="1">
                                  <Flex flexDirection="row" alignItems="center" gap="1" justifyContent="flex-start">
                                    <CheckCircleIcon color="green" />
                                    <Text fontSize="xs" mt="0" mb="0">
                                      {__("Zuletzt synchronisiert am", "pingvin-bexio-sync")} {order.pv_bexio_sync && order.pv_bexio_sync.last_sync ? new Date(order.pv_bexio_sync.last_sync).toLocaleString() : __("Unbekannt", "pingvin-bexio-sync")}
                                    </Text>
                                  </Flex>
                                  <Link href={`https://office.bexio.com/index.php/kb_order/show/id/${order.bexio_order_id}`} isExternal>
                                    <Flex flexDirection="row" alignItems="center" gap="1" justifyContent="flex-start">
                                      <ExternalLinkIcon color="pingvin.primary" />
                                      <Text fontSize="xs" mt="0" mb="0">
                                        {order.bexio_order_nr ? `${__("Bexio Auftrag:", "pingvin-bexio-sync")} ${order.bexio_order_nr}` : __("Kein Bexio Auftrag", "pingvin-bexio-sync")}
                                      </Text>
                                    </Flex>
                                  </Link>
                                  {/*}
                                  <Flex flexDirection="row" alignItems="center" gap="1" justifyContent="flex-start">
                                    <TimeIcon color="pingvin.primary" />
                                    <Text fontSize="xs" mt="0" mb="0">
                                      {order.bexio_order_status ? `${__("Bexio Status:", "pingvin-bexio-sync")} ${order.bexio_order_status}` : __("Kein Bexio Status", "pingvin-bexio-sync")}
                                    </Text>
                                  </Flex>
                                  */}
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

export default Orders
