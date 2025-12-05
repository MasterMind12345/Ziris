<?php
// Récupérer les informations de l'admin connecté
$admin_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT nom, email FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

// Générer les initiales pour l'avatar
$initials = '';
if ($admin && isset($admin['nom'])) {
    $names = explode(' ', $admin['nom']);
    foreach ($names as $name) {
        $initials .= strtoupper(substr($name, 0, 1));
    }
    $initials = substr($initials, 0, 2);
}
?>
<header class="header">
    <div class="header-left">
        <h1>Ziris Admin</h1>
    </div>
    <div class="header-right">
        <div class="notifications">
            <i class="fas fa-bell"></i>
            <span class="notification-badge">3</span>
        </div>
        <div class="user-profile" id="userMenu">
            <div class="avatar"><?php echo $initials; ?></div>
            <span><?php echo htmlspecialchars($admin['nom'] ?? 'Admin'); ?></span>
            <i class="fas fa-chevron-down"></i>
            
            <div class="user-dropdown" id="userDropdown">
                <a href="profile.php" class="dropdown-item">
                    <i class="fas fa-user"></i> Mon Profil
                </a>
                <a href="logs.php" class="dropdown-item">
                    <i class="fas fa-history"></i> Historique
                </a>
                <div class="dropdown-divider"></div>
                <a href="../logout2.php" class="dropdown-item text-danger">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>
        </div>
    </div>
</header>

<script>
// Gestion du menu utilisateur
document.addEventListener('DOMContentLoaded', function() {
    const userMenu = document.getElementById('userMenu');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userMenu && userDropdown) {
        userMenu.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });
        
        // Fermer le dropdown en cliquant ailleurs
        document.addEventListener('click', function() {
            userDropdown.classList.remove('show');
        });
    }
});
</script>

<style>
.user-profile {
    position: relative;
}

.user-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    min-width: 200px;
    display: none;
    z-index: 1000;
    margin-top: 10px;
}

.user-dropdown.show {
    display: block;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 15px;
    color: var(--dark);
    text-decoration: none;
    transition: var(--transition);
    border-bottom: 1px solid var(--gray-light);
}

.dropdown-item:last-child {
    border-bottom: none;
}

.dropdown-item:hover {
    background: var(--gray-light);
}

.dropdown-item.text-danger {
    color: var(--danger);
}

.dropdown-divider {
    height: 1px;
    background: var(--gray-light);
    margin: 5px 0;
}

.notifications {
    position: relative;
    cursor: pointer;
    padding: 8px;
    border-radius: 50%;
    transition: var(--transition);
}

.notifications:hover {
    background: var(--gray-light);
}

.notification-badge {
    position: absolute;
    top: 0;
    right: 0;
    background: var(--danger);
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>