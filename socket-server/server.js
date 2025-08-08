import 'dotenv/config';
import express from 'express';
import http from 'http';
import { Server } from 'socket.io';
import { createClient } from 'redis';
import { createAdapter } from '@socket.io/redis-adapter';

const app = express();
const server = http.createServer(app);
const io = new Server(server, {
    cors: {
        origin: '*',
        methods: ['GET', 'POST']
    }
});

const pubClient = createClient({ url: process.env.REDIS_URL });
const subClient = pubClient.duplicate();
await pubClient.connect();
await subClient.connect();

io.adapter(createAdapter(pubClient, subClient));

io.on('connection', (socket) => {
    console.log('✅ Socket connected:', socket.id);

    socket.on('join', (room) => {
        socket.join(room);
        console.log(`🔗 joined room ${room}`);
    });

    socket.on('send_message', ({ room, message }) => {
        io.to(room).emit('new_message', message);
    });

    socket.on('disconnect', () => {
        console.log('❌ Socket disconnected:', socket.id);
    });
});

const PORT = process.env.PORT || 3001;
server.listen(PORT, () => {
    console.log(`🚀 Socket.IO server running on port ${PORT}`);
});