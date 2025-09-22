<?php
error_reporting(E_ALL);
ini_set('display_errors',1);

header("Content-Type: application/json");
include "db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $school_id = isset($_POST['school_id']) ? $_POST['school_id'] : '';

    $stmt = $conn->prepare("SELECT * FROM students WHERE school_id = ? LIMIT 1");
    $stmt->bind_param("s", $school_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($student = $result->fetch_assoc()) {
        // ✅ Insert login event into library_visits
        $logStmt = $conn->prepare("INSERT INTO library_visits (student_id) VALUES (?)");
        $logStmt->bind_param("s", $school_id);
        $logStmt->execute();
        $logStmt->close();

        // ✅ Increment visits counter in students table
        $updateStmt = $conn->prepare("UPDATE students SET visits = visits + 1 WHERE school_id = ?");
        $updateStmt->bind_param("s", $school_id);
        $updateStmt->execute();
        $updateStmt->close();

        echo json_encode([
            "status" => "success",
            "student" => $student
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid School ID"]);
    }

    $stmt->close();
    $conn->close();
}
?>
