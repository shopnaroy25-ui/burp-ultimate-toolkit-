// ===== APP INITIALIZATION =====
const APP = {
    version: '3.0.0',
    name: 'Burp Ultimate Toolkit Pro',
    ws: null,
    currentView: 'dashboard',
    selectedRequest: null
};

document.addEventListener('DOMContentLoaded', function() {
    console.log(`${APP.name} v${APP.version} initialized`);
    initializeApp();
    loadDashboardStats();
    setupWebSocket();
});

function initializeApp() {
    // Navigation
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', function() {
            const view = this.dataset.view;
            switchView(view);
        });
    });

    // Menu toggle (mobile)
    document.getElementById('menuToggle')?.addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('open');
    });

    // Theme toggle
    document.getElementById('themeToggle')?.addEventListener('click', function() {
        document.body.classList.toggle('light');
        const icon = this.querySelector('i');
        icon.classList.toggle('fa-moon');
        icon.classList.toggle('fa-sun');
    });

    // Fullscreen toggle
    document.getElementById('fullscreenToggle')?.addEventListener('click', function() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen();
        } else {
            document.exitFullscreen();
        }
    });

    // Settings toggle
    document.getElementById('settingsToggle')?.addEventListener('click', function() {
        // Show settings modal
        showSettingsModal();
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+1-9 for navigation
        if (e.ctrlKey && e.key >= '1' && e.key <= '9') {
            const views = ['dashboard', 'proxy', 'repeater', 'intruder', 'otp-bypass', 'ssl', 'ai', 'exploit'];
            const index = parseInt(e.key) - 1;
            if (views[index]) {
                switchView(views[index]);
                e.preventDefault();
            }
        }
        
        // Ctrl+Enter for send
        if (e.ctrlKey && e.key === 'Enter') {
            const activeView = document.querySelector('.view.active');
            if (activeView) {
                const sendBtn = activeView.querySelector('.btn-primary, .btn-danger');
                if (sendBtn) sendBtn.click();
            }
        }
    });
}

function switchView(view) {
    // Update nav
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.toggle('active', item.dataset.view === view);
    });
    
    // Update views
    document.querySelectorAll('.view').forEach(v => {
        v.classList.toggle('active', v.id === `view-${view}`);
    });
    
    APP.currentView = view;
    
    // Refresh view data
    switch(view) {
        case 'proxy': loadRequests(); break;
        case 'repeater': loadRepeaterHistory(); break;
        case 'dashboard': loadDashboardStats(); break;
        case 'intruder': loadIntruderStatus(); break;
        case 'ssl': loadSSLStatus(); break;
    }
    
    // Close sidebar on mobile
    if (window.innerWidth <= 768) {
        document.getElementById('sidebar').classList.remove('open');
    }
}

// ===== DASHBOARD =====
async function loadDashboardStats() {
    try {
        const response = await fetch('/backend/index.php?route=proxy/capture');
        const data = await response.json();
        const requests = data.requests || [];
        document.getElementById('totalRequests').textContent = requests.length;
        
        // Load other stats
        const otpCount = document.getElementById('otpBypassCount');
        if (otpCount) otpCount.textContent = localStorage.getItem('otp_bypass_count') || 0;
        
        const exploitCount = document.getElementById('exploitCount');
        if (exploitCount) exploitCount.textContent = localStorage.getItem('exploit_count') || 0;
        
        // Update response time (simulated)
        const responseTime = document.getElementById('responseTime');
        if (responseTime) {
            const times = [45, 67, 89, 34, 56, 78, 23, 45];
            const avg = times.reduce((a,b) => a+b, 0) / times.length;
            responseTime.textContent = Math.round(avg) + 'ms';
        }
        
        // Update charts
        if (window.requestChart) {
            updateCharts(requests);
        }
    } catch(e) {
        console.error('Dashboard load error:', e);
    }
}

function updateCharts(requests) {
    // Simple chart update placeholder
    // Full Chart.js integration can be added here
}

// ===== SETTINGS =====
function showSettingsModal() {
    // Simple settings modal (can be expanded)
    const settings = JSON.stringify({
        proxy_port: 8080,
        intercept_enabled: true,
        exclude_hosts: ['localhost', '127.0.0.1']
    }, null, 2);
    
    alert(`Settings:\n${settings}\n\n(Full settings UI coming soon)`);
}

// ===== WEBSOCKET =====
function setupWebSocket() {
    try {
        const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${wsProtocol}//${window.location.host}:8443`;
        APP.ws = new WebSocket(wsUrl);
        
        APP.ws.onopen = function() {
            console.log('WebSocket connected');
            updateConnectionStatus(true);
        };
        
        APP.ws.onmessage = function(event) {
            const data = JSON.parse(event.data);
            handleWebSocketMessage(data);
        };
        
        APP.ws.onclose = function() {
            console.log('WebSocket disconnected');
            updateConnectionStatus(false);
            // Reconnect after 5 seconds
            setTimeout(setupWebSocket, 5000);
        };
        
        APP.ws.onerror = function(error) {
            console.error('WebSocket error:', error);
        };
    } catch(e) {
        console.error('WebSocket setup error:', e);
    }
}

function handleWebSocketMessage(data) {
    switch(data.type) {
        case 'new_request':
            addRequestToList(data.data);
            break;
        case 'intercept_update':
            updateInterceptStatus(data.enabled);
            break;
        case 'intruder_progress':
            updateIntruderProgress(data.progress);
            break;
        case 'otp_bypass_result':
            updateOTPBypassResult(data.result);
            break;
        default:
            console.log('Unknown WebSocket message:', data);
    }
}

function updateConnectionStatus(connected) {
    const badge = document.getElementById('connectionStatus');
    if (connected) {
        badge.innerHTML = '<i class="fas fa-circle" style="color: #4CAF50;"></i> Connected';
        badge.style.borderColor = 'rgba(76, 175, 80, 0.3)';
    } else {
        badge.innerHTML = '<i class="fas fa-circle" style="color: #d32f2f;"></i> Disconnected';
        badge.style.borderColor = 'rgba(211, 47, 47, 0.3)';
    }
}

// ===== UTILITY FUNCTIONS =====
function showToast(message, type = 'info') {
    const colors = {
        info: '#007acc',
        success: '#4caf50',
        warning: '#f9a825',
        error: '#d32f2f'
    };
    
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: ${colors[type] || colors.info};
        color: white;
        padding: 12px 20px;
        border-radius: 6px;
        font-size: 14px;
        z-index: 10000;
        max-width: 400px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        animation: slideIn 0.3s ease;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Add CSS animation for toast
const styleSheet = document.createElement('style');
styleSheet.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
`;
document.head.appendChild(styleSheet);

// Export for use in other modules
window.APP = APP;
window.showToast = showToast;
window.switchView = switchView;
