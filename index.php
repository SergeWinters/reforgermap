<!DOCTYPE html>
<html lang="en">
<head>
    <title>Interactive Map with POI, Drawing Tools, for Arma Reforger</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>

    <link rel="stylesheet" href="https://unpkg.com/@raruto/leaflet-gesture-handling@latest/dist/leaflet-gesture-handling.min.css" type="text/css">
    <script src="https://unpkg.com/@raruto/leaflet-gesture-handling@latest/dist/leaflet-gesture-handling.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/leaflet-rotatedmarker@0.2.0/leaflet.rotatedMarker.js"></script>
	
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

    <script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
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
            <option value="maps/mapa1.png">Everon</option>
            <option value="maps/mapa2.png">Arland</option>
            <option value="maps/mapa3.png">Everon</option>
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

    <script>
        const SOCKET_SERVER_URL = 'http://localhost:3000'; 
        let socket;
        let currentRoomId = null;

        // CAMBIO: Definir gestureHandlingOptions para evitar carga de locales y error CORS
        const gestureHandlingOptionsConfig = {
            text: {
                touch: "Use two fingers to move the map", // Texto en inglés
                scroll: "Use ctrl + scroll to zoom the map", // Texto en inglés
                scrollMac: "Use \u2318 + scroll to zoom the map" // \u2318 es el símbolo Command ⌘
            }
            // Alternativamente, si quisieras forzar un idioma específico sin cargar dinámicamente:
            // locale: 'en' 
            // Pero definir `text` es la forma más segura de evitar cargas externas.
        };

        const map = L.map('map', {
            crs: L.CRS.Simple,
            maxZoom: 3,
            minZoom: 0,
            gestureHandling: true,
            gestureHandlingOptions: gestureHandlingOptionsConfig, // CAMBIO: Aplicar opciones aquí
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
            loot: L.icon({ iconUrl: 'images/icon-loot.png', iconSize: [15, 15] }) 
        };
        let localMarkers = {}; 
        let localDrawings = {}; 
        let currentMarkerType = null;
        const drawnItems = new L.FeatureGroup(); 

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
            window.history.pushState({ path: newUrl }, '', newUrl);
            console.log("Generated new session ID and updated URL:", newRoomId);
            return newRoomId;
        }

        map.addLayer(drawnItems); 

        function loadMap(mapFile) {
            if (currentMapLayer) {
                map.removeLayer(currentMapLayer);
            }
            currentMapLayer = L.imageOverlay(mapFile, bounds).addTo(map);
            map.setView([1024, 1024], 1);
            
            if (minimap && minimapLayer) { 
                minimapLayer.setUrl(mapFile);
                minimap.invalidateSize(); 
            }
        }

        function changeMap(event) {
            const mapFile = event.target.value;
            loadMap(mapFile);
        }

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

            socket.on('initial_state', (state) => {
                console.log("Received initial state:", state);
                clearLocalMap(); 

                if (state.markers) {
                    Object.values(state.markers).forEach(markerData => addMarkerToMap(markerData, false));
                }
                if (state.drawings) {
                    Object.values(state.drawings).forEach(drawingData => addDrawingToMap(drawingData, false));
                }
                console.log("Initial state applied.");
            });

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

        map.on('click', e => {
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

        function setMarkerType(type) {
            currentMarkerType = type;
            document.querySelectorAll('#menuItems button').forEach(button => {
                button.classList.remove('selected');
            });
            const selectedButton = document.getElementById(`${type}Button`);
            if (selectedButton) {
                selectedButton.classList.add('selected');
            }
             if (drawControl && drawControl._toolbars.edit._activeMode) {
                 drawControl._toolbars.edit.disable();
             }
              if (drawControl && drawControl._toolbars.draw._activeMode) {
                  drawControl._toolbars.draw.disable();
              }
               disableDrawing(); 
        }

        function removeAllMarkers() {
            Object.keys(localMarkers).forEach(markerId => {
                removeMarkerFromMap(markerId, true);
            });
        }

        map.on(L.Draw.Event.CREATED, function (event) {
            const layer = event.layer;
            const layerType = event.layerType;
            const drawingId = generateUUID();
            layer.drawingId = drawingId;

            localDrawings[drawingId] = layer;
            drawnItems.addLayer(layer);

            let drawingData = { id: drawingId, type: layerType };

            if (layerType === 'polyline' || layerType === 'polygon' || layerType === 'rectangle') {
                drawingData.latlngs = layer.getLatLngs();
            } else if (layerType === 'circle') {
                drawingData.latlng = layer.getLatLng();
                drawingData.radius = layer.getRadius();
            } else {
                console.warn("Unknown layer type created:", layerType);
                delete localDrawings[drawingId];
                drawnItems.removeLayer(layer); 
                return;
            }

            if (socket && socket.connected) {
                console.log(`Emitting add_drawing: ${drawingId}`);
                socket.emit('add_drawing', drawingData);
            } else {
                 console.warn("Socket not connected. Drawing is local only.");
            }
        });

        map.on(L.Draw.Event.DELETED, function (event) {
            event.layers.eachLayer(function (layer) {
                 if (layer.drawingId) {
                    const drawingId = layer.drawingId;
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
            if (socket && socket.connected) {
                console.log("Emitting clear_drawings");
                socket.emit('clear_drawings');
            } else {
                 clearLocalDrawings(false);
                 alert("Not connected to server. Drawings cleared locally only.");
            }
        }

        function addDrawingToMap(drawingData, emitToServer = false) {
             if (!drawingData || !drawingData.id) return;
             if (localDrawings[drawingData.id]) return;

             let layer;
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
                         if (Array.isArray(drawingData.latlngs) && drawingData.latlngs.length >= 2) {
                             const bounds = L.latLngBounds(drawingData.latlngs);
                              layer = L.rectangle(bounds, options);
                         } else {
                            console.error("Invalid latlngs for rectangle:", drawingData.latlngs);
                            return;
                         }
                        break;
                    case 'circle':
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
                return; 
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

        const drawControl = new L.Control.Draw({
            draw: {
                polyline: { shapeOptions: { color: '#f357a1', weight: 4 } },
                polygon: { shapeOptions: { color: '#f357a1', weight: 4 } },
                rectangle: { shapeOptions: { color: '#f357a1', weight: 4 } },
                circle: { shapeOptions: { color: '#f357a1', weight: 4 } },
                marker: false 
            },
            edit: {
                featureGroup: drawnItems,
                remove: true
            }
        });

         function enableDrawing() {
             map.addControl(drawControl);
             currentMarkerType = null;
              document.querySelectorAll('#menuItems button').forEach(button => {
                 button.classList.remove('selected');
             });
         }
         function disableDrawing() {
             if (drawControl._toolbars.draw && drawControl._toolbars.draw._activeMode) {
                 drawControl._toolbars.draw._modes[drawControl._toolbars.draw._activeMode.handler._type].handler.disable();
             }
              if (drawControl._toolbars.edit && drawControl._toolbars.edit._activeMode) {
                   if (drawControl._toolbars.edit._modes.edit.handler.enabled()) {
                       drawControl._toolbars.edit._modes.edit.handler.disable();
                   }
                   if (drawControl._toolbars.edit._modes.remove.handler.enabled()) {
                       drawControl._toolbars.edit._modes.remove.handler.disable();
                   }
              }
             map.removeControl(drawControl);
         }

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

        const minimapContainer = document.getElementById('minimapContainer');
        const toggleMinimapButton = document.getElementById('toggleMinimap');
        const closeMinimapButton = document.getElementById('closeMinimap');
        let minimap = null; 
        let minimapLayer = null; 
        let minimapViewRect = null; 

        toggleMinimapButton.addEventListener('click', () => {
            const isVisible = minimapContainer.style.display !== 'none';
            minimapContainer.style.display = isVisible ? 'none' : 'block';
            if (!isVisible && minimap) {
                minimap.invalidateSize(); 
            }
        });
        closeMinimapButton.addEventListener('click', () => { minimapContainer.style.display = 'none'; });

        function initializeMinimap() {
            try {
                const minimapBounds = [[0, 0], [2048, 2048]]; 
                minimap = L.map('minimap', {
                    crs: L.CRS.Simple,
                    zoomControl: false,
                    attributionControl: false,
                    maxBounds: minimapBounds,
                    maxBoundsViscosity: 1.0,
                    gestureHandling: true, 
                    gestureHandlingOptions: gestureHandlingOptionsConfig, // CAMBIO: Aplicar opciones aquí también para el minimapa
                    dragging: false, 
                    scrollWheelZoom: false, 
                    doubleClickZoom: false, 
                }).setView([1024, 1024], -2); 

                const initialMapFile = document.getElementById('mapSelector').value || 'maps/mapa1.png';
                minimapLayer = L.imageOverlay(initialMapFile, minimapBounds).addTo(minimap);

                minimapViewRect = L.rectangle(map.getBounds(), { color: "#ff7800", weight: 1, interactive: false }).addTo(minimap);

                map.on('move zoom', () => {
                    if (minimapViewRect) { 
                        minimapViewRect.setBounds(map.getBounds());
                    }
                    minimap.setView(map.getCenter(), minimap.getZoom(), { animate: false });
                });

                minimap.on('click', (e) => {
                    map.setView(e.latlng, map.getZoom());
                });

            } catch (error) {
                console.error("Error initializing minimap:", error);
                if(toggleMinimapButton) toggleMinimapButton.style.display = 'none';
                if(minimapContainer) minimapContainer.style.display = 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const initialMapFile = document.getElementById('mapSelector').value || 'maps/mapa1.png';
            loadMap(initialMapFile);
            initializeMinimap(); 

            currentRoomId = getRoomIdFromUrl();
            if (!currentRoomId) {
                 currentRoomId = generateRoomIdAndSetUrl();
                 alert(`Generated a new collaboration session ID: ${currentRoomId}. Share this page's URL to collaborate!`);
                 connectWebSocket(); 
            } else {
                 console.log(`Joining existing session: ${currentRoomId}`);
                 connectWebSocket();
            }

            zoomInfo.textContent = map.getZoom();
            map.on('zoomend', () => { zoomInfo.textContent = map.getZoom(); });
            map.on('mousemove', (e) => {
                 const { lat, lng } = e.latlng;
                 mouseCoordinates.textContent = `Lat: ${lat.toFixed(1)}, Lng: ${lng.toFixed(1)}`; 
            });
            sidebar.classList.add('hidden');
        });

    </script>
</body>
</html>