// socket-server/server.js
import 'dotenv/config';
import express from 'express';
import http from 'http';
import { Server } from 'socket.io';
import { createClient } from 'redis';
import { createAdapter } from '@socket.io/redis-adapter';
import crypto from 'crypto';

const PORT = process.env.PORT || 3001;
const REDIS_URL = process.env.REDIS_URL || 'redis://redis-realtime:6379';
const SOCKET_PATH = process.env.SOCKET_PATH || '/socket.io';
const isProd = process.env.NODE_ENV === 'prod';
//const ORIGIN = process.env.SOCKET_ORIGIN || 'https://chat.2bstock.ru';
const ORIGIN = process.env.SOCKET_ORIGIN || (isProd ? 'https://chat.2bstock.ru' : 'http://localhost:3001');
const WEBCHAT_JWT_SECRET = process.env.WEBCHAT_JWT_SECRET || process.env.APP_SECRET || 'insecure-webchat-secret';

const app = express();
app.get('/health', (_req, res) => res.json({ ok: true }));

const server = http.createServer(app);
const io = new Server(server, {
    path: SOCKET_PATH,
    transports: ['websocket'], // polling оставляем на время проверки
    //cors: { origin: ORIGIN, credentials: true },
    cors: { origin: ORIGIN === '*' ? true : ORIGIN, credentials: true },
});

const pub = createClient({ url: REDIS_URL });
const sub = pub.duplicate();
await pub.connect();
await sub.connect();
io.adapter(createAdapter(pub, sub));

const verifyToken = (token) => {
    if (typeof token !== 'string' || token.split('.').length !== 3) {
        throw new Error('Malformed token');
    }

    const [headerPart, payloadPart, signature] = token.split('.');
    const expectedSignature = crypto
        .createHmac('sha256', WEBCHAT_JWT_SECRET)
        .update(`${headerPart}.${payloadPart}`)
        .digest('base64url');

    if (signature.length !== expectedSignature.length) {
        throw new Error('Invalid signature');
    }

    if (!crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expectedSignature))) {
        throw new Error('Invalid signature');
    }

    const payload = JSON.parse(Buffer.from(payloadPart, 'base64url').toString('utf8'));
    if (typeof payload !== 'object' || payload === null) {
        throw new Error('Invalid payload');
    }

    if (typeof payload.exp !== 'number' || payload.exp * 1000 < Date.now() - 60000) {
        throw new Error('Token expired');
    }

    if (typeof payload.thread !== 'string') {
        throw new Error('Thread missing');
    }

    return {
        threadId: payload.thread,
        siteKey: typeof payload.aud === 'string' ? payload.aud : null,
        visitorId: typeof payload.sub === 'string' ? payload.sub : null,
    };
};

io.on('connection', (socket) => {
    const rawToken = socket.handshake?.auth?.token;
    if (rawToken) {
        try {
            socket.data.webchat = verifyToken(rawToken);
        } catch (err) {
            console.warn('[socket] invalid token', err.message);
            socket.disconnect(true);
            return;
        }
    }

    socket.on('join', ({ room }) => {
        if (typeof room !== 'string' || room === '') {
            return;
        }

        if (room.startsWith('thread-')) {
            const threadId = room.slice('thread-'.length);
            if (!socket.data?.webchat || socket.data.webchat.threadId !== threadId) {
                return;
            }
        }

        socket.join(room);
    });

    socket.on('leave', ({ room }) => {
        if (typeof room !== 'string' || room === '') {
            return;
        }

        if (room.startsWith('thread-')) {
            const threadId = room.slice('thread-'.length);
            if (!socket.data?.webchat || socket.data.webchat.threadId !== threadId) {
                return;
            }
        }

        socket.leave(room);
    });
});

await sub.pSubscribe('chat.client.*', (message) => {
    try {
        const payload = JSON.parse(message);
        const clientId = String(payload?.clientId || '');
        if (!clientId) return;
        io.to(`client-${clientId}`).emit('new_message', payload);
    } catch (err) {
        console.warn('[socket] failed to broadcast client payload', err.message);
    }
});

await sub.pSubscribe('chat.thread.*', (message, channel) => {
    try {
        const payload = JSON.parse(message);
        const parts = channel.split('.');
        const threadId = String(payload?.threadId || parts[2] || '');
        if (!threadId) return;

        const eventName = typeof payload.event === 'string'
            ? payload.event.replace('.', ':')
            : 'message:new';

        io.to(`thread-${threadId}`).emit(eventName, {
            ...payload,
            threadId,
        });
    } catch (err) {
        console.warn('[socket] failed to broadcast thread payload', err.message);
    }
});

server.listen(PORT, () => {
    console.log(`[socket-server] :${PORT} path=${SOCKET_PATH} origin=${ORIGIN}`);
});
