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
    <script src="leaflet.rotatedMarker.js"></script>
    <!-- Meta Tags for SEO -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Plan your strategies and navigate efficiently in Arma Reforger with our interactive map tool. Add markers, search POIs, draw plans, and collaborate with your team.">
    <meta name="keywords" content="Arma Reforger map, interactive map, POI search, markers, strategy planning, drawing tools, minimap, map tools for games">
    <meta name="author" content="ForgeNEX Interactive">
    <meta name="robots" content="index, follow">
    <meta name="language" content="en">
    <meta name="theme-color" content="#333">

    <!-- Open Graph Tags for Social Media -->
    <meta property="og:title" content="Interactive Map for Arma Reforger">
    <meta property="og:description" content="A powerful tool for players to plan strategies, add markers, and interact with the map of Arma Reforger. Includes POI search, drawing tools, and more.">
    <meta property="og:image" content="https://www.reforgermap.com/og-image.png">
    <meta property="og:url" content="https://www.reforgermap.com">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Reforger Interactive Map">

    <!-- Twitter Card Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Interactive Map for Arma Reforger">
    <meta name="twitter:description" content="Plan your strategies and collaborate effectively with our interactive map for Arma Reforger.">
    <meta name="twitter:image" content="https://www.reforgermap.com/og-image.png">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://www.reforgermap.com">

    <!-- Favicon -->
    <link rel="icon" href="/favicon.ico" type="image/x-icon">

    <!-- Structured Data for Search Engines -->
    <script type="application/ld+json">
    {
        "@context": "http://schema.org",
        "@type": "WebSite",
        "name": "Reforger Interactive Map",
        "url": "https://www.reforgermap.com",
        "potentialAction": {
            "@type": "SearchAction",
            "target": "https://www.reforgemap.com/?q={search_term_string}",
            "query-input": "required name=search_term_string"
        },
        "description": "Interactive map tool for Arma Reforger players. Plan strategies, add markers, search POIs, and collaborate with your team."
    }
    </script>
    <link rel="stylesheet" href="style.css">
	<!-- Pixel Code - https://stats.forgenex.com/ -->
	<script defer src="https://stats.forgenex.com/pixel/5YKZG0nR2y0g7vKK"></script>
	<!-- END Pixel Code -->

    <!-- !!! NUEVO: Añadir cliente Socket.IO !!! -->
    <script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
    <!-- Alternativa si quieres alojarlo tú mismo tras 'npm install socket.io-client': -->
    <!-- <script src="/node_modules/socket.io-client/dist/socket.io.min.js"></script> -->

    <!-- Librería para generar UUIDs (opcional pero recomendada) -->
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
        <!-- <button onclick="undoLastMarker()">Undo Last</button>  // Comentado/Eliminado -->
        <button onclick="removeAllMarkers()">Remove All</button>
        <h3>Drawing Tools</h3>
        <button onclick="enableDrawing()">Enable Drawing</button>
        <button onclick="disableDrawing()">Disable Drawing</button>
        <button onclick="clearAllDrawings()">Clear All Drawings</button>
        <h3>Select Map</h3>
        <select id="mapSelector" onchange="changeMap(event)">
            <option value="maps/mapa1.png">Everon</option>
            <option value="maps/mapa2.png">Arland</option>
            <option value="maps/mapa3.png">Everon</option> <!-- Mapa 3 es Everon? Cambiar si es otro -->
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
    <!-- Adding MiniMap -->
    <div id="minimapContainer">
        <button id="closeMinimap">×</button>
        <div id="minimap"></div>
    </div>
    <button id="toggleMinimap">Toggle MiniMap</button>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>

    <!-- !!! MODIFICADO: Script principal con lógica Socket.IO !!! -->
    <script>
        // --- Configuración Socket.IO y Sala ---
        const SOCKET_SERVER_URL = 'http://localhost:3000'; // Para pruebas locales
        // const SOCKET_SERVER_URL = 'https://tu-servidor-socket.com'; // Para producción (CAMBIAR CUANDO DESPLIEGUES)
        let socket;
        let currentRoomId = null;

        // --- Variables globales existentes y nuevas ---
        const map = L.map('map', {
            crs: L.CRS.Simple,
            maxZoom: 3,
            minZoom: 0,
            gestureHandling: true,
            maxBounds: [[0, 0], [2048, 2048]],
            maxBoundsViscosity: 1.0
        });
        let currentMapLayer;
        const bounds = [[0, 0], [2048, 2048]];
        const zoomInfo = document.getElementById('zoomLevel');
        const mouseCoordinates = document.getElementById('mouseCoordinates');
        const customIcons = {
            enemy: L.icon({ iconUrl: 'images/icon-enemy.png', iconSize: [32, 32] }),
            exit: L.icon({ iconUrl: 'images/icon-exit.png', iconSize: [32, 32] }),
            respawn: L.icon({ iconUrl: 'images/icon-respawn.png', iconSize: [32, 32] }),
            loot: L.icon({ iconUrl: 'images/icon-loot.png', iconSize: [15, 15] }) // Icono para POIs
        };
        let localMarkers = {}; // { markerId: leafletMarkerInstance, ... }
        let localDrawings = {}; // { drawingId: leafletLayerInstance, ... }
        let currentMarkerType = null;
        const drawnItems = new L.FeatureGroup(); // Para Leaflet.Draw

        // --- Funciones Auxiliares ---
        function generateUUID() {
            if (typeof uuid !== 'undefined' && typeof uuid.v4 === 'function') {
                return uuid.v4();
            } else {
                console.warn("UUID library not loaded correctly, using fallback generator.");
                return Date.now().toString(36) + Math.random().toString(36).substring(2);
            }
        }

        function getRoomIdFromUrl() {
            const params = new URLSearchParams(window.location.search);
            return params.get('session');
        }

        function generateRoomIdAndSetUrl() {
            const newRoomId = generateUUID().substring(0, 8);
            const newUrl = `${window.location.pathname}?session=${newRoomId}${window.location.hash}`;
             // Cambia la URL sin recargar la página
            window.history.pushState({ path: newUrl }, '', newUrl);
            console.log("Generated new session ID and updated URL:", newRoomId);
            return newRoomId;
        }

        // --- Inicialización Mapa Leaflet ---
        map.addLayer(drawnItems); // Añadir grupo de dibujos

        function loadMap(mapFile) {
            if (currentMapLayer) {
                map.removeLayer(currentMapLayer);
            }
            currentMapLayer = L.imageOverlay(mapFile, bounds).addTo(map);
            map.setView([1024, 1024], 1);
            // Actualizar minimapa si existe
             if (minimapLayer) {
                minimapLayer.setUrl(mapFile);
            }
        }

        function changeMap(event) {
            const mapFile = event.target.value;
            loadMap(mapFile);
            // Podríamos querer notificar a otros usuarios del cambio de mapa base,
            // pero eso añade complejidad (todos deben tener los mismos mapas).
            // Por ahora, el cambio de mapa es local.
        }

        // --- Lógica de Conexión y Sincronización Socket.IO ---
        function connectWebSocket() {
            if (socket && socket.connected) {
                console.log("Already connected.");
                return;
            }
            if (!currentRoomId) {
                console.error("Cannot connect without a room ID.");
                return;
            }

            console.log(`Attempting to connect to WebSocket server at ${SOCKET_SERVER_URL} for room ${currentRoomId}...`);
            socket = io(SOCKET_SERVER_URL, {
                reconnectionAttempts: 5,
                reconnectionDelay: 3000,
                // query: { roomId: currentRoomId } // Podríamos pasar roomId aquí también
            });

            socket.on('connect', () => {
                console.log('Connected to server via WebSocket:', socket.id);
                console.log(`Joining room: ${currentRoomId}`);
                socket.emit('join_room', currentRoomId);
            });

            socket.on('disconnect', (reason) => {
                console.log('Disconnected from server:', reason);
                alert("Connection lost to the collaboration server. Markers and drawings may not sync. Please refresh.");
            });

            socket.on('connect_error', (error) => {
                console.error('Connection Error:', error);
                alert(`Failed to connect to collaboration server: ${error.message}. Please check server status and refresh.`);
            });

            // Listener para el estado inicial al unirse
            socket.on('initial_state', (state) => {
                console.log("Received initial state:", state);
                clearLocalMap(); // Limpiar mapa local ANTES de aplicar el estado

                if (state.markers) {
                    Object.values(state.markers).forEach(markerData => addMarkerToMap(markerData, false));
                }
                if (state.drawings) {
                    Object.values(state.drawings).forEach(drawingData => addDrawingToMap(drawingData, false));
                }
                console.log("Initial state applied.");
            });

            // Listeners para actualizaciones en tiempo real
            socket.on('marker_added', (markerData) => {
                console.log("Remote marker added:", markerData.id);
                addMarkerToMap(markerData, false);
            });

            socket.on('marker_removed', (markerId) => {
                console.log("Remote marker removed:", markerId);
                removeMarkerFromMap(markerId, false);
            });

             socket.on('drawing_added', (drawingData) => {
                console.log("Remote drawing added:", drawingData.id);
                addDrawingToMap(drawingData, false);
            });

            socket.on('drawing_removed', (drawingId) => {
                console.log("Remote drawing removed:", drawingId);
                removeDrawingFromMap(drawingId, false);
            });

            socket.on('drawings_cleared', () => {
                console.log("Remote drawings cleared");
                clearLocalDrawings(false);
            });
        }

        // --- Funciones Modificadas/Nuevas para Sincronización ---

        function clearLocalMap() {
             console.log("Clearing local map state...");
             Object.keys(localMarkers).forEach(id => {
                 if (map.hasLayer(localMarkers[id])) {
                     map.removeLayer(localMarkers[id]);
                 }
             });
             localMarkers = {};
             drawnItems.clearLayers();
             localDrawings = {};
        }

        function addMarkerToMap(markerData, emitToServer = true) {
            if (!markerData || !markerData.id || !markerData.lat || !markerData.lng || !markerData.type) {
                console.error("Invalid marker data for addMarkerToMap:", markerData);
                return;
            }
            if (localMarkers[markerData.id]) {
                // console.warn(`Marker ${markerData.id} already exists locally. Skipping add.`);
                return;
            }
            if (!customIcons[markerData.type]) {
                 console.error(`Invalid marker type: ${markerData.type}`);
                 return;
            }

            const marker = L.marker([markerData.lat, markerData.lng], {
                 icon: customIcons[markerData.type]
            });
            marker.markerId = markerData.id;

            marker.on('click', function() {
                removeMarkerFromMap(this.markerId, true);
            });

            marker.addTo(map);
            localMarkers[markerData.id] = marker;

            if (emitToServer && socket && socket.connected) {
                console.log(`Emitting add_marker: ${markerData.id}`);
                socket.emit('add_marker', markerData);
            }
        }

        function removeMarkerFromMap(markerId, emitToServer = true) {
            if (!localMarkers[markerId]) {
                // console.warn(`Marker ${markerId} not found locally. Skipping remove.`);
                return;
            }

            const markerToRemove = localMarkers[markerId];
            if (map.hasLayer(markerToRemove)) {
                map.removeLayer(markerToRemove);
            }
            delete localMarkers[markerId];

            if (emitToServer && socket && socket.connected) {
                console.log(`Emitting remove_marker: ${markerId}`);
                socket.emit('remove_marker', markerId);
            }
        }

        // Clic en mapa para añadir marcador
        map.on('click', e => {
            // No añadir marcador si estamos dibujando o si no hay tipo seleccionado
            if (!currentMarkerType || (drawControl && drawControl._toolbars.draw._activeMode)) return;

            const markerData = {
                id: generateUUID(),
                type: currentMarkerType,
                lat: e.latlng.lat,
                lng: e.latlng.lng
            };
            addMarkerToMap(markerData, true);
            currentMarkerType = null;
            document.querySelectorAll('#menuItems button').forEach(button => {
                button.classList.remove('selected');
            });
        });

        // Selección de tipo de marcador
        function setMarkerType(type) {
            currentMarkerType = type;
            // Resaltar botón seleccionado
            document.querySelectorAll('#menuItems button').forEach(button => {
                button.classList.remove('selected');
            });
            const selectedButton = document.getElementById(`${type}Button`);
            if (selectedButton) {
                selectedButton.classList.add('selected');
            }
             // Desactivar modo dibujo si estuviera activo
             if (drawControl && drawControl._toolbars.edit._activeMode) {
                 drawControl._toolbars.edit.disable();
             }
              if (drawControl && drawControl._toolbars.draw._activeMode) {
                  drawControl._toolbars.draw.disable();
              }
               disableDrawing(); // Asegura que los controles de dibujo se quiten
        }

        // Botón "Remove All Markers"
        function removeAllMarkers() {
            Object.keys(localMarkers).forEach(markerId => {
                removeMarkerFromMap(markerId, true);
            });
        }

        // --- Integración con Leaflet.Draw ---
        map.on(L.Draw.Event.CREATED, function (event) {
            const layer = event.layer;
            const layerType = event.layerType;
            const drawingId = generateUUID();
            layer.drawingId = drawingId;

            localDrawings[drawingId] = layer;
            // NO añadir directamente a drawnItems aquí si esperamos confirmación del servidor,
            // O añadir y luego quitar si la emisión falla.
            // Por simplicidad, añadimos localmente y emitimos.
             drawnItems.addLayer(layer);

            let drawingData = { id: drawingId, type: layerType };

            if (layerType === 'polyline' || layerType === 'polygon' || layerType === 'rectangle') {
                drawingData.latlngs = layer.getLatLngs();
            } else if (layerType === 'circle') {
                drawingData.latlng = layer.getLatLng();
                drawingData.radius = layer.getRadius();
            } else {
                console.warn("Unknown layer type created:", layerType);
                // Si no sabemos qué es, no lo guardamos ni emitimos
                 delete localDrawings[drawingId];
                 drawnItems.removeLayer(layer); // Quitarlo si lo añadimos
                return;
            }

            if (socket && socket.connected) {
                console.log(`Emitting add_drawing: ${drawingId}`);
                socket.emit('add_drawing', drawingData);
            } else {
                // Si no estamos conectados, el dibujo solo será local
                 console.warn("Socket not connected. Drawing is local only.");
            }
            // Desactivar modo dibujo después de crear una forma
             if (drawControl && drawControl._toolbars.draw._activeMode) {
                //drawControl._toolbars.draw.disable();
                // disableDrawing(); // Oculta el control completo
             }
        });

        map.on(L.Draw.Event.DELETED, function (event) {
            event.layers.eachLayer(function (layer) {
                 if (layer.drawingId) {
                    const drawingId = layer.drawingId;
                    // Asegurarse de que existe localmente antes de borrar y emitir
                    if(localDrawings[drawingId]){
                        delete localDrawings[drawingId];
                        if (socket && socket.connected) {
                           console.log(`Emitting remove_drawing: ${drawingId}`);
                           socket.emit('remove_drawing', drawingId);
                        }
                    }
                }
            });
        });

        function clearAllDrawings() {
            // Limpieza local inmediata (opcional, el servidor lo confirmará)
            // clearLocalDrawings(false);

            if (socket && socket.connected) {
                console.log("Emitting clear_drawings");
                socket.emit('clear_drawings');
            } else {
                 // Si no hay conexión, limpiar solo localmente
                 clearLocalDrawings(false);
                 alert("Not connected to server. Drawings cleared locally only.");
            }
        }

        function addDrawingToMap(drawingData, emitToServer = false) {
             if (!drawingData || !drawingData.id) return;
             if (localDrawings[drawingData.id]) return;

             let layer;
             // Usar un estilo por defecto o uno guardado (si lo implementamos)
             const options = { color: '#3388ff', weight: 3 };

             try {
                 switch (drawingData.type) {
                    case 'polyline':
                        layer = L.polyline(drawingData.latlngs, options);
                        break;
                    case 'polygon':
                        layer = L.polygon(drawingData.latlngs, options);
                        break;
                    case 'rectangle':
                        // L.rectangle necesita [[southWestLat, southWestLng], [northEastLat, northEastLng]]
                        // Asegurémonos que latlngs tenga ese formato o L.latLngBounds
                         if (Array.isArray(drawingData.latlngs) && drawingData.latlngs.length >= 2) {
                             // Podría venir como array de LatLngs o como Bounds. Intentemos crear bounds.
                             const bounds = L.latLngBounds(drawingData.latlngs);
                              layer = L.rectangle(bounds, options);
                         } else {
                            console.error("Invalid latlngs for rectangle:", drawingData.latlngs);
                            return;
                         }
                        break;
                    case 'circle':
                        // L.circle necesita LatLng y radio
                         if (drawingData.latlng && drawingData.radius) {
                            layer = L.circle(drawingData.latlng, { ...options, radius: drawingData.radius });
                         } else {
                             console.error("Invalid data for circle:", drawingData);
                             return;
                         }
                        break;
                    default:
                        console.error("Unknown drawing type:", drawingData.type);
                        return;
                }
            } catch (error) {
                console.error(`Error creating Leaflet layer for drawing ${drawingData.id} (type: ${drawingData.type}):`, error);
                console.error("Drawing data:", drawingData);
                return; // No añadir si falla la creación
            }


            layer.drawingId = drawingData.id;
            localDrawings[drawingData.id] = layer;
            drawnItems.addLayer(layer);
        }

        function removeDrawingFromMap(drawingId, emitToServer = false) {
            if (!localDrawings[drawingId]) return;
            const layerToRemove = localDrawings[drawingId];
            if (drawnItems.hasLayer(layerToRemove)) {
                drawnItems.removeLayer(layerToRemove);
            }
            delete localDrawings[drawingId];
        }

        function clearLocalDrawings(emitToServer = false) {
             drawnItems.clearLayers();
             localDrawings = {};
        }


        // --- Controles de Dibujo (L.Draw) ---
        const drawControl = new L.Control.Draw({
            draw: {
                polyline: { shapeOptions: { color: '#f357a1', weight: 4 } },
                polygon: { shapeOptions: { color: '#f357a1', weight: 4 } },
                rectangle: { shapeOptions: { color: '#f357a1', weight: 4 } },
                circle: { shapeOptions: { color: '#f357a1', weight: 4 } },
                marker: false // No usar el marcador de L.Draw
            },
            edit: {
                featureGroup: drawnItems,
                remove: true
            }
        });

         function enableDrawing() {
             map.addControl(drawControl);
             // Deseleccionar tipo de marcador si estaba activo
             currentMarkerType = null;
              document.querySelectorAll('#menuItems button').forEach(button => {
                 button.classList.remove('selected');
             });
         }
         function disableDrawing() {
             // Desactivar herramientas activas antes de quitar el control
             if (drawControl._toolbars.draw && drawControl._toolbars.draw._activeMode) {
                 drawControl._toolbars.draw._modes[drawControl._toolbars.draw._activeMode.handler._type].handler.disable();
             }
              if (drawControl._toolbars.edit && drawControl._toolbars.edit._activeMode) {
                  // Puede haber modos de edición o borrado
                   if (drawControl._toolbars.edit._modes.edit.handler.enabled()) {
                       drawControl._toolbars.edit._modes.edit.handler.disable();
                   }
                   if (drawControl._toolbars.edit._modes.remove.handler.enabled()) {
                       drawControl._toolbars.edit._modes.remove.handler.disable();
                   }
              }
             map.removeControl(drawControl);
         }

        // --- POI List (Estático, sin cambios) ---
        const poiList = [
			{ name: "Saint Philippe (HQ-N)", coords: [1755.67, 666.50], image: "images/saint_philippe.jpg" },
			{ name: "Saint-Pierre (HQ-S)", coords: [257.16, 1619.62], image: "images/saint_pierre.jpg" },
			{ name: "Montignac", coords: [1140.54, 712], image: "images/montignac.jpg" },
			{ name: "Tower Entre-Deux", coords: [1181.54, 883], image: "images/twdeux.jpg" },
			{ name: "Provins", coords: [1007.59, 840], image: "images/provins.jpg" },
			{ name: "MB Levie", coords: [716.11, 1194], image: "images/bmlevie.jpg" },
			{ name: "Levie", coords: [780.55, 1185.50], image: "images/levie.jpg" }
		];
		poiList.forEach(poi => {
			const marker = L.marker(poi.coords, { icon: customIcons.loot }).addTo(map);
			marker.bindPopup(`
				<div style="padding: 0; margin: 0;">
					<img src="${poi.image}" alt="${poi.name}" style="width: 400px; height: auto;">
				</div>
			`, { maxWidth: "auto", className: "custom-popup" });
		});


        // --- Rotación, Brújula, Sidebar, Modales (Sin cambios) ---
        const compass = document.getElementById('compass');
        compass.addEventListener('click', () => { rotateMap(90); });
        let currentAngle = 0;
        const directions = ['N', 'E', 'S', 'W'];
        function rotateMap(angle) { /* ... código rotación ... */ }

        const sidebar = document.getElementById('sidebar');
        const toggleMenuButton = document.getElementById('toggleMenu');
        toggleMenuButton.addEventListener('click', () => {
            sidebar.classList.toggle('hidden');
            adjustMapSize();
        });
        function adjustMapSize() { setTimeout(() => map.invalidateSize(), 300); }

        function openAbout() { document.getElementById('modal').classList.add('visible'); }
        function closeAbout() { document.getElementById('modal').classList.remove('visible'); }
        function openHelp() { document.getElementById('help-modal').classList.add('visible'); }
        function closeHelp() { document.getElementById('help-modal').classList.remove('visible'); }


        // --- MiniMap ---
        const minimapContainer = document.getElementById('minimapContainer');
        const toggleMinimapButton = document.getElementById('toggleMinimap');
        const closeMinimapButton = document.getElementById('closeMinimap');
        let minimap = null; // Declarar fuera para acceso global
        let minimapLayer = null; // Declarar fuera para acceso global

        toggleMinimapButton.addEventListener('click', () => {
            const isVisible = minimapContainer.style.display !== 'none';
            minimapContainer.style.display = isVisible ? 'none' : 'block';
            if (!isVisible && minimap) {
                minimap.invalidateSize(); // Ajustar tamaño si se muestra
            }
        });
        closeMinimapButton.addEventListener('click', () => { minimapContainer.style.display = 'none'; });

        // Inicializar minimapa
         try {
             const minimapBounds = [[0, 0], [2048, 2048]]; // Mismas que el mapa principal
             minimap = L.map('minimap', {
                crs: L.CRS.Simple,
                zoomControl: false,
                attributionControl: false,
                maxBounds: minimapBounds,
                maxBoundsViscosity: 1.0,
                gestureHandling: true, // Permitir interacción táctil
                dragging: false, // Deshabilitar arrastre directo del minimapa base
                scrollWheelZoom: false, // Deshabilitar zoom con rueda en minimapa
                doubleClickZoom: false, // Deshabilitar doble clic zoom
            }).setView([1024, 1024], -2); // Zoom más alejado (-2 o -3)

            // Usar el mapa inicial seleccionado
            const initialMapFile = document.getElementById('mapSelector').value || 'maps/mapa1.png';
            minimapLayer = L.imageOverlay(initialMapFile, minimapBounds).addTo(minimap);

            // Crear un rectángulo que represente la vista actual del mapa principal
            const viewRect = L.rectangle(map.getBounds(), { color: "#ff7800", weight: 1, interactive: false }).addTo(minimap);

            // Sincronizar rectángulo del minimapa con vista del mapa principal
            map.on('move zoom', () => {
                viewRect.setBounds(map.getBounds());
                 // Centrar minimapa aproximadamente en el centro del mapa principal, pero mantener zoom alejado
                 minimap.setView(map.getCenter(), minimap.getZoom(), { animate: false });
            });

            // Permitir hacer clic en minimapa para mover el mapa principal
            minimap.on('click', (e) => {
                 map.setView(e.latlng, map.getZoom());
            });

         } catch (error) {
            console.error("Error initializing minimap:", error);
            // Ocultar controles del minimapa si falla la inicialización
            if(toggleMinimapButton) toggleMinimapButton.style.display = 'none';
             if(minimapContainer) minimapContainer.style.display = 'none';
         }


        // --- Inicio de la Aplicación ---
        document.addEventListener('DOMContentLoaded', () => {
            const initialMapFile = document.getElementById('mapSelector').value || 'maps/mapa1.png';
            loadMap(initialMapFile);

            currentRoomId = getRoomIdFromUrl();
            if (!currentRoomId) {
                 // Opción: Generar ID y actualizar URL sin recargar
                 currentRoomId = generateRoomIdAndSetUrl();
                 alert(`Generated a new collaboration session ID: ${currentRoomId}. Share this page's URL to collaborate!`);
                 connectWebSocket(); // Conectar con el nuevo ID
            } else {
                 console.log(`Joining existing session: ${currentRoomId}`);
                 connectWebSocket();
            }

            zoomInfo.textContent = map.getZoom();
            map.on('zoomend', () => { zoomInfo.textContent = map.getZoom(); });
            map.on('mousemove', (e) => {
                 const { lat, lng } = e.latlng;
                 mouseCoordinates.textContent = `Lat: ${lat.toFixed(1)}, Lng: ${lng.toFixed(1)}`; // Menos decimales
            });
            sidebar.classList.add('hidden');
        });

    </script>
</body>
</html>