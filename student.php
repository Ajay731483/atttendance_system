<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'student') header('Location: login.php');
require 'config.php';

// Get student id from session using prepared statement
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$row = $stmt->fetch();
if (!$row) {
    header('Location: login.php');
    exit;
}
$student_id = $row['id'];

// Fetch attendance
// Fetch attendance
$stmt = $pdo->prepare("SELECT a.date, a.status, s.name AS subject FROM attendance a JOIN subjects s ON a.subject_id = s.id WHERE a.student_id = ? ORDER BY a.date DESC");
$stmt->execute([$student_id]);
$attendance = $stmt->fetchAll();

// Fetch marks
$stmt2 = $pdo->prepare("SELECT m.exam_type, m.marks, m.total_marks, s.name AS subject FROM marks m JOIN subjects s ON m.subject_id = s.id WHERE m.student_id = ? ORDER BY m.exam_type");
$stmt2->execute([$student_id]);
$marks = $stmt2->fetchAll();

// ... (existing code above)

// Reports Section
// Summaries
$stmt3 = $pdo->prepare("SELECT s.name AS subject, COUNT(CASE WHEN a.status='present' THEN 1 END) AS present, COUNT(a.id) AS total FROM attendance a JOIN subjects s ON a.subject_id = s.id WHERE a.student_id = ? GROUP BY s.id");
$stmt3->execute([$student_id]);
$attendance_summary = $stmt3->fetchAll();
$stmt4 = $pdo->prepare("SELECT s.name AS subject, AVG(m.marks / m.total_marks * 100) AS avg_percentage FROM marks m JOIN subjects s ON m.subject_id = s.id WHERE m.student_id = ? GROUP BY s.id");
$stmt4->execute([$student_id]);
$marks_summary = $stmt4->fetchAll();

echo "<h3>Attendance Summary by Subject</h3><table><tr><th>Subject</th><th>Present</th><th>Total</th><th>Percentage</th></tr>";
foreach ($attendance_summary as $row) {
    $percentage = $row['total'] > 0 ? round(($row['present'] / $row['total']) * 100, 2) : 0;
    echo "<tr><td>{$row['subject']}</td><td>{$row['present']}</td><td>{$row['total']}</td><td>{$percentage}%</td></tr>";
}
echo "</table>";

echo "<h3>Performance Summary by Subject</h3><table><tr><th>Subject</th><th>Average Percentage</th></tr>";
foreach ($marks_summary as $row) {
    echo "<tr><td>{$row['subject']}</td><td>" . round($row['avg_percentage'], 2) . "%</td></tr>";
}
echo "</table>";

// ... (existing code below)?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Panel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h2>Student Panel</h2>
    <a href="logout.php">Logout</a>

    <h3>Your Attendance</h3>
    <table>
        <tr><th>Date</th><th>Subject</th><th>Status</th></tr>
        <?php foreach ($attendance as $a) echo "<tr><td>{$a['date']}</td><td>{$a['subject']}</td><td>{$a['status']}</td></tr>"; ?>
    </table>

    <h3>Your Academic Performance</h3>
    <table>
        <tr><th>Exam Type</th><th>Subject</th><th>Marks</th><th>Total Marks</th></tr>
        <?php foreach ($marks as $m) echo "<tr><td>{$m['exam_type']}</td><td>{$m['subject']}</td><td>{$m['marks']}</td><td>{$m['total_marks']}</td></tr>"; ?>
    </table>
</body>
</html>