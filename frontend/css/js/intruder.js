// ===== INTRUDER MODULE =====
let intruderRunning = false;
let intruderInterval = null;

async function startIntruder() {
    if (intruderRunning) {
        showToast('Attack already running', 'warning');
        return;
    }
    
    const target = document.getElementById('intruderTarget').value;
    const method = document.getElementById('intruderMethod').value;
    const field = document.getElementById('intruderField').value;
    const threads = parseInt(document.getElementById('intruderThreads').value) || 20;
    const start = parseInt(document.getElementById('intruderStart').value) || 0;
    const end = parseInt(document.getElementById('intruderEnd').value) || 999999;
    const payloadType = document.getElementById('intruderPayloadType').value;
    const customPayloads = document.getElementById('intruderCustomPayloads').value;
    
    if (!target) {
        showToast('Please enter a target URL', 'warning');
        return;
    }
    
    if (start > end) {
        showToast('Start range cannot be greater than end range', 'warning');
        return;
    }
    
    intruderRunning = true;
    document.getElementById('intruderStart').style.display = 'none';
    document.getElementById('intruderStop').style.display = 'inline-block';
    document.getElementById('intruderProgress').style.width = '0%';
    document.getElementById('intruderResults').innerHTML = '';
    
    try {
        const response = await fetch('/backend/index.php?route=intruder/start', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                target,
                method,
                field,
                threads,
                start,
                end,
                payload_type: payloadType,
                custom_payloads: customPayloads,
                success_patterns: ['success', 'verified', 'approved', 'valid']
            })
        });
        
        const data = await response.json();
        
        if (data.error) {
            showToast('Intruder error: ' + data.error, 'error');
            intruderRunning = false;
            document.getElementById('intruderStart').style.display = 'inline-block';
            document.getElementById('intruderStop').style.display = 'none';
            return;
        }
        
        // Update progress
        updateIntruderProgress(data);
        
        // Display results
        displayIntruderResults(data);
        
        showToast(`Attack completed! ${data.success_count || 0} successes found`, 
                  data.success_count > 0 ? 'success' : 'info');
    } catch(e) {
        showToast('Intruder error: ' + e.message, 'error');
    } finally {
        intruderRunning = false;
        document.getElementById('intruderStart').style.display = 'inline-block';
        document.getElementById('intruderStop').style.display = 'none';
    }
}

function updateIntruderProgress(data) {
    const progress = data.progress || 0;
    document.getElementById('intruderProgress').style.width = progress + '%';
    document.getElementById('intruderTotal').textContent = `Total: ${data.total || 0}`;
    document.getElementById('intruderSuccess').textContent = `Success: ${data.success_count || 0}`;
    document.getElementById('intruderFailed').textContent = `Failed: ${(data.total || 0) - (data.success_count || 0)}`;
    document.getElementById('intruderPercent').textContent = Math.round(progress) + '%';
}

function displayIntruderResults(data) {
    const container = document.getElementById('intruderResults');
    if (!data.results || data.results.length === 0) {
        container.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-secondary);">No results to display</div>';
        return;
    }
    
    let html = `
        <table class="result-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Payload</th>
                    <th>Status</th>
                    <th>Size</th>
                    <th>Time</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    data.results.forEach((result, index) => {
        const success = result.success || false;
        html += `
            <tr>
                <td>${index + 1}</td>
                <td><code>${result.payload || 'N/A'}</code></td>
                <td>${result.status_code || 'N/A'}</td>
                <td>${result.size || 0}</td>
                <td>${result.time || '0s'}</td>
                <td class="${success ? 'success' : 'failed'}">
                    ${success ? '✅ Success' : '❌ Failed'}
                </td>
            </tr>
        `;
    });
    
    html += '</tbody></table>';
    container.innerHTML = html;
}

function stopIntruder() {
    if (!intruderRunning) return;
    intruderRunning = false;
    
    fetch('/backend/index.php?route=intruder/stop', {
        method: 'POST'
    });
    
    document.getElementById('intruderStart').style.display = 'inline-block';
    document.getElementById('intruderStop').style.display = 'none';
    showToast('Attack stopped', 'warning');
}

function loadIntruderStatus() {
    // Check if any attack is running
    fetch('/backend/index.php?route=intruder/status')
        .then(res => res.json())
        .then(data => {
            if (data.running) {
                intruderRunning = true;
                document.getElementById('intruderStart').style.display = 'none';
                document.getElementById('intruderStop').style.display = 'inline-block';
                updateIntruderProgress(data);
            }
        })
        .catch(e => console.error('Load status error:', e));
}

// Initialize intruder
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('intruderStart')?.addEventListener('click', startIntruder);
    document.getElementById('intruderStop')?.addEventListener('click', stopIntruder);
    
    // Auto-load status
    loadIntruderStatus();
});
