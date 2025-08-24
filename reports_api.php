<?php
// API for reading trade emulation reports
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'data' => null, 'error' => null];

try {
    switch ($action) {
        case 'list_sessions':
            $response['data'] = listReportSessions();
            $response['success'] = true;
            break;
            
        case 'list_reports':
            $session = $_GET['session'] ?? '';
            if (!$session) {
                throw new Exception('Session parameter required');
            }
            $response['data'] = listSessionReports($session);
            $response['success'] = true;
            break;
            
        case 'get_report':
            $session = $_GET['session'] ?? '';
            $filename = $_GET['filename'] ?? '';
            if (!$session || !$filename) {
                throw new Exception('Session and filename parameters required');
            }
            $response['data'] = getReportData($session, $filename);
            $response['success'] = true;
            break;
            
        case 'get_summary':
            $session = $_GET['session'] ?? '';
            if (!$session) {
                throw new Exception('Session parameter required');
            }
            $response['data'] = getSessionSummary($session);
            $response['success'] = true;
            break;
            
        case 'delete_report':
            $session = $_POST['session'] ?? '';
            $filename = $_POST['filename'] ?? '';
            if (!$session || !$filename) {
                throw new Exception('Session and filename parameters required');
            }
            $response['data'] = deleteReport($session, $filename);
            $response['success'] = true;
            break;
            
        case 'delete_session':
            $session = $_POST['session'] ?? '';
            if (!$session) {
                throw new Exception('Session parameter required');
            }
            $response['data'] = deleteSession($session);
            $response['success'] = true;
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

function listReportSessions() {
    $reportsDir = 'Reports';
    if (!is_dir($reportsDir)) {
        return [];
    }
    
    $sessions = [];
    $dirs = glob($reportsDir . '/*', GLOB_ONLYDIR);
    
    foreach ($dirs as $dir) {
        $sessionName = basename($dir);
        $sessions[] = [
            'name' => $sessionName,
            'path' => $dir,
            'created' => date('Y-m-d H:i:s', filemtime($dir)),
            'files_count' => count(glob($dir . '/*.json'))
        ];
    }
    
    // Sort by creation time, newest first
    usort($sessions, function($a, $b) {
        return strcmp($b['created'], $a['created']);
    });
    
    return $sessions;
}

function listSessionReports($session) {
    $sessionDir = 'Reports/' . $session;
    if (!is_dir($sessionDir)) {
        throw new Exception('Session not found');
    }
    
    $reports = [];
    $jsonFiles = glob($sessionDir . '/*.json');
    
    foreach ($jsonFiles as $file) {
        $filename = basename($file);
        $reports[] = [
            'filename' => $filename,
            'path' => $file,
            'size' => filesize($file),
            'modified' => date('Y-m-d H:i:s', filemtime($file)),
            'has_chart' => file_exists(str_replace('.json', '.jpg', $file)),
            'has_chart_proc' => file_exists(str_replace('.json', '_proc.jpg', $file))
        ];
    }
    
    return $reports;
}

function getReportData($session, $filename) {
    $filePath = 'Reports/' . $session . '/' . $filename;
    if (!file_exists($filePath)) {
        throw new Exception('Report file not found');
    }
    
    $jsonContent = file_get_contents($filePath);
    $data = json_decode($jsonContent, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON format: ' . json_last_error_msg());
    }
    
    // Add file info
    $data['_file_info'] = [
        'filename' => $filename,
        'session' => $session,
        'size' => filesize($filePath),
        'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
        'chart_url' => file_exists(str_replace('.json', '.jpg', $filePath)) ? 
            str_replace('.json', '.jpg', $filePath) : null,
        'chart_proc_url' => file_exists(str_replace('.json', '_proc.jpg', $filePath)) ? 
            str_replace('.json', '_proc.jpg', $filePath) : null
    ];
    
    return $data;
}

function getSessionSummary($session) {
    $sessionDir = 'Reports/' . $session;
    if (!is_dir($sessionDir)) {
        throw new Exception('Session not found');
    }
    
    $summary = [
        'session' => $session,
        'total_reports' => 0,
        'total_trades' => 0,
        'total_profit' => 0,
        'total_loss' => 0,
        'net_result' => 0,
        'reports' => []
    ];
    
    $jsonFiles = glob($sessionDir . '/*.json');
    
    foreach ($jsonFiles as $file) {
        $jsonContent = file_get_contents($file);
        $data = json_decode($jsonContent, true);
        
        if (json_last_error() === JSON_ERROR_NONE && isset($data['TRADE_CNT'])) {
            $filename = basename($file);
            $profit = $data['PROFIT'] ?? 0;
            $loss = $data['LOSS'] ?? 0;
            $trades = $data['TRADE_CNT'] ?? 0;
            
            $summary['reports'][] = [
                'filename' => $filename,
                'trades' => $trades,
                'profit' => $profit,
                'loss' => $loss,
                'net' => $profit - $loss
            ];
            
            $summary['total_trades'] += $trades;
            $summary['total_profit'] += $profit;
            $summary['total_loss'] += $loss;
        }
        
        $summary['total_reports']++;
    }
    
    $summary['net_result'] = $summary['total_profit'] - $summary['total_loss'];
    
    // Sort reports by net result
    usort($summary['reports'], function($a, $b) {
        return $b['net'] <=> $a['net'];
    });
    
    return $summary;
}

function deleteReport($session, $filename) {
    $sessionDir = 'Reports/' . $session;
    if (!is_dir($sessionDir)) {
        throw new Exception('Session not found');
    }
    
    // Validate filename to prevent directory traversal
    if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        throw new Exception('Invalid filename');
    }
    
    $jsonFile = $sessionDir . '/' . $filename;
    $jpgFile = str_replace('.json', '.jpg', $jsonFile);
    $jpgProcFile = str_replace('.json', '_proc.jpg', $jsonFile);
    
    $deletedFiles = [];
    
    // Delete JSON file
    if (file_exists($jsonFile)) {
        if (unlink($jsonFile)) {
            $deletedFiles[] = $filename;
        } else {
            throw new Exception('Failed to delete report file');
        }
    } else {
        throw new Exception('Report file not found');
    }
    
    // Delete associated chart files
    if (file_exists($jpgFile)) {
        if (unlink($jpgFile)) {
            $deletedFiles[] = basename($jpgFile);
        }
    }
    
    if (file_exists($jpgProcFile)) {
        if (unlink($jpgProcFile)) {
            $deletedFiles[] = basename($jpgProcFile);
        }
    }
    
    return [
        'deleted_files' => $deletedFiles,
        'message' => 'Report deleted successfully'
    ];
}

function deleteSession($session) {
    $sessionDir = 'Reports/' . $session;
    if (!is_dir($sessionDir)) {
        throw new Exception('Session not found');
    }
    
    // Validate session name to prevent directory traversal
    if (strpos($session, '..') !== false || strpos($session, '/') !== false || strpos($session, '\\') !== false) {
        throw new Exception('Invalid session name');
    }
    
    $deletedFiles = [];
    
    // Delete all files in session directory
    $files = glob($sessionDir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            if (unlink($file)) {
                $deletedFiles[] = basename($file);
            }
        }
    }
    
    // Delete TTLs subdirectory if exists
    $ttlsDir = $sessionDir . '/TTLs';
    if (is_dir($ttlsDir)) {
        $ttlsFiles = glob($ttlsDir . '/*');
        foreach ($ttlsFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($ttlsDir);
    }
    
    // Delete session directory
    if (rmdir($sessionDir)) {
        return [
            'deleted_files' => $deletedFiles,
            'message' => 'Session deleted successfully'
        ];
    } else {
        throw new Exception('Failed to delete session directory');
    }
}
?>
