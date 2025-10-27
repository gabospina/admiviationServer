<?php
// pilot_list.php - FINAL CORRECTED VERSION

require_once 'db_connect.php';

// Check if it's an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Initialize response
$response = [
    'html' => '',
    'count' => 0,
    'error' => false
];

try {
    // Get search and filter parameters
    $search = isset($_GET['search']) ? $mysqli->real_escape_string($_GET['search']) : '';
    $showNonPilots = isset($_GET['showNonPilots']) ? $_GET['showNonPilots'] : 'f';

    // =========================================================================
    // === THE FIX IS HERE: This is the new, correct SQL query               ===
    // =========================================================================
    // This query correctly joins all three tables and uses GROUP BY and GROUP_CONCAT
    // to ensure each user appears only once, with all their roles listed.
    $query = "SELECT 
            u.id, 
            CONCAT(u.firstname, ' ', u.lastname) AS fullname,
            u.firstname,
            u.lastname,
            u.username,
            u.user_nationality,
            GROUP_CONCAT(DISTINCT ur.role_name SEPARATOR ', ') AS roles,  -- ADD DISTINCT HERE
            u.nal_license,
            u.for_license,
            u.email,
            u.phone,
            u.phonetwo,
            u.access_level,
            u.created_at
          FROM users u
          LEFT JOIN user_has_roles uhr ON u.id = uhr.user_id
          LEFT JOIN users_roles ur ON uhr.role_id = ur.id
          WHERE 1=1";

    // Add search conditions
    if (!empty($search)) {
        $query .= " AND (u.firstname LIKE '%$search%' 
                     OR u.lastname LIKE '%$search%')";
    }

    // This access_level filter might need to be adjusted based on your new role system,
    // but we will keep it for now.
    if ($showNonPilots === 'f') {
        $query .= " AND u.access_level = 0"; // Show only pilots
    } elseif ($showNonPilots === 'm') {
        $query .= " AND u.access_level > 0"; // Show only managers
    }
    
    // This is the crucial part that removes duplicates
    $query .= " GROUP BY u.id";

    // =========================================================================
    // === END OF THE FIX                                                    ===
    // =========================================================================

    $result = $mysqli->query($query);
    
    if ($result === false) {
        throw new Exception("Database query failed: " . $mysqli->error);
    }

    // DEBUG: Check what's being returned
    error_log("Pilot List Query: " . $query);
    $debug_users = [];
    while ($row = $result->fetch_assoc()) {
        $debug_users[] = $row;
        error_log("User: " . $row['id'] . " - " . $row['fullname'] . " - Roles: " . $row['roles']);
    }

    // Reset result pointer for the actual processing
    $result->data_seek(0);

    $pilotCount = $result->num_rows;

    // Generate HTML
    $html = '';
    if ($pilotCount > 0) {
        while ($row = $result->fetch_assoc()) {
            $fullname = htmlspecialchars($row['fullname']);
            
            if (!empty($search)) {
                $pattern = '/(' . preg_quote($search, '/') . ')/i';
                $fullname = preg_replace($pattern, '<span class="highlight">$1</span>', $fullname);
            }
            
            // The HTML generation remains the same, but now it will only run ONCE per pilot.
            if ($showNonPilots === 'f') {
                $html .= '<div class="pilot-item" data-pilot-id="'.$row['id'].'">';
                $html .= '  <h4>'.$fullname.'</h4>';
                // You could optionally display the combined roles here if you wish:
                // $html .= '  <small class="text-muted">' . htmlspecialchars($row['roles']) . '</small>';
                $html .= '</div>';
            } else {
                $html .= '<div class="pilot-item" data-pilot-id="'.$row['id'].'">';
                $html .= '  <div class="pilot-header">';
                $html .= '    <h4>'.$fullname.'</h4>';
                $html .= '  </div>';
                $html .= '  <div class="pilot-details">';
                $html .= '  </div>';
                $html .= '</div>';
            }
        }
    } else {
        $html = '<div class="no-results">No pilots found matching your criteria</div>';
    }

    $response['html'] = $html;
    $response['count'] = $pilotCount;

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    $response['html'] = '<div class="error">Error loading pilot data</div>';
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>

<!-- Rest of your HTML remains the same -->
<div class="list">
<style>
    .highlight {
        background-color: #ffeb3b;
        border-radius: 3px;
        padding: 0 2px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.12);
    }
    .pilot-item h4 {
        margin: 2px 0;
        padding: 1px;
        transition: background-color 0.2s;
    }
    .pilot-item:hover h4 {
        background-color:rgb(137, 219, 246);
    }
    .autocomplete-wrapper {
    position: relative;
    margin-bottom: 15px;
  }

  .autocomplete-results {
      position: absolute;
      z-index: 1000;
      width: 100%;
      max-height: 200px;
      overflow-y: auto;
      background: white;
      border: 1px solid #ddd;
      border-top: none;
      display: none;
  }

  .autocomplete-item {
      padding: 10px;
      cursor: pointer;
      transition: background-color 0.2s;
  }

  .autocomplete-item:hover {
      background-color: #f8f9fa;
  }
  </style>
  
  <div class="autocomplete-wrapper">
    <input type="text" id="search_pilot" name="search_pilot" 
           class="form-control" placeholder="Pilot's Name-pilot_list.php"
           autocomplete="off">
    <div class="autocomplete-results"></div>
  </div>

  <select id="sortBy" class="form-control outer-top-xxs">
    <option value="creation">Creation Date</option>
    <option value="name">Name</option>
    <option value="position">Position</option>
    <option value="duty">On Duty</option>
  </select>
  <select id="craftType" class="form-control outer-top-xxs">
    <option value="all">All</option>
  </select>
  <select id="showNonPilots" class="form-control outer-top-xxs">
    <option value="f">Show Only Pilots</option>
    <option value="t">Show Managers and Pilots</option>
    <option value="m">Show Only Managers</option>
  </select>
  <div id="pilots_list"><?= $response['html'] ?></div>
</div>

<!-- <script src="pilot-ajax.js" type="module"></script> -->
 <!-- I removed on v43 since I do not need it -->