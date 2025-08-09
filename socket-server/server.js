// socket-server/server.js
import 'dotenv/config';
import express from 'express';
import http from 'http';
import { Server } from 'socket.io';
import { createClient } from 'redis';
import { createAdapter } from '@socket.io/redis-adapter';

const PORT = process.env.PORT || 3001;
const REDIS_URL = process.env.REDIS_URL || 'redis://redis-realtime:6379';
const SOCKET_PATH = process.env.SOCKET_PATH || '/socket.io';
const ORIGIN = process.env.SOCKET_ORIGIN || 'https://chat.2bstock.ru';

const app = express();
app.get('/health', (_req, res) => res.json({ ok: true }));

const server = http.createServer(app);
const io = new Server(server, {
    path: SOCKET_PATH,
    transports: ['websocket'], // polling оставляем на время проверки
    cors: { origin: ORIGIN, credentials: true },
});

const pub = createClient({ url: REDIS_URL });
const sub = pub.duplicate();
await pub.connect();
await sub.connect();
io.adapter(createAdapter(pub, sub));

io.on('connection', (socket) => {
    socket.on('join', ({ room }) => room && socket.join(room));
    socket.on('leave', ({ room }) => room && socket.leave(room));
});

await sub.pSubscribe('chat.client.*', (message) => {
    try {
        const payload = JSON.parse(message);
        const clientId = String(payload?.clientId || '');
        if (!clientId) return;
        io.to(`client-${clientId}`).emit('new_message', payload);
    } catch {}
});

server.listen(PORT, () => {
    console.log(`[socket-server] :${PORT} path=${SOCKET_PATH} origin=${ORIGIN}`);
});