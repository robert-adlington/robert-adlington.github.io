# Adlinkton Development Context

This document captures architectural decisions, lessons learned, and important implementation notes for the Adlinkton project.

## Lessons Learned

### Always Check Backend APIs Before Frontend Implementation

**Date**: 2026-01-11
**Context**: Implementing drag-and-drop database persistence for category reorganization

**What Happened**:
- Assumed database field name was `order_position` (a common convention)
- Implemented entire frontend persistence logic without checking backend
- Committed code and only discovered field mismatch after getting 400 Bad Request errors
- Had to debug, check backend, fix field names, and commit again

**What Should Have Been Done**:
1. **First**: Read `api/categories.php` to see what fields the endpoints expect
2. **Second**: Check `migrations/001_initial_schema.sql` to confirm database field names
3. **Third**: Implement handlers using the correct field names from the start

**Actual Field Names**:
- Backend uses `sort_order` not `order_position` for categories
- Backend's `reorderCategory()` requires `sort_order` field (line 354 in categories.php)
- Database schema defines `categories.sort_order` and `link_categories.sort_order`

**Impact**:
- Wasted 3 debugging iterations and multiple commits
- Research upfront would have taken minutes vs the debugging cycle which took much longer
- Created technical debt with extra logging code added during debugging

**Lesson**: Always verify backend API contracts and database schemas before implementing frontend features. The backend files are readily available - read them first.

---

## Architecture Decisions

### Drag-and-Drop Implementation: vue-draggable-next vs Custom HTML5

**Decision**: Use `vue-draggable-next` library instead of custom HTML5 drag-and-drop implementation

**Rationale**:
- Custom HTML5 drag-and-drop had persistent issues:
  - Event propagation problems through nested components
  - Vue reactivity conflicts (state updates not triggering re-renders)
  - Drag operations ending immediately
  - Complexity managing drop zones across recursive component hierarchy

- `vue-draggable-next` advantages:
  - Battle-tested wrapper around SortableJS
  - Built-in support for shared groups across containers
  - Handles all event management internally
  - Simpler code (~200 lines removed vs added)
  - Better Vue 3 reactivity integration

**Implementation Details**:
- All draggable containers share `group="items"` allowing cross-container dragging
- Each level (CategoryGrid, CategoryCard, SubcategoryItem) wraps content in VueDraggableNext
- Validation via `:move` prop prevents circular references
- Persistence via `@change` event handlers calling backend APIs

**Files Modified**:
- `CategoryGrid.vue`: Root-level category dragging
- `CategoryCard.vue`: Category content (subcategories + links)
- `SubcategoryItem.vue`: Recursive subcategory content

**Documented**: 2026-01-11

---

### Hierarchical Category System

**Architecture**: Categories can nest infinitely deep with mixed content (subcategories and links)

**Key Design Decisions**:
1. **Columns are purely visual** (CSS grid), not semantic containers
2. **Root level**: Only categories, no standalone links
3. **Drag groups**: All draggable containers share same group for free movement
4. **Circular reference prevention**: Can't drag category into its own descendant
5. **Database persistence**: Updates `parent_id` and `sort_order` on every move

**Documented**: 2026-01-11
