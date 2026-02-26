import React, { useState, useEffect } from "react"
import { Flex, Switch, FormControl, useToast, Text, Radio, RadioGroup, Stack } from "@chakra-ui/react"
import apiCall from "./helpers/apiCall"
import { __ } from "@wordpress/i18n"

const INTERVALS = [
  { value: "60", label: "1min" },
  { value: "120", label: "2min" },
  { value: "300", label: "5min" },
  { value: "3600", label: "60min" },
  { value: "14400", label: "4h" },
  { value: "86400", label: "24h" }
]

function SyncSettings({ setSettingsInterval }) {
  const [settingsLoading, setSettingsLoading] = useState(true)

  // Product sync
  const [productEnabled, setProductEnabled] = useState(false)
  const [productInterval, setProductInterval] = useState(null)

  // Contact sync
  const [contactEnabled, setContactEnabled] = useState(false)
  const [contactInterval, setContactInterval] = useState(null)

  const toast = useToast()

  // Load both on mount in parallel
  useEffect(() => {
    Promise.all([apiCall("GET", "syncSettings", { type: "products" }), apiCall("GET", "syncSettings", { type: "contacts" })]).then(([prodRes, contRes]) => {
      if (prodRes.data) {
        setProductEnabled(prodRes.data?.enabled ?? false)
        setProductInterval(String(prodRes.data?.interval ?? ""))
      }
      if (contRes.data) {
        setContactEnabled(contRes.data?.enabled ?? false)
        setContactInterval(String(contRes.data?.interval ?? ""))
      }
      setSettingsLoading(false)
    })
  }, [])

  const showToast = (success, message) => {
    toast({
      title: success ? __("Settings successfully updated.", "pingvin-sync-for-bexio") : __("An error occurred", "pingvin-sync-for-bexio"),
      description: !success ? message : undefined,
      status: success ? "success" : "error",
      duration: 5000,
      isClosable: true,
      position: "bottom-right"
    })
  }

  const saveSettings = (type, enabled, interval) => {
    apiCall("POST", "syncSettings", { type, enabled, interval }).then(res => {
      showToast(res.status === 200, res.response?.data?.message)
    })
  }

  const intervalRadios = (
    <Stack direction="row">
      {INTERVALS.map(({ value, label }) => (
        <Radio key={value} value={value} size="md" colorScheme="gray" mr="2">
          {label}
        </Radio>
      ))}
    </Stack>
  )

  return (
    <>
      {/* ── Product sync ────────────────────────────────────────── */}
      <Flex flexDirection="row" alignItems="center" justifyContent="flex-start" gap="2" borderBottom="1px" borderColor="pingvin.border" width="100%" pb="25px" mb="25px">
        <Stack>
          <Text fontSize="md" mt="0" fontWeight="bold">
            {__("Produkte", "pingvin-sync-for-bexio")}
          </Text>
          <Stack direction="column" gap="4">
            <FormControl display="flex" flexDirection="row" alignItems="flex-start" gap="8">
              <Text fontSize="sm" mt="0" fontWeight={700}>
                {__("Synchronisierung aktivieren:", "pingvin-sync-for-bexio")}
              </Text>
              <Switch
                size="md"
                colorScheme="gray"
                isChecked={productEnabled}
                onChange={e => {
                  const val = e.target.checked
                  setProductEnabled(val)
                  setSettingsInterval(val)
                  saveSettings("products", val, productInterval)
                }}
                isDisabled={settingsLoading}
              />
            </FormControl>

            <Text fontSize="sm" mt="-4" fontStyle="italic">
              {__("Wenn du die Synchronisierung deaktivierst, werden alle geplanten Synchronisierungen abgebrochen.", "pingvin-sync-for-bexio")}
            </Text>

            <FormControl display="flex" flexDirection="row" alignItems="flex-start" gap="8">
              <Text fontSize="sm" mt="0" fontWeight={700}>
                {__("Synchronisierungs-Intervall:", "pingvin-sync-for-bexio")}
              </Text>
              <RadioGroup
                onChange={e => {
                  setProductInterval(e)
                  saveSettings("products", productEnabled, e)
                }}
                value={productInterval}
                isDisabled={settingsLoading}
              >
                {intervalRadios}
              </RadioGroup>
            </FormControl>

            <Text fontSize="sm" mt="-4" fontStyle="italic">
              {__("Wenn du das Intervall änderst, wird die nächste Synchronisierung abgebrochen und entsprechend dem gewählten Intervall neu geplant.", "pingvin-sync-for-bexio")}
            </Text>
          </Stack>
        </Stack>
      </Flex>

      {/* ── Contact sync ────────────────────────────────────────── */}
      <Flex flexDirection="row" alignItems="center" justifyContent="flex-start" gap="2" borderBottom="1px" borderColor="pingvin.border" width="100%" pb="25px" mb="25px">
        <Stack>
          <Text fontSize="md" mt="0" fontWeight="bold">
            {__("Kontakte", "pingvin-sync-for-bexio")}
          </Text>
          <Stack direction="column" gap="4">
            <FormControl display="flex" flexDirection="row" alignItems="flex-start" gap="8">
              <Text fontSize="sm" mt="0" fontWeight={700}>
                {__("Synchronisierung aktivieren:", "pingvin-sync-for-bexio")}
              </Text>
              <Switch
                size="md"
                colorScheme="gray"
                isChecked={contactEnabled}
                onChange={e => {
                  const val = e.target.checked
                  setContactEnabled(val)
                  saveSettings("contacts", val, contactInterval)
                }}
                isDisabled={settingsLoading}
              />
            </FormControl>

            <Text fontSize="sm" mt="-4" fontStyle="italic">
              {__("Wenn du die Synchronisierung deaktivierst, werden alle geplanten Synchronisierungen abgebrochen.", "pingvin-sync-for-bexio")}
            </Text>

            <FormControl display="flex" flexDirection="row" alignItems="flex-start" gap="8">
              <Text fontSize="sm" mt="0" fontWeight={700}>
                {__("Synchronisierungs-Intervall:", "pingvin-sync-for-bexio")}
              </Text>
              <RadioGroup
                onChange={e => {
                  setContactInterval(e)
                  saveSettings("contacts", contactEnabled, e)
                }}
                value={contactInterval}
                isDisabled={settingsLoading}
              >
                {intervalRadios}
              </RadioGroup>
            </FormControl>

            <Text fontSize="sm" mt="-4" fontStyle="italic">
              {__("Wenn du das Intervall änderst, wird die nächste Synchronisierung abgebrochen und entsprechend dem gewählten Intervall neu geplant.", "pingvin-sync-for-bexio")}
            </Text>
          </Stack>
        </Stack>
      </Flex>
    </>
  )
}

export default SyncSettings
