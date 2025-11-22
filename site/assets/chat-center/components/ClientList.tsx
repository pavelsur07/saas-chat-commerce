// assets/chat-center/components/ClientList.tsx

import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { io, Socket } from 'socket.io-client';
import { PATH, getSocketUrl, MessagePayload } from '../hooks/useSocket';

type Client = {
    id: string;
    name: string;
    source: string;
    last_message?: string;
    last_message_at?: string;
    unread_count?: number;
    awaiting?: boolean;
};

type Props = {
    onSelect: (client: Client) => void;
};

const ClientList: React.FC<Props> = ({ onSelect }) => {
    const [clients, setClients] = useState<Client[]>([]);
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const socketRef = useRef<Socket | null>(null);
    const roomsRef = useRef<Set<string>>(new Set());
    const selectedRef = useRef<string | null>(null);
    const handlerRef = useRef<((data: MessagePayload) => void) | null>(null);

    useEffect(() => {
        axios.get('/api/clients').then((res) => setClients(res.data));
    }, []);

    useEffect(() => {
        selectedRef.current = selectedId;
    }, [selectedId]);

    useEffect(() => {
        if (!clients.length) {
            return;
        }

        if (!socketRef.current) {
            const socket = io(getSocketUrl(), {
                path: PATH,
                transports: ['websocket'],
                withCredentials: true,
                reconnection: true,
                reconnectionAttempts: Infinity,
                reconnectionDelay: 500,
                reconnectionDelayMax: 5000,
            });

            handlerRef.current = (payload: MessagePayload) => {
                setClients((prev) => {
                    const currentSelected = selectedRef.current;

                    return prev.map((client) => {
                        if (String(client.id) !== String(payload.clientId)) {
                            return client;
                        }

                        const isIncoming = payload.direction === 'in';
                        const isSelected = currentSelected === client.id;
                        const nextUnread = isIncoming && !isSelected
                            ? (client.unread_count || 0) + 1
                            : client.unread_count || 0;

                        return {
                            ...client,
                            last_message: payload.text ?? client.last_message,
                            last_message_at:
                                payload.createdAt || payload.timestamp || client.last_message_at,
                            unread_count: nextUnread,
                            awaiting: isIncoming && !isSelected ? true : client.awaiting,
                        };
                    });
                });
            };

            socket.on('new_message', handlerRef.current);
            socketRef.current = socket;
        }

        const socket = socketRef.current;
        if (!socket) {
            return;
        }

        clients.forEach((client) => {
            const room = `client-${client.id}`;
            if (!roomsRef.current.has(room)) {
                socket.emit('join', { room });
                roomsRef.current.add(room);
            }
        });

    }, [clients]);

    useEffect(() => {
        return () => {
            const socket = socketRef.current;
            if (!socket) {
                return;
            }

            roomsRef.current.forEach((room) => socket.emit('leave', { room }));
            if (handlerRef.current) {
                socket.off('new_message', handlerRef.current);
            }
            socket.disconnect();
            roomsRef.current.clear();
            socketRef.current = null;
        };
    }, []);

    const formatTime = (iso?: string) => {
        if (!iso) return '';
        const date = new Date(iso);
        if (Number.isNaN(date.getTime())) return '';
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    return (
        <div className="divide-y">
            {clients.map((client) => {
                const unread = client.unread_count ?? 0;
                const awaiting = Boolean(client.awaiting);

                return (
                    <button
                        key={client.id}
                        onClick={() => {
                            const updatedClient = { ...client, unread_count: 0, awaiting: false };
                            setSelectedId(client.id);
                            setClients((prev) =>
                                prev.map((item) =>
                                    item.id === client.id ? updatedClient : item
                                )
                            );
                            onSelect(updatedClient);
                        }}
                        className={`w-full text-left p-3 hover:bg-gray-100 ${
                            selectedId === client.id ? 'bg-gray-100 font-semibold' : ''
                        }`}
                    >
                        <div className="flex items-start justify-between gap-2">
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2">
                                    <span className="truncate">{client.name || 'Без имени'}</span>
                                    <span className="text-xs text-gray-500">{client.source}</span>
                                </div>
                                <div className="flex items-center justify-between gap-2 mt-1 text-xs text-gray-600">
                                    <span className="truncate">{client.last_message}</span>
                                    <span className="shrink-0 text-gray-400">{formatTime(client.last_message_at)}</span>
                                </div>
                            </div>
                            <div className="flex flex-col items-end gap-1">
                                {awaiting && (
                                    <span className="inline-block text-xs text-amber-600 bg-amber-100 px-2 py-0.5 rounded-full">
                                        Новые
                                    </span>
                                )}
                                {unread > 0 && (
                                    <span className="inline-flex items-center justify-center min-w-[1.5rem] px-2 py-0.5 text-xs font-semibold text-white bg-blue-600 rounded-full">
                                        {unread}
                                    </span>
                                )}
                            </div>
                        </div>
                    </button>
                );
            })}
        </div>
    );
};

export default ClientList;
