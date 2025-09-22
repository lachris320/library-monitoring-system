<?php
error_reporting(E_ALL);
ini_set('display_errors',1);

header("Content-Type: application/json");
include "db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $school_id  = isset($_POST['school_id']) ? trim($_POST['school_id']) : '';
    $name       = isset($_POST['name']) ? trim($_POST['name']) : '';
    $course     = isset($_POST['course']) ? trim($_POST['course']) : '';
    $year_level = isset($_POST['year_level']) ? trim($_POST['year_level']) : '';
    $department = isset($_POST['department']) ? trim($_POST['department']) : '';
    $code       = isset($_POST['code']) ? trim($_POST['code']) : '';
    $gender     = isset($_POST['gender']) ? trim($_POST['gender']) : '';
    $status     = isset($_POST['status']) ? trim($_POST['status']) : '';
    $visits     = isset($_POST['visits']) ? intval($_POST['visits']) : 0; // ✅ default 0 if not provided

    // ✅ Require school_id and name
    if (empty($school_id) || empty($name)) {
        echo json_encode(["status" => "error", "message" => "School ID and Name are required."]);
        exit;
    }

    // ✅ Check for duplicates
    $check = $conn->prepare("SELECT id FROM students WHERE school_id = ?");
    $check->bind_param("s", $school_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(["status" => "duplicate", "message" => "Student already exists."]);
        $check->close();
        exit;
    }
    $check->close();

    // ✅ Insert student with visits
    $stmt = $conn->prepare("
        INSERT INTO students 
        (code, school_id, name, course, year_level, department, gender, status, visits, time_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("ssssssssi", 
        $code, $school_id, $name, $course, $year_level, $department, $gender, $status, $visits
    );

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Student registered successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to register student"]);
    }
}
?>
