<?php
header('Content-Type: application/json');
include 'db.php'; // must set $conn = new mysqli(...);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit;
}

// Use isset() instead of null-coalescing (??) for compatibility
$department   = isset($data['department'])   ? $data['department']   : '';
$course       = isset($data['course'])       ? $data['course']       : '';
$durationType = isset($data['durationType']) ? $data['durationType'] : '';
$start        = isset($data['start'])        ? $data['start']        : '';
$end          = isset($data['end'])          ? $data['end']          : '';
$month        = isset($data['month'])        ? intval($data['month']) : 0;
$year         = isset($data['year'])         ? intval($data['year'])  : 0;
$semester     = isset($data['semester'])     ? $data['semester']     : '';

if ($department === '') {
    echo json_encode(['status' => 'error', 'message' => 'Department is required']);
    exit;
}

// Base query
$query = "SELECT s.school_id, s.name, s.course, COUNT(v.id) AS visits
          FROM students s
          LEFT JOIN library_visits v ON s.school_id = v.student_id
          WHERE s.department = ?";
$params = [$department];
$types  = "s";

// Course filter
if ($course !== '' && $course !== 'All Courses') {
    $query .= " AND s.course = ?";
    $params[] = $course;
    $types   .= "s";
}

// Duration filters
if ($durationType === 'day' || $durationType === 'custom') {
    if ($start !== '' && $end !== '') {
        $query .= " AND DATE(v.visit_time) BETWEEN ? AND ?";
        $params[] = $start;
        $params[] = $end;
        $types  .= "ss";
    }
} elseif ($durationType === 'month') {
    if ($month > 0 && $year > 0) {
        $query .= " AND MONTH(v.visit_time) = ? AND YEAR(v.visit_time) = ?";
        $params[] = $month;
        $params[] = $year;
        $types  .= "ii";
    }
} elseif ($durationType === 'semester') {
    if (!empty($semester) && $year > 0) {
        $sem = strtolower($semester);
        if (strpos($sem, '1') !== false || stripos($sem, 'first') !== false) {
            $query .= " AND v.visit_time BETWEEN ? AND ?";
            $params[] = "$year-06-01";
            $params[] = "$year-10-31";
            $types  .= "ss";
        } elseif (strpos($sem, '2') !== false || stripos($sem, 'second') !== false) {
            $query .= " AND v.visit_time BETWEEN ? AND ?";
            $params[] = "$year-11-01";
            $params[] = ($year + 1) . "-03-31";
            $types  .= "ss";
        } elseif (stripos($sem, 'summer') !== false) {
            $query .= " AND v.visit_time BETWEEN ? AND ?";
            $params[] = "$year-04-01";
            $params[] = "$year-05-31";
            $types  .= "ss";
        }
    }
}

// Group & order
$query .= " GROUP BY s.school_id, s.name, s.course ORDER BY visits DESC";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    echo json_encode(['status' => 'error', 'message' => 'SQL prepare error: ' . $conn->error]);
    exit;
}

// Bind parameters safely (call_user_func_array requires references)
if (count($params) > 0) {
    $bindParams = [];
    $bindParams[] = & $types;
    // create references to each param
    for ($i = 0; $i < count($params); $i++) {
        $bindParams[] = & $params[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
}

if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Execute failed: ' . $stmt->error]);
    $stmt->close();
    exit;
}

$rows = [];

// If get_result() is available (mysqlnd) use it; otherwise fallback to bind_result
if (method_exists($stmt, 'get_result')) {
    $result = $stmt->get_result();
    while ($r = $result->fetch_assoc()) {
        $rows[] = $r;
    }
} else {
    // Fallback: result_metadata + bind_result
    $meta = $stmt->result_metadata();
    if ($meta) {
        $fields = [];
        while ($f = $meta->fetch_field()) {
            $fields[] = $f->name;
        }
        $meta->free();

        $rowRefs = [];
        $rowOut  = [];
        foreach ($fields as $field) {
            $rowOut[$field] = null;
            $rowRefs[] = & $rowOut[$field];
        }

        call_user_func_array([$stmt, 'bind_result'], $rowRefs);
        while ($stmt->fetch()) {
            $entry = [];
            foreach ($rowOut as $k => $v) $entry[$k] = $v;
            $rows[] = $entry;
        }
    }
}

$stmt->close();

echo json_encode(['status' => 'success', 'data' => $rows]);
