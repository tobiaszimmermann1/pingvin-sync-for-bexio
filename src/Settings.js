import React, { useState, useEffect, useRef } from "react"
import _ from "lodash"
import { Flex, Button, Spinner, Text, Radio, RadioGroup, Stack, Box, TabPanel, Grid, GridItem, Table, Thead, Tbody, Tfoot, Tr, Th, Td, TableCaption, TableContainer } from "@chakra-ui/react"
import { CheckCircleIcon, WarningIcon } from "@chakra-ui/icons"
import apiCall from "./helpers/apiCall"
import { __ } from "@wordpress/i18n"

function Settings() {
  const [resultConnection, setResultConnection] = useState(null)
  const [resultMarket, setResultMarket] = useState(null)
  const [resultConnectionError, setResultConnectionError] = useState(null)
  const [resultMarketError, setResultMarketError] = useState(null)
  const [loading, setLaoding] = useState(false)

  function testApi() {
    apiCall("GET", "market").then(res => {
      if (res.data.status === 200) {
        setResultConnection({
          type: "success",
          data: res.data.result
        })
      } else {
        setResultConnection({
          type: "error",
          data: res.data.status
        })
      }
    })

    apiCall("GET", `market/${pvLoonityAppLocalizer.settings.loonity_market_id}`).then(res => {
      if (res.data.status === 200) {
        setResultMarket({
          type: "success",
          data: res.data.result
        })
      } else {
        setResultMarket({
          type: "error",
          data: res.data.status
        })
      }
    })
  }

  useEffect(() => {
    if (resultConnection !== null && resultMarket !== null) {
      setLaoding(false)
    }
  }, [resultConnection, resultMarket])

  return (
    <Flex flexDirection="row" alignItems="center" justifyContent="flex-start" gap="2" width="100%" pb="25px" mb="25px">
      <Stack>
        <Text fontSize="xl" mt="0" fontWeight="bold">
          {__("Test Connection", "pv_loonity_connector")}
        </Text>
        <Button
          width="350px"
          color="loonity.white"
          backgroundColor="loonity.primary"
          _hover={{
            backgroundColor: "loonity.primaryDark"
          }}
          onClick={() => {
            testApi()
            setLaoding(true)
          }}
          isDisabled={loading}
          className=""
        >
          {__("Verify connection to Loonity WP API", "pv_loonity_connector")}
        </Button>

        {loading ? (
          <Box>
            <Spinner />
          </Box>
        ) : (
          <Stack direction="horizontal">
            {resultConnection && resultConnection.type === "success" && (
              <Box width="50%">
                <Box p="0px 20px" color="loonity.fontPrimary" mt="4" bg="loonity.secondary" borderColor="loonity.border" borderWidth="1px" borderTopRadius="md">
                  <Text fontSize="lg" fontWeight="bold">
                    {__("Loonity WP API Connection", "pv_loonity_connector")}
                  </Text>
                </Box>
                <Box p="5px 20px" color="loonity.fontPrimary" mt="-1" bg="loonity.white" borderColor="loonity.border" borderWidth="1px" borderBottomRadius="md">
                  <Text fontSize="lg" fontWeight="regular" color="success">
                    <CheckCircleIcon mr="10px" />
                    {__("Successfully connected to the Loonity WP API", "pv_loonity_connector")}
                  </Text>
                  <Text fontSize="lg" fontWeight="regular" color="loonity.fontPrimary">
                    {__("This means that authentication works and WordPress can establish a connection to the Loonity WP API", "pv_loonity_connector")}
                  </Text>
                </Box>
              </Box>
            )}

            {resultConnection && resultConnection.type === "error" && (
              <Box width="50%">
                <Box p="0px 20px" color="loonity.fontPrimary" mt="4" bg="loonity.secondary" borderColor="loonity.border" borderWidth="1px" borderTopRadius="md">
                  <Text fontSize="lg" fontWeight="bold">
                    {__("Loonity WP API Connection", "pv_loonity_connector")}
                  </Text>
                </Box>
                <Box p="5px 20px" color="loonity.fontPrimary" mt="-1" bg="loonity.white" borderColor="loonity.border" borderWidth="1px" borderBottomRadius="md">
                  <Stack>
                    <Box>
                      <Text fontSize="lg" fontWeight="regular" color="error">
                        <WarningIcon mr="10px" color="error" />
                        {__("Unable to connect to the Loonity WP API", "pv_loonity_connector")}
                      </Text>
                      <Text fontSize="lg" fontWeight="regular" color="loonity.fontPrimary">
                        {__("Check your authentication token in the settings.", "pv_loonity_connector")}
                      </Text>
                      <Text fontSize="sm">
                        <i>
                          {__("Error message:", "pv_loonity_connector")} {resultConnection.data}
                        </i>
                      </Text>
                    </Box>
                  </Stack>
                </Box>
              </Box>
            )}

            {resultMarket && resultMarket.type === "success" && (
              <Box width="50%">
                <Box p="0px 20px" color="loonity.fontPrimary" mt="4" bg="loonity.secondary" borderColor="loonity.border" borderWidth="1px" borderTopRadius="md">
                  <Text fontSize="lg" fontWeight="bold">
                    {__("Loonity Market Connection", "pv_loonity_connector")}
                  </Text>
                </Box>
                <Box p="5px 20px" color="loonity.fontPrimary" mt="-1" bg="loonity.white" borderColor="loonity.border" borderWidth="1px" borderBottomRadius="md">
                  <Stack>
                    <Box>
                      <Text fontSize="lg" fontWeight="regular" color="success" mb="0">
                        <CheckCircleIcon mr="10px" />
                        {__("Successfully found the market", "pv_loonity_connector")}
                      </Text>
                    </Box>
                    <Table size="sm" mt="2" mb="4">
                      <Tbody>
                        <Tr>
                          <Td border="0" pl="0" py="0">
                            {" "}
                            <Text fontSize="lg" fontWeight="regular" m="0">
                              {__("Market name:", "pv_loonity_connector")}
                            </Text>
                          </Td>
                          <Td border="0" py="0">
                            {" "}
                            <Text fontSize="lg" fontWeight="bold" m="0">
                              {resultMarket.data.role.name}
                            </Text>
                          </Td>
                        </Tr>
                        <Tr>
                          <Td border="0" pl="0" py="0">
                            <Text fontSize="lg" fontWeight="regular" m="0">
                              {__("Market ID:", "pv_loonity_connector")}
                            </Text>
                          </Td>
                          <Td border="0" py="0">
                            <Text fontSize="lg" fontWeight="bold" m="0">
                              {resultMarket.data.id}
                            </Text>
                          </Td>
                        </Tr>
                      </Tbody>
                    </Table>
                  </Stack>
                </Box>
              </Box>
            )}

            {resultMarket && resultMarket.type === "error" && (
              <Box width="50%">
                <Box p="0px 20px" color="loonity.fontPrimary" mt="4" bg="loonity.secondary" borderColor="loonity.border" borderWidth="1px" borderTopRadius="md">
                  <Text fontSize="lg" fontWeight="bold">
                    {__("Market Connection", "pv_loonity_connector")}
                  </Text>
                </Box>
                <Box p="5px 20px" color="loonity.fontPrimary" mt="-1" bg="loonity.white" borderColor="loonity.border" borderWidth="1px" borderBottomRadius="md">
                  <Stack>
                    <Box>
                      <Text fontSize="lg" fontWeight="regular" color="error">
                        <WarningIcon mr="10px" color="error" />
                        {__("Unable to find the specified market", "pv_loonity_connector")}
                      </Text>
                      <Text fontSize="lg" fontWeight="regular" color="loonity.fontPrimary">
                        {__("Check your market ID in the settings.", "pv_loonity_connector")}
                      </Text>
                      <Text fontSize="sm">
                        <i>
                          {__("Error message:", "pv_loonity_connector")} {resultMarket.data}
                        </i>
                      </Text>
                    </Box>
                  </Stack>
                </Box>
              </Box>
            )}
          </Stack>
        )}
      </Stack>
    </Flex>
  )
}

export default Settings
