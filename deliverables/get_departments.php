<?php
header("Content-Type: application/json");
include "db.php";

$result = $conn->query("SELECT DISTINCT department FROM students WHERE department <> '' ORDER BY department ASC");

$departments = [];
while ($row = $result->fetch_assoc()) {
    $departments[] = $row['department'];
}

echo json_encode([
    "status" => "success",
    "departments" => $departments
]);
?>
