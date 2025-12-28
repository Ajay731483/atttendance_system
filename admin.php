<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') header('Location: login.php');
require 'config.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user'])) {
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $email = $_POST['email'];
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, email) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $password, $role, $email]);
        if ($role == 'student') {
            $user_id = $pdo->lastInsertId();
            $class_id = $_POST['class_id'];
            $pdo->prepare("INSERT INTO students (user_id, class_id) VALUES (?, ?)")->execute([$user_id, $class_id]);
        } elseif ($role == 'teacher') {
            $user_id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO teachers (user_id) VALUES (?)")->execute([$user_id]);
        }
        $message = "User added.";
    } elseif (isset($_POST['add_class'])) {
        $name = $_POST['class_name'];
        $pdo->prepare("INSERT INTO classes (name) VALUES (?)")->execute([$name]);
        $message = "Class added.";
    } elseif (isset($_POST['add_subject'])) {
        $name = $_POST['subject_name'];
        $pdo->prepare("INSERT INTO subjects (name) VALUES (?)")->execute([$name]);
        $message = "Subject added.";
    } elseif (isset($_POST['assign_subject'])) {
        $teacher_id = $_POST['teacher_id'];
        $subject_id = $_POST['subject_id'];
        $class_id = $_POST['class_id'];
        $pdo->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id, class_id) VALUES (?, ?, ?)")->execute([$teacher_id, $subject_id, $class_id]);
        $message = "Subject assigned.";
    }
}
// ... (existing code above)

// Reports Section
if (isset($_GET['report'])) {
    $report_type = $_GET['report'];
    if ($report_type == 'attendance') {
        $stmt = $pdo->query("SELECT c.name AS class, s.name AS subject, COUNT(CASE WHEN a.status='present' THEN 1 END) AS present, COUNT(a.id) AS total FROM attendance a JOIN students st ON a.student_id = st.id JOIN classes c ON st.class_id = c.id JOIN subjects s ON a.subject_id = s.id GROUP BY c.id, s.id");
        $data = $stmt->fetchAll();
        echo "<h3>Attendance Summary</h3><table><tr><th>Class</th><th>Subject</th><th>Present</th><th>Total</th><th>Percentage</th></tr>";
        foreach ($data as $row) {
            $percentage = $row['total'] > 0 ? round(($row['present'] / $row['total']) * 100, 2) : 0;
            echo "<tr><td>{$row['class']}</td><td>{$row['subject']}</td><td>{$row['present']}</td><td>{$row['total']}</td><td>{$percentage}%</td></tr>";
        }
        echo "</table>";
    } elseif ($report_type == 'performance') {
        $stmt = $pdo->query("SELECT c.name AS class, s.name AS subject, AVG(m.marks / m.total_marks * 100) AS avg_percentage FROM marks m JOIN students st ON m.student_id = st.id JOIN classes c ON st.class_id = c.id JOIN subjects s ON m.subject_id = s.id GROUP BY c.id, s.id");
        $data = $stmt->fetchAll();
        echo "<h3>Performance Summary</h3><table><tr><th>Class</th><th>Subject</th><th>Average Percentage</th></tr>";
        foreach ($data as $row) {
            echo "<tr><td>{$row['class']}</td><td>{$row['subject']}</td><td>" . round($row['avg_percentage'], 2) . "%</td></tr>";
        }
        echo "</table>";
    }
} else {
    echo "<h3>Reports</h3><a href='?report=attendance'>View Attendance Report</a> | <a href='?report=performance'>View Performance Report</a>";
}

// ... (existing code below)
// Fetch data for dropdowns
$classes = $pdo->query("SELECT * FROM classes")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects")->fetchAll();
$teachers = $pdo->query("SELECT t.id, u.username FROM teachers t JOIN users u ON t.user_id = u.id")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h2>Admin Panel</h2>
    <p><?php echo $message; ?></p>
    <a href="logout.php">Logout</a>

    <h3>Add User</h3>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <select name="role" required>
            <option value="teacher">Teacher</option>
            <option value="student">Student</option>
        </select>
        <input type="email" name="email" placeholder="Email" required>
        <select name="class_id" required>
            <?php foreach ($classes as $class) echo "<option value='{$class['id']}'>{$class['name']}</option>"; ?>
        </select>
        <button type="submit" name="add_user">Add User</button>
    </form>

    <h3>Add Class</h3>
    <form method="POST">
        <input type="text" name="class_name" placeholder="Class Name" required>
        <button type="submit" name="add_class">Add Class</button>
    </form>

    <h3>Add Subject</h3>
    <form method="POST">
        <input type="text" name="subject_name" placeholder="Subject Name" required>
        <button type="submit" name="add_subject">Add Subject</button>
    </form>

    <h3>Assign Subject to Teacher</h3>
    <form method="POST">
        <select name="teacher_id" required>
            <?php foreach ($teachers as $teacher) echo "<option value='{$teacher['id']}'>{$teacher['username']}</option>"; ?>
        </select>
        <select name="subject_id" required>
            <?php foreach ($subjects as $subject) echo "<option value='{$subject['id']}'>{$subject['name']}</option>"; ?>
        </select>
        <select name="class_id" required>
            <?php foreach ($classes as $class) echo "<option value='{$class['id']}'>{$class['name']}</option>"; ?>
        </select>
        <button type="submit" name="assign_subject">Assign</button>
    </form>
    
</body>
</html>
