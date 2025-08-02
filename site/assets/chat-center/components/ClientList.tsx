// assets/chat-center/components/ClientList.tsx

import React, { useEffect, useState } from 'react';
import axios from 'axios';

type Client = {
    id: string;
    name: string;
    source: string;
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
            {clients.map((client) => (
                <button
                    key={client.id}
                    onClick={() => {
                        setSelectedId(client.id);
                        onSelect(client);
                    }}
                    className={`w-full text-left p-3 hover:bg-gray-100 ${
                        selectedId === client.id ? 'bg-gray-100 font-semibold' : ''
                    }`}
                >
                    <div>{client.name || 'Без имени'}</div>
                    <div className="text-xs text-gray-500">{client.source}</div>
                </button>
            ))}
        </div>
    );
};

export default ClientList;
