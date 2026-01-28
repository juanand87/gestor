<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?><?= APP_NAME ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= CSS_URL ?>/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= APP_URL ?>/dashboard.php">
                <i class="bi bi-folder2-open me-2"></i><?= APP_NAME ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'dashboard' ? 'active' : '' ?>" href="<?= APP_URL ?>/dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    
                    <?php if (Auth::hasPermission('cometidos_ver')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= $currentPage == 'cometidos' ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-file-earmark-text me-1"></i>Cometidos
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/cometidos/">Ver Cometidos</a></li>
                            <?php if (Auth::hasPermission('cometidos_crear')): ?>
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/cometidos/crear.php">Nuevo Cometido</a></li>
                            <?php endif; ?>
                            <?php if (Auth::hasPermission('cometidos_autorizar')): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/cometidos/pendientes.php">Pendientes de Autorización</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (Auth::hasPermission('funcionarios_gestionar')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'funcionarios' ? 'active' : '' ?>" href="<?= APP_URL ?>/funcionarios/">
                            <i class="bi bi-people me-1"></i>Funcionarios
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (Auth::hasPermission('usuarios_gestionar')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'usuarios' ? 'active' : '' ?>" href="<?= APP_URL ?>/usuarios/">
                            <i class="bi bi-person-gear me-1"></i>Usuarios
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <?= e(Session::getUser()['nombre_completo']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text text-muted small"><?= e(Session::getUser()['cargo']) ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/perfil.php"><i class="bi bi-person me-2"></i>Mi Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="container-fluid py-4">
            <?php if (Session::hasFlash()): ?>
                <?php $flash = Session::getFlash(); ?>
                <div class="alert alert-<?= $flash['type'] == 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?= $content ?? '' ?>
        </div>
    </main>
    
    <!-- Footer -->
    <footer class="footer bg-light py-3 mt-auto">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <span class="text-muted">&copy; <?= date('Y') ?> Asociación de Municipios - <?= APP_NAME ?></span>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="text-muted">Versión <?= APP_VERSION ?></span>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom JS -->
    <script src="<?= JS_URL ?>/main.js"></script>
    
    <?= $scripts ?? '' ?>
</body>
</html>
