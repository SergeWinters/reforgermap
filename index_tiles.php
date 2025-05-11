<?php
// Función para escanear y obtener los directorios de mapas de teselas disponibles
function forgeNexGetMapTileDirectories($baseDir = 'maps/tiles/') {
    $maps = [];
    if (is_dir($baseDir)) {
        $directories = scandir($baseDir);
        if ($directories === false) {
            // Registrar error si no se puede escanear el directorio
            error_log("ForgeNEX Map Script: Failed to scan directory: " . $baseDir);
            return $maps; // Devuelve array vacío
        }
        foreach ($directories as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            if (is_dir($baseDir . $dir)) {
                // Usar el nombre de la carpeta como ID
                // Crear un nombre "amigable" capitalizando y reemplazando guiones/guiones bajos
                $friendlyName = str_replace(['_', '-'], ' ', $dir);
                $friendlyName = ucwords($friendlyName);
                $maps[$dir] = $friendlyName;
            }
        }
    } else {
        // Registrar error si el directorio base de teselas no existe
        error_log("ForgeNEX Map Script: Base tile directory not found: " . $baseDir);
    }
    return $maps;
}
// Obtener los mapas disponibles
$availableTileMaps = forgeNexGetMapTileDirectories();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Interactive Map with POI, Drawing Tools, for Arma Reforger</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />
    <link rel="stylesheet" href="https://unpkg.com/@raruto/leaflet-gesture-handling@latest/dist/leaflet-gesture-handling.min.css" type="text/css">
    <script src="https://unpkg.com/@raruto/leaflet-gesture-handling@latest/dist/leaflet-gesture-handling.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet-rotatedmarker@0.2.0/leaflet.rotatedMarker.js"></script>
    <meta name="description" content="Plan your strategies and navigate efficiently in Arma Reforger with our interactive map tool. Add markers, search POIs, draw plans, and collaborate with your team.">
    <meta name="keywords" content="Arma Reforger map, interactive map, POI search, markers, strategy planning, drawing tools, minimap, map tools for games">
    <meta name="author" content="ForgeNEX Interactive">
    <meta name="robots" content="index, follow">
    <meta name="language" content="en">
    <meta name="theme-color" content="#333">

    <meta property="og:title" content="Interactive Map for Arma Reforger">
    <meta property="og:description" content="A powerful tool for players to plan strategies, add markers, and interact with the map of Arma Reforger. Includes POI search, drawing tools, and more.">
    <meta property="og:image" content="https://www.reforgermap.com/og-image.png"> <meta property="og:url" content="https://www.reforgermap.com"> <meta property="og:type" content="website">
    <meta property="og:site_name" content="Reforger Interactive Map">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Interactive Map for Arma Reforger">
    <meta name="twitter:description" content="Plan your strategies and collaborate effectively with our interactive map for Arma Reforger.">
    <meta name="twitter:image" content="https://www.reforgermap.com/og-image.png"> <link rel="canonical" href="https://www.reforgermap.com"> <link rel="icon" href="/favicon.ico" type="image/x-icon"> <script type="application/ld+json">
    {
        "@context": "http://schema.org",
        "@type": "WebSite",
        "name": "Reforger Interactive Map",
        "url": "https://www.reforgermap.com", // Cambia esta URL
        "potentialAction": {
            "@type": "SearchAction",
            "target": "https://www.reforgemap.com/?q={search_term_string}", // Cambia esta URL
            "query-input": "required name=search_term_string"
        },
        "description": "Interactive map tool for Arma Reforger players. Plan strategies, add markers, search POIs, and collaborate with your team."
    }
    </script>
    <link rel="stylesheet" href="style.css">
	<script defer src="https://stats.forgenex.com/pixel/5YKZG0nR2y0g7vKK"></script> <script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/uuid/8.3.2/uuid.min.js"></script>

</head>
<body>
    <button id="toggleMenu">☰</button>
    <div id="sidebar">
        <h3>Markers</h3>
        <div id="menuItems">
            <button id="enemyButton" onclick="setMarkerType('enemy')">Enemy</button>
            <button id="exitButton" onclick="setMarkerType('exit')">Attack</button>
            <button id="respawnButton" onclick="setMarkerType('respawn')">Respawn</button>
        </div>
        <button onclick="removeAllMarkers()">Remove All</button>
        <h3>Drawing Tools</h3>
        <button onclick="enableDrawing()">Enable Drawing</button>
        <button onclick="disableDrawing()">Disable Drawing</button>
        <button onclick="clearAllDrawings()">Clear All Drawings</button>
        <h3>Select Map</h3>
        <select id="mapSelector" onchange="changeMap(event)">
            <?php if (empty($availableTileMaps)): ?>
                <option value="mapa1" selected>Default Map (mapa1)</option>
                <?php else: ?>
                <?php
                $isFirstMap = true; // Para seleccionar el primer mapa por defecto
                foreach ($availableTileMaps as $mapId => $mapName): ?>
                    <option value="<?php echo htmlspecialchars($mapId); ?>" <?php if ($isFirstMap) { echo 'selected'; $isFirstMap = false; } ?>>
                        <?php echo htmlspecialchars($mapName); ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    </div>
    <div id="map"></div>
    <div id="zoomInfo">Zoom Level: <span id="zoomLevel">0</span></div>
    <div id="coordinatesInfo">Coordinates: <span id="mouseCoordinates">---</span></div>
    <div id="compass">
        <span>🧭</span>
        <span id="currentDirection">N</span>
    </div>
    <div id="footer">
        <div>
            <a href="#" onclick="openHelp()">Help</a>
			<div id="help-modal" class="hidden">
				<div class="modal-content">
					<iframe src="help.html" frameborder="0"></iframe>
					<button onclick="closeHelp()">Close</button>
				</div>
			</div>
			<a href="#" onclick="openAbout()">About</a>
			<div id="modal" class="hidden">
				<div class="modal-content">
					<iframe src="about.html" frameborder="0"></iframe>
					<button onclick="closeAbout()">Close</button>
				</div>
			</div>
            <a href="https://discord.gg/MfNDSg85Pf" target="_blank">Discord</a>
        </div>
        <div>
            Version 7.0 - Collaborative Mode - <a href="https://github.com/SergeWinters/reforgermap/commits/main/" target="_blank">Changelog</a>
        </div>
    </div>
    <div id="minimapContainer">
        <button id="closeMinimap">×</button>
        <div id="minimap"></div>
    </div>
    <button id="toggleMinimap">Toggle MiniMap</button>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>

    <script>
        const SOCKET_SERVER_URL = 'http://localhost:3000';
        let socket;
        let currentRoomId = null;

        // --- Configuración para mapas de teselas (tiles) ---
        const TILE_PATH_TEMPLATE = "maps/tiles/{mapId}/{z}/{x}/{y}.png";
        // Coordenadas del mapa: [[minY, minX], [maxY, maxX]] para CRS.Simple con (0,0) en esquina superior izquierda.
        const MAP_CRS_BOUNDS = [[0, 0], [2048, 2048]];
        const MAP_MIN_ZOOM = 3; // Zoom mínimo de las teselas (de gdal2tiles -z 0-4)
        const MAP_MAX_ZOOM = 6; // Zoom máximo de las teselas (de gdal2tiles -z 0-4)

        const map = L.map('map', {
            crs: L.CRS.Simple,
            minZoom: MAP_MIN_ZOOM,
            maxZoom: MAP_MAX_ZOOM, // El mapa principal permitirá hasta el zoom máximo de las teselas
            gestureHandling: true,
            //maxBounds: MAP_CRS_BOUNDS, // Restringe el paneo a estas coordenadas
            maxBoundsViscosity: 1.0 // Hace que los bordes sean "sólidos"
        });
        let currentMapLayer; // Capa del mapa principal (TileLayer)
        const zoomInfo = document.getElementById('zoomLevel');
        const mouseCoordinates = document.getElementById('mouseCoordinates');
        const customIcons = {
            enemy: L.icon({ iconUrl: 'images/icon-enemy.png', iconSize: [32, 32] }),
            exit: L.icon({ iconUrl: 'images/icon-exit.png', iconSize: [32, 32] }),
            respawn: L.icon({ iconUrl: 'images/icon-respawn.png', iconSize: [32, 32] }),
            loot: L.icon({ iconUrl: 'images/icon-loot.png', iconSize: [15, 15] })
        };
        let localMarkers = {};
        let localDrawings = {};
        let currentMarkerType = null;
        const drawnItems = new L.FeatureGroup().addTo(map);

        function generateUUID() {
            return (typeof uuid !== 'undefined' && uuid.v4) ? uuid.v4() : (Date.now().toString(36) + Math.random().toString(36).substring(2));
        }
        function getRoomIdFromUrl() { return new URLSearchParams(window.location.search).get('session'); }
        function generateRoomIdAndSetUrl() {
            const newRoomId = generateUUID().substring(0, 8);
            const newUrl = `${window.location.pathname}?session=${newRoomId}${window.location.hash}`;
            window.history.pushState({ path: newUrl }, '', newUrl);
            return newRoomId;
        }

        // --- Carga de Mapas de Teselas ---
        function loadMap(mapId) {
            if (!mapId) {
                console.error("ForgeNEX Map Script: Map ID is undefined in loadMap.");
                const selector = document.getElementById('mapSelector');
                if (selector && selector.options.length > 0 && selector.value) {
                    mapId = selector.value; // Intenta con el valor actual del selector
                } else if (selector && selector.options.length > 0) {
                     mapId = selector.options[0].value; // O con la primera opción
                } else {
                    document.getElementById('map').innerHTML = '<p style="text-align:center; padding-top: 50px;">No maps available. Check configuration.</p>';
                    if (currentMapLayer && map.hasLayer(currentMapLayer)) map.removeLayer(currentMapLayer);
                    if (minimapLayer && minimap && minimap.hasLayer(minimapLayer)) minimap.removeLayer(minimapLayer);
                    return;
                }
                console.warn("ForgeNEX Map Script: Map ID was initially undefined, attempting to load:", mapId);
            }

            const tilePath = TILE_PATH_TEMPLATE.replace('{mapId}', mapId);
            const mapOptionElement = document.querySelector(`#mapSelector option[value="${mapId}"]`);
            const mapDisplayName = mapOptionElement ? mapOptionElement.textContent : mapId;

            if (currentMapLayer && map.hasLayer(currentMapLayer)) {
                map.removeLayer(currentMapLayer);
            }

            currentMapLayer = L.tileLayer(tilePath, {
                attribution: mapDisplayName,
                //bounds: MAP_CRS_BOUNDS, // Mapea las teselas a estas coordenadas del CRS
                minZoom: MAP_MIN_ZOOM,     // Zoom mínimo que soporta esta capa de teselas
                maxZoom: MAP_MAX_ZOOM,     // Zoom máximo que el mapa permitirá para esta capa
                maxNativeZoom: MAP_MAX_ZOOM,// Zoom máximo real de las teselas generadas
                noWrap: true,              // Evita que el mapa se repita horizontalmente
                // tms: false, // No es necesario si usaste --xyz, Leaflet lo maneja por defecto
            }).addTo(map);

            // Centrar la vista. El centro de MAP_CRS_BOUNDS con un zoom intermedio.
            // Si MAP_CRS_BOUNDS es [[0,0],[2048,2048]], el centro es [1024,1024]
            map.setView([0, 0], MAP_MIN_ZOOM);

            // Actualizar minimapa
            if (minimap) {
                if (minimapLayer && minimap.hasLayer(minimapLayer)) {
                    minimap.removeLayer(minimapLayer);
                }
                minimapLayer = L.tileLayer(tilePath, { // Usa la misma ruta de teselas
                    bounds: MAP_CRS_BOUNDS,
                    minZoom: minimap.options.minZoom, // El minimapa puede tener sus propios límites de zoom
                    maxZoom: minimap.options.maxZoom,
                    maxNativeZoom: MAP_MAX_ZOOM,
                    noWrap: true,
                }).addTo(minimap);
                minimap.fitBounds(MAP_CRS_BOUNDS); // Ajusta el minimapa para que muestre toda la extensión
            }
        }

        function changeMap(event) {
            const mapId = event.target.value;
            loadMap(mapId);
            // Aquí podrías añadir lógica para notificar a otros usuarios del cambio de mapa base,
            // pero como se mencionó antes, esto añade complejidad. Por ahora es local.
        }

        // --- Lógica de WebSocket y Sincronización (mayormente sin cambios) ---
        function connectWebSocket() { /* ... Tu código existente ... */ 
            if (socket && socket.connected) { console.log("Already connected."); return; }
            if (!currentRoomId) { console.error("Cannot connect without a room ID."); return; }
            console.log(`Attempting to connect to WebSocket server at ${SOCKET_SERVER_URL} for room ${currentRoomId}...`);
            socket = io(SOCKET_SERVER_URL, { reconnectionAttempts: 5, reconnectionDelay: 3000 });
            socket.on('connect', () => {
                console.log('Connected to server via WebSocket:', socket.id);
                console.log(`Joining room: ${currentRoomId}`);
                socket.emit('join_room', currentRoomId);
            });
            socket.on('disconnect', (reason) => {
                console.log('Disconnected from server:', reason);
                alert("Connection lost. Markers/drawings may not sync. Please refresh.");
            });
            socket.on('connect_error', (error) => {
                console.error('Connection Error:', error);
                alert(`Failed to connect: ${error.message}. Check server & refresh.`);
            });
            socket.on('initial_state', (state) => {
                console.log("Received initial state:", state);
                clearLocalMap();
                if (state.markers) Object.values(state.markers).forEach(markerData => addMarkerToMap(markerData, false));
                if (state.drawings) Object.values(state.drawings).forEach(drawingData => addDrawingToMap(drawingData, false));
                console.log("Initial state applied.");
            });
            socket.on('marker_added', (markerData) => addMarkerToMap(markerData, false));
            socket.on('marker_removed', (markerId) => removeMarkerFromMap(markerId, false));
            socket.on('drawing_added', (drawingData) => addDrawingToMap(drawingData, false));
            socket.on('drawing_removed', (drawingId) => removeDrawingFromMap(drawingId, false));
            socket.on('drawings_cleared', () => clearLocalDrawings(false));
        }
        function clearLocalMap() { /* ... Tu código existente ... */ 
            Object.keys(localMarkers).forEach(id => { if (map.hasLayer(localMarkers[id])) map.removeLayer(localMarkers[id]); });
            localMarkers = {};
            drawnItems.clearLayers();
            localDrawings = {};
        }
        function addMarkerToMap(markerData, emitToServer = true) { /* ... Tu código existente ... */ 
            if (!markerData || !markerData.id || markerData.lat == null || markerData.lng == null || !markerData.type) { console.error("Invalid marker data for addMarkerToMap:", markerData); return; }
            if (localMarkers[markerData.id]) return; // Ya existe localmente
            if (!customIcons[markerData.type]) { console.error(`Invalid marker type: ${markerData.type}`); return; }
            const marker = L.marker([markerData.lat, markerData.lng], { icon: customIcons[markerData.type] });
            marker.markerId = markerData.id;
            marker.on('click', function() { removeMarkerFromMap(this.markerId, true); });
            marker.addTo(map);
            localMarkers[markerData.id] = marker;
            if (emitToServer && socket && socket.connected) socket.emit('add_marker', markerData);
        }
        function removeMarkerFromMap(markerId, emitToServer = true) { /* ... Tu código existente ... */ 
            if (!localMarkers[markerId]) return;
            if (map.hasLayer(localMarkers[markerId])) map.removeLayer(localMarkers[markerId]);
            delete localMarkers[markerId];
            if (emitToServer && socket && socket.connected) socket.emit('remove_marker', markerId);
        }

        map.on('click', e => { /* ... Tu código existente ... */ 
            if (!currentMarkerType || (drawControl && drawControl._toolbars.draw._activeMode)) return;
            const markerData = { id: generateUUID(), type: currentMarkerType, lat: e.latlng.lat, lng: e.latlng.lng };
            addMarkerToMap(markerData, true);
            currentMarkerType = null;
            document.querySelectorAll('#menuItems button').forEach(button => button.classList.remove('selected'));
        });
        function setMarkerType(type) { /* ... Tu código existente ... */ 
            currentMarkerType = type;
            document.querySelectorAll('#menuItems button').forEach(button => button.classList.remove('selected'));
            const selectedButton = document.getElementById(`${type}Button`);
            if (selectedButton) selectedButton.classList.add('selected');
            if (drawControl && drawControl._toolbars.edit._activeMode) drawControl._toolbars.edit.disable();
            if (drawControl && drawControl._toolbars.draw._activeMode) drawControl._toolbars.draw.disable();
            disableDrawing(); // Asegura que los controles de dibujo se quiten/desactiven
        }
        function removeAllMarkers() { /* ... Tu código existente ... */ Object.keys(localMarkers).forEach(id => removeMarkerFromMap(id, true));}

        // --- Integración Leaflet.Draw (mayormente sin cambios) ---
        map.on(L.Draw.Event.CREATED, function (event) { /* ... Tu código existente ... */ 
            const layer = event.layer; const layerType = event.layerType; const drawingId = generateUUID(); layer.drawingId = drawingId;
            localDrawings[drawingId] = layer; drawnItems.addLayer(layer);
            let drawingData = { id: drawingId, type: layerType };
            if (layerType === 'polyline' || layerType === 'polygon' || layerType === 'rectangle') drawingData.latlngs = layer.getLatLngs();
            else if (layerType === 'circle') { drawingData.latlng = layer.getLatLng(); drawingData.radius = layer.getRadius(); }
            else { console.warn("Unknown layer type:", layerType); delete localDrawings[drawingId]; drawnItems.removeLayer(layer); return; }
            if (socket && socket.connected) socket.emit('add_drawing', drawingData);
            else console.warn("Socket not connected. Drawing is local only.");
        });
        map.on(L.Draw.Event.DELETED, function (event) { /* ... Tu código existente ... */ 
            event.layers.eachLayer(function (layer) {
                if (layer.drawingId && localDrawings[layer.drawingId]) {
                    delete localDrawings[layer.drawingId];
                    if (socket && socket.connected) socket.emit('remove_drawing', layer.drawingId);
                }
            });
        });
        function clearAllDrawings() { /* ... Tu código existente ... */ 
            if (socket && socket.connected) socket.emit('clear_drawings');
            else { clearLocalDrawings(false); alert("Not connected. Drawings cleared locally."); }
        }
        function addDrawingToMap(drawingData, emitToServer = false) { /* ... Tu código existente ... */ 
            if (!drawingData || !drawingData.id || localDrawings[drawingData.id]) return; let layer; const opts = { color: '#3388ff', weight: 3 };
            try {
                switch (drawingData.type) {
                    case 'polyline': layer = L.polyline(drawingData.latlngs, opts); break;
                    case 'polygon': layer = L.polygon(drawingData.latlngs, opts); break;
                    case 'rectangle': layer = L.rectangle(L.latLngBounds(drawingData.latlngs), opts); break;
                    case 'circle': layer = L.circle(drawingData.latlng, { ...opts, radius: drawingData.radius }); break;
                    default: console.error("Unknown drawing type:", drawingData.type); return;
                }
            } catch (e) { console.error("Error creating drawing:", e, drawingData); return; }
            layer.drawingId = drawingData.id; localDrawings[drawingData.id] = layer; drawnItems.addLayer(layer);
        }
        function removeDrawingFromMap(drawingId, emitToServer = false) { /* ... Tu código existente ... */ 
            if (!localDrawings[drawingId]) return;
            if (drawnItems.hasLayer(localDrawings[drawingId])) drawnItems.removeLayer(localDrawings[drawingId]);
            delete localDrawings[drawingId];
        }
        function clearLocalDrawings(emitToServer = false) { /* ... Tu código existente ... */ drawnItems.clearLayers(); localDrawings = {};}

        const drawControl = new L.Control.Draw({ /* ... Tu código existente ... */ 
            draw: { polyline: { shapeOptions: { color: '#f357a1', weight: 4 } }, polygon: { shapeOptions: { color: '#f357a1', weight: 4 } }, rectangle: { shapeOptions: { color: '#f357a1', weight: 4 } }, circle: { shapeOptions: { color: '#f357a1', weight: 4 } }, marker: false },
            edit: { featureGroup: drawnItems, remove: true }
        });
        function enableDrawing() { /* ... Tu código existente ... */ map.addControl(drawControl); currentMarkerType = null; document.querySelectorAll('#menuItems button').forEach(b => b.classList.remove('selected'));}
        function disableDrawing() { /* ... Tu código existente ... */ 
            try { // Añadido try-catch por si alguna toolbar no está activa
                if (drawControl._toolbars.draw && drawControl._toolbars.draw._activeMode) drawControl._toolbars.draw._modes[drawControl._toolbars.draw._activeMode.handler._type].handler.disable();
                if (drawControl._toolbars.edit && drawControl._toolbars.edit._activeMode) {
                    if (drawControl._toolbars.edit._modes.edit && drawControl._toolbars.edit._modes.edit.handler.enabled()) drawControl._toolbars.edit._modes.edit.handler.disable();
                    if (drawControl._toolbars.edit._modes.remove && drawControl._toolbars.edit._modes.remove.handler.enabled()) drawControl._toolbars.edit._modes.remove.handler.disable();
                }
            } catch (e) { console.warn("Minor error disabling draw controls:", e); }
            map.removeControl(drawControl);
        }

        // --- POI List (sin cambios, usa el sistema de coordenadas preservado) ---
        const poiList = [
            { name: "Saint Philippe (HQ-N)", coords: [1755.67, 666.50], image: "images/saint_philippe.jpg" },
			{ name: "Saint-Pierre (HQ-S)", coords: [257.16, 1619.62], image: "images/saint_pierre.jpg" },
			{ name: "Montignac", coords: [1140.54, 712], image: "images/montignac.jpg" },
			{ name: "Tower Entre-Deux", coords: [1181.54, 883], image: "images/twdeux.jpg" },
			{ name: "Provins", coords: [1007.59, 840], image: "images/provins.jpg" },
			{ name: "MB Levie", coords: [716.11, 1194], image: "images/bmlevie.jpg" },
			{ name: "Levie", coords: [780.55, 1185.50], image: "images/levie.jpg" }
            // Asegúrate que las rutas a las imágenes sean correctas
		];
		poiList.forEach(poi => {
			const marker = L.marker(poi.coords, { icon: customIcons.loot }).addTo(map); // Coords son [Y,X]
			marker.bindPopup(`<div style="padding:0;margin:0;"><img src="${poi.image}" alt="${poi.name}" style="width:400px;height:auto;"></div>`, { maxWidth: "auto", className: "custom-popup" });
		});

        // --- Rotación, Brújula, Sidebar, Modales (sin cambios relevantes) ---
        const compass = document.getElementById('compass');
        compass.addEventListener('click', () => { rotateMap(90); });
        let currentAngle = 0; const directions = ['N', 'E', 'S', 'W'];
        function rotateMap(angle) { /* Tu código de rotación aquí */ }
        const sidebar = document.getElementById('sidebar');
        const toggleMenuButton = document.getElementById('toggleMenu');
        toggleMenuButton.addEventListener('click', () => { sidebar.classList.toggle('hidden'); setTimeout(() => map.invalidateSize(), 300); });
        function openAbout() { document.getElementById('modal').classList.add('visible'); }
        function closeAbout() { document.getElementById('modal').classList.remove('visible'); }
        function openHelp() { document.getElementById('help-modal').classList.add('visible'); }
        function closeHelp() { document.getElementById('help-modal').classList.remove('visible'); }

        // --- MiniMap ---
        const minimapContainer = document.getElementById('minimapContainer');
        const toggleMinimapButton = document.getElementById('toggleMinimap');
        const closeMinimapButton = document.getElementById('closeMinimap');
        let minimap = null; // Instancia del minimapa
        let minimapLayer = null; // Capa de teselas del minimapa (TileLayer)

        toggleMinimapButton.addEventListener('click', () => {
            const isVisible = minimapContainer.style.display !== 'none';
            minimapContainer.style.display = isVisible ? 'none' : 'block';
            if (!isVisible && minimap) minimap.invalidateSize();
        });
        closeMinimapButton.addEventListener('click', () => { minimapContainer.style.display = 'none'; });

        try {
            minimap = L.map('minimap', {
                crs: L.CRS.Simple,
                zoomControl: false, attributionControl: false,
                maxBounds: MAP_CRS_BOUNDS,
                maxBoundsViscosity: 1.0,
                gestureHandling: true, dragging: false, scrollWheelZoom: false, doubleClickZoom: false,
                minZoom: MAP_MIN_ZOOM, // O un valor fijo más bajo si prefieres, ej. 0
                maxZoom: MAP_MAX_ZOOM - 2 // Ejemplo: Minimapa muestra hasta zoom 2 (de teselas 0-4)
                                        // Ajusta para que el minimapa se vea bien y sea útil.
            });
            // La capa del minimapa (minimapLayer) se añade/actualiza en loadMap()
            // Establece una vista inicial, se ajustará con fitBounds() en loadMap()
            minimap.setView([MAP_CRS_BOUNDS[1][0] / 2, MAP_CRS_BOUNDS[1][1] / 2], minimap.options.minZoom);

            const viewRect = L.rectangle(map.getBounds(), { color: "#ff7800", weight: 1, interactive: false }).addTo(minimap);
            map.on('move zoom', () => {
                viewRect.setBounds(map.getBounds());
                minimap.setView(map.getCenter(), minimap.getZoom(), { animate: false }); // Sincroniza el centro
            });
            minimap.on('click', (e) => { map.setView(e.latlng, map.getZoom()); }); // Clic en minimapa mueve mapa principal

        } catch (error) {
            console.error("Error initializing minimap:", error);
            if(toggleMinimapButton) toggleMinimapButton.style.display = 'none';
            if(minimapContainer) minimapContainer.style.display = 'none';
        }

        // --- Inicio de la Aplicación ---
        document.addEventListener('DOMContentLoaded', () => {
            const mapSelector = document.getElementById('mapSelector');
            let initialMapId = "mapa1"; // Fallback por si el selector está vacío

            if (mapSelector && mapSelector.options.length > 0) {
                initialMapId = mapSelector.value; // Valor del <option> seleccionado (por PHP o el primero)
            } else {
                console.warn("ForgeNEX Map Script: Map selector empty or not found. Using fallback 'mapa1'. Ensure PHP generates options and 'maps/tiles/mapa1/' exists.");
                if (mapSelector) { // Si el <select> existe pero está vacío
                    const defaultOption = document.createElement('option');
                    defaultOption.value = "mapa1";
                    defaultOption.textContent = "Default Map (mapa1)";
                    defaultOption.selected = true;
                    mapSelector.appendChild(defaultOption);
                }
            }
            
            loadMap(initialMapId); // Carga el mapa de teselas inicial

            currentRoomId = getRoomIdFromUrl();
            if (!currentRoomId) {
                currentRoomId = generateRoomIdAndSetUrl();
                // alert(`Generated new collaboration session ID: ${currentRoomId}. Share URL to collaborate!`); // Opcional: alertar
                connectWebSocket();
            } else {
                console.log(`Joining existing session: ${currentRoomId}`);
                connectWebSocket();
            }

            zoomInfo.textContent = map.getZoom();
            map.on('zoomend', () => { zoomInfo.textContent = map.getZoom(); });
            map.on('mousemove', (e) => {
                // En CRS.Simple, lat es Y (de arriba a abajo), lng es X (de izquierda a derecha)
                mouseCoordinates.textContent = `Y: ${e.latlng.lat.toFixed(1)}, X: ${e.latlng.lng.toFixed(1)}`;
            });
            sidebar.classList.add('hidden'); // Ocultar sidebar inicialmente
        });
    </script>
</body>
</html>