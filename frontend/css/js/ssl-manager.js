// ===== SSL MANAGER MODULE =====

async function generateCA() {
    showToast('Generating CA certificate...', 'info');
    
    try {
        const response = await fetch('/backend/index.php?route=ssl/generate', {
            method: 'POST'
        });
        const data = await response.json();
        
        if (data.error) {
            showToast('CA generation error: ' + data.error, 'error');
            return;
        }
        
        document.getElementById('sslStatus').innerHTML = `
            <div style="padding:15px;background:var(--bg-primary);border-radius:4px;">
                <h4>✅ CA Certificate ${data.status === 'generated' ? 'Generated' : 'Exists'}</h4>
                <div style="margin-top:10px;font-size:13px;color:var(--text-secondary);">
                    ${data.message || 'CA certificate is ready'}
                    ${data.fingerprint ? `<div><strong>Fingerprint:</strong> ${data.fingerprint}</div>` : ''}
                    ${data.download_url ? `<div><strong>Download:</strong> <a href="${data.download_url}" style="color:var(--primary);">Download CA Certificate</a></div>` : ''}
                </div>
            </div>
        `;
        
        showToast(data.message || 'CA certificate ready', 'success');
    } catch(e) {
        showToast('CA generation error: ' + e.message, 'error');
    }
}

function downloadCA() {
    window.location.href = '/backend/index.php?route=ssl/download';
    showToast('Downloading CA certificate...', 'info');
}

async function viewPinningGuide() {
    try {
        const response = await fetch('/backend/index.php?route=ssl/pinning-guide');
        const data = await response.json();
        
        if (data.error) {
            showToast('Guide error: ' + data.error, 'error');
            return;
        }
        
        let html = `<h4>${data.title || 'SSL Pinning Bypass Guide'}</h4>`;
        if (data.sections) {
            data.sections.forEach(section => {
                html += `
                    <div style="margin:15px 0;padding:15px;background:var(--bg-primary);border-radius:4px;">
                        <h5>${section.title}</h5>
                        <p style="color:var(--text-secondary);font-size:13px;">${section.description}</p>
                        ${section.script ? `<pre><code>${section.script}</code></pre>` : ''}
                        ${section.command ? `<pre><code>${section.command}</code></pre>` : ''}
                        ${section.steps ? `<ul>${section.steps.map(s => `<li>${s}</li>`).join('')}</ul>` : ''}
                    </div>
                `;
            });
        }
        
        document.getElementById('sslGuide').innerHTML = html;
    } catch(e) {
        showToast('Guide error: ' + e.message, 'error');
    }
}

async function generateDomainCert() {
    const domain = document.getElementById('sslDomain').value;
    const san = document.getElementById('sslSan').value;
    
    if (!domain) {
        showToast('Please enter a domain', 'warning');
        return;
    }
    
    const sanArray = san.split(',').map(s => s.trim()).filter(Boolean);
    
    showToast('Generating domain certificate...', 'info');
    
    try {
        const response = await fetch('/backend/index.php?route=ssl/domain', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ domain, san: sanArray })
        });
        const data = await response.json();
        
        if (data.error) {
            showToast('Domain cert error: ' + data.error, 'error');
            return;
        }
        
        document.getElementById('sslStatus').innerHTML += `
            <div style="padding:15px;background:var(--bg-primary);border-radius:4px;margin-top:10px;">
                <h4>✅ Certificate for ${data.domain}</h4>
                <div style="margin-top:10px;font-size:13px;color:var(--text-secondary);">
                    <div><strong>Cert File:</strong> ${data.cert_file || 'N/A'}</div>
                    <div><strong>Key File:</strong> ${data.key_file || 'N/A'}</div>
                    <div><strong>Expires:</strong> ${data.expires || 'N/A'}</div>
                    ${data.fingerprint ? `<div><strong>Fingerprint:</strong> ${data.fingerprint}</div>` : ''}
                </div>
            </div>
        `;
        
        showToast('Domain certificate generated', 'success');
    } catch(e) {
        showToast('Domain cert error: ' + e.message, 'error');
    }
}

function loadSSLStatus() {
    fetch('/backend/index.php?route=ssl/generate', {
        method: 'POST'
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'exists') {
            document.getElementById('sslStatus').innerHTML = `
                <div style="padding:15px;background:var(--bg-primary);border-radius:4px;">
                    <h4>✅ CA Certificate Ready</h4>
                    <div style="margin-top:10px;font-size:13px;color:var(--text-secondary);">
                        ${data.message || 'CA certificate exists'}
                        ${data.download_url ? `<div><strong>Download:</strong> <a href="${data.download_url}" style="color:var(--primary);">Download CA Certificate</a></div>` : ''}
                    </div>
                </div>
            `;
        }
    })
    .catch(e => console.error('SSL status error:', e));
}

// Initialize SSL manager
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('generateCA')?.addEventListener('click', generateCA);
    document.getElementById('downloadCA')?.addEventListener('click', downloadCA);
    document.getElementById('viewPinningGuide')?.addEventListener('click', viewPinningGuide);
    document.getElementById('generateDomainCert')?.addEventListener('click', generateDomainCert);
    
    loadSSLStatus();
});
