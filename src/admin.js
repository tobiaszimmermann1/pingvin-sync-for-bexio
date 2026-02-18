import React from "react"
import { createRoot } from "react-dom/client"
import App from "./App"
import Contacts from "./Contacts"
import Orders from "./Orders"
import Products from "./Products"

window.addEventListener("DOMContentLoaded", function (e) {
  const syncEl = document.getElementById("pv_sync")
  if (syncEl) {
    const products = createRoot(syncEl)
    products.render(<App />)
  }

  const contactsEl = document.getElementById("pv_contacts")
  if (contactsEl) {
    const contacts = createRoot(contactsEl)
    contacts.render(<Contacts />)
  }

  const ordersEl = document.getElementById("pv_orders")
  if (ordersEl) {
    const orders = createRoot(ordersEl)
    orders.render(<Orders />)
  }

  const productsEl = document.getElementById("pv_products")
  if (productsEl) {
    const products = createRoot(productsEl)
    products.render(<Products />)
  }
})
