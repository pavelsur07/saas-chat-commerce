// assets/chat-center/components/MessageList.tsx__

import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { useSocket } from '../hooks/useSocket';

type Message = {
    id: string;
    text: string;
    direction: 'in' | 'out';
    createdAt: string;
};

type ApiMessage = {
    id: string;
    text: string;
    direction: 'in' | 'out';
    timestamp?: string;
    createdAt?: string;
};

type Props = {
    clientId: string;
    onNewMessage?: () => void;
};

const markRead = async (clientId: string) =>
    axios.post(`/api/messages/${clientId}/read`).catch((error) => {
        console.warn('Failed to mark messages as read', error);
    });

const MessageList: React.FC<Props> = ({ clientId, onNewMessage }) => {
    const [messages, setMessages] = useState<Message[]>([]);

    useEffect(() => {
        if (!clientId) return;
        axios.get(`/api/messages/${clientId}`).then((res) => {
            const loadedMessages: Message[] = res.data.messages.map((msg: ApiMessage) => ({
                id: msg.id,
                text: msg.text,
                direction: msg.direction,
                createdAt: msg.createdAt || msg.timestamp || new Date().toISOString(),
            }));
            setMessages(loadedMessages);
            // После загрузки сообщений уведомляем родителя, чтобы прокрутить чат вниз
            onNewMessage && onNewMessage();
        });
    }, [clientId]);

    useSocket(clientId, (payload) => {
        setMessages((prev) => [
            ...prev,
            {
                id: payload.id || `${Date.now()}`,
                text: payload.text,
                direction: payload.direction,
                createdAt: payload.createdAt || payload.timestamp || new Date().toISOString(),
            },
        ]);
        onNewMessage && onNewMessage();
    });

    useEffect(() => {
        if (!clientId) {
            return;
        }

        const timeout = setTimeout(() => {
            markRead(clientId);
        }, 500);

        return () => clearTimeout(timeout);
    }, [clientId, messages]);

    return (
        <div className="space-y-2">
            {messages.map((msg) => (
                <div
                    key={msg.id}
                    className={`max-w-xs px-4 py-2 rounded-lg ${
                        msg.direction === 'in' ? 'bg-gray-200 self-start' : 'bg-blue-500 text-white self-end ml-8'
                    }`}
                >
                    <div>{msg.text}</div>
                    <div className="text-xs text-right mt-1 opacity-70">
                        {new Date(msg.createdAt).toLocaleString()}
                    </div>
                </div>
            ))}
        </div>
    );
};

export default MessageList;
