// Initialize the map
const map = L.map('map', {
    crs: L.CRS.Simple,
    maxZoom: 4,
    minZoom: -2
});

// Set bounds and initial zoom level
const bounds = [[0, 0], [2048, 2048]];
L.imageOverlay('maps/mapa.png', bounds).addTo(map);
map.setView([1024, 1024], 0); // Change the "0" to your preferred initial zoom level

// Update zoom level information
const zoomInfo = document.getElementById('zoomLevel');
map.on('zoomend', () => {
    zoomInfo.textContent = map.getZoom();
});
zoomInfo.textContent = map.getZoom();

const customIcons = {
    enemy: L.icon({ iconUrl: 'images/icon-enemy.png', iconSize: [32, 32] }),
    exit: L.icon({ iconUrl: 'images/icon-exit.png', iconSize: [32, 32] }),
    respawn: L.icon({ iconUrl: 'images/icon-respawn.png', iconSize: [32, 32] }),
    loot: L.icon({ iconUrl: 'images/icon-loot.png', iconSize: [32, 32] })
};

// Marker functions and rest of the code here (unchanged)
// Sidebar toggle
const sidebar = document.getElementById('sidebar');
const toggleMenuButton = document.getElementById('toggleMenu');
toggleMenuButton.addEventListener('click', () => {
    sidebar.classList.toggle('hidden');
    adjustMapSize(); // Reajustar el mapa después de colapsar/expandir el menú
});

function adjustMapSize() {
    setTimeout(() => map.invalidateSize(), 300);
}
