// Chart.js pour les statistiques
document.addEventListener('DOMContentLoaded', function() {
    // Graphique des présences hebdomadaires
    const weeklyCtx = document.getElementById('weeklyChart');
    if (weeklyCtx) {
        const weeklyChart = new Chart(weeklyCtx, {
            type: 'bar',
            data: {
                labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
                datasets: [{
                    label: 'Présences',
                    data: [65, 59, 80, 81, 56, 55, 40],
                    backgroundColor: '#4361ee',
                    borderColor: '#3a56d4',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    // Toggle sidebar sur mobile
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
    }
    
    // Confirmation pour les actions critiques
    const deleteButtons = document.querySelectorAll('.btn-danger');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Êtes-vous sûr de vouloir effectuer cette action ?')) {
                e.preventDefault();
            }
        });
    });
    
    // Filtrage des tableaux
    const searchInputs = document.querySelectorAll('.table-search');
    searchInputs.forEach(input => {
        input.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const table = this.closest('.table-container').querySelector('table');
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    });
});

// Fonction pour télécharger le QR Code
function downloadQRCode() {
    const qrCode = document.querySelector('.qr-code img');
    if (qrCode) {
        const link = document.createElement('a');
        link.download = 'qr-code-batobaye.png';
        link.href = qrCode.src;
        link.click();
    }
}

// Fonction pour exporter les données en CSV
function exportToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            // Nettoyer les données pour le CSV
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, "").replace(/(\s\s)/gm, " ");
            data = data.replace(/"/g, '""');
            row.push('"' + data + '"');
        }
        
        csv.push(row.join(","));
    }
    
    // Télécharger le fichier CSV
    const csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
    const downloadLink = document.createElement("a");
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Fonction pour supprimer un utilisateur
function deleteUser(userId, userName) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer l'utilisateur "${userName}" ?\n\nCette action est irréversible et supprimera également toutes ses données de présence.`)) {
        // Créer un formulaire pour envoyer la requête
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete_user.php';
        
        const userIdInput = document.createElement('input');
        userIdInput.type = 'hidden';
        userIdInput.name = 'user_id';
        userIdInput.value = userId;
        
        form.appendChild(userIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Fonction pour éditer un utilisateur
function editUser(userId) {
    alert('Fonction d\'édition à implémenter pour l\'utilisateur ID: ' + userId);
    // window.location.href = `edit_user.php?id=${userId}`;
}