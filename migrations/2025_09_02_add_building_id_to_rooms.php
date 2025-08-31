<?php
/**
 * Migration: Add building_id to rooms and populate from buildings table
 * Run from project root: php migrations/2025_09_02_add_building_id_to_rooms.php
 */
require __DIR__ . '/../connect.php';

echo "Starting migration: add building_id to rooms...\n";

// Check if column already exists
$res = $conn->query("SHOW COLUMNS FROM rooms LIKE 'building_id'");
if ($res && $res->num_rows > 0) {
    echo "Column building_id already exists — nothing to do.\n";
    exit(0);
}

// Begin transaction
$conn->begin_transaction();
try {
    // Add nullable building_id column
    echo "Adding column building_id...\n";
    if (!$conn->query("ALTER TABLE rooms ADD COLUMN building_id INT DEFAULT NULL")) {
        throw new Exception('ALTER TABLE add column failed: ' . $conn->error);
    }

    // If rooms has a textual 'building' column, try to resolve building_id by name
    $hasBuildingCol = false;
    $bres = $conn->query("SHOW COLUMNS FROM rooms LIKE 'building'");
    if ($bres && $bres->num_rows > 0) $hasBuildingCol = true;

    if ($hasBuildingCol) {
        echo "Populating building_id from rooms.building (joining buildings.name)...\n";
        $updateSql = "UPDATE rooms r JOIN buildings b ON r.building = b.name SET r.building_id = b.id WHERE r.building_id IS NULL";
        if (!$conn->query($updateSql)) {
            throw new Exception('Failed populating building_id from building name: ' . $conn->error);
        }
    }

    // Any remaining NULL building_id -> set to a valid default building (first active building)
    $defId = 0;
    $row = $conn->query("SELECT id FROM buildings WHERE is_active = 1 LIMIT 1")->fetch_assoc();
    if ($row && isset($row['id'])) {
        $defId = (int)$row['id'];
    } else {
        // Fallback: try any building
        $row2 = $conn->query("SELECT id FROM buildings LIMIT 1")->fetch_assoc();
        $defId = $row2['id'] ?? 1;
    }

    echo "Setting missing building_id to default building id {$defId}...\n";
    if (!$conn->query("UPDATE rooms SET building_id = {$defId} WHERE building_id IS NULL")) {
        throw new Exception('Failed setting default building_id: ' . $conn->error);
    }

    // Make building_id NOT NULL
    echo "Altering column building_id to NOT NULL...\n";
    if (!$conn->query("ALTER TABLE rooms MODIFY building_id INT NOT NULL")) {
        throw new Exception('Failed to modify building_id to NOT NULL: ' . $conn->error);
    }

    // Add foreign key constraint (if not exists)
    echo "Adding foreign key constraint fk_rooms_building_id...\n";
    // Drop existing constraint with same name if present
    // Note: MySQL will error if constraint exists; attempt to add directly
    $fkSql = "ALTER TABLE rooms ADD CONSTRAINT fk_rooms_building_id FOREIGN KEY (building_id) REFERENCES buildings(id) ON UPDATE CASCADE ON DELETE RESTRICT";
    if (!$conn->query($fkSql)) {
        throw new Exception('Failed to add foreign key constraint: ' . $conn->error);
    }

    $conn->commit();
    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    $conn->rollback();
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
echo "Done.\n";


