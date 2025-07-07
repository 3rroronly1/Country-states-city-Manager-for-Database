<?php
require_once 'config.php';

// Handle AJAX requests for cascading dropdowns
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'get_states' && isset($_GET['country_id'])) {
        $country_id = intval($_GET['country_id']);
        $states = [];
        
        $stmt = $conn->prepare("SELECT id, name FROM states WHERE country_id = ? ORDER BY name");
        $stmt->bind_param("i", $country_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $states[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $states
        ]);
        exit;
    }
    
    if ($_GET['ajax'] === 'get_cities' && isset($_GET['state_id'])) {
        $state_id = intval($_GET['state_id']);
        $cities = [];
        
        $stmt = $conn->prepare("SELECT id, name FROM cities WHERE state_id = ? ORDER BY name");
        $stmt->bind_param("i", $state_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $cities[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $cities
        ]);
        exit;
    }
    
    if ($_GET['ajax'] === 'search_locations' && isset($_GET['query'])) {
        $query = trim($_GET['query']);
        $type = $_GET['type'] ?? 'all';
        $results = [];
        
        if ($type === 'all' || $type === 'countries') {
            $stmt = $conn->prepare("SELECT 'country' as type, name, code as extra FROM countries WHERE name LIKE ? ORDER BY name LIMIT 10");
            $search_term = "%$query%";
            $stmt->bind_param("s", $search_term);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $results[] = $row;
            }
        }
        
        if ($type === 'all' || $type === 'states') {
            $stmt = $conn->prepare("SELECT 'state' as type, s.name, c.name as extra FROM states s JOIN countries c ON s.country_id = c.id WHERE s.name LIKE ? ORDER BY s.name LIMIT 10");
            $search_term = "%$query%";
            $stmt->bind_param("s", $search_term);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $results[] = $row;
            }
        }
        
        if ($type === 'all' || $type === 'cities') {
            $stmt = $conn->prepare("SELECT 'city' as type, ci.name, CONCAT(s.name, ', ', c.name) as extra FROM cities ci JOIN states s ON ci.state_id = s.id JOIN countries c ON s.country_id = c.id WHERE ci.name LIKE ? ORDER BY ci.name LIMIT 10");
            $search_term = "%$query%";
            $stmt->bind_param("s", $search_term);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $results[] = $row;
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $results
        ]);
        exit;
    }
    
    if ($_GET['ajax'] === 'get_list' && isset($_GET['type'])) {
        $type = $_GET['type'];
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 20);
        $search = trim($_GET['search'] ?? '');
        $country_id = intval($_GET['country_id'] ?? 0);
        $state_id = intval($_GET['state_id'] ?? 0);
        $offset = ($page - 1) * $limit;
        
        $results = [];
        $total = 0;
        
        if ($type === 'countries') {
            $where = $search ? "WHERE name LIKE ?" : "";
            $count_query = "SELECT COUNT(*) as total FROM countries $where";
            $data_query = "SELECT id, name, code FROM countries $where ORDER BY name LIMIT ? OFFSET ?";
            
            if ($search) {
                $search_term = "%$search%";
                $stmt = $conn->prepare($count_query);
                $stmt->bind_param("s", $search_term);
                $stmt->execute();
                $total = $stmt->get_result()->fetch_assoc()['total'];
                
                $stmt = $conn->prepare($data_query);
                $stmt->bind_param("sii", $search_term, $limit, $offset);
            } else {
                $stmt = $conn->prepare($count_query);
                $stmt->execute();
                $total = $stmt->get_result()->fetch_assoc()['total'];
                
                $stmt = $conn->prepare($data_query);
                $stmt->bind_param("ii", $limit, $offset);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $results[] = $row;
            }
        } elseif ($type === 'states') {
            $where_parts = [];
            $params = [];
            $param_types = "";
            
            if ($country_id > 0) {
                $where_parts[] = "s.country_id = ?";
                $params[] = $country_id;
                $param_types .= "i";
            }
            
            if ($search) {
                $where_parts[] = "(s.name LIKE ? OR c.name LIKE ?)";
                $search_term = "%$search%";
                $params[] = $search_term;
                $params[] = $search_term;
                $param_types .= "ss";
            }
            
            $where = !empty($where_parts) ? "WHERE " . implode(" AND ", $where_parts) : "";
            
            $count_query = "SELECT COUNT(*) as total FROM states s JOIN countries c ON s.country_id = c.id $where";
            $data_query = "SELECT s.id, s.name, s.code, c.name as country_name FROM states s JOIN countries c ON s.country_id = c.id $where ORDER BY c.name, s.name LIMIT ? OFFSET ?";
            
            // Add limit and offset to params
            $params[] = $limit;
            $params[] = $offset;
            $param_types .= "ii";
            
            if (!empty($params)) {
                $stmt = $conn->prepare($count_query);
                if (!empty($where_parts)) {
                    $count_params = array_slice($params, 0, -2); // Remove limit and offset for count
                    $count_param_types = substr($param_types, 0, -2);
                    $stmt->bind_param($count_param_types, ...$count_params);
                }
                $stmt->execute();
                $total = $stmt->get_result()->fetch_assoc()['total'];
                
                $stmt = $conn->prepare($data_query);
                $stmt->bind_param($param_types, ...$params);
            } else {
                $stmt = $conn->prepare($count_query);
                $stmt->execute();
                $total = $stmt->get_result()->fetch_assoc()['total'];
                
                $stmt = $conn->prepare($data_query);
                $stmt->bind_param("ii", $limit, $offset);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $results[] = $row;
            }
        } elseif ($type === 'cities') {
            $where_parts = [];
            $params = [];
            $param_types = "";
            
            if ($state_id > 0) {
                $where_parts[] = "ci.state_id = ?";
                $params[] = $state_id;
                $param_types .= "i";
            } elseif ($country_id > 0) {
                $where_parts[] = "s.country_id = ?";
                $params[] = $country_id;
                $param_types .= "i";
            }
            
            if ($search) {
                $where_parts[] = "(ci.name LIKE ? OR s.name LIKE ? OR c.name LIKE ?)";
                $search_term = "%$search%";
                $params[] = $search_term;
                $params[] = $search_term;
                $params[] = $search_term;
                $param_types .= "sss";
            }
            
            $where = !empty($where_parts) ? "WHERE " . implode(" AND ", $where_parts) : "";
            
            $count_query = "SELECT COUNT(*) as total FROM cities ci JOIN states s ON ci.state_id = s.id JOIN countries c ON s.country_id = c.id $where";
            $data_query = "SELECT ci.id, ci.name, s.name as state_name, c.name as country_name FROM cities ci JOIN states s ON ci.state_id = s.id JOIN countries c ON s.country_id = c.id $where ORDER BY c.name, s.name, ci.name LIMIT ? OFFSET ?";
            
            // Add limit and offset to params
            $params[] = $limit;
            $params[] = $offset;
            $param_types .= "ii";
            
            if (!empty($params)) {
                $stmt = $conn->prepare($count_query);
                if (!empty($where_parts)) {
                    $count_params = array_slice($params, 0, -2); // Remove limit and offset for count
                    $count_param_types = substr($param_types, 0, -2);
                    $stmt->bind_param($count_param_types, ...$count_params);
                }
                $stmt->execute();
                $total = $stmt->get_result()->fetch_assoc()['total'];
                
                $stmt = $conn->prepare($data_query);
                $stmt->bind_param($param_types, ...$params);
            } else {
                $stmt = $conn->prepare($count_query);
                $stmt->execute();
                $total = $stmt->get_result()->fetch_assoc()['total'];
                
                $stmt = $conn->prepare($data_query);
                $stmt->bind_param("ii", $limit, $offset);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $results[] = $row;
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $results,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit)
        ]);
        exit;
    }
    
    echo json_encode(['success' => false, 'data' => []]);
    exit;
}

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    $message = '';
    
    try {
        switch ($action) {
            case 'add_country':
                $name = trim($_POST['country_name']);
                $code = strtoupper(trim($_POST['country_code']));
                
                if (empty($name) || empty($code)) {
                    throw new Exception("Country name and code are required");
                }
                
                // Check for case-insensitive duplicates
                $stmt = $conn->prepare("SELECT name FROM countries WHERE LOWER(name) = LOWER(?) OR LOWER(code) = LOWER(?)");
                $stmt->bind_param("ss", $name, $code);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $existing = $result->fetch_assoc();
                    throw new Exception("Country already exists as: " . $existing['name']);
                }
                
                $stmt = $conn->prepare("INSERT INTO countries (name, code) VALUES (?, ?)");
                $stmt->bind_param("ss", $name, $code);
                $stmt->execute();
                
                $message = "Country '$name' added successfully!";
                break;
                
            case 'add_state':
                $name = trim($_POST['state_name']);
                $country_id = intval($_POST['country_id']);
                $code = strtoupper(trim($_POST['state_code']));
                
                if (empty($name) || $country_id <= 0) {
                    throw new Exception("State name and country selection are required");
                }
                
                // Check for case-insensitive duplicates within the same country
                $stmt = $conn->prepare("SELECT name FROM states WHERE LOWER(name) = LOWER(?) AND country_id = ?");
                $stmt->bind_param("si", $name, $country_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $existing = $result->fetch_assoc();
                    throw new Exception("State already exists as: " . $existing['name']);
                }
                
                $stmt = $conn->prepare("INSERT INTO states (name, country_id, code) VALUES (?, ?, ?)");
                $stmt->bind_param("sis", $name, $country_id, $code);
                $stmt->execute();
                
                $message = "State '$name' added successfully!";
                break;
                
            case 'add_bulk_states':
                $states_input = trim($_POST['bulk_states']);
                $country_id = intval($_POST['bulk_country_id']);
                
                if (empty($states_input) || $country_id <= 0) {
                    throw new Exception("States list and country selection are required");
                }
                
                // Split by comma and clean up
                $states_array = array_map('trim', explode(',', $states_input));
                $states_array = array_filter($states_array, function($state) {
                    return !empty($state);
                });
                
                if (empty($states_array)) {
                    throw new Exception("No valid states found in the input");
                }
                
                // Get existing states for this country (case-insensitive)
                $existing_states = [];
                $stmt = $conn->prepare("SELECT LOWER(name) as name_lower, name FROM states WHERE country_id = ?");
                $stmt->bind_param("i", $country_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $existing_states[strtolower($row['name_lower'])] = $row['name'];
                }
                
                $added_states = [];
                $duplicate_states = [];
                $stmt = $conn->prepare("INSERT INTO states (name, country_id) VALUES (?, ?)");
                
                foreach ($states_array as $state_name) {
                    $state_lower = strtolower($state_name);
                    
                    if (isset($existing_states[$state_lower])) {
                        // Duplicate found
                        $duplicate_states[] = $state_name . " (already exists as: " . $existing_states[$state_lower] . ")";
                    } else {
                        // Check if we already processed this name in current batch
                        $already_in_batch = false;
                        foreach ($added_states as $added_state) {
                            if (strtolower($added_state) === $state_lower) {
                                $duplicate_states[] = $state_name . " (duplicate in current batch)";
                                $already_in_batch = true;
                                break;
                            }
                        }
                        
                        if (!$already_in_batch) {
                            $stmt->bind_param("si", $state_name, $country_id);
                            if ($stmt->execute()) {
                                $added_states[] = $state_name;
                                $existing_states[$state_lower] = $state_name; // Add to existing list
                            }
                        }
                    }
                }
                
                $message = "<strong>Bulk States Addition Results:</strong><br>";
                $message .= "✅ <strong>Added (" . count($added_states) . "):</strong> " . implode(', ', $added_states) . "<br>";
                if (!empty($duplicate_states)) {
                    $message .= "❌ <strong>Rejected (" . count($duplicate_states) . "):</strong> " . implode(', ', $duplicate_states);
                }
                break;
                
            case 'add_city':
                $name = trim($_POST['city_name']);
                $state_id = intval($_POST['state_id']);
                
                if (empty($name) || $state_id <= 0) {
                    throw new Exception("City name and state selection are required");
                }
                
                // Check for case-insensitive duplicates within the same state
                $stmt = $conn->prepare("SELECT name FROM cities WHERE LOWER(name) = LOWER(?) AND state_id = ?");
                $stmt->bind_param("si", $name, $state_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $existing = $result->fetch_assoc();
                    throw new Exception("City already exists as: " . $existing['name']);
                }
                
                $stmt = $conn->prepare("INSERT INTO cities (name, state_id) VALUES (?, ?)");
                $stmt->bind_param("si", $name, $state_id);
                $stmt->execute();
                
                $message = "City '$name' added successfully!";
                break;
                
            case 'add_bulk_cities':
                $cities_input = trim($_POST['bulk_cities']);
                $state_id = intval($_POST['bulk_state_id']);
                
                if (empty($cities_input) || $state_id <= 0) {
                    throw new Exception("Cities list and state selection are required");
                }
                
                // Split by comma and clean up
                $cities_array = array_map('trim', explode(',', $cities_input));
                $cities_array = array_filter($cities_array, function($city) {
                    return !empty($city);
                });
                
                if (empty($cities_array)) {
                    throw new Exception("No valid cities found in the input");
                }
                
                // Get existing cities for this state (case-insensitive)
                $existing_cities = [];
                $stmt = $conn->prepare("SELECT LOWER(name) as name_lower, name FROM cities WHERE state_id = ?");
                $stmt->bind_param("i", $state_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $existing_cities[strtolower($row['name_lower'])] = $row['name'];
                }
                
                $added_cities = [];
                $duplicate_cities = [];
                $stmt = $conn->prepare("INSERT INTO cities (name, state_id) VALUES (?, ?)");
                
                foreach ($cities_array as $city_name) {
                    $city_lower = strtolower($city_name);
                    
                    if (isset($existing_cities[$city_lower])) {
                        // Duplicate found
                        $duplicate_cities[] = $city_name . " (already exists as: " . $existing_cities[$city_lower] . ")";
                    } else {
                        // Check if we already processed this name in current batch
                        $already_in_batch = false;
                        foreach ($added_cities as $added_city) {
                            if (strtolower($added_city) === $city_lower) {
                                $duplicate_cities[] = $city_name . " (duplicate in current batch)";
                                $already_in_batch = true;
                                break;
                            }
                        }
                        
                        if (!$already_in_batch) {
                            $stmt->bind_param("si", $city_name, $state_id);
                            if ($stmt->execute()) {
                                $added_cities[] = $city_name;
                                $existing_cities[$city_lower] = $city_name; // Add to existing list
                            }
                        }
                    }
                }
                
                $message = "<strong>Bulk Cities Addition Results:</strong><br>";
                $message .= "✅ <strong>Added (" . count($added_cities) . "):</strong> " . implode(', ', $added_cities) . "<br>";
                if (!empty($duplicate_cities)) {
                    $message .= "❌ <strong>Rejected (" . count($duplicate_cities) . "):</strong> " . implode(', ', $duplicate_cities);
                }
                break;
                
            case 'remove_duplicates':
                $table = $_POST['table'] ?? '';
                $removed_count = 0;
                
                if ($table === 'countries') {
                    // Remove duplicate countries (keep the first occurrence)
                    $conn->query("DELETE c1 FROM countries c1 
                                 INNER JOIN countries c2 
                                 WHERE c1.id > c2.id 
                                 AND c1.name = c2.name");
                    $removed_count = $conn->affected_rows;
                } elseif ($table === 'states') {
                    // Remove duplicate states within same country
                    $conn->query("DELETE s1 FROM states s1 
                                 INNER JOIN states s2 
                                 WHERE s1.id > s2.id 
                                 AND s1.name = s2.name 
                                 AND s1.country_id = s2.country_id");
                    $removed_count = $conn->affected_rows;
                } elseif ($table === 'cities') {
                    // Remove duplicate cities within same state
                    $conn->query("DELETE c1 FROM cities c1 
                                 INNER JOIN cities c2 
                                 WHERE c1.id > c2.id 
                                 AND c1.name = c2.name 
                                 AND c1.state_id = c2.state_id");
                    $removed_count = $conn->affected_rows;
                } else {
                    throw new Exception("Invalid table specified");
                }
                
                $message = "Successfully removed $removed_count duplicate entries from " . ucfirst($table) . "!";
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get countries for dropdowns
$countries = [];
$result = $conn->query("SELECT id, name, code FROM countries ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $countries[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Add Locations - FlightBook Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen py-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-8 text-center">Bulk Add Locations - FlightBook Admin</h1>
            
            <?php if (isset($message)): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo strpos($message, 'Error') === 0 ? 'bg-red-100 text-red-700 border border-red-300' : 'bg-green-100 text-green-700 border border-green-300'; ?>">
                <?php 
                // Check if message contains HTML (bulk results)
                if (strpos($message, '<strong>') !== false) {
                    echo $message; // Display HTML for bulk results
                } else {
                    echo htmlspecialchars($message); // Escape HTML for simple messages
                }
                ?>
            </div>
            <?php endif; ?>
            
            <!-- Tab Navigation -->
            <div class="border-b border-gray-200 mb-8">
                <nav class="-mb-px flex space-x-8">
                    <button id="single-tab" onclick="showTab('single')" 
                            class="tab-button py-2 px-1 border-b-2 font-medium text-sm border-blue-500 text-blue-600">
                        Individual Entry
                    </button>
                    <button id="bulk-tab" onclick="showTab('bulk')" 
                            class="tab-button py-2 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        Bulk Entry
                    </button>
                    <button id="list-tab" onclick="showTab('list')" 
                            class="tab-button py-2 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        List View
                    </button>
                    <button id="cleanup-tab" onclick="showTab('cleanup')" 
                            class="tab-button py-2 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        Cleanup
                    </button>
                    <button id="test-tab" onclick="showTab('test')" 
                            class="tab-button py-2 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        🧪 Test Dropdowns
                    </button>
                </nav>
            </div>
            
            <!-- Single Entry Tab -->
            <div id="single-content" class="tab-content">
                <div class="grid md:grid-cols-3 gap-8">
                    <!-- Add Country -->
                    <div class="bg-blue-50 p-6 rounded-lg">
                        <h2 class="text-xl font-semibold text-blue-800 mb-4">Add Country</h2>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_country">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Country Name</label>
                                <input type="text" name="country_name" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="e.g., Pakistan">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Country Code</label>
                                <input type="text" name="country_code" required maxlength="3"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="e.g., PK">
                            </div>
                            
                            <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200">
                                Add Country
                            </button>
                        </form>
                    </div>
                    
                    <!-- Add State -->
                    <div class="bg-green-50 p-6 rounded-lg">
                        <h2 class="text-xl font-semibold text-green-800 mb-4">Add State/Province</h2>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_state">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select Country</label>
                                <select name="country_id" required 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500">
                                    <option value="">Select Country</option>
                                    <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo $country['id']; ?>">
                                        <?php echo htmlspecialchars($country['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">State/Province Name</label>
                                <input type="text" name="state_name" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500"
                                       placeholder="e.g., Punjab">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">State Code (Optional)</label>
                                <input type="text" name="state_code" maxlength="10"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500"
                                       placeholder="e.g., PB">
                            </div>
                            
                            <button type="submit" class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition duration-200">
                                Add State
                            </button>
                        </form>
                    </div>
                    
                    <!-- Add City -->
                    <div class="bg-purple-50 p-6 rounded-lg">
                        <h2 class="text-xl font-semibold text-purple-800 mb-4">Add City</h2>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_city">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select Country</label>
                                <select id="city-country" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                    <option value="">Select Country</option>
                                    <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo $country['id']; ?>">
                                        <?php echo htmlspecialchars($country['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select State</label>
                                <select name="state_id" id="city-state" required disabled
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                    <option value="">Select State</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">City Name</label>
                                <input type="text" name="city_name" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500"
                                       placeholder="e.g., Lahore">
                            </div>
                            
                            <button type="submit" class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition duration-200">
                                Add City
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Bulk Entry Tab -->
            <div id="bulk-content" class="tab-content hidden">
                <div class="grid md:grid-cols-2 gap-8">
                    <!-- Bulk Add States -->
                    <div class="bg-green-50 p-6 rounded-lg">
                        <h2 class="text-xl font-semibold text-green-800 mb-4">🚀 Bulk Add States</h2>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_bulk_states">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select Country</label>
                                <select name="bulk_country_id" required 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500">
                                    <option value="">Select Country</option>
                                    <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo $country['id']; ?>">
                                        <?php echo htmlspecialchars($country['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">States (Comma Separated)</label>
                                <textarea name="bulk_states" required rows="6"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500"
                                          placeholder="Punjab, Sindh, Khyber Pakhtunkhwa, Balochistan, Gilgit-Baltistan, Azad Kashmir, Islamabad Capital Territory"></textarea>
                                <p class="text-sm text-gray-600 mt-1">Enter state names separated by commas. Each state will be added automatically.</p>
                            </div>
                            
                            <div class="bg-green-100 p-3 rounded-md">
                                <h4 class="font-medium text-green-800 mb-2">💡 Example:</h4>
                                <code class="text-sm text-green-700">Punjab, Sindh, Khyber Pakhtunkhwa, Balochistan</code>
                            </div>
                            
                            <button type="submit" class="w-full bg-green-600 text-white py-3 px-4 rounded-md hover:bg-green-700 transition duration-200 font-medium">
                                🚀 Add All States
                            </button>
                        </form>
                    </div>
                    
                    <!-- Bulk Add Cities -->
                    <div class="bg-purple-50 p-6 rounded-lg">
                        <h2 class="text-xl font-semibold text-purple-800 mb-4">🏙️ Bulk Add Cities</h2>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_bulk_cities">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select Country</label>
                                <select id="bulk-city-country" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                    <option value="">Select Country</option>
                                    <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo $country['id']; ?>">
                                        <?php echo htmlspecialchars($country['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select State</label>
                                <select name="bulk_state_id" id="bulk-city-state" required disabled
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                    <option value="">Select State</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Cities (Comma Separated)</label>
                                <textarea name="bulk_cities" required rows="6"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500"
                                          placeholder="Lahore, Karachi, Islamabad, Rawalpindi, Faisalabad, Multan, Gujranwala, Peshawar, Quetta, Sialkot"></textarea>
                                <p class="text-sm text-gray-600 mt-1">Enter city names separated by commas. Each city will be added automatically.</p>
                            </div>
                            
                            <div class="bg-purple-100 p-3 rounded-md">
                                <h4 class="font-medium text-purple-800 mb-2">💡 Example:</h4>
                                <code class="text-sm text-purple-700">Lahore, Karachi, Islamabad, Rawalpindi, Faisalabad</code>
                            </div>
                            
                            <button type="submit" class="w-full bg-purple-600 text-white py-3 px-4 rounded-md hover:bg-purple-700 transition duration-200 font-medium">
                                🏙️ Add All Cities
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Cleanup Tab -->
            <div id="cleanup-content" class="tab-content hidden">
                <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-red-800 mb-4">🧹 Remove Duplicate Entries</h2>
                    <p class="text-red-700 mb-4">
                        <strong>⚠️ Warning:</strong> This will permanently remove duplicate entries from your database. 
                        This action cannot be undone. The system will keep the first occurrence and remove subsequent duplicates.
                    </p>
                    
                    <div class="grid md:grid-cols-3 gap-4">
                        <!-- Remove Duplicate Countries -->
                        <div class="bg-white p-4 rounded-lg border border-red-200">
                            <h3 class="font-semibold text-red-800 mb-3">🌍 Countries</h3>
                            <p class="text-sm text-gray-600 mb-3">Remove countries with identical names</p>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to remove duplicate countries? This cannot be undone!')">
                                <input type="hidden" name="action" value="remove_duplicates">
                                <input type="hidden" name="table" value="countries">
                                <button type="submit" class="w-full bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 transition duration-200">
                                    Remove Duplicates
                                </button>
                            </form>
                        </div>
                        
                        <!-- Remove Duplicate States -->
                        <div class="bg-white p-4 rounded-lg border border-red-200">
                            <h3 class="font-semibold text-red-800 mb-3">🏛️ States</h3>
                            <p class="text-sm text-gray-600 mb-3">Remove states with identical names within the same country</p>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to remove duplicate states? This cannot be undone!')">
                                <input type="hidden" name="action" value="remove_duplicates">
                                <input type="hidden" name="table" value="states">
                                <button type="submit" class="w-full bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 transition duration-200">
                                    Remove Duplicates
                                </button>
                            </form>
                        </div>
                        
                        <!-- Remove Duplicate Cities -->
                        <div class="bg-white p-4 rounded-lg border border-red-200">
                            <h3 class="font-semibold text-red-800 mb-3">🏙️ Cities</h3>
                            <p class="text-sm text-gray-600 mb-3">Remove cities with identical names within the same state</p>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to remove duplicate cities? This cannot be undone!')">
                                <input type="hidden" name="action" value="remove_duplicates">
                                <input type="hidden" name="table" value="cities">
                                <button type="submit" class="w-full bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 transition duration-200">
                                    Remove Duplicates
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="mt-6 bg-yellow-100 border border-yellow-300 rounded-lg p-4">
                        <h4 class="font-semibold text-yellow-800 mb-2">💡 How it works:</h4>
                        <ul class="text-sm text-yellow-700 space-y-1">
                            <li>• <strong>Countries:</strong> Removes countries with exact same names</li>
                            <li>• <strong>States:</strong> Removes states with same names within the same country</li>
                            <li>• <strong>Cities:</strong> Removes cities with same names within the same state</li>
                            <li>• <strong>Safe:</strong> Always keeps the first entry, removes later duplicates</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Find Duplicates (Preview) -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <h2 class="text-xl font-semibold text-blue-800 mb-4">🔍 Preview Duplicates</h2>
                    <p class="text-blue-700 mb-4">Check what duplicates exist before removing them:</p>
                    
                    <div class="grid md:grid-cols-3 gap-4">
                        <?php
                        // Find duplicate countries
                        $duplicate_countries = [];
                        $result = $conn->query("SELECT name, COUNT(*) as count FROM countries GROUP BY name HAVING count > 1");
                        if ($result) {
                            while ($row = $result->fetch_assoc()) {
                                $duplicate_countries[] = $row;
                            }
                        }
                        
                        // Find duplicate states
                        $duplicate_states = [];
                        $result = $conn->query("SELECT s.name, c.name as country_name, COUNT(*) as count 
                                              FROM states s 
                                              JOIN countries c ON s.country_id = c.id 
                                              GROUP BY s.name, s.country_id 
                                              HAVING count > 1");
                        if ($result) {
                            while ($row = $result->fetch_assoc()) {
                                $duplicate_states[] = $row;
                            }
                        }
                        
                        // Find duplicate cities
                        $duplicate_cities = [];
                        $result = $conn->query("SELECT ci.name, s.name as state_name, c.name as country_name, COUNT(*) as count 
                                              FROM cities ci 
                                              JOIN states s ON ci.state_id = s.id 
                                              JOIN countries c ON s.country_id = c.id 
                                              GROUP BY ci.name, ci.state_id 
                                              HAVING count > 1");
                        if ($result) {
                            while ($row = $result->fetch_assoc()) {
                                $duplicate_cities[] = $row;
                            }
                        }
                        ?>
                        
                        <div class="bg-white p-4 rounded-lg border border-blue-200">
                            <h3 class="font-semibold text-blue-800 mb-3">🌍 Duplicate Countries</h3>
                            <?php if (empty($duplicate_countries)): ?>
                                <p class="text-green-600 text-sm">✅ No duplicate countries found</p>
                            <?php else: ?>
                                <div class="space-y-1 text-sm">
                                    <?php foreach ($duplicate_countries as $dup): ?>
                                        <div class="text-red-600">
                                            "<?php echo htmlspecialchars($dup['name']); ?>" (<?php echo $dup['count']; ?> times)
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="bg-white p-4 rounded-lg border border-blue-200">
                            <h3 class="font-semibold text-blue-800 mb-3">🏛️ Duplicate States</h3>
                            <?php if (empty($duplicate_states)): ?>
                                <p class="text-green-600 text-sm">✅ No duplicate states found</p>
                            <?php else: ?>
                                <div class="space-y-1 text-sm max-h-32 overflow-y-auto">
                                    <?php foreach ($duplicate_states as $dup): ?>
                                        <div class="text-red-600">
                                            "<?php echo htmlspecialchars($dup['name']); ?>" in <?php echo htmlspecialchars($dup['country_name']); ?> (<?php echo $dup['count']; ?> times)
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="bg-white p-4 rounded-lg border border-blue-200">
                            <h3 class="font-semibold text-blue-800 mb-3">🏙️ Duplicate Cities</h3>
                            <?php if (empty($duplicate_cities)): ?>
                                <p class="text-green-600 text-sm">✅ No duplicate cities found</p>
                            <?php else: ?>
                                <div class="space-y-1 text-sm max-h-32 overflow-y-auto">
                                    <?php foreach ($duplicate_cities as $dup): ?>
                                        <div class="text-red-600">
                                            "<?php echo htmlspecialchars($dup['name']); ?>" in <?php echo htmlspecialchars($dup['state_name']); ?>, <?php echo htmlspecialchars($dup['country_name']); ?> (<?php echo $dup['count']; ?> times)
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Test Dropdowns Tab -->
            <div id="test-content" class="tab-content hidden">
                <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-lg p-8">
                    <div class="text-center mb-8">
                        <h2 class="text-3xl font-bold text-gray-800 mb-2">🧪 Test Cascading Dropdowns</h2>
                        <p class="text-gray-600">Test the Country → State → City dropdown functionality without adding any data</p>
                    </div>
                    
                    <div class="max-w-2xl mx-auto">
                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <h3 class="text-xl font-semibold text-gray-800 mb-6 text-center">🌍 Select Location Hierarchy</h3>
                            
                            <div class="space-y-6">
                                <!-- Country Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        🌍 Step 1: Select Country
                                    </label>
                                    <select id="test-country" 
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg">
                                        <option value="">Choose a country...</option>
                                        <?php foreach ($countries as $country): ?>
                                        <option value="<?php echo $country['id']; ?>">
                                            <?php echo htmlspecialchars($country['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="test-country-status" class="mt-2 text-sm text-gray-500">
                                        Select a country to proceed
                                    </div>
                                </div>
                                
                                <!-- State Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        🏛️ Step 2: Select State/Province
                                    </label>
                                    <select id="test-state" disabled
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 text-lg disabled:bg-gray-100 disabled:cursor-not-allowed">
                                        <option value="">First select a country...</option>
                                    </select>
                                    <div id="test-state-status" class="mt-2 text-sm text-gray-500">
                                        Waiting for country selection
                                    </div>
                                </div>
                                
                                <!-- City Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        🏙️ Step 3: Select City
                                    </label>
                                    <select id="test-city" disabled
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-lg disabled:bg-gray-100 disabled:cursor-not-allowed">
                                        <option value="">First select a state...</option>
                                    </select>
                                    <div id="test-city-status" class="mt-2 text-sm text-gray-500">
                                        Waiting for state selection
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Results Display -->
                            <div id="test-results" class="mt-8 p-4 bg-gray-50 rounded-lg hidden">
                                <h4 class="font-semibold text-gray-800 mb-3">✅ Selection Complete!</h4>
                                <div class="space-y-2 text-sm">
                                    <div><strong>Country:</strong> <span id="selected-country-name" class="text-blue-600"></span></div>
                                    <div><strong>State:</strong> <span id="selected-state-name" class="text-green-600"></span></div>
                                    <div><strong>City:</strong> <span id="selected-city-name" class="text-purple-600"></span></div>
                                </div>
                                <div class="mt-4 p-3 bg-white border border-gray-200 rounded-md">
                                    <div class="text-xs text-gray-500 mb-1">Full Location Path:</div>
                                    <div id="full-location-path" class="font-medium text-gray-800"></div>
                                </div>
                            </div>
                            
                            <!-- Reset Button -->
                            <div class="mt-6 text-center">
                                <button onclick="resetTestDropdowns()" 
                                        class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200">
                                    🔄 Reset Test
                                </button>
                            </div>
                        </div>
                        
                        <!-- Instructions -->
                        <div class="mt-8 bg-blue-100 border border-blue-200 rounded-lg p-4">
                            <h4 class="font-semibold text-blue-800 mb-2">📋 How to Test:</h4>
                            <ol class="text-sm text-blue-700 space-y-1">
                                <li>1. Select a country from the first dropdown</li>
                                <li>2. Watch the states dropdown populate automatically</li>
                                <li>3. Select a state to populate the cities dropdown</li>
                                <li>4. Select a city to see the complete location path</li>
                                <li>5. Use the reset button to test again</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- List View Tab -->
            <div id="list-content" class="tab-content hidden">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-2xl font-semibold text-gray-800 mb-6">📋 Browse All Locations</h2>
                    
                    <!-- Breadcrumb Navigation -->
                    <div id="list-breadcrumb" class="mb-4 text-sm text-gray-600 hidden">
                        <nav class="flex" aria-label="Breadcrumb">
                            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                                <li class="inline-flex items-center">
                                    <button onclick="resetToCountries()" class="text-blue-600 hover:text-blue-800">
                                        🌍 All Countries
                                    </button>
                                </li>
                                <li id="country-breadcrumb" class="hidden">
                                    <div class="flex items-center">
                                        <span class="mx-2">/</span>
                                        <button id="country-breadcrumb-btn" onclick="showCountryStates()" class="text-blue-600 hover:text-blue-800">
                                            <!-- Will be populated -->
                                        </button>
                                    </div>
                                </li>
                                <li id="state-breadcrumb" class="hidden">
                                    <div class="flex items-center">
                                        <span class="mx-2">/</span>
                                        <span id="state-breadcrumb-text" class="text-gray-500">
                                            <!-- Will be populated -->
                                        </span>
                                    </div>
                                </li>
                            </ol>
                        </nav>
                    </div>
                    
                    <!-- Controls -->
                    <div class="flex flex-col md:flex-row gap-4 mb-6">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">View Type</label>
                            <select id="list-type" onchange="handleViewTypeChange()" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                <option value="countries">🌍 All Countries</option>
                                <option value="states">🏛️ All States</option>
                                <option value="cities">🏙️ All Cities</option>
                            </select>
                        </div>
                        
                        <div class="flex-1" id="country-filter-container" style="display: none;">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Country</label>
                            <select id="country-filter" onchange="handleCountryFilter()" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500">
                                <option value="">All Countries</option>
                                <!-- Will be populated -->
                            </select>
                        </div>
                        
                        <div class="flex-1" id="state-filter-container" style="display: none;">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Filter by State</label>
                            <select id="state-filter" onchange="handleStateFilter()" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                <option value="">All States</option>
                                <!-- Will be populated -->
                            </select>
                        </div>
                        
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                            <input type="text" id="list-search" placeholder="Search locations..." 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Items per page</label>
                            <select id="list-limit" onchange="loadListData()" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                <option value="10">10</option>
                                <option value="20" selected>20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Loading State -->
                    <div id="list-loading" class="text-center py-8 hidden">
                        <div class="text-blue-500">🔄 Loading...</div>
                    </div>
                    
                    <!-- Results Info -->
                    <div id="list-info" class="mb-4 text-sm text-gray-600 hidden">
                        <!-- Will be populated by JavaScript -->
                    </div>
                    
                    <!-- Data Table -->
                    <div id="list-table-container" class="overflow-x-auto">
                        <table id="list-table" class="min-w-full bg-white border border-gray-200 rounded-lg hidden">
                            <thead class="bg-gray-50">
                                <tr id="list-table-header">
                                    <!-- Will be populated by JavaScript -->
                                </tr>
                            </thead>
                            <tbody id="list-table-body">
                                <!-- Will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div id="list-pagination" class="mt-6 flex justify-between items-center hidden">
                        <div class="text-sm text-gray-600">
                            <span id="pagination-info"><!-- Will be populated by JavaScript --></span>
                        </div>
                        <div class="flex space-x-2">
                            <button id="prev-page" onclick="changePage(-1)" 
                                    class="px-3 py-1 bg-gray-200 text-gray-600 rounded-md hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed">
                                Previous
                            </button>
                            <span id="page-numbers" class="flex space-x-1">
                                <!-- Will be populated by JavaScript -->
                            </span>
                            <button id="next-page" onclick="changePage(1)" 
                                    class="px-3 py-1 bg-gray-200 text-gray-600 rounded-md hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed">
                                Next
                            </button>
                        </div>
                    </div>
                    
                    <!-- Empty State -->
                    <div id="list-empty" class="text-center py-8 text-gray-500 hidden">
                        <div class="text-4xl mb-4">📭</div>
                        <div class="text-lg font-medium">No locations found</div>
                        <div class="text-sm">Try adjusting your search or add some locations first</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="bg-white rounded-lg shadow-lg p-8">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Current Statistics</h2>
            <div class="grid md:grid-cols-3 gap-6">
                <?php
                // Get statistics
                $stats = [
                    'countries' => 0,
                    'states' => 0,
                    'cities' => 0
                ];
                
                $result = $conn->query("SELECT COUNT(*) as count FROM countries");
                if ($result && $row = $result->fetch_assoc()) {
                    $stats['countries'] = $row['count'];
                }
                
                $result = $conn->query("SELECT COUNT(*) as count FROM states");
                if ($result && $row = $result->fetch_assoc()) {
                    $stats['states'] = $row['count'];
                }
                
                $result = $conn->query("SELECT COUNT(*) as count FROM cities");
                if ($result && $row = $result->fetch_assoc()) {
                    $stats['cities'] = $row['count'];
                }
                ?>
                
                <div class="text-center p-6 bg-blue-100 rounded-lg">
                    <div class="text-3xl font-bold text-blue-600"><?php echo $stats['countries']; ?></div>
                    <div class="text-blue-800 font-medium">Countries</div>
                </div>
                
                <div class="text-center p-6 bg-green-100 rounded-lg">
                    <div class="text-3xl font-bold text-green-600"><?php echo $stats['states']; ?></div>
                    <div class="text-green-800 font-medium">States/Provinces</div>
                </div>
                
                <div class="text-center p-6 bg-purple-100 rounded-lg">
                    <div class="text-3xl font-bold text-purple-600"><?php echo $stats['cities']; ?></div>
                    <div class="text-purple-800 font-medium">Cities</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Simple tab switching functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active styles from all tabs
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-blue-500', 'text-blue-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-content').classList.remove('hidden');
            
            // Add active styles to selected tab
            const activeTab = document.getElementById(tabName + '-tab');
            activeTab.classList.remove('border-transparent', 'text-gray-500');
            activeTab.classList.add('border-blue-500', 'text-blue-600');
        }
        
        // Handle cascading dropdown for single city addition
        document.getElementById('city-country').addEventListener('change', function() {
            const countryId = this.value;
            const stateSelect = document.getElementById('city-state');
            
            // Reset state dropdown
            stateSelect.innerHTML = '<option value="">Loading...</option>';
            stateSelect.disabled = true;
            
            if (countryId) {
                fetch(`admin_add_locations_bulk.php?ajax=get_states&country_id=${countryId}`)
                    .then(response => response.json())
                    .then(data => {
                        stateSelect.innerHTML = '<option value="">Select State</option>';
                        if (data.success && data.data.length > 0) {
                            data.data.forEach(state => {
                                const option = document.createElement('option');
                                option.value = state.id;
                                option.textContent = state.name;
                                stateSelect.appendChild(option);
                            });
                            stateSelect.disabled = false;
                        } else {
                            stateSelect.innerHTML = '<option value="">No states found</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching states:', error);
                        stateSelect.innerHTML = '<option value="">Error loading states</option>';
                    });
            } else {
                stateSelect.innerHTML = '<option value="">Select State</option>';
            }
        });
        
        // Handle cascading dropdown for bulk city addition
        document.getElementById('bulk-city-country').addEventListener('change', function() {
            const countryId = this.value;
            const stateSelect = document.getElementById('bulk-city-state');
            
            // Reset state dropdown
            stateSelect.innerHTML = '<option value="">Loading...</option>';
            stateSelect.disabled = true;
            
            if (countryId) {
                fetch(`admin_add_locations_bulk.php?ajax=get_states&country_id=${countryId}`)
                    .then(response => response.json())
                    .then(data => {
                        stateSelect.innerHTML = '<option value="">Select State</option>';
                        if (data.success && data.data.length > 0) {
                            data.data.forEach(state => {
                                const option = document.createElement('option');
                                option.value = state.id;
                                option.textContent = state.name;
                                stateSelect.appendChild(option);
                            });
                            stateSelect.disabled = false;
                        } else {
                            stateSelect.innerHTML = '<option value="">No states found</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching bulk states:', error);
                        stateSelect.innerHTML = '<option value="">Error loading states</option>';
                    });
            } else {
                stateSelect.innerHTML = '<option value="">Select State</option>';
            }
        });
        
        // Test Dropdowns Functionality
        function resetTestDropdowns() {
            // Reset all dropdowns
            document.getElementById('test-country').value = '';
            document.getElementById('test-state').innerHTML = '<option value="">First select a country...</option>';
            document.getElementById('test-state').disabled = true;
            document.getElementById('test-city').innerHTML = '<option value="">First select a state...</option>';
            document.getElementById('test-city').disabled = true;
            
            // Reset status messages
            document.getElementById('test-country-status').textContent = 'Select a country to proceed';
            document.getElementById('test-country-status').className = 'mt-2 text-sm text-gray-500';
            document.getElementById('test-state-status').textContent = 'Waiting for country selection';
            document.getElementById('test-state-status').className = 'mt-2 text-sm text-gray-500';
            document.getElementById('test-city-status').textContent = 'Waiting for state selection';
            document.getElementById('test-city-status').className = 'mt-2 text-sm text-gray-500';
            
            // Hide results
            document.getElementById('test-results').classList.add('hidden');
        }
        
        // Test Country Selection
        document.getElementById('test-country').addEventListener('change', function() {
            const countryId = this.value;
            const countryName = this.options[this.selectedIndex].text;
            const stateSelect = document.getElementById('test-state');
            const citySelect = document.getElementById('test-city');
            const countryStatus = document.getElementById('test-country-status');
            const stateStatus = document.getElementById('test-state-status');
            const cityStatus = document.getElementById('test-city-status');
            
            // Reset dependent dropdowns
            citySelect.innerHTML = '<option value="">First select a state...</option>';
            citySelect.disabled = true;
            cityStatus.textContent = 'Waiting for state selection';
            cityStatus.className = 'mt-2 text-sm text-gray-500';
            
            // Hide results
            document.getElementById('test-results').classList.add('hidden');
            
            if (countryId) {
                // Update country status
                countryStatus.textContent = `✅ Selected: ${countryName}`;
                countryStatus.className = 'mt-2 text-sm text-green-600';
                
                // Load states
                stateSelect.innerHTML = '<option value="">Loading states...</option>';
                stateSelect.disabled = true;
                stateStatus.textContent = '⏳ Loading states...';
                stateStatus.className = 'mt-2 text-sm text-blue-600';
                
                fetch(`admin_add_locations_bulk.php?ajax=get_states&country_id=${countryId}`)
                    .then(response => response.json())
                    .then(data => {
                        stateSelect.innerHTML = '<option value="">Choose a state...</option>';
                        if (data.success && data.data.length > 0) {
                            data.data.forEach(state => {
                                const option = document.createElement('option');
                                option.value = state.id;
                                option.textContent = state.name;
                                stateSelect.appendChild(option);
                            });
                            stateSelect.disabled = false;
                            stateStatus.textContent = `✅ ${data.data.length} states loaded. Select one to proceed.`;
                            stateStatus.className = 'mt-2 text-sm text-green-600';
                        } else {
                            stateSelect.innerHTML = '<option value="">No states found</option>';
                            stateStatus.textContent = '❌ No states found for this country';
                            stateStatus.className = 'mt-2 text-sm text-red-600';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching states:', error);
                        stateSelect.innerHTML = '<option value="">Error loading states</option>';
                        stateStatus.textContent = '❌ Error loading states';
                        stateStatus.className = 'mt-2 text-sm text-red-600';
                    });
            } else {
                // Reset everything
                countryStatus.textContent = 'Select a country to proceed';
                countryStatus.className = 'mt-2 text-sm text-gray-500';
                stateSelect.innerHTML = '<option value="">First select a country...</option>';
                stateSelect.disabled = true;
                stateStatus.textContent = 'Waiting for country selection';
                stateStatus.className = 'mt-2 text-sm text-gray-500';
            }
        });
        
        // Test State Selection
        document.getElementById('test-state').addEventListener('change', function() {
            const stateId = this.value;
            const stateName = this.options[this.selectedIndex].text;
            const citySelect = document.getElementById('test-city');
            const stateStatus = document.getElementById('test-state-status');
            const cityStatus = document.getElementById('test-city-status');
            
            // Hide results
            document.getElementById('test-results').classList.add('hidden');
            
            if (stateId) {
                // Update state status
                stateStatus.textContent = `✅ Selected: ${stateName}`;
                stateStatus.className = 'mt-2 text-sm text-green-600';
                
                // Load cities
                citySelect.innerHTML = '<option value="">Loading cities...</option>';
                citySelect.disabled = true;
                cityStatus.textContent = '⏳ Loading cities...';
                cityStatus.className = 'mt-2 text-sm text-blue-600';
                
                fetch(`admin_add_locations_bulk.php?ajax=get_cities&state_id=${stateId}`)
                    .then(response => response.json())
                    .then(data => {
                        citySelect.innerHTML = '<option value="">Choose a city...</option>';
                        if (data.success && data.data.length > 0) {
                            data.data.forEach(city => {
                                const option = document.createElement('option');
                                option.value = city.id;
                                option.textContent = city.name;
                                citySelect.appendChild(option);
                            });
                            citySelect.disabled = false;
                            cityStatus.textContent = `✅ ${data.data.length} cities loaded. Select one to complete.`;
                            cityStatus.className = 'mt-2 text-sm text-green-600';
                        } else {
                            citySelect.innerHTML = '<option value="">No cities found</option>';
                            cityStatus.textContent = '❌ No cities found for this state';
                            cityStatus.className = 'mt-2 text-sm text-red-600';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching cities:', error);
                        citySelect.innerHTML = '<option value="">Error loading cities</option>';
                        cityStatus.textContent = '❌ Error loading cities';
                        cityStatus.className = 'mt-2 text-sm text-red-600';
                    });
            } else {
                // Reset city dropdown
                citySelect.innerHTML = '<option value="">First select a state...</option>';
                citySelect.disabled = true;
                cityStatus.textContent = 'Waiting for state selection';
                cityStatus.className = 'mt-2 text-sm text-gray-500';
            }
        });
        
        // Test City Selection
        document.getElementById('test-city').addEventListener('change', function() {
            const cityId = this.value;
            const cityName = this.options[this.selectedIndex].text;
            const cityStatus = document.getElementById('test-city-status');
            
            if (cityId) {
                // Update city status
                cityStatus.textContent = `✅ Selected: ${cityName}`;
                cityStatus.className = 'mt-2 text-sm text-green-600';
                
                // Show complete results
                const countryName = document.getElementById('test-country').options[document.getElementById('test-country').selectedIndex].text;
                const stateName = document.getElementById('test-state').options[document.getElementById('test-state').selectedIndex].text;
                
                document.getElementById('selected-country-name').textContent = countryName;
                document.getElementById('selected-state-name').textContent = stateName;
                document.getElementById('selected-city-name').textContent = cityName;
                document.getElementById('full-location-path').textContent = `${cityName}, ${stateName}, ${countryName}`;
                
                document.getElementById('test-results').classList.remove('hidden');
            } else {
                // Hide results
                document.getElementById('test-results').classList.add('hidden');
                cityStatus.textContent = 'Waiting for city selection';
                cityStatus.className = 'mt-2 text-sm text-gray-500';
            }
        });
    </script>
</body>
</html> 