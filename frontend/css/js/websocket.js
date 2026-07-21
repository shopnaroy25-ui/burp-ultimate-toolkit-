// ===== WEBSOCKET MODULE =====
// This extends the main WebSocket functionality from app.js

// Additional WebSocket handlers
function setupWebSocketExtended() {
    if (APP.ws) {
        // Handle specific message types
        const originalHandler = APP.ws.onmessage;
        APP.ws.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);
                handleExtendedMessage(data);
            } catch(e) {
                console.error('WebSocket parse error:', e);
            }
        };
    }
}

function handleExtendedMessage(data) {
    switch(data.type) {
        case 'intruder_progress':
            updateIntruderProgress(data);
            break;
        case 'otp_bypass_result':
            updateOTPBypassResult(data);
            break;
        case 'ssl_status':
            updateSSLStatus(data);
            break;
        case 'ai_training':
            showToast('AI Training completed: ' + (data.message || ''), 'info');
            break;
        default:
            // Pass to main handler
            if (window.handleWebSocketMessage) {
                window.handleWebSocketMessage(data);
            }
    }
}

// Send WebSocket message
function sendWSMessage(type, data) {
    if (APP.ws && APP.ws.readyState === WebSocket.OPEN) {
        APP.ws.send(JSON.stringify({ type, ...data }));
    } else {
        console.warn('WebSocket not connected');
    }
}

// Reconnection with exponential backoff
function setupReconnection() {
    let attempts = 0;
    const maxAttempts = 10;
    const baseDelay = 1000;
    
    function reconnect() {
        if (attempts >= maxAttempts) {
            console.error('Max reconnection attempts reached');
            return;
        }
        
        const delay = baseDelay * Math.pow(2, attempts);
        attempts++;
        
        setTimeout(() => {
            setupWebSocket();
        }, delay);
    }
    
    // Override close handler
    const originalClose = APP.ws?.onclose;
    if (APP.ws) {
        APP.ws.onclose = function(event) {
            if (originalClose) originalClose(event);
            reconnect();
        };
    }
}

// Initialize extended WebSocket
document.addEventListener('DOMContentLoaded', function() {
    setupWebSocketExtended();
    setupReconnection();
});
