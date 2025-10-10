import React, { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { useSocket } from '../hooks/useSocket';

type Message = {
    id: string;
    text: string;
    direction: 'in' | 'out';
    timestamp: string;
};

type ApiMessage = {
    id: string;
    text: string;
    direction: 'in' | 'out';
    timestamp?: string;
    createdAt?: string;
};

type MessagePage = {
    messages: Message[];
    page: {
        oldest_id: string | null;
        newest_id: string | null;
        has_more_top: boolean;
        has_more_bottom: boolean;
    };
    firstUnreadId: string | null;
};

const MESSAGE_LIMIT = 30;
const TOP_LOAD_THRESHOLD = 100;
const BOTTOM_LOAD_THRESHOLD = 100;
const AUTO_SCROLL_THRESHOLD = 80;

const normalizeApiMessages = (apiMessages: ApiMessage[]): Message[] =>
    apiMessages.map((msg) => ({
        id: msg.id,
        text: msg.text,
        direction: msg.direction,
        timestamp: msg.timestamp || msg.createdAt || new Date().toISOString(),
    }));

const mergeMessages = (existing: Message[], incoming: Message[], placement: 'append' | 'prepend'): Message[] => {
    if (incoming.length === 0) {
        return existing;
    }

    const knownIds = new Set(existing.map((item) => item.id));
    const filtered = incoming.filter((item) => !knownIds.has(item.id));

    if (filtered.length === 0) {
        return existing;
    }

    return placement === 'append' ? [...existing, ...filtered] : [...filtered, ...existing];
};

const isNearTop = (element: HTMLDivElement, threshold: number): boolean => element.scrollTop <= threshold;

const isNearBottom = (element: HTMLDivElement, threshold: number): boolean => {
    const distance = element.scrollHeight - element.scrollTop - element.clientHeight;
    return distance <= threshold;
};

const MessageList: React.FC<{ clientId: string; onNewMessage?: () => void }> = ({ clientId, onNewMessage }) => {
    const [items, setItems] = useState<Message[]>([]);
    const [oldestId, setOldestId] = useState<string | null>(null);
    const [newestId, setNewestId] = useState<string | null>(null);
    const [hasMoreTop, setHasMoreTop] = useState(false);
    const [hasMoreBottom, setHasMoreBottom] = useState(false);
    const [pendingNewCount, setPendingNewCount] = useState(0);
    const [isAtBottom, setIsAtBottom] = useState(true);

    const listRef = useRef<HTMLDivElement>(null);
    const loadingTopRef = useRef(false);
    const loadingBottomRef = useRef(false);

    const scrollToBottom = useCallback(() => {
        const container = listRef.current;
        if (!container) {
            return;
        }

        container.scrollTop = container.scrollHeight;
        setIsAtBottom(true);
        setPendingNewCount(0);
    }, []);

    const fetchPage = useCallback(
        async (params: Record<string, string | number | undefined>): Promise<MessagePage | null> => {
            if (!clientId) {
                return null;
            }

            const response = await axios.get(`/api/messages/${clientId}`, {
                params: {
                    limit: MESSAGE_LIMIT,
                    ...params,
                },
            });

            const rawPage = response.data.page ?? {};

            return {
                messages: normalizeApiMessages(response.data.messages ?? []),
                page: {
                    oldest_id: rawPage.oldest_id ?? null,
                    newest_id: rawPage.newest_id ?? null,
                    has_more_top: Boolean(rawPage.has_more_top),
                    has_more_bottom: Boolean(rawPage.has_more_bottom),
                },
                firstUnreadId: response.data.first_unread_id ?? null,
            };
        },
        [clientId],
    );

    useEffect(() => {
        setItems([]);
        setOldestId(null);
        setNewestId(null);
        setHasMoreTop(false);
        setHasMoreBottom(false);
        setPendingNewCount(0);
        setIsAtBottom(true);
        loadingTopRef.current = false;
        loadingBottomRef.current = false;

        if (!clientId) {
            return;
        }

        let isCancelled = false;

        const loadInitial = async () => {
            try {
                const initial = await fetchPage({});
                if (!initial || isCancelled) {
                    return;
                }

                let normalized = initial.messages;
                let oldest = initial.page.oldest_id ?? (normalized[0]?.id ?? null);
                let newest = initial.page.newest_id ?? (normalized[normalized.length - 1]?.id ?? null);
                let moreTop = initial.page.has_more_top;
                let moreBottom = initial.page.has_more_bottom;
                const firstUnread = initial.firstUnreadId;

                if (firstUnread) {
                    const present = normalized.some((message) => message.id === firstUnread);
                    if (!present) {
                        const extra = await fetchPage({ before_id: firstUnread });
                        if (!extra || isCancelled) {
                            return;
                        }

                        normalized = [...extra.messages, ...normalized];
                        oldest = extra.page.oldest_id ?? (normalized[0]?.id ?? oldest);
                        moreTop = extra.page.has_more_top;
                    }
                }

                if (isCancelled) {
                    return;
                }

                setItems(normalized);
                setOldestId(oldest ?? null);
                setNewestId(newest ?? null);
                setHasMoreTop(moreTop);
                setHasMoreBottom(moreBottom);
                setPendingNewCount(0);

                requestAnimationFrame(() => {
                    if (isCancelled) {
                        return;
                    }

                    const container = listRef.current;
                    if (!container) {
                        return;
                    }

                    if (firstUnread) {
                        const target = container.querySelector<HTMLElement>(`[data-message-id="${firstUnread}"]`);
                        if (target) {
                            const offset = target.offsetTop - container.offsetTop;
                            container.scrollTop = Math.max(offset - 80, 0);
                            setIsAtBottom(isNearBottom(container, AUTO_SCROLL_THRESHOLD));
                        } else {
                            scrollToBottom();
                        }
                    } else {
                        scrollToBottom();
                        onNewMessage && onNewMessage();
                    }
                });
            } catch (error) {
                console.error('Failed to load messages', error);
            }
        };

        loadInitial();

        return () => {
            isCancelled = true;
        };
    }, [clientId, fetchPage, onNewMessage, scrollToBottom]);

    const loadBefore = useCallback(
        async (anchorId: string) => {
            if (!clientId || !anchorId || loadingTopRef.current) {
                return;
            }

            try {
                loadingTopRef.current = true;

                const container = listRef.current;
                const previousHeight = container?.scrollHeight ?? 0;
                const previousTop = container?.scrollTop ?? 0;

                const result = await fetchPage({ before_id: anchorId });
                if (!result) {
                    return;
                }

                const newMessages = result.messages;
                let nextOldestId = result.page.oldest_id;

                setItems((prev) => {
                    const merged = mergeMessages(prev, newMessages, 'prepend');
                    if (!nextOldestId && merged.length > 0) {
                        nextOldestId = merged[0].id;
                    }
                    return merged;
                });

                setOldestId((prev) => nextOldestId ?? prev);
                setHasMoreTop(result.page.has_more_top);

                requestAnimationFrame(() => {
                    if (container) {
                        const newHeight = container.scrollHeight;
                        container.scrollTop = previousTop + (newHeight - previousHeight);
                    }
                });
            } catch (error) {
                console.error('Failed to load older messages', error);
            } finally {
                loadingTopRef.current = false;
            }
        },
        [clientId, fetchPage],
    );

    const loadAfter = useCallback(
        async (anchorId: string) => {
            if (!clientId || !anchorId || loadingBottomRef.current) {
                return;
            }

            try {
                loadingBottomRef.current = true;
                const container = listRef.current;
                const wasNearBottom = container ? isNearBottom(container, BOTTOM_LOAD_THRESHOLD) : false;

                const result = await fetchPage({ after_id: anchorId });
                if (!result) {
                    return;
                }

                const newMessages = result.messages;
                let nextNewestId = result.page.newest_id;

                setItems((prev) => {
                    const merged = mergeMessages(prev, newMessages, 'append');
                    if (!nextNewestId && merged.length > 0) {
                        nextNewestId = merged[merged.length - 1].id;
                    }
                    return merged;
                });

                setNewestId((prev) => nextNewestId ?? prev ?? anchorId);
                setHasMoreBottom(result.page.has_more_bottom);
                setHasMoreTop(result.page.has_more_top);

                if (wasNearBottom) {
                    requestAnimationFrame(() => {
                        scrollToBottom();
                    });
                }
            } catch (error) {
                console.error('Failed to load newer messages', error);
            } finally {
                loadingBottomRef.current = false;
            }
        },
        [clientId, fetchPage, scrollToBottom],
    );

    const handleScroll = useCallback(
        (event: React.UIEvent<HTMLDivElement>) => {
            const target = event.currentTarget;

            if (hasMoreTop && oldestId && isNearTop(target, TOP_LOAD_THRESHOLD)) {
                loadBefore(oldestId);
            }

            if (isNearBottom(target, BOTTOM_LOAD_THRESHOLD)) {
                setIsAtBottom(true);
                setPendingNewCount(0);
                if (hasMoreBottom && newestId) {
                    loadAfter(newestId);
                }
            } else {
                setIsAtBottom(false);
            }
        },
        [hasMoreTop, hasMoreBottom, oldestId, newestId, loadBefore, loadAfter],
    );

    useSocket(clientId, (payload) => {
        const incoming: Message = {
            id: payload.id || `${Date.now()}`,
            text: payload.text,
            direction: payload.direction,
            timestamp: payload.createdAt || payload.timestamp || new Date().toISOString(),
        };

        let appended = false;
        setItems((prev) => {
            if (prev.some((item) => item.id === incoming.id)) {
                return prev;
            }
            appended = true;
            return [...prev, incoming];
        });

        if (!appended) {
            return;
        }

        setOldestId((prev) => prev ?? incoming.id);
        setNewestId(incoming.id);

        const container = listRef.current;
        if (container && isNearBottom(container, AUTO_SCROLL_THRESHOLD)) {
            requestAnimationFrame(() => {
                scrollToBottom();
                onNewMessage && onNewMessage();
            });
        } else {
            setIsAtBottom(false);
            setPendingNewCount((count) => count + 1);
        }
    });

    useEffect(() => {
        if (!clientId || !isAtBottom || items.length === 0) {
            return;
        }

        const timeout = window.setTimeout(() => {
            markRead(clientId);
        }, 500);

        return () => window.clearTimeout(timeout);
    }, [clientId, isAtBottom, items]);

    const handleNewMessagesClick = useCallback(() => {
        scrollToBottom();
        onNewMessage && onNewMessage();
    }, [onNewMessage, scrollToBottom]);

    return (
        <div className="relative flex h-full flex-col">
            <div
                ref={listRef}
                onScroll={handleScroll}
                className="flex-1 space-y-2 overflow-y-auto pr-2"
            >
                {items.map((msg) => (
                    <div
                        key={msg.id}
                        data-message-id={msg.id}
                        className={`flex ${msg.direction === 'out' ? 'justify-end' : 'justify-start'}`}
                    >
                        <div
                            className={`max-w-xs rounded-lg px-4 py-2 ${
                                msg.direction === 'in' ? 'bg-gray-200 text-gray-900' : 'bg-blue-500 text-white'
                            }`}
                        >
                            <div>{msg.text}</div>
                            <div className="mt-1 text-right text-xs opacity-70">
                                {new Date(msg.timestamp).toLocaleString()}
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {pendingNewCount > 0 && (
                <button
                    type="button"
                    onClick={handleNewMessagesClick}
                    className="absolute bottom-4 left-1/2 -translate-x-1/2 transform rounded-full bg-blue-500 px-4 py-2 text-white shadow"
                >
                    Новые сообщения ({pendingNewCount})
                </button>
            )}
        </div>
    );
};

const markRead = async (clientId: string) =>
    axios.post(`/api/messages/${clientId}/read`).catch((error) => {
        console.warn('Failed to mark messages as read', error);
    });

export default MessageList;
