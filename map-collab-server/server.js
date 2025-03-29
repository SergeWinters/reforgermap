// server.js

const http = require('http');
const { Server } = require("socket.io");

// 1. Creamos un servidor HTTP básico
const httpServer = http.createServer((req, res) => {
  res.writeHead(200, { 'Content-Type': 'text/plain' });
  res.end('Map Collaboration Server OK\n');
});

// 2. Inicializamos Socket.IO adjuntándolo al servidor HTTP
// ¡IMPORTANTE!: Cambia 'http://tu-dominio-o-ip.com' al origen real de tu index.php
// Si pruebas localmente, puedes usar 'http://localhost' o 'http://127.0.0.1' con el puerto si es necesario.
// Usar '*' es inseguro para producción.
const io = new Server(httpServer, {
  cors: {
    origin: "http://localhost", // <-- CAMBIA ESTO para producción (ej: 'https://www.reforgemap.com')
    methods: ["GET", "POST"]
  }
});

// 3. Almacenamiento en memoria para el estado de cada sala
// Estructura: { roomId: { markers: { markerId: markerData, ... }, drawings: { drawingId: drawingData, ... } } }
const roomStates = {};

// 4. Evento principal: conexión de un nuevo cliente
io.on('connection', (socket) => {
  console.log(`Client connected: ${socket.id}`);

  // 5. Evento para unirse a una sala
  socket.on('join_room', (roomId) => {
    if (!roomId) {
      console.log(`Client ${socket.id} tried to join an invalid room.`);
      return; // Ignorar si no hay roomId
    }

    console.log(`Client ${socket.id} joining room: ${roomId}`);
    socket.join(roomId); // Unir el socket a la sala de Socket.IO

    // Inicializar el estado de la sala si es nueva
    if (!roomStates[roomId]) {
      roomStates[roomId] = {
        markers: {},
        drawings: {}
      };
      console.log(`Initialized state for new room: ${roomId}`);
    }

    // Enviar el estado actual de la sala SOLAMENTE al cliente que acaba de unirse
    socket.emit('initial_state', roomStates[roomId]);
    console.log(`Sent initial state of room ${roomId} to ${socket.id}`);

    // Guardar la roomId en el socket para referencia futura (opcional pero útil)
    socket.roomId = roomId;
  });

  // --- Eventos de Marcadores ---

  socket.on('add_marker', (markerData) => {
    const roomId = socket.roomId; // Obtener la sala desde la info del socket
    if (!roomId || !markerData || !markerData.id || !roomStates[roomId]) {
        console.error("Invalid add_marker data or client not in a room", { roomId, markerData, socketId: socket.id });
        return;
    }
    console.log(`[Room: ${roomId}] Received add_marker from ${socket.id}:`, markerData.id);
    // Almacena el marcador en el estado
    roomStates[roomId].markers[markerData.id] = markerData;
    // Retransmite a TODOS los demás en la sala
    socket.to(roomId).emit('marker_added', markerData);
  });

  socket.on('remove_marker', (markerId) => {
    const roomId = socket.roomId;
    if (!roomId || !markerId || !roomStates[roomId] || !roomStates[roomId].markers[markerId]) {
        console.error("Invalid remove_marker data or marker/room not found", { roomId, markerId, socketId: socket.id });
        return;
    }
    console.log(`[Room: ${roomId}] Received remove_marker for ${markerId} from ${socket.id}`);
    // Elimina el marcador del estado
    delete roomStates[roomId].markers[markerId];
    // Retransmite a TODOS los demás en la sala
    socket.to(roomId).emit('marker_removed', markerId);
  });

  // --- Eventos de Dibujos ---

  socket.on('add_drawing', (drawingData) => {
    const roomId = socket.roomId;
    if (!roomId || !drawingData || !drawingData.id || !roomStates[roomId]) {
      console.error("Invalid add_drawing data or client not in a room", { roomId, drawingData, socketId: socket.id });
      return;
    }
    console.log(`[Room: ${roomId}] Received add_drawing from ${socket.id}:`, drawingData.id);
    // Almacena el dibujo
    roomStates[roomId].drawings[drawingData.id] = drawingData;
    // Retransmite a TODOS los demás en la sala
    socket.to(roomId).emit('drawing_added', drawingData);
  });

  // Necesitamos un evento para eliminar un dibujo específico
  socket.on('remove_drawing', (drawingId) => {
    const roomId = socket.roomId;
     if (!roomId || !drawingId || !roomStates[roomId] || !roomStates[roomId].drawings[drawingId]) {
        console.error("Invalid remove_drawing data or drawing/room not found", { roomId, drawingId, socketId: socket.id });
        return;
    }
    console.log(`[Room: ${roomId}] Received remove_drawing for ${drawingId} from ${socket.id}`);
    // Elimina el dibujo del estado
    delete roomStates[roomId].drawings[drawingId];
    // Retransmite a TODOS los demás en la sala
    socket.to(roomId).emit('drawing_removed', drawingId);
  });


  socket.on('clear_drawings', () => {
    const roomId = socket.roomId;
    if (!roomId || !roomStates[roomId]) {
        console.error("Invalid clear_drawings data or client not in a room", { roomId, socketId: socket.id });
        return;
    }
    console.log(`[Room: ${roomId}] Received clear_drawings from ${socket.id}`);
    // Limpia los dibujos para esta sala
    roomStates[roomId].drawings = {};
    // Retransmite a TODOS los demás en la sala
    socket.to(roomId).emit('drawings_cleared');
  });

  // --- Desconexión ---
  socket.on('disconnect', () => {
    console.log(`Client disconnected: ${socket.id}`);
    // Socket.IO lo saca automáticamente de las salas.
    // Podríamos limpiar salas vacías aquí si quisiéramos, pero no es estrictamente necesario ahora.
    // const roomId = socket.roomId;
    // if (roomId && io.sockets.adapter.rooms.get(roomId)?.size === 0) {
    //    console.log(`Room ${roomId} is now empty. Removing state.`);
    //    delete roomStates[roomId];
    // }
  });

  // --- Manejo de errores ---
  socket.on('error', (err) => {
    console.error(`Socket Error on ${socket.id}:`, err);
  });
});

// 6. Puerto en el que escuchará el servidor Node.js
const PORT = process.env.PORT || 3000;
httpServer.listen(PORT, () => {
  console.log(`Server listening on port ${PORT}`);
  console.log("Asegúrate de que la configuración CORS 'origin' es correcta para tu frontend.");
});