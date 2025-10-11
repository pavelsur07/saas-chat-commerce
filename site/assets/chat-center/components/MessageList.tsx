// assets/chat-center/components/MessageList.tsx__

import React, { useEffect, useRef, useState } from 'react';
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

type MessagesResponse = {
    messages: ApiMessage[];
    last_read_message_id?: string | null;
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
    const [lastReadMessageId, setLastReadMessageId] = useState<string | null>(null);
    const [initialScrollDone, setInitialScrollDone] = useState(false);
    const messageRefs = useRef<Map<string, HTMLDivElement>>(new Map());

    useEffect(() => {
        if (!clientId) return;
        setMessages([]);
        setLastReadMessageId(null);
        setInitialScrollDone(false);
        messageRefs.current = new Map<string, HTMLDivElement>();

        axios.get(`/api/messages/${clientId}`).then((res) => {
            const data: MessagesResponse = res.data;
            const loadedMessages: Message[] = (data.messages || []).map((msg: ApiMessage) => ({
                id: msg.id,
                text: msg.text,
                direction: msg.direction,
                createdAt: msg.createdAt || msg.timestamp || new Date().toISOString(),
            }));
            setMessages(loadedMessages.slice(-30));
            setLastReadMessageId(data.last_read_message_id ?? null);
        });
    }, [clientId]);

    useSocket(clientId, (payload) => {
        setMessages((prev) => {
            const next = [
                ...prev,
                {
                    id: payload.id || `${Date.now()}`,
                    text: payload.text,
                    direction: payload.direction,
                    createdAt: payload.createdAt || payload.timestamp || new Date().toISOString(),
                },
            ];

            return next.slice(-30);
        });
        onNewMessage && onNewMessage();
    });

    useEffect(() => {
        if (initialScrollDone) {
            return;
        }

        if (!messages.length) {
            return;
        }

        const scrollToMessage = (messageId: string | null): boolean => {
            if (!messageId) {
                return false;
            }

            const target = messageRefs.current.get(messageId);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setInitialScrollDone(true);
                return true;
            }

            return false;
        };

        if (scrollToMessage(lastReadMessageId)) {
            return;
        }

        const lastMessage = messages[messages.length - 1];
        if (lastMessage) {
            scrollToMessage(lastMessage.id);
        }
    }, [messages, lastReadMessageId, initialScrollDone]);

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
                    ref={(el) => {
                        if (el) {
                            messageRefs.current.set(msg.id, el);
                        } else {
                            messageRefs.current.delete(msg.id);
                        }
                    }}
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
