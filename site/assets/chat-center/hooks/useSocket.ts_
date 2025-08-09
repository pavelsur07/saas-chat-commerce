// assets/chat-center/hooks/useSocket.ts__
import { useEffect, useRef } from 'react';
import { io, Socket } from 'socket.io-client';

type MessagePayload = {
    id?: string;
    clientId: string;
    text: string;
    direction: 'in' | 'out';
    createdAt?: string;
    timestamp?: string;
};

// allow using an env variable injected at build time
declare const process: {
    env: {
        SOCKET_URL?: string;
    };
};

function getSocketUrl(): string {
    if (typeof process !== 'undefined' && process.env.SOCKET_URL) {
        return process.env.SOCKET_URL;
    }
    if (typeof window !== 'undefined' && (window as any).__SOCKET_URL__) {
        return (window as any).__SOCKET_URL__;
    }
    return 'http://localhost:3001';
}

export function useSocket(
    clientId: string | null,
    onMessage: (data: MessagePayload) => void
) {
    const socketRef = useRef<Socket | null>(null);

    useEffect(() => {
        if (!clientId) return;

        const socket = io(getSocketUrl(), {
            transports: ['websocket'],
        });
        socketRef.current = socket;

        const room = `client-${clientId}`;
        socket.emit('join', { room });

        const handler = (data: MessagePayload) => {
            // подстраховка: принимаем только сообщения для активного клиента
            if (String(data.clientId) === String(clientId)) {
                onMessage(data);
            }
        };
        socket.on('new_message', handler);

        return () => {
            try {
                socket.emit('leave', { room });
            } finally {
                socket.off('new_message', handler);
                socket.disconnect();
            }
        };
    }, [clientId, onMessage]);
}
