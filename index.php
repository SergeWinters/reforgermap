<!DOCTYPE html>
<html lang="en">
<head>
    <title>Interactive Map with POI, Drawing Tools, for Arma Reforger</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />
    <link rel="stylesheet" href="https://unpkg.com/@raruto/leaflet-gesture-handling@latest/dist/leaflet-gesture-handling.min.css" type="text/css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-iconpicker/1.10.0/css/bootstrap-iconpicker.min.css"/>

    <meta name="description" content="Plan your strategies and navigate efficiently in Arma Reforger with our interactive map tool. Add markers, search POIs, draw plans, and collaborate with your team.">
    <meta name="keywords" content="Arma Reforger map, interactive map, POI search, markers, strategy planning, drawing tools, minimap, map tools for games, collaboration, font awesome icons">
    <meta name="author" content="ForgeNEX Interactive">
    <meta name="robots" content="index, follow">
    <meta name="language" content="en">
    <meta name="theme-color" content="#333">

    <meta property="og:title" content="Interactive Map for Arma Reforger with Collaboration & Icons">
    <meta property="og:description" content="A powerful tool for players to plan strategies, add markers with custom icons, and interact with the map of Arma Reforger. Includes POI search, drawing tools, and real-time collaboration.">
    <meta property="og:image" content="https://www.reforgemap.com/og-image.png">
    <meta property="og:url" content="https://www.reforgemap.com">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Reforger Interactive Map">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Interactive Map for Arma Reforger with Collaboration & Icons">
    <meta name="twitter:description" content="Plan your strategies and collaborate effectively with our interactive map for Arma Reforger, featuring real-time drawing, marker sharing, and Font Awesome POI icons.">
    <meta name="twitter:image" content="https://www.reforgemap.com/og-image.png">
    <link rel="canonical" href="https://www.reforgemap.com">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">

    <script type="application/ld+json">
    {
        "@context": "http://schema.org",
        "@type": "WebSite",
        "name": "Reforger Interactive Map",
        "url": "https://www.reforgemap.com",
        "potentialAction": { "@type": "SearchAction", "target": "https://www.reforgemap.com/?q={search_term_string}", "query-input": "required name=search_term_string" },
        "description": "Interactive map tool for Arma Reforger players. Plan strategies, add markers with custom icons, search POIs, and collaborate with your team in real-time."
    }
    </script>
    <link rel="stylesheet" href="style.css">
	<script defer src="https://stats.forgenex.com/pixel/5YKZG0nR2y0g7vKK"></script>
    <style>
        /* General Modal Styling */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.7); }
        .modal.visible { display: flex; align-items: center; justify-content: center; }
        .modal-content { background-color: #2c2c2c; color: #f0f0f0; margin: auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 500px; /* Slightly narrower */ border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        .modal-content iframe { width: 100%; height: 400px; border: none; }
        /* Buttons in modals */
        .modal-content button, #sessionModal button {
            background-color: #5cb85c; color: white; padding: 8px 12px; /* Smaller padding */
            margin-top: 5px; /* Reduced margin-top */ border: none; border-radius: 4px;
            cursor: pointer; font-size: 0.9em; /* Smaller font */
        }
        .modal-content button:hover, #sessionModal button:hover { opacity: 0.9; }

        /* Session Modal Specifics */
        #sessionModal input[type="text"], #sessionModal select {
            background-color: #444; color: #fff; border: 1px solid #666;
            border-radius: 4px; padding: 8px; margin-bottom: 10px; box-sizing: border-box;
        }
        #sessionModal label { display: block; margin-bottom: 5px; font-weight: bold; }
        #sessionModal .form-row {
            display: flex;
            align-items: center; /* Vertically align items in the row */
            margin-bottom: 10px; /* Space between form rows */
        }
        #sessionModal .form-row label {
            margin-bottom: 0; /* Remove bottom margin if label is part of a row */
            margin-right: 10px; /* Space between label and input/select */
            flex-shrink: 0; /* Prevent label from shrinking */
        }
        #sessionModal .form-row select, #sessionModal .form-row input[type="text"] {
            flex-grow: 1; /* Allow input/select to take available space */
            margin-bottom: 0; /* Remove bottom margin as it's part of a row */
            margin-right: 10px; /* Space before button */
        }
        #sessionModal .form-row button {
            flex-shrink: 0; /* Prevent button from shrinking */
            margin-top: 0; /* Align with input/select */
            padding: 8px 12px; /* Consistent with other buttons */
        }
         /* Full width button for Leave Session and potentially others if needed */
        #sessionModal .full-width-button {
            display: block;
            width: 100%;
            box-sizing: border-box;
        }


        /* POI submission modal specific styles */
        #poiSubmissionModal label { display: block; margin-top: 10px; margin-bottom: 5px; font-weight: bold; }
        #poiSubmissionModal input[type="text"],
        #poiSubmissionModal input[type="file"] { width: calc(100% - 22px); padding: 10px; margin-bottom: 10px; border: 1px solid #555; border-radius: 4px; background-color: #333; color: #fff; box-sizing: border-box;}
        #poiSubmissionModal button[type="submit"] { background-color: #5cb85c; }
        #poiSubmissionModal button[type="button"] { background-color: #d9534f; margin-left: 10px; }
        #poi_submit_icon_picker_button { font-size: 0.9em; padding: 6px 10px; background-color: #17a2b8; }
        #poi_submit_icon_preview { margin-left: 10px; font-size: 1.2em; }

        #sidebar button.selected { background-color: #007bff; color: white; border-color: #0056b3; }
        .custom-popup .leaflet-popup-content-wrapper { background: #333; color: #fff; border-radius: 5px; padding: 1px; }
        .custom-popup .leaflet-popup-content { margin: 0; padding: 10px; font-size: 14px; line-height: 1.4; }
        .custom-popup img { border-radius: 3px; }
        .custom-popup strong { display: block; margin-bottom: 5px; }
        #map { filter: blur(0px); transition: filter 0.3s ease-in-out; }
        #map.blurred { filter: blur(5px); }
        .fontawesome-map-marker { text-align: center; }
        .fontawesome-map-marker i { display: block; }

        /* Resetting styles from previous fix attempt, as flexbox will handle layout */
        #sessionModal .modal-content > * { display: block; width: auto; position: relative; float: none; }
        #sessionModal .modal-content > div#sessionCreateSection,
        #sessionModal .modal-content > div#sessionJoinSection,
        #sessionModal .modal-content > div#sessionInfo {
            margin-bottom: 15px;
        }
        #sessionModal .modal-content > hr {
            width: 100%; height: 1px; border: 0; background-color: #555; margin: 15px 0; /* Reduced margin */
        }
    </style>
</head>
<body>
    <!-- Collaboration Session Modal -->
   <div id="sessionModal" class="modal visible">
       <div class="modal-content">
           <h4>Collaboration Session</h4>
           <p id="sessionModalInstructions">Create a new session to collaborate with others, or join an existing one using a session key.</p>

           <div id="sessionCreateSection">
               <label for="sessionMapSelector">Select Map for New Session:</label>
               <div class="form-row">
                   <select id="sessionMapSelector"></select>
                   <button id="createSessionButton" type="button">Create</button>
               </div>
           </div>
           <hr>
           <div id="sessionJoinSection">
               <label for="sessionKeyInput">Enter Session Key to Join:</label>
               <div class="form-row">
                   <input type="text" id="sessionKeyInput" placeholder="Paste session key here">
                   <button id="joinSessionButton" type="button">Join</button>
               </div>
           </div>

           <div id="sessionInfo" style="margin-top: 20px; display: none; text-align:center;">
               <p><strong>Active Session:</strong> <span id="activeSessionKey" style="font-weight:bold; color:lightgreen;"></span></p>
               <p><strong>Map:</strong> <span id="activeSessionMapName" style="font-weight:bold;"></span></p>
               <label for="shareableLink" style="display:block; margin-top:15px;">Share this link with friends:</label>
               <input type="text" id="shareableLink" readonly style="width: calc(100% - 20px); padding: 8px; background-color:#555; color:#fff; border:1px solid #666; text-align:center; margin-bottom:10px; box-sizing: border-box;">
               <button id="leaveSessionButton" type="button" style="background-color: #d9534f;" class="full-width-button">Leave Session</button>
           </div>
           <div id="sessionMessage" style="margin-top: 15px; color: salmon; text-align:center; font-weight:bold;"></div>
       </div>
   </div>

    <button id="toggleMenu">☰</button>
    <div id="sidebar">
        <h3>Markers</h3>
        <div id="menuItems">
            <button id="enemyButton" onclick="setMarkerType('enemy')">Enemy</button>
            <button id="exitButton" onclick="setMarkerType('exit')">Attack</button>
            <button id="respawnButton" onclick="setMarkerType('respawn')">Respawn</button>
        </div>
        <button onclick="undoLastMarker()">Undo Last (Personal)</button>
        <button onclick="removeAllMarkers()">Remove All (Personal)</button>
        <h3>Drawing Tools</h3>
        <button onclick="enableDrawing()">Enable Drawing</button>
        <button onclick="disableDrawing()">Disable Drawing</button>
        <button onclick="clearAllDrawings()">Clear All Drawings (Personal)</button>
        <h3>Select Map</h3>
        <select id="mapSelector" onchange="handleMapChange(event)"></select>
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
			<div id="help-modal" class="modal hidden">
				<div class="modal-content">
					<iframe src="help.html" frameborder="0"></iframe>
					<button onclick="closeHelp()">Close</button>
				</div>
			</div>
			<a href="#" onclick="openAbout()">About</a>
			<div id="modal" class="modal hidden">
				<div class="modal-content">
					<iframe src="about.html" frameborder="0"></iframe>
					<button onclick="closeAbout()">Close</button>
				</div>
			</div>
            <a href="https://discord.gg/MfNDSg85Pf" target="_blank">Discord</a>
        </div>
        <div>
            Version 6.4.2 - <a href="https://github.com/SergeWinters/reforgermap/commits/main/" target="_blank">Changelog</a>
        </div>
    </div>

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
                    <label for="poiSubmitFaIconInput">Icon (optional):</label>
                    <div class="input-group" style="display: flex; align-items: center; margin-bottom:10px;">
                        <input type="text" id="poiSubmitFaIconInput" name="fa_icon_class" class="form-control form-control-sm" placeholder="e.g., fas fa-star" style="flex-grow:1; margin-right: 5px;">
                        <button type="button" id="poi_submit_icon_picker_button" class="btn btn-info btn-sm" style="margin-top:0;">
                            <i class="fas fa-icons"></i> Pick
                        </button>
                        <span id="poi_submit_icon_preview" style="margin-left: 10px; font-size: 1.5em;"></span>
                    </div>
                </div>
                <div>
                    <p>Coordinates: <span id="poiCoordsPreview"></span> (Drag marker on map to adjust)</p>
                </div>
                <button type="submit">Submit POI</button>
                <button type="button" onclick="closePoiSubmissionModal()">Cancel</button>
            </form>
            <div id="poiSubmissionMessage" style="margin-top: 10px;"></div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
    <script src="https://unpkg.com/@raruto/leaflet-gesture-handling@latest/dist/leaflet-gesture-handling.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet-rotatedmarker@0.2.0/leaflet.rotatedMarker.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-iconpicker/1.10.0/js/bootstrap-iconpicker.bundle.min.js"></script>

    <script src="script.js" defer></script>

    <div id="minimapContainer" style="display: none;">
        <button id="closeMinimap">×</button>
        <div id="minimap"></div>
    </div>
    <button id="toggleMinimap">Toggle MiniMap</button>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const mc = document.getElementById('minimapContainer'), tmb = document.getElementById('toggleMinimap'), cmb = document.getElementById('closeMinimap');
            if (!mc || !tmb || !cmb) { console.warn("Minimap UI missing."); return; }
            tmb.addEventListener('click', () => {
                const iv = mc.style.display !== 'none'; mc.style.display = iv ? 'none' : 'block';
                if (!iv && window.minimap) { window.minimap.invalidateSize(); if (window.map && window.map.getCenter()) { let mz = window.map.getZoom() - 2; if (window.map.getZoom() <= window.map.getMinZoom() + 1) mz = window.minimap.getMinZoom() !== undefined ? window.minimap.getMinZoom() : -2; else if (window.map.getZoom() <= 1) mz = -1; window.minimap.setView(window.map.getCenter(), mz, { animate: false, noMoveStart: true }); } }
            });
            cmb.addEventListener('click', () => { mc.style.display = 'none'; });
            if (L && window.map) {
                const mb = [[0,0],[2048,2048]]; window.minimap = L.map('minimap', {crs:L.CRS.Simple, zoomControl:false, attributionControl:false, maxBounds:mb, maxBoundsViscosity:1.0, gestureHandling:false}); window.minimapLayer = L.imageOverlay('', mb).addTo(window.minimap);
                window.map.on('move zoomend', () => { if (mc.style.display !== 'none' && window.minimap) { const mcc = window.map.getCenter(); let mz = window.map.getZoom()-2; if (window.map.getZoom() <= window.map.getMinZoom()+1) mz = window.minimap.getMinZoom() !== undefined ? window.minimap.getMinZoom() : -2; else if (window.map.getZoom() <= 1) mz = -1; if(window.minimap.options.minZoom !== undefined && mz < window.minimap.options.minZoom) mz=window.minimap.options.minZoom; if(window.minimap.options.maxZoom !== undefined && mz > window.minimap.options.maxZoom) mz=window.minimap.options.maxZoom; window.minimap.setView(mcc, mz, {animate:false, noMoveStart:true});}});
                window.minimap.on('click', (e) => { window.map.setView(e.latlng, window.map.getZoom()); });
            } else console.error("Leaflet/map missing for minimap (inline).")
        });
    </script>
</body>
</html>