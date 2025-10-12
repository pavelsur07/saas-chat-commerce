// assets/chat-center/components/MessageList.tsx__

import React, { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
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
    messages?: ApiMessage[];
    last_read_message_id?: string | null;
};

type Props = {
    clientId: string;
    onNewMessage?: () => void;
};

const PAGE_SIZE = 30;

const markRead = async (clientId: string) =>
    axios.post(`/api/messages/${clientId}/read`).catch((error) => {
        console.warn('Failed to mark messages as read', error);
    });

const MessageList: React.FC<Props> = ({ clientId, onNewMessage }) => {
    const [messages, setMessages] = useState<Message[]>([]);
    const [lastReadMessageId, setLastReadMessageId] = useState<string | null>(null);
    const [lastReadLoaded, setLastReadLoaded] = useState(false);
    const [initialScrollDone, setInitialScrollDone] = useState(false);
    const [hasMore, setHasMore] = useState(true);
    const [isLoadingMore, setIsLoadingMore] = useState(false);
    const messageRefs = useRef<Map<string, HTMLDivElement>>(new Map());
    const messagesRef = useRef<Message[]>([]);
    const clientIdRef = useRef(clientId);
    const rootRef = useRef<HTMLDivElement | null>(null);
    const scrollContainerRef = useRef<HTMLDivElement | null>(null);
    const topSentinelRef = useRef<HTMLDivElement | null>(null);
    const pendingScrollAdjustment = useRef<{ height: number; top: number } | null>(null);

    useEffect(() => {
        clientIdRef.current = clientId;
    }, [clientId]);

    useEffect(() => {
        messagesRef.current = messages;
    }, [messages]);

    useEffect(() => {
        if (!clientId) return;
        setMessages([]);
        setLastReadMessageId(null);
        setInitialScrollDone(false);
        setLastReadLoaded(false);
        setHasMore(true);
        setIsLoadingMore(false);
        messageRefs.current = new Map<string, HTMLDivElement>();
        messagesRef.current = [];
        pendingScrollAdjustment.current = null;

        let cancelled = false;

        axios
            .get<MessagesResponse>(`/api/messages/${clientId}`, { params: { limit: PAGE_SIZE } })
            .then((res) => {
                if (cancelled || clientIdRef.current !== clientId) {
                    return;
                }

                const data = res.data;
                const loadedMessages: Message[] = (data.messages || []).map((msg: ApiMessage) => ({
                    id: msg.id,
                    text: msg.text,
                    direction: msg.direction,
                    createdAt: msg.createdAt || msg.timestamp || new Date().toISOString(),
                }));

                setMessages(loadedMessages);
                setLastReadMessageId(data.last_read_message_id ?? null);
                setLastReadLoaded(true);
                setHasMore(loadedMessages.length >= PAGE_SIZE);
            })
            .catch((error) => {
                console.warn('Failed to load messages', error);
                setLastReadLoaded(true);
            });

        return () => {
            cancelled = true;
        };
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

            return next;
        });
        onNewMessage && onNewMessage();
    });

    useEffect(() => {
        const parent = rootRef.current?.parentElement;
        if (!parent) {
            scrollContainerRef.current = null;
            return;
        }

        const container = parent as HTMLDivElement;
        scrollContainerRef.current = container;

        return () => {
            if (scrollContainerRef.current === container) {
                scrollContainerRef.current = null;
            }
        };
    }, [clientId]);

    useEffect(() => {
        if (initialScrollDone || !lastReadLoaded) {
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
    }, [messages, lastReadMessageId, initialScrollDone, lastReadLoaded]);

    const loadOlderMessages = useCallback(async () => {
        if (isLoadingMore || !hasMore) {
            return;
        }

        const currentMessages = messagesRef.current;
        const firstMessage = currentMessages[0];
        if (!firstMessage) {
            setHasMore(false);
            return;
        }

        const container = scrollContainerRef.current;
        if (container) {
            pendingScrollAdjustment.current = {
                height: container.scrollHeight,
                top: container.scrollTop,
            };
        } else {
            pendingScrollAdjustment.current = null;
        }

        setIsLoadingMore(true);
        const currentClientId = clientIdRef.current;

        try {
            const res = await axios.get<MessagesResponse>(`/api/messages/${clientId}`, {
                params: { before_id: firstMessage.id, limit: PAGE_SIZE },
            });

            if (clientIdRef.current !== currentClientId) {
                return;
            }

            const older: Message[] = (res.data.messages || []).map((msg: ApiMessage) => ({
                id: msg.id,
                text: msg.text,
                direction: msg.direction,
                createdAt: msg.createdAt || msg.timestamp || new Date().toISOString(),
            }));

            if (!older.length) {
                setHasMore(false);
                pendingScrollAdjustment.current = null;
                return;
            }

            setMessages((prev) => [...older, ...prev]);

            if (older.length < PAGE_SIZE) {
                setHasMore(false);
            }
        } catch (error) {
            console.warn('Failed to load older messages', error);
            pendingScrollAdjustment.current = null;
        } finally {
            if (clientIdRef.current !== currentClientId) {
                pendingScrollAdjustment.current = null;
            }
            setIsLoadingMore(false);
        }
    }, [clientId, hasMore, isLoadingMore]);

    useLayoutEffect(() => {
        if (!pendingScrollAdjustment.current) {
            return;
        }

        const container = scrollContainerRef.current;
        if (!container) {
            pendingScrollAdjustment.current = null;
            return;
        }

        const { height, top } = pendingScrollAdjustment.current;
        const newScrollHeight = container.scrollHeight;
        container.scrollTop = newScrollHeight - height + top;
        pendingScrollAdjustment.current = null;
    }, [messages]);

    useEffect(() => {
        const sentinel = topSentinelRef.current;
        const container = scrollContainerRef.current;

        if (!sentinel || !container || !hasMore) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        loadOlderMessages();
                    }
                });
            },
            { root: container, threshold: 0.1 },
        );

        observer.observe(sentinel);

        return () => observer.disconnect();
    }, [hasMore, loadOlderMessages, messages.length]);

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
        <div ref={rootRef} className="space-y-2">
            <div ref={topSentinelRef} className="h-0" />
            {isLoadingMore && (
                <div className="text-center text-xs text-gray-400">Загрузка сообщений…</div>
            )}
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
