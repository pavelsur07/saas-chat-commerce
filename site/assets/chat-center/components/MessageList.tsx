// assets/chat-center/components/MessageList.tsx__

import React, { useCallback, useEffect, useState } from 'react';
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
    containerRef?: React.RefObject<HTMLDivElement | null>;
    onNewMessage?: () => void;
};

const markRead = async (clientId: string) =>
    axios.post(`/api/messages/${clientId}/read`).catch((error) => {
        console.warn('Failed to mark messages as read', error);
    });

const PAGE_SIZE = 30;

const normalizeMessage = (msg: ApiMessage): Message => ({
    id: msg.id,
    text: msg.text,
    direction: msg.direction,
    createdAt: msg.createdAt || msg.timestamp || new Date().toISOString(),
});

const MessageList: React.FC<Props> = ({ clientId, containerRef, onNewMessage }) => {
    const [messages, setMessages] = useState<Message[]>([]);
    const [hasMore, setHasMore] = useState(false);
    const [nextCursor, setNextCursor] = useState<string | null>(null);
    const [isInitialLoading, setIsInitialLoading] = useState(false);
    const [isLoadingMore, setIsLoadingMore] = useState(false);

    const fetchChunk = useCallback(
        async (before?: string) => {
            if (!clientId) {
                return { items: [], hasMore: false, nextCursor: null };
            }

            const params: Record<string, string | number> = { limit: PAGE_SIZE };
            if (before) {
                params.before = before;
            }

            const res = await axios.get(`/api/messages/${clientId}`, { params });
            const rawMessages: ApiMessage[] = res.data.messages ?? [];
            const normalized = rawMessages.map(normalizeMessage);
            const pagination = res.data.pagination ?? {};

            return {
                items: normalized,
                hasMore: Boolean(pagination.has_more),
                nextCursor: pagination.next_before_id ?? null,
            };
        },
        [clientId],
    );

    useEffect(() => {
        if (!clientId) {
            return;
        }

        let cancelled = false;

        setMessages([]);
        setHasMore(false);
        setNextCursor(null);

        const load = async () => {
            setIsInitialLoading(true);
            try {
                const result = await fetchChunk();
                if (cancelled) {
                    return;
                }

                setMessages(result.items);
                setHasMore(result.hasMore);
                setNextCursor(result.hasMore ? result.nextCursor : null);

                if (result.items.length > 0 && onNewMessage) {
                    requestAnimationFrame(() => onNewMessage());
                }
            } catch (error) {
                console.warn('Failed to load messages', error);
            } finally {
                if (!cancelled) {
                    setIsInitialLoading(false);
                }
            }
        };

        load();

        return () => {
            cancelled = true;
        };
    }, [clientId, fetchChunk, onNewMessage]);

    const loadOlder = useCallback(async () => {
        if (!clientId || !hasMore || isLoadingMore || !nextCursor) {
            return;
        }

        const container = containerRef?.current ?? null;
        const previousScrollHeight = container?.scrollHeight ?? 0;
        const previousScrollTop = container?.scrollTop ?? 0;

        setIsLoadingMore(true);
        try {
            const result = await fetchChunk(nextCursor);

            setHasMore(result.hasMore);
            setNextCursor(result.hasMore ? result.nextCursor ?? nextCursor : null);

            if (result.items.length === 0) {
                return;
            }

            setMessages((prev) => {
                const existingIds = new Set(prev.map((m) => m.id));
                const deduped = result.items.filter((m) => !existingIds.has(m.id));

                if (deduped.length === 0) {
                    return prev;
                }

                const combined = [...deduped, ...prev];

                requestAnimationFrame(() => {
                    if (container) {
                        const newHeight = container.scrollHeight;
                        container.scrollTop = newHeight - previousScrollHeight + previousScrollTop;
                    }
                });

                return combined;
            });
        } catch (error) {
            console.warn('Failed to load older messages', error);
        } finally {
            setIsLoadingMore(false);
        }
    }, [clientId, containerRef, fetchChunk, hasMore, isLoadingMore, nextCursor]);

    useEffect(() => {
        const container = containerRef?.current ?? null;
        if (!container) {
            return;
        }

        const handleScroll = () => {
            if (!hasMore || isLoadingMore) {
                return;
            }

            if (container.scrollTop <= 80) {
                loadOlder();
            }
        };

        container.addEventListener('scroll', handleScroll);

        return () => {
            container.removeEventListener('scroll', handleScroll);
        };
    }, [containerRef, hasMore, isLoadingMore, loadOlder]);

    useSocket(clientId, (payload) => {
        const incoming: Message = {
            id: payload.id || `${Date.now()}`,
            text: payload.text,
            direction: payload.direction,
            createdAt: payload.createdAt || payload.timestamp || new Date().toISOString(),
        };

        setMessages((prev) => {
            if (prev.some((m) => m.id === incoming.id)) {
                return prev;
            }

            if (onNewMessage) {
                requestAnimationFrame(() => onNewMessage());
            }

            return [...prev, incoming];
        });
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
            {hasMore && (
                <div className="text-center text-xs text-gray-500 py-2">
                    {isLoadingMore ? 'Загрузка...' : 'Потяните вверх, чтобы загрузить ещё'}
                </div>
            )}

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

            {!isInitialLoading && messages.length === 0 && (
                <div className="text-center text-sm text-gray-500 py-6">Сообщений пока нет</div>
            )}

            {isInitialLoading && (
                <div className="text-center text-sm text-gray-500 py-6">Загрузка…</div>
            )}
        </div>
    );
};

export default MessageList;
