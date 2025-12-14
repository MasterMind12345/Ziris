<?php
// Déterminer la page active pour la navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h2>Ziris</h2>
        <p style="font-size: 12px; color: var(--gray); margin-top: 5px;">Système de Présence</p>
    </div>
    
    <nav class="sidebar-menu">
        <a href="index.php" class="menu-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Tableau de Bord</span>
        </a>
        
        <a href="users.php" class="menu-item <?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Gestion des Utilisateurs</span>
        </a>

        
        <a href="postes.php" class="menu-item <?php echo $current_page == 'postes.php' ? 'active' : ''; ?>">
            <i class="fas fa-briefcase"></i>
            <span>Gestion des Postes</span>
        </a>
        
        <a href="presences.php" class="menu-item <?php echo $current_page == 'presences.php' ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-check"></i>
            <span>Suivi des Présences</span>
        </a>

      <a href="theme.php" class="menu-item <?php echo $current_page == 'theme.php' ? 'active' : ''; ?>">
            <i class="fas fa-paint-brush"></i>
            <span>Personnaliser le Thème</span>
        </a>
        
        <!-- NOUVEAU : Gestion des Absences -->
        <a href="absences.php" class="menu-item <?php echo $current_page == 'absences.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-times"></i>
            <span>Gestion des Absences</span>
        </a>
        
        <a href="qr_code.php" class="menu-item <?php echo $current_page == 'qr_code.php' ? 'active' : ''; ?>">
            <i class="fas fa-qrcode"></i>
            <span>QR Code</span>
        </a>
        
        <a href="parametres.php" class="menu-item <?php echo $current_page == 'parametres.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Paramètres</span>
        </a>
        
        <a href="logs.php" class="menu-item <?php echo $current_page == 'logs.php' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i>
            <span>Historique</span>
        </a>
        <a href="salaire.php" class="menu-item <?php echo $current_page == 'salaire.php' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i>
            <span>salaire</span>
        </a>
        <a href="fiche.php" class="menu-item <?php echo $current_page == 'fiche.php' ? 'active' : ''; ?>">
             <i class="fas fa-file-invoice"></i>
            <span>Fiches de Paie</span>
        </a>
        <a href="profile.php" class="menu-item <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-cog"></i>
            <span>Profil Admin</span>
        </a>
        
        <div class="menu-item" style="margin-top: 20px; border-top: 1px solid var(--gray-light); padding-top: 20px;">
            <i class="fas fa-sign-out-alt"></i>
            <a href="../logout.php" style="color: inherit; text-decoration: none;">
                <span>Déconnexion</span>
            </a>
        </div>
    </nav>
</div>
<style>
    .sidebar {
        background-color: var(--bg-card);
        color: var(--text-primary);
    }
    
    .sidebar-header h2 {
        color: var(--text-primary);
    }
    
    .sidebar-menu .menu-item {
        color: var(--text-secondary);
    }
    
    .sidebar-menu .menu-item:hover,
    .sidebar-menu .menu-item.active {
        color: var(--primary);
        background-color: var(--bg-secondary);
    }
</style>