// assets/chat-center/components/ChatLayout.tsx__

import React, { useState, useRef, useEffect } from 'react';
import ClientList from './ClientList';
import MessageList from './MessageList';
import SendMessageForm from './SendMessageForm';

type Client = {
    id: string;
    name: string;
    source: string;
};

const ChatLayout: React.FC = () => {
    const [selectedClient, setSelectedClient] = useState<Client | null>(null);
    const [reload, setReload] = useState(false);
    const bottomRef = useRef<HTMLDivElement | null>(null);

    // Скролл вниз при новых сообщениях
    useEffect(() => {
        if (bottomRef.current) {
            bottomRef.current.scrollIntoView({ behavior: 'smooth' });
        }
    }, [selectedClient, reload]);

    return (
        <div className="flex h-[85vh] bg-white border rounded-lg shadow overflow-hidden">
            {/* Клиенты */}
            <div className="w-1/3 border-r overflow-y-auto">
                <ClientList onSelect={(client) => setSelectedClient(client)} />
            </div>

            {/* Сообщения и форма */}
            <div className="flex flex-col flex-1">
                <div className="flex items-center px-4 py-3 border-b bg-gray-50">
                    <h2 className="text-lg font-semibold">
                        {selectedClient ? selectedClient.name : 'Выберите клиента'}
                    </h2>
                    <span className="ml-2 text-sm text-gray-500">
            {selectedClient?.source?.toUpperCase()}
          </span>
                </div>

                <div className="flex-1 overflow-y-auto px-4 py-2 space-y-2">
                    {selectedClient && (
                        <>
                            <MessageList clientId={selectedClient.id} />
                            <div ref={bottomRef} />
                        </>
                    )}
                </div>

                <div className="border-t p-4">
                    {selectedClient && (
                        <SendMessageForm
                            clientId={selectedClient.id}
                            onMessageSent={() => setReload(!reload)}
                        />
                    )}
                </div>
            </div>
        </div>
    );
};

export default ChatLayout;
