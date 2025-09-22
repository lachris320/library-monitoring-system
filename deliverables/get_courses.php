<?php
header("Content-Type: application/json");
include "db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $department = isset($_POST['department']) ? trim($_POST['department']) : '';

    if (empty($department)) {
        echo json_encode(["status" => "error", "message" => "Department not specified"]);
        exit;
    }

    // ✅ Use DISTINCT to make sure only unique course names are returned
    $stmt = $conn->prepare("SELECT DISTINCT course FROM students WHERE department = ? AND course IS NOT NULL AND course != '' ORDER BY course ASC");
    $stmt->bind_param("s", $department);
    $stmt->execute();
    $result = $stmt->get_result();

    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row['course'];
    }

    echo json_encode([
        "status" => "success",
        "courses" => $courses
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
}
?>