# LGU IPMS Restructuring Plan

## Current Issues Identified

1. **Inconsistent file locations**: Files scattered across multiple directories without clear organization
2. **Poor naming conventions**: Mix of camelCase, snake_case, and unclear names (e.g., "style - Copy.css")
3. **Broken relative paths**: Multiple `require` statements using incorrect relative paths
4. **Duplicate file inclusions**: Config files included multiple times (config-path.php included twice in some files)
5. **Mixed asset locations**: Images and resources scattered (logocityhall.png at root, images in various folders)
6. **No shared components**: Header, footer, sidebar duplicated across pages
7. **Unclear API structure**: No dedicated API folder for backend endpoints
8. **Security files at root**: Security and database files at root level, not properly organized

---

## Proposed New Folder Structure

```
lgu-ipms/
├── public/                          # Entry point for web server
│   ├── index.php                   # Homepage/portal
│   └── .htaccess                   # URL rewriting rules
│
├── app/                            # Application logic and pages
│   ├── auth/
│   │   ├── login.php              # User login page
│   │   ├── register.php           # User registration
│   │   └── logout.php             # Session termination
│   │
│   ├── admin/                      # Admin dashboard and modules
│   │   ├── dashboard.php
│   │   ├── budget/
│   │   │   ├── index.php
│   │   │   ├── budget.js
│   │   │   └── budget-api.php
│   │   ├── contractors/
│   │   │   ├── index.php
│   │   │   ├── contractors.js
│   │   │   └── contractors-api.php
│   │   ├── projects/
│   │   │   ├── index.php
│   │   │   ├── projects.js
│   │   │   └── projects-api.php
│   │   ├── progress/
│   │   │   ├── index.php
│   │   │   ├── progress.js
│   │   │   └── progress-api.php
│   │   ├── priorities/
│   │   │   ├── index.php
│   │   │   ├── priorities.js
│   │   │   └── priorities-api.php
│   │   ├── tasks/
│   │   │   ├── index.php
│   │   │   ├── tasks.js
│   │   │   └── tasks-api.php
│   │   └── reports/
│   │       └── index.php
│   │
│   └── user/                       # User pages
│       ├── dashboard.php
│       ├── feedback.php
│       ├── progress-monitoring.php
│       ├── settings.php
│       └── create-account.php
│
├── api/                            # RESTful API endpoints
│   ├── projects/
│   │   ├── list.php
│   │   ├── create.php
│   │   ├── update.php
│   │   └── delete.php
│   ├── contractors/
│   │   ├── list.php
│   │   ├── create.php
│   │   └── delete.php
│   ├── feedback/
│   │   ├── list.php
│   │   ├── create.php
│   │   └── update.php
│   ├── tasks/
│   │   ├── list.php
│   │   ├── create.php
│   │   └── update.php
│   └── common/
│       ├── response.php            # API response handler
│       └── validator.php           # Input validation
│
├── includes/                       # Shared PHP components
│   ├── config.php                 # Database and app config
│   ├── database.php               # Database connection
│   ├── auth.php                   # Authentication functions
│   ├── security.php               # Security functions
│   ├── header.php                 # Header component
│   ├── footer.php                 # Footer component
│   ├── sidebar.php                # Sidebar navigation
│   ├── navbar.php                 # Top navigation bar
│   ├── session-manager.php        # Session handling
│   └── helpers.php                # Utility functions
│
├── assets/                         # Static assets
│   ├── css/
│   │   ├── main.css              # Main styles
│   │   ├── admin.css             # Admin panel styles
│   │   ├── user.css              # User page styles
│   │   ├── responsive.css        # Mobile responsive styles
│   │   └── themes.css            # Theme variations
│   ├── js/
│   │   ├── main.js               # Core functionality
│   │   ├── admin.js              # Admin functionality
│   │   ├── user.js               # User functionality
│   │   ├── security.js           # Security features
│   │   ├── validation.js         # Form validation
│   │   └── utils.js              # Utility functions
│   ├── images/
│   │   ├── logo.png
│   │   ├── icons/
│   │   │   ├── dashboard.png
│   │   │   ├── projects.png
│   │   │   ├── contractors.png
│   │   │   ├── budget.png
│   │   │   ├── progress.png
│   │   │   ├── tasks.png
│   │   │   ├── priorities.png
│   │   │   ├── user.png
│   │   │   ├── settings.png
│   │   │   └── logout.png
│   │   └── gallery/
│   │       ├── road.jpg
│   │       ├── construction.jpg
│   │       ├── drainage.jpg
│   │       └── bridge.jpg
│   ├── fonts/                    # Custom fonts directory
│   └── vendor/                   # Third-party libraries
│       └── (Bootstrap, PHPMailer, etc.)
│
├── config/                        # Configuration files
│   ├── database.php              # Database credentials
│   ├── app.php                   # Application settings
│   ├── paths.php                 # Path constants
│   └── security.php              # Security settings
│
├── storage/                       # Uploads and temporary files
│   ├── uploads/
│   │   ├── user-documents/
│   │   ├── project-files/
│   │   └── contractor-docs/
│   └── cache/
│
├── database/
│   ├── migrations/               # Database migrations
│   ├── seeds/                    # Database seeds
│   └── backups/                  # Database backups
│       ├── lgu_ipms.sql
│       ├── db_setup.sql
│       └── feedback.sql
│
├── docs/                         # Documentation
│   ├── API.md                   # API documentation
│   ├── DATABASE.md              # Database schema
│   ├── INSTALLATION.md          # Setup instructions
│   ├── BEST_PRACTICES.md        # Development standards
│   └── RESTRUCTURING.md         # This restructuring plan
│
└── .env                          # Environment variables (not in git)
```

---

## Key Improvements

### 1. **Organized Structure**
- Clear separation of concerns (app, api, includes, assets)
- Logical grouping of related functionality
- Easy to find and maintain files

### 2. **Consistent Naming Conventions**
- Files use lowercase with hyphens: `user-dashboard.php` → `dashboard.php`
- API endpoints use REST patterns: `create.php`, `update.php`, `delete.php`
- CSS files are semantic: `main.css`, `admin.css`, `user.css`
- JS files match PHP structure for easy correlation

### 3. **Centralized Configuration**
- Single source of truth for database and app config
- Path constants for easy relative URL generation
- Environment variables support for different deployment environments

### 4. **Reusable Components**
- Header, footer, sidebar, navbar as includes
- No duplication of common HTML/styling
- Easy updates to navigation across all pages

### 5. **Proper Asset Organization**
- CSS, JS, images in dedicated folders with subfolders
- Easy to include and reference assets
- Better cache-busting strategies

### 6. **API Structure**
- Clear separation between web pages and API endpoints
- Follows REST principles
- Common response handlers and validators

### 7. **Security Improvements**
- Configuration files in dedicated config folder
- No sensitive files at root level
- Better access control with proper directory structure

---

## Migration Path

1. Create new directory structure
2. Copy files to new locations with renamed conventions
3. Update all PHP require/include statements
4. Update all CSS/JS link references
5. Update all image path references
6. Create unified include files (header, footer, sidebar)
7. Test all functionality
8. Remove old directories

---

## File Mapping (Old → New)

| Old Location | New Location | Changes |
|---|---|---|
| `index.php` | `public/index.php` | Entry point |
| `admin/admin.php` | `app/auth/login.php` | Admin login |
| `user-dashboard/user-login.php` | `app/auth/login.php` | User login (merge/rename) |
| `user-dashboard/create.php` | `app/auth/register.php` | Registration |
| `user-dashboard/user-dashboard.php` | `app/user/dashboard.php` | User dashboard |
| `user-dashboard/user-feedback.php` | `app/user/feedback.php` | User feedback |
| `user-dashboard/user-settings.php` | `app/user/settings.php` | User settings |
| `user-dashboard/user-progress-monitoring.php` | `app/user/progress-monitoring.php` | User progress |
| `admin/dashboard/dashboard.php` | `app/admin/dashboard.php` | Admin dashboard |
| `admin/project-registration/project_registration.php` | `app/admin/projects/index.php` | Projects module |
| `admin/budget-resources/budget_resources.php` | `app/admin/budget/index.php` | Budget module |
| `admin/contractors/contractors.php` | `app/admin/contractors/index.php` | Contractors module |
| `admin/progress-monitoring/progress_monitoring.php` | `app/admin/progress/index.php` | Progress module |
| `admin/task-milestone/tasks_milestones.php` | `app/admin/tasks/index.php` | Tasks module |
| `admin/project-prioritization/project-prioritization.php` | `app/admin/priorities/index.php` | Priorities module |
| `session-auth.php` | `includes/auth.php` | Auth functions |
| `database.php` | `includes/database.php` | DB connection |
| `config-path.php` | `includes/helpers.php` | Path helpers |
| `security-no-back.js` | `assets/js/security.js` | Security JS |
| `style.css` & `style - Copy.css` | `assets/css/main.css` | Main styles |
| Various admin CSS | `assets/css/admin.css` | Admin styles |
| Various user CSS | `assets/css/user.css` | User styles |
| Images (scattered) | `assets/images/` | Centralized |
| API files | `api/` | New API layer |

---

## Critical Path Dependencies to Fix

### PHP Includes
- Replace all `require 'database.php'` with `require __DIR__ . '/../../includes/database.php'`
- Or better: Set up a ROOT constant in each file
- Create a bootstrap file that sets up all paths once

### CSS Links
- Replace `<link href="style - Copy.css">` with `<link href="/assets/css/main.css">`
- Use root-relative paths (`/assets/...`) for consistency

### JavaScript Sources
- Replace `<script src="security-no-back.js">` with `<script src="/assets/js/security.js">`
- Update all data URLs to use API routes

### Image Paths
- Replace scattered image references with centralized `/assets/images/`
- Update all icon references to `/assets/images/icons/`

---

## Best Practices for Future Development

1. **Always use root-relative paths** for assets: `/assets/css/style.css`
2. **Create includes/components** for repeated HTML sections
3. **Namespace API endpoints** logically: `/api/projects/`, `/api/contractors/`
4. **Use constants for paths** in PHP: `define('ASSETS_PATH', '/assets')`
5. **Separate concerns**: No business logic in templates
6. **Version assets** for cache-busting: `main.css?v=1.2.3`
7. **Document API endpoints** in `docs/API.md`
8. **Use environment variables** for configuration differences
9. **Implement proper error handling** with consistent response formats
10. **Keep security files protected** outside public web root when possible

---

## Timeline Estimate

- Analysis & Planning: ✓ Completed
- Create new directory structure: 1 hour
- Move and rename files: 2-3 hours
- Update PHP includes: 2-3 hours
- Update CSS/JS/Image links: 1-2 hours
- Create reusable components: 2-3 hours
- Test all functionality: 2-3 hours
- Documentation: 1 hour

**Total: ~12-15 hours**

---

## Next Steps

1. Review and approve this restructuring plan
2. Begin creating new folder structure
3. Start file migration and path updates
4. Create shared includes (header, footer, sidebar)
5. Test all functionality after changes
6. Update documentation
