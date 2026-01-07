// Shared data management for IPMS
// This file provides utility functions for managing project data across the system

const IPMS_DATA = {
    projectsKey: 'lgu_ipms_projects',
    
    // Get all projects from localStorage
    getProjects: function() {
        try {
            return JSON.parse(localStorage.getItem(this.projectsKey) || '[]');
        } catch (e) {
            console.error('Error parsing projects from localStorage:', e);
            return [];
        }
    },
    
    // Save projects to localStorage
    saveProjects: function(projects) {
        try {
            localStorage.setItem(this.projectsKey, JSON.stringify(projects));
            return true;
        } catch (e) {
            console.error('Error saving projects to localStorage:', e);
            return false;
        }
    },
    
    // Add or update a project
    saveProject: function(project) {
        try {
            const projects = this.getProjects();
            const index = projects.findIndex(p => p.id === project.id);
            if (index >= 0) {
                projects[index] = project;
            } else {
                projects.push(project);
            }
            this.saveProjects(projects);
            return true;
        } catch (e) {
            console.error('Error saving project:', e);
            return false;
        }
    },
    
    // Delete a project
    deleteProject: function(projectId) {
        try {
            const projects = this.getProjects();
            const filtered = projects.filter(p => p.id !== projectId);
            this.saveProjects(filtered);
            return true;
        } catch (e) {
            console.error('Error deleting project:', e);
            return false;
        }
    }
};

// Make IPMS_DATA available globally
window.IPMS_DATA = IPMS_DATA;
