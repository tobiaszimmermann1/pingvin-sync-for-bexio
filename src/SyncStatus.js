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
    <Flex flexDirection="row" alignItems="center" justifyContent="flex-start" gap="2" borderBottom="1px" borderColor="pingvin.border" width="100%" pb="25px" mb="25px">
      <Stack direction="column">
        <Stack direction="row">
          <Text fontSize="md" mt="0" fontWeight="bold">
            {__("Status", "pingvin-bexio-sync")}
            {enabled ? (
              <Badge variant="solid" colorScheme="green" ml="3">
                {__("SYNC EIN", "pingvin-bexio-sync")}
              </Badge>
            ) : (
              <Badge variant="solid" colorScheme="red" ml="3">
                {__("SYNC AUS", "pingvin-bexio-sync")}
              </Badge>
            )}
          </Text>
        </Stack>
        {loading ? (
          noData ? (
            <Text fontSize="sm" mt="-4" fontStyle="italic">
              {__("Keine Synchronisierungsdaten.", "pingvin-bexio-sync")}
            </Text>
          ) : (
            <Box>
              <Spinner />
            </Box>
          )
        ) : (
          <Stack>
            {status || next ? (
              <Stack direction="row" gap="10">
                <Box>
                  <Box p="0px 20px" color="pingvin.fontPrimary" mt="0" bg="pingvin.border" borderColor="pingvin.border" borderWidth="1px" borderTopRadius="md">
                    <Text fontSize="sm" fontWeight="bold">
                      {__("Letzte Synchronisierung", "pingvin-bexio-sync")}
                    </Text>
                  </Box>
                  <Box p="5px 20px" color="pingvin.fontPrimary" mt="-1" bg="pingvin.white" borderColor="pingvin.border" borderWidth="1px" borderBottomRadius="md">
                    <Stack>
                      <Table size="sm" mt="0">
                        <Tbody>
                          <Tr>
                            <Td border="0" pl="0">
                              <Text fontSize="sm" fontWeight="regular" m="0">
                                {__("Zeit:", "pingvin-bexio-sync")}
                              </Text>
                            </Td>
                            <Td border="0">
                              <Text fontSize="sm" m="0">
                                {status.date}
                              </Text>
                            </Td>
                          </Tr>
                          <Tr>
                            <Td border="0" pl="0">
                              <Text fontSize="sm" fontWeight="regular" m="0">
                                {__("Produkte:", "pingvin-bexio-sync")}
                              </Text>
                            </Td>
                            <Td border="0">
                              <Text fontSize="sm" m="0">
                                {status.products_count}
                              </Text>
                            </Td>
                          </Tr>
                        </Tbody>
                      </Table>
                    </Stack>
                  </Box>
                </Box>

                <Box>
                  <Box p="0px 20px" color="pingvin.fontPrimary" mt="0" bg="pingvin.border" borderColor="pingvin.border" borderWidth="1px" borderTopRadius="md">
                    <Text fontSize="sm" fontWeight="bold">
                      {__("Nächste Synchronisierung", "pingvin-bexio-sync")}
                    </Text>
                  </Box>
                  <Box p="5px 20px" color="pingvin.fontPrimary" mt="-1" bg="pingvin.white" borderColor="pingvin.border" borderWidth="1px" borderBottomRadius="md">
                    {next ? (
                      <Stack>
                        <Table size="sm" mt="0">
                          <Tbody>
                            <Tr>
                              <Td border="0" pl="0">
                                <Text fontSize="sm" fontWeight="regular" m="0">
                                  {__("Zeit:", "pingvin-bexio-sync")}
                                </Text>
                              </Td>
                              <Td border="0">
                                <Text fontSize="sm" m="0">
                                  {next.date}
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
                                <Text fontSize="sm" fontWeight="regular" m="0">
                                  {__("Keine Synchronisierung geplant", "pingvin-bexio-sync")}
                                </Text>
                              </Td>
                            </Tr>
                          </Tbody>
                        </Table>
                      </Stack>
                    )}
                  </Box>
                </Box>
              </Stack>
            ) : !isEnabled ? (
              <Box>{__("Synchronisierung ist nicht aktiviert", "pingvin-bexio-sync")}</Box>
            ) : !status ? (
              <Box>{__("Keine Daten", "pingvin-bexio-sync")}</Box>
            ) : (
              <Box>{__("Keine Daten", "pingvin-bexio-sync")}</Box>
            )}
          </Stack>
        )}
      </Stack>
    </Flex>
  )
}

export default SyncStatus
