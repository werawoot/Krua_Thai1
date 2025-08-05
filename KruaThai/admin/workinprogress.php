<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Somdul Table - Admin Dashboard</title>
    <link href="https://ydpschool.com/fonts/" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://ydpschool.com/fonts/');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'BaticaSans', Arial, sans-serif;
            background: #ece8e1;
            color: #333;
        }

        /* Main Content Area */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .wip-container {
            background: white;
            border-radius: 15px;
            padding: 60px 80px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(189, 147, 121, 0.15);
            border: 2px solid #bd9379;
            max-width: 600px;
            width: 100%;
        }
        
        .wip-title {
            font-size: 2.5rem;
            color: #bd9379;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .wip-icon {
            font-size: 4rem;
            color: #adb89d;
            margin-bottom: 30px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.1); opacity: 1; }
            100% { transform: scale(1); opacity: 0.8; }
        }
        
        .admin-badge {
            background: #cf723a;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: bold;
            margin-top: 30px;
            display: inline-block;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .wip-container {
                padding: 40px 30px;
                margin: 20px;
            }
            
            .wip-title {
                font-size: 2rem;
            }
            
            .wip-icon {
                font-size: 3rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="wip-container">
            <div class="wip-icon">⚙️</div>
            <h1 class="wip-title">Work in Progress</h1>
            <div class="admin-badge">Admin Dashboard</div>
        </div>
    </div>
</body>
</html>