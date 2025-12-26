// Job Portal JavaScript with AJAX

const API_BASE = 'api';

// Toast Notification System
const Toast = {
    container: null,
    
    init() {
        this.container = document.createElement('div');
        this.container.className = 'toast-container';
        document.body.appendChild(this.container);
    },
    
    show(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <span>${message}</span>
            <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-size:18px;">&times;</button>
        `;
        this.container.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }
};

// Initialize toast on page load
document.addEventListener('DOMContentLoaded', () => {
    Toast.init();
    checkAuth();
});

// AJAX Helper Function
function ajax(url, method = 'GET', data = null) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.withCredentials = true;
        
        xhr.onload = function() {
            try {
                const response = JSON.parse(xhr.responseText);
                resolve(response);
            } catch (e) {
                reject(new Error('Invalid JSON response'));
            }
        };
        
        xhr.onerror = function() {
            reject(new Error('Network error'));
        };
        
        if (data instanceof FormData) {
            xhr.send(data);
        } else if (data) {
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            const params = new URLSearchParams(data).toString();
            xhr.send(params);
        } else {
            xhr.send();
        }
    });
}

// Authentication Functions
async function checkAuth() {
    try {
        const response = await ajax(`${API_BASE}/auth.php?action=check`);
        if (response.success) {
            updateUIForLoggedInUser(response.data);
        } else {
            updateUIForGuest();
        }
    } catch (error) {
        console.error('Auth check failed:', error);
        updateUIForGuest();
    }
}

function updateUIForLoggedInUser(user) {
    const authContainer = document.getElementById('auth-container');
    if (authContainer) {
        authContainer.innerHTML = `
            <div class="user-menu">
                <button class="user-btn" onclick="toggleUserMenu()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    ${user.name}
                </button>
                <div class="user-dropdown" id="userDropdown">
                    <a href="dashboard.html">Dashboard</a>
                    ${user.role === 'employer' ? '<a href="post-job.html">Post a Job</a>' : '<a href="my-applications.html">My Applications</a>'}
                    <a href="#" onclick="logout(); return false;">Logout</a>
                </div>
            </div>
        `;
    }
    
    // Store user data
    window.currentUser = user;
}

function updateUIForGuest() {
    const authContainer = document.getElementById('auth-container');
    if (authContainer) {
        authContainer.innerHTML = `
            <a href="login.html" class="btn btn-secondary">Login</a>
            <a href="register.html" class="btn btn-primary">Sign Up</a>
        `;
    }
    window.currentUser = null;
}

function toggleUserMenu() {
    const dropdown = document.getElementById('userDropdown');
    dropdown.classList.toggle('active');
}

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
    const dropdown = document.getElementById('userDropdown');
    const userBtn = document.querySelector('.user-btn');
    if (dropdown && !dropdown.contains(e.target) && !userBtn?.contains(e.target)) {
        dropdown.classList.remove('active');
    }
});

// Register Function
async function register(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'register');
    
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Creating Account...';
    
    try {
        const response = await ajax(`${API_BASE}/auth.php`, 'POST', formData);
        if (response.success) {
            Toast.show('Account created successfully!', 'success');
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 1000);
        } else {
            Toast.show(response.message, 'error');
        }
    } catch (error) {
        Toast.show('Registration failed. Please try again.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Create Account';
    }
}

// Login Function
async function login(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'login');
    
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Logging in...';
    
    try {
        const response = await ajax(`${API_BASE}/auth.php`, 'POST', formData);
        if (response.success) {
            Toast.show('Login successful!', 'success');
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 1000);
        } else {
            Toast.show(response.message, 'error');
        }
    } catch (error) {
        Toast.show('Login failed. Please try again.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Login';
    }
}

// Logout Function
async function logout() {
    try {
        await ajax(`${API_BASE}/auth.php?action=logout`);
        Toast.show('Logged out successfully', 'success');
        setTimeout(() => {
            window.location.href = 'index.html';
        }, 500);
    } catch (error) {
        console.error('Logout failed:', error);
    }
}

// Load Jobs Function
async function loadJobs(container = 'jobs-container') {
    const jobsContainer = document.getElementById(container);
    if (!jobsContainer) return;
    
    jobsContainer.innerHTML = '<div class="spinner"></div>';
    
    try {
        const response = await ajax(`${API_BASE}/jobs.php?action=list`);
        if (response.success && response.data.length > 0) {
            jobsContainer.innerHTML = response.data.map(job => createJobCard(job)).join('');
        } else {
            jobsContainer.innerHTML = `
                <div class="empty-state">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <h3>No jobs found</h3>
                    <p>Check back later for new opportunities</p>
                </div>
            `;
        }
    } catch (error) {
        jobsContainer.innerHTML = '<p class="empty-state">Failed to load jobs. Please refresh the page.</p>';
    }
}

// Create Job Card HTML
function createJobCard(job) {
    const salary = job.salary_min && job.salary_max 
        ? `$${Number(job.salary_min).toLocaleString()} - $${Number(job.salary_max).toLocaleString()}`
        : 'Not specified';
    
    const postedDate = new Date(job.created_at).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
    
    return `
        <div class="job-card" onclick="window.location.href='job-details.html?id=${job.id}'">
            <div class="job-card-header">
                <div>
                    <h3>${escapeHtml(job.title)}</h3>
                    <span class="company">${escapeHtml(job.company)}</span>
                </div>
                <span class="job-type-badge ${job.job_type}">${job.job_type.replace('-', ' ')}</span>
            </div>
            <div class="job-meta">
                <span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    ${escapeHtml(job.location)}
                </span>
                <span class="salary">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    ${salary}
                </span>
                <span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    ${postedDate}
                </span>
            </div>
        </div>
    `;
}

// Search Jobs
async function searchJobs(event) {
    event.preventDefault();
    const form = event.target;
    const keyword = form.querySelector('[name="keyword"]').value;
    const location = form.querySelector('[name="location"]').value;
    const jobType = form.querySelector('[name="job_type"]')?.value || '';
    
    const jobsContainer = document.getElementById('jobs-container');
    jobsContainer.innerHTML = '<div class="spinner"></div>';
    
    try {
        const params = new URLSearchParams({
            action: 'search',
            keyword,
            location,
            job_type: jobType
        });
        
        const response = await ajax(`${API_BASE}/jobs.php?${params}`);
        if (response.success && response.data.length > 0) {
            jobsContainer.innerHTML = response.data.map(job => createJobCard(job)).join('');
        } else {
            jobsContainer.innerHTML = `
                <div class="empty-state">
                    <h3>No jobs found</h3>
                    <p>Try different search terms</p>
                </div>
            `;
        }
    } catch (error) {
        Toast.show('Search failed. Please try again.', 'error');
    }
}

// Load Single Job Details
async function loadJobDetails() {
    const urlParams = new URLSearchParams(window.location.search);
    const jobId = urlParams.get('id');
    
    if (!jobId) {
        window.location.href = 'index.html';
        return;
    }
    
    const container = document.getElementById('job-details-container');
    container.innerHTML = '<div class="spinner"></div>';
    
    try {
        const response = await ajax(`${API_BASE}/jobs.php?action=get&id=${jobId}`);
        if (response.success) {
            renderJobDetails(response.data);
        } else {
            container.innerHTML = '<div class="empty-state"><h3>Job not found</h3></div>';
        }
    } catch (error) {
        container.innerHTML = '<div class="empty-state"><h3>Failed to load job</h3></div>';
    }
}

function renderJobDetails(job) {
    const salary = job.salary_min && job.salary_max 
        ? `$${Number(job.salary_min).toLocaleString()} - $${Number(job.salary_max).toLocaleString()}`
        : 'Not specified';
    
    const container = document.getElementById('job-details-container');
    container.innerHTML = `
        <div class="job-details-card">
            <h1>${escapeHtml(job.title)}</h1>
            <p class="company-name">${escapeHtml(job.company)}</p>
            
            <div class="job-info-grid">
                <div class="job-info-item">
                    <div class="label">Location</div>
                    <div class="value">${escapeHtml(job.location)}</div>
                </div>
                <div class="job-info-item">
                    <div class="label">Job Type</div>
                    <div class="value">${job.job_type.replace('-', ' ')}</div>
                </div>
                <div class="job-info-item">
                    <div class="label">Salary</div>
                    <div class="value">${salary}</div>
                </div>
                <div class="job-info-item">
                    <div class="label">Posted</div>
                    <div class="value">${new Date(job.created_at).toLocaleDateString()}</div>
                </div>
            </div>
            
            <div class="job-description">
                <h3>Job Description</h3>
                <p>${escapeHtml(job.description).replace(/\n/g, '<br>')}</p>
                
                ${job.requirements ? `
                    <h3>Requirements</h3>
                    <p>${escapeHtml(job.requirements).replace(/\n/g, '<br>')}</p>
                ` : ''}
            </div>
            
            <div class="apply-section" id="apply-section">
                <!-- Will be populated based on auth status -->
            </div>
        </div>
    `;
    
    // Check if user can apply
    updateApplySection(job.id);
}

async function updateApplySection(jobId) {
    const section = document.getElementById('apply-section');
    
    if (!window.currentUser) {
        section.innerHTML = `
            <p style="margin-bottom: 16px;">Login to apply for this job</p>
            <a href="login.html" class="btn btn-primary btn-lg">Login to Apply</a>
        `;
        return;
    }
    
    if (window.currentUser.role === 'employer') {
        section.innerHTML = '<p style="color: var(--text-muted);">Employers cannot apply for jobs</p>';
        return;
    }
    
    // Check if already applied
    try {
        const response = await ajax(`${API_BASE}/applications.php?action=check&job_id=${jobId}`);
        if (response.success) {
            section.innerHTML = `
                <div style="background: var(--success); color: white; padding: 16px; border-radius: var(--radius-sm); text-align: center;">
                    ✓ You have already applied for this job<br>
                    <small>Status: ${response.data.status}</small>
                </div>
            `;
        } else {
            section.innerHTML = `
                <button class="btn btn-primary btn-lg" onclick="openApplyModal(${jobId})">
                    Apply Now
                </button>
            `;
        }
    } catch (error) {
        section.innerHTML = '<p>Error checking application status</p>';
    }
}

// Apply Modal
function openApplyModal(jobId) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.id = 'applyModal';
    modal.innerHTML = `
        <div class="modal">
            <div class="modal-header">
                <h3>Apply for this Job</h3>
                <button class="modal-close" onclick="closeApplyModal()">&times;</button>
            </div>
            <form onsubmit="submitApplication(event, ${jobId})">
                <div class="form-group">
                    <label>Cover Letter (Optional)</label>
                    <textarea name="cover_letter" placeholder="Tell the employer why you're a great fit..."></textarea>
                </div>
                <div class="form-group">
                    <label>Resume (PDF, DOC, DOCX)</label>
                    <input type="file" name="resume" accept=".pdf,.doc,.docx">
                </div>
                <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">Submit Application</button>
            </form>
        </div>
    `;
    document.body.appendChild(modal);
    setTimeout(() => modal.classList.add('active'), 10);
}

function closeApplyModal() {
    const modal = document.getElementById('applyModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => modal.remove(), 300);
    }
}

async function submitApplication(event, jobId) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'apply');
    formData.append('job_id', jobId);
    
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';
    
    try {
        const response = await ajax(`${API_BASE}/applications.php`, 'POST', formData);
        if (response.success) {
            Toast.show('Application submitted successfully!', 'success');
            closeApplyModal();
            updateApplySection(jobId);
        } else {
            Toast.show(response.message, 'error');
        }
    } catch (error) {
        Toast.show('Failed to submit application', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Application';
    }
}

// Post Job
async function postJob(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'create');
    
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Posting...';
    
    try {
        const response = await ajax(`${API_BASE}/jobs.php`, 'POST', formData);
        if (response.success) {
            Toast.show('Job posted successfully!', 'success');
            setTimeout(() => {
                window.location.href = 'dashboard.html';
            }, 1000);
        } else {
            Toast.show(response.message, 'error');
        }
    } catch (error) {
        Toast.show('Failed to post job', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Post Job';
    }
}

// Load My Applications (Job Seeker)
async function loadMyApplications() {
    const container = document.getElementById('applications-container');
    if (!container) return;
    
    container.innerHTML = '<div class="spinner"></div>';
    
    try {
        const response = await ajax(`${API_BASE}/applications.php?action=my-applications`);
        if (response.success && response.data.length > 0) {
            container.innerHTML = `
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Job Title</th>
                                <th>Company</th>
                                <th>Applied On</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${response.data.map(app => `
                                <tr>
                                    <td><a href="job-details.html?id=${app.job_id}">${escapeHtml(app.title)}</a></td>
                                    <td>${escapeHtml(app.company)}</td>
                                    <td>${new Date(app.created_at).toLocaleDateString()}</td>
                                    <td><span class="status-badge ${app.status}">${app.status}</span></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        } else {
            container.innerHTML = `
                <div class="empty-state">
                    <h3>No applications yet</h3>
                    <p>Start applying for jobs to see them here</p>
                    <a href="index.html" class="btn btn-primary" style="margin-top:16px;">Browse Jobs</a>
                </div>
            `;
        }
    } catch (error) {
        container.innerHTML = '<div class="empty-state"><h3>Failed to load applications</h3></div>';
    }
}

// Utility: Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Load My Jobs (Employer)
async function loadMyJobs() {
    const container = document.getElementById('my-jobs-container');
    if (!container) return;
    
    container.innerHTML = '<div class="spinner"></div>';
    
    try {
        const response = await ajax(`${API_BASE}/jobs.php?action=my-jobs`);
        if (response.success && response.data.length > 0) {
            container.innerHTML = `
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Job Title</th>
                                <th>Location</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Posted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${response.data.map(job => `
                                <tr>
                                    <td>${escapeHtml(job.title)}</td>
                                    <td>${escapeHtml(job.location)}</td>
                                    <td>${job.job_type}</td>
                                    <td>${job.is_active ? '<span class="status-badge shortlisted">Active</span>' : '<span class="status-badge rejected">Closed</span>'}</td>
                                    <td>${new Date(job.created_at).toLocaleDateString()}</td>
                                    <td>
                                        <a href="job-applications.html?id=${job.id}" class="btn btn-secondary" style="padding:6px 12px;font-size:12px;">View Applications</a>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        } else {
            container.innerHTML = `
                <div class="empty-state">
                    <h3>No jobs posted yet</h3>
                    <a href="post-job.html" class="btn btn-primary" style="margin-top:16px;">Post Your First Job</a>
                </div>
            `;
        }
    } catch (error) {
        container.innerHTML = '<div class="empty-state"><h3>Failed to load jobs</h3></div>';
    }
}
