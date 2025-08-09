import 'dotenv/config';
import express from 'express';
import http from 'http';
import { Server } from 'socket.io';
import { createClient } from 'redis';
import { createAdapter } from '@socket.io/redis-adapter';

const app = express();
const server = http.createServer(app);
const io = new Server(server, {
    cors: { origin: '*', methods: ['GET', 'POST'] },
});

const pub = createClient({ url: process.env.REDIS_URL });      // redis://redis-realtime:6379
const sub = pub.duplicate();
await pub.connect();
await sub.connect();

io.adapter(createAdapter(pub, sub));

io.on('connection', (socket) => {
    socket.on('join', ({ room }) => socket.join(room));
    socket.on('leave', ({ room }) => socket.leave(room));
});

// ВАЖНО: подписываемся по паттерну на все каналы клиентов
await sub.pSubscribe('chat.client.*', (message, channel) => {
    try {
        const payload = JSON.parse(message);
        const clientId = (payload.clientId || '').toString();
        if (!clientId) return;

        // Отправляем в комнату конкретного клиента
        io.to(`client-${clientId}`).emit('new_message', payload);
    } catch (_) {}
});

const PORT = process.env.PORT || 3001;
server.listen(PORT, () => console.log(`Socket.IO on :${PORT}`));