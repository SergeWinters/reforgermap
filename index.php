<?php
include 'config.php';
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
    <meta property="og:image" content="https://www.reforgemap.com/og-image.png">
    <meta property="og:url" content="https://www.reforgemap.com">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Reforger Interactive Map">

    <!-- Twitter Card Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Interactive Map for Arma Reforger">
    <meta name="twitter:description" content="Plan your strategies and collaborate effectively with our interactive map for Arma Reforger.">
    <meta name="twitter:image" content="https://www.reforgemap.com/og-image.png">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://www.reforgemap.com">

    <!-- Favicon -->
    <link rel="icon" href="/favicon.ico" type="image/x-icon">

    <!-- Structured Data for Search Engines -->
    <script type="application/ld+json">
    {
        "@context": "http://schema.org",
        "@type": "WebSite",
        "name": "Reforger Interactive Map",
        "url": "https://www.reforgemap.com",
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
</head>
<body>
    <button id="toggleMenu">☰</button>
    <div id="sidebar">
        <h3>Markers</h3>
        <div id="menuItems">
            <button id="enemyButton" onclick="setMarkerType('enemy')">Enemy</button>
            <button id="exitButton" onclick="setMarkerType('exit')">Attack</button>
            <button id="respawnButton" onclick="setMarkerType('respawn')">Respawn</button>
            <button id="lootButton" onclick="setMarkerType('loot')">GO</button>
        </div>
        <button onclick="undoLastMarker()">Undo Last</button>
        <button onclick="removeAllMarkers()">Remove All</button>
        <h3>POI Search</h3>
        <input type="text" id="poiSearch" placeholder="Search POI" oninput="searchPOI()" />
        <ul id="poiList">
            <!-- Dynamic list of POI -->
        </ul>
        <h3>Drawing Tools</h3>
        <button onclick="enableDrawing()">Enable Drawing</button>
        <button onclick="disableDrawing()">Disable Drawing</button>
        <button onclick="clearAllDrawings()">Clear All Drawings</button>
        <h3>Select Map</h3>
        <select id="mapSelector" onchange="changeMap(event)">
            <option value="maps/mapa1.png">Map 1</option>
            <option value="maps/mapa2.png">Map 2</option>
            <option value="maps/mapa3.png">Map 3</option>
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
            <a href="mailto:sergewint3rs@gmail.com" target="_blank">Contact</a>
        </div>
        <div>
            Version 6.0 - <a href="https://github.com/SergeWinters/reforgermap/commits/main/" target="_blank">Changelog</a>
        </div>
    </div>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
    <script>
        const map = L.map('map', {
            crs: L.CRS.Simple,
            maxZoom: 3,
            minZoom: 0,
            gestureHandling: true, // Activa el manejo táctil
            maxBounds: [[0, 0], [2048, 2048]],
            maxBoundsViscosity: 1.0
        });
        let currentMapLayer;
        const marker = L.marker([51.5, -0.09], { rotationAngle: 45 }).addTo(map); // Rotar marcador 45 grados
        const bounds = [[0, 0], [2048, 2048]];
        function loadMap(mapFile) {
            if (currentMapLayer) {
                map.removeLayer(currentMapLayer);
            }
            currentMapLayer = L.imageOverlay(mapFile, bounds).addTo(map);
            map.setView([1024, 1024], 1);
        }
        loadMap('maps/mapa1.png');

        function changeMap(event) {
            const mapFile = event.target.value;
            loadMap(mapFile);
        }

        const zoomInfo = document.getElementById('zoomLevel');
        map.on('zoomend', () => {
            zoomInfo.textContent = map.getZoom();
        });
        zoomInfo.textContent = map.getZoom();

        const mouseCoordinates = document.getElementById('mouseCoordinates');
        map.on('mousemove', (e) => {
            const { lat, lng } = e.latlng;
            mouseCoordinates.textContent = `Lat: ${lat.toFixed(2)}, Lng: ${lng.toFixed(2)}`;
        });

        const customIcons = {
            enemy: L.icon({ iconUrl: 'images/icon-enemy.png', iconSize: [32, 32] }),
            exit: L.icon({ iconUrl: 'images/icon-exit.png', iconSize: [32, 32] }),
            respawn: L.icon({ iconUrl: 'images/icon-respawn.png', iconSize: [32, 32] }),
            loot: L.icon({ iconUrl: 'images/icon-loot.png', iconSize: [32, 32] })
        };

        let markers = [];
        let currentMarkerType = null;

        function setMarkerType(type) {
            currentMarkerType = type;

            document.querySelectorAll('#menuItems button').forEach(button => {
                button.classList.remove('selected');
            });
            const selectedButton = document.querySelector(`[id="${type}Button"]`);
            if (selectedButton) {
                selectedButton.classList.add('selected');
            }
        }

        map.on('click', e => {
            if (!currentMarkerType) return;

            const lat = e.latlng.lat;
            const lng = e.latlng.lng;

            const marker = L.marker([lat, lng], { icon: customIcons[currentMarkerType] }).addTo(map).on('click', function () {
                map.removeLayer(marker);
                markers = markers.filter(m => m !== marker);
            });

            markers.push(marker);

            currentMarkerType = null;
            document.querySelectorAll('#menuItems button').forEach(button => {
                button.classList.remove('selected');
            });
        });

        function undoLastMarker() {
            if (markers.length > 0) {
                const lastMarker = markers.pop();
                map.removeLayer(lastMarker);
            } else {
                alert('No markers to undo.');
            }
        }

        function removeAllMarkers() {
            markers.forEach(marker => map.removeLayer(marker));
            markers = [];
        }

        // POI List
        const poiList = [
            { name: "Saint Philippe (HQ-N)", coords: [1755.67, 666.50] },
            { name: "Saint-Pierre (HQ-S)", coords: [250.16, 1592.62] },
            { name: "Montignac", coords: [1140.54, 712] },
            { name: "Provins", coords: [988.61, 846.50] },
            { name: "Levie", coords: [769.24, 1190] }
        ];

        // Populate POI List
        function populatePOIList() {
            const listElement = document.getElementById('poiList');
            listElement.innerHTML = '';
            poiList.forEach(poi => {
                const li = document.createElement('li');
                li.textContent = poi.name;
                li.style.cursor = 'pointer';
                li.onclick = () => {
                    map.setView(poi.coords, 3);
                    L.marker(poi.coords, { icon: customIcons.loot }).addTo(map).bindPopup(poi.name).openPopup();
                };
                listElement.appendChild(li);
            });
        }
        populatePOIList();

        function searchPOI() {
            const query = document.getElementById('poiSearch').value.toLowerCase();
            const listElement = document.getElementById('poiList');
            listElement.innerHTML = '';
            poiList.filter(poi => poi.name.toLowerCase().includes(query))
                .forEach(poi => {
                    const li = document.createElement('li');
                    li.textContent = poi.name;
                    li.style.cursor = 'pointer';
                    li.onclick = () => {
                        map.setView(poi.coords, 3);
                        L.marker(poi.coords, { icon: customIcons.loot }).addTo(map).bindPopup(poi.name).openPopup();
                    };
                    listElement.appendChild(li);
                });
        }

        // Drawing Tools
        const drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);

        const drawControl = new L.Control.Draw({
            draw: {
                polyline: true,
                polygon: true,
                rectangle: true,
                circle: true,
                marker: false
            },
            edit: {
                featureGroup: drawnItems,
                remove: true
            }
        });

        map.on(L.Draw.Event.CREATED, function (event) {
            const layer = event.layer;
            drawnItems.addLayer(layer);
        });

        function enableDrawing() {
            map.addControl(drawControl);
        }

        function disableDrawing() {
            map.removeControl(drawControl);
        }

        function clearAllDrawings() {
            drawnItems.clearLayers();
        }

        // Compass and Rotation
        const compass = document.getElementById('compass');
        compass.addEventListener('click', () => {
            rotateMap(90);
        });

        let currentAngle = 0;
        const directions = ['N', 'E', 'S', 'W'];
        function rotateMap(angle) {
            currentAngle = (currentAngle + angle) % 360;
            const mapContainer = document.getElementById('map');
            mapContainer.style.transform = `rotate(${currentAngle}deg)`;

            const directionIndex = Math.round((360 - currentAngle) / 90) % 4;
            document.getElementById('currentDirection').textContent = directions[directionIndex];
        }

        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const toggleMenuButton = document.getElementById('toggleMenu');
        toggleMenuButton.addEventListener('click', () => {
            sidebar.classList.toggle('hidden');
            adjustMapSize();
        });

        function adjustMapSize() {
            setTimeout(() => map.invalidateSize(), 300);
        }

        //toggle about dialog
        function openAbout() {
            const modal = document.getElementById('modal');
            modal.classList.add('visible');
        }

        function closeAbout() {
            const modal = document.getElementById('modal');
            modal.classList.remove('visible');
        }
        //toggle help dialog
        function openHelp() {
            const modal = document.getElementById('help-modal');
            modal.classList.add('visible');
        }

        function closeHelp() {
            const modal = document.getElementById('help-modal');
            modal.classList.remove('visible');
        }
    </script>
    <!-- Adding MiniMap -->
    <div id="minimapContainer">
        <button id="closeMinimap">×</button>
        <div id="minimap"></div>
    </div>
    <button id="toggleMinimap">Toggle MiniMap</button>
    <script>
        const minimapContainer = document.getElementById('minimapContainer');
        const toggleMinimapButton = document.getElementById('toggleMinimap');
        const closeMinimapButton = document.getElementById('closeMinimap');

        // Toggle button to show/hide the minimap container
        toggleMinimapButton.addEventListener('click', () => {
            const isVisible = minimapContainer.style.display !== 'none';
            minimapContainer.style.display = isVisible ? 'none' : 'block';
        });

        // Close button to hide the minimap container
        closeMinimapButton.addEventListener('click', () => {
            minimapContainer.style.display = 'none';
        });

        const minimapBounds = [[0, 0], [2048, 2048]];
        const minimap = L.map('minimap', {
            crs: L.CRS.Simple,
            zoomControl: false,
            attributionControl: false,
            maxBounds: minimapBounds,
            maxBoundsViscosity: 1.0,
        }).setView([1824, 1824], -1); // Zoom alejado para mostrar más del mapa principal

        const minimapLayer = L.imageOverlay('maps/mapa1.png', minimapBounds).addTo(minimap);

        // Sync main map with minimap
        map.on('move', () => {
            const mainCenter = map.getCenter();
            minimap.setView(mainCenter, minimap.getZoom() - 2);
        });

        // Allow clicking on minimap to move the main map
        minimap.on('click', (e) => {
            const clickedPoint = e.latlng;
            map.setView(clickedPoint, map.getZoom());
        });

        // Update minimap layer on main map change
        document.getElementById('mapSelector').addEventListener('change', (e) => {
            const selectedMap = e.target.value;
            minimapLayer.setUrl(selectedMap);
        });
    </script>
</body>
</html>