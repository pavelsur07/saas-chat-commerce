// assets/chat-center/hooks/useSocket.ts
import { useEffect, useRef } from 'react';
import { io, Socket } from 'socket.io-client';

type MessagePayload = {
    clientId: string;
    text: string;
    direction: 'in' | 'out';
    timestamp?: string;
    createdAt?: string;
    id?: string;
};

export function useSocket(
    clientId: string | null,
    onMessage: (data: MessagePayload) => void
) {
    const socketRef = useRef<Socket | null>(null);

    useEffect(() => {
        if (!clientId) return;

        const socket = io('http://localhost:3001', {
            transports: ['websocket'], // прод: поставь свой публичный адрес/прокси
        });
        socketRef.current = socket;

        // вступаем в комнату клиента
        socket.emit('join', { room: `client-${clientId}` });

        // новые сообщения
        const handler = (data: MessagePayload) => {
            if (data.clientId === clientId) onMessage(data);
        };
        socket.on('new_message', handler);

        // отписка при размонтировании/смене клиента
        return () => {
            socket.emit('leave', { room: `client-${clientId}` });
            socket.off('new_message', handler);
            socket.disconnect();
        };
    }, [clientId, onMessage]);
}
