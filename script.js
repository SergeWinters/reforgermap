// Wait for the DOM to be fully loaded before executing scripts
document.addEventListener('DOMContentLoaded', () => {

    window.mapElement = document.getElementById('map');
    window.map = L.map('map', {
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

    let poiMarkersLayer = L.featureGroup().addTo(window.map);
    let tempPoiSubmissionMarker = null;

    // --- START OF COLLABORATION VARIABLES ---
    let currentSessionKey = null;
    let currentSessionMapId = null;
    let currentSessionMapName = '';
    let collabClientId = localStorage.getItem('collabClientId');
    if (!collabClientId) {
        collabClientId = generateUUIDv4();
        localStorage.setItem('collabClientId', collabClientId);
    }

    let lastReceivedMarkerId = 0;
    let lastReceivedDrawingId = 0;
    let collabPollingInterval = null;
    const POLLING_RATE = 5000;

    const collabMarkersLayer = L.featureGroup().addTo(window.map);
    const collabDrawingsLayer = L.featureGroup().addTo(window.map);
    const renderedCollabItemDBIds = { markers: new Set(), drawings: new Set() };
    // --- END OF COLLABORATION VARIABLES ---

    function generateUUIDv4() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    function loadMapImage(mapImagePath) {
        if (currentMapLayer) {
            window.map.removeLayer(currentMapLayer);
        }
        currentMapLayer = L.imageOverlay(mapImagePath, bounds).addTo(window.map);
        window.map.setView([1024, 1024], 1);
    }

    async function loadPoisForMap(mapId) {
        poiMarkersLayer.clearLayers();
        if (!mapId) return;
        try {
            const response = await fetch(`get_pois.php?map_id=${mapId}`);
            if (!response.ok) throw new Error(`Failed to fetch POIs: ${response.statusText}`);
            const pois = await response.json();
            pois.forEach(poi => {
                let poiIconInstance; // Use 'Instance' to avoid conflict with customIcons.loot
                if (poi.fa_icon_class) {
                    poiIconInstance = L.divIcon({
                        html: `<i class="${poi.fa_icon_class}" style="font-size: 24px; color: #FFF; line-height: 1;"></i>`, // Adjust style, added line-height
                        className: 'fontawesome-map-marker',
                        iconSize: [24, 24],
                        iconAnchor: [12, 12], // Centered anchor for a square icon
                        popupAnchor: [0, -12]
                    });
                } else {
                    poiIconInstance = customIcons.loot; // Fallback to your existing image icon
                }

                const marker = L.marker([poi.latitude, poi.longitude], { icon: poiIconInstance }).addTo(poiMarkersLayer);
                
                const popupContent = (poi.image_path ?
                    `<img src="${poi.image_path}" alt="${poi.name}" style="width: 400px; height: auto; display:block; margin-bottom:5px;">` : '') +
                    `<strong>${poi.name}</strong>` +
                    (poi.fa_icon_class ? `<br><small>Icon: <i class="${poi.fa_icon_class}"></i> <span style="font-family:monospace;">${poi.fa_icon_class}</span></small>` : '');
                
                marker.bindPopup(popupContent, { maxWidth: "auto", className: "custom-popup" });
            });
        } catch (error) {
            console.error("Error loading POIs:", error);
        }
    }
    
    window.handleMapChange = async function (event) {
        const selectedOption = event.target.selectedOptions[0];
        const mapImagePath = selectedOption.value;
        const newMapId = parseInt(selectedOption.dataset.mapId);

        if (currentSessionKey && newMapId !== currentSessionMapId) {
            alert("You are in an active collaboration session for a different map. Please leave the current session to change maps.");
             for(let i=0; i<event.target.options.length; i++){
                if(parseInt(event.target.options[i].dataset.mapId) === currentMapId){
                    event.target.selectedIndex = i;
                    break;
                }
            }
            return;
        }
        currentMapId = newMapId; 
        loadMapImage(mapImagePath);
        await loadPoisForMap(currentMapId);

        if (typeof window.minimapLayer !== 'undefined' && window.minimapLayer) {
             window.minimapLayer.setUrl(mapImagePath);
        }
    }
    
    async function initializeMapSelector() {
        const mapSelector = document.getElementById('mapSelector');
        const sessionMapSel = document.getElementById('sessionMapSelector');
        if (!mapSelector || !sessionMapSel) {
            console.error("Map selector elements not found!");
            return;
        }
        try {
            const response = await fetch('get_maps.php');
            if (!response.ok) throw new Error(`Failed to fetch maps list: ${response.statusText}`);
            const mapsData = await response.json();

            if (mapsData.length === 0) {
                alert("No maps available.");
                mapSelector.innerHTML = sessionMapSel.innerHTML = "<option>No maps configured</option>";
                mapSelector.disabled = sessionMapSel.disabled = true;
                return;
            }

            mapsData.forEach(mapData => {
                const option = document.createElement('option');
                option.value = mapData.image_path;
                option.textContent = mapData.name;
                option.dataset.mapId = mapData.id;
                mapSelector.appendChild(option.cloneNode(true));
                sessionMapSel.appendChild(option);
            });

            if (mapSelector.options.length > 0) {
                mapSelector.selectedIndex = 0;
                sessionMapSel.selectedIndex = 0;
                const firstMapOption = mapSelector.options[0];
                currentMapId = parseInt(firstMapOption.dataset.mapId);
                loadMapImage(firstMapOption.value);
                await loadPoisForMap(currentMapId);

                if (typeof window.minimap !== 'undefined' && typeof window.minimapLayer !== 'undefined') {
                    window.minimapLayer.setUrl(firstMapOption.value);
                    window.minimap.setView([1024,1024], -1);
                    window.map.fire('move');
                }
            }
        } catch (error) {
            console.error("Error initializing map selector:", error);
            alert("A critical error occurred while loading map data.");
        }
    }

    const zoomInfo = document.getElementById('zoomLevel');
    window.map.on('zoomend', () => { zoomInfo.textContent = window.map.getZoom(); });
    zoomInfo.textContent = window.map.getZoom();

    const mouseCoordinates = document.getElementById('mouseCoordinates');
    window.map.on('mousemove', (e) => {
        const { lat, lng } = e.latlng;
        mouseCoordinates.textContent = `Y: ${lat.toFixed(2)}, X: ${lng.toFixed(2)}`;
    });

    const customIcons = { // These are image-based icons for tactical markers
        enemy: L.icon({ iconUrl: 'images/icon-enemy.png', iconSize: [32, 32], iconAnchor: [16, 16] }),
        exit: L.icon({ iconUrl: 'images/icon-exit.png', iconSize: [32, 32], iconAnchor: [16, 16] }),
        respawn: L.icon({ iconUrl: 'images/icon-respawn.png', iconSize: [32, 32], iconAnchor: [16, 16] }),
        loot: L.icon({ iconUrl: 'images/icon-loot.png', iconSize: [24, 24], iconAnchor: [12, 12] }), // Fallback for POIs without FA icon
        submitPoiTemp: L.icon({ iconUrl: 'images/icon-poi-submit-temp.png', iconSize: [32, 32], iconAnchor: [16, 32] })
    };

    let userMarkers = []; 
    let currentMarkerType = null;

    window.setMarkerType = function (type) {
        if (currentMarkerType === 'submitPoi' && type !== 'submitPoi' && tempPoiSubmissionMarker) {
            window.map.removeLayer(tempPoiSubmissionMarker);
            tempPoiSubmissionMarker = null;
        }
        currentMarkerType = type;
        document.querySelectorAll('#sidebar button').forEach(button => button.classList.remove('selected'));
        let selectedButton;
        if (type === 'submitPoi') selectedButton = document.getElementById('sendPoiButton');
        else if (type) selectedButton = document.getElementById(`${type}Button`);
        if (selectedButton) selectedButton.classList.add('selected');
    }

    window.map.on('click', e => {
        if (window.map.drawControl && window.map.drawControl._toolbars.draw._activeMode) return;
        if (e.originalEvent.target.closest('.leaflet-popup-pane')) return;
        if (e.originalEvent.target.closest('.leaflet-control')) return;
        if (e.originalEvent.target.closest('.iconpicker-popover')) return; // Prevent map click when icon picker is open

        const lat = e.latlng.lat;
        const lng = e.latlng.lng;

        if (currentMarkerType === 'submitPoi') {
            if (!currentMapId) { alert("Please select a map first."); window.setMarkerType(null); return; }
            if (tempPoiSubmissionMarker) window.map.removeLayer(tempPoiSubmissionMarker);
            tempPoiSubmissionMarker = L.marker([lat, lng], { icon: customIcons.submitPoiTemp, draggable: true }).addTo(window.map);
            tempPoiSubmissionMarker.on('dragend', function(event) {
                const upLatLng = event.target.getLatLng();
                document.getElementById('poiLat').value = upLatLng.lat.toFixed(6);
                document.getElementById('poiLng').value = upLatLng.lng.toFixed(6);
                document.getElementById('poiCoordsPreview').textContent = `Lat: ${upLatLng.lat.toFixed(2)}, Lng: ${upLatLng.lng.toFixed(2)}`;
            });
            openPoiSubmissionModal(lat, lng, currentMapId);
        } else if (currentMarkerType) {
            if (currentSessionKey) { 
                sendCollabData('marker', 'add', {
                    marker_type_name: currentMarkerType, // This is for tactical markers, not POIs
                    latitude: lat,
                    longitude: lng
                });
            } else { 
                const marker = L.marker([lat, lng], { icon: customIcons[currentMarkerType] }).addTo(window.map);
                marker.on('click', function (ev) {
                    L.DomEvent.stopPropagation(ev);
                    window.map.removeLayer(this);
                    userMarkers = userMarkers.filter(m => m !== this);
                });
                userMarkers.push(marker);
            }
            window.setMarkerType(null);
        }
    });
    
    function openPoiSubmissionModal(lat, lng, mapId) {
        document.getElementById('poiLat').value = lat.toFixed(6);
        document.getElementById('poiLng').value = lng.toFixed(6);
        document.getElementById('poiMapId').value = mapId;
        document.getElementById('poiName').value = '';
        document.getElementById('poiSubmitFaIconInput').value = ''; // Clear icon input
        $('#poi_submit_icon_preview').html(''); // Clear icon preview
        document.getElementById('poiCoordsPreview').textContent = `Lat: ${lat.toFixed(2)}, Lng: ${lng.toFixed(2)}`;
        document.getElementById('poiSubmissionMessage').textContent = '';
        document.getElementById('poiSubmissionModal').classList.remove('hidden');
        document.getElementById('poiSubmissionModal').classList.add('visible');
    }

    window.closePoiSubmissionModal = function () {
        document.getElementById('poiSubmissionModal').classList.remove('visible');
        document.getElementById('poiSubmissionModal').classList.add('hidden');
        if (tempPoiSubmissionMarker) { window.map.removeLayer(tempPoiSubmissionMarker); tempPoiSubmissionMarker = null; }
        if (currentMarkerType === 'submitPoi') window.setMarkerType(null);
    }

    document.getElementById('poiForm').addEventListener('submit', async function(event) {
        event.preventDefault();
        const formData = new FormData(this);
        const msgDiv = document.getElementById('poiSubmissionMessage');
        msgDiv.textContent = 'Submitting...'; msgDiv.style.color = '#f0f0f0';
        try {
            const response = await fetch('submit_poi.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (response.ok && result.success) {
                msgDiv.textContent = 'POI submitted for review! Thank you.'; msgDiv.style.color = 'lightgreen';
                setTimeout(window.closePoiSubmissionModal, 2500);
            } else {
                msgDiv.textContent = `Error: ${result.message || 'Could not submit POI.'}`; msgDiv.style.color = 'salmon';
            }
        } catch (error) {
            console.error('POI Submission error:', error);
            msgDiv.textContent = 'An error occurred during submission.'; msgDiv.style.color = 'salmon';
        }
    });

    // Initialize icon picker for POI Submission Modal
    if (document.getElementById('poi_submit_icon_picker_button')) {
        $('#poi_submit_icon_picker_button').iconpicker({
            iconset: 'fontawesome6', // Make sure this matches your Font Awesome version
            icon: 'fas fa-map-marker-alt', // Default icon
            rows: 5,
            cols: 10,
            placement: 'bottom' // Or 'top', 'left', 'right'
        }).on('change', function(e) {
            if (e.icon) {
                $('#poiSubmitFaIconInput').val(e.icon);
                $('#poi_submit_icon_preview').html('<i class="' + e.icon + '"></i>');
            } else { // If deselected or search yields no result and picker clears
                $('#poiSubmitFaIconInput').val('');
                $('#poi_submit_icon_preview').html('');
            }
        });
    }


    window.undoLastMarker = function () { 
        if (userMarkers.length > 0) window.map.removeLayer(userMarkers.pop());
        else alert('No personal markers to undo.');
    }
    window.removeAllMarkers = function () { 
        userMarkers.forEach(marker => window.map.removeLayer(marker));
        userMarkers = [];
    }

    const drawnItems = new L.FeatureGroup().addTo(window.map); 
    const drawControlOptions = {
        draw: { polyline: true, polygon: true, rectangle: true, circle: true, marker: false },
        edit: { featureGroup: drawnItems, remove: true } 
    };
    window.map.drawControl = new L.Control.Draw(drawControlOptions);


    window.map.on(L.Draw.Event.CREATED, function (event) {
        const layer = event.layer;
        const type = event.layerType;
        if (currentSessionKey) {
            const geojsonData = layer.toGeoJSON();
            layer.client_layer_id = layer._leaflet_id; 
            sendCollabData('drawing', 'add', {
                layer_type: type,
                geojson_data: JSON.stringify(geojsonData),
                client_layer_id: layer.client_layer_id
            });
        } else {
            drawnItems.addLayer(layer); 
        }
    });

    window.map.on(L.Draw.Event.EDITED, function (event) {
        if (!currentSessionKey) {
            console.log("Personal drawing edited.");
            return;
        }
        event.layers.eachLayer(function (layer) {
            if (layer.db_id) { 
                console.log("TODO: Send collaborative drawing update for db_id:", layer.db_id, " New GeoJSON:", JSON.stringify(layer.toGeoJSON()));
            }
        });
    });

    window.map.on(L.Draw.Event.DELETED, function (event) {
        if (!currentSessionKey) { 
             console.log("Personal drawing deleted.");
            return;
        }
        event.layers.eachLayer(function (layer) {
            if (layer.db_id) {
                if (confirm("Delete this collaborative drawing for everyone?")) {
                    sendCollabData('drawing', 'delete', { db_item_id: layer.db_id });
                    if (collabDrawingsLayer.hasLayer(layer)) {
                        collabDrawingsLayer.removeLayer(layer);
                    }
                    renderedCollabItemDBIds.drawings.delete(layer.db_id);
                }
            }
        });
    });

    window.enableDrawing = function () { window.map.addControl(window.map.drawControl); }
    window.disableDrawing = function () {
        if (window.map.drawControl && window.map.pm) { 
            if (window.map.pm.globalDrawModeEnabled()) window.map.pm.disableDraw();
            if (window.map.pm.globalEditModeEnabled()) window.map.pm.disableGlobalEditMode();
        }
        if (window.map.drawControl) { 
            for (const type in window.map.drawControl._toolbars.draw._modes) {
                if (window.map.drawControl._toolbars.draw._modes[type].handler.enabled()) window.map.drawControl._toolbars.draw._modes[type].handler.disable();
            }
            for (const type in window.map.drawControl._toolbars.edit._modes) {
                 if (window.map.drawControl._toolbars.edit._modes[type].handler && window.map.drawControl._toolbars.edit._modes[type].handler.enabled()) window.map.drawControl._toolbars.edit._modes[type].handler.disable();
            }
            window.map.removeControl(window.map.drawControl);
        }
    }
    window.clearAllDrawings = function () { drawnItems.clearLayers(); } 

    const compass = document.getElementById('compass');
    compass.addEventListener('click', () => { rotateMap(90); });
    let currentAngle = 0; const directions = ['N', 'E', 'S', 'W'];
    function rotateMap(angle) {
        currentAngle = (currentAngle + angle) % 360;
        document.getElementById('map').style.transform = `rotate(${currentAngle}deg)`;
        const effectiveAngle = (360 - currentAngle) % 360;
        document.getElementById('currentDirection').textContent = directions[Math.round(effectiveAngle / 90) % 4];
    }

    const sidebar = document.getElementById('sidebar');
    const toggleMenuButton = document.getElementById('toggleMenu');
    toggleMenuButton.addEventListener('click', () => {
        sidebar.classList.toggle('hidden');
        adjustMapSize();
    });
    function adjustMapSize() { setTimeout(() => window.map.invalidateSize(), 300); }

    window.openAbout = function () { document.getElementById('modal').classList.add('visible'); }
    window.closeAbout = function () { document.getElementById('modal').classList.remove('visible'); }
    window.openHelp = function () { document.getElementById('help-modal').classList.add('visible'); }
    window.closeHelp = function () { document.getElementById('help-modal').classList.remove('visible'); }
    
    // --- COLLABORATION LOGIC ---
    initializeMapSelector().then(() => { 
        const sessionModal = document.getElementById('sessionModal');
        const createSessBtn = document.getElementById('createSessionButton');
        const joinSessBtn = document.getElementById('joinSessionButton');
        const sessKeyInput = document.getElementById('sessionKeyInput');
        const sessMsg = document.getElementById('sessionMessage');
        const sessInfoDiv = document.getElementById('sessionInfo');
        const activeSessKeySpan = document.getElementById('activeSessionKey');
        const activeSessMapNameSpan = document.getElementById('activeSessionMapName');
        const shareLinkInput = document.getElementById('shareableLink');
        const leaveSessBtn = document.getElementById('leaveSessionButton');
        const sessMapSelector = document.getElementById('sessionMapSelector');
        const mainMapSelector = document.getElementById('mapSelector');
        const mapElementRef = document.getElementById('map'); 

        const sessionCreateSection = document.getElementById('sessionCreateSection');
        const sessionJoinSection = document.getElementById('sessionJoinSection');
        const sessionModalInstructions = document.getElementById('sessionModalInstructions');

        function updateSessionUI(isActive) {
            if (isActive) { 
                sessionModal.classList.remove('visible');
                sessionModal.classList.add('hidden');
                mapElementRef.classList.remove('blurred');
                sessInfoDiv.style.display = 'block'; 
                sessionCreateSection.style.display = 'none'; 
                sessionJoinSection.style.display = 'none'; 
                sessionModalInstructions.style.display = 'none'; 
                activeSessKeySpan.textContent = currentSessionKey;
                activeSessMapNameSpan.textContent = currentSessionMapName;
                const newUrl = `${window.location.protocol}//${window.location.host}${window.location.pathname}?session=${currentSessionKey}`;
                shareLinkInput.value = newUrl;
                try { window.history.pushState({path:newUrl},'',newUrl); } catch(e) { console.warn("Could not update URL:", e); }
                mainMapSelector.disabled = true;
            } else { 
                sessionModal.classList.add('visible');
                sessionModal.classList.remove('hidden');
                mapElementRef.classList.add('blurred');
                sessInfoDiv.style.display = 'none'; 
                sessionCreateSection.style.display = 'block'; 
                sessionJoinSection.style.display = 'block'; 
                sessionModalInstructions.style.display = 'block'; 
                try { window.history.pushState({path: window.location.pathname},'',window.location.pathname); } catch(e) { console.warn("Could not update URL:", e); }
                mainMapSelector.disabled = false;
            }
        }
        updateSessionUI(false); 

        const urlParams = new URLSearchParams(window.location.search);
        const sessionKeyFromUrl = urlParams.get('session');
        if (sessionKeyFromUrl) {
            sessKeyInput.value = sessionKeyFromUrl; 
            joinSession(sessionKeyFromUrl); 
        } else { updateSessionUI(false); }

        createSessBtn.addEventListener('click', async () => {
            const selectedOpt = sessMapSelector.options[sessMapSelector.selectedIndex];
            if (!selectedOpt || !selectedOpt.dataset.mapId) { sessMsg.textContent = 'Please select map.'; return; }
            const mapIdForSess = parseInt(selectedOpt.dataset.mapId);
            const mapNameForSess = selectedOpt.textContent;
            sessMsg.textContent = 'Creating...';
            try {
                const resp = await fetch('create_session.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: `map_id=${mapIdForSess}` });
                const data = await resp.json();
                if (data.success) {
                    currentSessionKey = data.session_key; currentSessionMapId = data.map_id; currentSessionMapName = mapNameForSess;
                    updateSessionUI(true); startPolling();
                    if (currentMapId !== currentSessionMapId) {
                        for(let i=0; i<mainMapSelector.options.length; i++){ if(parseInt(mainMapSelector.options[i].dataset.mapId) === currentSessionMapId){ mainMapSelector.selectedIndex = i; mainMapSelector.dispatchEvent(new Event('change')); break; } }
                    }
                    loadAllCollabItems();
                } else { sessMsg.textContent = `Error: ${data.message}`; updateSessionUI(false); }
            } catch (err) { console.error("Create sess err:", err); sessMsg.textContent = 'Network error.'; updateSessionUI(false); }
        });

        joinSessBtn.addEventListener('click', () => { const key = sessKeyInput.value.trim(); if (key) joinSession(key); else sessMsg.textContent = 'Enter session key.'; });

        async function joinSession(key) {
            sessMsg.textContent = 'Joining...';
            try {
                const resp = await fetch(`join_session.php?session_key=${key}`);
                const data = await resp.json();
                if (data.success) {
                    currentSessionKey = data.session_key; currentSessionMapId = data.map_id;
                    let mapNameFound = false;
                    for(let i=0; i<mainMapSelector.options.length; i++){ if(parseInt(mainMapSelector.options[i].dataset.mapId) === currentSessionMapId){ currentSessionMapName = mainMapSelector.options[i].textContent; if (currentMapId !== currentSessionMapId) { mainMapSelector.selectedIndex = i; mainMapSelector.dispatchEvent(new Event('change')); } mapNameFound = true; break; } }
                    if (!mapNameFound) currentSessionMapName = `Map ID ${currentSessionMapId}`;
                    updateSessionUI(true); startPolling(); loadAllCollabItems();
                } else { sessMsg.textContent = `Error: ${data.message}`; updateSessionUI(false); }
            } catch (err) { console.error("Join sess err:", err); sessMsg.textContent = 'Network error.'; updateSessionUI(false); }
        }

        leaveSessBtn.addEventListener('click', () => {
            currentSessionKey = null; currentSessionMapId = null; currentSessionMapName = '';
            stopPolling(); 
            collabMarkersLayer.clearLayers(); collabDrawingsLayer.clearLayers();
            renderedCollabItemDBIds.markers.clear(); renderedCollabItemDBIds.drawings.clear();
            lastReceivedMarkerId = 0; lastReceivedDrawingId = 0;
            sessMsg.textContent = 'You have left the session.'; updateSessionUI(false); 
        });
    }); 

    function startPolling() { if (collabPollingInterval) clearInterval(collabPollingInterval); collabPollingInterval = setInterval(fetchCollabUpdates, POLLING_RATE); console.log("Collab polling started."); }
    function stopPolling() { if (collabPollingInterval) clearInterval(collabPollingInterval); collabPollingInterval = null; console.log("Collab polling stopped."); }

    async function fetchCollabUpdates() {
        if (!currentSessionKey) return;
        try {
            const resp = await fetch(`get_collab_updates.php?session_key=${currentSessionKey}&client_id=${collabClientId}&last_marker_id=${lastReceivedMarkerId}&last_drawing_id=${lastReceivedDrawingId}`);
            const data = await resp.json();
            if (data.success) {
                if (data.map_id && data.map_id !== currentSessionMapId) { 
                    console.warn(`Session map ${data.map_id} differs. Aligning...`); currentSessionMapId = data.map_id; let mapFound = false; const mainMapSelector = document.getElementById('mapSelector');
                    for(let i=0; i<mainMapSelector.options.length; i++){ if(parseInt(mainMapSelector.options[i].dataset.mapId) === currentSessionMapId){ currentSessionMapName = mainMapSelector.options[i].textContent; document.getElementById('activeSessionMapName').textContent = currentSessionMapName; if (currentMapId !== currentSessionMapId) { mainMapSelector.selectedIndex = i; mainMapSelector.dispatchEvent(new Event('change')); } mapFound = true; break; } }
                    if (!mapFound) console.error("Session map ID from server not found!");
                    collabMarkersLayer.clearLayers(); collabDrawingsLayer.clearLayers(); renderedCollabItemDBIds.markers.clear(); renderedCollabItemDBIds.drawings.clear(); lastReceivedMarkerId = 0; lastReceivedDrawingId = 0;
                }
                data.markers.forEach(mkrData => { renderCollabMarker(mkrData); if (mkrData.id > lastReceivedMarkerId) lastReceivedMarkerId = mkrData.id; });
                data.drawings.forEach(drwData => { renderCollabDrawing(drwData); if (drwData.id > lastReceivedDrawingId) lastReceivedDrawingId = drwData.id; });
            } else { console.error("Error fetching updates:", data.message); if (data.message === 'Invalid session.') { alert("Collaboration session is no longer valid."); document.getElementById('leaveSessionButton').click(); } }
        } catch (err) { console.error("Network error fetching updates:", err); }
    }

    async function loadAllCollabItems() {
        if (!currentSessionKey) return; console.log("Loading all collab items for session:", currentSessionKey);
        lastReceivedMarkerId = 0; lastReceivedDrawingId = 0;
        collabMarkersLayer.clearLayers(); collabDrawingsLayer.clearLayers();
        renderedCollabItemDBIds.markers.clear(); renderedCollabItemDBIds.drawings.clear();
        await fetchCollabUpdates();
    }

    async function sendCollabData(itemType, action, payload) {
        if (!currentSessionKey) return; const formData = new FormData(); formData.append('session_key', currentSessionKey); formData.append('client_id', collabClientId); formData.append('item_type', itemType); formData.append('action', action);
        for (const key in payload) formData.append(key, payload[key]);
        try {
            const resp = await fetch('manage_collab_item.php', { method: 'POST', body: formData });
            const data = await resp.json();
            if (data.success) console.log(`Collab ${itemType} ${action} success:`, data.message, "DB ID:", data.item_id);
            else console.error(`Collab ${itemType} ${action} failed:`, data.message);
        } catch (err) { console.error(`Network error sending collab ${itemType}:`, err); }
    }

    function renderCollabMarker(markerData) { // This is for tactical markers, not POIs
        if (renderedCollabItemDBIds.markers.has(markerData.id)) return;
        if (currentMapId !== currentSessionMapId) { console.log("Skipping collab marker render, map mismatch.", markerData); return; }
        const icon = customIcons[markerData.marker_type] || customIcons.loot; // Tactical markers use predefined image icons
        const marker = L.marker([markerData.latitude, markerData.longitude], { icon: icon }).addTo(collabMarkersLayer);
        marker.db_id = markerData.id; marker.client_id_owner = markerData.client_id;
        marker.on('click', function(ev) { L.DomEvent.stopPropagation(ev); if (confirm(`Delete collaborative marker? (Owner: ${this.client_id_owner === collabClientId ? "You" : "Other"})`)) { sendCollabData('marker', 'delete', { db_item_id: this.db_id }); collabMarkersLayer.removeLayer(this); renderedCollabItemDBIds.markers.delete(this.db_id); } });
        renderedCollabItemDBIds.markers.add(markerData.id);
    }
    function renderCollabDrawing(drawingData) {
        if (renderedCollabItemDBIds.drawings.has(drawingData.id)) return;
         if (currentMapId !== currentSessionMapId) { console.log("Skipping collab drawing render, map mismatch.", drawingData); return; }
        try {
            const geoJsonFeat = JSON.parse(drawingData.geojson_data);
            const layer = L.geoJSON(geoJsonFeat).getLayers()[0];
            if (layer) {
                layer.db_id = drawingData.id; layer.client_id_owner = drawingData.client_id; layer.client_layer_id = drawingData.client_layer_id;
                collabDrawingsLayer.addLayer(layer); renderedCollabItemDBIds.drawings.add(drawingData.id);
                layer.on('click', function(ev) { L.DomEvent.stopPropagation(ev); if (window.map.drawControl && window.map.drawControl._toolbars.edit && window.map.drawControl._toolbars.edit._activeMode && window.map.drawControl._toolbars.edit._featureGroup.hasLayer(this)) { return; } if (confirm(`Delete collaborative drawing? (Owner: ${this.client_id_owner === collabClientId ? "You" : "Other"})`)) { sendCollabData('drawing', 'delete', { db_item_id: this.db_id }); collabDrawingsLayer.removeLayer(this); renderedCollabItemDBIds.drawings.delete(this.db_id); } });
            } else console.error("Could not create layer from GeoJSON:", drawingData);
        } catch (e) { console.error("Error parsing GeoJSON for collab drawing:", e, drawingData.geojson_data); }
    }
    // --- END COLLABORATION LOGIC ---

}); // End of DOMContentLoaded