import axios from 'axios'

// Create axios instance with base configuration
const apiClient = axios.create({
  baseURL: '/adlington/projects/adlinkton/api',
  headers: {
    'Content-Type': 'application/json',
  },
  withCredentials: true, // Include session cookies
})

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
      // Redirect to login if unauthorized
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default apiClient
