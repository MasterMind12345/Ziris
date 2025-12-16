<?php
// Calculer les statistiques
$totalPresences = count($presences);
$onTime = 0;
$late = 0;
$withPause = 0;
$suspectLieux = 0;
$criticalLieux = 0;

foreach ($presences as $presence) {
    if ($presence['retard_minutes'] <= 0) {
        $onTime++;
    } else {
        $late++;
    }
    if (!empty($presence['heure_pause_debut']) && !empty($presence['heure_pause_fin'])) {
        $withPause++;
    }
    
    // Vérifier les lieux suspects
    if (!empty($presence['lieu'])) {
        $result = estLieuAutorise($presence['lieu'], $presence['latitude'], $presence['longitude'], 
                                 $quartiers_autorises, $villes_autorisees, $noms_lieux_autorises, $coordonnees_lieux);
        if (!$result['autorise']) {
            $suspectLieux++;
            // Si le lieu est très éloigné ou complètement inconnu
            if (strpos(strtolower($presence['lieu']), 'douala') === false && 
                strpos(strtolower($presence['lieu']), 'carrières') === false) {
                $criticalLieux++;
            }
        }
    }
}
?>

<!-- Stats Summary amélioré -->
<div class="stats-summary">
    <div class="stat-item fade-in" style="animation-delay: 0.1s;">
        <div class="stat-icon" style="background: linear-gradient(135deg, var(--primary), var(--secondary));">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $totalPresences; ?></h3>
            <p>Présences totales</p>
        </div>
    </div>
    
    <div class="stat-item fade-in" style="animation-delay: 0.2s;">
        <div class="stat-icon" style="background: linear-gradient(135deg, var(--success), #0da271);">
            <i class="fas fa-user-check"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $onTime; ?></h3>
            <p>À l'heure</p>
        </div>
    </div>
    
    <div class="stat-item fade-in" style="animation-delay: 0.3s;">
        <div class="stat-icon" style="background: linear-gradient(135deg, var(--warning), #d97706);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $late; ?></h3>
            <p>En retard</p>
        </div>
    </div>
    
    <div class="stat-item <?php echo $suspectLieux > 0 ? ($criticalLieux > 0 ? 'critical' : '') : ''; ?> fade-in" style="animation-delay: 0.4s;">
        <div class="stat-icon" style="background: linear-gradient(135deg, var(--danger), #dc2626);">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $suspectLieux; ?></h3>
            <p>Anomalies</p>
            <?php if ($criticalLieux > 0): ?>
                <div style="font-size: 12px; color: var(--danger); margin-top: 5px;">
                    <i class="fas fa-radiation"></i> <?php echo $criticalLieux; ?> critique(s)
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Legend améliorée -->
<div class="legend fade-in" style="animation-delay: 0.5s;">
    <div class="legend-item">
        <div class="legend-color" style="background: var(--bg-secondary); border-color: var(--primary);"></div>
        <span>Normal</span>
    </div>
    <div class="legend-item">
        <div class="legend-color" style="background: var(--suspicion-bg); border-color: var(--danger);"></div>
        <span>Suspicion</span>
    </div>
    <div class="legend-item">
        <div class="legend-color" style="background: var(--critical-bg); border-color: var(--danger); animation: pulseCritical 2s infinite;"></div>
        <span>Critique</span>
    </div>
</div>

<!-- Filters Section -->
<div class="filters">
    <form method="GET" class="filter-form">
        <input type="hidden" name="period" value="<?php echo $period; ?>">
        <input type="hidden" name="date" value="<?php echo $date; ?>">
        
        <div class="form-group">
            <label for="employeeFilter"><i class="fas fa-user"></i> Employé</label>
            <select class="form-control select2" id="employeeFilter" name="employee">
                <option value="">Tous les employés</option>
                <?php
                $stmt = $pdo->query("SELECT DISTINCT u.id, u.nom FROM users u JOIN presences p ON u.id = p.user_id ORDER BY u.nom");
                $employees = $stmt->fetchAll();
                foreach ($employees as $emp): ?>
                    <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['nom']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="posteFilter"><i class="fas fa-briefcase"></i> Poste</label>
            <select class="form-control select2" id="posteFilter" name="poste">
                <option value="">Tous les postes</option>
                <?php
                $stmt = $pdo->query("SELECT DISTINCT p.id, p.nom FROM postes p JOIN users u ON p.id = u.poste_id JOIN presences pr ON u.id = pr.user_id ORDER BY p.nom");
                $postes = $stmt->fetchAll();
                foreach ($postes as $poste): ?>
                    <option value="<?php echo $poste['id']; ?>"><?php echo htmlspecialchars($poste['nom']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="anomalyFilter"><i class="fas fa-filter"></i> Filtre anomalies</label>
            <select class="form-control" id="anomalyFilter" name="anomaly" onchange="this.form.submit()">
                <option value="">Toutes les présences</option>
                <option value="suspect" <?php echo isset($_GET['anomaly']) && $_GET['anomaly'] == 'suspect' ? 'selected' : ''; ?>>Avec anomalies</option>
                <option value="critical" <?php echo isset($_GET['anomaly']) && $_GET['anomaly'] == 'critical' ? 'selected' : ''; ?>>Anomalies critiques</option>
                <option value="none" <?php echo isset($_GET['anomaly']) && $_GET['anomaly'] == 'none' ? 'selected' : ''; ?>>Sans anomalies</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-filter"></i> Filtrer
            </button>
        </div>
    </form>
</div>

<!-- Table Container -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-list"></i> Liste des Présences</h2>
        <div class="table-actions">
            <input type="text" class="form-control table-search" placeholder="Rechercher..." id="tableSearch" style="width: 300px;">
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-secondary" onclick="clearSearch()">
                    <i class="fas fa-times"></i> Effacer
                </button>
                <button class="btn btn-warning" onclick="filterSuspectLieux()" id="filterSuspectBtn">
                    <i class="fas fa-exclamation-triangle"></i> Voir anomalies
                </button>
                <button class="btn btn-success" onclick="exportToCSV()">
                    <i class="fas fa-file-export"></i> Exporter
                </button>
                <?php if ($suspectLieux > 0): ?>
                    <button class="btn btn-danger" onclick="showAnomaliesAlert()">
                        <i class="fas fa-radiation-alt"></i> Alertes (<?php echo $suspectLieux; ?>)
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if (empty($presences)): ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-list"></i>
            <h3>Aucune présence trouvée</h3>
            <p>Aucune présence n'a été enregistrée pour la période sélectionnée.</p>
            <a href="?period=daily&date=<?php echo date('Y-m-d'); ?>" class="btn btn-primary">
                <i class="fas fa-calendar-day"></i> Voir les présences du jour
            </a>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table id="presencesTable">
                <thead>
                    <tr>
                        <th style="background: linear-gradient(135deg, var(--primary), var(--secondary));"><i class="fas fa-user"></i> Employé</th>
                        <th style="background: linear-gradient(135deg, var(--primary), var(--secondary));"><i class="fas fa-briefcase"></i> Poste</th>
                        <th style="background: linear-gradient(135deg, var(--primary), var(--secondary));"><i class="fas fa-calendar"></i> Date</th>
                        <th style="background: linear-gradient(135deg, var(--primary), var(--secondary));"><i class="fas fa-sign-in-alt"></i> Arrivée</th>
                        <th style="background: linear-gradient(135deg, var(--primary), var(--secondary));"><i class="fas fa-sign-out-alt"></i> Départ</th>
                        <th style="background: linear-gradient(135deg, var(--primary), var(--secondary));"><i class="fas fa-coffee"></i> Pause</th>
                        <th style="background: linear-gradient(135deg, var(--primary), var(--secondary));"><i class="fas fa-clock"></i> Retard</th>
                        <th style="background: linear-gradient(135deg, var(--primary), var(--secondary));"><i class="fas fa-map-marker-alt"></i> Lieu Arrivée</th>
                        <th style="background: linear-gradient(135deg, var(--primary), var(--secondary));"><i class="fas fa-map-marker-alt"></i> Lieu Pause</th>
                        <th style="background: linear-gradient(135deg, var(--primary), var(--secondary));"><i class="fas fa-map-marker-alt"></i> Lieu Départ</th>
                        <th style="background: linear-gradient(135deg, var(--primary), var(--secondary));"><i class="fas fa-exclamation-triangle"></i> Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($presences as $index => $presence): 
                        // Analyse détaillée des lieux
                        $lieu_arrivee_result = !empty($presence['lieu']) ? 
                            estLieuAutorise($presence['lieu'], $presence['latitude'], $presence['longitude'], 
                                          $quartiers_autorises, $villes_autorisees, $noms_lieux_autorises, $coordonnees_lieux) : 
                            ['autorise' => true, 'raison' => 'Non spécifié'];
                        
                        $lieu_arrivee_critical = !$lieu_arrivee_result['autorise'] && 
                            (strpos(strtolower($presence['lieu']), 'douala') === false && 
                             strpos(strtolower($presence['lieu']), 'carrières') === false);
                        
                        $has_anomalies = !$lieu_arrivee_result['autorise'];
                        $has_critical_anomalies = $lieu_arrivee_critical;
                    ?>
                    <tr class="fade-in <?php echo $has_critical_anomalies ? 'row-critical' : ($has_anomalies ? 'has-suspect-lieu' : ''); ?>" 
                        style="animation-delay: <?php echo $index * 0.05; ?>s;">
                        
                        <td>
                            <div class="employee-name"><?php echo htmlspecialchars($presence['nom']); ?></div>
                            <?php if ($has_critical_anomalies): ?>
                                <span class="badge badge-critical" style="margin-top: 4px;">
                                    <i class="fas fa-radiation"></i> CRITIQUE
                                </span>
                            <?php elseif ($has_anomalies): ?>
                                <span class="badge badge-danger" style="margin-top: 4px;">
                                    <i class="fas fa-exclamation-triangle"></i> SUSPECT
                                </span>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <span class="poste-badge"><?php echo htmlspecialchars($presence['poste'] ?? 'Non défini'); ?></span>
                        </td>
                        
                        <td>
                            <div class="time-display"><?php echo date('d/m/Y', strtotime($presence['date_presence'])); ?></div>
                        </td>
                        
                        <td>
                            <div class="time-display" style="background: linear-gradient(135deg, rgba(67, 97, 238, 0.1), rgba(67, 97, 238, 0.2)); color: var(--primary);">
                                <?php echo $presence['heure_debut_reel']; ?>
                            </div>
                            <?php if ($presence['retard_minutes'] > 0): ?>
                                <div style="font-size: 11px; color: var(--warning); margin-top: 3px;">
                                    +<?php echo $presence['retard_minutes']; ?> min
                                </div>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <?php if ($presence['heure_fin_reel']): ?>
                                <div class="time-display" style="background: linear-gradient(135deg, rgba(114, 9, 183, 0.1), rgba(114, 9, 183, 0.2)); color: var(--secondary);">
                                    <?php echo $presence['heure_fin_reel']; ?>
                                </div>
                            <?php else: ?>
                                <span class="badge badge-warning">
                                    <i class="fas fa-clock"></i> En cours
                                </span>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <?php if ($presence['heure_pause_debut'] && $presence['heure_pause_fin']): ?>
                                <div style="font-size: 12px; text-align: center;">
                                    <div style="color: var(--success);"><?php echo substr($presence['heure_pause_debut'], 0, 5); ?></div>
                                    <div style="font-size: 10px; color: var(--text-secondary);">à</div>
                                    <div style="color: var(--success);"><?php echo substr($presence['heure_pause_fin'], 0, 5); ?></div>
                                </div>
                            <?php elseif ($presence['heure_pause_debut']): ?>
                                <span class="badge badge-info">
                                    <i class="fas fa-pause"></i> En pause
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <?php if ($presence['retard_minutes'] > 30): ?>
                                <span class="badge badge-danger">
                                    <i class="fas fa-clock"></i> <?php echo $presence['retard_minutes']; ?> min
                                </span>
                            <?php elseif ($presence['retard_minutes'] > 0): ?>
                                <span class="badge badge-warning">
                                    <i class="fas fa-clock"></i> <?php echo $presence['retard_minutes']; ?> min
                                </span>
                            <?php else: ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-check-circle"></i> À l'heure
                                </span>
                            <?php endif; ?>
                        </td>
                        
                        <!-- Lieu Arrivée avec analyse améliorée -->
                        <td class="lieu-container">
                            <?php if (!empty($presence['lieu'])): ?>
                                <div class="lieu-title">Arrivée:</div>
                                <div class="lieu-info <?php echo !$lieu_arrivee_result['autorise'] ? 
                                    ($lieu_arrivee_critical ? 'lieu-suspect-critical' : 'lieu-suspect') : ''; ?>"
                                    data-tooltip="<?php echo $lieu_arrivee_result['raison']; ?>">
                                    <?php echo htmlspecialchars(substr($presence['lieu'], 0, 50)); ?>
                                    <?php echo strlen($presence['lieu']) > 50 ? '...' : ''; ?>
                                    
                                    <?php if (!$lieu_arrivee_result['autorise']): ?>
                                        <br>
                                        <small style="color: inherit; opacity: 0.9; font-weight: bold;">
                                            <i class="fas fa-exclamation-circle"></i> 
                                            <?php echo $lieu_arrivee_critical ? 'CRITIQUE' : 'SUSPECT'; ?>
                                        </small>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($presence['latitude']) && !empty($presence['longitude'])): ?>
                                        <br>
                                        <small style="font-size: 10px; opacity: 0.7;">
                                            <i class="fas fa-map-pin"></i> 
                                            <?php echo round($presence['latitude'], 4); ?>, <?php echo round($presence['longitude'], 4); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">Non spécifié</span>
                            <?php endif; ?>
                        </td>
                        
                        <!-- Lieu Pause (simplifié) -->
                        <td class="lieu-container">
                            <?php if (!empty($presence['lieu_pause_debut'])): ?>
                                <div class="lieu-title">Pause:</div>
                                <div class="lieu-info" style="font-size: 11px; padding: 4px 6px;">
                                    <?php echo htmlspecialchars(substr($presence['lieu_pause_debut'], 0, 40)); ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        
                        <!-- Lieu Départ (simplifié) -->
                        <td class="lieu-container">
                            <?php if (!empty($presence['lieu_fin'])): ?>
                                <div class="lieu-title">Départ:</div>
                                <div class="lieu-info" style="font-size: 11px; padding: 4px 6px;">
                                    <?php echo htmlspecialchars(substr($presence['lieu_fin'], 0, 40)); ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        
                        <!-- Statut global -->
                        <td>
                            <?php if ($has_critical_anomalies): ?>
                                <span class="badge badge-critical">
                                    <i class="fas fa-radiation-alt"></i> Anomalie Critique
                                </span>
                            <?php elseif ($has_anomalies): ?>
                                <span class="badge badge-danger">
                                    <i class="fas fa-exclamation-triangle"></i> Anomalie
                                </span>
                            <?php elseif ($presence['retard_minutes'] > 30): ?>
                                <span class="badge badge-warning">
                                    <i class="fas fa-clock"></i> Retard Important
                                </span>
                            <?php elseif ($presence['retard_minutes'] > 0): ?>
                                <span class="badge badge-info">
                                    <i class="fas fa-clock"></i> Léger Retard
                                </span>
                            <?php else: ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-check"></i> Normal
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Table Footer amélioré -->
        <div class="table-footer">
            <div>
                <i class="fas fa-info-circle"></i> 
                <strong><?php echo count($presences); ?></strong> présence(s) | 
                <i class="fas fa-check-circle" style="color: var(--success);"></i> 
                <?php echo $onTime; ?> à l'heure | 
                <i class="fas fa-clock" style="color: var(--warning);"></i> 
                <?php echo $late; ?> en retard
                
                <?php if ($suspectLieux > 0): ?>
                    | <i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i>
                    <strong style="color: var(--danger);"><?php echo $suspectLieux; ?> anomalie(s)</strong>
                    <?php if ($criticalLieux > 0): ?>
                        <span class="badge badge-critical" style="margin-left: 10px;">
                            <i class="fas fa-radiation"></i> <?php echo $criticalLieux; ?> critique(s)
                        </span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i> Imprimer
                </button>
                <button class="btn btn-primary" onclick="refreshPage()">
                    <i class="fas fa-sync-alt"></i> Actualiser
                </button>
                <button class="btn btn-info" onclick="exportStatistics()">
                    <i class="fas fa-chart-bar"></i> Statistiques
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>