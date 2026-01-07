import axios from 'axios'

// Create axios instance with base configuration
// Note: Using query parameter routing since .htaccess and PATH_INFO don't work on this hosting
const apiClient = axios.create({
  baseURL: '/projects/adlinkton/api/index.php',
  headers: {
    'Content-Type': 'application/json',
  },
  withCredentials: true, // Include session cookies
})

// Request interceptor to convert URL paths to query parameters
apiClient.interceptors.request.use(
  (config) => {
    // Convert /links to ?endpoint=/links
    if (config.url && config.url !== '/') {
      const url = config.url.startsWith('/') ? config.url : '/' + config.url
      config.url = ''
      config.params = {
        ...config.params,
        endpoint: url
      }
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Request interceptor for authentication
apiClient.interceptors.request.use(
  (config) => {
    // Add CSRF token if available
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
    if (csrfToken) {
      config.headers['X-CSRF-Token'] = csrfToken
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response interceptor for error handling
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Redirect to main page with return URL if unauthorized
      const returnUrl = encodeURIComponent(window.location.pathname)
      window.location.href = `/index.html?return=${returnUrl}`
    }
    return Promise.reject(error)
  }
)

export default apiClient
