<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'teacher') header('Location: login.php');
require 'config.php';

$message = '';
// Get teacher id from session (use prepared statement and validate)
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$row = $stmt->fetch();
if (!$row) {
    // no teacher record found for this user
    header('Location: login.php');
    exit;
}
$teacher_id = $row['id'];

// Fetch assigned subjects/classes
$assignments = $pdo->query("SELECT ts.id, s.name AS subject, c.name AS class FROM teacher_subjects ts JOIN subjects s ON ts.subject_id = s.id JOIN classes c ON ts.class_id = c.id WHERE ts.teacher_id = $teacher_id")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_attendance'])) {
        $assignment_id = $_POST['assignment_id'];
        $student_id = $_POST['student_id'];
        $date = $_POST['date'];
        $status = $_POST['status'];
        $stmt = $pdo->prepare("SELECT subject_id FROM teacher_subjects WHERE id = ?");
        $stmt->execute([$assignment_id]);
        $sub = $stmt->fetch();
        $subject_id = $sub ? $sub['subject_id'] : null;
        if ($subject_id) {
            $pdo->prepare("INSERT INTO attendance (student_id, subject_id, date, status) VALUES (?, ?, ?, ?)")->execute([$student_id, $subject_id, $date, $status]);
            $message = "Attendance added.";
        } else {
            $message = "Invalid assignment selected.";
        }
        $message = "Attendance added.";
    } elseif (isset($_POST['add_marks'])) {
        $assignment_id = $_POST['assignment_id'];
        $student_id = $_POST['student_id'];
        $exam_type = $_POST['exam_type'];
        $marks = $_POST['marks'];
        $total_marks = $_POST['total_marks'];
        $stmt = $pdo->prepare("SELECT subject_id FROM teacher_subjects WHERE id = ?");
        $stmt->execute([$assignment_id]);
        $sub = $stmt->fetch();
        $subject_id = $sub ? $sub['subject_id'] : null;
        if ($subject_id) {
            $pdo->prepare("INSERT INTO marks (student_id, subject_id, exam_type, marks, total_marks) VALUES (?, ?, ?, ?, ?)")->execute([$student_id, $subject_id, $exam_type, $marks, $total_marks]);
            $message = "Marks added.";
        } else {
            $message = "Invalid assignment selected.";
        }
    }
}
// ... (existing code above)

// Reports Section
if (isset($_GET['report'])) {
    $report_type = $_GET['report'];
    $assignment_id = isset($_GET['assignment_id']) ? $_GET['assignment_id'] : null;
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

    if ($report_type == 'attendance' && $assignment_id) {
        $stmt = $pdo->prepare("SELECT subject_id FROM teacher_subjects WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$assignment_id, $teacher_id]);
        $sub = $stmt->fetch();
        if ($sub) {
            $subject_id = $sub['subject_id'];
            $query = "SELECT u.username, a.date, a.status FROM attendance a JOIN students st ON a.student_id = st.id JOIN users u ON st.user_id = u.id WHERE a.subject_id = ? AND a.date BETWEEN ? AND ?";
            $stmt2 = $pdo->prepare($query);
            $stmt2->execute([$subject_id, $start_date, $end_date]);
            $data = $stmt2->fetchAll();
            echo "<h3>Attendance Report</h3><table><tr><th>Student</th><th>Date</th><th>Status</th></tr>";
            foreach ($data as $row) echo "<tr><td>{$row['username']}</td><td>{$row['date']}</td><td>{$row['status']}</td></tr>";
            echo "</table>";
        } else {
            echo "<p>Invalid assignment or permission denied.</p>";
        }
        $data = $stmt->fetchAll();
        echo "<h3>Attendance Report</h3><table><tr><th>Student</th><th>Date</th><th>Status</th></tr>";
        foreach ($data as $row) echo "<tr><td>{$row['username']}</td><td>{$row['date']}</td><td>{$row['status']}</td></tr>";
        echo "</table>";
    } elseif ($report_type == 'marks' && $assignment_id) {
        $subject_id = $pdo->query("SELECT subject_id FROM teacher_subjects WHERE id = $assignment_id AND teacher_id = $teacher_id")->fetch()['subject_id'];
        $query = "SELECT u.username, m.exam_type, m.marks, m.total_marks FROM marks m JOIN students st ON m.student_id = st.id JOIN users u ON st.user_id = u.id WHERE m.subject_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$subject_id]);
    $data = $stmt->fetchAll();
        echo "<h3>Marks Report</h3><table><tr><th>Student</th><th>Exam Type</th><th>Marks</th><th>Total</th><th>Percentage</th></tr>";
        foreach ($data as $row) {
            $percentage = $row['total_marks'] > 0 ? round(($row['marks'] / $row['total_marks']) * 100, 2) : 0;
            echo "<tr><td>{$row['username']}</td><td>{$row['exam_type']}</td><td>{$row['marks']}</td><td>{$row['total_marks']}</td><td>{$percentage}%</td></tr>";
        }
        echo "</table>";
    }
} else {
    echo "<h3>Reports</h3>";
    echo "<form method='GET'><select name='assignment_id' required>";
    foreach ($assignments as $a) echo "<option value='{$a['id']}'>{$a['subject']} - {$a['class']}</option>";
    echo "</select><input type='date' name='start_date' required><input type='date' name='end_date' required><button type='submit' name='report' value='attendance'>View Attendance Report</button><button type='submit' name='report' value='marks'>View Marks Report</button></form>";
}

// ... (existing code below)
?>
<!DOCTYPE html>
<html>
<head>
    <title>Teacher Panel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h2>Teacher Panel</h2>
    <p><?php echo $message; ?></p>
    <a href="logout.php">Logout</a>

    <h3>Add Attendance</h3>
    <form method="POST">
        <select name="assignment_id" required>
            <?php foreach ($assignments as $a) echo "<option value='{$a['id']}'>{$a['subject']} - {$a['class']}</option>"; ?>
        </select>
        <select name="student_id" required>
            <!-- Dynamically load students based on class; for simplicity, assume you select class first -->
            <?php $students = $pdo->query("SELECT s.id, u.username FROM students s JOIN users u ON s.user_id = u.id")->fetchAll();
            foreach ($students as $s) echo "<option value='{$s['id']}'>{$s['username']}</option>"; ?>
        </select>
        <input type="date" name="date" required>
        <select name="status" required>
            <option value="present">Present</option>
            <option value="absent">Absent</option>
        </select>
        <button type="submit" name="add_attendance">Add Attendance</button>
    </form>

    <h3>Add Marks</h3>
    <form method="POST">
        <select name="assignment_id" required>
            <?php foreach ($assignments as $a) echo "<option value='{$a['id']}'>{$a['subject']} - {$a['class']}</option>"; ?>
        </select>
        <select name="student_id" required>
            <?php foreach ($students as $s) echo "<option value='{$s['id']}'>{$s['username']}</option>"; ?>
        </select>
        <input type="text" name="exam_type" placeholder="Exam Type" required>
        <input type="number" name="marks" placeholder="Marks" required>
        <input type="number" name="total_marks" placeholder="Total Marks" required>
        <button type="submit" name="add_marks">Add Marks</button>
    </form>
</body>
</html>