const express = require('express');
const http = require('http');
const { Server } = require('socket.io');

const app = express();
const server = http.createServer(app);
const io = new Server(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    }
});

const groups = {};

io.on('connection', (socket) => {
    const groupId = socket.handshake.query.group;
    console.log('User connected:', socket.id, 'Group:', groupId);
    socket.join(groupId); // Unir al cliente a la sala (room) del grupo

    // Enviar datos iniciales al conectarse al grupo
    if (groups[groupId]) {
      socket.emit('initialData', groups[groupId]);
    } else {
      groups[groupId] = { markers: [], drawings: [] }; // Inicializa el grupo si es nuevo
    }

    socket.on('mapAction', (data) => {
        // console.log('Received map action:', data);
        groups[groupId] = updateGroupData(groups[groupId], data); // Actualizar los datos del grupo
         io.to(groupId).emit('mapAction', data); // Enviar solo a la sala del grupo
    });
    socket.on('disconnect', () => {
        console.log('User disconnected:', socket.id, 'Group:', groupId);
        socket.leave(groupId);
    });
});
 function updateGroupData(groupData, action) {
   if(action.type === "marker"){
    groupData.markers.push({ markerType: action.markerType, coords: action.coords });
   }
   if(action.type === "draw"){
    groupData.drawings.push({ layer: action.layer });
   }
    if(action.type === "removeMarker"){
      groupData.markers = groupData.markers.filter( marker=> (marker.coords.lat !== action.coords.lat) || (marker.coords.lng !== action.coords.lng) )
     }
    if(action.type === "removeDrawing"){
      groupData.drawings = groupData.drawings.filter(drawing=> drawing.layer !== action.layer)
     }
  return groupData
 }

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
    console.log(`Server listening on port ${PORT}`);
});