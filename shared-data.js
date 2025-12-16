/**
 * Shared Data Service for LGU IPMS
 * Provides centralized access to all module data stored in localStorage
 */

const IPMS_DATA = {
    // Storage keys
    KEYS: {
        PROJECTS: 'projects',
        BUDGET: 'lgu_budget_module_v1',
        TASKS: 'lgu_tasks_v1',
        CONTRACTORS: 'contractors_module_v1'
    },

    // Get all projects
    getProjects() {
        try {
            return JSON.parse(localStorage.getItem(this.KEYS.PROJECTS) || '[]');
        } catch (e) {
            console.error('Error loading projects:', e);
            return [];
        }
    },

    // Get budget data
    getBudgetData() {
        try {
            return JSON.parse(localStorage.getItem(this.KEYS.BUDGET) || '{"globalBudget":0,"milestones":[],"expenses":[]}');
        } catch (e) {
            console.error('Error loading budget:', e);
            return { globalBudget: 0, milestones: [], expenses: [] };
        }
    },

    // Get tasks
    getTasks() {
        try {
            return JSON.parse(localStorage.getItem(this.KEYS.TASKS) || '[]');
        } catch (e) {
            console.error('Error loading tasks:', e);
            return [];
        }
    },

    // Get contractors data
    getContractorsData() {
        try {
            return JSON.parse(localStorage.getItem(this.KEYS.CONTRACTORS) || '{}');
        } catch (e) {
            console.error('Error loading contractors:', e);
            return {};
        }
    },

    // Dashboard Analytics
    getDashboardMetrics() {
        const projects = this.getProjects();
        const budgetData = this.getBudgetData();
        const tasks = this.getTasks();

        // Project metrics
        const totalProjects = projects.length;
        const inProgressProjects = projects.filter(p => 
            p.status === 'Approved' && (p.progress || 0) < 100
        ).length;
        const completedProjects = projects.filter(p => 
            (p.progress || 0) >= 100 || p.status === 'Completed'
        ).length;

        // Budget metrics
        const totalBudget = projects.reduce((sum, p) => sum + (Number(p.budget) || 0), 0);
        const allocatedBudget = budgetData.milestones.reduce((sum, m) => sum + (Number(m.allocated) || 0), 0);
        const spentBudget = (budgetData.expenses || []).reduce((sum, e) => sum + (Number(e.amount) || 0), 0);
        const budgetUtilization = totalBudget > 0 ? Math.round((spentBudget / totalBudget) * 100) : 0;

        // Task metrics
        const totalTasks = tasks.length;
        const overdueTasks = tasks.filter(t => {
            if (!t.deadline || t.status === 'Completed') return false;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const deadline = new Date(t.deadline);
            deadline.setHours(0, 0, 0, 0);
            return deadline < today;
        }).length;

        // Status distribution
        const statusCounts = {
            completed: completedProjects,
            inProgress: inProgressProjects,
            delayed: projects.filter(p => {
                if (!p.endDate || p.status === 'Completed') return false;
                const today = new Date();
                const endDate = new Date(p.endDate);
                return endDate < today && (p.progress || 0) < 100;
            }).length
        };

        const totalWithStatus = statusCounts.completed + statusCounts.inProgress + statusCounts.delayed;
        const statusDistribution = {
            completed: totalWithStatus > 0 ? Math.round((statusCounts.completed / totalWithStatus) * 100) : 0,
            inProgress: totalWithStatus > 0 ? Math.round((statusCounts.inProgress / totalWithStatus) * 100) : 0,
            delayed: totalWithStatus > 0 ? Math.round((statusCounts.delayed / totalWithStatus) * 100) : 0
        };

        // Average project duration
        const projectsWithDuration = projects.filter(p => p.durationMonths && p.durationMonths > 0);
        const avgDuration = projectsWithDuration.length > 0
            ? (projectsWithDuration.reduce((sum, p) => sum + Number(p.durationMonths), 0) / projectsWithDuration.length).toFixed(1)
            : 0;

        // On-time delivery rate
        const completedWithDates = projects.filter(p => 
            (p.progress >= 100 || p.status === 'Completed') && p.endDate
        );
        const onTimeDeliveries = completedWithDates.filter(p => {
            if (!p.updatedAt && !p.createdAt) return true;
            const completionDate = new Date(p.updatedAt || p.createdAt);
            const plannedEnd = new Date(p.endDate);
            return completionDate <= plannedEnd;
        }).length;
        const onTimeRate = completedWithDates.length > 0
            ? Math.round((onTimeDeliveries / completedWithDates.length) * 100)
            : 0;

        // Budget variance
        const budgetVariance = totalBudget > 0
            ? (((spentBudget - allocatedBudget) / totalBudget) * 100).toFixed(1)
            : 0;

        return {
            projects: {
                total: totalProjects,
                inProgress: inProgressProjects,
                completed: completedProjects,
                delayed: statusCounts.delayed
            },
            budget: {
                total: totalBudget,
                allocated: allocatedBudget,
                spent: spentBudget,
                remaining: Math.max(0, totalBudget - spentBudget),
                utilization: budgetUtilization
            },
            tasks: {
                total: totalTasks,
                overdue: overdueTasks
            },
            statusDistribution,
            analytics: {
                avgDuration,
                onTimeRate,
                budgetVariance
            }
        };
    },

    // Get recent projects for dashboard table
    getRecentProjects(limit = 5) {
        const projects = this.getProjects();
        return projects
            .sort((a, b) => new Date(b.createdAt || 0) - new Date(a.createdAt || 0))
            .slice(0, limit)
            .map(p => ({
                name: p.name || 'Unnamed Project',
                location: p.location || p.barangay || 'N/A',
                status: this.getProjectStatus(p),
                progress: Number(p.progress || 0),
                budget: Number(p.budget || 0)
            }));
    },

    // Determine project status
    getProjectStatus(project) {
        if (project.progress >= 100 || project.status === 'Completed') {
            return 'Completed';
        }
        if (project.status === 'Approved' && project.progress > 0) {
            return 'In Progress';
        }
        if (project.endDate) {
            const today = new Date();
            const endDate = new Date(project.endDate);
            if (endDate < today && project.progress < 100) {
                return 'Delayed';
            }
        }
        return project.status || 'Draft';
    },

    // Format currency
    formatCurrency(amount) {
        if (!amount && amount !== 0) return '₱0';
        const num = Number(amount);
        if (num >= 1000000) {
            return '₱' + (num / 1000000).toFixed(1) + 'M';
        }
        if (num >= 1000) {
            return '₱' + (num / 1000).toFixed(0) + 'K';
        }
        return '₱' + num.toLocaleString();
    }
};

// Make available globally
if (typeof window !== 'undefined') {
    window.IPMS_DATA = IPMS_DATA;
}