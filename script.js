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
    let tempCustomCollabMarkerPlacement = null;

    // --- POI Data Storage & Connection Line Management ---
    let currentPoisData = {};
    let currentPoiConnections = [];
    let poiConnectionLinesLayer = L.featureGroup().addTo(window.map);
    let activePoiConnectionLinesLayer = L.featureGroup().addTo(window.map);
    let currentlyClickedPoiId = null;
    const defaultConnectionLineStyle = { color: '#FFFF00', weight: 2, opacity: 0.6, dashArray: '5, 5' };
    // --- End POI Connection ---

    // --- Collaboration Variables ---
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
    // --- End Collaboration Variables ---

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
        poiConnectionLinesLayer.clearLayers();
        activePoiConnectionLinesLayer.clearLayers();
        currentPoisData = {};
        currentPoiConnections = [];
        currentlyClickedPoiId = null;

        if (!mapId) return;
        try {
            const response = await fetch(`get_pois.php?map_id=${mapId}`);
            if (!response.ok) {
                throw new Error(`POIs fetch failed: ${response.statusText}`);
            }
            const data = await response.json(); // Expecting { pois: [], connections: [] }

            data.pois.forEach(poi => {
                let poiIconInstance;
                if (poi.fa_icon_class) {
                    poiIconInstance = L.divIcon({
                        html: `<i class="${poi.fa_icon_class}" style="font-size: 24px; color: #FFF; line-height: 1;"></i>`,
                        className: 'fontawesome-map-marker',
                        iconSize: [24, 24],
                        iconAnchor: [12, 12],
                        popupAnchor: [0, -12]
                    });
                } else {
                    poiIconInstance = customIcons.loot; // Fallback
                }
                
                const marker = L.marker([poi.latitude, poi.longitude], { icon: poiIconInstance });
                
                currentPoisData[poi.id] = {
                    data: poi,
                    markerInstance: marker,
                    latlng: L.latLng(poi.latitude, poi.longitude)
                };

                const popupContent = (poi.image_path ? `<img src="${poi.image_path}" alt="${poi.name}" style="width:400px;height:auto;display:block;margin-bottom:5px;">` : '') +
                                     `<strong>${poi.name}</strong>` +
                                     (poi.fa_icon_class ? `<br><small>Icon: <i class="${poi.fa_icon_class}"></i> <span style="font-family:monospace;">${poi.fa_icon_class}</span></small>` : '');
                marker.bindPopup(popupContent, { maxWidth: "auto", className: "custom-popup" });

                marker.on('mouseover', function () {
                    if (poi.id !== currentlyClickedPoiId) {
                        showPoiConnections(poi.id, poiConnectionLinesLayer, true);
                    }
                });
                marker.on('mouseout', function () {
                    if (poi.id !== currentlyClickedPoiId) {
                        poiConnectionLinesLayer.clearLayers();
                    }
                });
                marker.on('click', function () {
                    handlePoiClick(poi.id);
                });
                
                poiMarkersLayer.addLayer(marker);
            });

            if (data.connections) {
                currentPoiConnections = data.connections;
            }

        } catch (error) {
            console.error("Error loading POIs & Connections:", error);
            // Consider user feedback here if appropriate
        }
    }

    function showPoiConnections(poiId, targetLayer, isTemporary) {
        if (!currentPoisData[poiId]) return;
        targetLayer.clearLayers();
        const originPoi = currentPoisData[poiId];

        currentPoiConnections.forEach(conn => {
            let connectedPoiId = null;
            let lineShouldBeDrawn = false;

            if (conn.poi_id_from === poiId) {
                connectedPoiId = conn.poi_id_to;
                lineShouldBeDrawn = true;
            } else if (conn.poi_id_to === poiId && isTemporary) {
                connectedPoiId = conn.poi_id_from;
                lineShouldBeDrawn = true;
            }
            
            if (!isTemporary && conn.poi_id_from !== poiId) {
                lineShouldBeDrawn = false;
            }

            if (lineShouldBeDrawn && currentPoisData[connectedPoiId]) {
                const destinationPoi = currentPoisData[connectedPoiId];
                const latlngs = [originPoi.latlng, destinationPoi.latlng];
                let lineStyle = defaultConnectionLineStyle;
                if (conn.line_style && typeof conn.line_style === 'object') {
                    lineStyle = { ...defaultConnectionLineStyle, ...conn.line_style };
                }
                targetLayer.addLayer(L.polyline(latlngs, lineStyle));
            }
        });
    }

    function handlePoiClick(poiId) {
        activePoiConnectionLinesLayer.clearLayers();
        if (currentlyClickedPoiId === poiId) {
            currentlyClickedPoiId = null;
        } else {
            currentlyClickedPoiId = poiId;
            showPoiConnections(poiId, activePoiConnectionLinesLayer, false);
            poiConnectionLinesLayer.clearLayers(); 
        }
    }

    window.map.on('click', function(e) {
        let onPoiMarker = false;
        if (e.originalEvent.target && $(e.originalEvent.target).closest('.leaflet-marker-icon').length) {
            onPoiMarker = true;
        }
        if (!onPoiMarker && currentlyClickedPoiId !== null) {
            activePoiConnectionLinesLayer.clearLayers();
            currentlyClickedPoiId = null;
        }
        // Tactical marker logic will follow if other conditions are met by currentMarkerType
    });
    
    window.handleMapChange = async function (event) {
        const selectedOption = event.target.selectedOptions[0];
        const mapImagePath = selectedOption.value;
        const newMapId = parseInt(selectedOption.dataset.mapId);

        if (currentSessionKey && newMapId !== currentSessionMapId) {
            alert("You are in an active collaboration session for a different map. Please leave the current session to change maps.");
            $(event.target).val($(event.target).find(`option[data-map-id="${currentMapId}"]`).val()); // Revert
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
        const mapSelector = $('#mapSelector');
        const sessionMapSel = $('#sessionMapSelector');
        if (!mapSelector.length || !sessionMapSel.length) {
            console.error("Map selector elements not found!");
            return;
        }
        try {
            const response = await fetch('get_maps.php');
            if (!response.ok) {
                throw new Error(`Maps list fetch failed: ${response.statusText}`);
            }
            const mapsData = await response.json();

            if (mapsData.length === 0) {
                alert("No maps available.");
                mapSelector.html("<option>No maps configured</option>").prop('disabled', true);
                sessionMapSel.html("<option>No maps configured</option>").prop('disabled', true);
                return;
            }

            mapsData.forEach(mapData => {
                const optionHtml = `<option value="${mapData.image_path}" data-map-id="${mapData.id}">${mapData.name}</option>`;
                mapSelector.append(optionHtml);
                sessionMapSel.append(optionHtml);
            });

            if (mapSelector.find('option').length > 0) {
                mapSelector.prop('selectedIndex', 0);
                sessionMapSel.prop('selectedIndex', 0);
                const firstMapOption = mapSelector.find('option:selected');
                currentMapId = parseInt(firstMapOption.data('mapId'));
                loadMapImage(firstMapOption.val());
                await loadPoisForMap(currentMapId);

                if (typeof window.minimap !== 'undefined' && typeof window.minimapLayer !== 'undefined') {
                    window.minimapLayer.setUrl(firstMapOption.val());
                    window.minimap.setView([1024,1024], -1);
                    window.map.fire('move');
                }
            }
        } catch (error) {
            console.error("Error initializing map selector:", error);
            alert("A critical error occurred while loading map data.");
        }
    }

    $('#zoomLevel').text(window.map.getZoom());
    window.map.on('zoomend', () => {
        $('#zoomLevel').text(window.map.getZoom());
    });
    window.map.on('mousemove', (e) => {
        $('#mouseCoordinates').text(`Y: ${e.latlng.lat.toFixed(2)}, X: ${e.latlng.lng.toFixed(2)}`);
    });

    const customIcons = {
        enemy: L.icon({iconUrl:'images/icon-enemy.png',iconSize:[32,32],iconAnchor:[16,16]}),
        exit: L.icon({iconUrl:'images/icon-exit.png',iconSize:[32,32],iconAnchor:[16,16]}),
        // No 'respawn' anymore, it's 'customCollab'
        loot: L.icon({iconUrl:'images/icon-loot.png',iconSize:[24,24],iconAnchor:[12,12]}), // Fallback for POIs
        submitPoiTemp: L.icon({iconUrl:'images/icon-poi-submit-temp.png',iconSize:[32,32],iconAnchor:[16,32]})
    };
    let userMarkers = [];
    let currentMarkerType = null;

    window.setMarkerType = function (type) {
        if (currentMarkerType === 'submitPoi' && type !== 'submitPoi' && tempPoiSubmissionMarker) {
            window.map.removeLayer(tempPoiSubmissionMarker);
            tempPoiSubmissionMarker = null;
        }
        if (currentMarkerType === 'customCollab' && type !== 'customCollab' && tempCustomCollabMarkerPlacement) {
            window.map.removeLayer(tempCustomCollabMarkerPlacement);
            tempCustomCollabMarkerPlacement = null;
        }

        currentMarkerType = type;
        $('#sidebar button').removeClass('selected');
        let selectedButton;
        if (type === 'submitPoi') {
            selectedButton = $('#sendPoiButton');
        } else if (type === 'customCollab') {
            selectedButton = $('#customCollabMarkerButton');
        } else if (type) {
            selectedButton = $(`#${type}Button`);
        }
        if (selectedButton && selectedButton.length) {
            selectedButton.addClass('selected');
        }
    }

    window.map.on('click', e => {
        if (window.map.drawControl && window.map.drawControl._toolbars.draw._activeMode) return;
        // Combined check for elements that should prevent marker placement
        if ($(e.originalEvent.target).closest('.leaflet-popup-pane, .leaflet-control, .iconpicker-popover, .modal-content, .leaflet-marker-icon.fontawesome-map-marker').length) {
             if (!$(e.originalEvent.target).closest('.modal-content, .leaflet-control, .iconpicker-popover').length) {
                // This means it might be a POI marker handled by its own click (handlePoiClick)
                // or a collab marker which also has its own click handler.
                return;
             }
        }

        const lat = e.latlng.lat;
        const lng = e.latlng.lng;

        if (currentMarkerType === 'submitPoi') {
            if (!currentMapId) { alert("Please select a map first."); window.setMarkerType(null); return; }
            if (tempPoiSubmissionMarker) window.map.removeLayer(tempPoiSubmissionMarker);
            tempPoiSubmissionMarker = L.marker([lat, lng], {icon: customIcons.submitPoiTemp, draggable: true}).addTo(window.map);
            tempPoiSubmissionMarker.on('dragend', function(event) {
                const upLatLng = event.target.getLatLng();
                $('#poiLat').val(upLatLng.lat.toFixed(6));
                $('#poiLng').val(upLatLng.lng.toFixed(6));
                $('#poiCoordsPreview').text(`Lat: ${upLatLng.lat.toFixed(2)}, Lng: ${upLatLng.lng.toFixed(2)}`);
            });
            openPoiSubmissionModal(lat, lng, currentMapId);
        } else if (currentMarkerType === 'customCollab') {
            if (!currentSessionKey) { alert("You must be in a collaboration session to place custom collab markers."); window.setMarkerType(null); return; }
            if (tempCustomCollabMarkerPlacement) window.map.removeLayer(tempCustomCollabMarkerPlacement);
            tempCustomCollabMarkerPlacement = L.marker([lat, lng], { icon: L.divIcon({className:'temp-placement-dot', html:'.', iconSize:[6,6], iconAnchor:[3,3]}) }).addTo(window.map);
            openCustomCollabMarkerModal(lat, lng);
        } else if (currentMarkerType) { // Standard tactical markers
            if (currentSessionKey) {
                sendCollabData('marker', 'add', {
                    marker_type_name: currentMarkerType, // e.g., 'enemy', 'exit'
                    latitude: lat,
                    longitude: lng
                    // No fa_icon_class, marker_color, marker_text for these simple tactical ones
                });
            } else { // Personal tactical marker
                const marker = L.marker([lat, lng], {icon: customIcons[currentMarkerType]}).addTo(window.map);
                marker.on('click', function (ev) {
                    L.DomEvent.stopPropagation(ev);
                    window.map.removeLayer(this);
                    userMarkers = userMarkers.filter(m => m !== this);
                });
                userMarkers.push(marker);
            }
            window.setMarkerType(null); // Deselect button
        }
    });
    
    function openPoiSubmissionModal(lat, lng, mapId) {
        $('#poiLat').val(lat.toFixed(6)); $('#poiLng').val(lng.toFixed(6)); $('#poiMapId').val(mapId);
        $('#poiName').val(''); $('#poiSubmitFaIconInput').val(''); $('#poi_submit_icon_preview').html('');
        $('#poiCoordsPreview').text(`Lat: ${lat.toFixed(2)}, Lng: ${lng.toFixed(2)}`);
        $('#poiSubmissionMessage').text('');
        $('#poiSubmissionModal').removeClass('hidden').addClass('visible');
    }
    window.closePoiSubmissionModal = function() {
        $('#poiSubmissionModal').removeClass('visible').addClass('hidden');
        if (tempPoiSubmissionMarker) { window.map.removeLayer(tempPoiSubmissionMarker); tempPoiSubmissionMarker = null; }
        if (currentMarkerType === 'submitPoi') window.setMarkerType(null);
    }
    $('#poiForm').on('submit', async function(event) {
        event.preventDefault(); const formData = new FormData(this); const msgDiv = $('#poiSubmissionMessage');
        msgDiv.text('Submitting...').css('color', '#f0f0f0');
        try {
            const response = await fetch('submit_poi.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (response.ok && result.success) {
                msgDiv.text('POI submitted for review! Thank you.').css('color', 'lightgreen');
                setTimeout(window.closePoiSubmissionModal, 2500);
            } else {
                msgDiv.text(`Error: ${result.message || 'Could not submit POI.'}`).css('color', 'salmon');
            }
        } catch (error) {
            console.error('POI Submission error:', error);
            msgDiv.text('An error occurred during submission.').css('color', 'salmon');
        }
    });
    if ($('#poi_submit_icon_picker_button').length) {
        $('#poi_submit_icon_picker_button').iconpicker({ iconset: 'fontawesome6', icon: 'fas fa-map-marker-alt', rows: 5, cols: 10, placement: 'bottom' })
        .on('change', function(e) {
            if (e.icon) {
                $('#poiSubmitFaIconInput').val(e.icon);
                $('#poi_submit_icon_preview').html(`<i class="${e.icon}"></i>`);
            } else {
                $('#poiSubmitFaIconInput').val('');
                $('#poi_submit_icon_preview').html('');
            }
        });
    }

    function openCustomCollabMarkerModal(lat, lng) {
        $('#customMarkerLat').val(lat.toFixed(6));
        $('#customMarkerLng').val(lng.toFixed(6));
        $('#customMarkerCoordsPreview').text(`Lat: ${lat.toFixed(2)}, Lng: ${lng.toFixed(2)}`);
        $('#customMarkerFaIconInput').val('fas fa-map-pin'); // Default
        $('#custom_marker_icon_preview').html('<i class="fas fa-map-pin" style="color: #FFFFFF;"></i>'); // Default with color
        $('#customMarkerColorInput').val('#FFFFFF');
        $('#customMarkerTextInput').val('');
        $('#customCollabMarkerMessage').text('');
        $('#customCollabMarkerModal').removeClass('hidden').addClass('visible');
    }
    window.closeCustomCollabMarkerModal = function() {
        $('#customCollabMarkerModal').removeClass('visible').addClass('hidden');
        if (tempCustomCollabMarkerPlacement) { window.map.removeLayer(tempCustomCollabMarkerPlacement); tempCustomCollabMarkerPlacement = null; }
        if (currentMarkerType === 'customCollab') window.setMarkerType(null);
    }
    $('#customCollabMarkerForm').on('submit', async function(event) {
        event.preventDefault();
        const lat = $('#customMarkerLat').val(); const lng = $('#customMarkerLng').val();
        const fa_icon_class = $('#customMarkerFaIconInput').val();
        const marker_color = $('#customMarkerColorInput').val();
        const marker_text = $('#customMarkerTextInput').val();
        if (!fa_icon_class) { $('#customCollabMarkerMessage').text('Please select an icon.').css('color', 'salmon'); return; }
        sendCollabData('marker', 'add', {
            marker_type_name: 'custom_collab', latitude: lat, longitude: lng,
            fa_icon_class: fa_icon_class, marker_color: marker_color, marker_text: marker_text
        });
        $('#customCollabMarkerMessage').text('Marker sent!').css('color', 'lightgreen');
        setTimeout(window.closeCustomCollabMarkerModal, 1500);
    });
    if ($('#custom_marker_icon_picker_button').length) {
        $('#custom_marker_icon_picker_button').iconpicker({ iconset: 'fontawesome6', icon: 'fas fa-map-pin', rows: 5, cols: 10, placement: 'bottom' })
        .on('change', function(e) {
            if (e.icon) {
                $('#customMarkerFaIconInput').val(e.icon);
                $('#custom_marker_icon_preview').html(`<i class="${e.icon}" style="color: ${$('#customMarkerColorInput').val()};"></i>`);
            } else {
                $('#customMarkerFaIconInput').val('');
                $('#custom_marker_icon_preview').html('');
            }
        });
        $('#customMarkerColorInput').on('input change', function() {
            const currentIcon = $('#customMarkerFaIconInput').val();
            if (currentIcon) {
                $('#custom_marker_icon_preview').html(`<i class="${currentIcon}" style="color: ${$(this).val()};"></i>`);
            }
        });
    }

    window.undoLastMarker = function () { if (userMarkers.length > 0) window.map.removeLayer(userMarkers.pop()); else alert('No personal markers to undo.'); }
    window.removeAllMarkers = function () { userMarkers.forEach(marker => window.map.removeLayer(marker)); userMarkers = []; }
    const drawnItems = L.featureGroup().addTo(window.map);
    const drawControlOptions = { draw: { polyline: true, polygon: true, rectangle: true, circle: true, marker: false }, edit: { featureGroup: drawnItems, remove: true } };
    window.map.drawControl = new L.Control.Draw(drawControlOptions);
    window.map.on(L.Draw.Event.CREATED, function (event) { const layer = event.layer; const type = event.layerType; if (currentSessionKey) { const geojsonData = layer.toGeoJSON(); layer.client_layer_id = layer._leaflet_id; sendCollabData('drawing', 'add', { layer_type: type, geojson_data: JSON.stringify(geojsonData), client_layer_id: layer.client_layer_id }); } else { drawnItems.addLayer(layer); } });
    window.map.on(L.Draw.Event.EDITED, function (event) { if (!currentSessionKey) { console.log("Personal drawing edited."); return; } event.layers.eachLayer(function (layer) { if (layer.db_id) { console.log("TODO: Send collaborative drawing update for db_id:", layer.db_id, "New GeoJSON:", JSON.stringify(layer.toGeoJSON())); } }); });
    window.map.on(L.Draw.Event.DELETED, function (event) { if (!currentSessionKey) { console.log("Personal drawing deleted."); return; } event.layers.eachLayer(function (layer) { if (layer.db_id && confirm("Delete this collaborative drawing for everyone?")) { sendCollabData('drawing', 'delete', { db_item_id: layer.db_id }); if (collabDrawingsLayer.hasLayer(layer)) collabDrawingsLayer.removeLayer(layer); renderedCollabItemDBIds.drawings.delete(layer.db_id); } }); });
    window.enableDrawing = function () { window.map.addControl(window.map.drawControl); }
    window.disableDrawing = function () { if (window.map.drawControl) { for (const type in window.map.drawControl._toolbars.draw._modes) if (window.map.drawControl._toolbars.draw._modes[type].handler.enabled()) window.map.drawControl._toolbars.draw._modes[type].handler.disable(); for (const type in window.map.drawControl._toolbars.edit._modes) if (window.map.drawControl._toolbars.edit._modes[type].handler && window.map.drawControl._toolbars.edit._modes[type].handler.enabled()) window.map.drawControl._toolbars.edit._modes[type].handler.disable(); window.map.removeControl(window.map.drawControl); } }
    window.clearAllDrawings = function () { drawnItems.clearLayers(); }
    $('#compass').on('click', () => { rotateMap(90); }); let currentAngle = 0; const directions = ['N', 'E', 'S', 'W'];
    function rotateMap(angle) { currentAngle = (currentAngle + angle) % 360; $('#map').css('transform', `rotate(${currentAngle}deg)`); const effectiveAngle = (360 - currentAngle) % 360; $('#currentDirection').text(directions[Math.round(effectiveAngle / 90) % 4]); }
    $('#sidebar, #toggleMenu').on('click', function(e) { if (e.target.id === 'toggleMenu') $('#sidebar').toggleClass('hidden'); if ($(e.target).closest('#sidebar').length || e.target.id === 'toggleMenu') adjustMapSize(); });
    function adjustMapSize() { setTimeout(() => window.map.invalidateSize(), 300); }
    window.openAbout = function () { $('#modal').addClass('visible'); }
    window.closeAbout = function () { $('#modal').removeClass('visible'); }
    window.openHelp = function () { $('#help-modal').addClass('visible'); }
    window.closeHelp = function () { $('#help-modal').removeClass('visible'); }
    
    initializeMapSelector().then(() => {
        const sessionModal = $('#sessionModal'), createSessBtn = $('#createSessionButton'), joinSessBtn = $('#joinSessionButton'), sessKeyInput = $('#sessionKeyInput');
        const sessMsg = $('#sessionMessage'), sessInfoDiv = $('#sessionInfo'), activeSessKeySpan = $('#activeSessionKey'), activeSessMapNameSpan = $('#activeSessionMapName');
        const shareLinkInput = $('#shareableLink'), leaveSessBtn = $('#leaveSessionButton'), sessMapSelector = $('#sessionMapSelector'), mainMapSelector = $('#mapSelector');
        const mapElementRef = $('#map'); const sessCreateSection = $('#sessionCreateSection'), sessJoinSection = $('#sessionJoinSection'), sessModalInstructions = $('#sessionModalInstructions');
        const copyShareLinkBtn = $('#copyShareLinkButton'), copyStatusMsg = $('#copyStatusMessage');
        const shareToDiscordBtn = $('#shareToDiscordButton'), shareToWhatsAppBtn = $('#shareToWhatsAppButton');

        function updateSessionUI(isActive) {
            if (isActive) {
                sessionModal.removeClass('visible').addClass('hidden'); mapElementRef.removeClass('blurred');
                sessInfoDiv.show(); sessCreateSection.hide(); sessJoinSection.hide(); sessModalInstructions.hide();
                activeSessKeySpan.text(currentSessionKey); activeSessMapNameSpan.text(currentSessionMapName);
                const newUrl = `${location.protocol}//${location.host}${location.pathname}?session=${currentSessionKey}`;
                shareLinkInput.val(newUrl); try { history.pushState({ path: newUrl }, '', newUrl); } catch (e) { console.warn("URL update fail:", e); }
                mainMapSelector.prop('disabled', true);
            } else {
                sessionModal.addClass('visible').removeClass('hidden'); mapElementRef.addClass('blurred');
                sessInfoDiv.hide(); sessCreateSection.show(); sessJoinSection.show(); sessModalInstructions.show();
                try { history.pushState({ path: location.pathname }, '', location.pathname); } catch (e) { console.warn("URL update fail:", e); }
                mainMapSelector.prop('disabled', false);
            }
        }
        updateSessionUI(false);
        const urlParams = new URLSearchParams(location.search); const sessionKeyFromUrl = urlParams.get('session');
        if (sessionKeyFromUrl) { sessKeyInput.val(sessionKeyFromUrl); joinSession(sessionKeyFromUrl); } else { updateSessionUI(false); }

        createSessBtn.on('click', async () => {
            const selectedOpt = sessMapSelector.find('option:selected');
            if (!selectedOpt.length || !selectedOpt.data('mapId')) { sessMsg.text('Please select map.'); return; }
            const mapIdForSess = parseInt(selectedOpt.data('mapId')), mapNameForSess = selectedOpt.text();
            sessMsg.text('Creating...');
            try {
                const response = await fetch('create_session.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `map_id=${mapIdForSess}` });
                const data = await response.json();
                if (data.success) {
                    currentSessionKey = data.session_key; currentSessionMapId = data.map_id; currentSessionMapName = mapNameForSess;
                    updateSessionUI(true); startPolling();
                    if (currentMapId !== currentSessionMapId) { mainMapSelector.val(mainMapSelector.find(`option[data-map-id="${currentSessionMapId}"]`).val()).trigger('change'); }
                    loadAllCollabItems();
                } else { sessMsg.text(`Error: ${data.message}`); updateSessionUI(false); }
            } catch (err) { console.error("Create session err:", err); sessMsg.text('Network error.'); updateSessionUI(false); }
        });
        joinSessBtn.on('click', () => { const key = sessKeyInput.val().trim(); if (key) joinSession(key); else sessMsg.text('Enter session key.'); });
        async function joinSession(key) {
            sessMsg.text('Joining...');
            try {
                const response = await fetch(`join_session.php?session_key=${key}`); const data = await response.json();
                if (data.success) {
                    currentSessionKey = data.session_key; currentSessionMapId = data.map_id; let mapNameFound = false;
                    mainMapSelector.find('option').each(function() { const $opt = $(this); if (parseInt($opt.data('mapId')) === currentSessionMapId) { currentSessionMapName = $opt.text(); if (currentMapId !== currentSessionMapId) mainMapSelector.val($opt.val()).trigger('change'); mapNameFound = true; return false; } });
                    if (!mapNameFound) currentSessionMapName = `Map ID ${currentSessionMapId}`;
                    updateSessionUI(true); startPolling(); loadAllCollabItems();
                } else { sessMsg.text(`Error: ${data.message}`); updateSessionUI(false); }
            } catch (err) { console.error("Join session err:", err); sessMsg.text('Network error.'); updateSessionUI(false); }
        }
        leaveSessBtn.on('click', () => {
            currentSessionKey = null; currentSessionMapId = null; currentSessionMapName = '';
            stopPolling(); collabMarkersLayer.clearLayers(); collabDrawingsLayer.clearLayers();
            renderedCollabItemDBIds.markers.clear(); renderedCollabItemDBIds.drawings.clear();
            lastReceivedMarkerId = 0; lastReceivedDrawingId = 0;
            sessMsg.text('You have left the session.'); updateSessionUI(false);
        });
        if (copyShareLinkBtn.length) { copyShareLinkBtn.on('click', () => { shareLinkInput[0].select(); shareLinkInput[0].setSelectionRange(0, 99999); try { const succ = document.execCommand('copy'); copyStatusMsg.text(succ ? 'Link copied!' : 'Copy failed.').show().delay(2000).fadeOut(); } catch (err) { copyStatusMsg.text('Copy failed.').show().delay(2000).fadeOut(); console.error('Copy err:', err); } }); }
        if (shareToDiscordBtn.length) { shareToDiscordBtn.on('click', () => { if (!currentSessionKey) return; const text = `Join my Arma Reforger map session! Link: ${shareLinkInput.val()}\nOr use Session Key: ${currentSessionKey}`; alert("Copy link/key for Discord.\n\n" + text); }); }
        if (shareToWhatsAppBtn.length) { shareToWhatsAppBtn.on('click', () => { if (!currentSessionKey) return; const text = `Join my Arma Reforger map session! Link: ${shareLinkInput.val()}\nOr use Session Key: ${currentSessionKey}`; const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(text)}`; window.open(whatsappUrl, '_blank'); }); }
    });
    function startPolling() { if (collabPollingInterval) clearInterval(collabPollingInterval); collabPollingInterval = setInterval(fetchCollabUpdates, POLLING_RATE); console.log("Polling started."); }
    function stopPolling() { if (collabPollingInterval) clearInterval(collabPollingInterval); collabPollingInterval = null; console.log("Polling stopped."); }
    async function fetchCollabUpdates() {
        if (!currentSessionKey) return;
        try {
            const response = await fetch(`get_collab_updates.php?session_key=${currentSessionKey}&client_id=${collabClientId}&last_marker_id=${lastReceivedMarkerId}&last_drawing_id=${lastReceivedDrawingId}`);
            const data = await response.json();
            if (data.success) {
                if (data.map_id && data.map_id !== currentSessionMapId) {
                    console.warn(`Session map ${data.map_id} differs. Aligning...`); currentSessionMapId = data.map_id; let mapFound = false; const mainMapSel = $('#mapSelector');
                    mainMapSel.find('option').each(function() { const $opt = $(this); if (parseInt($opt.data('mapId')) === currentSessionMapId) { currentSessionMapName = $opt.text(); $('#activeSessionMapName').text(currentSessionMapName); if (currentMapId !== currentSessionMapId) mainMapSel.val($opt.val()).trigger('change'); mapFound = true; return false; } });
                    if (!mapFound) console.error("Session map ID from server not found!");
                    collabMarkersLayer.clearLayers(); collabDrawingsLayer.clearLayers(); renderedCollabItemDBIds.markers.clear(); renderedCollabItemDBIds.drawings.clear(); lastReceivedMarkerId = 0; lastReceivedDrawingId = 0;
                }
                data.markers.forEach(mD => { renderCollabMarker(mD); if (mD.id > lastReceivedMarkerId) lastReceivedMarkerId = mD.id; });
                data.drawings.forEach(dD => { renderCollabDrawing(dD); if (dD.id > lastReceivedDrawingId) lastReceivedDrawingId = dD.id; });
            } else { console.error("Update fetch error:", data.message); if (data.message === 'Invalid session.') { alert("Session invalid."); $('#leaveSessionButton').trigger('click'); } }
        } catch (err) { console.error("Network error fetching updates:", err); }
    }
    async function loadAllCollabItems() { if (!currentSessionKey) return; console.log("Loading all for session:", currentSessionKey); lastReceivedMarkerId = 0; lastReceivedDrawingId = 0; collabMarkersLayer.clearLayers(); collabDrawingsLayer.clearLayers(); renderedCollabItemDBIds.markers.clear(); renderedCollabItemDBIds.drawings.clear(); await fetchCollabUpdates(); }
    async function sendCollabData(itemType, action, payload) {
        if (!currentSessionKey) return; const fd = new FormData(); fd.append('session_key', currentSessionKey); fd.append('client_id', collabClientId); fd.append('item_type', itemType); fd.append('action', action);
        for (const key in payload) fd.append(key, payload[key]);
        try { const r = await fetch('manage_collab_item.php', { method: 'POST', body: fd }); const d = await r.json(); if (d.success) console.log(`Collab ${itemType} ${action} ok:`, d.message, d.item_id); else console.error(`Collab ${itemType} ${action} fail:`, d.message); }
        catch (err) { console.error(`Net err send collab ${itemType}:`, err); }
    }
    function renderCollabMarker(markerData) {
        if (renderedCollabItemDBIds.markers.has(markerData.id) || currentMapId !== currentSessionMapId) return;
        let iconToUse;
        if (markerData.marker_type === 'custom_collab' && markerData.fa_icon_class) {
            iconToUse = L.divIcon({ html: `<i class="${markerData.fa_icon_class}" style="font-size:24px; color:${markerData.marker_color || '#FFF'}; line-height:1;"></i>`, className: 'fontawesome-map-marker', iconSize: [24, 24], iconAnchor: [12, 12], popupAnchor: [0, -12] });
        } else { // Fallback for older tactical markers or if custom_collab is missing icon
            iconToUse = customIcons[markerData.marker_type] || customIcons.loot;
        }
        const marker = L.marker([markerData.latitude, markerData.longitude], { icon: iconToUse }).addTo(collabMarkersLayer);
        marker.db_id = markerData.id; marker.client_id_owner = markerData.client_id;
        if (markerData.marker_text) { marker.bindTooltip(markerData.marker_text, { permanent: false, direction: 'top', className: 'custom-collab-marker-tooltip', offset: [0, -12] }); }
        marker.on('click', function(e) { L.DomEvent.stopPropagation(e); if (confirm(`Delete collaborative marker? (Owner: ${this.client_id_owner === collabClientId ? "You" : "Other"})`)) { sendCollabData('marker', 'delete', { db_item_id: this.db_id }); collabMarkersLayer.removeLayer(this); renderedCollabItemDBIds.markers.delete(this.db_id); } });
        renderedCollabItemDBIds.markers.add(markerData.id);
    }
    function renderCollabDrawing(drawingData) {
        if (renderedCollabItemDBIds.drawings.has(drawingData.id) || currentMapId !== currentSessionMapId) return;
        try {
            const geoJsonFeature = JSON.parse(drawingData.geojson_data);
            const layer = L.geoJSON(geoJsonFeature).getLayers()[0]; // Assumes single feature
            if (layer) {
                layer.db_id = drawingData.id; layer.client_id_owner = drawingData.client_id; layer.client_layer_id = drawingData.client_layer_id;
                collabDrawingsLayer.addLayer(layer); renderedCollabItemDBIds.drawings.add(drawingData.id);
                layer.on('click', function(e) { L.DomEvent.stopPropagation(e); if (window.map.drawControl && window.map.drawControl._toolbars.edit && window.map.drawControl._toolbars.edit._activeMode && window.map.drawControl._toolbars.edit._featureGroup.hasLayer(this)) return; if (confirm(`Delete collaborative drawing? (Owner: ${this.client_id_owner === collabClientId ? "You" : "Other"})`)) { sendCollabData('drawing', 'delete', { db_item_id: this.db_id }); collabDrawingsLayer.removeLayer(this); renderedCollabItemDBIds.drawings.delete(this.db_id); } });
            } else console.error("Could not create layer from GeoJSON:", drawingData);
        } catch (e) { console.error("Error parsing GeoJSON for collab drawing:", e, drawingData.geojson_data); }
    }
});