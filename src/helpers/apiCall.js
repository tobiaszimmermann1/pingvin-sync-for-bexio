import axios from "axios"

const apiCall = async (method, endpoint, params = null) => {
  try {
    if (method === "GET") {
      const response = await axios.get(`${pvLoonityAppLocalizer.loonityApiUrl}/${endpoint}`, {
        headers: {
          "X-WP-Nonce": pvLoonityAppLocalizer.nonce
        }
      })

      return response
    }

    if (method === "POST") {
      const response = await axios.post(`${pvLoonityAppLocalizer.loonityApiUrl}/${endpoint}`, params, {
        headers: {
          "X-WP-Nonce": pvLoonityAppLocalizer.nonce
        }
      })

      return response
    }
  } catch (error) {
    console.error("API call error:", error)
    return error
  }
}

export default apiCall
