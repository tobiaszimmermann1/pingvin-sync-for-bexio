import React, { useState, useEffect, useRef } from "react"
import _ from "lodash"
import { Flex, Button, Spinner, Text, Radio, RadioGroup, Stack, Box, TabPanel, Grid, GridItem, Table, Thead, Tbody, Tfoot, Tr, Th, Td, TableCaption, TableContainer } from "@chakra-ui/react"
import { CheckCircleIcon, WarningIcon } from "@chakra-ui/icons"
import apiCall from "./helpers/apiCall"
import { __ } from "@wordpress/i18n"

function Settings() {
  const [resultConnection, setResultConnection] = useState(null)
  const [loading, setLaoding] = useState(false)

  function testApi() {
    apiCall("GET", "article").then(res => {
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
  }

  useEffect(() => {
    if (resultConnection !== null) {
      setLaoding(false)
    }
  }, [resultConnection])

  return (
    <Flex flexDirection="row" alignItems="center" justifyContent="flex-start" gap="2" width="100%" pb="0px" mb="25px">
      <Stack>
        <Text fontSize="xl" mt="0" fontWeight="bold">
          {__("Bexio API Verbindung prüfen", "pv_bexio_connector")}
        </Text>
        <Button
          width="200px"
          color="pingvin.white"
          backgroundColor="pingvin.primary"
          _hover={{
            backgroundColor: "pingvin.primaryDark"
          }}
          onClick={() => {
            testApi()
            setLaoding(true)
          }}
          isDisabled={loading}
        >
          {__("Verbindung prüfen", "pv_bexio_connector")}
        </Button>

        {loading ? (
          <Box>
            <Spinner />
          </Box>
        ) : (
          <Stack direction="horizontal">
            {resultConnection && resultConnection.type === "success" && (
              <Box width="50%">
                <Box p="0px 20px" color="pingvin.fontPrimary" mt="4" bg="pingvin.border" borderColor="pingvin.border" borderWidth="1px" borderTopRadius="md">
                  <Text fontSize="sm" fontWeight="bold">
                    {__("Bexio API Verbindung", "pv_bexio_connector")}
                  </Text>
                </Box>
                <Box p="5px 20px" color="pingvin.fontPrimary" mt="-1" bg="pingvin.white" borderColor="pingvin.border" borderWidth="1px" borderBottomRadius="md">
                  <Text fontSize="sm" fontWeight="regular" color="success">
                    <CheckCircleIcon mr="10px" />
                    {__("Erfolgreiche Verbindung zur Bexio API", "pv_bexio_connector")}
                  </Text>
                  <Text fontSize="sm" fontWeight="regular" color="pingvin.fontPrimary">
                    {__("Das heisst, dass die Authentifizierung funktioniert und WordPress eine Verbindung zur Bexio API herstellen kann.", "pv_bexio_connector")}
                  </Text>
                </Box>
              </Box>
            )}

            {resultConnection && resultConnection.type === "error" && (
              <Box width="50%">
                <Box p="0px 20px" color="pingvin.fontPrimary" mt="4" bg="pingvin.border" borderColor="pingvin.border" borderWidth="1px" borderTopRadius="md">
                  <Text fontSize="sm" fontWeight="bold">
                    {__("Bexio API Verbindung", "pv_bexio_connector")}
                  </Text>
                </Box>
                <Box p="5px 20px" color="pingvin.fontPrimary" mt="-1" bg="pingvin.white" borderColor="pingvin.border" borderWidth="1px" borderBottomRadius="md">
                  <Stack>
                    <Box>
                      <Text fontSize="sm" fontWeight="regular" color="error">
                        <WarningIcon mr="10px" color="error" />
                        {__("Keine Verbindung zur Bexio API möglich", "pv_bexio_connector")}
                      </Text>
                      <Text fontSize="sm" fontWeight="regular" color="pingvin.fontPrimary">
                        {__("Setze die Authentifizierungseinstellungen zurück und verbinde WordPress neu mit Bexio.", "pv_bexio_connector")}
                      </Text>
                      <Text fontSize="sm">
                        <i>
                          {__("Fehler:", "pv_bexio_connector")} {resultConnection.data}
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
