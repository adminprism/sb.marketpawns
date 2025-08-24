// Reports Viewer JavaScript
let currentSession = null;
let currentReport = null;
let reportsData = {};

// Toggle reports viewer visibility
function toggleReportsViewer() {
    const viewer = document.getElementById('reports_viewer');
    if (viewer.style.display === 'none') {
        viewer.style.display = 'block';
        loadReportSessions();
    } else {
        viewer.style.display = 'none';
        hideAllSections();
    }
}

// Hide all sections
function hideAllSections() {
    document.getElementById('session_details').style.display = 'none';
    document.getElementById('report_details').style.display = 'none';
}

// Load available report sessions
function loadReportSessions() {
    showLoader('Loading sessions...');
    
    fetch('reports_api.php?action=list_sessions')
        .then(response => response.json())
        .then(data => {
            hideLoader();
            if (data.success) {
                displaySessions(data.data);
            } else {
                showError('Error loading sessions: ' + data.error);
            }
        })
        .catch(error => {
            hideLoader();
            showError('Network error: ' + error.message);
        });
}

// Display sessions list
function displaySessions(sessions) {
    const container = document.getElementById('sessions_container');
    
    if (sessions.length === 0) {
        container.innerHTML = '<p>No report sessions found.</p>';
        return;
    }
    
    let html = '<div class="sessions-grid">';
    sessions.forEach(session => {
        html += `
            <div class="session-card">
                <div class="session-header" onclick="loadSessionDetails('${session.name}')">
                    <h5>📁 ${session.name}</h5>
                    <p>Created: ${session.created}</p>
                    <p>Reports: ${formatNumber(session.files_count)}</p>
                </div>
                <div class="session-actions">
                    <button class="delete-btn" onclick="event.stopPropagation(); confirmDeleteSession('${session.name}')" title="Delete Session">
                        🗑️
                    </button>
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
    hideAllSections();
}

// Load session details and summary
function loadSessionDetails(sessionName) {
    currentSession = sessionName;
    showLoader('Loading session details...');
    
    Promise.all([
        fetch(`reports_api.php?action=get_summary&session=${encodeURIComponent(sessionName)}`),
        fetch(`reports_api.php?action=list_reports&session=${encodeURIComponent(sessionName)}`)
    ])
    .then(responses => Promise.all(responses.map(r => r.json())))
    .then(([summaryData, reportsData]) => {
        hideLoader();
        if (summaryData.success && reportsData.success) {
            displaySessionDetails(summaryData.data, reportsData.data);
        } else {
            showError('Error loading session: ' + (summaryData.error || reportsData.error));
        }
    })
    .catch(error => {
        hideLoader();
        showError('Network error: ' + error.message);
    });
}

// Display session details
function displaySessionDetails(summary, reports) {
    document.getElementById('session_title').textContent = `Session: ${summary.session}`;
    
    // Display summary
    const summaryHtml = `
        <div class="summary-stats">
            <div class="stat-item">
                <strong>Total Reports:</strong> ${formatNumber(summary.total_reports)}
            </div>
            <div class="stat-item">
                <strong>Total Trades:</strong> ${formatNumber(summary.total_trades)}
            </div>
            <div class="stat-item">
                <strong>Total Profit:</strong> <span class="profit">${formatNumber(summary.total_profit)}</span>
            </div>
            <div class="stat-item">
                <strong>Total Loss:</strong> <span class="loss">${formatNumber(summary.total_loss)}</span>
            </div>
            <div class="stat-item">
                <strong>Net Result:</strong> <span class="${summary.net_result >= 0 ? 'profit' : 'loss'}">${formatNumber(summary.net_result)}</span>
            </div>
        </div>
    `;
    document.getElementById('session_summary').innerHTML = summaryHtml;
    
    // Sort reports by Net Result for rating
    const sortedReports = [...reports].map(report => {
        const summaryReport = summary.reports.find(r => r.filename === report.filename);
        return {
            ...report,
            netResult: summaryReport ? summaryReport.net : 0,
            trades: summaryReport ? summaryReport.trades : 0
        };
    }).sort((a, b) => b.netResult - a.netResult);

    // Display reports list with ratings
    let reportsHtml = '<div class="reports-grid">';
    sortedReports.forEach((report, index) => {
        const rating = getRating(index + 1, sortedReports.length, report.netResult);
        
        reportsHtml += `
            <div class="report-card">
                <div class="report-content" onclick="loadReportDetails('${report.filename}')">
                    <div class="report-header">
                        <h6>${report.filename}</h6>
                        <div class="rating-badge ${rating.class}">
                            ${rating.icon} #${index + 1}
                        </div>
                    </div>
                    <p>Trades: ${formatNumber(report.trades)}</p>
                    <p>Net: <span class="${report.netResult >= 0 ? 'profit' : 'loss'}">${formatNumber(report.netResult)}</span></p>
                    <p>Size: ${formatFileSize(report.size)}</p>
                    <div class="chart-indicators">
                        ${report.has_chart ? '📈' : ''} ${report.has_chart_proc ? '📊' : ''}
                    </div>
                </div>
                <div class="report-actions">
                    <button class="delete-btn" onclick="event.stopPropagation(); confirmDeleteReport('${report.filename}')" title="Delete Report">
                        🗑️
                    </button>
                </div>
            </div>
        `;
    });
    reportsHtml += '</div>';
    
    document.getElementById('reports_list').innerHTML = reportsHtml;
    document.getElementById('session_details').style.display = 'block';
    document.getElementById('report_details').style.display = 'none';
}

// Load specific report details
function loadReportDetails(filename) {
    currentReport = filename;
    showLoader('Loading report details...');
    
    fetch(`reports_api.php?action=get_report&session=${encodeURIComponent(currentSession)}&filename=${encodeURIComponent(filename)}`)
        .then(response => response.json())
        .then(data => {
            hideLoader();
            if (data.success) {
                displayReportDetails(data.data);
            } else {
                showError('Error loading report: ' + data.error);
            }
        })
        .catch(error => {
            hideLoader();
            showError('Network error: ' + error.message);
        });
}

// Display report details
function displayReportDetails(reportData) {
    document.getElementById('report_title').textContent = `Report: ${reportData._file_info.filename}`;
    
    let html = '<div class="report-content">';
    
    // File info
    html += `
        <div class="file-info">
            <h5>📄 File Information</h5>
            <p><strong>Session:</strong> ${reportData._file_info.session}</p>
            <p><strong>Size:</strong> ${formatFileSize(reportData._file_info.size)}</p>
            <p><strong>Modified:</strong> ${reportData._file_info.modified}</p>
        </div>
    `;
    
    // Charts
    if (reportData._file_info.chart_url || reportData._file_info.chart_proc_url) {
        html += '<div class="charts-section"><h5>📈 Charts</h5>';
        if (reportData._file_info.chart_url) {
            html += `<div class="chart-container">
                <h6>PnL Chart (Pips)</h6>
                <img src="${reportData._file_info.chart_url}" alt="PnL Chart" style="max-width: 100%; height: auto;" onclick="openImageModal('${reportData._file_info.chart_url}')">
            </div>`;
        }
        if (reportData._file_info.chart_proc_url) {
            html += `<div class="chart-container">
                <h6>PnL Chart (Percentage)</h6>
                <img src="${reportData._file_info.chart_proc_url}" alt="PnL Chart %" style="max-width: 100%; height: auto;" onclick="openImageModal('${reportData._file_info.chart_proc_url}')">
            </div>`;
        }
        html += '</div>';
    }
    
    // Summary report
    if (reportData['Отчет'] || reportData['Report']) {
        const report = reportData['Отчет'] || reportData['Report'];
        html += '<div class="summary-report"><h5>📊 Summary</h5><table class="report-table">';
        
        Object.entries(report).forEach(([key, value]) => {
            html += `<tr><td>${key}</td><td>${value}</td></tr>`;
        });
        
        html += '</table></div>';
    }
    
    // Key metrics
    html += '<div class="key-metrics"><h5>🎯 Key Metrics</h5><table class="metrics-table">';
    const metrics = [
        ['ALL_CNT', 'Total Models'],
        ['TRADE_CNT', 'Trades Count'],
        ['CANCEL_CNT', 'Cancelled'],
        ['PROFIT', 'Profit'],
        ['LOSS', 'Loss'],
        ['AIM_CNT', 'Reached Aim'],
        ['SL1_CNT', 'Stop Loss 1'],
        ['SL2_CNT', 'Stop Loss 2'],
        ['SL3_CNT', 'Stop Loss 3']
    ];
    
    metrics.forEach(([key, label]) => {
        if (reportData[key] !== undefined) {
            html += `<tr><td>${label}</td><td>${formatNumber(reportData[key])}</td></tr>`;
        }
    });
    html += '</table></div>';
    
    // Setup parameters
    if (reportData.setupParams) {
        html += '<div class="setup-params"><h5>⚙️ Setup Parameters</h5><table class="params-table">';
        Object.entries(reportData.setupParams).forEach(([key, value]) => {
            html += `<tr><td>${key}</td><td>${JSON.stringify(value)}</td></tr>`;
        });
        html += '</table></div>';
    }
    
    // PnL data preview
    if (reportData.PNLs && reportData.PNLs.length > 0) {
        html += `<div class="pnl-data"><h5>💰 PnL Data (${formatNumber(reportData.PNLs.length)} trades)</h5>`;
        html += '<table class="pnl-table"><tr><th>Model ID</th><th>Close Time</th><th>PnL</th><th>Close Level</th></tr>';
        
        // Show first 10 trades
        reportData.PNLs.slice(0, 10).forEach(trade => {
            html += `<tr>
                <td>${formatNumber(trade.model_id)}</td>
                <td>${trade.close_time}</td>
                <td class="${trade.pnl >= 0 ? 'profit' : 'loss'}">${formatNumber(trade.pnl)}</td>
                <td>${formatNumber(trade.close_lvl)}</td>
            </tr>`;
        });
        
        if (reportData.PNLs.length > 10) {
            html += `<tr><td colspan="4">... and ${formatNumber(reportData.PNLs.length - 10)} more trades</td></tr>`;
        }
        
        html += '</table></div>';
    }
    
    html += '</div>';
    
    document.getElementById('report_content').innerHTML = html;
    document.getElementById('session_details').style.display = 'none';
    document.getElementById('report_details').style.display = 'block';
    
    // Store data for export
    reportsData.currentReportData = reportData;
}

// Show session details (back button)
function showSessionDetails() {
    document.getElementById('session_details').style.display = 'block';
    document.getElementById('report_details').style.display = 'none';
}

// Export report data
function exportReportData() {
    if (!reportsData.currentReportData) {
        showError('No report data to export');
        return;
    }
    
    const dataStr = JSON.stringify(reportsData.currentReportData, null, 2);
    const dataBlob = new Blob([dataStr], {type: 'application/json'});
    const url = URL.createObjectURL(dataBlob);
    
    const link = document.createElement('a');
    link.href = url;
    link.download = `${currentSession}_${currentReport}`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

// Open image in modal
function openImageModal(imageSrc) {
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(0,0,0,0.8); z-index: 10000; display: flex; 
        align-items: center; justify-content: center; cursor: pointer;
    `;
    
    const img = document.createElement('img');
    img.src = imageSrc;
    img.style.cssText = 'max-width: 90%; max-height: 90%; object-fit: contain;';
    
    modal.appendChild(img);
    modal.onclick = () => document.body.removeChild(modal);
    document.body.appendChild(modal);
}

// Get rating based on position and net result
function getRating(position, totalReports, netResult) {
    // For single report
    if (totalReports === 1) {
        if (netResult > 0) return { icon: '🏆', class: 'rating-gold' };
        if (netResult === 0) return { icon: '⚪', class: 'rating-neutral' };
        return { icon: '🔴', class: 'rating-poor' };
    }
    
    // Calculate position percentage
    const percentage = ((position - 1) / (totalReports - 1)) * 100;
    
    if (percentage <= 20) {
        // Top 20% - Gold
        return { icon: '🏆', class: 'rating-gold' };
    } else if (percentage <= 40) {
        // Top 40% - Silver
        return { icon: '🥈', class: 'rating-silver' };
    } else if (percentage <= 60) {
        // Top 60% - Bronze
        return { icon: '🥉', class: 'rating-bronze' };
    } else if (percentage <= 80) {
        // Top 80% - Average
        return { icon: '🟡', class: 'rating-average' };
    } else {
        // Bottom 20% - Poor
        return { icon: '🔴', class: 'rating-poor' };
    }
}

// Utility functions
function formatNumber(num) {
    if (num === null || num === undefined) return '0';
    return Number(num).toLocaleString('en-US');
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function showLoader(message) {
    // Reuse existing loader or create simple one
    if (typeof loaderOn === 'function') {
        loaderOn();
    } else {
        console.log('Loading: ' + message);
    }
}

function hideLoader() {
    if (typeof loaderOff === 'function') {
        loaderOff();
    }
}

function showError(message) {
    alert('Error: ' + message);
    console.error(message);
}

// Delete functions
function confirmDeleteSession(sessionName) {
    if (confirm(`Are you sure you want to delete the entire session "${sessionName}"?\n\nThis will permanently delete all reports and charts in this session.`)) {
        deleteSessionRequest(sessionName);
    }
}

function confirmDeleteReport(filename) {
    if (confirm(`Are you sure you want to delete the report "${filename}"?\n\nThis will also delete associated chart files.`)) {
        deleteReportRequest(filename);
    }
}

function deleteSessionRequest(sessionName) {
    showLoader('Deleting session...');
    
    const formData = new FormData();
    formData.append('action', 'delete_session');
    formData.append('session', sessionName);
    
    fetch('reports_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoader();
        if (data.success) {
            alert(`Session deleted successfully!\nDeleted ${data.data.deleted_files.length} files.`);
            loadReportSessions(); // Refresh sessions list
        } else {
            showError('Failed to delete session: ' + data.error);
        }
    })
    .catch(error => {
        hideLoader();
        showError('Network error: ' + error.message);
    });
}

function deleteReportRequest(filename) {
    showLoader('Deleting report...');
    
    const formData = new FormData();
    formData.append('action', 'delete_report');
    formData.append('session', currentSession);
    formData.append('filename', filename);
    
    fetch('reports_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoader();
        if (data.success) {
            alert(`Report deleted successfully!\nDeleted files: ${data.data.deleted_files.join(', ')}`);
            loadSessionDetails(currentSession); // Refresh current session
        } else {
            showError('Failed to delete report: ' + data.error);
        }
    })
    .catch(error => {
        hideLoader();
        showError('Network error: ' + error.message);
    });
}
