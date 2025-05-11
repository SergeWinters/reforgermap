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
    let tempPoiSubmissionMarker = null; // For POI submission
    let tempCustomCollabMarkerPlacement = null; // For custom collab marker placement

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

    function generateUUIDv4() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    function loadMapImage(mapImagePath) {
        if (currentMapLayer) window.map.removeLayer(currentMapLayer);
        currentMapLayer = L.imageOverlay(mapImagePath, bounds).addTo(window.map);
        window.map.setView([1024, 1024], 1);
    }

    async function loadPoisForMap(mapId) {
        poiMarkersLayer.clearLayers();
        if (!mapId) return;
        try {
            const response = await fetch(`get_pois.php?map_id=${mapId}`);
            if (!response.ok) throw new Error(`POIs fetch failed: ${response.statusText}`);
            const pois = await response.json();
            pois.forEach(poi => {
                let poiIconInstance;
                if (poi.fa_icon_class) {
                    poiIconInstance = L.divIcon({ html: `<i class="${poi.fa_icon_class}" style="font-size: 24px; color: #FFF; line-height: 1;"></i>`, className: 'fontawesome-map-marker', iconSize: [24, 24], iconAnchor: [12, 12], popupAnchor: [0, -12] });
                } else { poiIconInstance = customIcons.loot; }
                const marker = L.marker([poi.latitude, poi.longitude], { icon: poiIconInstance }).addTo(poiMarkersLayer);
                const popupContent = (poi.image_path ? `<img src="${poi.image_path}" alt="${poi.name}" style="width:400px;height:auto;display:block;margin-bottom:5px;">`:'') + `<strong>${poi.name}</strong>` + (poi.fa_icon_class ? `<br><small>Icon: <i class="${poi.fa_icon_class}"></i> <span style="font-family:monospace;">${poi.fa_icon_class}</span></small>`:'');
                marker.bindPopup(popupContent, { maxWidth: "auto", className: "custom-popup" });
            });
        } catch (error) { console.error("Error loading POIs:", error); }
    }
    
    window.handleMapChange = async function (event) {
        const selOpt = event.target.selectedOptions[0]; const imgPath = selOpt.value; const newMapId = parseInt(selOpt.dataset.mapId);
        if (currentSessionKey && newMapId !== currentSessionMapId) { alert("In active session on different map. Leave session to change maps."); $(event.target).val($(event.target).find(`option[data-map-id="${currentMapId}"]`).val()); return; }
        currentMapId = newMapId; loadMapImage(imgPath); await loadPoisForMap(currentMapId);
        if (window.minimapLayer) window.minimapLayer.setUrl(imgPath);
    }
    
    async function initializeMapSelector() {
        const mapSel = $('#mapSelector'), sessMapSel = $('#sessionMapSelector'); if (!mapSel.length || !sessMapSel.length) { console.error("Map selectors not found!"); return; }
        try {
            const resp = await fetch('get_maps.php'); if (!resp.ok) throw new Error(`Maps fetch failed: ${resp.statusText}`);
            const mapsData = await resp.json();
            if (mapsData.length === 0) { alert("No maps."); mapSel.html("<option>No maps</option>").prop('disabled', true); sessMapSel.html("<option>No maps</option>").prop('disabled', true); return; }
            mapsData.forEach(mD => { const opt = `<option value="${mD.image_path}" data-map-id="${mD.id}">${mD.name}</option>`; mapSel.append(opt); sessMapSel.append(opt); });
            if (mapSel.find('option').length > 0) {
                mapSel.prop('selectedIndex', 0); sessMapSel.prop('selectedIndex', 0); const firstOpt = mapSel.find('option:selected');
                currentMapId = parseInt(firstOpt.data('mapId')); loadMapImage(firstOpt.val()); await loadPoisForMap(currentMapId);
                if (window.minimap && window.minimapLayer) { window.minimapLayer.setUrl(firstOpt.val()); window.minimap.setView([1024,1024], -1); window.map.fire('move'); }
            }
        } catch (err) { console.error("Init map selector err:", err); alert("Critical error loading map data."); }
    }

    $('#zoomLevel').text(window.map.getZoom()); window.map.on('zoomend', () => { $('#zoomLevel').text(window.map.getZoom()); });
    window.map.on('mousemove', (e) => { $('#mouseCoordinates').text(`Y: ${e.latlng.lat.toFixed(2)}, X: ${e.latlng.lng.toFixed(2)}`); });

    const customIcons = { // Tactical markers (image-based)
        enemy: L.icon({iconUrl:'images/icon-enemy.png',iconSize:[32,32],iconAnchor:[16,16]}), exit: L.icon({iconUrl:'images/icon-exit.png',iconSize:[32,32],iconAnchor:[16,16]}),
        loot: L.icon({iconUrl:'images/icon-loot.png',iconSize:[24,24],iconAnchor:[12,12]}), submitPoiTemp: L.icon({iconUrl:'images/icon-poi-submit-temp.png',iconSize:[32,32],iconAnchor:[16,32]})
    };
    let userMarkers = []; let currentMarkerType = null;

    window.setMarkerType = function (type) {
        if (currentMarkerType === 'submitPoi' && type !== 'submitPoi' && tempPoiSubmissionMarker) { window.map.removeLayer(tempPoiSubmissionMarker); tempPoiSubmissionMarker = null; }
        if (currentMarkerType === 'customCollab' && type !== 'customCollab' && tempCustomCollabMarkerPlacement) { window.map.removeLayer(tempCustomCollabMarkerPlacement); tempCustomCollabMarkerPlacement = null; }

        currentMarkerType = type; $('#sidebar button').removeClass('selected'); let selBtn;
        if (type === 'submitPoi') selBtn = $('#sendPoiButton');
        else if (type === 'customCollab') selBtn = $('#customCollabMarkerButton');
        else if (type) selBtn = $(`#${type}Button`);
        if (selBtn && selBtn.length) selBtn.addClass('selected');
    }

    window.map.on('click', e => {
        if (window.map.drawControl && window.map.drawControl._toolbars.draw._activeMode) return;
        if ($(e.originalEvent.target).closest('.leaflet-popup-pane, .leaflet-control, .iconpicker-popover, .modal-content').length) return; // Ignore clicks inside these elements

        const lat = e.latlng.lat, lng = e.latlng.lng;
        if (currentMarkerType === 'submitPoi') {
            if (!currentMapId) { alert("Select map."); window.setMarkerType(null); return; }
            if (tempPoiSubmissionMarker) window.map.removeLayer(tempPoiSubmissionMarker);
            tempPoiSubmissionMarker = L.marker([lat,lng], {icon:customIcons.submitPoiTemp, draggable:true}).addTo(window.map);
            tempPoiSubmissionMarker.on('dragend', function(ev){ const ll = ev.target.getLatLng(); $('#poiLat').val(ll.lat.toFixed(6)); $('#poiLng').val(ll.lng.toFixed(6)); $('#poiCoordsPreview').text(`Lat: ${ll.lat.toFixed(2)}, Lng: ${ll.lng.toFixed(2)}`); });
            openPoiSubmissionModal(lat, lng, currentMapId);
        } else if (currentMarkerType === 'customCollab') {
            if (!currentSessionKey) { alert("You must be in a collaboration session to place custom collab markers."); window.setMarkerType(null); return; }
            if (tempCustomCollabMarkerPlacement) window.map.removeLayer(tempCustomCollabMarkerPlacement); // Remove previous temp marker
            // Place a temporary, non-interactive marker
            tempCustomCollabMarkerPlacement = L.marker([lat, lng], { icon: L.divIcon({className:'temp-placement-dot', html:'.', iconSize:[6,6], iconAnchor:[3,3]}) }).addTo(window.map);
            openCustomCollabMarkerModal(lat, lng);
        } else if (currentMarkerType) { // Standard tactical markers
            if (currentSessionKey) { sendCollabData('marker', 'add', { marker_type_name: currentMarkerType, latitude: lat, longitude: lng }); }
            else { const m = L.marker([lat,lng], {icon:customIcons[currentMarkerType]}).addTo(window.map); m.on('click', function(ev){ L.DomEvent.stopPropagation(ev); window.map.removeLayer(this); userMarkers = userMarkers.filter(um => um !== this); }); userMarkers.push(m); }
            window.setMarkerType(null);
        }
    });
    
    function openPoiSubmissionModal(lat,lng,mapId){ $('#poiLat').val(lat.toFixed(6)); $('#poiLng').val(lng.toFixed(6)); $('#poiMapId').val(mapId); $('#poiName').val(''); $('#poiSubmitFaIconInput').val(''); $('#poi_submit_icon_preview').html(''); $('#poiCoordsPreview').text(`Lat: ${lat.toFixed(2)}, Lng: ${lng.toFixed(2)}`); $('#poiSubmissionMessage').text(''); $('#poiSubmissionModal').removeClass('hidden').addClass('visible'); }
    window.closePoiSubmissionModal = function(){ $('#poiSubmissionModal').removeClass('visible').addClass('hidden'); if(tempPoiSubmissionMarker){window.map.removeLayer(tempPoiSubmissionMarker);tempPoiSubmissionMarker=null;} if(currentMarkerType==='submitPoi')window.setMarkerType(null); }
    $('#poiForm').on('submit', async function(ev){ ev.preventDefault(); const fd=new FormData(this); const msgDiv=$('#poiSubmissionMessage'); msgDiv.text('Submitting...').css('color','#f0f0f0'); try { const r=await fetch('submit_poi.php',{method:'POST',body:fd}); const d=await r.json(); if(r.ok && d.success){msgDiv.text('POI submitted!').css('color','lightgreen');setTimeout(window.closePoiSubmissionModal,2500);}else{msgDiv.text(`Error: ${d.message||'Failed.'}`).css('color','salmon');}}catch(err){console.error('POI Submit err:',err);msgDiv.text('Network error.').css('color','salmon');}});
    if($('#poi_submit_icon_picker_button').length){ $('#poi_submit_icon_picker_button').iconpicker({iconset:'fontawesome6',icon:'fas fa-map-marker-alt',rows:5,cols:10,placement:'bottom'}).on('change',function(e){if(e.icon){$('#poiSubmitFaIconInput').val(e.icon);$('#poi_submit_icon_preview').html(`<i class="${e.icon}"></i>`);}else{$('#poiSubmitFaIconInput').val('');$('#poi_submit_icon_preview').html('');}}); }

    // --- Custom Collab Marker Modal Logic ---
    function openCustomCollabMarkerModal(lat, lng) {
        $('#customMarkerLat').val(lat.toFixed(6));
        $('#customMarkerLng').val(lng.toFixed(6));
        $('#customMarkerCoordsPreview').text(`Lat: ${lat.toFixed(2)}, Lng: ${lng.toFixed(2)}`);
        // Reset form fields (optional, good practice)
        $('#customMarkerFaIconInput').val('fas fa-map-pin'); // Default icon
        $('#custom_marker_icon_preview').html('<i class="fas fa-map-pin"></i>');
        $('#customMarkerColorInput').val('#FFFFFF'); // Default color
        $('#customMarkerTextInput').val('');
        $('#customCollabMarkerMessage').text('');
        $('#customCollabMarkerModal').removeClass('hidden').addClass('visible');
    }

    window.closeCustomCollabMarkerModal = function() {
        $('#customCollabMarkerModal').removeClass('visible').addClass('hidden');
        if (tempCustomCollabMarkerPlacement) { window.map.removeLayer(tempCustomCollabMarkerPlacement); tempCustomCollabMarkerPlacement = null; }
        if (currentMarkerType === 'customCollab') window.setMarkerType(null); // Deselect button
    }

    $('#customCollabMarkerForm').on('submit', async function(event) {
        event.preventDefault();
        const lat = $('#customMarkerLat').val();
        const lng = $('#customMarkerLng').val();
        const fa_icon_class = $('#customMarkerFaIconInput').val();
        const marker_color = $('#customMarkerColorInput').val();
        const marker_text = $('#customMarkerTextInput').val();

        if (!fa_icon_class) {
            $('#customCollabMarkerMessage').text('Please select an icon.').css('color', 'salmon');
            return;
        }

        sendCollabData('marker', 'add', {
            marker_type_name: 'custom_collab', // Specific type for these
            latitude: lat,
            longitude: lng,
            fa_icon_class: fa_icon_class,
            marker_color: marker_color,
            marker_text: marker_text
        });
        // Message will be cleared by close or next open, or add success message
        $('#customCollabMarkerMessage').text('Marker sent!').css('color', 'lightgreen');
        setTimeout(window.closeCustomCollabMarkerModal, 1500);
    });

    if ($('#custom_marker_icon_picker_button').length) {
        $('#custom_marker_icon_picker_button').iconpicker({
            iconset: 'fontawesome6',
            icon: 'fas fa-map-pin', // Default selected icon
            rows: 5, cols: 10, placement: 'bottom'
        }).on('change', function(e) {
            if (e.icon) {
                $('#customMarkerFaIconInput').val(e.icon);
                $('#custom_marker_icon_preview').html(`<i class="${e.icon}" style="color: ${$('#customMarkerColorInput').val()};"></i>`);
            } else {
                $('#customMarkerFaIconInput').val('');
                $('#custom_marker_icon_preview').html('');
            }
        });
        // Update preview color when color input changes
        $('#customMarkerColorInput').on('input change', function() {
            const currentIcon = $('#customMarkerFaIconInput').val();
            if (currentIcon) {
                $('#custom_marker_icon_preview').html(`<i class="${currentIcon}" style="color: ${$(this).val()};"></i>`);
            }
        });
    }
    // --- End Custom Collab Marker Modal Logic ---

    window.undoLastMarker=function(){if(userMarkers.length>0)window.map.removeLayer(userMarkers.pop());else alert('No personal markers.');}
    window.removeAllMarkers=function(){userMarkers.forEach(m=>window.map.removeLayer(m));userMarkers=[];}
    const drawnItems=L.featureGroup().addTo(window.map); const drawControlOptions={draw:{polyline:true,polygon:true,rectangle:true,circle:true,marker:false},edit:{featureGroup:drawnItems,remove:true}};
    window.map.drawControl=new L.Control.Draw(drawControlOptions);
    window.map.on(L.Draw.Event.CREATED,function(ev){const l=ev.layer,t=ev.layerType;if(currentSessionKey){const gj=l.toGeoJSON();l.client_layer_id=l._leaflet_id;sendCollabData('drawing','add',{layer_type:t,geojson_data:JSON.stringify(gj),client_layer_id:l.client_layer_id});}else{drawnItems.addLayer(l);}});
    window.map.on(L.Draw.Event.EDITED,function(ev){if(!currentSessionKey){console.log("Personal drawing edited.");return;}ev.layers.eachLayer(function(l){if(l.db_id){console.log("TODO: Send collab drawing update for db_id:",l.db_id,"New GeoJSON:",JSON.stringify(l.toGeoJSON()));}});});
    window.map.on(L.Draw.Event.DELETED,function(ev){if(!currentSessionKey){console.log("Personal drawing deleted.");return;}ev.layers.eachLayer(function(l){if(l.db_id&&confirm("Delete collaborative drawing?")){sendCollabData('drawing','delete',{db_item_id:l.db_id});if(collabDrawingsLayer.hasLayer(l))collabDrawingsLayer.removeLayer(l);renderedCollabItemDBIds.drawings.delete(l.db_id);}});});
    window.enableDrawing=function(){window.map.addControl(window.map.drawControl);}
    window.disableDrawing=function(){if(window.map.drawControl){for(const t in window.map.drawControl._toolbars.draw._modes)if(window.map.drawControl._toolbars.draw._modes[t].handler.enabled())window.map.drawControl._toolbars.draw._modes[t].handler.disable();for(const t in window.map.drawControl._toolbars.edit._modes)if(window.map.drawControl._toolbars.edit._modes[t].handler&&window.map.drawControl._toolbars.edit._modes[t].handler.enabled())window.map.drawControl._toolbars.edit._modes[t].handler.disable();window.map.removeControl(window.map.drawControl);}}
    window.clearAllDrawings=function(){drawnItems.clearLayers();}
    $('#compass').on('click',()=>{rotateMap(90);});let currentAngle=0;const directions=['N','E','S','W'];
    function rotateMap(a){currentAngle=(currentAngle+a)%360;$('#map').css('transform',`rotate(${currentAngle}deg)`);const ea=(360-currentAngle)%360;$('#currentDirection').text(directions[Math.round(ea/90)%4]);}
    $('#sidebar, #toggleMenu').on('click',function(e){if(e.target.id==='toggleMenu')$('#sidebar').toggleClass('hidden');if($(e.target).closest('#sidebar').length||e.target.id==='toggleMenu')adjustMapSize();}); // Simplified toggle
    function adjustMapSize(){setTimeout(()=>window.map.invalidateSize(),300);}
    window.openAbout=function(){$('#modal').addClass('visible');}
    window.closeAbout=function(){$('#modal').removeClass('visible');}
    window.openHelp=function(){$('#help-modal').addClass('visible');}
    window.closeHelp=function(){$('#help-modal').removeClass('visible');}
    
    initializeMapSelector().then(()=>{
        const sm=$('#sessionModal'),csb=$('#createSessionButton'),jsb=$('#joinSessionButton'),ski=$('#sessionKeyInput'),smsg=$('#sessionMessage'),sid=$('#sessionInfo'),ask=$('#activeSessionKey'),asmn=$('#activeSessionMapName'),sli=$('#shareableLink'),lsb=$('#leaveSessionButton'),ssm=$('#sessionMapSelector'),msm=$('#mapSelector'),mer=$('#map'),scs=$('#sessionCreateSection'),sjs=$('#sessionJoinSection'),smi=$('#sessionModalInstructions'),cslb=$('#copyShareLinkButton'),csms=$('#copyStatusMessage'),sDisc=$('#shareToDiscordButton'),sWA=$('#shareToWhatsAppButton');
        function uSUI(ia){if(ia){sm.removeClass('visible').addClass('hidden');mer.removeClass('blurred');sid.show();scs.hide();sjs.hide();smi.hide();ask.text(currentSessionKey);asmn.text(currentSessionMapName);const nu=`${location.protocol}//${location.host}${location.pathname}?session=${currentSessionKey}`;sli.val(nu);try{history.pushState({path:nu},'',nu);}catch(e){console.warn("URLupd fail:",e);}msm.prop('disabled',true);}else{sm.addClass('visible').removeClass('hidden');mer.addClass('blurred');sid.hide();scs.show();sjs.show();smi.show();try{history.pushState({path:location.pathname},'',location.pathname);}catch(e){console.warn("URLupd fail:",e);}msm.prop('disabled',false);}}
        uSUI(false);const up=new URLSearchParams(location.search);const skfu=up.get('session');if(skfu){ski.val(skfu);joinSession(skfu);}else{uSUI(false);}
        csb.on('click',async()=>{const so=ssm.find('option:selected');if(!so.length||!so.data('mapId')){smsg.text('Select map.');return;}const mId=parseInt(so.data('mapId')),mName=so.text();smsg.text('Creating...');try{const r=await fetch('create_session.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`map_id=${mId}`});const d=await r.json();if(d.success){currentSessionKey=d.session_key;currentSessionMapId=d.map_id;currentSessionMapName=mName;uSUI(true);startPolling();if(currentMapId!==currentSessionMapId)msm.val(msm.find(`option[data-map-id="${currentSessionMapId}"]`).val()).trigger('change');loadAllCollabItems();}else{smsg.text(`Error: ${d.message}`);uSUI(false);}}catch(err){console.error("Create err:",err);smsg.text('Network error.');uSUI(false);}});
        jsb.on('click',()=>{const k=ski.val().trim();if(k)joinSession(k);else smsg.text('Enter key.');});
        async function joinSession(k){smsg.text('Joining...');try{const r=await fetch(`join_session.php?session_key=${k}`);const d=await r.json();if(d.success){currentSessionKey=d.session_key;currentSessionMapId=d.map_id;let mnf=false;msm.find('option').each(function(){const $o=$(this);if(parseInt($o.data('mapId'))===currentSessionMapId){currentSessionMapName=$o.text();if(currentMapId!==currentSessionMapId)msm.val($o.val()).trigger('change');mnf=true;return false;}});if(!mnf)currentSessionMapName=`Map ID ${currentSessionMapId}`;uSUI(true);startPolling();loadAllCollabItems();}else{smsg.text(`Error: ${d.message}`);uSUI(false);}}catch(err){console.error("Join err:",err);smsg.text('Network error.');uSUI(false);}}
        lsb.on('click',()=>{currentSessionKey=null;currentSessionMapId=null;currentSessionMapName='';stopPolling();collabMarkersLayer.clearLayers();collabDrawingsLayer.clearLayers();renderedCollabItemDBIds.markers.clear();renderedCollabItemDBIds.drawings.clear();lastReceivedMarkerId=0;lastReceivedDrawingId=0;smsg.text('Left session.');uSUI(false);});
        if(cslb.length){cslb.on('click',()=>{sli[0].select();sli[0].setSelectionRange(0,99999);try{const sc=document.execCommand('copy');csms.text(sc?'Link copied!':'Copy failed.').show().delay(2000).fadeOut();}catch(err){csms.text('Copy failed.').show().delay(2000).fadeOut();console.error('Copy err:',err);}});}
        if(sDisc.length){sDisc.on('click',()=>{if(!currentSessionKey)return;const txt=`Join map session! Link: ${sli.val()}\nKey: ${currentSessionKey}`;alert("Copy link/key for Discord.\n\n"+txt);});}
        if(sWA.length){sWA.on('click',()=>{if(!currentSessionKey)return;const txt=`Join map session! Link: ${sli.val()}\nKey: ${currentSessionKey}`;const waUrl=`https://wa.me/?text=${encodeURIComponent(txt)}`;window.open(waUrl,'_blank');});}
    }); 
    function startPolling(){if(collabPollingInterval)clearInterval(collabPollingInterval);collabPollingInterval=setInterval(fetchCollabUpdates,POLLING_RATE);console.log("Polling started.");}
    function stopPolling(){if(collabPollingInterval)clearInterval(collabPollingInterval);collabPollingInterval=null;console.log("Polling stopped.");}
    async function fetchCollabUpdates(){if(!currentSessionKey)return;try{const r=await fetch(`get_collab_updates.php?session_key=${currentSessionKey}&client_id=${collabClientId}&last_marker_id=${lastReceivedMarkerId}&last_drawing_id=${lastReceivedDrawingId}`);const d=await r.json();if(d.success){if(d.map_id&&d.map_id!==currentSessionMapId){console.warn(`Sess map ${d.map_id} differs. Align...`);currentSessionMapId=d.map_id;let mf=false;const msm=$('#mapSelector');msm.find('option').each(function(){const $o=$(this);if(parseInt($o.data('mapId'))===currentSessionMapId){currentSessionMapName=$o.text();$('#activeSessionMapName').text(currentSessionMapName);if(currentMapId!==currentSessionMapId)msm.val($o.val()).trigger('change');mf=true;return false;}});if(!mf)console.error("Sess map ID from serv not found!");collabMarkersLayer.clearLayers();collabDrawingsLayer.clearLayers();renderedCollabItemDBIds.markers.clear();renderedCollabItemDBIds.drawings.clear();lastReceivedMarkerId=0;lastReceivedDrawingId=0;}d.markers.forEach(mD=>{renderCollabMarker(mD);if(mD.id>lastReceivedMarkerId)lastReceivedMarkerId=mD.id;});d.drawings.forEach(dD=>{renderCollabDrawing(dD);if(dD.id>lastReceivedDrawingId)lastReceivedDrawingId=dD.id;});}else{console.error("Update fetch error:",d.message);if(d.message==='Invalid session.'){alert("Session invalid.");$('#leaveSessionButton').trigger('click');}}}catch(err){console.error("Net err fetch updates:",err);}}
    async function loadAllCollabItems(){if(!currentSessionKey)return;console.log("Loading all for session:",currentSessionKey);lastReceivedMarkerId=0;lastReceivedDrawingId=0;collabMarkersLayer.clearLayers();collabDrawingsLayer.clearLayers();renderedCollabItemDBIds.markers.clear();renderedCollabItemDBIds.drawings.clear();await fetchCollabUpdates();}
    async function sendCollabData(iT,act,pay){if(!currentSessionKey)return;const fd=new FormData();fd.append('session_key',currentSessionKey);fd.append('client_id',collabClientId);fd.append('item_type',iT);fd.append('action',act);for(const k in pay)fd.append(k,pay[k]);try{const r=await fetch('manage_collab_item.php',{method:'POST',body:fd});const d=await r.json();if(d.success)console.log(`Collab ${iT} ${act} ok:`,d.message,d.item_id);else console.error(`Collab ${iT} ${act} fail:`,d.message);}catch(err){console.error(`Net err send ${iT}:`,err);}}
    function renderCollabMarker(mD){if(renderedCollabItemDBIds.markers.has(mD.id)||currentMapId!==currentSessionMapId)return;let iconToUse;if(mD.marker_type==='custom_collab'&&mD.fa_icon_class){iconToUse=L.divIcon({html:`<i class="${mD.fa_icon_class}" style="font-size:24px; color:${mD.marker_color||'#FFF'}; line-height:1;"></i>`,className:'fontawesome-map-marker',iconSize:[24,24],iconAnchor:[12,12],popupAnchor:[0,-12]});}else{iconToUse=customIcons[mD.marker_type]||customIcons.loot;}const m=L.marker([mD.latitude,mD.longitude],{icon:iconToUse}).addTo(collabMarkersLayer);m.db_id=mD.id;m.client_id_owner=mD.client_id;if(mD.marker_text){m.bindTooltip(mD.marker_text,{permanent:false,direction:'top',className:'custom-collab-marker-tooltip',offset:[0,-12]});}m.on('click',function(e){L.DomEvent.stopPropagation(e);if(confirm(`Del collab marker? (Owner: ${this.client_id_owner===collabClientId?"You":"Other"})`)){sendCollabData('marker','delete',{db_item_id:this.db_id});collabMarkersLayer.removeLayer(this);renderedCollabItemDBIds.markers.delete(this.db_id);}});renderedCollabItemDBIds.markers.add(mD.id);}
    function renderCollabDrawing(dD){if(renderedCollabItemDBIds.drawings.has(dD.id)||currentMapId!==currentSessionMapId)return;try{const gJF=JSON.parse(dD.geojson_data);const l=L.geoJSON(gJF).getLayers()[0];if(l){l.db_id=dD.id;l.client_id_owner=dD.client_id;l.client_layer_id=dD.client_layer_id;collabDrawingsLayer.addLayer(l);renderedCollabItemDBIds.drawings.add(dD.id);l.on('click',function(e){L.DomEvent.stopPropagation(e);if(window.map.drawControl&&window.map.drawControl._toolbars.edit&&window.map.drawControl._toolbars.edit._activeMode&&window.map.drawControl._toolbars.edit._featureGroup.hasLayer(this))return;if(confirm(`Del collab drawing? (Owner: ${this.client_id_owner===collabClientId?"You":"Other"})`)){sendCollabData('drawing','delete',{db_item_id:this.db_id});collabDrawingsLayer.removeLayer(this);renderedCollabItemDBIds.drawings.delete(this.db_id);}}); }else console.error("Could not create layer from GeoJSON:",dD);}catch(e){console.error("Err parsing GeoJSON for collab drawing:",e,dD.geojson_data);}}
});