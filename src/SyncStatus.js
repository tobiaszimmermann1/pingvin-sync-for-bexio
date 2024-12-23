import React, { useState, useEffect, useRef } from "react"
import _ from "lodash"
import { Flex, Badge, useToast, Switch, FormControl, FormLabel, Spinner, Text, Radio, RadioGroup, Stack, Box, TabPanel, Grid, GridItem, Table, Thead, Tbody, Tfoot, Tr, Th, Td, TableCaption, TableContainer } from "@chakra-ui/react"
import { CheckCircleIcon, WarningIcon } from "@chakra-ui/icons"
import apiCall from "./helpers/apiCall"
import { __ } from "@wordpress/i18n"

function SyncStatus({ enabled }) {
  const [loading, setLoading] = useState(true)
  const [loadingNext, setLoadingNext] = useState(true)
  const [status, setStatus] = useState(null)
  const [next, setNext] = useState(null)
  const [isEnabled, setIsEnabled] = useState(enabled)
  const [noData, setNoData] = useState(false)

  useEffect(() => {
    let isGetStatus = true

    apiCall("GET", `transient`).then(res => {
      console.log(res.data)
      if (res.data) {
        setStatus(JSON.parse(res.data.status))
        setNext(JSON.parse(res.data.next))
        setLoading(false)
      } else {
        setNoData(true)
      }
    })

    return () => {
      isGetStatus = false
    }
  }, [])

  useEffect(() => {
    let statusInterval
    if (enabled) {
      statusInterval = setInterval(() => {
        let isGetStatus = true

        apiCall("GET", `transient`).then(res => {
          if (res.data) {
            setStatus(JSON.parse(res.data.status))
            setNext(JSON.parse(res.data.next))
          }
        })

        return () => {
          isGetStatus = false
        }
      }, 5000)
    }

    return () => {
      clearInterval(statusInterval)
    }
  }, [enabled])

  return (
    <Flex flexDirection="row" alignItems="center" justifyContent="flex-start" gap="2" borderBottom="1px" borderColor="pingvin.border" width="100%" pb="40px" mb="25px">
      {loading ? (
        noData ? (
          <Stack direction="column">
            <Text fontSize="xl" mt="0" fontWeight="bold">
              {__("Status", "pv_bexio_connector")}
            </Text>
            <Text fontSize="sm" mt="-4" fontStyle="italic">
              {__("Keine Synchronisierungsdaten.", "pv_bexio_connector")}
            </Text>
          </Stack>
        ) : (
          <Box>
            <Spinner />
          </Box>
        )
      ) : (
        <Stack>
          <Stack direction="row">
            <Text fontSize="xl" mt="0" fontWeight="bold">
              {__("Status", "pv_bexio_connector")}
              {enabled ? (
                <Badge variant="solid" colorScheme="green" ml="3">
                  SYNC ON
                </Badge>
              ) : (
                <Badge variant="solid" colorScheme="red" ml="3">
                  SYNC OFF
                </Badge>
              )}
            </Text>
          </Stack>
          {status || next ? (
            <Stack direction="row" gap="10">
              <Box>
                <Box p="0px 20px" color="pingvin.fontPrimary" mt="0" bg="pingvin.secondary" borderColor="pingvin.border" borderWidth="1px" borderTopRadius="md">
                  <Text fontSize="lg" fontWeight="bold">
                    {__("Last synchronization", "pv_bexio_connector")}
                  </Text>
                </Box>
                <Box p="5px 20px" color="pingvin.fontPrimary" mt="-1" bg="pingvin.white" borderColor="pingvin.border" borderWidth="1px" borderBottomRadius="md">
                  <Stack>
                    <Table size="sm" mt="0">
                      <Tbody>
                        <Tr>
                          <Td border="0" pl="0">
                            <Text fontSize="lg" fontWeight="regular" m="0">
                              {__("Time:", "pv_bexio_connector")}
                            </Text>
                          </Td>
                          <Td border="0">
                            <Text fontSize="lg" m="0">
                              {status.date}
                            </Text>
                          </Td>
                        </Tr>
                        <Tr>
                          <Td border="0" pl="0">
                            <Text fontSize="lg" fontWeight="regular" m="0">
                              {__("Market ID:", "pv_bexio_connector")}
                            </Text>
                          </Td>
                          <Td border="0">
                            <Text fontSize="lg" m="0">
                              {status.market_id}
                            </Text>
                          </Td>
                        </Tr>
                        <Tr>
                          <Td border="0" pl="0">
                            <Text fontSize="lg" fontWeight="regular" m="0">
                              {__("# Products:", "pv_bexio_connector")}
                            </Text>
                          </Td>
                          <Td border="0">
                            <Text fontSize="lg" m="0">
                              {status.products_count}
                            </Text>
                          </Td>
                        </Tr>
                        <Tr>
                          <Td border="0" pl="0">
                            <Text fontSize="lg" fontWeight="regular" m="0">
                              {__("Locale:", "pv_bexio_connector")}
                            </Text>
                          </Td>
                          <Td border="0">
                            <Text fontSize="lg" m="0">
                              {status.locale}
                            </Text>
                          </Td>
                        </Tr>
                      </Tbody>
                    </Table>
                  </Stack>
                </Box>
              </Box>

              <Box>
                <Box p="0px 20px" color="pingvin.fontPrimary" mt="0" bg="pingvin.secondary" borderColor="pingvin.border" borderWidth="1px" borderTopRadius="md">
                  <Text fontSize="lg" fontWeight="bold">
                    {__("Next scheduled synchronisation", "pv_bexio_connector")}
                  </Text>
                </Box>
                <Box p="5px 20px" color="pingvin.fontPrimary" mt="-1" bg="pingvin.white" borderColor="pingvin.border" borderWidth="1px" borderBottomRadius="md">
                  {next ? (
                    <Stack>
                      <Table size="sm" mt="0">
                        <Tbody>
                          <Tr>
                            <Td border="0" pl="0">
                              <Text fontSize="lg" fontWeight="regular" m="0">
                                {__("Time:", "pv_bexio_connector")}
                              </Text>
                            </Td>
                            <Td border="0">
                              <Text fontSize="lg" m="0">
                                {next.date}
                              </Text>
                            </Td>
                          </Tr>
                          <Tr>
                            <Td border="0" pl="0">
                              <Text fontSize="lg" fontWeight="regular" m="0">
                                {__("Market ID:", "pv_bexio_connector")}
                              </Text>
                            </Td>
                            <Td border="0">
                              <Text fontSize="lg" m="0">
                                {next.market_id}
                              </Text>
                            </Td>
                          </Tr>
                          <Tr>
                            <Td border="0" pl="0">
                              <Text fontSize="lg" fontWeight="regular" m="0">
                                {__("Locale:", "pv_bexio_connector")}
                              </Text>
                            </Td>
                            <Td border="0">
                              <Text fontSize="lg" m="0">
                                {next.locale}
                              </Text>
                            </Td>
                          </Tr>
                        </Tbody>
                      </Table>
                    </Stack>
                  ) : (
                    <Stack>
                      <Table size="sm" mt="0">
                        <Tbody>
                          <Tr>
                            <Td border="0" pl="0">
                              <Text fontSize="lg" fontWeight="regular" m="0">
                                {__("No scheduled synchronisations", "pv_bexio_connector")}
                              </Text>
                            </Td>
                          </Tr>
                        </Tbody>
                      </Table>
                    </Stack>
                  )}
                </Box>
              </Box>

              <Box>
                <Box p="0px 20px" color="pingvin.fontPrimary" mt="0" bg="pingvin.secondary" borderColor="pingvin.border" borderWidth="1px" borderTopRadius="md">
                  <Text fontSize="lg" fontWeight="bold">
                    {__("Synchronization Log", "pv_bexio_connector")}
                  </Text>
                </Box>
                <Box p="5px 20px" color="pingvin.fontPrimary" mt="-1" bg="pingvin.white" borderColor="pingvin.border" borderWidth="1px" borderBottomRadius="md">
                  <Stack>
                    <Table size="sm" mt="0">
                      <Tbody>
                        <Tr>
                          <Td border="0" pl="0">
                            <Text fontSize="lg" m="0" textDecoration="underline">
                              <a href={`${pvProductSyncAppLocalizer.homeUrl}/wp-content/pv-loonity-logger.log`} target="_blank">
                                Download Log
                              </a>
                            </Text>
                          </Td>
                        </Tr>
                      </Tbody>
                    </Table>
                  </Stack>
                </Box>
              </Box>
            </Stack>
          ) : !isEnabled ? (
            <Box>{__("Sync is not enabled.", "pv_bexio_connector")}</Box>
          ) : !status ? (
            <Box>{__("No status data to display", "pv_bexio_connector")}</Box>
          ) : (
            <Box>{__("No data found.", "pv_bexio_connector")}</Box>
          )}
        </Stack>
      )}
    </Flex>
  )
}

export default SyncStatus
