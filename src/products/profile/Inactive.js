import React, { useState, useEffect, useRef } from "react"
import axios from "axios"
import _ from "lodash"
import { Step, StepDescription, StepIcon, StepIndicator, StepNumber, StepSeparator, StepStatus, StepTitle, Stepper, useSteps, Flex, ChakraProvider, extendTheme, Button, Spinner, Alert, AlertIcon, AlertTitle, AlertDescription, Stack, Box, List, ListItem, ListIcon, OrderedList, UnorderedList, Text, Tabs, TabList, TabPanels, Tab, TabPanel, Grid, GridItem } from "@chakra-ui/react"

function Inactive() {
  return (
    <Alert status="warning" variant="subtle" flexDirection="row" alignItems="flex-start" justifyContent="flex-start" textAlign="left" p={4}>
      <AlertIcon boxSize="40px" mr={8} />
      <AlertDescription maxWidth="sm">
        <AlertTitle mt={0} mb={1} fontSize="lg">
          Synchronization is currently <u>inactive</u>!
        </AlertTitle>
        You can set up synchronization here.
      </AlertDescription>
    </Alert>
  )
}

export default Inactive
