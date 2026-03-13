// resources/js/app.js
import './bootstrap';
import './ga-live-dashboard';

import Alpine from 'alpinejs';

// Inicializar Alpine apenas se ainda não foi inicializado
if (!window.Alpine) {
    window.Alpine = Alpine;
    Alpine.start();
}
