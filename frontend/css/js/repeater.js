// ===== REPEATER MODULE =====

async function sendRepeaterRequest() {
    const url = document.getElementById('repeaterUrl').value;
    const method = document.getElementById('repeaterMethod').value;
    const headersText = document.getElementById('repeaterHeaders').value;
    const body = document.getElementById('repeaterBody').value;
    
    if (!url) {
        showToast('Please enter a URL', 'warning');
        return;
    }
    
    // Parse headers
    const headers = {};
    headersText.split('\n').forEach(line => {
        const parts = line.split(':');
        if (parts.length >= 2) {
            const key = parts[0].trim();
            const value = parts.slice(1).join(':').trim();
            if (key && value) headers[key] = value;
        }
    });
    
    const startTime = performance.now();
    
    try {
        const response = await fetch('/backend/index.php?route=repeater/send', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                url, 
                method, 
                headers, 
                body,
                request: '' // Empty for now
            })
        });
        
        const data = await response.json();
        const endTime = performance.now();
        
        if (data.error) {
            showToast('Repeater error: ' + data.error, 'error');
            return;
        }
        
        // Display response
        const responseContainer = document.getElementById('repeaterResponse');
        const statusElem = document.getElementById('responseStatus');
        const timeElem = document.getElementById('responseTime');
        const sizeElem = document.getElementById('responseSize');
        
        if (data.response) {
            const resp = data.response;
            responseContainer.textContent = resp.body || 'No response body';
            statusElem.textContent = `Status: ${resp.status_code || 'N/A'}`;
            timeElem.textContent = `Time: ${Math.round(endTime - startTime)}ms`;
            sizeElem.textContent = `Size: ${(resp.size || 0).toLocaleString()} bytes`;
        } else {
            responseContainer.textContent = 'No response data';
        }
        
        // Add to history
        addToRepeaterHistory(data);
        
        showToast('Request sent successfully', 'success');
    } catch(e) {
        showToast('Repeater error: ' + e.message, 'error');
    }
}

function addToRepeaterHistory(data) {
    const history = document.getElementById('repeaterHistory');
    if (!history) return;
    
    const item = document.createElement('div');
    item.className = 'history-item';
    const timestamp = new Date().toLocaleTimeString();
    const status = data.response?.status_code || 'N/A';
    item.innerHTML = `
        <span>${timestamp} - ${data.request?.method || 'GET'} ${data.request?.url || ''}</span>
        <span style="color: ${status < 400 ? 'var(--success)' : 'var(--danger)'}">${status}</span>
    `;
    item.onclick = function() {
        if (data.request) {
            document.getElementById('repeaterUrl').value = data.request.url || '';
            document.getElementById('repeaterMethod').value = data.request.method || 'GET';
            document.getElementById('repeaterBody').value = data.request.body || '';
            if (data.request.headers) {
                document.getElementById('repeaterHeaders').value = Object.entries(data.request.headers)
                    .map(([k,v]) => `${k}: ${v}`).join('\n');
            }
        }
    };
    
    history.prepend(item);
    
    // Limit to 50 items
    while (history.children.length > 50) {
        history.removeChild(history.lastChild);
    }
}

async function loadRepeaterHistory() {
    try {
        const response = await fetch('/backend/index.php?route=repeater/history');
        const data = await response.json();
        const history = document.getElementById('repeaterHistory');
        if (!history) return;
        
        history.innerHTML = '';
        if (data.history && data.history.length > 0) {
            data.history.forEach(item => {
                addToRepeaterHistory(item);
            });
        } else {
            history.innerHTML = '<div style="padding:10px;color:var(--text-secondary);">No history yet</div>';
        }
    } catch(e) {
        console.error('Load history error:', e);
    }
}

// Initialize repeater
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('repeaterSend')?.addEventListener('click', sendRepeaterRequest);
    
    // Ctrl+Enter shortcut
    document.getElementById('repeaterBody')?.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            sendRepeaterRequest();
        }
    });
});
