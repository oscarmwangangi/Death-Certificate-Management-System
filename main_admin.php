<?php
session_start();
if ($_SESSION['role'] !== 'main_admin') {
    header("Location: index.php");
    exit();
}

// Database connection
require './log/config.php'; // Ensure you have the correct path to your config file

// Initialize variables
$Name_of_the_filler = '';
$start_date = '';
$end_date = '';
$user_name = '';

// Check if GET parameters are set
if (isset($_GET['Name_of_the_filler'])) {
    $Name_of_the_filler = $_GET['Name_of_the_filler'];
}
if (isset($_GET['start_date'])) {
    $start_date = $_GET['start_date'];
}
if (isset($_GET['end_date'])) {
    $end_date = $_GET['end_date'];
}

// Fetch user name based on session user ID
$user_id = $_SESSION['user_id']; // Ensure this is set when the user logs in
$user_query = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$user_query->execute([$user_id]);
$user_result = $user_query->fetch(PDO::FETCH_ASSOC);
$user_name = $user_result['username'] ?? '';

// Query data for charts
$today = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));

// Fetch data for today's forms and weekly forms using submission_timestamp
$today_query = $pdo->prepare("SELECT COUNT(*) FROM deathcertificate_information WHERE DATE(submission_timestamp) = ?");
$today_query->execute([$today]);
$today_count = $today_query->fetchColumn() ?: 0;

$week_query = $pdo->prepare("SELECT COUNT(*) FROM deathcertificate_information WHERE DATE(submission_timestamp) BETWEEN ? AND ?");
$week_query->execute([$week_start, $week_end]);
$week_count = $week_query->fetchColumn() ?: 0;

// Fetch user data
$user_query = $pdo->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$user_query->execute();
$user_data = $user_query->fetchAll(PDO::FETCH_ASSOC);

// Initialize default counts
$user_counts = [
    'main_admin' => 0,
    'second_admin' => 0,
    'user' => 0
];

// Populate counts from fetched data
foreach ($user_data as $data) {
    if (array_key_exists($data['role'], $user_counts)) {
        $user_counts[$data['role']] = $data['count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="main_admin.css">
    <style>
        .btn-lg {
            font-size: 1.5rem;
        }
        .sidebar {
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #f8f9fa;
            padding: 15px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .content {
            margin-left: 270px;
        }
        .chart-container {
            margin-top: 20px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="sidebar">
        <h4>Main Admin Dashboard</h4>
        <p>Welcome, <?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?>!</p>
        <a href="register.php" class="btn btn-primary w-100 mb-3">Register New User</a>
        <a href="./correction_form.php" class="btn btn-secondary w-100 mb-3">Correction</a>
        <a href="./log/fill.php" class="btn btn-success w-100 mb-3">Data Entry</a>
        <a href="./change_roles.php" class="btn btn-info w-100 mb-3">Change Roles</a>
        <form method="POST" action="logout.php">
            <button type="submit" class="btn btn-danger w-100">Logout</button>
        </form>
    </div>

    <div class="content">
        <h2 class="text-primary mb-4">Dashboard</h2>

        <!-- Search Bar -->
        <div class="container mt-5">
            <form method="GET" action="search_results.php">
                <div class="input-group mb-4">
                    <input type="text" class="form-control" name="Name_of_the_filler" placeholder="Search by Name of Filler" value="<?php echo htmlspecialchars($Name_of_the_filler, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="date" class="form-control" name="start_date" placeholder="Start Date" value="<?php echo htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="date" class="form-control" name="end_date" placeholder="End Date" value="<?php echo htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8'); ?>">
                    <button class="btn btn-primary" type="submit">Search</button>
                </div>
            </form>
        </div>

        <!-- Charts -->
        <div class="container mt-5 chart-container">
            <h3>Data Filled Statistics</h3>
            <div class="row">
                <div class="col-md-6">
                    <canvas id="pieChart"></canvas>
                </div>
                <div class="col-md-6">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Chart.js Scripts -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const ctxPie = document.getElementById('pieChart').getContext('2d');
            const ctxBar = document.getElementById('barChart').getContext('2d');

            // Pie Chart for Data Filled Statistics
            new Chart(ctxPie, {
                type: 'pie',
                data: {
                    labels: ['Today', 'This Week'],
                    datasets: [{
                        label: 'Forms Filled',
                        data: [
                            <?php echo $today_count; ?>,
                            <?php echo $week_count; ?>
                        ],
                        backgroundColor: ['#36a2eb', '#ff6384'],
                    }]
                },
                options: {
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.raw || 0;
                                    return label + ': ' + value;
                                }
                            }
                        }
                    }
                }
            });

            // Bar Chart for User Count
            new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: ['Main Admin', 'Second Admin', 'User'],
                    datasets: [{
                        label: 'User Count',
                        data: [
                            <?php echo $user_counts['main_admin']; ?>,
                            <?php echo $user_counts['second_admin']; ?>,
                            <?php echo $user_counts['user']; ?>
                        ],
                        backgroundColor: ['#4bc0c0', '#ffcc00', '#ff6f61'],
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            suggestedMin: 1
                        }
                    }
                }
            });
        </script>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js" integrity="sha384-BBtl+eGJRgqQAUMxJ7pMwbEyER4l1g+O15P+16Ep7Q9Q+zqX6gSbd85u4mG4QzX+" crossorigin="anonymous"></script>
</body>
</html>
