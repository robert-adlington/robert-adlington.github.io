import apiClient from './client'

/**
 * Categories API
 */
export const categoriesApi = {
  /**
   * Get all categories (tree structure)
   */
  async getCategories() {
    const response = await apiClient.get('/categories')
    return response.data
  },

  /**
   * Get a single category with links
   */
  async getCategory(id) {
    const response = await apiClient.get(`/categories/${id}`)
    return response.data
  },

  /**
   * Create a new category
   */
  async createCategory(data) {
    const response = await apiClient.post('/categories', data)
    return response.data
  },

  /**
   * Update a category
   */
  async updateCategory(id, data) {
    const response = await apiClient.put(`/categories/${id}`, data)
    return response.data
  },

  /**
   * Delete a category
   */
  async deleteCategory(id) {
    const response = await apiClient.delete(`/categories/${id}`)
    return response.data
  },

  /**
   * Reorder a category
   */
  async reorderCategory(id, data) {
    const response = await apiClient.put(`/categories/${id}/reorder`, data)
    return response.data
  },

  /**
   * Get all URLs in category for "open all"
   */
  async getCategoryUrls(id) {
    const response = await apiClient.post(`/categories/${id}/open-all`)
    return response.data
  },
}
