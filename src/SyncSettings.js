import React, { useState, useEffect, useRef } from "react"
import _ from "lodash"
import { Flex, Switch, FormControl, useToast, FormLabel, Spinner, Text, Radio, RadioGroup, Stack, Box, TabPanel, Grid, GridItem, Table, Thead, Tbody, Tfoot, Tr, Th, Td, TableCaption, TableContainer } from "@chakra-ui/react"
import { CheckCircleIcon, WarningIcon } from "@chakra-ui/icons"
import apiCall from "./helpers/apiCall"
import { __ } from "@wordpress/i18n"

function SyncSettings({ setSettingsInterval }) {
  const [loading, setLaoding] = useState(false)
  const [settingsLoading, setSettingsLoading] = useState(true)
  const [userInputRegistered, setUserInputRegistered] = useState(false)
  const [interval, setInterval] = useState("300")
  const [enabled, setEnabled] = useState(false)
  const [allProducts, setAllProducts] = useState(false)

  const toast = useToast()

  useEffect(() => {
    let isGetOptions = true
    apiCall("GET", "syncSettings").then(res => {
      if (res.data) {
        let optionsData = JSON.parse(res.data)
        setInterval(optionsData?.interval)
        setEnabled(optionsData?.enabled)
        setAllProducts(optionsData?.allProducts)
      }
      setSettingsLoading(false)
    })

    return () => {
      isGetOptions = false
    }
  }, [])

  useEffect(() => {
    let isSaveOptions = true

    if (userInputRegistered === true) {
      let optionsObject = {
        interval: interval,
        enabled: enabled,
        allProducts: allProducts
      }

      apiCall("POST", "syncSettings", optionsObject).then(res => {
        if (res.status === 200) {
          toast({
            title: __("Settings successfully updated.", "pv_loonity_connector"),
            status: "success",
            duration: 5000,
            isClosable: true,
            position: "bottom-right"
          })
        } else {
          toast({
            title: __("An error occured", "pv_loonity_connector"),
            description: res.response.data.message,
            status: "error",
            duration: 5000,
            isClosable: true,
            position: "bottom-right"
          })
        }
      })
    }

    return () => {
      isSaveOptions = false
    }
  }, [interval, enabled, allProducts])

  return (
    <Flex flexDirection="row" alignItems="center" justifyContent="flex-start" gap="2" borderBottom="1px" borderColor="loonity.border" width="100%" pb="25px" mb="25px">
      <Stack>
        <Text fontSize="xl" mt="0" fontWeight="bold">
          {__("Settings", "pv_loonity_connector")}
        </Text>
        <Stack direction="column" gap="4">
          <FormControl display="flex" flexDirection="row" alignItems="flex-start" gap="8">
            <Text fontSize="lg" mt="0" fontWeight="bold">
              {__("Enable synchronization:", "pv_loonity_connector")}
            </Text>
            <Switch
              size="md"
              colorScheme="teal"
              isChecked={enabled}
              onChange={e => {
                setEnabled(e.target.checked)
                setUserInputRegistered(true)
                setSettingsInterval(e.target.checked)
              }}
              isDisabled={settingsLoading}
            />
          </FormControl>

          <Text fontSize="sm" mt="-4" fontStyle="italic">
            {__("If disabled, all scheduled synchronizations will be cancelled.", "pv_loonity_connector")}
          </Text>

          <FormControl display="flex" flexDirection="row" alignItems="flex-start" gap="8">
            <Text fontSize="lg" mt="0" fontWeight="bold">
              {__("Synchronization interval:", "pv_loonity_connector")}
            </Text>
            <RadioGroup
              onChange={e => {
                setInterval(e)
                setUserInputRegistered(true)
              }}
              value={interval}
              isDisabled={settingsLoading}
            >
              <Stack direction="row">
                <Radio value="300" size="lg" colorScheme="teal" mr="2">
                  5min
                </Radio>
                <Radio value="3600" size="lg" colorScheme="teal" mr="2">
                  60min
                </Radio>
                <Radio value="14400" size="lg" colorScheme="teal" mr="2">
                  4h
                </Radio>
                <Radio value="86400" size="lg" colorScheme="teal" mr="2">
                  24h
                </Radio>
              </Stack>
            </RadioGroup>{" "}
          </FormControl>

          <Text fontSize="sm" mt="-4" fontStyle="italic">
            {__("If you change the interval, the next synchronization will be cancelled and rescheduled according to the chosen interval.", "pv_loonity_connector")}
          </Text>

          {/*
          <FormControl display="flex" flexDirection="row" alignItems="flex-start" gap="8" mb="0">
            <Text fontSize="lg" mt="0">
              {__("Delete all non-Loonity products:", "pv_loonity_connector")}
            </Text>
            <Switch
              size="md"
              colorScheme="teal"
              isChecked={allProducts}
              onChange={e => {
                setAllProducts(e.target.checked)
                setUserInputRegistered(true)
              }}
              isDisabled={settingsLoading}
            />
          </FormControl>
          
          <Text fontSize="sm" mt="-4" fontStyle="italic">
            {__("If turned on, this will delete all products that don't exist in Loonity during the next synchronization.", "pv_loonity_connector")}
          </Text>
          */}
        </Stack>
      </Stack>
    </Flex>
  )
}

export default SyncSettings
