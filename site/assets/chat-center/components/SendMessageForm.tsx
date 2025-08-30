// assets/chat-center/components/SendMessageForm.tsx
import React from 'react';
import { useForm } from 'react-hook-form';
import axios from 'axios';
import { toast } from 'react-toastify';
import ChatHints, { Suggestion } from './ChatHints';

type Props = {
    clientId: string;
    onMessageSent: () => void;
};

const SendMessageForm: React.FC<Props> = ({ clientId, onMessageSent }) => {
    const { register, handleSubmit, reset, setValue } = useForm<{ message: string }>();

    const onSubmit = async (data: { message: string }) => {
        try {
            const msg = (data.message || '').trim();
            if (!msg) return;

            // 🔧 БЭК ожидает { text: string }
            await axios.post(`/api/messages/${clientId}`, { text: msg });

            onMessageSent();
            reset();
        } catch (e: any) {
            const serverMsg = e?.response?.data?.error || e?.message || 'Не удалось отправить сообщение';
            toast.error(serverMsg);
        }
    };

    // POST /api/suggestions/{clientId} -> { suggestions: string[] }
    const loadHints = async (): Promise<Suggestion[]> => {
        const { data } = await axios.post(`/api/suggestions/${encodeURIComponent(clientId)}`);
        const arr: string[] = Array.isArray(data?.suggestions) ? data.suggestions : [];
        return arr.slice(0, 4).map((text, idx) => ({ id: String(idx), text }));
    };

    return (
        <div className="border-t p-3">
            <form onSubmit={handleSubmit(onSubmit)} className="flex gap-2">
                <input
                    {...register('message', { required: true })}
                    placeholder="Введите сообщение..."
                    className="flex-1"
                />
                <button type="submit" className="bg-blue-600 text-white px-4 py-2 rounded">
                    ➤
                </button>
            </form>

            {/* Подсказки из API под полем ввода */}
            <div className="mt-2">
                <ChatHints
                    loadSuggestions={loadHints}
                    onInsert={(text) => {
                        // Вставляем длинные тексты без отправки
                        setValue('message', text, { shouldDirty: true, shouldTouch: true });
                    }}
                />
            </div>
        </div>
    );
};

export default SendMessageForm;
