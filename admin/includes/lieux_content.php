<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-map-marker-alt"></i> Gestion des Lieux Autorisés</h2>
        <div class="table-actions">
            <button class="btn btn-success" onclick="openLieuModal()">
                <i class="fas fa-plus"></i> Ajouter un Lieu
            </button>
            <button class="btn btn-info" onclick="refreshLieux()">
                <i class="fas fa-sync-alt"></i> Actualiser
            </button>
        </div>
    </div>
    
    <?php if (empty($lieux_autorises)): ?>
        <div class="empty-state">
            <i class="fas fa-map-marked-alt"></i>
            <h3>Aucun lieu configuré</h3>
            <p>Commencez par ajouter des lieux autorisés pour la vérification des pointages.</p>
            <button class="btn btn-primary" onclick="openLieuModal()">
                <i class="fas fa-plus"></i> Ajouter le premier lieu
            </button>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table id="lieuxTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><i class="fas fa-map-pin"></i> Nom</th>
                        <th><i class="fas fa-city"></i> Ville/Quartier</th>
                        <th><i class="fas fa-ruler"></i> Rayon</th>
                        <th><i class="fas fa-map"></i> Coordonnées</th>
                        <th><i class="fas fa-tag"></i> Type</th>
                        <th><i class="fas fa-toggle-on"></i> Statut</th>
                        <th><i class="fas fa-calendar"></i> Créé le</th>
                        <th><i class="fas fa-cogs"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lieux_autorises as $lieu): ?>
                    <tr style="<?php echo $lieu['est_actif'] ? '' : 'opacity: 0.6; background: var(--bg-secondary);'; ?>">
                        <td><strong>#<?php echo $lieu['id']; ?></strong></td>
                        <td>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($lieu['nom_lieu']); ?></div>
                            <div style="font-size: 11px; color: var(--text-secondary);">
                                <?php echo htmlspecialchars($lieu['adresse'] ?: 'Pas d\'adresse'); ?>
                            </div>
                        </td>
                        <td>
                            <div>
                                <?php if ($lieu['quartier']): ?>
                                    <span class="badge badge-info"><?php echo htmlspecialchars($lieu['quartier']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="margin-top: 5px; font-weight: 500;">
                                <?php echo htmlspecialchars($lieu['ville']); ?>
                                <?php if ($lieu['code_postal']): ?>
                                    <small>(<?php echo $lieu['code_postal']; ?>)</small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div style="text-align: center;">
                                <span class="badge" style="background: linear-gradient(135deg, rgba(67, 97, 238, 0.1), rgba(67, 97, 238, 0.2));">
                                    <?php echo $lieu['rayon_autorise_metres']; ?> m
                                </span>
                            </div>
                        </td>
                        <td>
                            <?php if ($lieu['latitude'] && $lieu['longitude']): ?>
                                <div style="font-family: monospace; font-size: 11px;">
                                    <?php echo round($lieu['latitude'], 6); ?><br>
                                    <?php echo round($lieu['longitude'], 6); ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">Non défini</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $typeColors = [
                                'bureau' => 'primary',
                                'client' => 'success',
                                'exterieur' => 'warning',
                                'autre' => 'secondary'
                            ];
                            $color = $typeColors[$lieu['type_lieu']] ?? 'secondary';
                            ?>
                            <span class="badge badge-<?php echo $color; ?>">
                                <i class="fas fa-<?php echo $lieu['type_lieu'] === 'bureau' ? 'building' : 
                                               ($lieu['type_lieu'] === 'client' ? 'handshake' : 
                                               ($lieu['type_lieu'] === 'exterieur' ? 'tree' : 'map-marker')); ?>"></i>
                                <?php echo ucfirst($lieu['type_lieu']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($lieu['est_actif']): ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-check-circle"></i> Actif
                                </span>
                            <?php else: ?>
                                <span class="badge badge-secondary">
                                    <i class="fas fa-times-circle"></i> Inactif
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-size: 12px;">
                                <?php echo date('d/m/Y', strtotime($lieu['created_at'])); ?>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <button class="btn btn-sm btn-info" 
                                        onclick="openLieuModal('edit', <?php echo htmlspecialchars(json_encode($lieu)); ?>)"
                                        title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-<?php echo $lieu['est_actif'] ? 'warning' : 'success'; ?>" 
                                        onclick="toggleLieuStatus(<?php echo $lieu['id']; ?>, <?php echo $lieu['est_actif']; ?>)"
                                        title="<?php echo $lieu['est_actif'] ? 'Désactiver' : 'Activer'; ?>">
                                    <i class="fas fa-<?php echo $lieu['est_actif'] ? 'toggle-on' : 'toggle-off'; ?>"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" 
                                        onclick="confirmDelete(<?php echo $lieu['id']; ?>, '<?php echo addslashes($lieu['nom_lieu']); ?>')"
                                        title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Stats des lieux -->
        <div class="table-footer">
            <div>
                <i class="fas fa-info-circle"></i> 
                <?php echo count($lieux_autorises); ?> lieu(x) configuré(s) | 
                <?php echo count(array_filter($lieux_autorises, fn($l) => $l['est_actif'])); ?> actif(s) | 
                <?php echo count(array_filter($lieux_autorises, fn($l) => $l['latitude'] && $l['longitude'])); ?> avec GPS
            </div>
            <div>
                <button class="btn btn-secondary" onclick="exportLieux()">
                    <i class="fas fa-download"></i> Exporter la liste
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>