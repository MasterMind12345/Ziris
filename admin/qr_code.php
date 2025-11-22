<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Générer ou récupérer le QR Code
$qrUrl = generateQRCode();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code - Batobaye Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>QR Code de Présence</h1>
            <p>Générez et téléchargez le QR Code pour la prise de présence</p>
        </div>
        
        <div class="qr-container">
            <div class="form-container">
                <div class="form-group">
                    <label>URL de la page de présence:</label>
                    <input type="text" class="form-control" value="<?php echo $qrUrl; ?>" readonly id="qrUrl">
                    <small class="form-text">Les employés scanneront ce QR Code pour pointer</small>
                </div>
                
                <div class="qr-code-container">
                    <div id="qrcode">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?php echo urlencode($qrUrl); ?>&color=4361ee&bgcolor=ffffff&margin=10" 
                             alt="QR Code Batobaye" 
                             id="qrImage"
                             style="border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button class="btn btn-primary" onclick="downloadQRCode()">
                        <i class="fas fa-download"></i> Télécharger le QR Code
                    </button>
                    <button class="btn btn-secondary" onclick="printQRCode()">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                    <button class="btn btn-success" onclick="refreshQRCode()">
                        <i class="fas fa-sync-alt"></i> Régénérer
                    </button>
                    <button class="btn btn-info" onclick="copyUrl()">
                        <i class="fas fa-copy"></i> Copier le lien
                    </button>
                </div>

                <div class="qr-instructions">
                    <h3><i class="fas fa-info-circle"></i> Instructions</h3>
                    <ul>
                        <li>Le QR Code pointe vers la page de pointage des employés</li>
                        <li>Téléchargez le QR Code pour l'imprimer et l'afficher</li>
                        <li>Les employés scanneront ce code avec leur smartphone</li>
                        <li>Ils devront être connectés pour pointer leur présence</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <style>
        .qr-container {
            text-align: center;
            padding: 20px;
        }

        .qr-code-container {
            margin: 30px 0;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: inline-block;
        }

        #qrcode {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 300px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
            margin: 30px 0;
        }

        .qr-instructions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: left;
            margin-top: 20px;
        }

        .qr-instructions h3 {
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .qr-instructions ul {
            list-style: none;
            padding: 0;
        }

        .qr-instructions li {
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .qr-instructions li:before {
            content: "•";
            color: var(--primary);
            font-weight: bold;
        }

        .qr-instructions li:last-child {
            border-bottom: none;
        }

        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .form-actions .btn {
                width: 200px;
            }
        }
    </style>
    
    <script>
        // Télécharger le QR Code
        function downloadQRCode() {
            const qrImage = document.getElementById('qrImage');
            const link = document.createElement('a');
            link.download = 'qr-code-batobaye.png';
            link.href = qrImage.src;
            link.click();
            showNotification('QR Code téléchargé avec succès!', 'success');
        }

        // Imprimer le QR Code
        function printQRCode() {
            const printWindow = window.open('', '_blank');
            const qrUrl = document.getElementById('qrUrl').value;
            const qrImageSrc = document.getElementById('qrImage').src;
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>QR Code Batobaye - Impression</title>
                    <style>
                        body { 
                            font-family: Arial, sans-serif; 
                            text-align: center; 
                            padding: 40px;
                        }
                        .print-title { 
                            font-size: 24px; 
                            margin-bottom: 20px; 
                            color: #333;
                        }
                        .print-url { 
                            margin: 20px 0; 
                            color: #666;
                            font-size: 14px;
                            word-break: break-all;
                        }
                        .print-instructions {
                            margin-top: 30px;
                            color: #888;
                            font-size: 12px;
                        }
                        .qr-image {
                            max-width: 300px;
                            border: 1px solid #ddd;
                            border-radius: 8px;
                        }
                    </style>
                </head>
                <body>
                    <div class="print-title">Batobaye - QR Code de Présence</div>
                    <div class="print-url">${qrUrl}</div>
                    <img src="${qrImageSrc}" class="qr-image">
                    <div class="print-instructions">
                        Scanner ce QR Code pour pointer votre présence<br>
                        Date d'impression: ${new Date().toLocaleDateString()}
                    </div>
                </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.print();
        }

        // Rafraîchir le QR Code
        function refreshQRCode() {
            if (confirm('Voulez-vous régénérer le QR Code ?')) {
                const qrUrl = document.getElementById('qrUrl').value;
                const qrImage = document.getElementById('qrImage');
                qrImage.src = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(qrUrl)}&color=4361ee&bgcolor=ffffff&margin=10&t=${new Date().getTime()}`;
                showNotification('QR Code régénéré!', 'success');
            }
        }

        // Copier l'URL dans le presse-papier
        function copyUrl() {
            const urlInput = document.getElementById('qrUrl');
            urlInput.select();
            urlInput.setSelectionRange(0, 99999);
            document.execCommand('copy');
            showNotification('Lien copié dans le presse-papier!', 'success');
        }

        // Afficher une notification
        function showNotification(message, type = 'info') {
            // Supprimer les notifications existantes
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(notification => notification.remove());

            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                background: ${type === 'success' ? '#4caf50' : type === 'error' ? '#f44336' : '#2196f3'};
                color: white;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                z-index: 10000;
                animation: slideIn 0.3s ease;
                display: flex;
                align-items: center;
                gap: 10px;
            `;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation' : 'info'}-circle"></i>
                ${message}
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Ajouter les styles d'animation
        document.addEventListener('DOMContentLoaded', function() {
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(style);

            // Vérifier que l'image est chargée
            const qrImage = document.getElementById('qrImage');
            qrImage.onerror = function() {
                this.src = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent('<?php echo $qrUrl; ?>')}&color=4361ee&bgcolor=ffffff&margin=10`;
            };
        });
    </script>
</body>
</html>