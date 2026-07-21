// ===== OTP BYPASS MODULE =====

async function runOTPBypass() {
    const target = document.getElementById('bypassTarget').value;
    const otp = document.getElementById('bypassOtp').value;
    const method = document.getElementById('bypassMethod').value;
    const body = document.getElementById('bypassBody').value;
    
    if (!target) {
        showToast('Please enter a target URL', 'warning');
        return;
    }
    
    // Get selected techniques
    const techniques = [];
    document.querySelectorAll('#techniqueGrid input[type="checkbox"]:checked').forEach(cb => {
        const label = cb.closest('label');
        if (label) {
            techniques.push(label.textContent.trim().toLowerCase().replace(/\s+/g, '_'));
        }
    });
    
    if (techniques.length === 0) {
        showToast('Please select at least one technique', 'warning');
        return;
    }
    
    const startTime = performance.now();
    showToast('Running OTP bypass techniques...', 'info');
    
    try {
        const response = await fetch('/backend/index.php?route=otp/bypass', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                target,
                otp,
                method,
                body,
                techniques
            })
        });
        
        const data = await response.json();
        const endTime = performance.now();
        
        if (data.error) {
            showToast('Bypass error: ' + data.error, 'error');
            return;
        }
        
        // Display results
        displayOTPBypassResults(data);
        
        // Update count
        const count = data.successful_count || 0;
        localStorage.setItem('otp_bypass_count', count);
        document.getElementById('otpBypassCount').textContent = count;
        
        showToast(`Bypass completed! ${count} successful techniques found`, 
                  count > 0 ? 'success' : 'info');
    } catch(e) {
        showToast('Bypass error: ' + e.message, 'error');
    }
}

function displayOTPBypassResults(data) {
    const container = document.getElementById('bypassResults');
    if (!data.all_results) {
        container.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-secondary);">No results</div>';
        return;
    }
    
    let html = `
        <div style="padding:15px;border-bottom:1px solid var(--border-color);">
            <h4>📊 Bypass Report</h4>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:10px;">
                <div><strong>Target:</strong> ${data.target || 'N/A'}</div>
                <div><strong>Techniques Tested:</strong> ${data.techniques_tested || 0}</div>
                <div><strong>Successful:</strong> ${data.successful_count || 0}</div>
                <div><strong>Severity:</strong> <span style="color:${data.severity === 'CRITICAL' ? 'var(--danger)' : 'var(--warning)'}">${data.severity || 'N/A'}</span></div>
            </div>
            ${data.recommendations ? `
                <div style="margin-top:10px;padding:10px;background:var(--bg-primary);border-radius:4px;">
                    <strong>💡 Recommendations:</strong>
                    <ul style="margin:5px 0 0 20px;">
                        ${data.recommendations.map(r => `<li>${r}</li>`).join('')}
                    </ul>
                </div>
            ` : ''}
        </div>
        <div style="padding:15px;">
    `;
    
    Object.entries(data.all_results).forEach(([technique, result]) => {
        const status = result.status || false;
        html += `
            <div style="padding:10px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;">
                <span>${technique.replace(/_/g, ' ').toUpperCase()}</span>
                <span style="color:${status ? 'var(--success)' : 'var(--danger)'};">
                    ${status ? '✅ Success' : '❌ Failed'}
                </span>
            </div>
        `;
        
        // Show details if available
        if (result.results) {
            html += `<div style="padding:5px 15px;font-size:12px;color:var(--text-secondary);">`;
            result.results.forEach(r => {
                html += `<div>• ${JSON.stringify(r)}</div>`;
            });
            html += `</div>`;
        }
    });
    
    html += '</div>';
    container.innerHTML = html;
}

function updateOTPBypassResult(result) {
    // Real-time update via WebSocket
    const container = document.getElementById('bypassResults');
    if (!container) return;
    // Could append new results here
}

// Initialize OTP bypass
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('bypassStart')?.addEventListener('click', runOTPBypass);
    
    // Select all techniques by default
    document.querySelectorAll('#techniqueGrid input[type="checkbox"]').forEach(cb => {
        cb.checked = true;
    });
});
