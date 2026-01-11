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

### Always Check Event Handler Signatures Before Emitting Events

**Date**: 2026-01-11
**Context**: Same drag-and-drop implementation - fixing SubcategoryItem reload logic

**What Happened**:
- After moving a category in SubcategoryItem, wanted to trigger parent reload
- Emitted `emit('link-click')` with no argument, thinking it was a generic reload signal
- CategoryCard's `handleLinkClick(link)` expects a link object parameter
- Caused: `TypeError: Cannot read properties of undefined (reading 'id')`
- Only discovered when testing the drag operation

**What Should Have Been Done**:
1. **First**: Check CategoryCard's `handleLinkClick` function to see what it expects
2. **Second**: Verify if this event is meant for link clicks or generic reloads (it's for link clicks)
3. **Third**: Question if a reload is even needed (vue-draggable-next handles UI updates via v-model)

**The Real Solution**:
- No emit needed at all - vue-draggable-next's v-model handles UI updates automatically
- Database is updated asynchronously via API calls
- Event was being misused as a generic "reload parent" signal

**Impact**:
- Runtime error that crashed the drag operation after it completed
- Another debugging cycle that could have been avoided
- Same pattern as the backend API error - didn't check the other side of the interface

**Pattern Identified**: This is the **second occurrence** of not checking interface contracts:
1. First: Didn't check backend API → wrong field name
2. This: Didn't check event handler → wrong parameter

**Lesson**: Before implementing any interface interaction (API calls, event emits, props, function calls):
1. **Read the receiver first** - what does the other side expect?
2. **Verify the contract** - what data/format is required?
3. **Question the approach** - is this even the right way to solve the problem?

This applies to: backend APIs, event handlers, props, callbacks, and any other interface boundary.

---

### Understand Library APIs Before Using Them

**Date**: 2026-01-11
**Context**: Same drag-and-drop implementation - circular reference validation

**What Happened**:
- Implemented `:move` validation callback to prevent circular references
- Assumed `:move` received the target parent container
- Actually receives the item being hovered over for positioning (not the parent)
- Validation logic was backwards and checking the wrong relationship
- Caused false positives: blocked valid moves (e.g., subcategory to root)
- Also failed to reload category tree after successful moves, causing UI desync

**What the :move Callback Actually Does**:
- Called when hovering over items during drag for sort positioning
- Receives `draggedContext.element` (item being dragged) and `relatedContext.element` (item being hovered)
- Purpose: Determine if item can be placed at that position in the list
- **Does NOT** provide information about parent-child relationships
- Parent is determined by which VueDraggableNext container you drop into, not which item you hover over

**What Should Have Been Done**:
1. **First**: Read vue-draggable-next documentation for `:move` callback contract
2. **Second**: Understand that container determines parent, not hover target
3. **Third**: Validate in `@change` handler where we know the parent container
4. **Fourth**: Ensure tree reload after database updates

**The Real Solution**:
- Remove `:move` validation (can't properly check parent-child relationships)
- Validate in `@change` handler: `hasDescendant(draggedItem.id, parentContainer.id)`
- Emit `category-moved` event to trigger tree reload from server
- This ensures both validation correctness and UI consistency

**Impact**:
- False circular reference warnings blocking valid operations
- Items disappearing after page refresh (DB updated but UI not reloaded)
- Another complete rewrite of validation logic
- Yet another debugging cycle

**Pattern Identified**: This is the **third occurrence** of not checking interface contracts:
1. First: Didn't check backend API → wrong field name (`order_position` vs `sort_order`)
2. Second: Didn't check event handler → wrong parameter (emitted without arguments)
3. Third: Didn't check library API → wrong assumptions about `:move` callback

**Lesson**: Before using ANY library feature or API:
1. **Read the documentation** - what does this function/callback actually provide?
2. **Understand the contract** - what data is available? What's its purpose?
3. **Verify assumptions** - test your understanding before building on it
4. **Consider side effects** - does the operation need follow-up actions (like reloading)?

This is the same fundamental error repeated three times: **implementing one side of an interface without understanding what the other side provides**.

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
