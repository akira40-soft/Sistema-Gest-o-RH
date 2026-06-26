<?php
/**
 * Helper para carregar includes do SG
 */
function rg_header($title = 'Dashboard', $subtitle = '', $bodyClass = 'dashboard-body') {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> | SG Farmácia Gingongo</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="css/style-2026.css">
    </head>
    <body class="<?php echo $bodyClass; ?>">
    <?php
}

function rg_dashboard_header($title = 'Dashboard', $subtitle = '') {
    $user = $_SESSION['username'] ?? 'Usuário';
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> | SG Farmácia Gingongo</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="css/style-2026.css">
    </head>
    <body class="dashboard-body">
        <div class="app-wrapper">
            <?php include __DIR__ . '/sidebar.php'; ?>
            <div class="main-area" id="mainArea">
                <?php include __DIR__ . '/topbar.php'; ?>
                <div class="content-body">
    <?php
}

function rg_dashboard_footer() {
    ?>
                </div><!-- content-body -->
            </div><!-- main-area -->
        </div><!-- app-wrapper -->
        <script src="js/app-2026.js"></script>
    </body>
    </html>
    <?php
}
