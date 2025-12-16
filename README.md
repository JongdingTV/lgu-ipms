# LGU Infrastructure Project Management System (IPMS)

An admin-only web application for managing local government infrastructure projects.

## Features

### 📊 Dashboard Overview
- Real-time metrics from all modules
- Project status distribution
- Budget utilization tracking
- Recent projects display
- Performance analytics

### 📝 Project Registration
- Comprehensive project details form
- Location and geography tracking
- Schedule and timeline management
- Budget and funding information
- Technical scope definition
- Procurement details
- Permits and compliance tracking

### 📈 Progress Monitoring
- View and filter all projects
- Update project progress in real-time
- Status management
- Search and sort functionality
- Export to CSV

### 💰 Budget & Resources
- Allocate funds per milestone
- Track expenses against budget
- Budget consumption visualization
- Import budget from projects
- Export budget data

### ✅ Task & Milestone Management
- Create and assign tasks
- Deliverable tracking
- Deadline management
- Contractor and LGU engineer assignments
- Task status monitoring

### 👷 Contractors
- Contractor profile management
- Performance tracking
- Compliance checklist
- Document management
- Feedback and inspection notes

## Data Storage

Currently uses **localStorage** for client-side data persistence:
- `projects` - All project data
- `lgu_budget_module_v1` - Budget and expenses
- `lgu_tasks_v1` - Tasks and milestones
- `contractors_module_v1` - Contractor information

## Connected Architecture

All modules are now connected through the **shared-data.js** service:
- Dashboard displays real-time data from all modules
- Automatic updates when data changes
- Centralized data access layer
- Ready for future database integration

## Future Enhancements

- [ ] Backend database integration (MySQL/PostgreSQL)
- [ ] User authentication and authorization
- [ ] Role-based access control
- [ ] Real-time notifications
- [ ] Advanced reporting and analytics
- [ ] Mobile responsive design improvements
- [ ] File upload to server storage

## How to Use

1. Open `dashboard/dashboard.html` to view the main dashboard
2. Navigate to different modules using the sidebar
3. All data is automatically synchronized across modules
4. Dashboard updates in real-time as you add/modify data

## Technical Stack

- **Frontend**: Vanilla JavaScript, HTML5, CSS3
- **Storage**: localStorage (temporary, will be replaced with database)
- **Architecture**: Modular design with shared data service

---

**Note**: This is currently a client-side only application. Database integration is planned for production deployment.