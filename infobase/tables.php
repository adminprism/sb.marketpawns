﻿<?php
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

    $fileName = basename($file);
    
    // Special handling for Legend.csv to prevent comma issues in definitions
    if ($fileName === 'Legend.csv') {
        // Use a custom approach for Legend.csv
        $content = file_get_contents($file);
        $lines = explode("\n", $content);
        
        if (empty($lines)) {
            return array(array(), array());
        }
        
        // Process header (first line)
        $headers = array_map('trim', explode(',', $lines[0], 2));
        
        // Process data rows
        $csv = [];
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;
            
            // Split only at the first comma to separate Term from Definition
            $parts = explode(',', $line, 2);
            if (count($parts) < 2) {
                $parts[] = ''; // Add empty definition if missing
            }
            
            $term = trim($parts[0]);
            $definition = trim($parts[1]);
            
            if (!empty($term)) {
                $csv[] = array_combine($headers, [$term, $definition]);
            }
        }
        
        return array($headers, $csv);
    }
    
    // Regular handling for other CSV files
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
        'parameters' => __DIR__ . '/content/parameters.csv',
        'legend' => __DIR__ . '/content/Legend.csv',
        'potential' => __DIR__ . '/content/potential_next_parameters.csv',
        'next' => __DIR__ . '/content/next_parameters.csv'
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
    
    $tableClass = "data-table";
    // Add specific ID for Legend table
    if ($fileId === 'legend') {
        $tableClass .= " legend-table";
    }
    
    $html = '<table id="' . $fileId . '-table" tabindex="0" class="' . $tableClass . '" aria-label="Data table for ' . htmlspecialchars($fileId) . '">';
    
    // Headers - now without Additional information
    $html .= "<tr class='header-row'>";
    foreach ($headers as $headerIndex => $header) {
        // Skip Column8 if present
        if ($header !== 'Column8') {
            $html .= "<th tabindex='0' role='button' aria-sort='none' onClick='sortTable(this, \"$fileId\")'>" . htmlspecialchars($header) . "</th>";
        }
    }
    $html .= "</tr>";
    
    // Extract sections for navigation
    $sections = [];
    $sectionIndex = 0;
    
    // Data rows - Add original index tracking
    $originalIndex = 0;
    foreach ($data as $rowIndex => $row) {
        $firstCell = reset($row);
        if (strpos($firstCell, '//') === 0) {
            $sectionName = trim($firstCell, '/ ');
            $sectionId = 'section-' . $fileId . '-' . $sectionIndex;
            $sections[] = [
                'name' => $sectionName,
                'id' => $sectionId
            ];
            $sectionIndex++;
            
            $html .= "<tr class='section-row' id='$sectionId' data-original-index='{$originalIndex}'><td colspan='" . count($headers) . "' class='section-header'>" . 
                     htmlspecialchars($sectionName) . "</td></tr>";
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
    
    // Store the sections data for JS
    if (!empty($sections)) {
        $html .= "<script>
            if (typeof tableSections === 'undefined') {
                var tableSections = {};
            }
            tableSections['$fileId'] = " . json_encode($sections) . ";
        </script>";
    }
    
    // If Additional information exists, add the hidden container
    if ($hasAdditionalInfo) {
        // For direct table generation
        $html .= "<div id='additional-data-$fileId' style='display:none;'>";
        foreach ($additionalInfoData as $rowIndex => $infoText) {
            $html .= "<div data-row-id='$rowIndex'>" . htmlspecialchars($infoText) . "</div>";
        }
        $html .= "</div>";
    }
    
    return $html;
}

// Add endpoint for AJAX refresh
if (isset($_POST['action']) && $_POST['action'] === 'refresh') {
    header('Content-Type: text/html; charset=utf-8');
    $fileId = $_POST['fileId'] ?? '';
    $csvFiles = [
        'parameters' => __DIR__ . '/content/parameters.csv',
        'legend' => __DIR__ . '/content/Legend.csv',
        'potential' => __DIR__ . '/content/potential_next_parameters.csv',
        'next' => __DIR__ . '/content/next_parameters.csv'
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
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand-dark-blue: #1A237E;
            --brand-dark-blue-hover: #283593;
            --brand-gold: #C4A66A;
            --brand-gold-highlight: #FFF8E1;
            --brand-blue-light-bg: #E8EAF6;
            --text-primary: #1A237E;
            --text-secondary: #4A5568;
            --text-light: #64748b;
            --border-color: #E2E8F0;
            --border-light: #edf2f7;
            --background-main: #f8fafc;
            --background-alt: #f0f5fa;
            --background-content: #ffffff;
        }
        
        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--background-main);
            color: var(--text-secondary);
            line-height: 1.5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23e6ecf5' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
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
            background: var(--background-content);
            padding: 0;
            margin: 0;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .content-wrapper {
            max-width: 100%;
            width: 100%;
            margin: 0 auto;
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .header {
            width: 100%;
            min-height: 90px;
            background-color: var(--background-content);
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .header .container_header {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 90px;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo img {
            height: 75px;
            width: auto;
        }
        
        .nav-right {
            display: flex;
            font-size: 14px;
            line-height: 16px;
            color: var(--text-primary);
            list-style: none;
            padding: 0;
            margin: 0;
            height: 100%;
        }
        
        .nav-right li {
            display: flex;
            align-items: center;
            height: 100%;
            position: relative;
        }
        
        .nav-right li a {
            text-decoration: none;
            color: var(--text-primary);
            padding: 0 20px;
            display: flex;
            align-items: center;
            height: 100%;
            position: relative;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .nav-right li a i {
            margin-right: 8px;
            font-size: 16px;
        }
        
        .nav-right li a:hover {
            color: var(--brand-dark-blue-hover);
            background-color: rgba(232, 234, 246, 0.4);
        }
        
        .nav-right li a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: var(--brand-dark-blue);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .nav-right li a:hover::after {
            transform: scaleX(1);
        }
        
        .nav-right li.active a {
            color: var(--brand-dark-blue);
            font-weight: 600;
        }
        
        .nav-right li.active a::after {
            transform: scaleX(1);
        }
        
        .tabs-wrapper {
            width: 100%;
            background-color: var(--background-main);
            border-bottom: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            position: relative;
            z-index: 10;
        }
        
        .tabs-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 15px 20px 0 20px;
            position: relative;
        }
        
        .tabs {
            display: flex;
            gap: 0;
            margin-bottom: 0;
            flex-wrap: wrap;
            border-bottom: none;
            position: relative;
        }
        
        .tabs::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1px;
            background-color: var(--border-color);
            z-index: 1;
        }
        
        .tab {
            padding: 14px 25px;
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
            font-size: 14px;
            font-weight: 500;
            letter-spacing: 0.01em;
            color: var(--text-light);
            transition: all 0.25s ease;
            border-bottom: 2px solid transparent;
            margin-right: 5px;
            z-index: 2;
        }
        
        .tab:hover {
            background: transparent;
            color: var(--brand-dark-blue-hover);
        }
        
        .tab.active {
            background: transparent;
            color: var(--brand-dark-blue);
            font-weight: 600;
            border-bottom: 2px solid var(--brand-dark-blue);
            box-shadow: none;
        }
        
        .tab::before {
            content: "";
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--brand-dark-blue);
            transform: scaleX(0);
            transition: transform 0.3s ease;
            z-index: 2;
        }
        
        .tab:hover::before {
            transform: scaleX(0.5);
        }
        
        .tab.active::before {
            transform: scaleX(1);
        }
        
        .tab-content {
            display: none;
            position: relative;
            border-radius: 4px;
        }
        
        .tab-content.active {
            display: block;
        }
        
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
        
        .search-container {
            position: sticky;
            top: 0;
            background: var(--background-content);
            padding: 18px 0 15px 0;
            margin: 0;
            z-index: 20;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            flex-direction: column;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        
        .search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .search-box {
            width: 100%;
            padding: 12px 70px 12px 18px;
            font-size: 14px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            box-sizing: border-box;
            transition: all 0.25s;
            background: var(--background-content);
            z-index: 1;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        
        .search-box:focus {
            outline: none;
            border-color: var(--brand-dark-blue);
            box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.15);
        }
        
        .clear-search-button {
            position: absolute;
            right: 70px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 18px;
            color: #999;
            cursor: pointer;
            padding: 0 5px;
            display: none;
            z-index: 2;
        }
        
        .clear-search-button:hover {
            color: #333;
        }
        
        .reset-filters-button {
            margin-left: 10px;
            padding: 7px 14px;
            font-size: 12px;
            background-color: var(--brand-dark-blue);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.25s;
            display: none;
            box-shadow: 0 1px 3px rgba(26, 35, 126, 0.25);
        }
        
        .reset-filters-button:hover {
            background-color: var(--brand-dark-blue-hover);
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(26, 35, 126, 0.3);
        }
        
        .search-icon {
            position: absolute;
            right: 45px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            pointer-events: none;
            z-index: 1;
        }
        
        .search-stats {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 5px;
        }
        
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
            color: var(--text-light);
            padding: 8px;
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            right: 10px;
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
            position: absolute;
            right: 75px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .refresh-status.show {
            opacity: 1;
        }
        
        .table-scroll-container {
            position: relative;
            flex-grow: 1;
            overflow-y: auto;
            overflow-x: hidden;
            border-radius: 8px;
            margin-top: 12px;
            min-height: 150px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        
        .table-container {
            overflow-x: hidden;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            position: relative;
            background-color: var(--background-content);
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        }
        
        .table-wrapper {
            position: relative;
        }
        
        .table-scroll-hint {
            position: absolute;
            right: 10px;
            top: -25px;
            font-size: 12px;
            color: var(--text-light);
            display: none;
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 0;
            font-size: 14px;
            table-layout: fixed;
        }
        
        th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--background-main);
            padding: 14px 16px;
            text-align: left;
            border-bottom: 2px solid var(--border-color);
            font-weight: 600;
            white-space: normal;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: background-color 0.2s;
            cursor: pointer;
            word-wrap: break-word;
            overflow-wrap: break-word;
            color: var(--text-primary);
            font-size: 13.5px;
            letter-spacing: 0.02em;
        }
        
        th:hover {
            background-color: var(--brand-blue-light-bg);
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
            color: var(--brand-dark-blue);
        }
        
        th.sort-desc::after {
            content: '↓';
            color: var(--brand-dark-blue);
        }
        
        td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
            vertical-align: top;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            color: var(--text-secondary);
            line-height: 1.5;
            font-size: 13.5px;
        }
        
        .cell-content {
            white-space: pre-wrap;
            word-wrap: break-word;
            word-break: normal;
            overflow-wrap: break-word;
            max-width: none;
            min-width: 0;
        }
        
        tr {
            transition: background-color 0.2s;
        }
        
        tr:nth-child(even) {
            background-color: var(--background-alt);
        }
        
        tr:nth-child(odd) {
            background-color: var(--background-content);
        }
        
        tr:hover {
            background-color: var(--brand-blue-light-bg);
        }
        
        tr.selected-row {
            background-color: var(--brand-blue-light-bg) !important;
            border-left: 3px solid var(--brand-dark-blue);
            box-shadow: 0 1px 3px rgba(26, 35, 126, 0.15);
        }
        
        tr.hidden {
            display: none;
        }
        
        .section-header {
            color: var(--text-primary);
            font-weight: 600;
            padding: 14px 18px;
            margin: 0;
            font-size: 15px;
            background-color: var(--brand-blue-light-bg);
            border-left: 4px solid var(--brand-dark-blue);
            letter-spacing: 0.03em;
            text-transform: uppercase;
            box-shadow: 0 1px 3px rgba(0,0,0,0.07);
            border-radius: 0 4px 4px 0;
        }
        
        .section-row {
            transition: all 0.3s ease;
            margin-top: 15px;
        }
        
        .section-row:first-child {
            margin-top: 0;
        }
        
        .section-row:hover .section-header {
            background-color: #DDE1F0; /* Slightly darker shade of light blue */
        }
        
        .table-description {
            margin-bottom: 15px;
            color: var(--text-secondary);
            font-size: 15px;
            line-height: 1.4;
            padding-left: 5px;
        }
        
        .sections-nav {
            width: 240px;
            background-color: var(--background-content);
            border-radius: 10px;
            border: none;
            padding: 18px;
            margin-right: 25px;
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 120px);
            overflow-y: auto;
            flex-shrink: 0;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .sections-nav-title {
            font-weight: 700;
            font-size: 15px;
            padding-bottom: 15px;
            margin-bottom: 15px;
            border-bottom: 2px solid var(--border-light);
            color: var(--text-primary);
            letter-spacing: 0.03em;
            display: flex;
            align-items: center;
        }
        
        .sections-nav-title::before {
            content: "📑";
            margin-right: 10px;
            font-size: 18px;
        }
        
        .sections-nav-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        
        .sections-nav-item {
            padding: 12px 15px;
            cursor: pointer;
            transition: all 0.25s ease;
            font-size: 13.5px;
            border-radius: 6px;
            margin-bottom: 6px;
            border-left: 2px solid transparent;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
        }
        
        .sections-nav-item:hover {
            color: var(--brand-dark-blue-hover);
            background-color: var(--background-alt);
            border-left-color: var(--brand-dark-blue-hover);
            transform: translateX(2px);
        }
        
        .sections-nav-item.active {
            color: var(--brand-dark-blue);
            font-weight: 600;
            background-color: var(--brand-blue-light-bg);
            border-left-color: var(--brand-dark-blue);
        }
        
        .sections-nav-item::before {
            content: "›";
            margin-right: 6px;
            font-size: 16px;
            opacity: 0.6;
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        
        .sections-nav-item:hover::before {
            opacity: 1;
            transform: translateX(2px);
        }
        
        .sections-nav-item.active::before {
            content: "»";
            opacity: 1;
        }
        
        .sections-nav.empty {
            display: none;
        }
        
        .with-sections-nav {
            display: flex;
        }
        
        .info-sidebar {
            width: 300px;
            background-color: var(--background-main);
            border-radius: 8px;
            border: 1px solid var(--border-color);
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
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-sidebar-title {
            font-weight: 600;
            font-size: 16px;
            color: var(--text-primary);
        }
        
        .info-sidebar-close {
            background: none;
            border: none;
            font-size: 24px;
            line-height: 24px;
            cursor: pointer;
            color: var(--text-light);
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
            color: var(--text-light);
            font-style: italic;
        }
        
        .highlight {
            background-color: var(--brand-gold-highlight);
            padding: 2px;
            border-radius: 2px;
            color: #5D4037; /* Darker text for gold highlight */
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
            color: var(--text-light);
            font-style: italic;
            background: var(--background-main);
            border-radius: 4px;
            margin-top: 10px;
            display: none;
        }
        
        .refresh-button:hover {
            color: var(--brand-dark-blue-hover);
        }
        
        .refresh-button.spinning {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            100% {
                transform: rotate(360deg);
            }
        }
        
        .layout-center-wrapper {
            width: 100%;
            display: flex;
            justify-content: center;
        }
        
        .back-to-top {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 45px;
            height: 45px;
            background-color: var(--brand-dark-blue);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            box-shadow: 0 3px 10px rgba(26, 35, 126, 0.3);
            z-index: 100;
        }
        
        .back-to-top.visible {
            opacity: 1;
            visibility: visible;
        }
        
        .back-to-top:hover {
            background-color: var(--brand-dark-blue-hover);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(26, 35, 126, 0.4);
        }
        
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
            .header .container_header {
                padding: 0 15px;
                flex-direction: column;
                height: auto;
                padding-top: 15px;
                padding-bottom: 15px;
            }
            
            .logo img {
                height: 60px;
            }
            
            .nav-right {
                margin: 15px 0 5px;
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .nav-right li {
                height: 40px;
            }
            
            .nav-right li a {
                padding: 0 12px;
                font-size: 13px;
            }
            
            .nav-right li a i {
                margin-right: 5px;
                font-size: 14px;
            }
            
            .logo {
                margin: 0;
            }
            
            .sections-nav {
                width: 100%;
                margin-right: 0;
                margin-bottom: 20px;
                position: relative;
                top: 0;
                max-height: 200px;
                border-radius: 6px;
            }
            
            .with-sections-nav {
                flex-direction: column;
            }
            
            .sections-nav-list {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
            }
            
            .sections-nav-item {
                flex: 0 0 auto;
                padding: 8px 12px;
                margin-bottom: 0;
                font-size: 12px;
            }
            
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
        }
        
        .footer {
            background-color: var(--background-content);
            border-top: 1px solid var(--border-color);
            padding: 15px 0;
            text-align: center;
            font-size: 12px;
            color: var(--text-light);
            margin-top: auto;
        }
        
        .refresh-animation {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(to right, transparent, var(--brand-dark-blue), transparent);
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
        
        table.legend-table th:nth-child(1),
        table.legend-table td:nth-child(1) {
            width: 25%;
        }
        
        table.legend-table th:nth-child(2),
        table.legend-table td:nth-child(2) {
            width: 75%;
        }
        
        table.legend-table .cell-content {
            padding-right: 15px;
        }

        .sections-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: var(--brand-dark-blue);
            color: white;
            border-radius: 20px;
            font-size: 11px;
            height: 22px;
            min-width: 22px;
            padding: 0 8px;
            margin-left: auto;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(26, 35, 126, 0.25);
        }

        .section-number {
            font-size: 10px;
            color: var(--text-secondary);
            background-color: var(--background-alt);
            border-radius: 12px;
            padding: 3px 8px;
            margin-left: auto;
            font-weight: 500;
            border: 1px solid var(--border-color);
        }

        .section-name {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sections-nav-item.clicked {
            transform: scale(0.97);
            background-color: var(--brand-blue-light-bg);
        }

        tr.section-row td.section-header::before {
            content: "";
            display: inline-block;
            width: 6px;
            height: 6px;
            background-color: var(--brand-dark-blue);
            border-radius: 50%;
            margin-right: 10px;
            box-shadow: 0 0 0 2px rgba(26, 35, 126, 0.2);
            vertical-align: middle;
        }
        
        /* Fixed header styles */
        .fixed-header {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            width: 100%;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .header-spacer {
            height: 90px;
            width: 100%;
        }
        
        /* For mobile */
        @media screen and (max-width: 768px) {
            .header-spacer {
                height: 140px;
            }
        }
        
        /* Fixed elements styles */
        .tabs-wrapper {
            position: sticky;
            top: 90px;
            z-index: 900;
            background-color: var(--background-main);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .search-container {
            position: sticky;
            top: 140px;
            z-index: 890;
        }
        
        .sections-nav {
            position: sticky;
            top: 190px;
        }
        
        .info-sidebar {
            position: sticky;
            top: 190px;
        }
        
        /* Adjust for mobile */
        @media screen and (max-width: 768px) {
            .tabs-wrapper {
                top: 140px;
            }
            
            .search-container {
                top: 190px;
            }
            
            .sections-nav, .info-sidebar {
                top: 240px;
            }
        }
    </style>
</head>
<body>
    <!-- Header matching main site -->
    <?php include '../includes/header.php'; ?>

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
                        'parameters' => __DIR__ . '/content/parameters.csv',
                        'legend' => __DIR__ . '/content/Legend.csv',
                        'potential' => __DIR__ . '/content/potential_next_parameters.csv',
                        'next' => __DIR__ . '/content/next_parameters.csv'
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
                            
                            // Count sections to determine if we need section navigation
                            $sectionCount = 0;
                            foreach ($data as $row) {
                                $firstCell = reset($row);
                                if (strpos($firstCell, '//') === 0) {
                                    $sectionCount++;
                                }
                            }
                            
                            echo "<div class='tab-content-layout" . ($sectionCount > 0 ? " with-sections-nav" : "") . "'>";
                            
                            // Add sections navigation if we have sections
                            if ($sectionCount > 0) {
                                echo "<div id='sections-nav-$id' class='sections-nav'>";
                                echo "<div class='sections-nav-title'>Jump to Section</div>";
                                echo "<ul class='sections-nav-list' id='sections-list-$id'></ul>";
                                echo "</div>";
                            }
                            
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
    <?php include '../includes/footer.php'; ?>

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

        // Initialize section navigation
        function initSectionsNav() {
            if (typeof tableSections === 'undefined') return;
            
            for (const tableId in tableSections) {
                const sections = tableSections[tableId];
                const navListElement = document.getElementById(`sections-list-${tableId}`);
                
                if (!navListElement || sections.length === 0) continue;
                
                // Clear existing items
                navListElement.innerHTML = '';
                
                // Count of sections for labeling
                const totalSections = sections.length;
                
                // Create list items for each section with improved labeling
                sections.forEach((section, index) => {
                    const li = document.createElement('li');
                    li.className = 'sections-nav-item';
                    
                    // Create span for section name with proper formatting
                    const nameSpan = document.createElement('span');
                    nameSpan.className = 'section-name';
                    nameSpan.textContent = section.name;
                    
                    // Add section number indicator
                    const numberBadge = document.createElement('span');
                    numberBadge.className = 'section-number';
                    numberBadge.textContent = `${index + 1}/${totalSections}`;
                    
                    li.appendChild(nameSpan);
                    li.appendChild(numberBadge);
                    
                    li.dataset.sectionId = section.id;
                    
                    // Add enhanced click behavior
                    li.addEventListener('click', function() {
                        // Visual feedback on click
                        li.classList.add('clicked');
                        setTimeout(() => {
                            li.classList.remove('clicked');
                        }, 300);
                        
                        navigateToSection(tableId, section.id);
                    });
                    
                    navListElement.appendChild(li);
                });
                
                // Add "All Sections" section count indicator
                const sectionTitle = document.querySelector(`#sections-nav-${tableId} .sections-nav-title`);
                if (sectionTitle) {
                    // Remove existing badge if any
                    const existingBadge = sectionTitle.querySelector('.sections-count');
                    if (existingBadge) {
                        existingBadge.remove();
                    }
                    
                    // Add new badge with total count
                    const countBadge = document.createElement('span');
                    countBadge.className = 'sections-count';
                    countBadge.textContent = totalSections;
                    sectionTitle.appendChild(countBadge);
                }
            }
        }
        
        // Navigate to specific section
        function navigateToSection(tableId, sectionId) {
            const sectionElement = document.getElementById(sectionId);
            if (!sectionElement) return;
            
            // Remove active class from all section items in this table
            const items = document.querySelectorAll(`#sections-list-${tableId} .sections-nav-item`);
            items.forEach(item => item.classList.remove('active'));
            
            // Add active class to the clicked item
            const clickedItem = document.querySelector(`#sections-list-${tableId} [data-section-id="${sectionId}"]`);
            if (clickedItem) clickedItem.classList.add('active');
            
            // Calculate position, accounting for sticky headers
            const headerHeight = document.querySelector('.search-container').offsetHeight || 0;
            const sectionRect = sectionElement.getBoundingClientRect();
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const targetPosition = scrollTop + sectionRect.top - headerHeight - 20;
            
            // Add highlight animation to the section
            sectionElement.style.transition = 'background-color 0.5s ease';
            const originalBgColor = getComputedStyle(sectionElement).backgroundColor;
            
            // Flash effect
            sectionElement.style.backgroundColor = '#fffde7';
            
            // Scroll to section with smooth animation
            window.scrollTo({
                top: targetPosition,
                behavior: 'smooth'
            });
            
            // Also scroll the table's scroll container if needed
            const tableContainer = document.querySelector(`#${tableId} .table-scroll-container`);
            if (tableContainer) {
                tableContainer.scrollTo({
                    top: sectionElement.offsetTop - headerHeight - 20,
                    behavior: 'smooth'
                });
            }
            
            // Return to original color after animation
            setTimeout(() => {
                sectionElement.style.backgroundColor = originalBgColor;
                setTimeout(() => {
                    sectionElement.style.transition = '';
                }, 500);
            }, 1000);
        }
        
        // Call this function when tabs are switched or when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            initSectionsNav();
        });
        
        // Update sections nav when tab is changed
        const openTabOriginal = openTab;
        openTab = function(evt, tabName) {
            openTabOriginal(evt, tabName);
            // Initialize section navigation after tab switch
            setTimeout(initSectionsNav, 100);
        };
        
        // Refresh sections navigation when data is refreshed
        const refreshTableOriginal = refreshTable;
        refreshTable = function(tableId) {
            const result = refreshTableOriginal(tableId);
            
            // Re-initialize sections navigation after refresh
            setTimeout(() => {
                initSectionsNav();
            }, 1000);
            
            return result;
        };
    </script>
</body>
</html> 