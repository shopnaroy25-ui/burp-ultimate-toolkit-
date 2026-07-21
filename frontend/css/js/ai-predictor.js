// ===== AI PREDICTOR MODULE =====

async function predictOTP() {
    const otpsText = document.getElementById('aiOtps').value;
    const limit = parseInt(document.getElementById('aiLimit').value) || 10;
    
    if (!otpsText.trim()) {
        showToast('Please enter some OTPs', 'warning');
        return;
    }
    
    const otps = otpsText.split(',').map(s => s.trim()).filter(Boolean);
    
    if (otps.length < 2) {
        showToast('Please enter at least 2 OTPs for prediction', 'warning');
        return;
    }
    
    showToast('Analyzing patterns and predicting...', 'info');
    
    try {
        const response = await fetch('/backend/index.php?route=ai/predict', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ otps, limit })
        });
        const data = await response.json();
        
        if (data.error) {
            showToast('AI error: ' + data.error, 'error');
            return;
        }
        
        displayAIPredictions(data);
        
        showToast('Prediction complete!', 'success');
    } catch(e) {
        showToast('AI error: ' + e.message, 'error');
    }
}

function displayAIPredictions(data) {
    const container = document.getElementById('aiResults');
    
    let html = `
        <div style="padding:15px;border-bottom:1px solid var(--border-color);">
            <h4>🤖 AI Prediction Results</h4>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:10px;">
                <div><strong>Confidence:</strong> ${Math.round((data.confidence || 0) * 100)}%</div>
                <div><strong>OTPs Analyzed:</strong> ${data.total_otps_analyzed || 0}</div>
            </div>
        </div>
        <div style="padding:15px;">
    `;
    
    // Predicted OTPs
    if (data.predictions && data.predictions.length > 0) {
        html += `<h5>🔮 Predicted OTPs (${data.predictions.length})</h5><div style="display:flex;flex-wrap:wrap;gap:10px;margin:10px 0;">`;
        data.predictions.forEach(otp => {
            html += `<span style="padding:5px 15px;background:var(--bg-primary);border-radius:4px;font-family:monospace;">${otp}</span>`;
        });
        html += `</div>`;
    }
    
    // Patterns found
    if (data.patterns_found) {
        html += `<h5>📊 Patterns Found</h5><div style="margin:10px 0;">`;
        Object.entries(data.patterns_found).forEach(([pattern, value]) => {
            html += `<div style="padding:5px;font-size:13px;color:var(--text-secondary);">• ${pattern.replace(/_/g, ' ')}: ${JSON.stringify(value)}</div>`;
        });
        html += `</div>`;
    }
    
    html += '</div>';
    container.innerHTML = html;
}

async function trainAIModel() {
    const otpsText = document.getElementById('aiOtps').value;
    if (!otpsText.trim()) {
        showToast('Please enter some OTPs for training', 'warning');
        return;
    }
    
    const otps = otpsText.split(',').map(s => s.trim()).filter(Boolean);
    
    showToast('Training AI model...', 'info');
    
    try {
        const response = await fetch('/backend/index.php?route=ai/train', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ otps })
        });
        const data = await response.json();
        
        if (data.error) {
            showToast('Training error: ' + data.error, 'error');
            return;
        }
        
        showToast(`Model trained! ${data.total_otps || 0} OTPs learned`, 'success');
    } catch(e) {
        showToast('Training error: ' + e.message, 'error');
    }
}

// Initialize AI predictor
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('aiPredict')?.addEventListener('click', predictOTP);
    document.getElementById('aiTrain')?.addEventListener('click', trainAIModel);
});
