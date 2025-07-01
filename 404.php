<?php
http_response_code(404);
require_once 'config/config.php'; // For BASE_URL
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/images/XDL-ICON.png">
    <style>
        body {
            margin: 0;
            background: #fff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .fullpage-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            position: relative;
            text-align: center;
            padding: 2rem;
            overflow: hidden;
        }
        .background-404 {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 25rem;
            font-weight: 900;
            color: #f0f2f5;
            z-index: 1;
            user-select: none;
            pointer-events: none;
        }
        .content-404 {
            position: relative;
            z-index: 2;
        }
        .content-404 h2 {
            font-size: 2.5rem;
            color: var(--xobo-primary);
            margin: 1rem 0;
            font-weight: 600;
        }
        .content-404 p {
            color: var(--xobo-gray);
            font-size: 1.1rem;
            margin-bottom: 2.5rem;
            max-width: 450px;
            margin-left: auto;
            margin-right: auto;
        }
        .content-404 .btn-home {
            display: inline-block;
            padding: 12px 28px;
            background: var(--xobo-primary);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
            border: none;
        }
        .content-404 .btn-home:hover {
            background: var(--xobo-primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="fullpage-container">
        <div class="background-404">404</div>
        <div class="content-404">
            <h2>Page not found.</h2>
            <p>Page you are trying to open does not exist. You may have mistyped the address, or the page has been moved to another URL.</p>
            <a href="<?php echo BASE_URL; ?>/" class="btn-home">
                Go back to home
            </a>
        </div>
    </div>
</body>
</html> 