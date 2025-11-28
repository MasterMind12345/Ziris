<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer les absences du jour
$absences_du_jour = getAbsencesDuJour();

// Récupérer les statistiques d'absences
$stats_absences = getStatsAbsences();

// Récupérer les employés les plus ponctuels
$employes_ponctuels = getEmployesPonctuels();

// Récupérer les données pour le calendrier (toutes les présences/absences)
$presences_calendrier = getPresencesPourCalendrier();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Absences - Batobaye Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- FullCalendar CSS -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#4361ee"/>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Batobaye">
    <link rel="apple-touch-icon" href="icons/icon-152x152.png">
    <link rel="manifest" href="/manifest.json">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Gestion des Absences</h1>
            <p>Surveillez et analysez les absences des employés</p>
        </div>
        
        <!-- Statistiques des absences -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon absent">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats_absences['absences_aujourdhui']; ?></h3>
                    <p>Absences aujourd'hui</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon late">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats_absences['absences_semaine']; ?></h3>
                    <p>Absences cette semaine</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon present">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats_absences['taux_absence']; ?>%</h3>
                    <p>Taux d'absence mensuel</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--secondary);">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats_absences['retard_moyen_heures']; ?>h</h3>
                    <p>Retard moyen</p>
                </div>
            </div>
        </div>
        
        <div class="content-grid">
            <!-- Section des absences du jour -->
            <div class="table-container">
                <div class="table-header">
                    <h2>Absences du jour (<?php echo date('d/m/Y'); ?>)</h2>
                    <div class="table-actions">
                        <input type="text" class="form-control table-search" placeholder="Rechercher..." id="searchAbsences">
                        <button class="btn btn-primary" onclick="exportToCSV('absencesTable', 'absences-batobaye.csv')">
                            <i class="fas fa-download"></i> Exporter
                        </button>
                    </div>
                </div>
                
                <?php if (count($absences_du_jour) > 0): ?>
                <table id="absencesTable">
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Poste</th>
                            <th>Email</th>
                            <th>Dernière présence</th>
                            <th>Retard moyen</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($absences_du_jour as $absence): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="avatar-small" style="background: <?php echo getRandomColor($absence['id']); ?>">
                                        <?php echo getInitials($absence['nom']); ?>
                                    </div>
                                    <?php echo htmlspecialchars($absence['nom']); ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($absence['poste'] ?? 'Non défini'); ?></td>
                            <td><?php echo htmlspecialchars($absence['email']); ?></td>
                            <td>
                                <?php if ($absence['derniere_presence']): ?>
                                    <?php echo date('d/m/Y', strtotime($absence['derniere_presence'])); ?>
                                <?php else: ?>
                                    <span class="badge badge-warning">Jamais</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($absence['retard_moyen_heures'] > 0): ?>
                                    <span class="badge badge-warning"><?php echo $absence['retard_moyen_heures']; ?>h</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Ponctuel</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon" title="Voir profil" onclick="voirProfil(<?php echo $absence['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon" title="Envoyer rappel" onclick="envoyerRappel(<?php echo $absence['id']; ?>)">
                                        <i class="fas fa-bell"></i>
                                    </button>
                                    <button class="btn-icon" title="Historique" onclick="voirHistorique(<?php echo $absence['id']; ?>)">
                                        <i class="fas fa-history"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h3>Aucune absence aujourd'hui</h3>
                    <p>Tous les employés sont présents</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Section des employés ponctuels -->
            <div class="table-container">
                <div class="table-header">
                    <h2>Top 5 des employés ponctuels</h2>
                    <div class="table-actions">
                        <select class="form-control" id="periodePonctualite" onchange="changerPeriodePonctualite(this.value)">
                            <option value="mois">Ce mois</option>
                            <option value="semaine">Cette semaine</option>
                            <option value="trimestre">Ce trimestre</option>
                        </select>
                    </div>
                </div>
                
                <?php if (count($employes_ponctuels) > 0): ?>
                <div class="ranking-list">
                    <?php $rank = 1; ?>
                    <?php foreach ($employes_ponctuels as $employe): ?>
                    <div class="ranking-item">
                        <div class="rank-number"><?php echo $rank; ?></div>
                        <div class="user-info">
                            <div class="avatar-small" style="background: <?php echo getRandomColor($employe['id']); ?>">
                                <?php echo getInitials($employe['nom']); ?>
                            </div>
                            <div class="user-details">
                                <div class="user-name"><?php echo htmlspecialchars($employe['nom']); ?></div>
                                <div class="user-poste"><?php echo htmlspecialchars($employe['poste'] ?? 'Non défini'); ?></div>
                            </div>
                        </div>
                        <div class="ranking-stats">
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $employe['taux_presence']; ?>%</span>
                                <span class="stat-label">Présence</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $employe['retard_moyen_heures']; ?>h</span>
                                <span class="stat-label">Retard moyen</span>
                            </div>
                        </div>
                    </div>
                    <?php $rank++; ?>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <h3>Aucune donnée de ponctualité</h3>
                    <p>Les statistiques seront disponibles après quelques jours</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Calendrier des présences/absences -->
        <div class="table-container" style="margin-top: 30px;">
            <div class="table-header">
                <h2>Calendrier des Présences et Absences</h2>
                <div class="table-actions">
                    <select class="form-control" id="selectEmploye" onchange="changerEmployeCalendrier(this.value)">
                        <option value="tous">Tous les employés</option>
                        <?php 
                        $employes = getAllUsers();
                        foreach ($employes as $employe): 
                        ?>
                        <option value="<?php echo $employe['id']; ?>"><?php echo htmlspecialchars($employe['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div id="calendar-container">
                <div id="calendar"></div>
            </div>
        </div>
        
        <!-- Statistiques détaillées -->
        <div class="content-grid" style="margin-top: 30px;">
            <!-- Graphique des tendances d'absences -->
            <div class="chart-container">
                <h2>Tendances des Absences (30 derniers jours)</h2>
                <canvas id="tendanceAbsencesChart"></canvas>
            </div>
            
            <!-- Graphique des motifs d'absence -->
            <div class="chart-container">
                <h2>Répartition des Absences</h2>
                <canvas id="repartitionAbsencesChart"></canvas>
            </div>
        </div>
        
        <!-- Graphique des retards par département -->
        <div class="table-container" style="margin-top: 30px;">
            <div class="table-header">
                <h2>Retards par Département</h2>
            </div>
            <div class="chart-container">
                <canvas id="retardsDepartementChart"></canvas>
            </div>
        </div>
    </main>
    
    <!-- FullCalendar JS -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/fr.js'></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script src="js/script.js"></script>
    <script>
    // Initialiser le calendrier
    document.addEventListener('DOMContentLoaded', function() {
        // Configuration du calendrier
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'fr',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: <?php echo json_encode($presences_calendrier); ?>,
            eventClick: function(info) {
                // Afficher les détails de l'événement
                afficherDetailsEvenement(info.event);
            },
            eventContent: function(arg) {
                // Personnaliser l'affichage des événements
                let element = document.createElement('div');
                element.className = 'fc-event-content';
                element.innerHTML = `
                    <div class="event-type-${arg.event.extendedProps.type}">
                        <i class="${arg.event.extendedProps.icon}"></i>
                        ${arg.event.title}
                    </div>
                `;
                return { domNodes: [element] };
            }
        });
        calendar.render();
        
        // Initialiser les graphiques
        initialiserGraphiques();
        
        // Recherche en temps réel dans le tableau des absences
        document.getElementById('searchAbsences').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#absencesTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    });
    
    // Fonction pour afficher les détails d'un événement du calendrier
    function afficherDetailsEvenement(event) {
        const type = event.extendedProps.type;
        const employe = event.title;
        const date = event.start.toLocaleDateString('fr-FR');
        const heure = event.extendedProps.heure || '';
        const retard = event.extendedProps.retard || 0;
        
        let message = '';
        let icon = '';
        
        if (type === 'presence') {
            message = `${employe} était présent le ${date}`;
            if (heure) {
                message += ` à ${heure}`;
            }
            icon = 'success';
        } else if (type === 'absence') {
            message = `${employe} était absent le ${date}`;
            icon = 'error';
        } else if (type === 'retard') {
            const heuresRetard = Math.floor(retard / 60);
            const minutesRetard = retard % 60;
            const retardFormate = heuresRetard > 0 ? 
                `${heuresRetard}h${minutesRetard.toString().padStart(2, '0')}min` : 
                `${minutesRetard}min`;
                
            message = `${employe} était en retard le ${date} (${retardFormate})`;
            icon = 'warning';
        }
        
        // Afficher une alerte (vous pouvez remplacer par une modale plus élaborée)
        showAlert(message, icon);
    }
    
    // Fonction pour changer l'employé affiché dans le calendrier
    function changerEmployeCalendrier(employeId) {
        // Recharger les données du calendrier pour cet employé
        // Cette fonction nécessiterait une implémentation AJAX
        console.log('Changement d\'employé:', employeId);
        // location.href = `absences.php?employe=${employeId}`;
    }
    
    // Fonction pour changer la période des statistiques de ponctualité
    function changerPeriodePonctualite(periode) {
        // Recharger les données de ponctualité
        // Cette fonction nécessiterait une implémentation AJAX
        console.log('Changement de période:', periode);
        // location.href = `absences.php?ponctualite=${periode}`;
    }
    
    // Fonction pour initialiser les graphiques
    function initialiserGraphiques() {
        // Graphique de tendance des absences (ligne)
        const ctx1 = document.getElementById('tendanceAbsencesChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: ['J-30', 'J-25', 'J-20', 'J-15', 'J-10', 'J-5', 'Aujourd\'hui'],
                datasets: [{
                    label: 'Nombre d\'absences',
                    data: [8, 12, 6, 15, 9, 11, <?php echo $stats_absences['absences_aujourdhui']; ?>],
                    borderColor: 'rgba(231, 57, 70, 1)',
                    backgroundColor: 'rgba(231, 57, 70, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgba(231, 57, 70, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        // Graphique de répartition des absences (doughnut)
        const ctx2 = document.getElementById('repartitionAbsencesChart').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Maladie', 'Congés', 'Formation', 'Personnel', 'Autre'],
                datasets: [{
                    data: [45, 25, 15, 10, 5],
                    backgroundColor: [
                        'rgba(231, 57, 70, 0.8)',
                        'rgba(67, 97, 238, 0.8)',
                        'rgba(76, 201, 240, 0.8)',
                        'rgba(247, 37, 133, 0.8)',
                        'rgba(108, 117, 125, 0.8)'
                    ],
                    borderColor: [
                        'rgba(231, 57, 70, 1)',
                        'rgba(67, 97, 238, 1)',
                        'rgba(76, 201, 240, 1)',
                        'rgba(247, 37, 133, 1)',
                        'rgba(108, 117, 125, 1)'
                    ],
                    borderWidth: 2,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    }
                }
            }
        });
        
        // Graphique des retards par département (barres horizontales)
        const ctx3 = document.getElementById('retardsDepartementChart').getContext('2d');
        new Chart(ctx3, {
            type: 'bar',
            data: {
                labels: ['Commercial', 'Développement', 'Design', 'RH', 'Administration'],
                datasets: [{
                    label: 'Retard moyen (heures)',
                    data: [1.2, 0.8, 0.5, 0.3, 0.1],
                    backgroundColor: [
                        'rgba(247, 37, 133, 0.8)',
                        'rgba(67, 97, 238, 0.8)',
                        'rgba(76, 201, 240, 0.8)',
                        'rgba(42, 157, 143, 0.8)',
                        'rgba(233, 196, 106, 0.8)'
                    ],
                    borderColor: [
                        'rgba(247, 37, 133, 1)',
                        'rgba(67, 97, 238, 1)',
                        'rgba(76, 201, 240, 1)',
                        'rgba(42, 157, 143, 1)',
                        'rgba(233, 196, 106, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Heures de retard moyen'
                        }
                    }
                }
            }
        });
    }
    
    // Fonction pour voir le profil d'un employé
    function voirProfil(employeId) {
        // Rediriger vers la page de profil de l'employé
        window.location.href = `profile_employe.php?id=${employeId}`;
    }
    
    // Fonction pour voir l'historique d'un employé
    function voirHistorique(employeId) {
        // Rediriger vers la page d'historique de l'employé
        window.location.href = `historique_employe.php?id=${employeId}`;
    }
    
    // Fonction pour envoyer un rappel à un employé absent
    function envoyerRappel(employeId) {
        if (confirm('Voulez-vous envoyer un rappel à cet employé ?')) {
            // Envoyer le rappel via AJAX
            fetch('ajax/envoyer_rappel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `employe_id=${employeId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Rappel envoyé avec succès', 'success');
                } else {
                    showAlert('Erreur lors de l\'envoi du rappel', 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showAlert('Erreur lors de l\'envoi du rappel', 'error');
            });
        }
    }
    
    // Fonction utilitaire pour afficher des alertes
    function showAlert(message, type) {
        const alertClass = type === 'success' ? 'alert-success' : 
                          type === 'error' ? 'alert-error' : 
                          type === 'warning' ? 'alert-warning' : 'alert-info';
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert ${alertClass}`;
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 
                             type === 'error' ? 'exclamation-circle' : 
                             type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
            ${message}
        `;
        
        document.querySelector('.main-content').insertBefore(alertDiv, document.querySelector('.page-header').nextSibling);
        
        // Supprimer l'alerte après 5 secondes
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
    </script>
    
    <style>
    /* Styles spécifiques à la page absences */
    .user-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .avatar-small {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 12px;
    }
    
    .action-buttons {
        display: flex;
        gap: 5px;
    }
    
    .btn-icon {
        width: 32px;
        height: 32px;
        border-radius: var(--border-radius);
        border: none;
        background: var(--gray-light);
        color: var(--dark);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: var(--transition);
    }
    
    .btn-icon:hover {
        background: var(--primary);
        color: white;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--gray);
    }
    
    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
        color: var(--success);
    }
    
    .empty-state h3 {
        margin-bottom: 10px;
        color: var(--dark);
    }
    
    .ranking-list {
        padding: 0;
    }
    
    .ranking-item {
        display: flex;
        align-items: center;
        padding: 15px;
        border-bottom: 1px solid var(--gray-light);
        transition: var(--transition);
    }
    
    .ranking-item:hover {
        background: #f8f9fa;
    }
    
    .ranking-item:last-child {
        border-bottom: none;
    }
    
    .rank-number {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: var(--primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-right: 15px;
    }
    
    .ranking-item:nth-child(1) .rank-number {
        background: gold;
        color: #333;
    }
    
    .ranking-item:nth-child(2) .rank-number {
        background: silver;
        color: #333;
    }
    
    .ranking-item:nth-child(3) .rank-number {
        background: #cd7f32; /* bronze */
        color: white;
    }
    
    .user-details {
        flex: 1;
    }
    
    .user-name {
        font-weight: 500;
        margin-bottom: 3px;
    }
    
    .user-poste {
        font-size: 12px;
        color: var(--gray);
    }
    
    .ranking-stats {
        display: flex;
        gap: 15px;
    }
    
    .stat-item {
        text-align: center;
    }
    
    .stat-value {
        display: block;
        font-weight: bold;
        font-size: 16px;
    }
    
    .stat-label {
        display: block;
        font-size: 11px;
        color: var(--gray);
        text-transform: uppercase;
    }
    
    /* Styles pour le calendrier */
    #calendar-container {
        padding: 20px;
    }
    
    #calendar {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .fc-event {
        border: none;
        padding: 2px 5px;
    }
    
    .fc-event-content {
        font-size: 12px;
        font-weight: 500;
    }
    
    .event-type-presence {
        color: #155724;
        background: #d4edda;
        border-radius: 4px;
        padding: 2px 5px;
    }
    
    .event-type-absence {
        color: #721c24;
        background: #f8d7da;
        border-radius: 4px;
        padding: 2px 5px;
    }
    
    .event-type-retard {
        color: #856404;
        background: #fff3cd;
        border-radius: 4px;
        padding: 2px 5px;
    }
    
    /* Styles pour les graphiques */
    .chart-container {
        position: relative;
        height: 300px;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .ranking-stats {
            flex-direction: column;
            gap: 5px;
        }
        
        .stat-item {
            text-align: left;
        }
        
        .action-buttons {
            flex-direction: column;
        }
        
        .chart-container {
            height: 250px;
        }
    }
    </style>
</body>
</html>