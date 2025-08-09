// assets/chat-center/components/MessageList.tsx
import React, { useEffect, useState } from 'react';
import axios from 'axios';

type Message = {
    id: string;
    text: string;
    direction: 'in' | 'out';
    createdAt: string;
};

type Props = {
    clientId: string;
    reload: boolean; // ⬅️ добавили
};

const MessageList: React.FC<Props> = ({ clientId, reload }) => {
    const [messages, setMessages] = useState<Message[]>([]);

    useEffect(() => {
        if (!clientId) return;
        axios.get(`/api/messages/${clientId}`).then((res) => {
            setMessages(res.data?.messages ?? []);
        });
    }, [clientId, reload]); // ⬅️ учитываем reload

    return (
        <div className="flex flex-col space-y-2">
            {messages.map((msg) => (
                <div
                    key={msg.id}
                    className={`max-w-xs px-4 py-2 rounded-lg ${
                        msg.direction === 'in'
                            ? 'bg-gray-200 self-start ml-2'
                            : 'bg-blue-500 text-white self-end mr-2'
                    }`}
                >
                    <div>{msg.text}</div>
                    <div className="text-xs text-right mt-1 opacity-70">
                        {new Date(msg.createdAt).toLocaleTimeString()}
                    </div>
                </div>
            ))}
        </div>
    );
};

export default MessageList;
