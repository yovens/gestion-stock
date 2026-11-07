import './bootstrap';
import Alpine from 'alpinejs';
import pos from './pos';
import productForm from './product-form';
import purchaseLines from './purchase-lines';
import adjustmentLines from './adjustment-lines';
import './bootstrap';
// Si Breeze a déjà démarré Alpine dans ce fichier, gardez un seul Alpine.start()
Alpine.data('pos', pos);
Alpine.data('productForm', productForm);
Alpine.data('purchaseLines', purchaseLines);
Alpine.data('adjustmentLines', adjustmentLines);

window.Alpine = Alpine;
Alpine.start();

// Chart.js n'est chargé que sur le tableau de bord (la caisse reste légère et rapide)
if (document.getElementById('dashboard-data')) {
    import('./dashboard').then(({ default: initDashboard }) => initDashboard());
}
