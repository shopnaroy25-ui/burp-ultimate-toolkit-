// ===== PROXY MODULE =====
let proxyInterceptEnabled = true;
let selectedRequestId = null;

async function loadRequests() {
    try {
        const response = await fetch('/backend/index.php?route=proxy/capture');
        const data = await response.json();
        const list = document.getElementById('requestList');
        if (!list) return;
        
        if (!data.requests || data.requests.length === 0) {
            list.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-secondary);">No requests captured yet</div>';
            return;
        }
        
        list.innerHTML = data.requests.map(req => `
            <div class="request-item" data-id="${req.id}" onclick="selectRequest('${req.id}')">
                <span class="method">${req.method || 'GET'}</span>
                <span class="host">${req.host || 'unknown'}</span>
                <span class="status">${req.status || '200'}</span>
            </div>
        `).join('');
        
        // Select first request if none selected
        if (!selectedRequestId && data.requests.length > 0) {
            selectRequest(data.requests[0].id);
        }
    } catch(e) {
        console.error('Load requests error:', e);
    }
}

function addRequestToList(request) {
    const list = document.getElementById('requestList');
    if (!list) return;
    
    const item = document.createElement('div');
    item.className = 'request-item';
    item.dataset.id = request.id;
    item.onclick = function() { selectRequest(request.id); };
    item.innerHTML = `
        <span class="method">${request.method || 'GET'}</span>
        <span class="host">${request.host || 'unknown'}</span>
        <span class="status">${request.status || '200'}</span>
    `;
    
    list.prepend(item);
    
    // Limit to 100 items
    while (list.children.length > 100) {
        list.removeChild(list.lastChild);
    }
}

async function selectRequest(id) {
    selectedRequestId = id;
    
    // Highlight selected
    document.querySelectorAll('.request-item').forEach(item => {
        item.classList.toggle('active', item.dataset.id === id);
    });
    
    try {
        const response = await fetch(`/backend/index.php?route=proxy/capture`);
        const data = await response.json();
        const request = data.requests?.find(r => r.id === id);
        
        if (!request) {
            document.getElementById('rawContent').textContent = 'Request not found';
            return;
        }
        
        APP.selectedRequest = request;
        
        // Update raw view
        const raw = `${request.method || 'GET'} ${request.path || '/'} HTTP/1.1\n` +
                    `Host: ${request.host || 'localhost'}\n` +
                    Object.entries(request.headers || {}).map(([k,v]) => `${k}: ${v}`).join('\n') +
                    `\n\n${request.body || ''}`;
        document.getElementById('rawContent').textContent = raw;
        
        // Update headers view (will be shown when tab clicked)
        window._requestHeaders = request.headers || {};
        window._requestBody = request.body || '';
        window._requestResponse = request.response || '';
        
        updateTabContent('raw');
    } catch(e) {
        console.error('Select request error:', e);
    }
}

function updateTabContent(tab) {
    const content = document.getElementById('rawContent');
    if (!content) return;
    
    switch(tab) {
        case 'raw':
            // Already updated in selectRequest
            break;
        case 'headers':
            content.textContent = JSON.stringify(window._requestHeaders || {}, null, 2);
            break;
        case 'body':
            content.textContent = window._requestBody || '';
            break;
        case 'response':
            content.textContent = window._requestResponse || 'No response yet';
            break;
    }
}

// Tab switching
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const tab = this.dataset.tab;
            updateTabContent(tab);
        });
    });
    
    // Intercept toggle
    document.getElementById('interceptToggle')?.addEventListener('click', function() {
        proxyInterceptEnabled = !proxyInterceptEnabled;
        this.innerHTML = proxyInterceptEnabled ? 
            '<i class="fas fa-pause"></i> Intercept ON' : 
            '<i class="fas fa-play"></i> Intercept OFF';
        this.classList.toggle('off', !proxyInterceptEnabled);
        
        // Send to server
        fetch('/backend/index.php?route=proxy/intercept', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ enabled: proxyInterceptEnabled })
        });
    });
    
    // Forward button
    document.getElementById('forwardRequest')?.addEventListener('click', function() {
        if (!APP.selectedRequest) {
            showToast('No request selected', 'warning');
            return;
        }
        forwardRequest(APP.selectedRequest.id);
    });
    
    // Drop button
    document.getElementById('dropRequest')?.addEventListener('click', function() {
        if (!APP.selectedRequest) {
            showToast('No request selected', 'warning');
            return;
        }
        dropRequest(APP.selectedRequest.id);
    });
    
    // Send to Repeater
    document.getElementById('sendToRepeater')?.addEventListener('click', function() {
        if (!APP.selectedRequest) {
            showToast('No request selected', 'warning');
            return;
        }
        sendToRepeater(APP.selectedRequest);
    });
    
    // Send to Intruder
    document.getElementById('sendToIntruder')?.addEventListener('click', function() {
        if (!APP.selectedRequest) {
            showToast('No request selected', 'warning');
            return;
        }
        sendToIntruder(APP.selectedRequest);
    });
    
    // Clear requests
    document.getElementById('clearRequests')?.addEventListener('click', function() {
        if (confirm('Clear all captured requests?')) {
            document.getElementById('requestList').innerHTML = '';
            document.getElementById('rawContent').textContent = 'Select a request to view details';
            selectedRequestId = null;
            APP.selectedRequest = null;
            showToast('Requests cleared', 'info');
        }
    });
    
    // Export requests
    document.getElementById('exportRequests')?.addEventListener('click', function() {
        exportRequests();
    });
});

async function forwardRequest(id) {
    try {
        const response = await fetch('/backend/index.php?route=proxy/forward', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await response.json();
        if (data.error) {
            showToast('Forward error: ' + data.error, 'error');
        } else {
            showToast('Request forwarded successfully', 'success');
            if (data.response) {
                window._requestResponse = data.response;
                updateTabContent('response');
            }
        }
    } catch(e) {
        showToast('Forward error: ' + e.message, 'error');
    }
}

async function dropRequest(id) {
    try {
        const response = await fetch('/backend/index.php?route=proxy/drop', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await response.json();
        if (data.error) {
            showToast('Drop error: ' + data.error, 'error');
        } else {
            showToast('Request dropped', 'info');
            // Remove from list
            document.querySelector(`.request-item[data-id="${id}"]`)?.remove();
            if (selectedRequestId === id) {
                selectedRequestId = null;
                document.getElementById('rawContent').textContent = 'Request dropped';
            }
        }
    } catch(e) {
        showToast('Drop error: ' + e.message, 'error');
    }
}

function sendToRepeater(request) {
    // Build request string
    const raw = `${request.method || 'GET'} ${request.path || '/'} HTTP/1.1\n` +
                `Host: ${request.host || 'localhost'}\n` +
                Object.entries(request.headers || {}).map(([k,v]) => `${k}: ${v}`).join('\n') +
                `\n\n${request.body || ''}`;
    
    // Switch to repeater view
    switchView('repeater');
    
    // Fill repeater input
    document.getElementById('repeaterUrl').value = `${request.scheme || 'http'}://${request.host || 'localhost'}${request.path || '/'}`;
    document.getElementById('repeaterMethod').value = request.method || 'GET';
    document.getElementById('repeaterHeaders').value = Object.entries(request.headers || {})
        .map(([k,v]) => `${k}: ${v}`).join('\n');
    document.getElementById('repeaterBody').value = request.body || '';
    
    showToast('Request sent to Repeater', 'success');
}

function sendToIntruder(request) {
    // Build URL
    const url = `${request.scheme || 'http'}://${request.host || 'localhost'}${request.path || '/'}`;
    
    // Switch to intruder view
    switchView('intruder');
    
    // Fill intruder fields
    document.getElementById('intruderTarget').value = url;
    document.getElementById('intruderMethod').value = request.method || 'POST';
    document.getElementById('intruderBody').value = request.body || '';
    
    showToast('Request sent to Intruder', 'success');
}

function exportRequests() {
    const items = document.querySelectorAll('.request-item');
    const requests = [];
    items.forEach(item => {
        requests.push({
            id: item.dataset.id,
            method: item.querySelector('.method')?.textContent || 'GET',
            host: item.querySelector('.host')?.textContent || 'unknown',
            status: item.querySelector('.status')?.textContent || '200'
        });
    });
    
    const blob = new Blob([JSON.stringify(requests, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `burp_requests_${new Date().toISOString().slice(0,10)}.json`;
    a.click();
    URL.revokeObjectURL(url);
    showToast('Requests exported', 'success');
}

// Make functions globally accessible
window.selectRequest = selectRequest;
window.proxyInterceptEnabled = proxyInterceptEnabled;
