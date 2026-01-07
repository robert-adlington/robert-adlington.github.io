import apiClient from './client'

/**
 * Links API
 */
export const linksApi = {
  /**
   * Get all links with optional filters
   */
  async getLinks(params = {}) {
    const response = await apiClient.get('/links', { params })
    return response.data
  },

  /**
   * Get a single link by ID
   */
  async getLink(id) {
    const response = await apiClient.get(`/links/${id}`)
    return response.data
  },

  /**
   * Create a new link
   */
  async createLink(data) {
    const response = await apiClient.post('/links', data)
    return response.data
  },

  /**
   * Update a link
   */
  async updateLink(id, data) {
    const response = await apiClient.put(`/links/${id}`, data)
    return response.data
  },

  /**
   * Delete a link
   */
  async deleteLink(id) {
    const response = await apiClient.delete(`/links/${id}`)
    return response.data
  },

  /**
   * Record link access
   */
  async recordAccess(id) {
    const response = await apiClient.post(`/links/${id}/open`)
    return response.data
  },

  /**
   * Reorder a link
   */
  async reorderLink(id, data) {
    const response = await apiClient.put(`/links/${id}/reorder`, data)
    return response.data
  },

  /**
   * Bulk operations on links
   */
  async bulkOperation(data) {
    const response = await apiClient.post('/links/bulk', data)
    return response.data
  },
}
