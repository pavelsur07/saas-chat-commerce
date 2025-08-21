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
            if (!data.message.trim()) return;
            await axios.post(`/api/messages/${clientId}`, { message: data.message });
            onMessageSent();
            reset();
        } catch (e: any) {
            toast.error(e?.message || 'Не удалось отправить сообщение');
        }
    };

    // Загружаем подсказки по нашему контракту:
    // GET /api/suggestions?clientId=... -> { suggestions: string[] }
    const loadHints = async (): Promise<Suggestion[]> => {
        const { data } = await axios.get('/api/suggestions', { params: { clientId } });
        const arr: string[] = Array.isArray(data?.suggestions) ? data.suggestions : [];
        return arr.slice(0, 4).map((text, idx) => ({ id: String(idx), text }));
    };

    return (
        <div className="border-t p-3">
            <form onSubmit={handleSubmit(onSubmit)} className="flex gap-2">
                <input
                    {...register('message', { required: true })}
                    placeholder="Введите сообщение..."
                    className="flex-1 px-4 py-2 border rounded"
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
                        // Вставляем (НЕ отправляем). Учитываем длинные тексты.
                        setValue('message', text, { shouldDirty: true, shouldTouch: true });
                    }}
                />
            </div>
        </div>
    );
};

export default SendMessageForm;
