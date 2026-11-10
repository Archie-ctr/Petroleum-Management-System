<?php
// fix_tanks_table.php - Run this once to fix the tanks table
require_once 'includes/config.php';

echo "<h1>Fixing Tanks Table</h1>";
echo "<pre>";

// Check current structure
echo "Checking current table structure...\n";
$result = $conn->query("DESCRIBE tanks");
if (!$result) {
    echo "ERROR: Tanks table doesn't exist. Creating it...\n";
    
    $sql = "CREATE TABLE tanks (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        branch_id INT(11) NOT NULL,
        fuel_type_id INT(11) NOT NULL,
        tank_number VARCHAR(20) NOT NULL,
        capacity DECIMAL(10,2) NOT NULL,
        current_quantity DECIMAL(10,2) DEFAULT 0.00,
        minimum_threshold DECIMAL(10,2) DEFAULT 100.00,
        status ENUM('active','maintenance','inactive') DEFAULT 'active',
        last_refill DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_branch (branch_id),
        INDEX idx_fuel_type (fuel_type_id),
        INDEX idx_status (status),
        UNIQUE KEY unique_tank_number (branch_id, tank_number),
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
        FOREIGN KEY (fuel_type_id) REFERENCES fuel_types(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($sql)) {
        echo "SUCCESS: Tanks table created.\n";
    } else {
        echo "ERROR creating table: " . $conn->error . "\n";
    }
} else {
    echo "Tanks table exists. Checking columns...\n";
    
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    $required_columns = [
        'branch_id',
        'fuel_type_id', 
        'tank_number',
        'capacity',
        'current_quantity',
        'minimum_threshold',
        'status',
        'last_refill'
    ];
    
    foreach ($required_columns as $column) {
        if (!in_array($column, $columns)) {
            echo "Missing column: $column\n";
            
            // Add missing column
            $alter_sql = "";
            switch($column) {
                case 'branch_id':
                    $alter_sql = "ALTER TABLE tanks ADD COLUMN branch_id INT(11) NOT NULL AFTER id";
                    break;
                case 'fuel_type_id':
                    $alter_sql = "ALTER TABLE tanks ADD COLUMN fuel_type_id INT(11) NOT NULL AFTER branch_id";
                    break;
                case 'tank_number':
                    $alter_sql = "ALTER TABLE tanks ADD COLUMN tank_number VARCHAR(20) NOT NULL AFTER fuel_type_id";
                    break;
                case 'capacity':
                    $alter_sql = "ALTER TABLE tanks ADD COLUMN capacity DECIMAL(10,2) NOT NULL AFTER tank_number";
                    break;
                case 'current_quantity':
                    $alter_sql = "ALTER TABLE tanks ADD COLUMN current_quantity DECIMAL(10,2) DEFAULT 0.00 AFTER capacity";
                    break;
                case 'minimum_threshold':
                    $alter_sql = "ALTER TABLE tanks ADD COLUMN minimum_threshold DECIMAL(10,2) DEFAULT 100.00 AFTER current_quantity";
                    break;
                case 'status':
                    $alter_sql = "ALTER TABLE tanks ADD COLUMN status ENUM('active','maintenance','inactive') DEFAULT 'active' AFTER minimum_threshold";
                    break;
                case 'last_refill':
                    $alter_sql = "ALTER TABLE tanks ADD COLUMN last_refill DATETIME DEFAULT NULL AFTER status";
                    break;
            }
            
            if ($alter_sql) {
                if ($conn->query($alter_sql)) {
                    echo "SUCCESS: Added column $column\n";
                } else {
                    echo "ERROR adding column $column: " . $conn->error . "\n";
                }
            }
        } else {
            echo "Column $column: OK\n";
        }
    }
}

echo "\nChecking fuel_types table...\n";
$result = $conn->query("DESCRIBE fuel_types");
$has_fuel_name = false;
while ($row = $result->fetch_assoc()) {
    if ($row['Field'] == 'fuel_name') {
        $has_fuel_name = true;
        break;
    }
}

if (!$has_fuel_name) {
    echo "Adding fuel_name column to fuel_types table...\n";
    $conn->query("ALTER TABLE fuel_types ADD COLUMN fuel_name VARCHAR(50) NOT NULL AFTER fuel_code");
    
    // Update existing records
    $conn->query("UPDATE fuel_types SET fuel_name = fuel_code WHERE fuel_name = '' OR fuel_name IS NULL");
    echo "fuel_name column added and populated.\n";
} else {
    echo "fuel_name column: OK\n";
}

echo "\nChecking foreign key constraints...\n";
$result = $conn->query("SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
                        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                        WHERE TABLE_NAME = 'tanks' AND CONSTRAINT_NAME LIKE 'fk_%'");
                        
if ($result->num_rows == 0) {
    echo "No foreign keys found. Adding them...\n";
    
    // Add foreign key for branches
    $conn->query("ALTER TABLE tanks ADD CONSTRAINT fk_tanks_branches 
                  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE");
    
    // Add foreign key for fuel_types  
    $conn->query("ALTER TABLE tanks ADD CONSTRAINT fk_tanks_fuel_types
                  FOREIGN KEY (fuel_type_id) REFERENCES fuel_types(id) ON DELETE CASCADE");
    
    echo "Foreign keys added.\n";
} else {
    echo "Foreign keys exist.\n";
}

echo "\nChecking sample data...\n";
$result = $conn->query("SELECT COUNT(*) as count FROM tanks");
$row = $result->fetch_assoc();
echo "Total tanks in database: " . $row['count'] . "\n";

// Add a test tank if none exist
if ($row['count'] == 0) {
    echo "\nAdding a test tank...\n";
    
    // Get first branch and fuel type
    $branch = $conn->query("SELECT id FROM branches LIMIT 1")->fetch_assoc();
    $fuel = $conn->query("SELECT id FROM fuel_types LIMIT 1")->fetch_assoc();
    
    if ($branch && $fuel) {
        $sql = "INSERT INTO tanks (branch_id, fuel_type_id, tank_number, capacity, 
                                   current_quantity, minimum_threshold, status, last_refill) 
                VALUES (?, ?, 'TEST-TANK-001', 1000.00, 500.00, 100.00, 'active', NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $branch['id'], $fuel['id']);
        
        if ($stmt->execute()) {
            echo "SUCCESS: Test tank added with ID: " . $stmt->insert_id . "\n";
        } else {
            echo "ERROR adding test tank: " . $conn->error . "\n";
        }
    } else {
        echo "ERROR: No branches or fuel types found to create test tank.\n";
    }
}

echo "\nTest inserting a tank directly...\n";
$test_sql = "INSERT INTO tanks (branch_id, fuel_type_id, tank_number, capacity, 
                               current_quantity, minimum_threshold, status, last_refill) 
            VALUES (1, 1, 'DEBUG-TEST-001', 2000.00, 1000.00, 200.00, 'active', NOW())";
            
if ($conn->query($test_sql)) {
    echo "SUCCESS: Direct SQL insert worked.\n";
    echo "Last inserted ID: " . $conn->insert_id . "\n";
    
    // Clean up test record
    $conn->query("DELETE FROM tanks WHERE tank_number = 'DEBUG-TEST-001'");
    echo "Test record cleaned up.\n";
} else {
    echo "ERROR in direct SQL insert: " . $conn->error . "\n";
}

echo "</pre>";
echo "<h2>Summary</h2>";
echo "<p>The tanks table should now be ready. Try adding a tank through the form again.</p>";
echo "<a href='tanks.php' class='btn btn-primary'>Go Back to Tanks Page</a>";
?>