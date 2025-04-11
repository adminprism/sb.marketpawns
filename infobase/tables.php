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
    $rows = array_map(function($line) use ($delimiter) {
        return str_getcsv($line, $delimiter);
    }, file($file));

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
        
        $rows = array_map(function($line) use ($delimiter) {
            return str_getcsv($line, $delimiter);
        }, file($file));
        
        echo "Total rows found: " . count($rows) . "\n";
        echo "Header count: " . count($rows[0]) . "\n\n";
        
        echo "Row analysis:\n";
        for ($i = 1; $i < min(count($rows), 20); $i++) {
            $row = $rows[$i];
            echo "Row " . $i . ": " . count($row) . " columns. First value: '" . ($row[0] ?? 'empty') . "'\n";
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
function getTableHtml($headers, $data) {
    $html = '<table>';
    
    // Headers
    $html .= "<tr class='header-row'>";
    foreach ($headers as $header) {
        if ($header !== 'Column8') {
            $html .= "<th>" . htmlspecialchars($header) . "</th>";
        }
    }
    $html .= "</tr>";
    
    // Data
    foreach ($data as $row) {
        $firstCell = reset($row);
        if (strpos($firstCell, '//') === 0) {
            $html .= "<tr class='section-row'><td colspan='" . (count($headers)-1) . "' class='section-header'>" . 
                    htmlspecialchars(trim($firstCell, '/ ')) . "</td></tr>";
            continue;
        }
        
        if (empty(array_filter($row))) {
            continue;
        }
        
        $html .= "<tr class='data-row'>";
        foreach ($row as $key => $cell) {
            if ($headers[$key] !== 'Column8') {
                $html .= "<td>" . htmlspecialchars($cell) . "</td>";
            }
        }
        $html .= "</tr>";
    }
    
    $html .= '</table>';
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
        
        if (!file_exists($file)) {
            echo "<div class='error-message'>Unable to load " . basename($file) . ". File may be missing.</div>";
            exit;
        }
        
        // Force cache invalidation by adding timestamp parameter
        clearstatcache(true, $file);
        
        list($headers, $data) = readCSV($file);
        
        $debug['header_count'] = count($headers);
        $debug['data_count'] = count($data);
        
        if (empty($headers)) {
            echo "<div class='error-message'>Unable to load " . basename($file) . ". File may be empty or has invalid format.</div>";
            
            // Add debug info in comment
            echo "<!-- Debug: " . json_encode($debug) . " -->";
            exit;
        }
        
        echo getTableHtml($headers, $data);
        
        // Add debug info in comment
        echo "<!-- Debug: " . json_encode($debug) . " -->";
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
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .tab-container {
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 20px;
        }
        .tabs {
            display: flex;
            gap: 2px;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            flex-wrap: wrap;
        }
        .tab {
            padding: 12px 24px;
            background: #e0e0e0;
            border: none;
            cursor: pointer;
            border-radius: 5px 5px 0 0;
            border: 1px solid #ddd;
            border-bottom: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .tab:hover {
            background: #d0d0d0;
        }
        .tab.active {
            background: #007bff;
            color: white;
            border-color: #0056b3;
        }
        .table-container {
            overflow-x: auto;
            margin-top: 10px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
            font-size: 14px;
        }
        th, td {
            padding: 12px 16px;
            text-align: left;
            border: 1px solid #dee2e6;
            min-width: 120px;
            max-width: 300px;
            vertical-align: top;
        }
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 1;
            white-space: nowrap;
        }
        td {
            white-space: normal;
            word-wrap: break-word;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        tr:hover {
            background-color: #f2f2f2;
        }
        .tab-content {
            display: none;
            position: relative;
            border-radius: 4px;
        }
        .tab-content.active {
            display: block;
        }
        .error-message {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 12px 16px;
            margin: 10px 0;
            border-radius: 4px;
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
        .table-description {
            margin-bottom: 15px;
            color: #555;
            font-size: 15px;
            line-height: 1.4;
        }
        .section-header {
            color: #0056b3;
            font-weight: 600;
            padding: 8px 0;
            margin: 0;
            font-size: 14px;
        }
        .search-container {
            position: sticky;
            top: 0;
            background: white;
            padding: 15px 0;
            margin: -15px 0 5px 0;
            z-index: 10;
            border-bottom: 1px solid #eee;
        }
        
        .search-box {
            width: 100%;
            padding: 12px 40px 12px 16px;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            transition: border-color 0.2s;
            background: white;
        }
        
        .search-box:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
        .search-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            pointer-events: none;
        }
        
        .no-results {
            padding: 20px;
            text-align: center;
            color: #666;
            font-style: italic;
            background: #f8f9fa;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .highlight {
            background-color: #fff3cd;
            padding: 2px;
            border-radius: 2px;
        }
        
        .search-stats {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        tr.hidden {
            display: none;
        }
        
        .table-scroll-container {
            max-height: calc(70vh - 100px);
            overflow-y: auto;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        @media screen and (max-width: 768px) {
            .container {
                padding: 10px;
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
                margin-bottom: 15px;
            }
            
            .search-box {
                padding: 10px 35px 10px 12px;
                font-size: 13px;
            }
        }
        
        .refresh-button {
            position: absolute;
            right: 60px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #666;
            padding: 5px;
            transition: transform 0.3s ease;
            z-index: 11;
        }
        
        .refresh-button:hover {
            color: #007bff;
        }
        
        .refresh-button.spinning {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            100% {
                transform: translateY(-50%) rotate(360deg);
            }
        }
        
        .refresh-status {
            position: absolute;
            right: 100px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
            color: #28a745;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .refresh-status.show {
            opacity: 1;
        }
        
        .search-wrapper {
            position: relative;
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
                <li><a href="/infobase/tables.php">Infobase</a></li>
                <li><a href="https://marketpawns.com">Marketpawns</a></li>
                <li><a href="https://wiki.marketpawns.com/index.php?title=Main_Page">Wiki</a></li>
            </ul>
        </div>
    </header>

    <div class="container">
        <div class="tab-container">
            <div class="tabs">
                <button class="tab active" onclick="openTab(event, 'parameters')">Parameters</button>
                <button class="tab" onclick="openTab(event, 'legend')">Legend</button>
                <button class="tab" onclick="openTab(event, 'potential')">Potential Parameters</button>
                <button class="tab" onclick="openTab(event, 'next')">Next Parameters</button>
            </div>

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
                    echo "<div class='table-description'>" . getTableDescription($id) . "</div>";
                    
                    // Add search box
                    echo "<div class='search-container'>";
                    echo "<div class='search-wrapper'>";
                    echo "<input type='text' class='search-box' placeholder='Search in this table...' onkeyup='searchTable(this, \"$id\")'>";
                    echo "<div class='search-icon'>🔍</div>";
                    echo "<button class='refresh-button' onclick='refreshTable(\"$id\")' title='Refresh data'>↻</button>";
                    echo "<div class='refresh-status' id='refresh-status-$id'>Updated!</div>";
                    echo "</div>";
                    echo "<div class='search-stats' id='search-stats-$id'></div>";
                    echo "</div>";
                    
                    echo "<div class='table-scroll-container'>";
                    echo "<div class='table-wrapper'>";
                    echo "<div class='table-scroll-hint'>Scroll horizontally to see more →</div>";
                    echo "<div class='table-container'><table>";
                    
                    // Headers
                    echo "<tr class='header-row'>";
                    foreach ($headers as $header) {
                        if ($header !== 'Column8') {
                            echo "<th>" . htmlspecialchars($header) . "</th>";
                        }
                    }
                    echo "</tr>";
                    
                    $currentSection = '';
                    // Data
                    foreach ($data as $row) {
                        $firstCell = reset($row);
                        if (strpos($firstCell, '//') === 0) {
                            echo "<tr class='section-row'><td colspan='" . (count($headers)-1) . "' class='section-header'>" . 
                                  htmlspecialchars(trim($firstCell, '/ ')) . "</td></tr>";
                            continue;
                        }
                        
                        if (empty(array_filter($row))) {
                            continue;
                        }
                        
                        echo "<tr class='data-row'>";
                        foreach ($row as $key => $cell) {
                            if ($headers[$key] !== 'Column8') {
                                echo "<td>" . htmlspecialchars($cell) . "</td>";
                            }
                        }
                        echo "</tr>";
                    }
                    
                    echo "</table></div></div>";
                    echo "</div>"; // Close table-scroll-container
                    echo "<div class='no-results' style='display: none;'>No matching results found</div>";
                }
                echo "</div>";
            }

            if (!$hasContent) {
                echo "<div class='error-message'>No data available. Please check if CSV files exist in the correct location.</div>";
            }
            ?>
        </div>
    </div>

    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            
            // Hide all tab content
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }
            
            // Remove active class from all tabs
            tablinks = document.getElementsByClassName("tab");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }
            
            // Show the selected tab content and add active class to the button
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }

        function searchTable(input, tableId) {
            const searchText = input.value.toLowerCase();
            const tableContent = document.getElementById(tableId);
            const rows = tableContent.getElementsByTagName('tr');
            const noResults = tableContent.querySelector('.no-results');
            let visibleCount = 0;
            let totalDataRows = 0;
            
            // Remove existing highlights
            const highlighted = tableContent.getElementsByClassName('highlight');
            while(highlighted.length) {
                const element = highlighted[0];
                element.outerHTML = element.innerHTML;
            }
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                
                // Skip header row and section headers
                if (row.classList.contains('header-row') || row.classList.contains('section-row')) {
                    continue;
                }
                
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
                                new RegExp(searchText, 'gi'),
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
            
            // Update search stats
            const statsElement = document.getElementById(`search-stats-${tableId}`);
            if (searchText) {
                statsElement.textContent = `Showing ${visibleCount} of ${totalDataRows} entries`;
            } else {
                statsElement.textContent = '';
            }
            
            // Show/hide no results message
            if (visibleCount === 0 && searchText !== '') {
                noResults.style.display = 'block';
            } else {
                noResults.style.display = 'none';
            }
        }

        // Show/hide scroll hint based on table width
        document.addEventListener('DOMContentLoaded', function() {
            const tableContainers = document.querySelectorAll('.table-container');
            tableContainers.forEach(container => {
                const hint = container.parentElement.querySelector('.table-scroll-hint');
                if (container.scrollWidth > container.clientWidth) {
                    hint.style.display = 'block';
                }
            });
        });

        function refreshTable(tableId) {
            const button = document.querySelector(`#${tableId} .refresh-button`);
            const status = document.querySelector(`#refresh-status-${tableId}`);
            const tableContainer = document.querySelector(`#${tableId} .table-container`);
            const statsElement = document.getElementById(`search-stats-${tableId}`);
            
            // Add spinning animation
            button.classList.add('spinning');
            
            // Clear any existing search stats
            if (statsElement) {
                statsElement.textContent = '';
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
                    
                    // Show success message
                    status.classList.add('show');
                    setTimeout(() => status.classList.remove('show'), 2000);
                    
                    // Get row count for the stats
                    const rowCount = tableContainer.querySelectorAll('tr.data-row').length;
                    
                    console.log(`Refreshed table ${tableId} - found ${rowCount} rows`);
                    
                    // Reset search if any
                    const searchBox = document.querySelector(`#${tableId} .search-box`);
                    if (searchBox.value) {
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
                
                alert('Error refreshing data: ' + error.message);
            })
            .finally(() => {
                // Remove spinning animation
                button.classList.remove('spinning');
            });
        }
    </script>
</body>
</html> 