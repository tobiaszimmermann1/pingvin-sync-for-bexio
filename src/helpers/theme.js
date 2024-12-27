import { extendTheme } from "@chakra-ui/react"

// example theme
const theme = extendTheme({
  colors: {
    pingvin: {
      bg: "#f9f9fa",
      white: "#ffffff",
      primary: "#9f5a58",
      primaryDark: "#472626",
      secondary: "#f4d4d3",
      iconFont: "#9f5a58",
      iconBG: "#ffecb6",
      fontPrimary: "#041321",
      fontSecondary: "#5c6873",
      border: "#cccccc",
      inputBorder: "#cccccc"
    },
    success: "#2e7d32",
    error: "#d32f2f",
    warning: "#ed6c02",
    info: "#0288d1"
  },
  fonts: {
    body: "system-ui, sans-serif",
    heading: "Georgia, serif",
    mono: "Menlo, monospace"
  },
  fontSizes: {
    xs: "0.75rem",
    sm: "0.875rem",
    md: "1rem",
    lg: "1.125rem",
    xl: "1.25rem",
    "2xl": "1.5rem",
    "3xl": "1.875rem",
    "4xl": "2.25rem",
    "5xl": "3rem",
    "6xl": "3.75rem",
    "7xl": "4.5rem",
    "8xl": "6rem",
    "9xl": "8rem"
  },
  fontWeights: {
    hairline: 100,
    thin: 200,
    light: 300,
    normal: 400,
    medium: 500,
    semibold: 600,
    bold: 700,
    extrabold: 800,
    black: 900
  }
})

export default theme
