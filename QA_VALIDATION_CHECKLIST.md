# LGU IPMS - UI/UX REFACTOR VALIDATION & QA CHECKLIST

## PRE-DEPLOYMENT COMPREHENSIVE QA CHECKLIST

### ✅ DESIGN SYSTEM VALIDATION

**CSS Custom Properties**
- [ ] All 30+ CSS variables defined in `:root`
- [ ] Color palette displays correctly across all pages
- [ ] Spacing scale (xs to 2xl) is consistent
- [ ] Typography scales properly (12px to 40px)
- [ ] Shadow system renders without artifacts
- [ ] Border radius applies uniformly
- [ ] Transition timing feels natural (not too fast/slow)
- [ ] Z-index scale prevents overlapping issues

**Utility Classes**
- [ ] Display utilities work (.u-flex, .u-grid, .u-block)
- [ ] Spacing utilities apply correctly (.u-m-*, .u-p-*, .u-gap-*)
- [ ] Typography utilities render properly (.u-text-*, .u-font-*)
- [ ] Color utilities display correct colors (.u-text-*, .u-bg-*)
- [ ] Shadow utilities add appropriate depth (.u-shadow-*)
- [ ] Border utilities render correctly (.u-border-*)
- [ ] Flexbox utilities align content properly (.u-flex-*)
- [ ] Animation utilities trigger smoothly (.u-animate-*)

**Colors (WCAG AA Compliance)**
- [ ] Primary text (#1a1a1a) on white: ✓ 21:1 contrast
- [ ] Secondary text (#6b7280) on white: ✓ 4.5:1 contrast
- [ ] Primary blue (#1e3a8a) for interactive elements
- [ ] Error red (#ef4444): ✓ Accessible
- [ ] Success green (#10b981): ✓ Accessible
- [ ] Warning amber (#f59e0b): ✓ Accessible

---

### ✅ SIDEBAR TOGGLE VALIDATION

**Visual Design**
- [ ] Button appears top-left (44-48px size)
- [ ] Dark blue gradient background
- [ ] White hamburger/arrow icon
- [ ] Rounded corners (12px radius)
- [ ] Clear hover state (darker blue + scale up)
- [ ] Box shadow for elevation

**Behavior**
- [ ] Toggle button appears only when sidebar hidden
- [ ] Click expands sidebar smoothly
- [ ] Sidebar content becomes visible
- [ ] Main content adjusts width properly
- [ ] No layout shifts or jumps
- [ ] State persists on page reload
- [ ] Works with multiple pages

**Keyboard Support**
- [ ] Ctrl+S toggles sidebar
- [ ] Cmd+S works on Mac
- [ ] Focus outline visible on button
- [ ] Can tab to and activate button
- [ ] No keyboard traps

**Responsive**
- [ ] Works on 375px width (mobile)
- [ ] Touch target is at least 44x44px
- [ ] Doesn't overlap page content
- [ ] Positioning remains fixed when scrolling
- [ ] Works in landscape/portrait

**Accessibility**
- [ ] Button has proper `aria-label`
- [ ] Icon is clear and recognizable
- [ ] Tooltip shows on hover
- [ ] Focus state is visible
- [ ] Screen reader announces button

---

### ✅ NAVIGATION DROPDOWN VALIDATION

**Visual Design**
- [ ] Dropdown toggle has consistent styling
- [ ] Arrow icon rotates 180° when open
- [ ] Submenu items have proper indentation
- [ ] Active items highlighted (blue)
- [ ] Hover state shows subtle background
- [ ] Icon appears before text in submenu

**Animation**
- [ ] Open animation is smooth (250ms)
- [ ] Close animation is smooth
- [ ] Arrow rotation is fluid
- [ ] No flickering or jank
- [ ] Matches design system timing

**Behavior**
- [ ] Click toggle opens dropdown
- [ ] Click again closes dropdown
- [ ] Only one dropdown open at a time
- [ ] Click outside closes dropdown
- [ ] Escape key closes dropdown
- [ ] Clicking submenu item navigates
- [ ] Active page shows active styling

**Keyboard Navigation**
- [ ] Tab navigates to dropdown toggle
- [ ] Enter/Space opens dropdown
- [ ] Escape closes dropdown
- [ ] Can tab through submenu items
- [ ] No keyboard traps
- [ ] Tab order is logical

**Responsive**
- [ ] Dropdowns work on mobile
- [ ] Touch targets are adequate
- [ ] Doesn't overflow screen
- [ ] Works in collapsed sidebar view
- [ ] Submenu text hidden when collapsed
- [ ] Icons visible when collapsed

**Cross-Device**
- [ ] Desktop (1920x1080): ✓
- [ ] Laptop (1366x768): ✓
- [ ] Tablet (768px): ✓
- [ ] Mobile (375px): ✓
- [ ] Very wide (2560px): ✓

---

### ✅ LOGOUT BUTTON VALIDATION

**Visual Design**
- [ ] Positioned in sidebar footer
- [ ] Red/error color scheme (#ef4444)
- [ ] Icon + text clearly visible
- [ ] Proper spacing between icon and text
- [ ] Rounded corners match design system
- [ ] Hover state darker red
- [ ] Active state shows press effect

**Styling**
- [ ] Background color correct
- [ ] Text color (red) correct
- [ ] Icon rendered properly
- [ ] Hover background updates
- [ ] No text selection on double-click
- [ ] Disabled state obvious (if applicable)

**Behavior**
- [ ] Click triggers confirmation modal
- [ ] Modal appears immediately
- [ ] No double-clicks needed
- [ ] Button doesn't become disabled
- [ ] Can cancel logout (go back to page)
- [ ] Confirm logout redirects to login

**Accessibility**
- [ ] Button has semantic `<a>` or `<button>`
- [ ] Has `class="nav-logout"` for auto-wiring
- [ ] Focus outline visible
- [ ] Tab navigation works
- [ ] Screen reader announces "Logout"
- [ ] Keyboard can activate (Enter/Space)

**Mobile**
- [ ] Touch target at least 44x44px
- [ ] Easily tappable in sidebar
- [ ] No accidental clicks
- [ ] Works on both portrait/landscape

---

### ✅ LOGOUT CONFIRMATION MODAL VALIDATION

**Visual Design**
- [ ] Centered on screen
- [ ] Dark backdrop behind modal
- [ ] White background card
- [ ] Proper padding and spacing
- [ ] Rounded corners
- [ ] Shadow for elevation
- [ ] Icon clearly visible (🚪)
- [ ] Title readable (dark text)
- [ ] Message text clear
- [ ] Buttons well-spaced

**Content**
- [ ] Title: "Confirm Logout"
- [ ] Icon: 🚪 door emoji
- [ ] Message: "You will be logged out..."
- [ ] Cancel button text: "Cancel"
- [ ] Confirm button text: "Logout"

**Animation**
- [ ] Modal appears with bounce-in animation
- [ ] Backdrop fades in
- [ ] Smooth appearance (not jittery)
- [ ] Timing matches design system (250ms)

**Behavior**
- [ ] Cancel button closes without action
- [ ] Logout button redirects to logout.php
- [ ] Modal doesn't re-appear after logout
- [ ] Session actually destroyed on backend
- [ ] User redirected to login page
- [ ] Can't go back with browser back button

**Keyboard Support**
- [ ] Escape key closes modal
- [ ] Tab navigates between buttons
- [ ] Enter on Logout button confirms
- [ ] Enter on Cancel closes
- [ ] Space on buttons works
- [ ] Focus trapped within modal
- [ ] No focus escape possible

**Focus Management**
- [ ] Focus moves to modal when shown
- [ ] Focus doesn't leave modal (trap)
- [ ] Cancel button gets initial focus
- [ ] Focus returns to logout button if cancelled
- [ ] Screen reader announces modal

**Mobile**
- [ ] Full modal visible on 375px width
- [ ] Buttons large enough to tap (44px+)
- [ ] Text readable at mobile size
- [ ] No horizontal scrolling
- [ ] Portrait and landscape work

**Responsive**
- [ ] Works on all screen sizes
- [ ] Max-width constrains on large screens
- [ ] Padding adjusts on mobile
- [ ] Text scales appropriately

---

### ✅ TOAST NOTIFICATIONS VALIDATION

**Visual Design (Success)**
- [ ] Icon: ✓ (checkmark)
- [ ] Color: Green (#10b981)
- [ ] Title: Bold white text
- [ ] Message: Gray text below title
- [ ] Left border: 4px green
- [ ] Position: Top-right corner
- [ ] Shadow: Subtle but visible

**Visual Design (Error)**
- [ ] Icon: ✕ (cross)
- [ ] Color: Red (#ef4444)
- [ ] Title: Bold white text
- [ ] Left border: 4px red

**Visual Design (Warning)**
- [ ] Icon: ⚠ (warning sign)
- [ ] Color: Amber (#f59e0b)
- [ ] Title: Bold
- [ ] Left border: 4px amber

**Visual Design (Info)**
- [ ] Icon: ℹ (info)
- [ ] Color: Cyan (#0ea5e9)
- [ ] Left border: 4px cyan

**Animations**
- [ ] Slide in from right (smooth)
- [ ] Auto-dismiss with slide out
- [ ] Smooth animation (not jerky)
- [ ] Timing matches design (250ms)

**Behavior**
- [ ] Multiple toasts stack vertically
- [ ] No overlap between toasts
- [ ] Gap between stacked toasts
- [ ] Close button removes toast
- [ ] Auto-dismiss after set duration
- [ ] Success: 3 seconds
- [ ] Error: 5 seconds
- [ ] Warning: 4 seconds
- [ ] Info: 3 seconds

**Durations**
- [ ] Can customize duration
- [ ] Duration=0 prevents auto-dismiss
- [ ] Close button always works
- [ ] New toasts stack above old ones

**Responsive**
- [ ] Desktop: Top-right corner
- [ ] Mobile: Full width top (375-400px max)
- [ ] Padding appropriate
- [ ] Text readable
- [ ] No overflow

**Accessibility**
- [ ] Not relied on for critical messages
- [ ] Toast type communicated by color + icon
- [ ] Text is readable for all
- [ ] Close button accessible
- [ ] Screen reader announces toast

**Usage Examples**
```javascript
showSuccess('Saved', 'Project saved successfully');
showError('Error', 'Failed to save project');
toastManager.warning('Warning', 'Unsaved changes');
toastManager.info('Info', 'Loading data...');
```

---

### ✅ EMPTY STATE VALIDATION

**Visual Design**
- [ ] Large icon (4rem emoji or SVG)
- [ ] Title text (readable size)
- [ ] Message text (descriptive)
- [ ] Action button (primary color)
- [ ] Centered layout
- [ ] Adequate vertical spacing
- [ ] Professional appearance

**Content Examples**
```
Icon: 📭
Title: "No Projects Found"
Message: "Get started by creating your first infrastructure project"
Button: "Create New Project" → links to project_registration.php
```

**Behavior**
- [ ] Shows when table tbody is empty
- [ ] Hides when data is present
- [ ] Can toggle visibility with JavaScript
- [ ] Smooth transition (if faded)

**Examples per Page**
- Dashboard: "No Recent Projects"
- Contractors: "No Contractors Registered"
- Projects: "No Projects Created Yet"
- Budget: "No Budget Records"

**Responsive**
- [ ] Centered on all screen sizes
- [ ] Text readable on mobile
- [ ] Button tappable on mobile
- [ ] Adequate padding

---

### ✅ FORM VALIDATION

**Visual Design**
- [ ] Input focus shows blue glow (4px outline)
- [ ] Error inputs have red outline
- [ ] Error messages display below input
- [ ] Labels are dark blue and bold
- [ ] Consistent input heights (44px+)
- [ ] Consistent padding (12px+)
- [ ] Placeholder text visible

**States**
- [ ] Normal: Light gray border
- [ ] Focus: Blue border + glow
- [ ] Error: Red border + message
- [ ] Disabled (if applicable): Grayed out
- [ ] Success (optional): Green checkmark

**Validation**
- [ ] Required fields marked with *
- [ ] Real-time validation (on change)
- [ ] Clear error messages
- [ ] Error messages red
- [ ] Multiple errors display
- [ ] Error is cleared when fixed

**Accessibility**
- [ ] Labels associated with inputs
- [ ] for/id attributes correct
- [ ] Error messages linked to inputs
- [ ] aria-invalid set on errors
- [ ] aria-describedby points to error
- [ ] Tab navigates all inputs
- [ ] Focus visible on all inputs

**Keyboard**
- [ ] Tab moves to next field
- [ ] Shift+Tab moves backward
- [ ] Enter submits form (if only button)
- [ ] Escape doesn't interfere
- [ ] No keyboard traps

---

### ✅ TABLE STYLING

**Header Styling**
- [ ] Dark blue background (#1e3a8a)
- [ ] White text
- [ ] Bold font weight (700)
- [ ] Uppercase text transform
- [ ] Letter-spacing for clarity
- [ ] Proper padding (16-18px)
- [ ] Sticky position (scrolls with body)

**Row Styling**
- [ ] Alternating row colors (optional)
- [ ] Hover effect on rows
- [ ] Hover shows subtle blue tint
- [ ] No jarring color changes
- [ ] Subtle transition (smooth)

**Cell Styling**
- [ ] Consistent padding (16px)
- [ ] Border-bottom dividing rows
- [ ] Text left-aligned
- [ ] Readable font size
- [ ] Proper vertical alignment

**Status Badges**
- [ ] Color-coded by status
- [ ] Completed: Green gradient
- [ ] In Progress: Amber gradient
- [ ] Delayed: Red gradient
- [ ] Approved: Blue gradient
- [ ] Pending: Orange gradient
- [ ] Proper padding & border-radius
- [ ] Box shadow for depth

**Responsive**
- [ ] Horizontal scrollable on mobile
- [ ] Headers remain visible
- [ ] Not cramped on 375px
- [ ] Text readable at mobile size

**Accessibility**
- [ ] Proper table semantics
- [ ] Headers marked with `<th>`
- [ ] Data in `<td>`
- [ ] ARIA labeling if needed
- [ ] Keyboard accessible
- [ ] Screen reader friendly

---

### ✅ CROSS-PAGE CONSISTENCY

**Navigation**
- [ ] All pages have sidebar
- [ ] Sidebar styling matches
- [ ] Logo same on all pages
- [ ] Nav links same structure
- [ ] Dropdowns work same way
- [ ] Logout button same style

**Headers**
- [ ] Page title styling uniform
- [ ] Description text styling same
- [ ] Background treatment identical
- [ ] Size and spacing consistent

**Spacing**
- [ ] Margin between sections consistent
- [ ] Padding within cards consistent
- [ ] Gap between items uniform
- [ ] Responsive adjustments uniform

**Colors**
- [ ] Primary blue consistent (#1e3a8a)
- [ ] Text colors consistent
- [ ] Border colors consistent
- [ ] Shadow colors consistent

**Typography**
- [ ] Font Poppins on all pages
- [ ] Size scale consistent
- [ ] Weight usage consistent
- [ ] Line-height appropriate

**Components**
- [ ] Buttons styled uniformly
- [ ] Forms styled uniformly
- [ ] Tables styled uniformly
- [ ] Cards styled uniformly

---

### ✅ RESPONSIVE DESIGN (MOBILE)

**Viewport Sizes Tested**
- [ ] 375px (iPhone SE)
- [ ] 414px (iPhone 12)
- [ ] 768px (iPad)
- [ ] 1024px (iPad Pro)
- [ ] 1366px (Laptop)
- [ ] 1920px (Desktop)
- [ ] 2560px (4K Monitor)

**Mobile Behavior (375px)**
- [ ] No horizontal scrolling
- [ ] Text readable (16px+ minimum)
- [ ] Touch targets 44x44px minimum
- [ ] Buttons easily tappable
- [ ] Forms easy to fill on mobile
- [ ] Modal fits on screen
- [ ] Sidebar toggle works
- [ ] Dropdowns work on touch

**Sidebar Collapse**
- [ ] Sidebar hides on mobile (optional)
- [ ] Menu icon appears
- [ ] Toggle button functional
- [ ] Can navigate with sidebar closed
- [ ] Content expands to full width

**Tables**
- [ ] Horizontally scrollable on mobile
- [ ] Headers remain sticky
- [ ] No text cutoff
- [ ] Readable at small size

**Forms**
- [ ] Inputs stack vertically
- [ ] Labels readable
- [ ] Inputs full width
- [ ] Buttons full width
- [ ] Easy to submit on mobile

**Images**
- [ ] Scale properly
- [ ] No distortion
- [ ] Load quickly
- [ ] Accessible alt text

---

### ✅ ACCESSIBILITY (WCAG AA)

**Color Contrast**
- [ ] Text: #1a1a1a on white = 21:1 ✓
- [ ] Secondary: #6b7280 on white = 4.5:1 ✓
- [ ] All status badges readable
- [ ] All buttons readable
- [ ] All links readable
- [ ] All form fields readable

**Keyboard Navigation**
- [ ] All interactive elements accessible
- [ ] Tab order logical
- [ ] No keyboard traps
- [ ] Focus visible always
- [ ] Escape closes modals/dropdowns
- [ ] Enter activates buttons/links

**Focus Indicators**
- [ ] Visible on all interactive elements
- [ ] Thick enough to see (2px+)
- [ ] Color contrasts with background
- [ ] Not hidden by other elements
- [ ] Consistent across page
- [ ] No outline: none!

**Form Labels**
- [ ] Every input has label
- [ ] Label associated (for/id)
- [ ] Required field marked *
- [ ] Error messages clear
- [ ] Error linked to input

**Aria Attributes**
- [ ] aria-label on icon buttons
- [ ] aria-invalid on error inputs
- [ ] aria-describedby on error messages
- [ ] role attributes where needed
- [ ] aria-hidden on decorative icons

**Semantic HTML**
- [ ] `<button>` for buttons
- [ ] `<a>` for links
- [ ] `<button type="submit">` for form submit
- [ ] `<table>` with `<th>` for headers
- [ ] Heading hierarchy correct (h1 > h2 > h3)
- [ ] `<label>` for form labels
- [ ] `<form>` wrapper for forms

**Screen Reader Testing**
- [ ] Page title announced
- [ ] Navigation structure clear
- [ ] Form labels read
- [ ] Error messages read
- [ ] Modal title announced
- [ ] Buttons purpose clear
- [ ] Links destination clear

---

### ✅ CROSS-BROWSER COMPATIBILITY

**Chrome/Edge (Latest)**
- [ ] All features work
- [ ] CSS renders correctly
- [ ] Animations smooth
- [ ] No console errors
- [ ] Responsive works

**Firefox (Latest)**
- [ ] All features work
- [ ] CSS renders correctly
- [ ] Animations smooth
- [ ] No console errors

**Safari (Latest)**
- [ ] All features work
- [ ] CSS renders correctly
- [ ] Animations smooth
- [ ] No console errors
- [ ] -webkit prefixes OK

**Mobile Safari (iOS 14+)**
- [ ] Touch interactions work
- [ ] Responsive layout OK
- [ ] Form inputs work
- [ ] Modals display properly
- [ ] Notifications display

**Chrome Mobile (Android 10+)**
- [ ] Touch interactions work
- [ ] Responsive layout OK
- [ ] Form inputs work
- [ ] Modals display properly

---

### ✅ PERFORMANCE

**Load Time**
- [ ] CSS load time: <100ms
- [ ] JS load time: <100ms
- [ ] Total additional: <200ms
- [ ] No render-blocking CSS
- [ ] No defer on critical JS

**Runtime Performance**
- [ ] Animations 60fps (no jank)
- [ ] No layout thrashing
- [ ] Smooth scrolling
- [ ] Form interactions instant
- [ ] Dropdown toggle instant

**Bundle Sizes**
- [ ] design-system.css: <3KB gzipped
- [ ] components.css: <5KB gzipped
- [ ] component-utilities.js: <4KB gzipped
- [ ] Total new: <12KB gzipped

**Optimization**
- [ ] CSS minified
- [ ] JS minified
- [ ] Images optimized (if any)
- [ ] No duplicate code
- [ ] No unused CSS

---

### ✅ FUNCTIONALITY PRESERVED

**Backend Logic**
- [ ] Database queries unchanged
- [ ] API endpoints work
- [ ] Form submissions work
- [ ] Authentication unchanged
- [ ] Authorization unchanged

**Form Submissions**
- [ ] Submit to correct endpoint
- [ ] Data posted correctly
- [ ] Validation works (backend)
- [ ] Redirect works
- [ ] Success message shows

**Session Management**
- [ ] Login still works
- [ ] Session created properly
- [ ] Session persists
- [ ] Logout destroys session
- [ ] Re-login required after logout

**Page Navigation**
- [ ] All links work
- [ ] No 404 errors
- [ ] Correct pages load
- [ ] Query parameters preserved
- [ ] Anchor links work

**Data Display**
- [ ] Tables populate correctly
- [ ] Charts display (if any)
- [ ] Images load
- [ ] Metrics calculate correctly
- [ ] Lists display properly

---

### ✅ ERROR HANDLING

**Form Errors**
- [ ] Validation errors show
- [ ] Error messages clear
- [ ] Fields marked with error style
- [ ] User can fix and resubmit
- [ ] Errors clear when fixed

**Network Errors**
- [ ] Timeout handled gracefully
- [ ] User gets error message
- [ ] Retry possible
- [ ] No infinite loops

**JavaScript Errors**
- [ ] Console has no errors
- [ ] Page still usable
- [ ] Features degrade gracefully
- [ ] Error messages helpful

---

### ✅ MANUAL TESTING PROCEDURES

#### Test 1: Logout Flow (5 minutes)
1. Login to admin panel
2. Click "Logout" button (sidebar footer)
3. Verify modal appears with:
   - [ ] Door emoji 🚪
   - [ ] Title: "Confirm Logout"
   - [ ] Message: "You will be logged out..."
4. Click "Cancel" - should return to page (still logged in)
5. Click "Logout" again
6. Click "Logout" button in modal
7. Verify redirect to login page
8. Verify cannot access admin pages (session destroyed)
9. Login again successfully

#### Test 2: Dropdown Navigation (3 minutes)
1. Navigate to any admin page
2. Click "Project Registration" dropdown
3. Verify:
   - [ ] Arrow rotates 180°
   - [ ] Submenu items appear (with animation)
   - [ ] Each item is clickable
4. Hover over "New Project" - should highlight
5. Click "View All" - navigate to registered_projects.php
6. Wait for page load
7. Verify "Project Registration" dropdown is expanded on new page

#### Test 3: Sidebar Toggle (3 minutes)
1. On desktop (1366px+), sidebar visible
2. Click hamburger icon inside sidebar (bottom)
3. Sidebar collapses smoothly
4. Verify floating toggle button appears (top-left)
5. Main content expands to fill space
6. Click floating button
7. Sidebar expands smoothly
8. Floating button disappears
9. Refresh page - state persists (sidebar still visible)
10. Toggle again and refresh - verify state persists

#### Test 4: Toast Notifications (2 minutes)
1. Open browser Console (F12)
2. Type: `showSuccess('Success', 'This is a test')`
3. Verify green toast appears top-right
4. Auto-dismisses after 3 seconds
5. Type: `showError('Error', 'Something failed')`
6. Verify red toast appears
7. Click 'X' button - closes immediately
8. Type: `toastManager.warning('Be Careful', 'Warning message')`
9. Verify amber toast with ⚠ icon

#### Test 5: Form Validation (3 minutes)
1. Navigate to a form page (e.g., Project Registration)
2. Try to submit without filling required fields
3. Verify error messages appear
4. Verify fields have red outline
5. Fill in at least one field
6. Verify error clears for that field
7. Fill all required fields
8. Submit - should work
9. Verify toast says "Success" (if implemented)

#### Test 6: Empty State (2 minutes)
1. Navigate to "Registered Projects" page
2. If database is empty
3. Verify empty state displays:
   - [ ] Icon (📭 or equivalent)
   - [ ] Title: "No Projects Found"
   - [ ] Message: descriptive text
   - [ ] Button: "Create New Project"
4. Click button - navigate to project_registration.php
5. Create a new project
6. Return to Registered Projects
7. Verify empty state hidden
8. Verify table shows the new project

#### Test 7: Mobile Responsiveness (3 minutes)
1. Open DevTools (F12)
2. Toggle Device Toolbar (Ctrl+Shift+M)
3. Set to iPhone 12 (414px)
4. Refresh page
5. Verify layout looks good:
   - [ ] No horizontal scrolling
   - [ ] Text readable
   - [ ] Buttons tappable
   - [ ] Forms easy to fill
6. Navigate through pages
7. All dropdowns work
8. Toggle sidebar works
9. Switch to iPad (768px)
10. Repeat tests
11. Switch to landscape - verify works

#### Test 8: Keyboard Navigation (2 minutes)
1. Refresh page
2. Press Tab repeatedly
3. Verify Tab order is logical
4. Verify focus indicator visible
5. Press Ctrl+S - sidebar toggles
6. Navigate dropdown with Tab
7. Press Enter on dropdown - opens
8. Press Escape - closes
9. Navigate to button with Tab
10. Press Enter - button activates
11. Navigate to form input
12. Type - input accepts text

#### Test 9: Cross-Browser (20 minutes)
**Chrome/Edge:**
- [ ] Load page - no errors
- [ ] All features work
- [ ] Responsive works
- [ ] Animations smooth

**Firefox:**
- [ ] Load page - no errors
- [ ] All features work
- [ ] Responsive works

**Safari (if Mac):**
- [ ] Load page - no errors
- [ ] All features work
- [ ] Check -webkit prefixes OK

#### Test 10: Color Contrast (2 minutes)
1. Open online contrast checker
2. Test text colors:
   - Primary text (#1a1a1a) on white: 21:1 ✓
   - Secondary text (#6b7280) on white: 4.5:1 ✓
3. Test button colors - all readable
4. Test badge colors - all readable
5. Verify WCAG AA compliance

---

### ✅ FULL PAGE CHECKLIST (repeat for each page)

**All Admin Pages:**
- [ ] Dashboard
- [ ] Project Registration
- [ ] Registered Projects
- [ ] Progress Monitoring
- [ ] Budget & Resources
- [ ] Tasks & Milestones
- [ ] Contractors
- [ ] Registered Contractors
- [ ] Project Prioritization
- [ ] Settings
- [ ] Change Password
- [ ] Audit Logs  
(continuing...)

**For each page, verify:**
- [ ] Sidebar displays correctly
- [ ] Navigation dropdowns work
- [ ] Logout button present and styled
- [ ] Page title displays
- [ ] Content sections render
- [ ] Forms work (if present)
- [ ] Tables work (if present)
- [ ] No console errors
- [ ] Responsive on mobile
- [ ] All colors correct
- [ ] Spacing consistent

---

## SIGN-OFF

**Project:** LGU IPMS UI/UX Refactor
**Date:** February 12, 2026
**Lead Tester:** _________________
**Status:** [ ] PASS  [ ] FAIL  
**Notes:** ___________________

### Test Results
- Total Tests: 200+
- Passed: ____
- Failed: ____
- Blockers: ____
- Minor Issues: ____

**Go-Live Approved:** [ ] Yes [ ] No

**Signature:** _________________ **Date:** _________

---

## POST-LAUNCH MONITORING (First Week)

- [ ] Monitor console errors from live users
- [ ] Check analytics for page load times
- [ ] Gather user feedback via survey
- [ ] Monitor performance metrics
- [ ] Check mobile usage patterns
- [ ] Verify no database issues from new code
- [ ] check email for support tickets

**Issues Found:**
1. _________________ (Severity: High/Med/Low)
2. _________________ (Severity: High/Med/Low)
3. _________________ (Severity: High/Med/Low)

**Resolution Status:**
- Critical issues: Within 24 hours
- High priority: Within 48 hours
- Medium: Within 1 week
- Low: Backlog for next update

