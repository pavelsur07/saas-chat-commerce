// assets/chat-center/hooks/useSocket.ts_
import { useEffect, useRef } from 'react';
import { io, Socket } from 'socket.io-client';

type MessagePayload = {
    clientId: string;
    text: string;
    direction: 'in' | 'out';
    id?: string;
    createdAt?: string;
    timestamp?: string;
};

const DEV_URL  = 'http://localhost:3001';
const PROD_URL = 'https://chat.2bstock.ru';
const PATH = '/socket.io';

function getSocketUrl() {
    if (typeof window === 'undefined') return PROD_URL;
    const h = window.location.hostname;
    return h === 'localhost' || h === '127.0.0.1' ? DEV_URL : PROD_URL;
}

export function useSocket(
    clientId: string | null,
    onMessage: (data: MessagePayload) => void
) {
    const socketRef = useRef<Socket | null>(null);

    useEffect(() => {
        if (!clientId) return;

        const socket = io(getSocketUrl(), {
            path: PATH,
            transports: ['websocket'], // ← временно оставляем два
            withCredentials: true,
            reconnection: true,
            reconnectionAttempts: Infinity,
            reconnectionDelay: 500,
            reconnectionDelayMax: 5000,
        });

        socketRef.current = socket;

        socket.on('connect', () => console.log('[socket] connected', socket.id));
        socket.on('connect_error', (err) => console.error('[socket] connect_error', err.message));
        socket.on('error', (err) => console.error('[socket] error', err));
        socket.on('disconnect', (reason) => console.warn('[socket] disconnect', reason));

        const room = `client-${clientId}`;
        socket.emit('join', { room });

        const handler = (data: MessagePayload) => {
            if (String(data.clientId) === String(clientId)) onMessage(data);
        };
        socket.on('new_message', handler);

        return () => {
            try { socket.emit('leave', { room }); } finally {
                socket.off('new_message', handler);
                socket.disconnect();
            }
        };
    }, [clientId, onMessage]);
}
