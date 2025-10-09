// assets/chat-center/components/ClientList.tsx

import React, { useEffect, useState } from 'react';
import axios from 'axios';

type Client = {
    id: string;
    name: string;
    source: string;
    unread_count?: number;
    awaiting?: boolean;
};

type Props = {
    onSelect: (client: Client) => void;
};

const ClientList: React.FC<Props> = ({ onSelect }) => {
    const [clients, setClients] = useState<Client[]>([]);
    const [selectedId, setSelectedId] = useState<string | null>(null);

    useEffect(() => {
        axios.get('/api/clients').then((res) => setClients(res.data));
    }, []);

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
                            <div>
                                <div>{client.name || 'Без имени'}</div>
                                <div className="text-xs text-gray-500">{client.source}</div>
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
