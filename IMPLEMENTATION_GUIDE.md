/**
 * LGU IPMS - UI/UX REFACTOR IMPLEMENTATION GUIDE
 * Step-by-Step Rollout Plan
 */

# PHASE 1: SETUP & FOUNDATION (WEEK 1)

## Step 1.1: Add Design System & Components CSS to All Admin Pages

Update the `<head>` section of EVERY admin page to include:

```html
<!-- Design System & Components -->
<link rel="stylesheet" href="../assets/css/design-system.css">
<link rel="stylesheet" href="../assets/css/components.css">
<link rel="stylesheet" href="../assets/css/admin.css?v=timestamp">
```

**Files to update:**
1. admin/dashboard.php
2. admin/budget_resources.php
3. admin/progress_monitoring.php
4. admin/tasks_milestones.php
5. admin/project_registration.php
6. admin/registered_projects.php
7. admin/contractors.php
8. admin/registered_contractors.php
9. admin/project-prioritization.php
10. admin/settings.php
11. admin/forgot-password.php
12. admin/change-password.php
13. admin/manage-employees.php
14. admin/audit-logs.php

---

## Step 1.2: Add Component Utilities JavaScript

Add to the `<body>` BEFORE closing tag (after existing scripts):

```html
<!-- Component Utilities: Dropdowns, Modals, Toast, etc. -->
<script src="../assets/js/component-utilities.js"></script>
```

Add to EVERY admin page at the end of the body.

---

## Step 1.3: Create Sidebar Toggle Button Wrapper

Add this HTML right after the opening `<body>` tag in admin pages:

```html
<div class="sidebar-toggle-wrapper">
    <button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
    </button>
</div>
```

**Note:** This is the floating button that appears when sidebar is hidden. Place it at the top-level body element.

---

# PHASE 2: COMPONENT UPDATES (WEEK 2)

## Step 2.1: Standardize Navigation Dropdowns

**Current HTML structure (in nav):**
```html
<div class="nav-item-group">
    <a href="..." class="nav-main-item" id="projectRegToggle">
        <img src="..." class="nav-icon">
        Project Registration
        <span class="dropdown-arrow">▼</span>
    </a>
    <div class="nav-submenu" id="projectRegSubmenu">
        <a href="..." class="nav-submenu-item">
            <span class="submenu-icon">➕</span>
            <span>New Project</span>
        </a>
        <a href="..." class="nav-submenu-item">
            <span class="submenu-icon">📋</span>
            <span>View All</span>
        </a>
    </div>
</div>
```

**Update CSS classes for consistency:**
- Keep `nav-item-group` as container
- Keep `nav-main-item` as toggle button
- Update `.nav-submenu` styling (already done in components.css)
- Ensure all dropdowns use consistent class names

**No HTML changes needed** - The new CSS in `components.css` and JavaScript in `component-utilities.js` will handle all styling and behavior automatically.

---

## Step 2.2: Standardize Logout Button

**Current HTML (in sidebar footer):**
```html
<div class="ac-723b1a7b">
    <a href="/admin/logout.php" class="ac-bb30b003">
        <svg>...</svg>
        <span>Logout</span>
    </a>
</div>
```

**Update to:**
```html
<div class="nav-action-footer">
    <a href="/admin/logout.php" class="btn-logout nav-logout">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
            <polyline points="16 17 21 12 16 7"></polyline>
            <line x1="21" y1="12" x2="9" y2="12"></line>
        </svg>
        <span>Logout</span>
    </a>
</div>
```

**Update in sidebar:**
- Replace auto-generated class `ac-723b1a7b` with `.nav-action-footer`
- Replace auto-generated class `ac-bb30b003` with `.btn-logout nav-logout`
- Add SVG logout icon
- The logout confirmation will auto-wire via `LogoutConfirmationManager`

---

## Step 2.3: Wire Logout Confirmation

The `LogoutConfirmationManager` class automatically handles all logout links with:
- Selector: `.nav-logout`
- Selector: `.logout-btn`
- Selector: `a[href*="logout"]`

**No additional code needed** - Just add the class `.nav-logout` to logout links.

---

# PHASE 3: FORM & TABLE IMPROVEMENTS (WEEK 3)

## Step 3.1: Add Empty State to Tables

**Add this HTML when table tbody is empty:**

```html
<table class="projects-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Location</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <!-- Empty state when no data -->
    </tbody>
</table>

<!-- Empty state container -->
<div class="empty-state" id="emptyState" style="display: none;">
    <div class="empty-state-icon">📭</div>
    <h3 class="empty-state-title">No Projects Found</h3>
    <p class="empty-state-message">
        Get started by creating your first project.
    </p>
    <a href="project_registration.php" class="empty-state-action">
        Create Project
    </a>
</div>
```

**JavaScript to show/hide:**
```javascript
function updateTableEmptyState(tableBody, emptyStateDiv) {
    if (tableBody.children.length === 0) {
        tableBody.parentElement.style.display = 'none';
        emptyStateDiv.style.display = 'flex';
    } else {
        tableBody.parentElement.style.display = 'table';
        emptyStateDiv.style.display = 'none';
    }
}

// Call after loading data
const tbody = document.querySelector('.projects-table tbody');
const emptyState = document.getElementById('emptyState');
updateTableEmptyState(tbody, emptyState);
```

---

## Step 3.2: Use Toast Notifications

**Replace alert() calls with:**

```javascript
// Success
showSuccess('Project Created', 'Your project has been created successfully');

// Error
showError('Error', 'Failed to create project. Please try again.');

// Warning
window.toastManager.warning('Warning', 'Some fields are incomplete');

// Info
window.toastManager.info('Info', 'Loading data...');
```

**In forms:**
```javascript
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form)
        });
        
        if (response.ok) {
            showSuccess('Success', 'Form submitted successfully');
            // Reload or redirect after short delay
            setTimeout(() => location.reload(), 1500);
        } else {
            showError('Error', 'Submission failed');
        }
    } catch (err) {
        showError('Error', err.message);
    }
});
```

---

# PHASE 4: ACCESSIBILITY & POLISH (WEEK 4)

## Step 4.1: Keyboard Navigation

Dropdowns now support:
- **Tab**: Navigate to dropdown toggle
- **Enter/Space**: Open dropdown
- **Escape**: Close dropdown
- **Arrow Keys**: Future enhancement (can be added)

*Already implemented in `DropdownManager` class*

---

## Step 4.2: Focus States

All interactive elements have proper focus states:
- Buttons: `outline: 2px solid var(--color-primary)`
- Links: Same as buttons
- Modals: Focus-trap on confirm button

---

## Step 4.3: Color Contrast

All text colors meet WCAG AA standards:
- Primary text: #1a1a1a on white/light backgrounds
- Secondary text: #6b7280 (4.5:1 contrast ratio)
- Status badges: High contrast colors

---

# PHASE 5: TESTING & VALIDATION (WEEK 5)

## QA Checklist

### Pre-Deployment Testing

- [ ] **Design System**
  - [ ] Colors display correctly in all browsers
  - [ ] Typography scales properly on mobile
  - [ ] Spacing is consistent across pages
  - [ ] Shadows render without visual artifacts

- [ ] **Sidebar Toggle**
  - [ ] Toggle button appears when sidebar is hidden
  - [ ] Click toggles sidebar smoothly
  - [ ] Keyboard shortcut (Ctrl+S) works
  - [ ] State persists on page reload
  - [ ] Works on mobile (responsive)

- [ ] **Navigation Dropdowns**
  - [ ] All dropdowns open/close smoothly
  - [ ] Only one dropdown open at a time
  - [ ] Clicking outside closes dropdown
  - [ ] Arrow rotates when dropdown opens
  - [ ] Active items are highlighted
  - [ ] Keyboard navigation works (Tab, Enter, Escape)

- [ ] **Logout Button**
  - [ ] Button is visible in sidebar
  - [ ] Proper red styling on hover
  - [ ] Confirmation modal appears on click
  - [ ] Can cancel logout
  - [ ] Logout works from confirmation
  - [ ] Works across all admin pages

- [ ] **Confirmation Modal**
  - [ ] Appears with correct icon/title/message
  - [ ] Confirm button triggers action
  - [ ] Cancel button does nothing
  - [ ] Clicking backdrop closes modal
  - [ ] Escape key closes modal
  - [ ] Focus is managed properly
  - [ ] Responsive on mobile

- [ ] **Toast Notifications**
  - [ ] Show in top-right corner
  - [ ] Auto-dismiss after timeout
  - [ ] Manual close button works
  - [ ] Different types display correct colors
  - [ ] Multiple toasts stack vertically
  - [ ] No overlap or layout shift

- [ ] **Empty States**
  - [ ] Display when table has no data
  - [ ] Action button links to correct page
  - [ ] Text is clear and helpful
  - [ ] Icon displays properly

- [ ] **Forms**
  - [ ] Input focus state shows blue glow
  - [ ] Labels are readable
  - [ ] Consistent padding/height
  - [ ] Validation messages appear
  - [ ] Error states are clear
  - [ ] Disabled state is obvious

- [ ] **Tables**
  - [ ] Headers are dark blue with white text
  - [ ] Row hover effect is subtle
  - [ ] Status badges are color-coded
  - [ ] Table is scrollable on mobile
  - [ ] Borders are subtle but clear

- [ ] **Cross-Page Consistency**
  - [ ] All admin pages look the same
  - [ ] Navigation is consistent
  - [ ] Spacing is uniform
  - [ ] Button styles match
  - [ ] Colors match design system

- [ ] **Responsive (Mobile)**
  - [ ] Layout works on 375px width
  - [ ] Touch targets are at least 44x44px
  - [ ] Sidebar toggle is accessible
  - [ ] Modal fits on screen
  - [ ] Tables are scrollable
  - [ ] Text is readable (no small fonts)

- [ ] **Accessibility**
  - [ ] Color contrast meets WCAG AA
  - [ ] Focus outlines are visible
  - [ ] Form labels are associated
  - [ ] Buttons have proper aria-label
  - [ ] Modal is trapped (focus doesn't escape)
  - [ ] Screen reader announces actions
  - [ ] Keyboard-only navigation works

- [ ] **Cross-Browser**
  - [ ] Chrome/Edge (latest)
  - [ ] Firefox (latest)
  - [ ] Safari (latest)
  - [ ] Mobile Safari (iOS)
  - [ ] Chrome Mobile (Android)

- [ ] **Performance**
  - [ ] No layout shifts/CLS issues
  - [ ] Animations are smooth (60fps)
  - [ ] Page load time acceptable
  - [ ] CSS is not duplicated
  - [ ] JavaScript is minified

- [ ] **Functionality Preserved**
  - [ ] All existing features work
  - [ ] Form submissions work
  - [ ] API calls still function
  - [ ] Database operations not affected
  - [ ] Session management works
  - [ ] Authentication still works
  - [ ] No console errors

---

## Manual Testing Steps

### Test Logout Flow
1. Login to admin panel
2. Click Logout button (in sidebar footer)
3. Confirm modal appears
4. Verify title: "Confirm Logout"
5. Click "Cancel" - modal closes, stay logged in
6. Click Logout again
7. Click "Logout" - redirects to login page
8. Verify session is destroyed

### Test Dropdown Navigation
1. Click "Project Registration" dropdown
2. Verify arrow rotates 180°
3. Verify submenu items appear with animation
4. Hover over submenu item - should highlight blue
5. Click on submenu - navigate to page, dropdown closes
6. Click "Contractors" dropdown - Project Registration dropdown closes
7. Press Escape - dropdown closes
8. Click outside dropdown - closes

### Test Sidebar Toggle
1. Click hamburger icon (if visible) or use Ctrl+S
2. Sidebar collapses smoothly
3. Main content expands
4. Floating toggle button appears (top-left)
5. Icons are visible in collapsed sidebar
6. Click floating button or Ctrl+S again
7. Sidebar expands smoothly
8. Refresh page - state persists
9. On mobile - sidebar collapse works smoothly

### Test Empty State
1. Navigate to "Registered Projects"
2. If no projects exist, show empty state:
   - Icon displays
   - Text: "No Projects Found"
   - Button: "Create Project"
   - Click button - navigate to project_registration.php
3. Add a project
4. Page reloads
5. Empty state disappears
6. Table shows project

### Test Toast Notifications
1. Submit a form successfully
2. Toast appears top-right:
   - Icon (✓)
   - Title: "Success"
   - Message: "Form submitted successfully"
   - Auto-dismisses after 3 seconds
3. Try form validation error
4. Toast appears top-right:
   - Icon (✕)
   - Title: "Error"
   - Message: specific error
   - Stays until manual close or 5 seconds
5. Multiple toasts stack

---

## Rollout Strategy

### Option A: Big Bang (All Pages at Once)
- Update all admin pages simultaneously
- Test everything in production environment before launch
- **Risk**: High (all pages affected)
- **Benefit**: Consistent experience immediately

### Option B: Phased (Recommended)
1. **Week 1**: Dashboard + Settings
2. **Week 2**: Project pages (Registration, Listed)
3. **Week 3**: Budget + Monitoring
4. **Week 4**: Contractors + Tasks
5. **Week 5**: Remaining pages + full QA

**Advantage**: Issues caught early, easily reverted per page

### Option C: Gradual (By Feature)
1. Roll out sidebar toggle to all pages
2. Roll out dropdowns to all pages
3. Roll out logout confirmation
4. Roll out toast notifications
5. Roll out empty states

**Advantage**: Easy to identify which feature causes issues

---

## Rollback Plan

If issues occur:

```bash
# Revert component CSS
git revert <commit_hash>

# Or manually:
# 1. Remove new CSS links from HTML
# 2. Remove new JS links from HTML
# 3. Restore old admin.css if needed
# 4. Clear browser caches
```

---

## Post-Launch Monitoring

- Monitor console errors in production
- Check analytics for page performance
- Gather user feedback
- Monitor mobile usage
- Check accessibility reports

