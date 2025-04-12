<?php
// Function to detect CSV delimiter
function detectDelimiter($file) {
    $delimiters = [',', ';'];
    $firstLine = fgets(fopen($file, 'r'));
    $counts = array_map(function($delimiter) use ($firstLine) {
        return substr_count($firstLine, $delimiter);
    }, $delimiters);
    return $delimiters[array_search(max($counts), $counts)];
}

// Function to read and parse CSV file
function readCSV($file) {
    if (!file_exists($file)) {
        return array(array(), array());
    }

    $delimiter = detectDelimiter($file);
    
    // Use fgetcsv instead of array_map for better handling of quoted fields and spaces
    $rows = [];
    $handle = fopen($file, "r");
    if ($handle) {
        while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
            $rows[] = $data;
        }
        fclose($handle);
    }

    if (empty($rows)) {
        return array(array(), array());
    }

    // Clean up headers (remove BOM if present)
    $header = array_map(function($h) {
        return trim($h, "\xEF\xBB\xBF");
    }, $rows[0]);

    array_shift($rows);
    $csv = array();
    
    foreach($rows as $row) {
        // Skip completely empty rows
        if (empty(array_filter($row))) {
            continue;
        }
        
        // Modified: Only check if row has content, not strict column count
        if (!empty($row[0])) {
            // Handle different column counts
            if (count($row) < count($header)) {
                // Fill missing values with empty strings
                $row = array_pad($row, count($header), "");
            } elseif (count($row) > count($header)) {
                // Truncate extra columns
                $row = array_slice($row, 0, count($header));
            }
            
            // Create associative array
            $csv[] = array_combine($header, $row);
        }
    }
    
    return array($header, $csv);
}

// Add logging endpoint
if (isset($_GET['debug']) && $_GET['debug'] === 'csv') {
    $fileId = $_GET['file'] ?? 'parameters';
    $csvFiles = [
        'parameters' => __DIR__ . '/parameters.csv',
        'legend' => __DIR__ . '/Legend.csv',
        'potential' => __DIR__ . '/potential_next_parameters.csv',
        'next' => __DIR__ . '/next_parameters.csv'
    ];
    
    if (isset($csvFiles[$fileId])) {
        header('Content-Type: text/plain');
        echo "Debugging CSV parsing for: " . basename($csvFiles[$fileId]) . "\n\n";
        
        $file = $csvFiles[$fileId];
        $delimiter = detectDelimiter($file);
        echo "Detected delimiter: '" . $delimiter . "'\n\n";
        
        // Use fgetcsv for consistent parsing
        $rows = [];
        $handle = fopen($file, "r");
        if ($handle) {
            while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                $rows[] = $data;
            }
            fclose($handle);
        }
        
        echo "Total rows found: " . count($rows) . "\n";
        if (!empty($rows)) {
            echo "Header count: " . count($rows[0]) . "\n\n";
            
            echo "Row analysis:\n";
            for ($i = 1; $i < min(count($rows), 20); $i++) {
                $row = $rows[$i];
                echo "Row " . $i . ": " . count($row) . " columns. First value: '" . ($row[0] ?? 'empty') . "'\n";
                
                // Show a sample of the first cell with special character highlighting
                if (!empty($row[0])) {
                    $sample = $row[0];
                    echo "   Sample (first 50 chars): [" . substr($sample, 0, 50) . "]\n";
                    echo "   Contains spaces: " . (strpos($sample, ' ') !== false ? 'Yes' : 'No') . "\n";
                    echo "   Contains tabs: " . (strpos($sample, "\t") !== false ? 'Yes' : 'No') . "\n";
                    echo "   Contains newlines: " . (strpos($sample, "\n") !== false ? 'Yes' : 'No') . "\n";
                    echo "\n";
                }
            }
        } else {
            echo "No rows found in the file.\n";
        }
        
        exit;
    }
}

// Function to get table description
function getTableDescription($fileId) {
    $descriptions = [
        'parameters' => 'Complete list of model parameters with their types, descriptions, and dependencies.',
        'legend' => 'Explanation of key terms and abbreviations used in the model calculations.',
        'potential' => 'Parameters used for potential model calculations and predictions.',
        'next' => 'Parameters used for next bar calculations and predictions.'
    ];
    return $descriptions[$fileId] ?? '';
}

// Add new function to get table HTML
function getTableHtml($headers, $data, $fileId = '') {
    // Extract and remove Additional information column completely
    $additionalInfoData = [];
    $hasAdditionalInfo = false;
    $additionalInfoIndex = array_search('Additional information', $headers);
    
    if ($additionalInfoIndex !== false) {
        $hasAdditionalInfo = true;
        
        // Store Additional information separately for each row
        foreach ($data as $rowIndex => $row) {
            if (isset($row['Additional information']) && !empty($row['Additional information'])) {
                $additionalInfoData[$rowIndex] = $row['Additional information'];
            }
        }
        
        // Remove Additional information from headers
        unset($headers[$additionalInfoIndex]);
        $headers = array_values($headers); // Reindex array
        
        // Remove Additional information from each row
        foreach ($data as $rowIndex => &$row) {
            if (isset($row['Additional information'])) {
                unset($row['Additional information']);
            }
        }
    }
    
    $html = '<table tabindex="0" class="data-table" aria-label="Data table for ' . htmlspecialchars($fileId) . '">';
    
    // Headers - now without Additional information
    $html .= "<tr class='header-row'>";
    foreach ($headers as $headerIndex => $header) {
        // Skip Column8 if present
        if ($header !== 'Column8') {
            $html .= "<th tabindex='0' role='button' aria-sort='none' onClick='sortTable(this, \"$fileId\")'>" . htmlspecialchars($header) . "</th>";
        }
    }
    $html .= "</tr>";
    
    // Data rows - Add original index tracking
    $originalIndex = 0;
    foreach ($data as $rowIndex => $row) {
        $firstCell = reset($row);
        if (strpos($firstCell, '//') === 0) {
            $html .= "<tr class='section-row' data-original-index='{$originalIndex}'><td colspan='" . count($headers) . "' class='section-header'>" . 
                     htmlspecialchars(trim($firstCell, '/ ')) . "</td></tr>";
            $originalIndex++;
            continue;
        }
        
        if (empty(array_filter($row))) {
            continue;
        }
        
        // Add data-row-id attribute, original index, and click handler
        $html .= "<tr class='data-row' data-row-id='$rowIndex' data-original-index='{$originalIndex}' tabindex='0' onclick='selectRow(this, \"$fileId\")' onkeydown='handleRowKeydown(event, this, \"$fileId\")'>";
        
        foreach ($row as $key => $cell) {
            $header = $headers[$key] ?? '';
            // Skip Column8 if present
            if ($header !== 'Column8') {
                $html .= "<td><div class='cell-content'>" . htmlspecialchars($cell) . "</div></td>";
            }
        }
        $html .= "</tr>";
        $originalIndex++;
    }
    
    $html .= '</table>';
    
    // If Additional information exists, add the hidden container
    if ($hasAdditionalInfo) {
        // For direct table generation
        $html .= "<div id='additional-data-$fileId' style='display:none;'>";
        foreach ($additionalInfoData as $rowIndex => $infoText) {
            $html .= "<div data-row-id='$rowIndex'>" . htmlspecialchars($infoText) . "</div>";
        }
        $html .= "</div>";
    }
    
    // If this is an AJAX refresh request and there's Additional information,
    // the hidden data is already included above
    
    return $html;
}

// Add endpoint for AJAX refresh
if (isset($_POST['action']) && $_POST['action'] === 'refresh') {
    header('Content-Type: text/html; charset=utf-8');
    $fileId = $_POST['fileId'] ?? '';
    $csvFiles = [
        'parameters' => __DIR__ . '/parameters.csv',
        'legend' => __DIR__ . '/Legend.csv',
        'potential' => __DIR__ . '/potential_next_parameters.csv',
        'next' => __DIR__ . '/next_parameters.csv'
    ];
    
    if (isset($csvFiles[$fileId])) {
        $file = $csvFiles[$fileId];
        
        // Add logging for debugging
        $debug = [];
        $debug['file'] = $file;
        $debug['exists'] = file_exists($file);
        $debug['timestamp'] = date('Y-m-d H:i:s');
        $debug['filesize'] = file_exists($file) ? filesize($file) : 0;
        
        if (!file_exists($file)) {
            echo "<div class='error-message'>Unable to load " . basename($file) . ". File may be missing.</div>";
            // Add debug info in comment
            echo "<!-- Debug info: " . json_encode($debug) . " -->";
            exit;
        }
        
        // Force cache invalidation by adding timestamp parameter
        clearstatcache(true, $file);
        
        list($headers, $data) = readCSV($file);
        
        $debug['header_count'] = count($headers);
        $debug['data_count'] = count($data);
        $debug['headers'] = $headers;
        $debug['encoding'] = mb_detect_encoding(file_get_contents($file));
        
        if (empty($headers)) {
            echo "<div class='error-message'>Unable to load " . basename($file) . ". File may be empty or has invalid format.</div>";
            
            // Add debug info in comment
            echo "<!-- Debug info: " . json_encode($debug) . " -->";
            exit;
        }
        
        echo getTableHtml($headers, $data, $fileId);
        
        // Add debug info in comment with more useful information
        echo "<!-- Debug info: " . json_encode($debug) . " -->";
    } else {
        echo "<div class='error-message'>Invalid file specified.</div>";
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Pawns Infobase</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/mobile.css">
    <style>
        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--background-color, #f8fafc);
            color: var(--text-color, #1f2937);
            line-height: 1.5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .container {
            width: 100%;
            padding: 0;
            margin: 0;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .tab-container {
            background: white;
            padding: 0;
            margin: 0;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        /* Inner container that will have consistent padding with main site */
        .content-wrapper {
            max-width: 100%;
            width: 100%;
            margin: 0 auto;
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        /* Header styling to match main site */
        .header {
            width: 100%;
            min-height: 58px;
            background-color: white;
            border-bottom: 1px solid var(--border-color, #e2e8f0);
        }
        
        .header .container_header {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            margin-left: 0px;
            padding-top: 5px;
            padding-bottom: 5px;
        }
        
        .nav-right {
            display: flex;
            font-size: 12px;
            line-height: 14px;
            color: #2c2e61;
        }
        
        .nav-right li {
            padding-top: 5px;
            padding-right: 15px;
            list-style-type: none;
        }
        
        .nav-right li:not(:first-child) {
            padding-left: 15px;
            padding-bottom: 5px;
        }
        
        .nav-right li:not(:last-child) {
            border-right: 1px solid #e5e5e5;
        }
        
        .nav-right li:hover {
            font-weight: 500;
            font-size-adjust: 0.6;
        }
        
        /* Tab styling with proper padding */
        .tabs-wrapper {
            width: 100%;
            background-color: white;
            border-bottom: 1px solid var(--border-color, #e2e8f0);
        }
        
        .tabs-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 20px 0 20px;
        }
        
        .tabs {
            display: flex;
            gap: 2px;
            margin-bottom: 0;
            flex-wrap: wrap;
            border-bottom: none;
        }
        
        .tab {
            padding: 12px 24px;
            background: #f8f9fa; /* Very light grey for inactive */
            border: none;
            cursor: pointer;
            border-top-left-radius: 4px;
            border-top-right-radius: 4px;
            font-weight: 500;
            transition: background-color 0.2s, color 0.2s, border-color 0.2s;
            box-shadow: 0 -2px 3px rgba(0,0,0,0.03);
            color: #6c757d; /* Standard grey text for inactive */
            border-bottom: 3px solid transparent; /* Placeholder for smooth transition */
        }
        
        .tab:hover {
            background: #e9ecef; /* Slightly darker grey on hover */
            color: #495057;
        }
        
        .tab.active {
            background: #ffffff; /* White background for active */
            color: #212529; /* Black text for active */
            font-weight: 600; /* Bolder text for active */
            border-bottom: 3px solid #495057; /* Dark grey bottom border */
            position: relative;
            z-index: 2;
            box-shadow: 0 -2px 5px rgba(0,0,0,0.08);
        }
        
        /* Layout Components */
        .tab-content {
            display: none;
            position: relative;
            border-radius: 4px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Overall layout structure */
        .tab-content-layout {
            display: flex;
            width: 100%;
            gap: 20px;
            position: relative;
        }
        
        .table-main-container {
            flex: 1;
            min-width: 0;
            transition: width 0.3s ease;
            width: 100%;
            max-width: 1200px;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        
        /* Search container */
        .search-container {
            position: sticky;
            top: 0;
            background: white;
            padding: 15px 0 10px 0;
            margin: 0;
            z-index: 20;
            border-bottom: 1px solid #eee;
            display: flex;
            flex-direction: column;
        }
        
        .search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .search-box {
            width: 100%;
            padding: 12px 70px 12px 16px; /* Increased right padding for buttons */
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            transition: border-color 0.2s;
            background: white;
            z-index: 1;
        }
        
        .search-box:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
        /* Clear search button */
        .clear-search-button {
            position: absolute;
            right: 70px; /* Position left of search icon */
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 18px;
            color: #999;
            cursor: pointer;
            padding: 0 5px;
            display: none; /* Hidden by default */
            z-index: 2;
        }
        
        .clear-search-button:hover {
            color: #333;
        }
        
        /* Reset filters button */
        .reset-filters-button {
            margin-left: 10px;
            padding: 6px 12px;
            font-size: 12px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
            display: none; /* Hidden by default */
        }
        
        .reset-filters-button:hover {
            background-color: #5a6268;
        }
        
        .search-icon {
            position: absolute;
            right: 45px; /* Positioned left of the refresh button */
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            pointer-events: none;
            z-index: 1;
        }
        
        .search-stats {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Container for stats and reset button */
        .search-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 5px;
        }
        
        .refresh-button {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #666;
            padding: 8px;
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            right: 10px; /* Positioned to the far right */
            top: 50%;
            transform: translateY(-50%);
            z-index: 2;
        }
        
        .refresh-status {
            margin-left: 10px;
            font-size: 12px;
            color: #28a745;
            opacity: 0;
            transition: opacity 0.3s ease;
            position: absolute; /* Position refresh status */
            right: 75px; /* Positioned left of the search icon */
            top: 50%;
            transform: translateY(-50%);
        }
        
        .refresh-status.show {
            opacity: 1;
        }
        
        /* Table container hierarchy */
        .table-scroll-container {
            position: relative;
            flex-grow: 1;
            overflow-y: auto;
            overflow-x: hidden;
            border-radius: 4px;
            margin-top: 10px;
            min-height: 150px;
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            position: relative;
        }
        
        .table-wrapper {
            position: relative;
        }
        
        .table-scroll-hint {
            position: absolute;
            right: 10px;
            top: -25px;
            font-size: 12px;
            color: #666;
            display: none;
        }
        
        /* Table styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
            font-size: 14px;
            table-layout: auto;
        }
        
        th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            white-space: nowrap;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            transition: background-color 0.2s;
            cursor: pointer;
        }
        
        th:hover {
            background-color: #e9ecef;
        }
        
        th::after {
            content: '↕';
            font-size: 12px;
            color: #aaa;
            display: inline-block;
            margin-left: 5px;
            transition: transform 0.2s;
        }
        
        th.sort-asc::after {
            content: '↑';
            color: #007bff;
        }
        
        th.sort-desc::after {
            content: '↓';
            color: #007bff;
        }
        
        td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            min-width: 120px;
            vertical-align: top;
            white-space: normal;
            word-wrap: break-word;
        }
        
        .cell-content {
            white-space: pre-wrap;
            word-wrap: break-word;
            word-break: normal;
            overflow-wrap: break-word;
            max-width: 300px;
            min-width: 100px;
        }
        
        tr {
            transition: background-color 0.2s;
        }
        
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        tr:nth-child(odd) {
            background-color: #ffffff;
        }
        
        tr:hover {
            background-color: #e9f0fe;
        }
        
        tr.selected-row {
            background-color: #e9f5ff !important;
            border-left: 3px solid #007bff;
            box-shadow: 0 1px 3px rgba(0,123,255,0.2);
        }
        
        tr.hidden {
            display: none;
        }
        
        /* Sidebar styles */
        .info-sidebar {
            width: 300px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            padding: 15px;
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 120px);
            overflow-y: auto;
            display: none;
            flex-shrink: 0;
            transition: all 0.3s ease;
            margin-left: 20px;
            z-index: 5;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            opacity: 0;
            transform: translateX(20px);
        }
        
        .info-sidebar.visible {
            display: block;
            opacity: 1;
            transform: translateX(0);
        }
        
        .info-sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .info-sidebar-title {
            font-weight: 600;
            font-size: 16px;
        }
        
        .info-sidebar-close {
            background: none;
            border: none;
            font-size: 24px;
            line-height: 24px;
            cursor: pointer;
            color: #6c757d;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }
        
        .info-sidebar-close:hover {
            color: #dc3545;
        }
        
        .info-sidebar-content {
            white-space: pre-wrap;
            word-wrap: break-word;
            word-break: normal;
            overflow-wrap: break-word;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .no-info-message {
            color: #6c757d;
            font-style: italic;
        }
        
        /* Utility classes */
        .highlight {
            background-color: #fff3cd;
            padding: 2px;
            border-radius: 2px;
        }
        
        .error-message {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 12px 16px;
            margin: 10px 0;
            border-radius: 4px;
        }
        
        .no-results {
            padding: 20px;
            text-align: center;
            color: #666;
            font-style: italic;
            background: #f8f9fa;
            border-radius: 4px;
            margin-top: 10px;
            display: none;
        }
        
        .section-header {
            color: #0056b3;
            font-weight: 600;
            padding: 8px 0;
            margin: 0;
            font-size: 14px;
        }
        
        .table-description {
            margin-bottom: 15px;
            color: #555;
            font-size: 15px;
            line-height: 1.4;
            padding-left: 5px; /* Align description with table content */
        }
        
        /* Refresh button styles */
        .refresh-button:hover {
            color: #007bff;
        }
        
        .refresh-button.spinning {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            100% {
                transform: rotate(360deg);
            }
        }
        
        /* Layout center wrapper */
        .layout-center-wrapper {
            width: 100%;
            display: flex;
            justify-content: center;
        }
        
        /* Responsive layouts */
        @media screen and (min-width: 1520px) {
            .content-wrapper {
                max-width: 100%;
                padding: 20px;
            }
            
            .tab-content-layout {
                max-width: 100%;
                flex-wrap: nowrap;
                justify-content: center;
            }
            
            .table-main-container {
                max-width: 1200px;
                width: 1200px;
                flex: 0 0 1200px;
            }
        }
        
        @media screen and (min-width: 1200px) and (max-width: 1519px) {
            .content-wrapper {
                max-width: 1200px;
            }
            
            .tab-content-layout {
                flex-wrap: nowrap;
            }
            
            .table-main-container {
                flex: 1;
            }
            
            .table-main-container.with-sidebar {
                width: calc(100% - 340px);
            }
        }
        
        @media screen and (min-width: 992px) and (max-width: 1199px) {
            .tab-content-layout {
                flex-wrap: nowrap;
            }
            
            .table-main-container.with-sidebar {
                width: calc(100% - 280px);
            }
            
            .info-sidebar {
                width: 260px;
            }
        }
        
        @media screen and (max-width: 991px) {
            .tab-content-layout {
                flex-direction: column;
            }
            
            .table-main-container.with-sidebar {
                width: 100%;
            }
            
            .info-sidebar {
                width: 100%;
                margin-top: 20px;
                margin-left: 0;
                position: static;
                max-height: none;
            }
            
            .container {
                padding: 0;
            }
            
            .content-wrapper {
                padding: 15px;
            }
            
            .tabs-wrapper {
                padding: 0;
            }
            
            .tabs-inner {
                padding: 15px 15px 0 15px;
            }
            
            .tab {
                padding: 8px 16px;
                font-size: 13px;
                flex: 1 1 auto;
                text-align: center;
            }
            
            th, td {
                padding: 8px 12px;
                font-size: 13px;
                min-width: 100px;
            }
            
            .table-scroll-hint {
                display: block;
            }
            
            .search-container {
                padding: 10px 0;
            }
            
            .search-box {
                padding: 10px 35px 10px 12px;
                font-size: 13px;
            }
            
            .header .container_header {
                padding: 0 15px;
                flex-direction: column;
                align-items: center;
            }
            
            .nav-right {
                margin: 10px 0;
                width: 100%;
                justify-content: center;
            }
            
            .logo {
                margin: 10px 0;
            }
            
            .logo img {
                max-width: 150px;
                height: auto;
            }
        }
        
        /* Back to top button */
        .back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            background-color: #007bff;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 100;
        }
        
        .back-to-top.visible {
            opacity: 1;
            visibility: visible;
        }
        
        .back-to-top:hover {
            background-color: #0056b3;
        }
        
        /* Subtle pattern background */
        body {
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23f0f0f0' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            font-family: "Inter", -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--background-color, #f8fafc);
            color: var(--text-color, #1f2937);
            line-height: 1.5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        /* Footer styling */
        .footer {
            background-color: white;
            border-top: 1px solid #e2e8f0;
            padding: 15px 0;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
            margin-top: auto;
        }
        
        /* Loading animation */
        .refresh-animation {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(to right, transparent, #007bff, transparent);
            background-size: 50% 100%;
            animation: loading 1.5s infinite linear;
            display: none;
            z-index: 100;
        }
        
        .refresh-animation.visible {
            display: block;
        }
        
        @keyframes loading {
            0% {
                transform: translateX(-100%);
            }
            100% {
                transform: translateX(200%);
            }
        }
    </style>
</head>
<body>
    <!-- Header matching main site -->
    <header class="header">
        <div class="container_header">
            <a class="logo" href="../">
                <img src="../public/images/SANDBOX.png" alt="Sandbox" />
            </a>
            <ul class="nav-right">
                <li><a href="/index.php">Home</a></li>
                <li><a href="https://marketpawns.com">Marketpawns</a></li>
                <li><a href="https://wiki.marketpawns.com/index.php?title=Main_Page">Wiki</a></li>
            </ul>
        </div>
    </header>

    <div class="container">
        <div class="tab-container">
            <div class="tabs-wrapper">
                <div class="tabs-inner">
                    <div class="tabs">
                        <button class="tab active" onclick="openTab(event, 'parameters')">Parameters</button>
                        <button class="tab" onclick="openTab(event, 'legend')">Legend</button>
                        <button class="tab" onclick="openTab(event, 'potential')">Potential Parameters</button>
                        <button class="tab" onclick="openTab(event, 'next')">Next Parameters</button>
                    </div>
                </div>
            </div>
            
            <div class="layout-center-wrapper">
                <div class="content-wrapper">
                    <!-- Loading animation -->
                    <div class="refresh-animation" id="loading-bar"></div>
                    <?php
                    $csvFiles = [
                        'parameters' => __DIR__ . '/parameters.csv',
                        'legend' => __DIR__ . '/Legend.csv',
                        'potential' => __DIR__ . '/potential_next_parameters.csv',
                        'next' => __DIR__ . '/next_parameters.csv'
                    ];

                    $hasContent = false;

                    foreach ($csvFiles as $id => $file) {
                        list($headers, $data) = readCSV($file);
                        echo "<div id='$id' class='tab-content " . ($id === 'parameters' ? 'active' : '') . "'>";
                        
                        if (empty($headers)) {
                            echo "<div class='error-message'>Unable to load " . basename($file) . ". File may be missing or empty.</div>";
                        } else {
                            $hasContent = true;
                            
                            // Check if there's an Additional information column
                            $hasAdditionalInfo = array_search('Additional information', $headers) !== false;
                            $additionalInfoIndex = array_search('Additional information', $headers);
                            
                            echo "<div class='tab-content-layout'>";
                            
                            // Main table container
                            echo "<div class='table-main-container'>";
                            
                            // Add search box
                            echo "<div class='search-container'>";
                            echo "<div class='search-wrapper'>";
                            echo "<input type='text' class='search-box' placeholder='Search in this table...' onkeyup='searchTable(this, \"$id\")'>";
                            echo "<button class='clear-search-button' onclick='clearSearch(this, \"$id\")' title='Clear search'>×</button>"; // Clear button
                            echo "<button class='refresh-button' onclick='refreshTable(\"$id\")' title='Refresh data'>↻</button>"; 
                            echo "<div class='search-icon'>🔍</div>";
                            echo "<div class='refresh-status' id='refresh-status-$id'>Updated!</div>";
                            echo "</div>";
                            echo "<div class='search-controls'>"; // Container for stats and reset button
                            echo "<div class='search-stats' id='search-stats-$id'></div>";
                            echo "<button class='reset-filters-button' onclick='resetFilters(\"$id\")'>Reset Filters</button>"; // Reset button
                            echo "</div>";
                            echo "</div>";
                            
                            // Moved description below search container
                            echo "<div class='table-description'>" . getTableDescription($id) . "</div>";

                            echo "<div class='table-scroll-container'>";
                            echo "<div class='table-wrapper'>";
                            echo "<div class='table-scroll-hint'>Scroll horizontally to see more →</div>";
                            echo "<div class='table-container'>";
                            
                            echo getTableHtml($headers, $data, $id);
                            
                            echo "</div></div>";
                            echo "</div>"; // Close table-scroll-container
                            echo "<div class='no-results'>No matching results found</div>";
                            echo "</div>"; // Close table-main-container
                            
                            // Sidebar for additional information
                            if ($hasAdditionalInfo) {
                                echo "<div id='info-sidebar-$id' class='info-sidebar'>";
                                echo "<div class='info-sidebar-header'>";
                                echo "<div class='info-sidebar-title'>Additional Information</div>";
                                echo "<button class='info-sidebar-close' onclick='closeSidebar(\"$id\")'>×</button>";
                                echo "</div>";
                                echo "<div class='info-sidebar-content'>";
                                echo "<p class='no-info-message'>Select a row to view additional information.</p>";
                                echo "</div>";
                                echo "</div>";
                            }
                            
                            echo "</div>"; // Close tab-content-layout
                        }
                        echo "</div>";
                    }

                    if (!$hasContent) {
                        echo "<div class='error-message'>No data available. Please check if CSV files exist in the correct location.</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Back to Top Button -->
    <div class="back-to-top" id="backToTop" title="Back to Top">↑</div>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>© <?php echo date('Y'); ?> Market Pawns. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function closeSidebar(tableId) {
            const sidebar = document.getElementById(`info-sidebar-${tableId}`);
            if (!sidebar) return;
            
            // Hide the sidebar
            sidebar.classList.remove('visible');
            
            // Update table container
            const tableMainContainer = document.querySelector(`#${tableId} .table-main-container`);
            if (tableMainContainer) {
                tableMainContainer.classList.remove('with-sidebar');
            }
            
            // Clear row selection
            const rows = document.querySelectorAll(`#${tableId} .data-row`);
            rows.forEach(row => row.classList.remove('selected-row'));
        }
        
        function handleRowKeydown(event, row, tableId) {
            // Handle Enter or Space key
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                selectRow(row, tableId);
            }
        }
        
        function openTab(evt, tabName) {
            // Hide all tab content
            const tabcontent = document.getElementsByClassName("tab-content");
            for (let i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
                
                // Hide sidebar when switching tabs
                const tabId = tabcontent[i].id;
                const sidebar = document.getElementById(`info-sidebar-${tabId}`);
                if (sidebar) {
                    sidebar.classList.remove('visible');
                    
                    // Remove class from table container
                    const tableMainContainer = tabcontent[i].querySelector('.table-main-container');
                    if (tableMainContainer) {
                        tableMainContainer.classList.remove('with-sidebar');
                    }
                }
                
                // Clear row selection when changing tabs
                const rows = tabcontent[i].querySelectorAll('.data-row');
                rows.forEach(row => row.classList.remove('selected-row'));
            }
            
            // Remove active class from all tabs
            const tablinks = document.getElementsByClassName("tab");
            for (let i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }
            
            // Show the selected tab content and add active class to the button
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }

        function selectRow(row, tableId) {
            // Remove selection from all rows in the table
            const allRows = document.querySelectorAll(`#${tableId} .data-row`);
            allRows.forEach(r => r.classList.remove('selected-row'));
            
            // Add selection to the chosen row
            row.classList.add('selected-row');
            
            // Get the row ID
            const rowId = row.getAttribute('data-row-id');
            
            // Check if there's a sidebar for this table
            const sidebar = document.getElementById(`info-sidebar-${tableId}`);
            if (!sidebar) return;
            
            // Find data for the selected row
            const additionalDataContainer = document.getElementById(`additional-data-${tableId}`);
            const additionalData = additionalDataContainer.querySelector(`[data-row-id="${rowId}"]`);
            
            // Find the table container
            const tableMainContainer = row.closest('.table-main-container');
            
            // Update sidebar content
            const contentContainer = sidebar.querySelector('.info-sidebar-content');
            
            if (additionalData) {
                contentContainer.innerHTML = additionalData.textContent;
                sidebar.classList.add('visible');
                
                // Only on screens less than 1520px add the with-sidebar class
                if (window.innerWidth < 1520 && tableMainContainer) {
                    tableMainContainer.classList.add('with-sidebar');
                }
            } else {
                contentContainer.innerHTML = '<p class="no-info-message">No additional information available for this row.</p>';
                sidebar.classList.add('visible');
                
                // Only on screens less than 1520px add the with-sidebar class
                if (window.innerWidth < 1520 && tableMainContainer) {
                    tableMainContainer.classList.add('with-sidebar');
                }
            }
            
            // Scroll to the sidebar on mobile devices
            if (window.innerWidth <= 991 && sidebar.classList.contains('visible')) {
                setTimeout(() => {
                    sidebar.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 100);
            }
        }
        
        function searchTable(input, tableId) {
            const searchText = input.value.toLowerCase();
            const tableContent = document.getElementById(tableId);
            const rows = tableContent.getElementsByTagName('tr');
            const sectionRows = tableContent.querySelectorAll('.section-row');
            const noResults = tableContent.querySelector('.no-results');
            const clearButton = input.parentElement.querySelector('.clear-search-button');
            const resetButton = tableContent.closest('.tab-content').querySelector('.reset-filters-button');
            const headers = tableContent.querySelectorAll('th');
            let visibleCount = 0;
            let totalDataRows = 0;
            
            // Check if any sorting is active
            let isSortingActive = false;
            headers.forEach(th => {
                if (th.classList.contains('sort-asc') || th.classList.contains('sort-desc')) {
                    isSortingActive = true;
                }
            });

            // Show/hide clear button
            if (clearButton) {
                clearButton.style.display = searchText ? 'block' : 'none';
            }
            
            // Show/hide reset button (if search OR sorting is active)
            if (resetButton) {
                resetButton.style.display = (searchText || isSortingActive) ? 'inline-block' : 'none';
            }
            
            // Remove existing highlights
            const highlighted = tableContent.getElementsByClassName('highlight');
            while(highlighted.length) {
                const element = highlighted[0];
                element.outerHTML = element.innerHTML;
            }
            
            // When searching, clear row selection and hide sidebar
            const sidebar = document.getElementById(`info-sidebar-${tableId}`);
            if (sidebar) {
                sidebar.classList.remove('visible');
                
                // Find the table container and remove class
                const tableMainContainer = sidebar.closest('.tab-content').querySelector('.table-main-container');
                if (tableMainContainer) {
                    tableMainContainer.classList.remove('with-sidebar');
                }
            }
            
            // Handle section row visibility
            sectionRows.forEach(row => {
                row.style.display = searchText ? 'none' : ''; // Hide if searching, show if not
            });
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                
                // Skip header row (already handled section rows)
                if (row.classList.contains('header-row') || row.classList.contains('section-row')) {
                    continue;
                }
                
                // Clear row selection
                row.classList.remove('selected-row');
                
                if (row.classList.contains('data-row')) {
                    totalDataRows++;
                    const cells = row.getElementsByTagName('td');
                    let found = false;
                    
                    if (searchText === '') {
                        row.style.display = '';
                        visibleCount++;
                        continue;
                    }
                    
                    for (let j = 0; j < cells.length; j++) {
                        const cell = cells[j];
                        const text = cell.textContent || cell.innerText;
                        
                        if (text.toLowerCase().indexOf(searchText) > -1) {
                            found = true;
                            // Highlight matching text
                            cell.innerHTML = cell.textContent.replace(
                                new RegExp(searchText.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&'), 'gi'), // Escape regex special chars
                                match => `<span class="highlight">${match}</span>`
                            );
                        }
                    }
                    
                    if (found) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                }
            }
            
            // Update search stats using the refactored function
            updateSearchStats(tableId);
            
            // Show/hide no results message
            if (visibleCount === 0 && searchText !== '') {
                noResults.style.display = 'block';
            } else {
                noResults.style.display = 'none';
            }
        }

        // Function to clear search input
        function clearSearch(button, tableId) {
            const input = button.parentElement.querySelector('.search-box');
            input.value = '';
            searchTable(input, tableId);
            input.focus(); // Keep focus on the search box
        }

        // Function to reset filters (clears search and column sorting)
        function resetFilters(tableId) {
            const tableContent = document.getElementById(tableId);
            const searchInput = tableContent.querySelector('.search-box');
            const table = tableContent.querySelector('.data-table');
            const headers = tableContent.querySelectorAll('th');
            const resetButton = tableContent.querySelector('.reset-filters-button');

            let filtersWereActive = false;

            // 1. Clear search if active
            if (searchInput.value) {
                searchInput.value = '';
                searchTable(searchInput, tableId); // Calls updateSearchStats, hides button if needed
                filtersWereActive = true;
            }

            // 2. Reset column sorting if active
            let sortWasActive = false;
            headers.forEach(th => {
                if (th.classList.contains('sort-asc') || th.classList.contains('sort-desc')) {
                    sortWasActive = true;
                    th.classList.remove('sort-asc', 'sort-desc');
                }
            });

            if (sortWasActive) {
                filtersWereActive = true;
                // Restore original row order using data-original-index
                const tbody = table.querySelector('tbody') || table;
                // Select ALL rows within tbody that have the attribute, including section headers
                const allRows = Array.from(tbody.querySelectorAll('tr[data-original-index]'));
                
                const originalRows = allRows.sort((a, b) => {
                    const indexA = parseInt(a.getAttribute('data-original-index'));
                    const indexB = parseInt(b.getAttribute('data-original-index'));
                    return indexA - indexB;
                });
                
                // Reappend ALL rows in original order
                originalRows.forEach(row => tbody.appendChild(row));
            }

            // Hide the reset button ONLY if no filters are active anymore
            if (!filtersWereActive && !searchInput.value) {
                let isAnySortActive = false;
                headers.forEach(th => {
                    if (th.classList.contains('sort-asc') || th.classList.contains('sort-desc')) {
                         isAnySortActive = true;
                    }
                });
                if (!isAnySortActive && resetButton) {
                     resetButton.style.display = 'none';
                }
            }
            
            // Also ensure section rows are visible if they were hidden by search/sort
            const sectionRows = tableContent.querySelectorAll('.section-row');
             sectionRows.forEach(row => {
                row.style.display = ''; 
            });

            // Final stats update after potential changes
            updateSearchStats(tableId);
        }

        // Show/hide scroll hint based on table width
        document.addEventListener('DOMContentLoaded', function() {
            const tableContainers = document.querySelectorAll('.table-container');
            
            // Show/hide horizontal scroll hint
            tableContainers.forEach(container => {
                const hint = container.parentElement.querySelector('.table-scroll-hint');
                if (container.scrollWidth > container.clientWidth) {
                    hint.style.display = 'block';
                }
            });
            
            // Initialize tabs if multiple present
            if(document.querySelector('.tab')) {
                document.querySelector('.tab.active').click();
            }
        });

        function refreshTable(tableId) {
            const button = document.querySelector(`#${tableId} .refresh-button`);
            const status = document.querySelector(`#refresh-status-${tableId}`);
            const tableContainer = document.querySelector(`#${tableId} .table-container`);
            const statsElement = document.getElementById(`search-stats-${tableId}`);
            const loadingBar = document.getElementById('loading-bar');
            
            // Add spinning animation and loading bar
            button.classList.add('spinning');
            loadingBar.classList.add('visible');
            
            // Clear any existing search stats
            if (statsElement) {
                statsElement.textContent = '';
            }
            
            // Hide sidebar when refreshing
            const sidebar = document.getElementById(`info-sidebar-${tableId}`);
            if (sidebar) {
                sidebar.classList.remove('visible');
                
                // Find and update the table container
                const tableMainContainer = document.querySelector(`#${tableId} .table-main-container`);
                if (tableMainContainer) {
                    tableMainContainer.classList.remove('with-sidebar');
                }
            }
            
            // Cache-busting timestamp
            const timestamp = new Date().getTime();
            
            // Make AJAX request
            fetch(`tables.php?_=${timestamp}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Cache-Control': 'no-cache, no-store, must-revalidate'
                },
                body: `action=refresh&fileId=${tableId}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Network response was not ok: ${response.status}`);
                }
                return response.text();
            })
            .then(html => {
                // Check if response contains error message
                if (html.includes('error-message')) {
                    // Still update the container to show the error
                    tableContainer.innerHTML = html;
                    
                    // Show error message
                    status.textContent = "Error!";
                    status.style.color = "#dc3545";
                    status.classList.add('show');
                    setTimeout(() => {
                        status.classList.remove('show');
                        status.style.color = "#28a745";
                        status.textContent = "Updated!";
                    }, 2000);
                } else {
                    // Update table content
                    tableContainer.innerHTML = html;
                    
                    // Reattach header click handlers
                    const headerCells = tableContainer.querySelectorAll('th');
                    headerCells.forEach(th => {
                        th.addEventListener('click', function() {
                            sortTable(th, tableId);
                        });
                    });
                    
                    // Show success message
                    status.classList.add('show');
                    setTimeout(() => status.classList.remove('show'), 2000);
                    
                    // Reset search if any
                    const searchBox = document.querySelector(`#${tableId} .search-box`);
                    if (searchBox && searchBox.value) {
                        searchTable(searchBox, tableId);
                    }
                }
            })
            .catch(error => {
                console.error('Error refreshing table:', error);
                status.textContent = "Failed!";
                status.style.color = "#dc3545";
                status.classList.add('show');
                setTimeout(() => {
                    status.classList.remove('show');
                    status.style.color = "#28a745";
                    status.textContent = "Updated!";
                }, 2000);
            })
            .finally(() => {
                // Remove spinning animation and loading bar
                button.classList.remove('spinning');
                setTimeout(() => {
                    loadingBar.classList.remove('visible');
                }, 300);
            });
        }

        // Update dimensions when window is resized
        window.addEventListener('resize', function() {
            const visibleSidebars = document.querySelectorAll('.info-sidebar.visible');
            
            visibleSidebars.forEach(sidebar => {
                const tabId = sidebar.id.replace('info-sidebar-', '');
                const tableMainContainer = document.querySelector(`#${tabId} .table-main-container`);
                
                if (tableMainContainer) {
                    if (window.innerWidth < 1520) {
                        tableMainContainer.classList.add('with-sidebar');
                    } else {
                        tableMainContainer.classList.remove('with-sidebar');
                    }
                }
            });
            
            // Check table containers for horizontal scroll
            const tableContainers = document.querySelectorAll('.table-container');
            tableContainers.forEach(container => {
                const hint = container.parentElement.querySelector('.table-scroll-hint');
                if (container.scrollWidth > container.clientWidth) {
                    hint.style.display = 'block';
                } else {
                    hint.style.display = 'none';
                }
            });
        });

        // Back to Top button
        const backToTopButton = document.getElementById('backToTop');
        
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopButton.classList.add('visible');
            } else {
                backToTopButton.classList.remove('visible');
            }
        });
        
        backToTopButton.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // Column sorting functionality
        function sortTable(th, tableId) {
            const table = th.closest('table');
            const tableContent = th.closest('.tab-content');
            const tbody = table.querySelector('tbody') || table;
            const rows = Array.from(tbody.querySelectorAll('tr.data-row'));
            const sectionRows = tableContent.querySelectorAll('.section-row');
            const headerRow = table.querySelector('tr.header-row');
            const headers = Array.from(headerRow.querySelectorAll('th'));
            const columnIndex = headers.indexOf(th);
            const resetButton = tableContent.querySelector('.reset-filters-button'); // Find reset button
            const searchInput = tableContent.querySelector('.search-box');
            
            // Toggle sort direction
            let sortDirection = 1;
            let sortApplied = false;
            if (th.classList.contains('sort-asc')) {
                th.classList.remove('sort-asc');
                th.classList.add('sort-desc');
                sortDirection = -1;
                sortApplied = true;
            } else if (th.classList.contains('sort-desc')) {
                th.classList.remove('sort-desc');
                sortDirection = 0;
                // Sort is removed, button visibility handled below
            } else {
                // Remove sort classes from all headers
                headers.forEach(header => {
                    header.classList.remove('sort-asc', 'sort-desc');
                });
                th.classList.add('sort-asc');
                sortApplied = true;
            }

            // Hide section rows if sorting is applied
            if (sortApplied) {
                 sectionRows.forEach(row => row.style.display = 'none');
            }
            
            // If direction is 0, restore original order
            if (sortDirection === 0) {
                const originalRows = Array.from(rows).sort((a, b) => {
                    return parseInt(a.getAttribute('data-row-id')) - parseInt(b.getAttribute('data-row-id'));
                });
                
                // Reappend rows in original order
                originalRows.forEach(row => tbody.appendChild(row));

                // Show section rows ONLY if search is also inactive
                if (!searchInput.value) {
                    sectionRows.forEach(row => row.style.display = ''); 
                }

                // Check if reset button should still be visible (if search is active)
                 if (resetButton && !searchInput.value) {
                     resetButton.style.display = 'none';
                 }
                 updateSearchStats(tableId); // Update stats text
                return;
            }
            
            // Sort the rows
            const sortedRows = Array.from(rows).sort((a, b) => {
                const cellA = a.querySelectorAll('td')[columnIndex]?.textContent.trim() || '';
                const cellB = b.querySelectorAll('td')[columnIndex]?.textContent.trim() || '';
                
                // Try to convert to numbers if possible
                const numA = parseFloat(cellA);
                const numB = parseFloat(cellB);
                
                if (!isNaN(numA) && !isNaN(numB)) {
                    return sortDirection * (numA - numB);
                }
                
                return sortDirection * cellA.localeCompare(cellB);
            });
            
            // Reappend in sorted order
            sortedRows.forEach(row => tbody.appendChild(row));

            // Show the reset button if sort was applied
            if (sortApplied && resetButton) {
                resetButton.style.display = 'inline-block';
            }
            updateSearchStats(tableId); // Update stats text after sorting
        }

        // Refactored function to update search stats text
        function updateSearchStats(tableId) {
            const tableContent = document.getElementById(tableId);
            if (!tableContent) return; // Exit if table content doesn't exist
            const searchInput = tableContent.querySelector('.search-box');
            const statsElement = document.getElementById(`search-stats-${tableId}`);
            if (!statsElement) return; // Exit if stats element doesn't exist
            
            const headers = tableContent.querySelectorAll('th');
            const dataRows = tableContent.querySelectorAll('.data-row:not([style*="display: none"])');
            const totalDataRows = tableContent.querySelectorAll('.data-row').length;
            const visibleCount = dataRows.length;

            let isSortingActive = false;
            headers.forEach(th => {
                if (th.classList.contains('sort-asc') || th.classList.contains('sort-desc')) {
                    isSortingActive = true;
                }
            });

            const searchText = searchInput ? searchInput.value : '';
            let statsText = '';

            if (searchText || isSortingActive) {
                statsText = `Showing ${visibleCount} of ${totalDataRows} entries`;
                // Append (Sections hidden) if either search or sort is active
                if (searchText || isSortingActive) { 
                     statsText += ` (Sections hidden)`;
                }
            } 
            
            if (statsElement) {
                 statsElement.textContent = statsText;
            }
        }
    </script>
</body>
</html> 