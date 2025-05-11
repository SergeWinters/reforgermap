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
    <!-- Meta Tags for SEO -->
    <meta name="description" content="Plan your strategies and navigate efficiently in Arma Reforger with our interactive map tool. Add markers, search POIs, draw plans, and collaborate with your team.">
    <meta name="keywords" content="Arma Reforger map, interactive map, POI search, markers, strategy planning, drawing tools, minimap, map tools for games">
    <meta name="author" content="ForgeNEX Interactive">
    <meta name="robots" content="index, follow">
    <meta name="language" content="en">
    <meta name="theme-color" content="#333">

    <!-- Open Graph Tags for Social Media -->
    <meta property="og:title" content="Interactive Map for Arma Reforger">
    <meta property="og:description" content="A powerful tool for players to plan strategies, add markers, and interact with the map of Arma Reforger. Includes POI search, drawing tools, and more.">
    <meta property="og:image" content="https://www.reforgemap.com/og-image.png"> <!-- Replace with your actual OG image URL -->
    <meta property="og:url" content="https://www.reforgemap.com"> <!-- Replace with your actual URL -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Reforger Interactive Map">

    <!-- Twitter Card Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Interactive Map for Arma Reforger">
    <meta name="twitter:description" content="Plan your strategies and collaborate effectively with our interactive map for Arma Reforger.">
    <meta name="twitter:image" content="https://www.reforgemap.com/og-image.png"> <!-- Replace with your actual Twitter image URL -->

    <!-- Canonical URL -->
    <link rel="canonical" href="https://www.reforgemap.com"> <!-- Replace with your actual canonical URL -->

    <!-- Favicon -->
    <link rel="icon" href="/favicon.ico" type="image/x-icon"> <!-- Ensure favicon.ico exists -->

    <!-- Structured Data for Search Engines -->
    <script type="application/ld+json">
    {
        "@context": "http://schema.org",
        "@type": "WebSite",
        "name": "Reforger Interactive Map",
        "url": "https://www.reforgemap.com", // Replace
        "potentialAction": {
            "@type": "SearchAction",
            "target": "https://www.reforgemap.com/?q={search_term_string}", // Replace
            "query-input": "required name=search_term_string"
        },
        "description": "Interactive map tool for Arma Reforger players. Plan strategies, add markers, search POIs, and collaborate with your team."
    }
    </script>
    <link rel="stylesheet" href="style.css">
	<!-- Pixel Code - https://stats.forgenex.com/ -->
	<script defer src="https://stats.forgenex.com/pixel/5YKZG0nR2y0g7vKK"></script>
	<!-- END Pixel Code -->
    <style>
        /* General Modal Styling */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.6); /* Black w/ opacity */
        }

        .modal.visible {
            display: flex; /* Use flexbox to center content */
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: #2c2c2c; /* Dark theme content background */
            color: #f0f0f0;
            margin: auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .modal-content iframe {
            width: 100%;
            height: 400px; /* Adjust as needed */
            border: none;
        }
        .modal-content button { /* General button styling within modal */
            background-color: #5cb85c;
            color: white;
            padding: 10px 15px;
            margin-top: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .modal-content button:hover {
            opacity: 0.9;
        }


        /* Style for POI submission form elements within the modal */
        #poiSubmissionModal label {
            display: block;
            margin-top: 10px;
            margin-bottom: 5px;
            font-weight: bold;
        }

        #poiSubmissionModal input[type="text"],
        #poiSubmissionModal input[type="file"] {
            width: calc(100% - 22px); /* Full width minus padding and border */
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #555;
            border-radius: 4px;
            background-color: #333;
            color: #fff;
        }

        #poiSubmissionModal button[type="submit"] { /* Specific for submit */
             background-color: #5cb85c;
        }
        #poiSubmissionModal button[type="button"] { /* Specific for Cancel button */
            background-color: #d9534f;
            margin-left: 10px;
        }


        /* Sidebar button selection */
        #sidebar button.selected {
            background-color: #007bff; /* Or your preferred selection color */
            color: white;
            border-color: #0056b3;
        }
        /* Custom popup for POI images */
        .custom-popup .leaflet-popup-content-wrapper {
            background: #333; /* Dark background for popup */
            color: #fff;      /* Light text */
            border-radius: 5px;
            padding: 1px; /* Remove default padding */
        }
        .custom-popup .leaflet-popup-content {
            margin: 0; /* Remove default margin */
            padding: 10px; /* Add some padding inside */
            font-size: 14px;
            line-height: 1.4;
        }
        .custom-popup .leaflet-popup-tip-container {
            /* Optional: Style the tip if needed */
        }
        .custom-popup img {
            border-radius: 3px; /* Slight rounding for image */
        }
        .custom-popup strong {
            display: block;
            margin-bottom: 5px;
        }
    </style>
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
        <button onclick="undoLastMarker()">Undo Last</button>
        <button onclick="removeAllMarkers()">Remove All</button>
        <h3>Drawing Tools</h3>
        <button onclick="enableDrawing()">Enable Drawing</button>
        <button onclick="disableDrawing()">Disable Drawing</button>
        <button onclick="clearAllDrawings()">Clear All Drawings</button>
        <h3>Select Map</h3>
        <select id="mapSelector" onchange="handleMapChange(event)">
            <!-- Options will be populated by JavaScript -->
        </select>
        <h3>Contribute</h3>
        <button id="sendPoiButton" onclick="setMarkerType('submitPoi')">Submit POI</button>
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
			<div id="help-modal" class="modal hidden"> <!-- Added modal class -->
				<div class="modal-content">
					<iframe src="help.html" frameborder="0"></iframe>
					<button onclick="closeHelp()">Close</button>
				</div>
			</div>
			<a href="#" onclick="openAbout()">About</a>
			<div id="modal" class="modal hidden"> <!-- Added modal class -->
				<div class="modal-content">
					<iframe src="about.html" frameborder="0"></iframe>
					<button onclick="closeAbout()">Close</button>
				</div>
			</div>
            <a href="https://discord.gg/MfNDSg85Pf" target="_blank">Discord</a>
        </div>
        <div>
            Version 6.1 - <a href="https://github.com/SergeWinters/reforgermap/commits/main/" target="_blank">Changelog</a> <!-- Version bump -->
        </div>
    </div>

    <!-- POI Submission Modal -->
    <div id="poiSubmissionModal" class="modal hidden">
        <div class="modal-content">
            <h4>Submit New Point of Interest</h4>
            <form id="poiForm">
                <input type="hidden" id="poiLat" name="latitude">
                <input type="hidden" id="poiLng" name="longitude">
                <input type="hidden" id="poiMapId" name="map_id">

                <div>
                    <label for="poiName">Name:</label>
                    <input type="text" id="poiName" name="name" required maxlength="150">
                </div>
                <div>
                    <p>Coordinates: <span id="poiCoordsPreview"></span> (Drag marker on map to adjust)</p>
                </div>
                <!-- Optional: Image Upload (can be added to submit_poi.php later)
                <div>
                    <label for="poiImage">Image (optional):</label>
                    <input type="file" id="poiImage" name="image" accept="image/jpeg, image/png, image/gif">
                </div>
                -->
                <button type="submit">Submit POI</button>
                <button type="button" onclick="closePoiSubmissionModal()">Cancel</button>
            </form>
            <div id="poiSubmissionMessage" style="margin-top: 10px;"></div>
        </div>
    </div>


    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
    <script>
        const map = L.map('map', {
            crs: L.CRS.Simple,
            maxZoom: 3,
            minZoom: 0,
            gestureHandling: true,
            maxBounds: [[0, 0], [2048, 2048]],
            maxBoundsViscosity: 1.0
        });
        let currentMapLayer;
        let currentMapId = null;
        const bounds = [[0, 0], [2048, 2048]];

        let poiMarkersLayer = L.featureGroup().addTo(map);
        let tempPoiSubmissionMarker = null;


        function loadMapImage(mapImagePath) {
            if (currentMapLayer) {
                map.removeLayer(currentMapLayer);
            }
            currentMapLayer = L.imageOverlay(mapImagePath, bounds).addTo(map);
            map.setView([1024, 1024], 1);
        }

        async function loadPoisForMap(mapId) {
            poiMarkersLayer.clearLayers();
            if (!mapId) return;

            try {
                const response = await fetch(`get_pois.php?map_id=${mapId}`);
                if (!response.ok) {
                    console.error("Failed to fetch POIs:", response.statusText);
                    alert("Error loading points of interest for this map.");
                    return;
                }
                const pois = await response.json();
                pois.forEach(poi => {
                    const marker = L.marker([poi.latitude, poi.longitude], { icon: customIcons.loot }).addTo(poiMarkersLayer);
                    const popupContent = (poi.image_path ?
                        `<img src="${poi.image_path}" alt="${poi.name}" style="width: 400px; height: auto; display:block; margin-bottom:5px;">` : '') +
                        `<strong>${poi.name}</strong>`;

                    marker.bindPopup(popupContent, {
                        maxWidth: "auto",
                        className: "custom-popup"
                    });
                });
            } catch (error) {
                console.error("Error fetching or processing POIs:", error);
                alert("An error occurred while loading points of interest.");
            }
        }

        async function handleMapChange(event) {
            const selectedOption = event.target.selectedOptions[0];
            const mapImagePath = selectedOption.value;
            currentMapId = parseInt(selectedOption.dataset.mapId);

            loadMapImage(mapImagePath);
            await loadPoisForMap(currentMapId);

            if (typeof minimapLayer !== 'undefined' && minimapLayer) {
                 minimapLayer.setUrl(mapImagePath);
            }
        }

        async function initializeMapSelector() {
            const mapSelector = document.getElementById('mapSelector');
            try {
                const response = await fetch('get_maps.php');
                if (!response.ok) {
                    console.error("Failed to fetch maps list:", response.statusText);
                    alert("Error loading map list. Please try refreshing.");
                    return;
                }
                const mapsData = await response.json();

                if (mapsData.length === 0) {
                    alert("No maps available.");
                    mapSelector.innerHTML = "<option>No maps configured</option>";
                    mapSelector.disabled = true;
                    return;
                }

                mapsData.forEach(mapData => {
                    const option = document.createElement('option');
                    option.value = mapData.image_path;
                    option.textContent = mapData.name;
                    option.dataset.mapId = mapData.id;
                    mapSelector.appendChild(option);
                });

                if (mapSelector.options.length > 0) {
                    mapSelector.selectedIndex = 0;
                    const firstMapOption = mapSelector.options[0];
                    currentMapId = parseInt(firstMapOption.dataset.mapId);
                    loadMapImage(firstMapOption.value);
                    await loadPoisForMap(currentMapId);

                    if (typeof minimap !== 'undefined' && typeof minimapLayer !== 'undefined') {
                        minimapLayer.setUrl(firstMapOption.value);
                        minimap.setView([1024,1024], -1);
                        map.fire('move');
                    }
                }
            } catch (error) {
                console.error("Error initializing map selector:", error);
                alert("An critical error occurred while loading map data.");
            }
        }


        const zoomInfo = document.getElementById('zoomLevel');
        map.on('zoomend', () => {
            zoomInfo.textContent = map.getZoom();
        });
        zoomInfo.textContent = map.getZoom();

        const mouseCoordinates = document.getElementById('mouseCoordinates');
        map.on('mousemove', (e) => {
            const { lat, lng } = e.latlng;
            mouseCoordinates.textContent = `Y: ${lat.toFixed(2)}, X: ${lng.toFixed(2)}`;
        });

        const customIcons = {
            enemy: L.icon({ iconUrl: 'images/icon-enemy.png', iconSize: [32, 32], iconAnchor: [16, 16] }),
            exit: L.icon({ iconUrl: 'images/icon-exit.png', iconSize: [32, 32], iconAnchor: [16, 16] }),
            respawn: L.icon({ iconUrl: 'images/icon-respawn.png', iconSize: [32, 32], iconAnchor: [16, 16] }),
            loot: L.icon({ iconUrl: 'images/icon-loot.png', iconSize: [24, 24], iconAnchor: [12, 12] }),
            submitPoiTemp: L.icon({ iconUrl: 'images/icon-poi-submit-temp.png', iconSize: [32, 32], iconAnchor: [16, 32] }) // Anchor at bottom-center
        };

        let userMarkers = [];
        let currentMarkerType = null;

        function setMarkerType(type) {
            if (currentMarkerType === 'submitPoi' && type !== 'submitPoi' && tempPoiSubmissionMarker) {
                map.removeLayer(tempPoiSubmissionMarker);
                tempPoiSubmissionMarker = null;
            }

            currentMarkerType = type;
            // Clear selection from all buttons in sidebar first
            document.querySelectorAll('#sidebar button').forEach(button => button.classList.remove('selected'));

            let selectedButton;
            if (type === 'submitPoi') {
                selectedButton = document.getElementById('sendPoiButton');
            } else if (type) {
                const buttonId = `${type}Button`; // e.g. enemyButton
                 selectedButton = document.getElementById(buttonId);
            }

            if (selectedButton) {
                selectedButton.classList.add('selected');
            }
        }


        map.on('click', e => {
            if (map.drawControl && map.drawControl._toolbars.draw._activeMode) return;
            if (e.originalEvent.target.closest('.leaflet-popup-pane')) return;
            // Prevent action if click is on a control (like zoom or draw tools)
            if (e.originalEvent.target.closest('.leaflet-control')) return;


            const lat = e.latlng.lat;
            const lng = e.latlng.lng;

            if (currentMarkerType === 'submitPoi') {
                if (!currentMapId) {
                    alert("Please select a map first from the 'Select Map' dropdown.");
                     setMarkerType(null); // Deselect button
                    return;
                }
                if (tempPoiSubmissionMarker) {
                    map.removeLayer(tempPoiSubmissionMarker);
                }
                tempPoiSubmissionMarker = L.marker([lat, lng], { icon: customIcons.submitPoiTemp, draggable: true }).addTo(map);
                tempPoiSubmissionMarker.on('dragend', function(event) {
                    const updatedLatLng = event.target.getLatLng();
                    document.getElementById('poiLat').value = updatedLatLng.lat.toFixed(6);
                    document.getElementById('poiLng').value = updatedLatLng.lng.toFixed(6);
                    document.getElementById('poiCoordsPreview').textContent = `Lat: ${updatedLatLng.lat.toFixed(2)}, Lng: ${updatedLatLng.lng.toFixed(2)}`;
                });
                openPoiSubmissionModal(lat, lng, currentMapId);

            } else if (currentMarkerType) {
                const marker = L.marker([lat, lng], { icon: customIcons[currentMarkerType] }).addTo(map).on('click', function (ev) {
                    L.DomEvent.stopPropagation(ev);
                    map.removeLayer(marker);
                    userMarkers = userMarkers.filter(m => m !== marker);
                });
                userMarkers.push(marker);
                setMarkerType(null); // Deselect after placing one marker
            }
        });


        function openPoiSubmissionModal(lat, lng, mapId) {
            document.getElementById('poiLat').value = lat.toFixed(6);
            document.getElementById('poiLng').value = lng.toFixed(6);
            document.getElementById('poiMapId').value = mapId;
            document.getElementById('poiName').value = '';
            document.getElementById('poiCoordsPreview').textContent = `Lat: ${lat.toFixed(2)}, Lng: ${lng.toFixed(2)}`;
            document.getElementById('poiSubmissionMessage').textContent = '';
            document.getElementById('poiSubmissionModal').classList.remove('hidden');
            document.getElementById('poiSubmissionModal').classList.add('visible');
        }

        function closePoiSubmissionModal() {
            document.getElementById('poiSubmissionModal').classList.remove('visible');
            document.getElementById('poiSubmissionModal').classList.add('hidden');
            if (tempPoiSubmissionMarker) {
                map.removeLayer(tempPoiSubmissionMarker);
                tempPoiSubmissionMarker = null;
            }
            if (currentMarkerType === 'submitPoi') {
                setMarkerType(null); // Deselect button
            }
        }

        document.getElementById('poiForm').addEventListener('submit', async function(event) {
            event.preventDefault();
            const formData = new FormData(this);
            const submissionMessageDiv = document.getElementById('poiSubmissionMessage');
            submissionMessageDiv.textContent = 'Submitting...';
            submissionMessageDiv.style.color = '#f0f0f0'; // Default text color

            try {
                const response = await fetch('submit_poi.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (response.ok && result.success) {
                    submissionMessageDiv.textContent = 'POI submitted for review! Thank you.';
                    submissionMessageDiv.style.color = 'lightgreen';
                    setTimeout(() => {
                        closePoiSubmissionModal();
                    }, 2500);
                } else {
                    submissionMessageDiv.textContent = `Error: ${result.message || 'Could not submit POI.'}`;
                    submissionMessageDiv.style.color = 'salmon';
                }
            } catch (error) {
                console.error('Submission error:', error);
                submissionMessageDiv.textContent = 'An error occurred during submission.';
                submissionMessageDiv.style.color = 'salmon';
            }
        });


        function undoLastMarker() {
            if (userMarkers.length > 0) {
                const lastMarker = userMarkers.pop();
                map.removeLayer(lastMarker);
            } else {
                alert('No user-placed markers to undo.');
            }
        }

        function removeAllMarkers() {
            userMarkers.forEach(marker => map.removeLayer(marker));
            userMarkers = [];
        }

        const drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);

        const drawControlOptions = {
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
        };
        const drawControl = new L.Control.Draw(drawControlOptions);

        map.on(L.Draw.Event.CREATED, function (event) {
            const layer = event.layer;
            drawnItems.addLayer(layer);
        });

        function enableDrawing() {
            map.addControl(drawControl);
        }

        function disableDrawing() {
            if (map.drawControl) { // Check if control exists
                // Deactivate any active drawing/editing mode
                for (const type in map.drawControl._toolbars.draw._modes) {
                    if (map.drawControl._toolbars.draw._modes[type].handler.enabled()) {
                        map.drawControl._toolbars.draw._modes[type].handler.disable();
                    }
                }
                for (const type in map.drawControl._toolbars.edit._modes) {
                     if (map.drawControl._toolbars.edit._modes[type].handler && map.drawControl._toolbars.edit._modes[type].handler.enabled()) {
                        map.drawControl._toolbars.edit._modes[type].handler.disable();
                    }
                }
                map.removeControl(drawControl);
            }
        }

        function clearAllDrawings() {
            drawnItems.clearLayers();
        }

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

            const effectiveAngle = (360 - currentAngle) % 360; // Angle for direction finding
            const directionIndex = Math.round(effectiveAngle / 90) % 4;
            document.getElementById('currentDirection').textContent = directions[directionIndex];
        }

        const sidebar = document.getElementById('sidebar');
        const toggleMenuButton = document.getElementById('toggleMenu');
        toggleMenuButton.addEventListener('click', () => {
            sidebar.classList.toggle('hidden'); // Assuming 'hidden' class controls visibility
            adjustMapSize();
        });

        function adjustMapSize() {
            setTimeout(() => map.invalidateSize(), 300);
        }

        function openAbout() {
            document.getElementById('modal').classList.remove('hidden');
            document.getElementById('modal').classList.add('visible');
        }
        function closeAbout() {
            document.getElementById('modal').classList.remove('visible');
            document.getElementById('modal').classList.add('hidden');
        }
        function openHelp() {
            document.getElementById('help-modal').classList.remove('hidden');
            document.getElementById('help-modal').classList.add('visible');
        }
        function closeHelp() {
            document.getElementById('help-modal').classList.remove('visible');
            document.getElementById('help-modal').classList.add('hidden');
        }

        document.addEventListener('DOMContentLoaded', initializeMapSelector);

    </script>
    <!-- Adding MiniMap -->
    <div id="minimapContainer" style="display: none;">
        <button id="closeMinimap">×</button>
        <div id="minimap"></div>
    </div>
    <button id="toggleMinimap">Toggle MiniMap</button>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const minimapContainer = document.getElementById('minimapContainer');
            const toggleMinimapButton = document.getElementById('toggleMinimap');
            const closeMinimapButton = document.getElementById('closeMinimap');

            if (!minimapContainer || !toggleMinimapButton || !closeMinimapButton) {
                console.warn("Minimap UI elements not found.");
                return;
            }

            toggleMinimapButton.addEventListener('click', () => {
                const isVisible = minimapContainer.style.display !== 'none';
                minimapContainer.style.display = isVisible ? 'none' : 'block';
                if (!isVisible && typeof window.minimap !== 'undefined' && window.minimap) {
                    window.minimap.invalidateSize();
                    if (map && map.getCenter()) {
                         let minimapZoom = map.getZoom() - 2;
                         if (map.getZoom() <= map.getMinZoom() +1 ) minimapZoom = minimap.getMinZoom();
                         else if (map.getZoom() === 0) minimapZoom = -1;
                         else if (map.getZoom() === 1) minimapZoom = -1;
                         window.minimap.setView(map.getCenter(), minimapZoom, { animate: false, noMoveStart: true });
                    }
                }
            });

            closeMinimapButton.addEventListener('click', () => {
                minimapContainer.style.display = 'none';
            });

            if (typeof L !== 'undefined' && typeof map !== 'undefined') {
                const minimapBounds = [[0, 0], [2048, 2048]];

                window.minimap = L.map('minimap', {
                    crs: L.CRS.Simple,
                    zoomControl: false,
                    attributionControl: false,
                    maxBounds: minimapBounds,
                    maxBoundsViscosity: 1.0,
                    gestureHandling: false
                });
                // Set initial view after map selector initializes and provides the first map image
                // Default view: .setView([1024, 1024], -1);

                window.minimapLayer = L.imageOverlay('', minimapBounds).addTo(window.minimap);

                map.on('move zoomend', () => {
                    if (minimapContainer.style.display !== 'none' && window.minimap) {
                        const mainCenter = map.getCenter();
                        let minimapZoom = map.getZoom() - 2;
                        if (map.getZoom() <= map.getMinZoom() + 1) minimapZoom = window.minimap.getMinZoom(); // Ensure it doesn't go below minimap's minZoom
                        else if (map.getZoom() === 0) minimapZoom = -1;
                        else if (map.getZoom() === 1) minimapZoom = -1;

                        // Clamp zoom to minimap's valid range if it has one defined
                        if (window.minimap.options.minZoom !== undefined && minimapZoom < window.minimap.options.minZoom) {
                            minimapZoom = window.minimap.options.minZoom;
                        }
                        if (window.minimap.options.maxZoom !== undefined && minimapZoom > window.minimap.options.maxZoom) {
                            minimapZoom = window.minimap.options.maxZoom;
                        }
                        
                        window.minimap.setView(mainCenter, minimapZoom, { animate: false, noMoveStart: true });
                    }
                });

                window.minimap.on('click', (e) => {
                    const clickedPoint = e.latlng;
                    map.setView(clickedPoint, map.getZoom());
                });
            } else {
                console.error("Leaflet or main map not initialized before minimap script.")
            }
        });
    </script>
</body>
</html>